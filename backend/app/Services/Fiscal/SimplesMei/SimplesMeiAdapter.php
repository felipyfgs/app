<?php

namespace App\Services\Fiscal\SimplesMei;

use App\Contracts\FiscalSourceAdapter;
use App\DTO\Fiscal\FiscalAdapterRequest;
use App\DTO\Fiscal\FiscalAdapterResult;
use App\DTO\Fiscal\SimplesMei\SimplesMeiOperationDef;
use App\DTO\Serpro\MutationAuthorization;
use App\Enums\FiscalCoverage;
use App\Enums\FiscalMutability;
use App\Enums\SerproEnvironment;
use App\Services\Fiscal\SimplesMei\Pgdasd\PgdasdConsDeclaracao13Codec;
use App\Services\Fiscal\SimplesMei\Pgdasd\PgdasdDocumentCodecs;
use App\Services\Fiscal\SimplesMei\Pgdasd\PgdasdPeriod;
use App\Services\Fiscal\SimplesMei\Pgdasd\PgdasdPostConsultService;
use App\Services\Integra\ContributorCnpjResolver;
use App\Services\Integra\IntegraEligibilityService;
use App\Services\Integra\OfficeSerproAuthorizationService;
use App\Services\Serpro\Catalog\OperationKeyMap;
use App\Services\Serpro\SerproContractService;
use App\Services\Serpro\SerproOperationService;
use App\Support\FeatureFlags;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Adapter genérico por definição do catálogo Simples/MEI.
 */
final class SimplesMeiAdapter implements FiscalSourceAdapter
{
    public function __construct(
        private readonly SimplesMeiOperationDef $definition,
        private readonly IntegraEligibilityService $eligibility,
        private readonly SerproOperationService $operations,
        private readonly SimplesMeiResponseMapper $mapper,
        private readonly SerproContractService $contracts,
        private readonly OfficeSerproAuthorizationService $authorizations,
        private readonly RegimeApplicabilityService $regimeApplicability,
        private readonly DasGuideHookService $dasGuideHook,
        private readonly ContributorCnpjResolver $contributors,
        private readonly PgdasdConsDeclaracao13Codec $pgdasdCodec13,
        private readonly PgdasdDocumentCodecs $pgdasdDocumentCodecs,
        private readonly PgdasdPostConsultService $pgdasdPostConsult,
    ) {}

    public function systemCode(): string
    {
        return $this->definition->systemCode;
    }

    public function serviceCode(): string
    {
        return $this->definition->serviceCode;
    }

    public function operationCode(): string
    {
        return $this->definition->operationCode;
    }

    public function mutability(): FiscalMutability
    {
        // GERAR_DAS assistido no piloto: expõe READ_ONLY ao núcleo para permitir stub
        // local sem chamada externa; TRANSMITIR permanece MUTATING e é bloqueado.
        if (
            strtoupper($this->definition->operationCode) === 'GERAR_DAS'
            && (bool) config('fiscal_monitoring.simples_mei.das_stub_without_mutating', true)
            && ! FeatureFlags::isMutatingEnabled(SimplesMeiCatalog::MODULE)
            && ! (bool) config('fiscal_monitoring.mutating_enabled', false)
        ) {
            return FiscalMutability::ReadOnly;
        }

        return $this->definition->mutability;
    }

    public function coverage(): FiscalCoverage
    {
        return $this->definition->coverage;
    }

    public function moduleKey(): ?string
    {
        return SimplesMeiCatalog::MODULE;
    }

    public function supports(FiscalAdapterRequest $request): bool
    {
        return strcasecmp($request->systemCode, $this->definition->systemCode) === 0
            && strcasecmp($request->serviceCode, $this->definition->serviceCode) === 0
            && strcasecmp($request->operationCode, $this->definition->operationCode) === 0;
    }

    public function execute(FiscalAdapterRequest $request): FiscalAdapterResult
    {
        $def = $this->definition;
        $office = $request->office;
        $client = $request->client;

        // 1. Feature module
        if (! FeatureFlags::isModuleEnabled(SimplesMeiCatalog::MODULE, $office->id)
            && ! (bool) config('fiscal_monitoring.enabled', false)) {
            return FiscalAdapterResult::blocked('Módulo simples_mei desabilitado.', 'FEATURE_DISABLED');
        }

        // 2. Mutantes (transmissão / emissão) — bloqueio no piloto
        if ($def->mutability->isMutating()) {
            $mutatingOk = FeatureFlags::isMutatingEnabled(SimplesMeiCatalog::MODULE, $office->id)
                && (bool) config('fiscal_monitoring.mutating_enabled', false);

            // GERAR_DAS pode ser liberado via flag guias + simples_mei mutating parcial:
            // no piloto, só aceita se mutating_enabled; senão bloqueia e audita via result.
            if (! $mutatingOk) {
                // Para GERAR_DAS assistido, ainda podemos gravar stub sem chamar SERPRO se hook permitir
                if (strtoupper($def->operationCode) === 'GERAR_DAS'
                    && (bool) config('fiscal_monitoring.simples_mei.das_stub_without_mutating', true)) {
                    return $this->dasGuideHook->createStubWithoutExternalCall($request, $def);
                }

                return FiscalAdapterResult::blocked(
                    'Operação mutante desabilitada no piloto somente leitura.',
                    'MUTATING_DISABLED',
                );
            }
        }

        // 3. Regime: não misturar SN e MEI
        $periodKey = $request->competence?->period_key
            ?? (string) ($request->context['period_key'] ?? '');
        $regimeCheck = $this->regimeApplicability->assertOperationApplicable(
            $office,
            $client,
            $def,
            $periodKey !== '' ? $periodKey : null,
        );
        if ($regimeCheck !== null) {
            return $regimeCheck;
        }

        // 4. Ambiente + elegibilidade (procuração, termo, catálogo SERPRO)
        $env = SerproEnvironment::tryFrom((string) config('serpro.default_environment', 'TRIAL'))
            ?? SerproEnvironment::Trial;

        $eligibilityOp = $this->resolveCatalogOperation($def);
        $elig = $this->eligibility->evaluate(
            $office,
            $client,
            $def->systemCode,
            $def->serviceCode,
            $eligibilityOp,
            $env,
            null,
            SimplesMeiCatalog::MODULE,
        );

        if (! $elig->eligible) {
            $code = $elig->primaryCode()->value;

            return FiscalAdapterResult::blocked(
                'Elegibilidade Integra negada: '.$code,
                $code,
            );
        }

        $this->eligibility->touchRateLimit($office->id);

        $contract = $this->contracts->activeFor($env);
        if ($contract === null || ! $contract->isUsable()) {
            return FiscalAdapterResult::blocked('Contrato SERPRO indisponível.', 'CONTRACT_UNAVAILABLE');
        }

        // Autor/contribuinte resolvidos no executor; pre-check de identidade do autor
        $auth = $this->authorizations->getOrCreate($office, $env);
        if (trim((string) ($auth->author_identity ?? '')) === '') {
            return FiscalAdapterResult::blocked('Autor do Pedido não configurado.', 'AUTHOR_IDENTITY_MISSING');
        }
        try {
            $this->contributors->resolve($client);
        } catch (\Throwable) {
            return FiscalAdapterResult::blocked('CNPJ completo do contribuinte não encontrado.', 'CONTRIBUTOR_IDENTITY_MISSING');
        }

        $correlationId = $request->run->correlation_id ?? (string) Str::uuid();
        $idempotencyKey = 'sm:'.$request->run->idempotency_key.':'.$def->operationCode;

        try {
            $operationKey = OperationKeyMap::require(
                null,
                $def->systemCode,
                $def->serviceCode,
                $this->mapExternalOperation($def),
            );
            $payload = $this->buildPayload($request, $periodKey, $operationKey);
        } catch (InvalidArgumentException $e) {
            return FiscalAdapterResult::failed($e->getMessage(), 'INVALID_PAYLOAD');
        } catch (\Throwable $e) {
            return FiscalAdapterResult::failed(
                'Falha ao montar payload da operação.',
                'PAYLOAD_BUILD_ERROR',
            );
        }

        try {
            $response = $this->operations->execute(
                office: $office,
                client: $client,
                operationKey: $operationKey,
                businessData: $payload,
                idempotencyKey: $idempotencyKey,
                correlationId: $correlationId,
                mutationAuth: MutationAuthorization::none(),
                module: SimplesMeiCatalog::MODULE,
            );
        } catch (\Throwable) {
            return FiscalAdapterResult::failed(
                'Falha de transporte Integra Contador.',
                'TRANSPORT_ERROR',
            );
        }

        $result = $this->mapper->map($def, $response, $periodKey);

        // PGDAS-D 13–16: projeção + sanitização documental
        if (strtoupper($def->serviceCode) === 'PGDASD'
            && str_starts_with($operationKey, 'pgdasd.')
            && $result->result->value === 'SUCCESS') {
            $post = $this->pgdasdPostConsult->handle($request, $response, $result, $operationKey);
            $result = $post['result'];
        }

        // Projeções laterais (regime / guia) sem alterar evidência
        if ($result->result->value === 'SUCCESS' && is_array($result->normalized)) {
            if (strtoupper($def->serviceCode) === 'REGIME_APURACAO') {
                $this->regimeApplicability->projectFromNormalized(
                    $office,
                    $client,
                    $result->normalized,
                    $request->run->id,
                );
            }

            if (strtoupper($def->operationCode) === 'GERAR_DAS') {
                $this->dasGuideHook->persistFromAdapterResult($request, $def, $result);
            }

            if (in_array(strtoupper($def->serviceCode), ['PGDASD', 'DEFIS', 'PGMEI', 'DASN_SIMEI'], true)
                && strtoupper($def->operationCode) !== 'TRANSMITIR'
                && strtoupper($def->operationCode) !== 'GERAR_DAS') {
                $this->regimeApplicability->projectCompetenceSituation(
                    $office,
                    $client,
                    $def,
                    $periodKey,
                    $result->situation,
                    $result->coverage,
                    $result->normalized,
                );
            }
        }

        return $result;
    }

    /**
     * MONITOR mapeia para a consulta principal catalogada no SERPRO.
     */
    private function resolveCatalogOperation(SimplesMeiOperationDef $def): string
    {
        if (! $def->isMonitor) {
            return $def->operationCode;
        }

        return match (strtoupper($def->serviceCode)) {
            'PGDASD' => 'CONSULTAR_DECLARACAO',
            'DEFIS', 'REGIME_APURACAO', 'PGMEI', 'CCMEI', 'DASN_SIMEI' => 'CONSULTAR',
            default => $def->operationCode,
        };
    }

    private function mapExternalOperation(SimplesMeiOperationDef $def): string
    {
        return $this->resolveCatalogOperation($def);
    }

    /**
     * Payload oficial por operation_key. PGDAS-D 13 usa XOR ano/PA.
     *
     * @return array<string, mixed>
     */
    private function buildPayload(FiscalAdapterRequest $request, string $periodKey, string $operationKey): array
    {
        if (str_starts_with($operationKey, 'pgdasd.')) {
            return $this->buildPgdasdPayload($request, $periodKey, $operationKey);
        }

        $payload = [
            'periodo' => $periodKey,
            'competencia' => $periodKey,
        ];

        foreach (['ano', 'year', 'scenario', 'force_status'] as $key) {
            if (array_key_exists($key, $request->context)) {
                $payload[$key] = $request->context[$key];
            } elseif (array_key_exists($key, $request->progress)) {
                $payload[$key] = $request->progress[$key];
            }
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPgdasdPayload(FiscalAdapterRequest $request, string $periodKey, string $operationKey): array
    {
        $ctx = $request->context;
        $progress = $request->progress;

        return match ($operationKey) {
            'pgdasd.consdeclaracao' => $this->buildConsDeclaracao13Payload($request, $periodKey),
            'pgdasd.consultimadecrec' => $this->pgdasdDocumentCodecs->buildPayload14(
                (string) ($ctx['periodoApuracao']
                    ?? $progress['periodo_apuracao']
                    ?? ($periodKey !== '' ? PgdasdPeriod::periodoApuracaoFromPeriodKey($periodKey) : ''))
            ),
            'pgdasd.consdecrec' => $this->pgdasdDocumentCodecs->buildPayload15(
                (string) ($ctx['numeroDeclaracao'] ?? $progress['numero_declaracao'] ?? '')
            ),
            'pgdasd.consextrato' => $this->pgdasdDocumentCodecs->buildPayload16(
                (string) ($ctx['numeroDas'] ?? $progress['numero_das'] ?? '')
            ),
            default => [
                'periodo' => $periodKey,
                'competencia' => $periodKey,
            ],
        };
    }

    /**
     * @return array<string, string>
     */
    private function buildConsDeclaracao13Payload(FiscalAdapterRequest $request, string $periodKey): array
    {
        $ctx = $request->context;
        $progress = $request->progress;

        $ano = isset($ctx['anoCalendario'])
            ? (string) $ctx['anoCalendario']
            : (isset($progress['ano_calendario']) ? (string) $progress['ano_calendario'] : null);
        $pa = isset($ctx['periodoApuracao'])
            ? (string) $ctx['periodoApuracao']
            : (isset($progress['periodo_apuracao']) ? (string) $progress['periodo_apuracao'] : null);

        // Scheduler congela PA esperado e consulta o ANO do PA (serviço 13 anual).
        if (($ano === null || $ano === '') && ($pa === null || $pa === '')) {
            $expected = $progress['expected_periodo_apuracao']
                ?? $ctx['expected_periodo_apuracao']
                ?? null;
            if (is_string($expected) && preg_match('/^\d{6}$/', $expected) === 1) {
                $ano = substr($expected, 0, 4);
            } elseif ($periodKey !== '') {
                try {
                    $parsed = PgdasdPeriod::parse($periodKey);
                    $ano = PgdasdPeriod::yearFromPa($parsed);
                } catch (\Throwable) {
                    $ano = null;
                }
            } else {
                $tz = (string) ($request->office->timezone ?? 'America/Sao_Paulo') ?: 'America/Sao_Paulo';
                $expectedPa = PgdasdPeriod::expectedPa(null, $tz);
                $ano = PgdasdPeriod::yearFromPa($expectedPa);
            }
        }

        // Se ambos vierem, rejeita (XOR). Se só PA, usa PA. Se só ano, usa ano.
        return $this->pgdasdCodec13->buildPayload(
            ($ano !== null && $ano !== '') ? $ano : null,
            ($pa !== null && $pa !== '') ? $pa : null,
        );
    }
}

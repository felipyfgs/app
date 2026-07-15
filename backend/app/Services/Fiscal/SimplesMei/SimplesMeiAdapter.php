<?php

namespace App\Services\Fiscal\SimplesMei;

use App\Contracts\FiscalSourceAdapter;
use App\Contracts\IntegraContadorClient;
use App\DTO\Fiscal\FiscalAdapterRequest;
use App\DTO\Fiscal\FiscalAdapterResult;
use App\DTO\Fiscal\SimplesMei\SimplesMeiOperationDef;
use App\DTO\Serpro\IntegraRequest;
use App\Enums\FiscalCoverage;
use App\Enums\FiscalMutability;
use App\Enums\SerproEnvironment;
use App\Enums\SerproUsageResult;
use App\Models\Client;
use App\Models\Office;
use App\Services\Integra\IntegraEligibilityService;
use App\Services\Integra\OfficeSerproAuthorizationService;
use App\Services\Serpro\SerproContractService;
use App\Services\Serpro\Usage\UsageLedgerService;
use App\Services\Serpro\Usage\UsageReserveRequest;
use App\Support\FeatureFlags;
use Illuminate\Support\Str;

/**
 * Adapter genérico por definição do catálogo Simples/MEI.
 */
final class SimplesMeiAdapter implements FiscalSourceAdapter
{
    public function __construct(
        private readonly SimplesMeiOperationDef $definition,
        private readonly IntegraEligibilityService $eligibility,
        private readonly UsageLedgerService $ledger,
        private readonly SimplesMeiResponseMapper $mapper,
        private readonly SerproContractService $contracts,
        private readonly OfficeSerproAuthorizationService $authorizations,
        private readonly RegimeApplicabilityService $regimeApplicability,
        private readonly DasGuideHookService $dasGuideHook,
    ) {}

    private function integra(): IntegraContadorClient
    {
        // Resolve no execute para respeitar rebind de testes / fake trial
        return app(IntegraContadorClient::class);
    }

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

        $auth = $this->authorizations->getOrCreate($office, $env);
        $contributor = $this->resolveContributorCnpj($client);
        $authorIdentity = (string) ($auth->author_identity ?? '');

        $correlationId = $request->run->correlation_id ?? (string) Str::uuid();
        $idempotencyKey = 'sm:'.$request->run->idempotency_key.':'.$def->operationCode;

        $reserve = $this->ledger->reserve(new UsageReserveRequest(
            officeId: (int) $office->id,
            idempotencyKey: $idempotencyKey,
            systemCode: $def->systemCode,
            serviceCode: $def->serviceCode,
            operationCode: $def->operationCode,
            quantity: 1,
            clientId: (int) $client->id,
            contributorRef: substr(hash('sha256', $contributor), 0, 16),
            correlationId: $correlationId,
        ));

        if (! $reserve->allowed) {
            return FiscalAdapterResult::blocked(
                'Orçamento SERPRO bloqueou a operação.',
                'BUDGET_EXCEEDED',
            );
        }

        $payload = $this->buildPayload($request, $periodKey);

        try {
            $response = $this->integra()->execute(new IntegraRequest(
                officeId: (int) $office->id,
                clientId: (int) $client->id,
                environment: $env->value,
                solutionCode: $def->systemCode,
                serviceCode: $def->serviceCode,
                operationCode: $this->mapExternalOperation($def),
                contractorCnpj: (string) $contract->contractor_cnpj,
                authorIdentity: $authorIdentity,
                contributorCnpj: $contributor,
                payload: $payload,
                idempotencyKey: $idempotencyKey,
                correlationId: $correlationId,
            ));
        } catch (\Throwable $e) {
            $this->ledger->finalize(
                $reserve->reservation,
                SerproUsageResult::TransportError,
            );

            return FiscalAdapterResult::failed(
                'Falha de transporte Integra Contador.',
                'TRANSPORT_ERROR',
            );
        }

        $usageResult = $response->success
            ? SerproUsageResult::Success
            : ($response->httpStatus >= 500
                ? SerproUsageResult::HttpError
                : SerproUsageResult::ClientError);

        $this->ledger->finalize(
            $reserve->reservation,
            $usageResult,
            latencyMs: $response->latencyMs,
            httpStatus: $response->httpStatus,
        );

        $result = $this->mapper->map($def, $response, $periodKey);

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
     * @return array<string, mixed>
     */
    private function buildPayload(FiscalAdapterRequest $request, string $periodKey): array
    {
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

    private function resolveContributorCnpj(Client $client): string
    {
        $matrix = $client->establishments()
            ->where('is_matrix', true)
            ->first();

        if ($matrix !== null && is_string($matrix->cnpj) && strlen($matrix->cnpj) === 14) {
            return strtoupper($matrix->cnpj);
        }

        $any = $client->establishments()->first();
        if ($any !== null && is_string($any->cnpj) && strlen($any->cnpj) === 14) {
            return strtoupper($any->cnpj);
        }

        $root = strtoupper((string) $client->root_cnpj);
        if (strlen($root) === 8) {
            return $root.'0001'.'00'; // fallback sintético só para envelope trial — tests usam establishment
        }

        return $root !== '' ? $root : '00000000000000';
    }
}

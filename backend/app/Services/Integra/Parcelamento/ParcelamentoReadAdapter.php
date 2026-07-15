<?php

namespace App\Services\Integra\Parcelamento;

use App\Contracts\FiscalSourceAdapter;
use App\DTO\Fiscal\FiscalAdapterRequest;
use App\DTO\Fiscal\FiscalAdapterResult;
use App\Enums\FiscalCoverage;
use App\Enums\FiscalFindingSeverity;
use App\Enums\FiscalMutability;
use App\Enums\FiscalRunResult;
use App\Enums\FiscalSituation;
use App\Enums\TaxInstallmentModality;
use App\Services\Integra\TaxProxyPowerService;
use App\Support\FeatureFlags;

/**
 * Adapter somente-leitura para modalidades catalogadas de parcelamento SN/MEI.
 * service_code = modalidade (PARCSN, PARCMEI, …).
 */
final class ParcelamentoReadAdapter implements FiscalSourceAdapter
{
    public function __construct(
        private readonly FakeParcelamentoSource $source,
        private readonly ParcelamentoProjectionService $projection,
        private readonly TaxProxyPowerService $proxyPowers,
    ) {}

    public function systemCode(): string
    {
        return ParcelamentoServiceCatalog::SOLUTION;
    }

    public function serviceCode(): string
    {
        // Wildcard — supports() filtra modalidades
        return '*';
    }

    public function operationCode(): string
    {
        return '*';
    }

    public function mutability(): FiscalMutability
    {
        return FiscalMutability::ReadOnly;
    }

    public function coverage(): FiscalCoverage
    {
        return FiscalCoverage::Full;
    }

    public function moduleKey(): ?string
    {
        return ParcelamentoServiceCatalog::MODULE_KEY;
    }

    public function supports(FiscalAdapterRequest $request): bool
    {
        if (strcasecmp($request->systemCode, ParcelamentoServiceCatalog::SOLUTION) !== 0) {
            return false;
        }

        $modality = ParcelamentoServiceCatalog::parseModality($request->serviceCode);
        if ($modality === null) {
            return false;
        }

        $op = strtoupper($request->operationCode);

        return ParcelamentoServiceCatalog::isReadOperation($op);
    }

    public function execute(FiscalAdapterRequest $request): FiscalAdapterResult
    {
        $modality = ParcelamentoServiceCatalog::parseModality($request->serviceCode);
        if ($modality === null) {
            return FiscalAdapterResult::unsupported(
                "Modalidade de parcelamento desconhecida: {$request->serviceCode}"
            );
        }

        if (! FeatureFlags::isModuleEnabled(ParcelamentoServiceCatalog::MODULE_KEY, $request->office->id)
            && ! (bool) config('fiscal_monitoring.enabled', false)) {
            return FiscalAdapterResult::blocked(
                'Módulo parcelamentos desabilitado.',
                'FEATURE_DISABLED',
            );
        }

        $powerBlock = $this->assertPower($request, $modality);
        if ($powerBlock !== null) {
            return $powerBlock;
        }

        $operation = strtoupper($request->operationCode);
        $payload = is_array($request->context['payload'] ?? null)
            ? $request->context['payload']
            : [];

        // MONITOR consolida pedidos + parcelas + amostra de pagamento
        if ($operation === 'MONITOR') {
            return $this->executeMonitor($request, $modality, $payload);
        }

        $response = $this->source->execute($modality, $operation, $payload);
        if (! $response['success']) {
            return FiscalAdapterResult::failed(
                $response['error_message'] ?? 'Falha na consulta de parcelamento',
                $response['error_code'] ?? 'PARCELAMENTO_SOURCE_FAILED',
                FiscalCoverage::Full,
            );
        }

        $body = $response['body'];
        $evidence = json_encode($body, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        $sha = hash('sha256', $evidence);

        $projected = $this->projection->projectFromMonitorBody(
            $request->office,
            $request->client,
            $modality,
            $this->enrichBodyForProjection($modality, $operation, $body, $payload),
            $request->run,
            $sha,
        );

        return new FiscalAdapterResult(
            result: FiscalRunResult::Success,
            situation: $projected['situation'],
            coverage: FiscalCoverage::Full,
            evidenceBytes: $evidence,
            evidenceContentType: 'application/json',
            sourceVersion: 'parcelamento-fake-1',
            normalized: [
                'modality' => $modality->value,
                'regime' => $modality->regime(),
                'operation' => $operation,
                'orders' => array_map(
                    fn ($o) => $o->toPublicArray(),
                    $projected['orders'],
                ),
                'parcel_count' => count($projected['parcels']),
                'payment_count' => count($projected['payments']),
                'simulated' => true,
            ],
            findings: $projected['findings'],
            itemsProcessed: count($projected['orders']) + count($projected['parcels']),
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function executeMonitor(
        FiscalAdapterRequest $request,
        TaxInstallmentModality $modality,
        array $payload,
    ): FiscalAdapterResult {
        $pedidosResp = $this->source->execute($modality, 'CONSULTAR_PEDIDOS', $payload);
        if (! $pedidosResp['success']) {
            return FiscalAdapterResult::failed(
                $pedidosResp['error_message'] ?? 'Falha ao listar pedidos',
                $pedidosResp['error_code'] ?? 'PARCELAMENTO_PEDIDOS_FAILED',
            );
        }

        $pedidos = $pedidosResp['body']['pedidos'] ?? [];
        $detalhes = [];
        $pagamentos = [];
        $parcelasMerged = [];

        foreach ($pedidos as $pedido) {
            $numero = (string) ($pedido['numero'] ?? '');
            if ($numero === '') {
                continue;
            }

            $det = $this->source->execute($modality, 'CONSULTAR_PARCELAMENTO', [
                'numeroParcelamento' => $numero,
            ]);
            if ($det['success']) {
                $detalhes[$numero] = $det['body'];
                foreach ($det['body']['parcelas'] ?? [] as $p) {
                    $parcelasMerged[] = $p;
                }
            }

            $paraGerar = $this->source->execute($modality, 'CONSULTAR_PARCELAS', [
                'numeroParcelamento' => $numero,
            ]);
            if ($paraGerar['success']) {
                foreach ($paraGerar['body']['parcelasParaGerar'] ?? [] as $p) {
                    $parcelasMerged[] = $p;
                }
            }

            // Pagamento da parcela mais antiga do detalhe (quando houver)
            foreach ($det['body']['parcelas'] ?? [] as $p) {
                $key = (string) ($p['parcela'] ?? '');
                if ($key === '') {
                    continue;
                }
                $pag = $this->source->execute($modality, 'CONSULTAR_PAGAMENTO', [
                    'numeroParcelamento' => $numero,
                    'anoMesParcela' => $key,
                ]);
                if ($pag['success']) {
                    $pagamentos[$key] = $pag['body'];
                }
            }
        }

        $consolidated = [
            'pedidos' => $pedidos,
            'parcelas' => $this->uniqueParcels($parcelasMerged),
            'pagamentos' => $pagamentos,
            'detalhes' => $detalhes,
            'modality' => $modality->value,
            'regime' => $modality->regime(),
        ];

        $evidence = json_encode($consolidated, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        $sha = hash('sha256', $evidence);

        $projected = $this->projection->projectFromMonitorBody(
            $request->office,
            $request->client,
            $modality,
            $consolidated,
            $request->run,
            $sha,
        );

        return new FiscalAdapterResult(
            result: FiscalRunResult::Success,
            situation: $projected['situation'],
            coverage: FiscalCoverage::Full,
            evidenceBytes: $evidence,
            evidenceContentType: 'application/json',
            sourceVersion: 'parcelamento-monitor-fake-1',
            normalized: [
                'modality' => $modality->value,
                'regime' => $modality->regime(),
                'operation' => 'MONITOR',
                'orders' => array_map(fn ($o) => $o->toPublicArray(), $projected['orders']),
                'parcel_count' => count($projected['parcels']),
                'payment_count' => count($projected['payments']),
                'simulated' => true,
            ],
            findings: $projected['findings'],
            itemsProcessed: count($projected['orders']) + count($projected['parcels']),
        );
    }

    /**
     * @param  array<string, mixed>  $body
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function enrichBodyForProjection(
        TaxInstallmentModality $modality,
        string $operation,
        array $body,
        array $payload,
    ): array {
        if ($operation === 'CONSULTAR_PAGAMENTO') {
            $key = (string) ($body['anoMesParcela'] ?? $payload['anoMesParcela'] ?? '');
            $orderId = (string) ($body['numeroParcelamento'] ?? $payload['numeroParcelamento'] ?? '');

            return [
                'pedidos' => [[
                    'numero' => $orderId !== '' ? $orderId : 'UNKNOWN',
                    'situacao' => 'EM_ANDAMENTO',
                ]],
                'parcelas' => [[
                    'parcela' => $key,
                    'situacaoFonte' => ! empty($body['pagamentoConfirmado']) ? 'PAGA' : 'EM_ABERTO',
                ]],
                'pagamentos' => $key !== '' ? [$key => $body] : [],
            ];
        }

        if ($operation === 'CONSULTAR_PARCELAS') {
            return [
                'pedidos' => [[
                    'numero' => (string) ($body['numeroParcelamento'] ?? 'UNKNOWN'),
                    'situacao' => 'EM_ANDAMENTO',
                ]],
                'parcelas' => $body['parcelasParaGerar'] ?? [],
            ];
        }

        if ($operation === 'CONSULTAR_PARCELAMENTO') {
            return [
                'pedidos' => [[
                    'numero' => (string) ($body['numeroParcelamento'] ?? 'UNKNOWN'),
                    'situacao' => (string) ($body['situacao'] ?? 'EM_ANDAMENTO'),
                ]],
                'parcelas' => $body['parcelas'] ?? [],
            ];
        }

        return $body;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function uniqueParcels(array $rows): array
    {
        $byKey = [];
        foreach ($rows as $row) {
            $k = (string) ($row['parcela'] ?? $row['numero'] ?? uniqid('p', true));
            $byKey[$k] = array_merge($byKey[$k] ?? [], $row);
        }

        return array_values($byKey);
    }

    private function assertPower(
        FiscalAdapterRequest $request,
        TaxInstallmentModality $modality,
    ): ?FiscalAdapterResult {
        $author = (string) ($request->context['author_identity'] ?? '');
        if ($author === '') {
            // Sem autor no contexto: adapters de leitura ainda projetam em trial com fake,
            // mas marcamos finding de poder não verificado apenas se explicitamente exigido.
            if (! empty($request->context['require_proxy_power'])) {
                return FiscalAdapterResult::blocked(
                    "Procuração/poder {$modality->requiredPowerCode()} não verificado (autor ausente).",
                    'PROXY_POWER_MISSING',
                );
            }

            return null;
        }

        $power = $this->proxyPowers->findUsablePower(
            (int) $request->office->id,
            (int) $request->client->id,
            $modality->requiredPowerCode(),
            $author,
        );

        if ($power === null) {
            return new FiscalAdapterResult(
                result: FiscalRunResult::Blocked,
                situation: FiscalSituation::Blocked,
                coverage: FiscalCoverage::Full,
                findings: [[
                    'code' => 'PROXY_POWER_MISSING',
                    'severity' => FiscalFindingSeverity::High->value,
                    'title' => 'Poder de procuração ausente para modalidade',
                    'detail' => "Modalidade {$modality->value} exige poder {$modality->requiredPowerCode()}.",
                    'situation' => FiscalSituation::Blocked->value,
                    'creates_pending' => false,
                ]],
                skipReason: 'PROXY_POWER_MISSING',
                errorCode: 'PROXY_POWER_MISSING',
                errorMessage: "Sem poder {$modality->requiredPowerCode()} para {$modality->value}.",
            );
        }

        return null;
    }
}

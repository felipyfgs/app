<?php

namespace App\Services\Fiscal\SimplesMei;

use App\DTO\Fiscal\FiscalAdapterRequest;
use App\DTO\Fiscal\FiscalAdapterResult;
use App\DTO\Fiscal\SimplesMei\SimplesMeiOperationDef;
use App\Enums\FiscalCoverage;
use App\Enums\FiscalGuideEmissionStatus;
use App\Enums\FiscalGuidePaymentStatus;
use App\Enums\FiscalRunResult;
use App\Enums\FiscalSituation;
use App\Models\FiscalGuideStub;
use App\Services\Audit\AuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

/**
 * Hook de geração assistida de DAS → stub de guia sem marcar pagamento.
 * Integração plena com tax-guide-management (task 11) consome estes stubs.
 */
final class DasGuideHookService
{
    public function __construct(
        private readonly AuditLogger $audit,
    ) {}

    /**
     * Piloto: cria stub local sem chamar SERPRO quando mutações estão OFF.
     */
    public function createStubWithoutExternalCall(
        FiscalAdapterRequest $request,
        SimplesMeiOperationDef $def,
    ): FiscalAdapterResult {
        $periodKey = $request->competence?->period_key
            ?? (string) ($request->context['period_key'] ?? now()->format('Y-m'));

        $stub = FiscalGuideStub::query()->create([
            'office_id' => $request->office->id,
            'client_id' => $request->client->id,
            'run_id' => $request->run->id,
            'system_code' => $def->systemCode,
            'service_code' => $def->serviceCode,
            'operation_code' => $def->operationCode,
            'regime_family' => $def->regimeFamily->value,
            'period_key' => $periodKey,
            'document_number' => 'STUB-'.Str::upper(Str::random(10)),
            'due_date' => CarbonImmutable::now()->addDays(20)->toDateString(),
            'amount' => null,
            'emission_status' => FiscalGuideEmissionStatus::Stub,
            'payment_status' => FiscalGuidePaymentStatus::Unknown,
            'is_external_call' => false,
            'metadata' => [
                'source' => 'das_stub_without_mutating',
                'note' => 'Stub assistido — pagamento permanece UNKNOWN',
            ],
        ]);

        $this->audit->record(
            action: 'fiscal.simples_mei.das_stub',
            result: 'SUCCESS',
            subject: $stub,
            context: [
                'client_id' => $request->client->id,
                'period_key' => $periodKey,
                'service_code' => $def->serviceCode,
                'payment_status' => FiscalGuidePaymentStatus::Unknown->value,
            ],
            officeId: (int) $request->office->id,
        );

        $normalized = [
            'dto' => 'das_guide',
            'dto_version' => '1',
            'competence' => $periodKey,
            'regime_family' => $def->regimeFamily->value,
            'document_number' => $stub->document_number,
            'due_date' => $stub->due_date?->toDateString(),
            'amount' => null,
            'emission_status' => FiscalGuideEmissionStatus::Stub->value,
            'payment_status' => FiscalGuidePaymentStatus::Unknown->value,
            'payment_inferred' => false,
            'guide_stub_id' => $stub->id,
            'stub_without_external' => true,
        ];

        $evidence = json_encode($normalized, JSON_THROW_ON_ERROR);

        return new FiscalAdapterResult(
            result: FiscalRunResult::Success,
            situation: FiscalSituation::Attention,
            coverage: FiscalCoverage::Partial,
            evidenceBytes: $evidence,
            sourceVersion: '1',
            normalized: $normalized,
            findings: [[
                'code' => 'DAS_STUB_CREATED',
                'severity' => 'INFO',
                'title' => 'DAS assistido (stub) — pagamento não confirmado',
                'detail' => 'Gerado sem chamada externa (piloto). Obrigação não marcada como paga.',
                'situation' => FiscalSituation::Attention->value,
                'creates_pending' => false,
            ]],
            itemsProcessed: 1,
        );
    }

    public function persistFromAdapterResult(
        FiscalAdapterRequest $request,
        SimplesMeiOperationDef $def,
        FiscalAdapterResult $result,
    ): FiscalGuideStub {
        $n = $result->normalized ?? [];
        $periodKey = (string) ($n['competence'] ?? $request->competence?->period_key ?? '');

        $stub = FiscalGuideStub::query()->create([
            'office_id' => $request->office->id,
            'client_id' => $request->client->id,
            'run_id' => $request->run->id,
            'system_code' => $def->systemCode,
            'service_code' => $def->serviceCode,
            'operation_code' => $def->operationCode,
            'regime_family' => $def->regimeFamily->value,
            'period_key' => $periodKey,
            'document_number' => $n['document_number'] ?? null,
            'due_date' => $n['due_date'] ?? null,
            'amount' => $n['amount'] ?? null,
            'emission_status' => FiscalGuideEmissionStatus::tryFrom((string) ($n['emission_status'] ?? ''))
                ?? FiscalGuideEmissionStatus::Issued,
            'payment_status' => FiscalGuidePaymentStatus::Unknown,
            'is_external_call' => true,
            'metadata' => [
                'source' => 'integra_response',
                'payment_inferred' => false,
            ],
        ]);

        $this->audit->record(
            action: 'fiscal.simples_mei.das_issued',
            result: 'SUCCESS',
            subject: $stub,
            context: [
                'client_id' => $request->client->id,
                'period_key' => $periodKey,
                'emission_status' => $stub->emission_status->value,
                'payment_status' => FiscalGuidePaymentStatus::Unknown->value,
            ],
            officeId: (int) $request->office->id,
        );

        return $stub;
    }
}

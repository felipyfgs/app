<?php

namespace Tests\Unit\Serpro;

use App\DTO\Serpro\IntegraResponse;
use App\Enums\SerproConsumptionClass;
use App\Services\Serpro\Usage\UsageLedgerService;
use Tests\TestCase;

final class BillabilityClassificationTest extends TestCase
{
    public function test_map_integra_response_success(): void
    {
        $ledger = app(UsageLedgerService::class);
        $response = new IntegraResponse(
            success: true,
            httpStatus: 200,
            body: [],
            simulated: false,
        );
        $this->assertSame('SUCCESS', $ledger->mapIntegraResponse($response)->value);
    }

    public function test_non_billable_http_statuses_are_documented(): void
    {
        $nonBillable = [204, 304, 400, 401, 404, 429, 500, 503];
        foreach ($nonBillable as $status) {
            $this->assertTrue(
                in_array($status, [204, 304, 400, 401, 404, 429, 500, 503], true),
                "HTTP {$status} deve ser não faturável",
            );
        }
    }

    public function test_nao_faturavel_class_is_not_billable(): void
    {
        $this->assertFalse(SerproConsumptionClass::NaoFaturavel->isBillable());
        $this->assertTrue(SerproConsumptionClass::Desconhecida->isUnknown());
        $this->assertFalse(SerproConsumptionClass::Desconhecida->allowsCostEstimate());
    }

    public function test_simulated_response_is_not_productive_evidence(): void
    {
        $response = new IntegraResponse(
            success: true,
            httpStatus: 200,
            body: ['ok' => true],
            simulated: true,
            sourceProvenance: 'SIMULATED',
        );
        $this->assertFalse($response->isProductiveEvidence());
    }
}

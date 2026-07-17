<?php

namespace Tests\Unit\Fiscal\SimplesMei\Pgdasd;

use App\DTO\Fiscal\FiscalAdapterRequest;
use App\DTO\Fiscal\FiscalAdapterResult;
use App\DTO\Serpro\IntegraResponse;
use App\Enums\FiscalCoverage;
use App\Enums\FiscalRunResult;
use App\Enums\FiscalSituation;
use App\Enums\FiscalSourceProvenance;
use App\Enums\FiscalTrigger;
use App\Models\Client;
use App\Models\FiscalMonitoringRun;
use App\Models\FiscalSnapshot;
use App\Models\Office;
use App\Models\PgdasdRbt12Projection;
use App\Models\TaxObligationProjection;
use App\Services\Fiscal\SimplesMei\Pgdasd\PgdasdPostConsultService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PgdasdPostConsultServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function only_complete_serpro_real_response_advances_last_valid_fields(): void
    {
        Queue::fake();
        [$request, $result] = $this->context(FiscalSourceProvenance::Unverified);
        $service = app(PgdasdPostConsultService::class);
        $validDados = ['anoCalendario' => 2026, 'periodos' => []];

        foreach ([
            new IntegraResponse(true, 200, [], simulated: true, dados: $validDados, sourceProvenance: 'SIMULATED'),
            new IntegraResponse(true, 200, [], dados: $validDados, sourceProvenance: null),
            new IntegraResponse(true, 200, [], dados: [
                'anoCalendario' => 2026,
                'periodos' => [['periodoApuracao' => 202606]],
            ], sourceProvenance: 'SERPRO_REAL'),
        ] as $response) {
            $handled = $service->handle($request, $response, $result, 'pgdasd.consdeclaracao');
            $this->assertSame(FiscalSituation::Unknown, $handled['result']->situation);
            $this->assertDatabaseCount('tax_obligation_projections', 0);
        }

        $request->run->forceFill(['source_provenance' => FiscalSourceProvenance::SerproReal])->save();
        $handled = $service->handle($request, new IntegraResponse(
            true,
            200,
            [],
            dados: $validDados,
            sourceProvenance: 'SERPRO_REAL',
        ), $result, 'pgdasd.consdeclaracao');

        $projection = TaxObligationProjection::query()->firstOrFail();
        $this->assertNotNull($projection->last_valid_query_at);
        $this->assertSame($request->run->id, $projection->last_valid_run_id);
        $this->assertNull($projection->last_valid_snapshot_id);
        $this->assertSame('DUE_WITHIN_DEADLINE', $handled['result']->normalized['pgdasd']['declaration_state']);
        $this->assertDatabaseHas('pgdasd_rbt12_projections', [
            'projection_id' => $projection->id,
            'status' => 'NO_DAS',
        ]);

        $snapshot = FiscalSnapshot::query()->create([
            'office_id' => $request->office->id,
            'run_id' => $request->run->id,
            'client_id' => $request->client->id,
            'system_code' => 'INTEGRA_SN',
            'service_code' => 'PGDASD',
            'operation_code' => 'CONSULTAR_DECLARACAO',
            'source_provenance' => 'SERPRO_REAL',
            'situation' => 'PENDING',
            'coverage' => 'FULL',
            'version' => 1,
            'is_current' => true,
            'normalized' => [],
            'observed_at' => CarbonImmutable::now(),
            'created_at' => CarbonImmutable::now(),
        ]);
        $this->assertSame(1, $service->attachSnapshotToValidProjections($request->run->refresh(), $snapshot));
        $this->assertSame($snapshot->id, $projection->refresh()->last_valid_snapshot_id);
        $this->assertSame('NO_DAS', PgdasdRbt12Projection::query()->firstOrFail()->status->value);

        $lastValidAt = $projection->last_valid_query_at;
        $service->handle($request, new IntegraResponse(
            true,
            200,
            [],
            simulated: true,
            dados: $validDados,
            sourceProvenance: 'SIMULATED',
        ), $result, 'pgdasd.consdeclaracao');
        $projection->refresh();
        $this->assertSame('UNVERIFIED', $projection->pgdasd_declaration_state->value);
        $this->assertTrue($projection->last_valid_query_at?->equalTo($lastValidAt));
        $this->assertSame($request->run->id, $projection->last_valid_run_id);
        $this->assertSame($snapshot->id, $projection->last_valid_snapshot_id);
    }

    /** @return array{FiscalAdapterRequest,FiscalAdapterResult} */
    private function context(FiscalSourceProvenance $provenance): array
    {
        $office = Office::factory()->create(['timezone' => 'America/Sao_Paulo']);
        $client = Client::factory()->forOffice($office)->create(['tax_regime' => 'SIMPLES_NACIONAL']);
        $run = FiscalMonitoringRun::query()->create([
            'office_id' => $office->id,
            'client_id' => $client->id,
            'system_code' => 'INTEGRA_SN',
            'service_code' => 'PGDASD',
            'operation_code' => 'CONSULTAR_DECLARACAO',
            'source_provenance' => $provenance,
            'trigger' => 'MANUAL',
            'idempotency_key' => 'post-consult-test',
            'status' => 'QUEUED',
            'situation' => 'UNKNOWN',
            'coverage' => 'FULL',
            'mutability' => 'READ_ONLY',
            'progress' => [
                'expected_periodo_apuracao' => '202606',
                'period_key' => '2026-06',
            ],
        ]);
        $request = new FiscalAdapterRequest(
            office: $office,
            client: $client,
            run: $run,
            systemCode: 'INTEGRA_SN',
            serviceCode: 'PGDASD',
            operationCode: 'CONSULTAR_DECLARACAO',
            trigger: FiscalTrigger::Manual,
            progress: $run->progress,
        );
        $result = new FiscalAdapterResult(
            result: FiscalRunResult::Success,
            situation: FiscalSituation::Unknown,
            coverage: FiscalCoverage::Full,
            evidenceBytes: '{}',
            normalized: [],
        );

        return [$request, $result];
    }
}

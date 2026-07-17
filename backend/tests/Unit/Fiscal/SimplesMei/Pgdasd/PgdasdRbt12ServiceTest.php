<?php

namespace Tests\Unit\Fiscal\SimplesMei\Pgdasd;

use App\Enums\PgdasdOperationKind;
use App\Enums\PgdasdRbt12Status;
use App\Jobs\Fiscal\FetchPgdasdRbt12Job;
use App\Models\Client;
use App\Models\FiscalMonitoringRun;
use App\Models\Office;
use App\Models\PgdasdOperation;
use App\Models\PgdasdRbt12Projection;
use App\Models\TaxObligationProjection;
use App\Services\Fiscal\Declarations\TaxObligationCatalogService;
use App\Services\Fiscal\Declarations\TaxObligationProjectionService;
use App\Services\Fiscal\SimplesMei\Pgdasd\PgdasdRbt12Service;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PgdasdRbt12ServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function reserves_once_and_retifier_creates_exactly_one_new_extrato_query(): void
    {
        Queue::fake();
        [$office, $client, $run, $projection] = $this->context('2026-06');
        $declaration = $this->operation($run, $projection->id, [
            'kind' => PgdasdOperationKind::Declaration,
            'logical_key' => 'decl-original',
            'declaration_number' => 'DECL-1',
            'transmitted_at' => CarbonImmutable::parse('2026-07-10 10:00:00'),
        ]);
        $das = $this->operation($run, $projection->id, [
            'kind' => PgdasdOperationKind::Das,
            'logical_key' => 'das-1',
            'das_number' => 'DAS-1',
            'issued_at' => CarbonImmutable::parse('2026-07-10 10:05:00'),
        ]);
        $service = app(PgdasdRbt12Service::class);

        $first = $service->reserveFromOperations($run, [$declaration, $das], [$projection]);
        $replay = $service->reserveFromOperations($run, [$declaration, $das], [$projection]);

        $this->assertCount(1, $first);
        $this->assertSame([], $replay);
        Queue::assertPushed(FetchPgdasdRbt12Job::class, 1);

        $retifier = $this->operation($run, $projection->id, [
            'kind' => PgdasdOperationKind::Declaration,
            'logical_key' => 'decl-retifier',
            'declaration_number' => 'DECL-2',
            'transmitted_at' => CarbonImmutable::parse('2026-07-11 11:00:00'),
        ]);
        $afterRetifier = $service->reserveFromOperations($run, [$retifier], [$projection]);

        $this->assertCount(1, $afterRetifier);
        $this->assertSame('DAS-1', $afterRetifier[0]->source_das_number);
        $this->assertNotSame($first[0]->source_reference_key, $afterRetifier[0]->source_reference_key);
        Queue::assertPushed(FetchPgdasdRbt12Job::class, 2);
    }

    #[Test]
    public function valid_period_without_das_persists_no_das_without_dispatch(): void
    {
        Queue::fake();
        [, , $run, $projection] = $this->context('2026-05');

        $created = app(PgdasdRbt12Service::class)
            ->reserveFromOperations($run, [], [$projection]);

        $this->assertCount(1, $created);
        $this->assertSame(PgdasdRbt12Status::NoDas, $created[0]->status);
        Queue::assertNotPushed(FetchPgdasdRbt12Job::class);
    }

    #[Test]
    public function das_are_reserved_deterministically_and_pointer_uses_latest_emission(): void
    {
        Queue::fake();
        [, , $run, $projection] = $this->context('2026-04');
        $newer = $this->operation($run, $projection->id, [
            'kind' => PgdasdOperationKind::Das,
            'logical_key' => 'das-newer',
            'das_number' => 'DAS-Z',
            'issued_at' => CarbonImmutable::parse('2026-05-11 10:00:00'),
        ]);
        $older = $this->operation($run, $projection->id, [
            'kind' => PgdasdOperationKind::Das,
            'logical_key' => 'das-older',
            'das_number' => 'DAS-A',
            'issued_at' => CarbonImmutable::parse('2026-05-10 10:00:00'),
        ]);

        app(PgdasdRbt12Service::class)
            ->reserveFromOperations($run, [$newer, $older], [$projection]);

        $projection->refresh();
        $pointed = PgdasdRbt12Projection::query()->findOrFail($projection->pgdasd_latest_rbt12_projection_id);
        $this->assertSame('DAS-Z', $pointed->source_das_number);
    }

    /** @return array{Office,Client,FiscalMonitoringRun,TaxObligationProjection} */
    private function context(string $periodKey): array
    {
        $office = Office::factory()->create();
        $client = Client::factory()->forOffice($office)->create(['tax_regime' => 'SIMPLES_NACIONAL']);
        $obligation = app(TaxObligationCatalogService::class)->findByCode('PGDAS_D');
        $projection = app(TaxObligationProjectionService::class)->project(
            $office,
            $client,
            $obligation,
            $periodKey,
        );
        $run = FiscalMonitoringRun::query()->create([
            'office_id' => $office->id,
            'client_id' => $client->id,
            'system_code' => 'INTEGRA_SN',
            'service_code' => 'PGDASD',
            'operation_code' => 'CONSULTAR_DECLARACAO',
            'trigger' => 'MANUAL',
            'idempotency_key' => 'test-'.str_replace('-', '', $periodKey),
            'status' => 'QUEUED',
            'situation' => 'UNKNOWN',
            'coverage' => 'FULL',
            'mutability' => 'READ_ONLY',
        ]);

        return [$office, $client, $run, $projection];
    }

    /** @param array<string,mixed> $attributes */
    private function operation(FiscalMonitoringRun $run, int $projectionId, array $attributes): PgdasdOperation
    {
        $now = CarbonImmutable::now();

        return PgdasdOperation::query()->create($attributes + [
            'office_id' => $run->office_id,
            'client_id' => $run->client_id,
            'projection_id' => $projectionId,
            'period_key' => '2026-06',
            'first_seen_at' => $now,
            'last_seen_at' => $now,
            'source_run_id' => $run->id,
        ]);
    }
}

<?php

namespace Tests\Feature\Sync;

use App\Enums\SyncCursorStatus;
use App\Jobs\SyncEstablishmentDistributionJob;
use App\Models\Client;
use App\Models\Establishment;
use App\Models\Office;
use Carbon\CarbonImmutable;
use Database\Factories\EstablishmentFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class DispatchDueSyncsCapacityTest extends TestCase
{
    use RefreshDatabase;

    public function test_distribui_mais_de_mil_estabelecimentos_em_um_ciclo_horario(): void
    {
        Queue::fake();

        $office = Office::factory()->create();
        $client = Client::factory()->forOffice($office)->create(['root_cnpj' => '11222333']);
        $timestamp = CarbonImmutable::parse('2026-07-13 12:00:00', 'UTC');

        $establishments = [];
        for ($index = 1; $index <= 1_001; $index++) {
            $establishments[] = [
                'office_id' => $office->id,
                'client_id' => $client->id,
                'cnpj' => EstablishmentFactory::cnpjWithRoot('11222333', str_pad((string) $index, 4, '0', STR_PAD_LEFT)),
                'trade_name' => 'Estabelecimento '.$index,
                'is_matrix' => $index === 1,
                'is_active' => true,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ];
        }

        foreach (array_chunk($establishments, 250) as $chunk) {
            DB::table('establishments')->insert($chunk);
        }

        $cursors = Establishment::query()
            ->orderBy('id')
            ->get(['id', 'office_id'])
            ->map(fn (Establishment $establishment): array => [
                'office_id' => $establishment->office_id,
                'establishment_id' => $establishment->id,
                'environment' => 'production',
                'last_nsu' => 0,
                'status' => SyncCursorStatus::Idle->value,
                'consecutive_decode_failures' => 0,
                'attempts' => 0,
                'next_sync_at' => null,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ])
            ->all();

        foreach (array_chunk($cursors, 250) as $chunk) {
            DB::table('sync_cursors')->insert($chunk);
        }

        $startedAt = CarbonImmutable::parse('2026-07-13 12:17:00', 'UTC');
        $realStartedAt = hrtime(true);
        $dispatchedPerMinute = [];
        $previousTotal = 0;

        for ($offset = 0; $offset < 60; $offset++) {
            CarbonImmutable::setTestNow($startedAt->addMinutes($offset));
            Artisan::call('adn:dispatch-due-syncs');

            $currentTotal = Queue::pushed(SyncEstablishmentDistributionJob::class)->count();
            $dispatchedPerMinute[] = $currentTotal - $previousTotal;
            $previousTotal = $currentTotal;
        }

        CarbonImmutable::setTestNow();
        $elapsedSeconds = (hrtime(true) - $realStartedAt) / 1_000_000_000;
        $cursorIds = Queue::pushed(SyncEstablishmentDistributionJob::class)
            ->map(function (mixed $pushed): int {
                $job = is_array($pushed) ? ($pushed['job'] ?? $pushed[0] ?? null) : $pushed;

                return (int) $job->syncCursorId;
            })
            ->all();

        $this->assertCount(1_001, $cursorIds);
        $this->assertCount(1_001, array_unique($cursorIds));
        $this->assertLessThanOrEqual(17, max($dispatchedPerMinute));
        $this->assertLessThan(60.0, $elapsedSeconds, 'A simulação do ciclo horário excedeu 60 segundos de execução local.');
    }
}

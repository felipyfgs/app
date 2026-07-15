<?php

namespace Tests\Feature\Sefaz;

use App\Enums\CaptureChannel;
use App\Enums\SyncCursorStatus;
use App\Models\ChannelSyncCursor;
use App\Models\Client;
use App\Models\Establishment;
use App\Models\Office;
use App\Models\SyncCursor;
use Database\Factories\EstablishmentFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Bloqueio DistDFe (656/BLOCKED) não deve alterar cursor ADN do mesmo estabelecimento.
 */
class ChannelCoexistenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_bloqueio_distdfe_nao_altera_cursor_adn(): void
    {
        $office = Office::factory()->create();
        $client = Client::factory()->forOffice($office)->create(['root_cnpj' => '11222333']);
        $est = Establishment::factory()
            ->forClient($client, EstablishmentFactory::cnpjWithRoot('11222333'))
            ->create();

        $adn = SyncCursor::query()->create([
            'office_id' => $office->id,
            'establishment_id' => $est->id,
            'environment' => 'production',
            'last_nsu' => 42,
            'status' => SyncCursorStatus::Idle,
            'next_sync_at' => now()->addHour(),
        ]);

        $sefaz = ChannelSyncCursor::query()->create([
            'office_id' => $office->id,
            'establishment_id' => $est->id,
            'environment' => 'production',
            'source' => CaptureChannel::NfeDistDfe->source(),
            'channel' => CaptureChannel::NfeDistDfe,
            'last_nsu' => 10,
            'status' => SyncCursorStatus::Idle,
        ]);

        // Simula consumo indevido no processador DistDFe
        $sefaz->status = SyncCursorStatus::Blocked;
        $sefaz->last_cstat = '656';
        $sefaz->last_error = 'Consumo indevido SEFAZ (cStat 656). Aguardar ≥1h.';
        $sefaz->next_sync_at = now()->addHour();
        $sefaz->save();

        $adn->refresh();
        $this->assertSame(SyncCursorStatus::Idle, $adn->status);
        $this->assertSame(42, (int) $adn->last_nsu);
        $this->assertNotSame(SyncCursorStatus::Blocked, $adn->status);

        $sefaz->refresh();
        $this->assertSame(SyncCursorStatus::Blocked, $sefaz->status);
        $this->assertSame('656', $sefaz->last_cstat);
    }
}

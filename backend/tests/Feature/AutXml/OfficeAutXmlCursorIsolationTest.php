<?php

namespace Tests\Feature\AutXml;

use App\Enums\CaptureChannel;
use App\Enums\SyncCursorStatus;
use App\Models\Office;
use App\Models\OfficeDistributionCursor;
use App\Models\OfficeFiscalIdentity;
use App\Services\Sefaz\OfficeDistributionCursorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OfficeAutXmlCursorIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_duas_filiais_mesma_raiz_compartilham_um_cursor_por_ambiente(): void
    {
        $office = Office::factory()->create();
        // Identidade única da raiz do escritório (não por filial de cliente)
        $identity = OfficeFiscalIdentity::factory()->forOffice($office)->withCnpj('11222333000181')->create();

        $service = app(OfficeDistributionCursorService::class);
        $c1 = $service->ensureForIdentity($identity, 'production');
        $c2 = $service->ensureForIdentity($identity, 'production');

        $this->assertSame($c1->id, $c2->id);
        $this->assertSame(1, OfficeDistributionCursor::query()
            ->where('office_id', $office->id)
            ->where('interested_root_cnpj', $identity->root_cnpj)
            ->where('environment', 'production')
            ->where('channel', CaptureChannel::NfeAutXmlDistDfe->value)
            ->count());

        // Homologação é stream separado
        $cHom = $service->ensureForIdentity($identity, 'homologation');
        $this->assertNotSame($c1->id, $cHom->id);
    }

    public function test_constraint_impede_dois_cursores_mesma_chave_logica(): void
    {
        $office = Office::factory()->create();
        $identity = OfficeFiscalIdentity::factory()->forOffice($office)->create();
        OfficeDistributionCursor::factory()->forIdentity($identity)->create([
            'environment' => 'production',
            'status' => SyncCursorStatus::Idle,
        ]);

        $this->expectException(\Throwable::class);
        DB::table('office_distribution_cursors')->insert([
            'office_id' => $office->id,
            'office_fiscal_identity_id' => $identity->id,
            'interested_root_cnpj' => $identity->root_cnpj,
            'query_cnpj' => $identity->cnpj,
            'environment' => 'production',
            'channel' => CaptureChannel::NfeAutXmlDistDfe->value,
            'last_nsu' => 0,
            'status' => SyncCursorStatus::Idle->value,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}

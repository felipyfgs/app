<?php

namespace Tests\Feature\Sefaz;

use App\Enums\CaptureChannel;
use App\Enums\DocumentKind;
use App\Enums\OfficeRole;
use App\Models\Client;
use App\Models\Establishment;
use App\Models\Office;
use App\Models\User;
use App\Services\Clients\CaptureEligibilityService;
use App\Support\CurrentOffice;
use Database\Factories\EstablishmentFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * MDF-e fora do escopo escritural: kind=MDFE vazio sem consultar projeção.
 */
class MdfeCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_documents_kind_mdfe_retorna_vazio_sem_tabela(): void
    {
        // Garante que a API não depende de mdfe_documents existir no runtime.
        if (Schema::hasTable('mdfe_documents')) {
            Schema::drop('mdfe_documents');
        }

        $office = Office::factory()->create();
        $user = User::factory()->forOffice($office, OfficeRole::Operator)->withTwoFactorConfirmed()->create();
        $this->actingAs($user);
        app(CurrentOffice::class)->resolve($user);

        $this->getJson('/api/v1/documents?kind=MDFE')
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.next_cursor', null);
    }

    public function test_catalog_filter_options_sem_mdfe(): void
    {
        $codes = array_map(fn (DocumentKind $k) => $k->value, DocumentKind::catalogFilterOptions());
        $this->assertNotContains(DocumentKind::Mdfe->value, $codes);
        $this->assertContains(DocumentKind::Cte->value, $codes);
        $this->assertFalse(DocumentKind::Mdfe->captureAvailable());
        $this->assertFalse(CaptureChannel::MdfeDistDfe->isEnabled());
    }

    public function test_elegibilidade_canais_sem_mdfe(): void
    {
        $office = Office::factory()->create();
        $client = Client::factory()->forOffice($office)->create(['root_cnpj' => '11222333']);
        $est = Establishment::factory()
            ->forClient($client, EstablishmentFactory::cnpjWithRoot('11222333'))
            ->create();

        $eval = app(CaptureEligibilityService::class)->evaluate($est);
        $this->assertArrayNotHasKey(CaptureChannel::MdfeDistDfe->value, $eval['channels']);
        $this->assertArrayHasKey(CaptureChannel::NfseAdn->value, $eval['channels']);
        $this->assertArrayHasKey(CaptureChannel::NfeDistDfe->value, $eval['channels']);
        $this->assertArrayHasKey(CaptureChannel::CteDistDfe->value, $eval['channels']);
    }
}

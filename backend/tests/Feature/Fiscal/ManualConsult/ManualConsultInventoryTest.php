<?php

namespace Tests\Feature\Fiscal\ManualConsult;

use App\Enums\AuthorIdentityType;
use App\Enums\OfficeRole;
use App\Enums\SerproAuthorizationStatus;
use App\Enums\SerproContractStatus;
use App\Enums\SerproEnvironment;
use App\Models\Client;
use App\Models\Establishment;
use App\Models\Office;
use App\Models\OfficeSerproAuthorization;
use App\Models\SerproContract;
use App\Models\User;
use App\Support\CurrentOffice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ManualConsultInventoryTest extends TestCase
{
    use RefreshDatabase;

    private Office $office;

    private Client $client;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'features.global_enabled' => true,
            'features.kill_switch' => false,
            'features.modules.simples_mei.enabled' => true,
            'features.modules.simples_mei.allow_all_offices' => true,
            'features.modules.dctfweb_mit.enabled' => true,
            'features.modules.dctfweb_mit.allow_all_offices' => true,
            'features.modules.sitfis.enabled' => true,
            'features.modules.sitfis.allow_all_offices' => true,
            'features.modules.guias.enabled' => true,
            'features.modules.guias.allow_all_offices' => true,
            'features.modules.mailbox.enabled' => true,
            'features.modules.mailbox.allow_all_offices' => true,
            'features.modules.parcelamentos.enabled' => true,
            'features.modules.parcelamentos.allow_all_offices' => true,
            'fiscal_monitoring.enabled' => true,
            'serpro.kill_switch' => false,
            'serpro.default_environment' => 'TRIAL',
            'serpro.trial.use_fake_clients' => true,
            'serpro.capabilities.simples_mei' => 'real',
            'serpro.capabilities.dctfweb' => 'real',
            'serpro.capabilities.sitfis' => 'real',
            'serpro.capabilities.guides' => 'real',
            'serpro.capabilities.mailbox' => 'real',
            'serpro.capabilities.installments' => 'real',
            'serpro.capabilities.registrations' => 'real',
            'serpro.capabilities.tax_processes' => 'real',
        ]);

        $this->office = Office::factory()->create();
        $this->client = Client::factory()->forOffice($this->office)->create();
        Establishment::factory()->create([
            'office_id' => $this->office->id,
            'client_id' => $this->client->id,
            'cnpj' => '11222333000181',
            'is_matrix' => true,
        ]);
        $this->user = User::factory()->forOffice($this->office, OfficeRole::Admin)->withTwoFactorConfirmed()->create();
        $this->actingAs($this->user);
        app(CurrentOffice::class)->clear();
        app(CurrentOffice::class)->resolve($this->user);
        $this->seedIntegraChain();
    }

    #[Test]
    public function it_lists_read_only_actions_without_mutants_or_prospection(): void
    {
        $response = $this->getJson('/api/v1/fiscal/manual-consults');

        $response->assertOk()
            ->assertJsonPath('data.meta.serpro_called', false)
            ->assertJsonStructure([
                'data' => [
                    'actions' => [
                        ['action_id', 'label', 'surface_key', 'module_key', 'eligibility', 'executable', 'params_schema'],
                    ],
                    'meta' => ['total', 'ready', 'serpro_called'],
                ],
            ]);

        $actions = $response->json('data.actions');
        $this->assertNotEmpty($actions);

        $ids = array_column($actions, 'action_id');
        $this->assertContains('simples_mei_pgmei:pgmei.dividaativa', $ids);
        $this->assertContains('simples_mei_ccmei:ccmei.dadosccmei', $ids);
        $this->assertContains('guides:sicalc.consultaapoioreceitas', $ids);

        foreach ($actions as $action) {
            $this->assertArrayNotHasKey('id_sistema', $action);
            $this->assertArrayNotHasKey('id_servico', $action);
            $this->assertStringNotContainsString('gerarguia', (string) ($action['operation_hint'] ?? ''));
            $this->assertStringNotContainsString('transdeclaracao', (string) ($action['operation_hint'] ?? ''));
            $this->assertStringNotContainsString('autenticar_procurador_token', json_encode($action, JSON_THROW_ON_ERROR));
        }
    }

    #[Test]
    public function it_rejects_office_id_from_client_on_inventory(): void
    {
        $this->getJson('/api/v1/fiscal/manual-consults?office_id='.$this->office->id)
            ->assertStatus(422)
            ->assertJsonPath('code', 'CLIENT_OFFICE_ID_REJECTED');
    }

    #[Test]
    public function it_filters_by_surface_and_marks_module_off(): void
    {
        // fiscal_monitoring.enabled=true NÃO pode anular flag de módulo (fail-closed).
        config([
            'features.modules.simples_mei.enabled' => false,
            'fiscal_monitoring.enabled' => true,
        ]);

        $response = $this->getJson('/api/v1/fiscal/manual-consults?module_key=simples_mei&client_id='.$this->client->id);
        $response->assertOk();
        $actions = $response->json('data.actions');
        $this->assertNotEmpty($actions);
        foreach ($actions as $action) {
            $this->assertSame('simples_mei', $action['module_key']);
            if (($action['operation_hint'] ?? '') === 'ccmei.dadosccmei') {
                $this->assertSame('module_off', $action['eligibility']);
                $this->assertFalse($action['executable']);
            }
        }
    }

    #[Test]
    public function it_marks_capability_off_when_driver_disabled(): void
    {
        config(['serpro.capabilities.guides' => 'disabled']);

        $response = $this->getJson('/api/v1/fiscal/manual-consults?module_key=guides');
        $response->assertOk();
        $actions = $response->json('data.actions');
        $sicalc = collect($actions)->firstWhere('operation_hint', 'sicalc.consultaapoioreceitas');
        $this->assertNotNull($sicalc);
        $this->assertSame('capability_off', $sicalc['eligibility']);
    }

    private function seedIntegraChain(): void
    {
        SerproContract::query()->create([
            'environment' => SerproEnvironment::Trial,
            'status' => SerproContractStatus::Active,
            'contractor_cnpj' => '11222333000181',
            'health_status' => 'OK',
        ]);
        OfficeSerproAuthorization::query()->create([
            'office_id' => $this->office->id,
            'environment' => SerproEnvironment::Trial,
            'status' => SerproAuthorizationStatus::TokenActive,
            'author_identity_type' => AuthorIdentityType::Cpf,
            'author_identity' => '52998224725',
            'termo_vault_object_id' => '01ARZ3NDEKTSV4RRFFQ69G5FAV',
            'termo_valid_to' => now()->addYear(),
            'procurador_token_vault_object_id' => '01ARZ3NDEKTSV4RRFFQ69G5FAV',
            'procurador_token_expires_at' => now()->addHours(6),
        ]);
    }
}

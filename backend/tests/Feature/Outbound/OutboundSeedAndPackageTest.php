<?php

namespace Tests\Feature\Outbound;

use App\Enums\OfficeRole;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\Establishment;
use App\Models\Office;
use App\Models\OutboundCaptureProfile;
use App\Models\User;
use App\Support\CurrentOffice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class OutboundSeedAndPackageTest extends TestCase
{
    use RefreshDatabase;

    public function test_operator_registra_semente_procnfe_ma(): void
    {
        [$office, $user, $est] = $this->seedMaEstablishment();
        $this->actingAs($user);
        app(CurrentOffice::class)->resolve($user);

        $xml = file_get_contents(base_path('tests/fixtures/ma-outbound/procNFe_55_out_ma.xml'));

        $response = $this->postJson('/api/v1/outbound/establishments/'.$est->id.'/seed', [
            'environment' => 'homologation',
            'xml' => $xml,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.series.position_kind', 'nNF')
            ->assertJsonPath('data.series.seed_nnf', 1)
            ->assertJsonPath('data.series.discovery_position', 2);

        $this->assertDatabaseHas('outbound_series_cursors', [
            'establishment_id' => $est->id,
            'series' => 1,
            'seed_nnf' => 1,
            'discovery_position' => 2,
        ]);
        $this->assertFalse(
            Schema::hasColumn('outbound_series_cursors', 'last_nsu'),
            'Cursor de série não deve ter last_nsu (posição é nNF).'
        );
    }

    public function test_rejeita_semente_sem_protocolo(): void
    {
        [, $user, $est] = $this->seedMaEstablishment();
        $this->actingAs($user);
        app(CurrentOffice::class)->resolve($user);

        $xml = file_get_contents(base_path('tests/fixtures/ma-outbound/nfe_sem_protocolo.xml'));

        $this->postJson('/api/v1/outbound/establishments/'.$est->id.'/seed', [
            'environment' => 'homologation',
            'xml' => $xml,
        ])->assertStatus(422);
    }

    public function test_viewer_nao_envia_pacote_nem_semente(): void
    {
        $office = Office::factory()->create();
        $viewer = User::factory()->forOffice($office, OfficeRole::Viewer)->withTwoFactorConfirmed()->create();
        $client = Client::factory()->forOffice($office)->create();
        $est = Establishment::factory()->forClient($client)->create([
            'cnpj' => '12345678000190',
            'address_state' => 'MA',
        ]);
        $this->actingAs($viewer);
        app(CurrentOffice::class)->resolve($viewer);

        $xml = file_get_contents(base_path('tests/fixtures/ma-outbound/procNFe_55_out_ma.xml'));
        $this->postJson('/api/v1/outbound/establishments/'.$est->id.'/seed', [
            'environment' => 'homologation',
            'xml' => $xml,
        ])->assertForbidden();
    }

    public function test_package_ingest_idempotente(): void
    {
        [$office, $user, $est] = $this->seedMaEstablishment();
        $this->actingAs($user);
        app(CurrentOffice::class)->resolve($user);

        $xml = file_get_contents(base_path('tests/fixtures/ma-outbound/procNFe_55_out_ma.xml'));
        $this->postJson('/api/v1/outbound/establishments/'.$est->id.'/seed', [
            'environment' => 'homologation',
            'xml' => $xml,
        ])->assertCreated();

        $profile = OutboundCaptureProfile::query()->where('establishment_id', $est->id)->firstOrFail();

        $file = UploadedFile::fake()->createWithContent('nfe.xml', $xml);
        $this->post('/api/v1/outbound/profiles/'.$profile->id.'/package', [
            'files' => [$file],
        ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.imported', 1);

        $file2 = UploadedFile::fake()->createWithContent('nfe-dup.xml', $xml);
        $this->post('/api/v1/outbound/profiles/'.$profile->id.'/package', [
            'files' => [$file2],
        ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.skipped', 1);
    }

    public function test_admin_csc_pode_ser_lido_na_ui(): void
    {
        [$office, $user, $est] = $this->seedMaEstablishment(OfficeRole::Admin);
        $this->actingAs($user);
        app(CurrentOffice::class)->resolve($user);

        $xml = file_get_contents(base_path('tests/fixtures/ma-outbound/procNFe_65_out_ma.xml'));
        $this->postJson('/api/v1/outbound/establishments/'.$est->id.'/seed', [
            'environment' => 'homologation',
            'xml' => $xml,
        ])->assertCreated();

        $profile = OutboundCaptureProfile::query()->where('model', '65')->firstOrFail();

        $token = 'TOKEN-CSC-EXIBIVEL-ADMIN';
        $response = $this->postJson('/api/v1/outbound/profiles/'.$profile->id.'/csc', [
            'csc' => $token,
            'csc_id' => '000001',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.configured', true)
            ->assertJsonPath('data.csc_id', '000001')
            ->assertJsonPath('data.csc', $token);

        // GET devolve o valor para preencher o formulário (ADMIN + 2FA)
        $this->getJson('/api/v1/outbound/profiles/'.$profile->id.'/csc')
            ->assertOk()
            ->assertJsonPath('data.csc', $token)
            ->assertJsonPath('data.csc_id', '000001');

        // Auditoria de reveal sem o valor do CSC
        $revealed = AuditLog::query()
            ->where('action', 'outbound.csc.revealed')
            ->orderByDesc('id')
            ->first();
        $this->assertNotNull($revealed);
        $context = $revealed->context ?? [];
        $this->assertSame($profile->id, $context['profile_id'] ?? null);
        $this->assertTrue((bool) ($context['revealed'] ?? false));
        $this->assertArrayNotHasKey('csc', $context);
        $contextJson = json_encode($context, JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString($token, $contextJson);
    }

    public function test_operator_nao_le_csc(): void
    {
        [$office, $admin, $est] = $this->seedMaEstablishment(OfficeRole::Admin);
        $this->actingAs($admin);
        app(CurrentOffice::class)->resolve($admin);

        $xml = file_get_contents(base_path('tests/fixtures/ma-outbound/procNFe_65_out_ma.xml'));
        $this->postJson('/api/v1/outbound/establishments/'.$est->id.'/seed', [
            'environment' => 'homologation',
            'xml' => $xml,
        ])->assertCreated();
        $profile = OutboundCaptureProfile::query()->where('model', '65')->firstOrFail();
        $this->postJson('/api/v1/outbound/profiles/'.$profile->id.'/csc', [
            'csc' => 'TOKEN-X',
            'csc_id' => '1',
        ])->assertOk();

        $operator = User::factory()->forOffice($office, OfficeRole::Operator)->withTwoFactorConfirmed()->create();
        $this->actingAs($operator);
        app(CurrentOffice::class)->resolve($operator);

        $this->getJson('/api/v1/outbound/profiles/'.$profile->id.'/csc')->assertForbidden();
    }

    public function test_admin_sem_2fa_nao_le_csc(): void
    {
        config(['fortify.two_factor_required' => true]);
        $office = Office::factory()->create();
        $adminNo2fa = User::factory()->forOffice($office, OfficeRole::Admin)->create([
            'two_factor_secret' => null,
            'two_factor_confirmed_at' => null,
        ]);
        $client = Client::factory()->forOffice($office)->create(['root_cnpj' => '12345678']);
        $est = Establishment::factory()->forClient($client)->create([
            'cnpj' => '12345678000190',
            'address_state' => 'MA',
            'capture_enabled' => true,
            'is_active' => true,
        ]);

        // Seed com operator 2FA para ter o profile, depois tenta reveal sem 2FA
        $operator = User::factory()->forOffice($office, OfficeRole::Operator)->withTwoFactorConfirmed()->create();
        $this->actingAs($operator);
        app(CurrentOffice::class)->resolve($operator);
        $xml = file_get_contents(base_path('tests/fixtures/ma-outbound/procNFe_65_out_ma.xml'));
        $this->postJson('/api/v1/outbound/establishments/'.$est->id.'/seed', [
            'environment' => 'homologation',
            'xml' => $xml,
        ])->assertCreated();
        $profile = OutboundCaptureProfile::query()->where('model', '65')->firstOrFail();

        $this->actingAs($adminNo2fa);
        app(CurrentOffice::class)->resolve($adminNo2fa);

        $this->getJson('/api/v1/outbound/profiles/'.$profile->id.'/csc')->assertForbidden();
        $this->postJson('/api/v1/outbound/profiles/'.$profile->id.'/csc', [
            'csc' => 'TOKEN-Y',
            'csc_id' => '2',
        ])->assertForbidden();
    }

    /**
     * @return array{0: Office, 1: User, 2: Establishment}
     */
    private function seedMaEstablishment(OfficeRole $role = OfficeRole::Operator): array
    {
        $office = Office::factory()->create();
        $user = User::factory()->forOffice($office, $role)->withTwoFactorConfirmed()->create();
        $client = Client::factory()->forOffice($office)->create(['root_cnpj' => '12345678']);
        $est = Establishment::factory()->forClient($client)->create([
            'cnpj' => '12345678000190',
            'address_state' => 'MA',
            'capture_enabled' => true,
            'is_active' => true,
        ]);

        return [$office, $user, $est];
    }
}

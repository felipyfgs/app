<?php

namespace Tests\Feature\Clients;

use App\Enums\CredentialStatus;
use App\Enums\OfficeRole;
use App\Enums\RegistrationStatus;
use App\Enums\SyncCursorStatus;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\ClientCredential;
use App\Models\Establishment;
use App\Models\Office;
use App\Models\SyncCursor;
use App\Models\User;
use App\Services\Clients\CaptureEligibilityService;
use App\Support\CurrentOffice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CaptureEligibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_scheduler_nao_dispara_quando_captura_desabilitada_e_preserva_nsu(): void
    {
        $office = Office::factory()->create();
        $client = Client::factory()->forOffice($office)->create(['is_active' => true]);
        $est = Establishment::factory()->forClient($client)->create([
            'is_active' => true,
            'capture_enabled' => false,
        ]);
        $this->makeCredential($client, $est);
        $cursor = SyncCursor::query()->create([
            'office_id' => $office->id,
            'establishment_id' => $est->id,
            'environment' => config('adn.environment', 'restricted_production'),
            'last_nsu' => 42,
            'status' => SyncCursorStatus::Idle,
            'next_sync_at' => now()->subMinute(),
        ]);

        // Força minuto preferencial: se next_sync_at preenchido, não depende do minuto
        $this->artisan('adn:dispatch-due-syncs')->assertSuccessful();

        $cursor->refresh();
        $this->assertSame(42, $cursor->last_nsu);
        $this->assertNull($cursor->lock_owner);
        $this->assertSame(SyncCursorStatus::Idle, $cursor->status);
    }

    public function test_disparo_manual_rejeita_inelegivel_com_motivos_sanitizados(): void
    {
        [$office, $user] = $this->officeUser(OfficeRole::Operator);
        $this->actingAs($user);
        app(CurrentOffice::class)->resolve($user);

        $client = Client::factory()->forOffice($office)->create(['is_active' => false]);
        $est = Establishment::factory()->forClient($client)->create([
            'capture_enabled' => true,
            'is_active' => true,
        ]);

        $this->postJson('/api/v1/sync-runs', ['establishment_id' => $est->id])
            ->assertStatus(422)
            ->assertJsonPath('data.eligible', false)
            ->assertJsonFragment(['client_inactive']);
    }

    public function test_disparo_manual_exige_permissao_semantica_de_sincronizacao(): void
    {
        [$office, $user] = $this->officeUser(OfficeRole::Viewer);
        $this->actingAs($user);
        app(CurrentOffice::class)->resolve($user);

        $client = Client::factory()->forOffice($office)->create(['is_active' => true]);
        $establishment = Establishment::factory()->forClient($client)->create([
            'capture_enabled' => true,
            'is_active' => true,
        ]);

        $this->postJson('/api/v1/sync-runs', ['establishment_id' => $establishment->id])
            ->assertForbidden();
    }

    public function test_disparo_manual_nao_resolve_estabelecimento_de_outro_escritorio(): void
    {
        [$officeA, $user] = $this->officeUser(OfficeRole::Operator);
        $officeB = Office::factory()->create();
        $clientB = Client::factory()->forOffice($officeB)->create(['is_active' => true]);
        $establishmentB = Establishment::factory()->forClient($clientB)->create([
            'capture_enabled' => true,
            'is_active' => true,
        ]);

        $this->actingAs($user);
        app(CurrentOffice::class)->resolve($user);

        $this->postJson('/api/v1/sync-runs', ['establishment_id' => $establishmentB->id])
            ->assertNotFound();

        $this->assertSame($officeA->id, app(CurrentOffice::class)->id());
    }

    public function test_habilitar_captura_com_situacao_nao_ativa_exige_motivo(): void
    {
        [$office, $user] = $this->officeUser(OfficeRole::Operator);
        $this->actingAs($user);
        app(CurrentOffice::class)->resolve($user);

        $client = Client::factory()->forOffice($office)->create();
        $est = Establishment::factory()->forClient($client)->create([
            'capture_enabled' => false,
            'registration_status' => RegistrationStatus::Suspended,
        ]);

        $this->patchJson("/api/v1/establishments/{$est->id}", [
            'capture_enabled' => true,
        ])->assertStatus(422);

        $reason = 'Cliente regularizado junto à RFB; captura revisada.';
        $this->patchJson("/api/v1/establishments/{$est->id}", [
            'capture_enabled' => true,
            'capture_enable_reason' => $reason,
        ])->assertOk()
            ->assertJsonPath('data.capture_enabled', true);

        // BUG-API-004: audit de capture_enable deve incluir o texto do motivo.
        $audit = AuditLog::query()
            ->where('action', 'establishment.capture_enable')
            ->where('subject_id', $est->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($audit);
        $context = $audit->context;
        if (is_string($context)) {
            $context = json_decode($context, true);
        }
        $this->assertIsArray($context);
        $this->assertSame($reason, $context['capture_enable_reason'] ?? null);
        $this->assertTrue((bool) ($context['capture_enable_reason_present'] ?? false));
    }

    public function test_servico_central_avalia_credencial_e_cursor(): void
    {
        $office = Office::factory()->create();
        $client = Client::factory()->forOffice($office)->create();
        $est = Establishment::factory()->forClient($client)->create([
            'capture_enabled' => true,
            'is_active' => true,
        ]);
        $service = app(CaptureEligibilityService::class);

        $withoutCred = $service->evaluate($est);
        $this->assertFalse($withoutCred['eligible']);
        $this->assertContains('credential_missing', $withoutCred['reasons_codes']);

        $this->makeCredential($client, $est);
        $cursor = SyncCursor::query()->create([
            'office_id' => $office->id,
            'establishment_id' => $est->id,
            'environment' => 'restricted_production',
            'last_nsu' => 1,
            'status' => SyncCursorStatus::Blocked,
        ]);

        $blocked = $service->evaluate($est->fresh(), $cursor);
        $this->assertFalse($blocked['eligible']);
        $this->assertContains('cursor_blocked', $blocked['reasons_codes']);
    }

    private function makeCredential(Client $client, Establishment $est): void
    {
        ClientCredential::query()->create([
            'office_id' => $client->office_id,
            'client_id' => $client->id,
            'status' => CredentialStatus::Active,
            'subject_name' => $client->legal_name,
            'holder_cnpj' => $est->cnpj,
            'fingerprint_sha256' => hash('sha256', 'test-'.$client->id),
            'valid_from' => now()->subYear(),
            'valid_to' => now()->addYear(),
            'vault_object_id' => (string) Str::ulid(),
            'activated_at' => now(),
        ]);
    }

    /**
     * @return array{0: Office, 1: User}
     */
    private function officeUser(OfficeRole $role): array
    {
        $office = Office::factory()->create();
        $user = User::factory()->forOffice($office, $role)->create();

        return [$office, $user];
    }
}

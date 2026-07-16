<?php

namespace Tests\Feature\Platform;

use App\Enums\OfficeAccessMode;
use App\Enums\OfficeRole;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\Office;
use App\Models\OfficeMembership;
use App\Models\PlatformPrivilegedAuditEvent;
use App\Models\User;
use App\Services\Auth\RecentPasswordConfirmationGate;
use App\Support\CurrentOffice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlatformPrivilegedContextTest extends TestCase
{
    use RefreshDatabase;

    private function enablePrivileged(): void
    {
        config([
            'features.kill_switch' => false,
            'features.platform_privileged_context.enabled' => true,
        ]);
    }

    public function test_flag_off_bloqueia_selecao_privilegiada(): void
    {
        config([
            'features.kill_switch' => false,
            'features.platform_privileged_context.enabled' => false,
        ]);

        $office = Office::factory()->create();
        $admin = User::factory()->asPlatformAdmin()->create();

        $this->actingAs($admin)
            ->postJson('/api/v1/platform/offices/select', ['office_id' => $office->id])
            ->assertForbidden()
            ->assertJsonPath('code', 'privileged_context_disabled');

        $this->assertNull($admin->fresh()->selected_office_id);
        $this->assertSame(0, OfficeMembership::query()->where('user_id', $admin->id)->count());
    }

    public function test_platform_admin_seleciona_office_sem_membership(): void
    {
        $this->enablePrivileged();

        $office = Office::factory()->create(['name' => 'Escritório Alvo']);
        $admin = User::factory()->asPlatformAdmin()->create([
            'selected_office_id' => null,
        ]);

        $this->actingAs($admin)
            ->postJson('/api/v1/platform/offices/select', ['office_id' => $office->id])
            ->assertOk()
            ->assertJsonPath('data.office.id', $office->id)
            ->assertJsonPath('data.role', OfficeRole::Admin->value)
            ->assertJsonPath('data.access_mode', OfficeAccessMode::PlatformPrivileged->value)
            ->assertJsonPath('data.actor_user_id', $admin->id);

        $admin->refresh();
        $this->assertNull($admin->selected_office_id);
        $this->assertSame(0, OfficeMembership::query()->where('user_id', $admin->id)->count());

        app(CurrentOffice::class)->clear();
        $this->actingAs($admin)
            ->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.office.id', $office->id)
            ->assertJsonPath('data.role', OfficeRole::Admin->value)
            ->assertJsonPath('data.access_mode', OfficeAccessMode::PlatformPrivileged->value);

        $this->assertTrue(
            PlatformPrivilegedAuditEvent::query()
                ->where('actor_user_id', $admin->id)
                ->where('office_id', $office->id)
                ->where('action', PlatformPrivilegedAuditEvent::ACTION_SELECT_OFFICE)
                ->where('result', PlatformPrivilegedAuditEvent::RESULT_SUCCESS)
                ->exists()
        );
    }

    public function test_usuario_comum_nao_usa_seletor_global(): void
    {
        $this->enablePrivileged();

        $office = Office::factory()->create();
        $user = User::factory()->forOffice($office, OfficeRole::Admin)->withTwoFactorConfirmed()->create();

        $this->actingAs($user)
            ->postJson('/api/v1/platform/offices/select', ['office_id' => $office->id])
            ->assertForbidden();

        $this->actingAs($user)
            ->getJson('/api/v1/platform/offices')
            ->assertForbidden();
    }

    public function test_leitura_fiscal_privilegiada_somente_office_selecionado(): void
    {
        $this->enablePrivileged();

        $officeA = Office::factory()->create();
        $officeB = Office::factory()->create();
        Client::factory()->forOffice($officeA)->create(['legal_name' => 'Cliente A']);
        Client::factory()->forOffice($officeB)->create(['legal_name' => 'Cliente B Segredo']);

        $admin = User::factory()->asPlatformAdmin()->create([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ]);

        $this->actingAs($admin)
            ->postJson('/api/v1/platform/offices/select', ['office_id' => $officeA->id])
            ->assertOk();

        app(CurrentOffice::class)->clear();

        $this->actingAs($admin)
            ->getJson('/api/v1/clients')
            ->assertOk()
            ->assertJsonFragment(['legal_name' => 'Cliente A'])
            ->assertJsonMissing(['legal_name' => 'Cliente B Segredo']);

        // Troca privilegiada para B
        $this->actingAs($admin)
            ->postJson('/api/v1/platform/offices/select', ['office_id' => $officeB->id])
            ->assertOk();

        app(CurrentOffice::class)->clear();

        $this->actingAs($admin)
            ->getJson('/api/v1/clients')
            ->assertOk()
            ->assertJsonFragment(['legal_name' => 'Cliente B Segredo'])
            ->assertJsonMissing(['legal_name' => 'Cliente A']);
    }

    public function test_office_id_no_body_query_e_ignorado_em_contexto_privilegiado(): void
    {
        $this->enablePrivileged();

        $officeA = Office::factory()->create();
        $officeB = Office::factory()->create();
        Client::factory()->forOffice($officeB)->create(['legal_name' => 'Nao Deve Aparecer']);

        $admin = User::factory()->asPlatformAdmin()->create();

        $this->actingAs($admin)
            ->postJson('/api/v1/platform/offices/select', ['office_id' => $officeA->id])
            ->assertOk();

        app(CurrentOffice::class)->clear();

        $this->actingAs($admin)
            ->getJson('/api/v1/clients?office_id='.$officeB->id)
            ->assertOk()
            ->assertJsonMissing(['legal_name' => 'Nao Deve Aparecer']);

        $this->actingAs($admin)
            ->getJson('/api/v1/office/subscription?office_id='.$officeB->id)
            ->assertOk()
            ->assertJsonPath('data.office_id', $officeA->id);
    }

    public function test_clear_selecao_remove_contexto_privilegiado(): void
    {
        $this->enablePrivileged();

        $office = Office::factory()->create();
        $admin = User::factory()->asPlatformAdmin()->create();

        $this->actingAs($admin)
            ->postJson('/api/v1/platform/offices/select', ['office_id' => $office->id])
            ->assertOk();

        $this->actingAs($admin)
            ->deleteJson('/api/v1/platform/offices/select')
            ->assertOk()
            ->assertJsonPath('data.cleared', true);

        app(CurrentOffice::class)->clear();

        $this->actingAs($admin)
            ->getJson('/api/v1/clients')
            ->assertForbidden()
            ->assertJsonPath('message', 'Usuário sem escritório ativo.');

        $this->assertTrue(
            PlatformPrivilegedAuditEvent::query()
                ->where('action', PlatformPrivilegedAuditEvent::ACTION_CLEAR_OFFICE)
                ->where('actor_user_id', $admin->id)
                ->exists()
        );
    }

    public function test_trilha_privilegiada_invisivel_ao_tenant(): void
    {
        $this->enablePrivileged();

        $office = Office::factory()->create();
        $member = User::factory()->forOffice($office, OfficeRole::Admin)->withTwoFactorConfirmed()->create();
        $admin = User::factory()->asPlatformAdmin()->create();

        PlatformPrivilegedAuditEvent::record(
            actorUserId: $admin->id,
            officeId: $office->id,
            action: PlatformPrivilegedAuditEvent::ACTION_READ,
            metadata: ['secret_note' => 'internal-only'],
        );

        // Tenant não tem endpoint de auditoria privilegiada; exports/audit_logs gerais
        // não incluem a tabela platform_privileged_audit_events.
        $this->actingAs($member)
            ->getJson('/api/v1/exports')
            ->assertOk();

        $exportsBody = $this->actingAs($member)->getJson('/api/v1/exports')->getContent();
        $this->assertStringNotContainsString('platform_privileged', (string) $exportsBody);
        $this->assertStringNotContainsString('internal-only', (string) $exportsBody);
        $this->assertStringNotContainsString(PlatformPrivilegedAuditEvent::ACTION_READ, (string) $exportsBody);

        // audit_logs do tenant (membership) ≠ trilha privilegiada
        $this->assertFalse(
            AuditLog::query()
                ->where('action', PlatformPrivilegedAuditEvent::ACTION_READ)
                ->exists()
        );
        $this->assertSame(1, PlatformPrivilegedAuditEvent::query()->count());
    }

    public function test_mutacao_privilegiada_exige_reconfirmacao_senha(): void
    {
        $this->enablePrivileged();

        $office = Office::factory()->create();
        $admin = User::factory()->asPlatformAdmin()->create([
            'password' => 'password',
        ]);

        $this->actingAs($admin)
            ->postJson('/api/v1/platform/offices/select', ['office_id' => $office->id])
            ->assertOk();

        app(CurrentOffice::class)->clear();

        // Sem reconfirmação → bloqueio na mutação de A1
        $this->actingAs($admin)
            ->postJson('/api/v1/office/fiscal-identity/credential', [
                'password' => 'x',
            ])
            ->assertForbidden()
            ->assertJsonPath('code', 'password_confirmation_required');

        // Confirma senha
        $this->actingAs($admin)
            ->postJson('/api/v1/auth/confirm-password', ['password' => 'password'])
            ->assertOk()
            ->assertJsonPath('data.confirmed', true);

        // Com janela ativa, middleware deixa passar (falha de validação de arquivo, não 403 de senha)
        $this->actingAs($admin)
            ->postJson('/api/v1/office/fiscal-identity/credential', [
                'password' => 'x',
            ])
            ->assertStatus(422);

        // Expiração da janela bloqueia de novo
        app(RecentPasswordConfirmationGate::class)->expire(user: $admin);

        $this->actingAs($admin)
            ->postJson('/api/v1/office/fiscal-identity/credential', [
                'password' => 'x',
            ])
            ->assertForbidden()
            ->assertJsonPath('code', 'password_confirmation_required');
    }

    public function test_senha_invalida_na_reconfirmacao(): void
    {
        $this->enablePrivileged();

        $admin = User::factory()->asPlatformAdmin()->create([
            'password' => 'password',
        ]);

        $this->actingAs($admin)
            ->postJson('/api/v1/auth/confirm-password', ['password' => 'wrong-password'])
            ->assertStatus(422)
            ->assertJsonPath('code', 'PASSWORD_INVALID');
    }

    public function test_platform_admin_sem_totp_acessa_seletor_e_navegacao(): void
    {
        $this->enablePrivileged();
        config()->set('fortify.two_factor_required', true);

        $office = Office::factory()->create();
        $admin = User::factory()->asPlatformAdmin()->create([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ]);

        $this->actingAs($admin)
            ->getJson('/api/v1/platform/offices')
            ->assertOk();

        $this->actingAs($admin)
            ->postJson('/api/v1/platform/offices/select', ['office_id' => $office->id])
            ->assertOk();

        app(CurrentOffice::class)->clear();

        // Navegação tenant com papel efetivo ADMIN sem TOTP setup
        $this->actingAs($admin)
            ->getJson('/api/v1/clients')
            ->assertOk();
    }

    public function test_sessao_privilegiada_usa_chave_separada(): void
    {
        $this->enablePrivileged();

        $office = Office::factory()->create();
        $admin = User::factory()->asPlatformAdmin()->create();

        $this->actingAs($admin)
            ->postJson('/api/v1/platform/offices/select', ['office_id' => $office->id])
            ->assertOk();

        $this->assertSame(
            $office->id,
            app(CurrentOffice::class)->platformSelectedOfficeId($admin)
        );
        // selected_office_id de membership permanece intocado
        $this->assertNull($admin->fresh()->selected_office_id);
        $this->assertNull(session(CurrentOffice::SESSION_KEY));
    }

    public function test_kill_switch_desliga_contexto_privilegiado(): void
    {
        config([
            'features.platform_privileged_context.enabled' => true,
            'features.kill_switch' => true,
        ]);

        $office = Office::factory()->create();
        $admin = User::factory()->asPlatformAdmin()->create();

        $this->actingAs($admin)
            ->postJson('/api/v1/platform/offices/select', ['office_id' => $office->id])
            ->assertForbidden()
            ->assertJsonPath('code', 'privileged_context_disabled');
    }
}

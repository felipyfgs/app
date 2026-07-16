<?php

namespace Tests\Feature\Serpro;

use App\Enums\OfficeRole;
use App\Models\AuditLog;
use App\Models\Office;
use App\Models\SerproRuntimeControl;
use App\Models\User;
use App\Services\Audit\AuditIntegrityService;
use App\Services\Audit\AuditLogger;
use App\Services\Serpro\SerproKillSwitchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class SerproPlatformSecurityApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_kill_switch_persiste_apos_flush_redis(): void
    {
        $admin = User::factory()->asPlatformAdmin()->create();
        $ks = app(SerproKillSwitchService::class);

        $this->actingAs($admin)->postJson('/api/v1/platform/serpro/kill-switch', [
            'active' => true,
            'reason' => 'drill contencao',
        ])->assertOk()
            ->assertJsonPath('data.global.active', true);

        $this->assertTrue($ks->isGlobalActive());
        $this->assertDatabaseHas('serpro_runtime_controls', [
            'control_key' => 'kill_switch.global',
            'active' => 1,
        ]);

        Cache::flush();
        // mesmo sem espelho Redis, DB mantém kill ativo
        $this->assertTrue(app(SerproKillSwitchService::class)->isGlobalActive());

        $status = $this->actingAs($admin)->getJson('/api/v1/platform/serpro/kill-switch');
        $status->assertOk()->assertJsonPath('data.global.active', true);
        $this->assertTrue((bool) $status->json('data.global.durable'));
    }

    public function test_desligar_kill_switch_exige_dois_platform_admins(): void
    {
        $a = User::factory()->asPlatformAdmin()->create();
        $b = User::factory()->asPlatformAdmin()->create();
        $ks = app(SerproKillSwitchService::class);
        $ks->activateGlobal('prep', $a->id);
        $this->assertTrue($ks->isGlobalActive());

        $first = $this->actingAs($a)->postJson('/api/v1/platform/serpro/kill-switch', [
            'active' => false,
            'reason' => 'tentativa reabrir',
        ]);
        $first->assertOk();
        $this->assertFalse((bool) $first->json('executed'));
        $this->assertTrue(app(SerproKillSwitchService::class)->isGlobalActive());
        $approvalId = $first->json('approval.id');
        $this->assertNotNull($approvalId);

        // mesmo admin não fecha o segundo olho
        $same = $this->actingAs($a)->postJson("/api/v1/platform/serpro/rollouts/{$approvalId}/approve", [
            'reason' => 'mesmo admin',
        ]);
        $same->assertStatus(422);

        $second = $this->actingAs($b)->postJson("/api/v1/platform/serpro/rollouts/{$approvalId}/approve", [
            'reason' => 'segundo olho',
        ]);
        $second->assertOk()->assertJsonPath('executed', true);
        $this->assertFalse(app(SerproKillSwitchService::class)->isGlobalActive());

        Cache::flush();
        $this->assertFalse(app(SerproKillSwitchService::class)->isGlobalActive());
    }

    public function test_platform_admin_sem_membership_nao_acessa_tenant_serpro(): void
    {
        Office::factory()->create();
        $admin = User::factory()->asPlatformAdmin()->create();

        $this->actingAs($admin)
            ->getJson('/api/v1/serpro/authorization')
            ->assertForbidden();

        $this->actingAs($admin)
            ->getJson('/api/v1/serpro/readiness')
            ->assertForbidden();
    }

    public function test_tenant_serpro_readiness_sanitizada_e_sem_vault(): void
    {
        $office = Office::factory()->create();
        $user = User::factory()->forOffice($office, OfficeRole::Admin)->withTwoFactorConfirmed()->create();

        $response = $this->actingAs($user)->getJson('/api/v1/serpro/readiness?environment=TRIAL');
        $response->assertOk();
        $body = (string) $response->getContent();
        $this->assertStringNotContainsString('vault_object', $body);
        $this->assertStringNotContainsString('consumer_secret', $body);
        $this->assertStringNotContainsString('BEGIN ', $body);
        $this->assertArrayHasKey('scope', $response->json('data'));
    }

    public function test_office_id_do_cliente_nao_muda_escopo_serpro_usage(): void
    {
        $officeA = Office::factory()->create();
        $officeB = Office::factory()->create();
        $user = User::factory()->forOffice($officeA, OfficeRole::Admin)->withTwoFactorConfirmed()->create();

        $response = $this->actingAs($user)->getJson('/api/v1/serpro/usage?office_id='.$officeB->id);
        $response->assertOk();
        // payload tenant não deve ser do office B; se houver office_id, é o do contexto
        $data = $response->json('data');
        if (is_array($data) && array_key_exists('office_id', $data)) {
            $this->assertSame($officeA->id, $data['office_id']);
        }
    }

    public function test_audit_chain_integridade_e_quebra_detectada(): void
    {
        $logger = app(AuditLogger::class);
        $logger->record('serpro.kill_switch.global_on', 'SUCCESS', null, [
            'reason' => 't1',
            'password' => 'must-redact',
        ], null, null);
        $logger->record('serpro.rollout.request', 'SUCCESS', null, [
            'action' => 'KILL_SWITCH_OFF',
        ], null, null);

        $integrity = app(AuditIntegrityService::class);
        $ok = $integrity->verify();
        $this->assertTrue($ok['ok']);
        $this->assertGreaterThanOrEqual(2, $ok['checked']);

        $row = AuditLog::query()->whereNotNull('entry_hash')->orderBy('chain_seq')->first();
        $this->assertNotNull($row);
        $this->assertSame('[redacted]', $row->context['password'] ?? null);

        // adulteração
        $row->forceFill(['action' => 'tampered.action'])->save();
        $broken = $integrity->verify();
        $this->assertFalse($broken['ok']);
        $this->assertSame('ENTRY_HASH_MISMATCH', $broken['reason_code']);
        $this->assertArrayNotHasKey('payload', $broken);
    }

    public function test_readiness_e_budgets_platform_sanitizados(): void
    {
        $admin = User::factory()->asPlatformAdmin()->create();

        $ready = $this->actingAs($admin)->getJson('/api/v1/platform/serpro/readiness?environment=TRIAL&persist=0');
        $ready->assertOk();
        $this->assertStringNotContainsString('vault', (string) $ready->getContent());

        $budgets = $this->actingAs($admin)->getJson('/api/v1/platform/serpro/budgets');
        $budgets->assertOk()->assertJsonStructure(['data']);
    }

    public function test_runtime_control_model_sanitized(): void
    {
        $ctrl = SerproRuntimeControl::query()->create([
            'control_key' => 'kill_switch.global',
            'control_type' => 'KILL_SWITCH',
            'active' => true,
            'source' => 'runtime',
            'reason' => 'test',
        ]);
        $arr = $ctrl->toSanitizedArray();
        $this->assertArrayNotHasKey('metadata', $arr);
        $this->assertTrue($arr['active']);
    }
}

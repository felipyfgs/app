<?php

namespace Tests\Unit\Platform;

use App\Models\Office;
use App\Models\PlatformPrivilegedAuditEvent;
use App\Models\User;
use App\Support\FeatureFlags;
use App\Support\PlatformPrivilegedContext;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use LogicException;
use Tests\TestCase;

class PlatformPrivilegedAuditEventTest extends TestCase
{
    use RefreshDatabase;

    public function test_session_key_constante_separada_de_membership(): void
    {
        $this->assertSame('platform_selected_office_id', PlatformPrivilegedContext::SESSION_KEY);
        $this->assertSame('platform_privileged', PlatformPrivilegedContext::ACCESS_MODE);
        $this->assertNotSame('current_office_id', PlatformPrivilegedContext::SESSION_KEY);
    }

    public function test_feature_flag_default_off_e_kill_switch_vence(): void
    {
        config([
            'features.kill_switch' => false,
            'features.platform_privileged_context.enabled' => false,
        ]);
        $this->assertFalse(FeatureFlags::isPlatformPrivilegedContextEnabled());

        config(['features.platform_privileged_context.enabled' => true]);
        $this->assertTrue(FeatureFlags::isPlatformPrivilegedContextEnabled());

        config(['features.kill_switch' => true]);
        $this->assertFalse(FeatureFlags::isPlatformPrivilegedContextEnabled());
    }

    public function test_config_env_default_e_false(): void
    {
        // Sem override de env, o default em features.php é false.
        $this->assertFalse((bool) config('features.platform_privileged_context.enabled'));
    }

    public function test_cria_evento_com_constraints_basicas(): void
    {
        $actor = User::factory()->create();
        $office = Office::factory()->create();

        $event = PlatformPrivilegedAuditEvent::record(
            actorUserId: $actor->id,
            officeId: $office->id,
            action: PlatformPrivilegedAuditEvent::ACTION_SELECT_OFFICE,
            result: PlatformPrivilegedAuditEvent::RESULT_SUCCESS,
            targetType: Office::class,
            targetId: $office->id,
            requestId: 'corr-abc-1',
            metadata: ['access_mode' => PlatformPrivilegedContext::ACCESS_MODE],
        );

        $this->assertDatabaseHas('platform_privileged_audit_events', [
            'id' => $event->id,
            'actor_user_id' => $actor->id,
            'office_id' => $office->id,
            'action' => PlatformPrivilegedAuditEvent::ACTION_SELECT_OFFICE,
            'result' => PlatformPrivilegedAuditEvent::RESULT_SUCCESS,
            'request_id' => 'corr-abc-1',
            'target_id' => $office->id,
        ]);
        $this->assertNotNull($event->created_at);
        $this->assertTrue($event->actor->is($actor));
        $this->assertTrue($event->office->is($office));
    }

    public function test_factory_persiste(): void
    {
        $event = PlatformPrivilegedAuditEvent::factory()->create();

        $this->assertDatabaseCount('platform_privileged_audit_events', 1);
        $this->assertNotNull($event->actor_user_id);
        $this->assertNotNull($event->office_id);
    }

    public function test_metadata_sensivel_e_sanitizada_e_campos_seguros_retidos(): void
    {
        $actor = User::factory()->create();
        $office = Office::factory()->create();

        $event = PlatformPrivilegedAuditEvent::record(
            actorUserId: $actor->id,
            officeId: $office->id,
            action: PlatformPrivilegedAuditEvent::ACTION_MUTATE,
            metadata: [
                'password' => 'super-secret',
                'pfx' => 'binary',
                'token' => 'jwt-like',
                'access_mode' => PlatformPrivilegedContext::ACCESS_MODE,
                'reason' => 'replace_a1',
                'nested' => ['private_key' => '-----BEGIN PRIVATE KEY-----'],
            ],
        );

        $event->refresh();
        $meta = $event->metadata;

        $this->assertIsArray($meta);
        $this->assertSame('[redacted]', $meta['password']);
        $this->assertSame('[redacted]', $meta['pfx']);
        $this->assertSame('[redacted]', $meta['token']);
        $this->assertSame('[redacted]', $meta['nested']['private_key']);
        $this->assertSame(PlatformPrivilegedContext::ACCESS_MODE, $meta['access_mode']);
        $this->assertSame('replace_a1', $meta['reason']);
    }

    public function test_factory_with_sensitive_metadata_sanitiza_na_criacao(): void
    {
        $event = PlatformPrivilegedAuditEvent::factory()->withSensitiveMetadata()->create();
        $event->refresh();

        $this->assertSame('[redacted]', $event->metadata['password']);
        $this->assertSame('[redacted]', $event->metadata['pfx']);
        $this->assertSame('platform_privileged', $event->metadata['access_mode']);
        $this->assertSame('acme', $event->metadata['office_slug']);
    }

    public function test_update_e_proibido(): void
    {
        $event = PlatformPrivilegedAuditEvent::factory()->create();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('append-only');

        $event->action = PlatformPrivilegedAuditEvent::ACTION_CLEAR_OFFICE;
        $event->save();
    }

    public function test_delete_e_proibido(): void
    {
        $event = PlatformPrivilegedAuditEvent::factory()->create();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('append-only');

        $event->delete();
    }

    public function test_actor_user_id_e_obrigatorio(): void
    {
        $office = Office::factory()->create();

        $this->expectException(QueryException::class);

        PlatformPrivilegedAuditEvent::query()->create([
            'actor_user_id' => null,
            'office_id' => $office->id,
            'action' => PlatformPrivilegedAuditEvent::ACTION_READ,
            'result' => PlatformPrivilegedAuditEvent::RESULT_SUCCESS,
            'created_at' => now(),
        ]);
    }

    public function test_office_id_e_obrigatorio(): void
    {
        $actor = User::factory()->create();

        $this->expectException(QueryException::class);

        PlatformPrivilegedAuditEvent::query()->create([
            'actor_user_id' => $actor->id,
            'office_id' => null,
            'action' => PlatformPrivilegedAuditEvent::ACTION_READ,
            'result' => PlatformPrivilegedAuditEvent::RESULT_SUCCESS,
            'created_at' => now(),
        ]);
    }

    public function test_nao_ha_coluna_updated_at(): void
    {
        $event = PlatformPrivilegedAuditEvent::factory()->create();
        $attrs = $event->getAttributes();

        $this->assertArrayNotHasKey('updated_at', $attrs);
        $this->assertFalse(
            Schema::hasColumn('platform_privileged_audit_events', 'updated_at')
        );
    }
}

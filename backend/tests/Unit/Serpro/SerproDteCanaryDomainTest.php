<?php

namespace Tests\Unit\Serpro;

use App\Enums\OfficeRole;
use App\Enums\SerproAuthorizationStatus;
use App\Enums\SerproCredentialVersionStatus;
use App\Enums\SerproDataSegregationClass;
use App\Enums\SerproDteCanaryRequestStatus;
use App\Enums\SerproDteControlMode;
use App\Enums\SerproEnvironment;
use App\Enums\SerproExternalGateStatus;
use App\Enums\TaxProxyPowerSource;
use App\Enums\TaxProxyPowerStatus;
use App\Models\Client;
use App\Models\Office;
use App\Models\OfficeCredential;
use App\Models\OfficeMembership;
use App\Models\OfficeSerproAuthorization;
use App\Models\SerproCredentialConnectionEvidence;
use App\Models\SerproCredentialVersion;
use App\Models\SerproDteCanaryRequest;
use App\Models\SerproExternalGate;
use App\Models\SerproOfficeQuantityUsageLimit;
use App\Models\SerproQuantityUsageLimit;
use App\Models\TaxProxyPower;
use App\Models\User;
use App\Services\Serpro\SerproDteCanaryService;
use App\Services\Serpro\SerproExternalGateService;
use App\Support\Serpro\DteCanaryCoordinates;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

final class SerproDteCanaryDomainTest extends TestCase
{
    use RefreshDatabase;

    public function test_migration_cria_tabelas_e_rollback(): void
    {
        $this->assertTrue(Schema::hasTable('serpro_dte_controls'));
        $this->assertTrue(Schema::hasTable('serpro_dte_canary_requests'));
        $this->assertTrue(Schema::hasColumn('serpro_dte_canary_requests', 'operation_key'));
        $this->assertTrue(Schema::hasColumn('serpro_dte_canary_requests', 'id_sistema'));
        $this->assertTrue(Schema::hasColumn('serpro_dte_canary_requests', 'idempotency_key'));
        $this->assertTrue(Schema::hasColumn('serpro_dte_controls', 'mode'));
    }

    public function test_create_request_fixa_coordenadas_dte(): void
    {
        $owner = User::factory()->asPlatformAdmin()->create();
        $svc = app(SerproDteCanaryService::class);

        $req = $svc->createRequest($owner->id);

        $this->assertSame(DteCanaryCoordinates::OPERATION_KEY, $req->operation_key);
        $this->assertSame(DteCanaryCoordinates::ID_SISTEMA, $req->id_sistema);
        $this->assertSame(DteCanaryCoordinates::ID_SERVICO, $req->id_servico);
        $this->assertSame(DteCanaryCoordinates::FUNCTIONAL_ROUTE, $req->functional_route);
        $this->assertSame(DteCanaryCoordinates::REQUIRED_PROXY_POWER, $req->required_proxy_power);
        $this->assertSame(SerproDteCanaryRequestStatus::Draft, $req->status);
        $this->assertSame(SerproEnvironment::Production, $req->environment);
    }

    public function test_select_target_office_production_e_cliente_do_office(): void
    {
        $owner = User::factory()->asPlatformAdmin()->create();
        $office = Office::factory()->create([
            'serpro_segregation_class' => SerproDataSegregationClass::Production->value,
        ]);
        $client = Client::factory()->create(['office_id' => $office->id]);
        $svc = app(SerproDteCanaryService::class);
        $req = $svc->createRequest($owner->id);

        $updated = $svc->selectTarget($req, $office->id, $client->id, $owner->id);

        $this->assertSame(SerproDteCanaryRequestStatus::TargetSet, $updated->status);
        $this->assertSame($office->id, $updated->office_id);
        $this->assertSame($client->id, $updated->client_id);
        $this->assertNotNull($updated->idempotency_key);
        $this->assertSame(SerproDteControlMode::Canary, $svc->ensureControl()->mode);
    }

    public function test_select_target_rejeita_cliente_cross_tenant(): void
    {
        $owner = User::factory()->asPlatformAdmin()->create();
        $officeA = Office::factory()->create([
            'serpro_segregation_class' => SerproDataSegregationClass::Production->value,
        ]);
        $officeB = Office::factory()->create([
            'serpro_segregation_class' => SerproDataSegregationClass::Production->value,
        ]);
        $clientB = Client::factory()->create(['office_id' => $officeB->id]);
        $svc = app(SerproDteCanaryService::class);
        $req = $svc->createRequest($owner->id);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('não pertence');
        $svc->selectTarget($req, $officeA->id, $clientB->id, $owner->id);
    }

    public function test_select_target_rejeita_office_demo(): void
    {
        $owner = User::factory()->asPlatformAdmin()->create();
        $office = Office::factory()->create([
            'serpro_segregation_class' => SerproDataSegregationClass::Demo->value,
            'slug' => 'demo-escritorio-x',
        ]);
        $client = Client::factory()->create(['office_id' => $office->id]);
        $svc = app(SerproDteCanaryService::class);
        $req = $svc->createRequest($owner->id);

        $this->expectException(RuntimeException::class);
        $svc->selectTarget($req, $office->id, $client->id, $owner->id);
    }

    public function test_dual_approval_rejeita_mesma_conta(): void
    {
        $owner = User::factory()->asPlatformAdmin()->create();
        $office = Office::factory()->create([
            'serpro_segregation_class' => SerproDataSegregationClass::Production->value,
        ]);
        // Conta dual: platform admin + office admin
        $office->users()->attach($owner->id, [
            'role' => OfficeRole::Admin->value,
            'is_active' => true,
        ]);
        $client = Client::factory()->create(['office_id' => $office->id]);
        $svc = app(SerproDteCanaryService::class);
        $req = $svc->createRequest($owner->id);
        $req = $svc->selectTarget($req, $office->id, $client->id, $owner->id);

        $req = $svc->approveAsOwner($req, $owner, true);
        $this->assertTrue($req->hasOwnerApproval());
        $this->assertFalse($req->isFullyApproved());

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Conta dual');
        $svc->approveAsOfficeAdmin($req, $owner, $office, true);
    }

    public function test_dual_approval_dois_usuarios_distintos(): void
    {
        $owner = User::factory()->asPlatformAdmin()->create();
        $office = Office::factory()->create([
            'serpro_segregation_class' => SerproDataSegregationClass::Production->value,
        ]);
        $admin = User::factory()->forOffice($office, OfficeRole::Admin)->create();
        $client = Client::factory()->create(['office_id' => $office->id]);
        $svc = app(SerproDteCanaryService::class);
        $req = $svc->createRequest($owner->id);
        $req = $svc->selectTarget($req, $office->id, $client->id, $owner->id);
        $req = $svc->approveAsOwner($req, $owner, true);
        $req = $svc->approveAsOfficeAdmin($req, $admin, $office, true);

        $this->assertTrue($req->isFullyApproved());
        $this->assertSame(SerproDteCanaryRequestStatus::FullyApproved, $req->status);
    }

    public function test_approve_sem_senha_recente_falha(): void
    {
        $owner = User::factory()->asPlatformAdmin()->create();
        $office = Office::factory()->create([
            'serpro_segregation_class' => SerproDataSegregationClass::Production->value,
        ]);
        $client = Client::factory()->create(['office_id' => $office->id]);
        $svc = app(SerproDteCanaryService::class);
        $req = $svc->createRequest($owner->id);
        $req = $svc->selectTarget($req, $office->id, $client->id, $owner->id);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('senha');
        $svc->approveAsOwner($req, $owner, false);
    }

    public function test_global_dto_nao_inclui_payload_fiscal(): void
    {
        $req = SerproDteCanaryRequest::query()->create([
            'environment' => SerproEnvironment::Production,
            'status' => SerproDteCanaryRequestStatus::Succeeded,
            'operation_key' => DteCanaryCoordinates::OPERATION_KEY,
            'id_sistema' => DteCanaryCoordinates::ID_SISTEMA,
            'id_servico' => DteCanaryCoordinates::ID_SERVICO,
            'service_version' => DteCanaryCoordinates::SERVICE_VERSION,
            'functional_route' => DteCanaryCoordinates::FUNCTIONAL_ROUTE,
            'required_proxy_power' => DteCanaryCoordinates::REQUIRED_PROXY_POWER,
            'result_status' => 'SUCCEEDED',
            'consumption_quantity' => 1,
            'correlation_id' => 'corr-1',
        ]);

        $dto = $req->toGlobalSanitizedArray();
        $json = json_encode($dto, JSON_THROW_ON_ERROR);

        $this->assertArrayNotHasKey('dados', $dto);
        $this->assertArrayNotHasKey('fiscal_result', $dto);
        $this->assertArrayNotHasKey('body', $dto);
        $this->assertArrayNotHasKey('mensagens', $dto);
        $this->assertStringNotContainsString('fiscal_result', $json);
        $this->assertSame('SUCCEEDED', $dto['result_status']);
        $this->assertSame(1, $dto['consumption_quantity']);
    }

    public function test_gate_bloqueia_sem_aprovacao_e_kill_switch(): void
    {
        $owner = User::factory()->asPlatformAdmin()->create();
        $office = Office::factory()->create([
            'serpro_segregation_class' => SerproDataSegregationClass::Production->value,
        ]);
        $client = Client::factory()->create(['office_id' => $office->id]);
        $svc = app(SerproDteCanaryService::class);
        $req = $svc->createRequest($owner->id);
        $req = $svc->selectTarget($req, $office->id, $client->id, $owner->id);

        $gate = $svc->evaluatePreTransportGate($req);
        $this->assertFalse($gate['allowed']);
        $this->assertContains('APPROVAL_INCOMPLETE', $gate['blockers']);

        config(['serpro.kill_switch' => true]);
        $gate2 = $svc->evaluatePreTransportGate($req->fresh());
        $this->assertContains('KILL_SWITCH', $gate2['blockers']);
        config(['serpro.kill_switch' => false]);
    }

    public function test_replay_e_uncertain_sem_novo_transporte(): void
    {
        $owner = User::factory()->asPlatformAdmin()->create();
        $svc = app(SerproDteCanaryService::class);
        $req = $svc->createRequest($owner->id);
        $req->forceFill([
            'status' => SerproDteCanaryRequestStatus::Succeeded,
            'result_status' => 'SUCCEEDED',
            'consumption_quantity' => 1,
            'office_id' => 1,
            'client_id' => 1,
        ])->save();

        $result = $svc->execute($req->fresh(), $owner->id);
        $this->assertTrue($result['replay']);
        $this->assertSame(1, $result['global']['consumption_quantity']);

        $req->forceFill([
            'status' => SerproDteCanaryRequestStatus::Uncertain,
            'result_status' => 'UNCERTAIN',
        ])->save();

        $uncertain = $svc->execute($req->fresh(), $owner->id);
        $this->assertTrue($uncertain['replay']);
        $this->assertSame('UNCERTAIN', $uncertain['global']['result_status']);
    }

    public function test_reconcile_e_promote_limited_mesmo_office_teto_dez(): void
    {
        $owner = User::factory()->asPlatformAdmin()->create();
        $office = Office::factory()->create([
            'serpro_segregation_class' => SerproDataSegregationClass::Production->value,
        ]);
        $svc = app(SerproDteCanaryService::class);
        $req = $svc->createRequest($owner->id);
        $req->forceFill([
            'office_id' => $office->id,
            'client_id' => 99,
            'status' => SerproDteCanaryRequestStatus::Succeeded,
            'result_status' => 'SUCCEEDED',
            'consumption_quantity' => 1,
        ])->save();

        $req = $svc->reconcile($req, $owner->id, 'AREA-CLIENTE-REF-1', 'Histórico confere 1 consulta DTE.', true);
        $this->assertSame(SerproDteCanaryRequestStatus::Reconciled, $req->status);

        $control = $svc->promoteLimited(
            $req,
            $owner,
            true,
            SerproDteCanaryService::CONFIRM_PROMOTE_PHRASE,
            'Canário reconciliado com sucesso.',
            maxQuantity: 10,
        );

        $this->assertSame(SerproDteControlMode::Limited, $control->mode);
        $this->assertSame($office->id, $control->pilot_office_id);
        $this->assertSame(10, $control->limited_max_quantity);
        $this->assertSame(0, $control->limited_used_quantity);
    }

    public function test_promote_sem_reconciliacao_bloqueia(): void
    {
        $owner = User::factory()->asPlatformAdmin()->create();
        $svc = app(SerproDteCanaryService::class);
        $req = $svc->createRequest($owner->id);
        $req->forceFill([
            'office_id' => 1,
            'status' => SerproDteCanaryRequestStatus::Succeeded,
            'result_status' => 'SUCCEEDED',
        ])->save();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('reconciliação');
        $svc->promoteLimited(
            $req,
            $owner,
            true,
            SerproDteCanaryService::CONFIRM_PROMOTE_PHRASE,
            'sem reconciliação',
        );
    }

    public function test_disable_bloqueia_novas_reservas(): void
    {
        $owner = User::factory()->asPlatformAdmin()->create();
        $svc = app(SerproDteCanaryService::class);
        $svc->ensureControl()->forceFill(['mode' => SerproDteControlMode::Limited])->save();

        $control = $svc->disable(
            $owner,
            true,
            SerproDteCanaryService::CONFIRM_DISABLE_PHRASE,
            'incidente operacional',
        );

        $this->assertSame(SerproDteControlMode::Disabled, $control->mode);
        $this->assertFalse($control->mode->allowsNewReservation());
    }

    public function test_tenant_result_exige_membership(): void
    {
        $owner = User::factory()->asPlatformAdmin()->create();
        $office = Office::factory()->create([
            'serpro_segregation_class' => SerproDataSegregationClass::Production->value,
        ]);
        $member = User::factory()->forOffice($office, OfficeRole::Viewer)->create();
        $svc = app(SerproDteCanaryService::class);
        $req = $svc->createRequest($owner->id);
        $req->forceFill([
            'office_id' => $office->id,
            'status' => SerproDteCanaryRequestStatus::Succeeded,
            'result_status' => 'SUCCEEDED',
        ])->save();

        $ok = $svc->tenantResult($req, $member, $office);
        $this->assertArrayHasKey('fiscal_result', $ok);

        $this->expectException(RuntimeException::class);
        $svc->tenantResult($req, $owner, $office);
    }

    public function test_gate_power_00050_e_limites(): void
    {
        $owner = User::factory()->asPlatformAdmin()->create();
        $office = Office::factory()->create([
            'serpro_segregation_class' => SerproDataSegregationClass::Production->value,
        ]);
        $admin = User::factory()->forOffice($office, OfficeRole::Admin)->create();
        $client = Client::factory()->create(['office_id' => $office->id]);
        $svc = app(SerproDteCanaryService::class);
        $req = $svc->createRequest($owner->id);
        $req = $svc->selectTarget($req, $office->id, $client->id, $owner->id);
        $req = $svc->approveAsOwner($req, $owner, true);
        $req = $svc->approveAsOfficeAdmin($req, $admin, $office, true);

        $gate = $svc->evaluatePreTransportGate($req);
        $this->assertFalse($gate['allowed']);
        $this->assertTrue(
            in_array('PROXY_POWER_00050_MISSING', $gate['blockers'], true)
            || in_array('A1_INVALID', $gate['blockers'], true)
            || in_array('CREDENTIAL_NOT_ACTIVE', $gate['blockers'], true)
            || in_array('EXTERNAL_GATES_OPEN', $gate['blockers'], true)
            || in_array('QUANTITY_LIMITS_NOT_POSITIVE', $gate['blockers'], true)
            || in_array('TERMO_NOT_ACCEPTED', $gate['blockers'], true)
            || in_array('OAUTH_EVIDENCE_MISSING', $gate['blockers'], true)
        );
    }

    public function test_gate_revalida_membership_admin_e_platform_admin_ativos_antes_do_transporte(): void
    {
        [$req, $owner, $admin, $office] = $this->makeDispatchReadyRequest(SerproDteControlMode::Canary);
        $svc = app(SerproDteCanaryService::class);

        $this->assertTrue($svc->evaluatePreTransportGate($req)['allowed']);

        OfficeMembership::query()
            ->where('office_id', $office->id)
            ->where('user_id', $admin->id)
            ->update(['role' => OfficeRole::Operator->value]);
        $revokedOfficeRole = $svc->evaluatePreTransportGate($req->fresh());
        $this->assertFalse($revokedOfficeRole['allowed']);
        $this->assertContains('APPROVAL_INCOMPLETE', $revokedOfficeRole['blockers']);

        OfficeMembership::query()
            ->where('office_id', $office->id)
            ->where('user_id', $admin->id)
            ->update(['role' => OfficeRole::Admin->value]);
        $owner->forceFill(['is_active' => false])->save();
        $revokedOwner = $svc->evaluatePreTransportGate($req->fresh());
        $this->assertFalse($revokedOwner['allowed']);
        $this->assertContains('APPROVAL_INCOMPLETE', $revokedOwner['blockers']);
        $this->assertNull($req->fresh()->dispatched_at);
    }

    public function test_segundo_pedido_canary_e_bloqueado_antes_de_novo_dispatch(): void
    {
        [$first, $owner, $admin, $office, $client] = $this->makeDispatchReadyRequest(SerproDteControlMode::Canary);
        $first->forceFill([
            'status' => SerproDteCanaryRequestStatus::Dispatched,
            'dispatched_at' => now(),
            'correlation_id' => 'first-dispatch',
        ])->save();

        $second = app(SerproDteCanaryService::class)->createRequest($owner->id);
        $second->forceFill([
            'office_id' => $office->id,
            'client_id' => $client->id,
            'selected_by_user_id' => $owner->id,
            'selected_at' => now(),
            'status' => SerproDteCanaryRequestStatus::FullyApproved,
            'owner_approver_user_id' => $owner->id,
            'owner_approved_at' => now(),
            'office_admin_approver_user_id' => $admin->id,
            'office_admin_approved_at' => now(),
            'idempotency_key' => hash('sha256', 'second-canary-request'),
        ])->save();

        $gate = app(SerproDteCanaryService::class)->evaluatePreTransportGate($second->fresh());
        $this->assertFalse($gate['allowed']);
        $this->assertContains('CANARY_ALREADY_DISPATCHED', $gate['blockers']);
        $this->assertFalse($gate['checks']['canary_single_shot']);
        $this->assertNull($second->fresh()->dispatched_at);
    }

    public function test_segundo_pedido_canary_nao_pode_trocar_office_ou_cliente_piloto(): void
    {
        [$first, $owner] = $this->makeDispatchReadyRequest(SerproDteControlMode::Canary);
        $otherOffice = Office::factory()->create([
            'serpro_segregation_class' => SerproDataSegregationClass::Production->value,
        ]);
        $otherClient = Client::factory()->create(['office_id' => $otherOffice->id]);
        $second = app(SerproDteCanaryService::class)->createRequest($owner->id);

        try {
            app(SerproDteCanaryService::class)->selectTarget(
                $second,
                $otherOffice->id,
                $otherClient->id,
                $owner->id,
            );
            $this->fail('alvo piloto CANARY deveria permanecer imutável');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('já foi fixado', $e->getMessage());
        }

        $this->assertNull($second->fresh()->office_id);
        $this->assertNotNull($first->fresh()->office_id);
    }

    public function test_limited_reserva_teto_em_falha_e_replay_nao_duplica_consumo(): void
    {
        [$req, $owner, $admin, $office, $client] = $this->makeDispatchReadyRequest(SerproDteControlMode::Limited);
        $svc = app(SerproDteCanaryService::class);

        $result = $svc->execute($req, $owner->id);
        $this->assertFalse($result['replay']);
        $this->assertSame(1, $svc->ensureControl()->fresh()->limited_used_quantity);
        $this->assertSame(1, $result['request']->consumption_quantity);

        $replay = $svc->execute($result['request']->fresh(), $owner->id);
        $this->assertTrue($replay['replay']);
        $this->assertSame(1, $svc->ensureControl()->fresh()->limited_used_quantity);

        $second = $svc->createRequest($owner->id);
        $second = $svc->selectTarget($second, $office->id, $client->id, $owner->id);
        $second = $svc->approveAsOwner($second, $owner, true);
        $second = $svc->approveAsOfficeAdmin($second, $admin, $office, true);

        try {
            $svc->execute($second, $owner->id);
            $this->fail('teto LIMITED deveria impedir nova reserva');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('LIMITED_QUOTA_EXHAUSTED', $e->getMessage());
        }
        $this->assertSame(1, $svc->ensureControl()->fresh()->limited_used_quantity);
        $this->assertNull($second->fresh()->dispatched_at);
    }

    /**
     * @return array{SerproDteCanaryRequest, User, User, Office, Client}
     */
    private function makeDispatchReadyRequest(SerproDteControlMode $mode): array
    {
        config([
            'serpro.kill_switch' => false,
            'features.kill_switch' => false,
            'serpro.default_environment' => SerproEnvironment::Production->value,
        ]);

        $owner = User::factory()->asPlatformAdmin()->create();
        $office = Office::factory()->create([
            'serpro_segregation_class' => SerproDataSegregationClass::Production->value,
        ]);
        $admin = User::factory()->forOffice($office, OfficeRole::Admin)->create();
        $client = Client::factory()->create(['office_id' => $office->id]);
        $svc = app(SerproDteCanaryService::class);
        $req = $svc->createRequest($owner->id);
        $req = $svc->selectTarget($req, $office->id, $client->id, $owner->id);
        $req = $svc->approveAsOwner($req, $owner, true);
        $req = $svc->approveAsOfficeAdmin($req, $admin, $office, true);

        $svc->ensureControl()->forceFill([
            'mode' => $mode,
            'pilot_office_id' => $office->id,
            'pilot_client_id' => $client->id,
            'limited_max_quantity' => $mode === SerproDteControlMode::Limited ? 1 : null,
            'limited_used_quantity' => 0,
        ])->save();

        $credential = SerproCredentialVersion::query()->create([
            'environment' => SerproEnvironment::Production,
            'version_number' => 1,
            'status' => SerproCredentialVersionStatus::Active,
            'was_exposed' => false,
            'fingerprint_sha256' => hash('sha256', 'production-credential'),
            'contractor_cnpj' => '11222333000181',
            'activated_at' => now(),
        ]);
        SerproCredentialConnectionEvidence::query()->create([
            'serpro_credential_version_id' => $credential->id,
            'environment' => SerproEnvironment::Production,
            'fingerprint_sha256' => $credential->fingerprint_sha256,
            'success' => true,
            'tested_at' => now(),
            'expires_at' => now()->addMinutes(15),
            'invalidated' => false,
        ]);

        app(SerproExternalGateService::class)->ensureBaselineGates();
        SerproExternalGate::query()->update([
            'status' => SerproExternalGateStatus::Accepted->value,
            'ticket_ref' => 'TEST-ACCEPTED',
            'answer_summary' => 'Aceite isolado de teste.',
            'responsible_name' => 'Teste automatizado',
            'reference_date' => now()->toDateString(),
            'accepted_at' => now(),
        ]);

        OfficeCredential::factory()->canonical()->forOffice($office)->create();
        $authorization = OfficeSerproAuthorization::query()->create([
            'office_id' => $office->id,
            'environment' => SerproEnvironment::Production,
            'status' => SerproAuthorizationStatus::TokenActive,
            'author_identity_type' => 'CNPJ',
            'author_identity' => '11222333000181',
            'certificate_mode' => 'EXTERNAL_SIGNATURE',
            'termo_valid_to' => now()->addDay(),
        ]);
        TaxProxyPower::query()->create([
            'office_id' => $office->id,
            'client_id' => $client->id,
            'office_serpro_authorization_id' => $authorization->id,
            'environment' => SerproEnvironment::Production->value,
            'author_identity' => '11222333000181',
            'contributor_cnpj' => '11222333000181',
            'system_code' => 'DTE',
            'service_code' => DteCanaryCoordinates::ID_SERVICO,
            'power_code' => DteCanaryCoordinates::REQUIRED_PROXY_POWER,
            'source' => TaxProxyPowerSource::ManualOfficialEvidence,
            'provenance' => 'MANUAL_APPROVED',
            'status' => TaxProxyPowerStatus::Active,
            'accepted_at' => now(),
            'freshness_checked_at' => now(),
            'segregation_class' => SerproDataSegregationClass::Production->value,
        ]);
        SerproQuantityUsageLimit::query()->create([
            'environment' => SerproEnvironment::Production,
            'global_limit_quantity' => 10,
            'is_active' => true,
        ]);
        SerproOfficeQuantityUsageLimit::query()->create([
            'office_id' => $office->id,
            'environment' => SerproEnvironment::Production,
            'limit_quantity' => 10,
            'is_active' => true,
        ]);

        $this->assertTrue($svc->evaluatePreTransportGate($req->fresh())['allowed']);

        return [$req->fresh(), $owner, $admin, $office, $client];
    }
}

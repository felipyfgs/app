<?php

namespace Tests\Feature\Serpro;

use App\Enums\OfficeRole;
use App\Enums\SerproDataSegregationClass;
use App\Enums\SerproDteCanaryRequestStatus;
use App\Enums\SerproDteControlMode;
use App\Models\Client;
use App\Models\Office;
use App\Models\SerproDteCanaryRequest;
use App\Models\User;
use App\Services\Serpro\SerproDteCanaryService;
use App\Support\CurrentOffice;
use App\Support\Serpro\DteCanaryCoordinates;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class SerproDteCanaryApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_summary_exige_platform_admin(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)
            ->getJson('/api/v1/platform/serpro/dte-canary')
            ->assertForbidden();

        $owner = User::factory()->asPlatformAdmin()->create();
        $this->actingAs($owner)
            ->getJson('/api/v1/platform/serpro/dte-canary')
            ->assertOk()
            ->assertJsonPath('data.coordinates.operation_key', DteCanaryCoordinates::OPERATION_KEY)
            ->assertJsonPath('data.control.mode', SerproDteControlMode::Disabled->value);
    }

    public function test_create_target_e_rejeita_coordenadas_livres(): void
    {
        $owner = User::factory()->asPlatformAdmin()->create(['password' => 'password']);
        $office = Office::factory()->create([
            'serpro_segregation_class' => SerproDataSegregationClass::Production->value,
        ]);
        $client = Client::factory()->create(['office_id' => $office->id]);

        $this->actingAs($owner)
            ->postJson('/api/v1/auth/confirm-password', ['password' => 'password'])
            ->assertOk();

        $create = $this->actingAs($owner)->postJson('/api/v1/platform/serpro/dte-canary');
        $create->assertCreated();
        $id = (int) $create->json('data.id');
        $this->assertSame(DteCanaryCoordinates::OPERATION_KEY, $create->json('data.operation_key'));

        $bad = $this->actingAs($owner)->postJson("/api/v1/platform/serpro/dte-canary/{$id}/target", [
            'office_id' => $office->id,
            'client_id' => $client->id,
            'operation_key' => 'pgdasd.consdeclaracao',
        ]);
        $bad->assertStatus(422);

        $ok = $this->actingAs($owner)->postJson("/api/v1/platform/serpro/dte-canary/{$id}/target", [
            'office_id' => $office->id,
            'client_id' => $client->id,
        ]);
        $ok->assertOk()
            ->assertJsonPath('data.office_id', $office->id)
            ->assertJsonPath('data.client_id', $client->id)
            ->assertJsonPath('data.status', SerproDteCanaryRequestStatus::TargetSet->value);
    }

    public function test_execute_rejeita_office_id_e_exige_senha(): void
    {
        $owner = User::factory()->asPlatformAdmin()->create(['password' => 'password']);
        $svc = app(SerproDteCanaryService::class);
        $req = $svc->createRequest($owner->id);

        $denied = $this->actingAs($owner)
            ->postJson("/api/v1/platform/serpro/dte-canary/{$req->id}/execute");
        $denied->assertStatus(403)->assertJsonPath('code', 'password_confirmation_required');

        $this->actingAs($owner)
            ->postJson('/api/v1/auth/confirm-password', ['password' => 'password'])
            ->assertOk();

        $forbiddenField = $this->actingAs($owner)
            ->postJson("/api/v1/platform/serpro/dte-canary/{$req->id}/execute", [
                'office_id' => 999,
            ]);
        $forbiddenField->assertStatus(422)->assertJsonPath('code', 'forbidden_field');
    }

    public function test_global_show_sem_payload_fiscal(): void
    {
        $owner = User::factory()->asPlatformAdmin()->create();
        $req = SerproDteCanaryRequest::query()->create([
            'environment' => 'PRODUCTION',
            'status' => SerproDteCanaryRequestStatus::Succeeded,
            'operation_key' => DteCanaryCoordinates::OPERATION_KEY,
            'id_sistema' => DteCanaryCoordinates::ID_SISTEMA,
            'id_servico' => DteCanaryCoordinates::ID_SERVICO,
            'service_version' => '1.0',
            'functional_route' => '/Consultar',
            'required_proxy_power' => '00050',
            'result_status' => 'SUCCEEDED',
            'consumption_quantity' => 1,
            'correlation_id' => 'abc',
        ]);

        $res = $this->actingAs($owner)->getJson("/api/v1/platform/serpro/dte-canary/{$req->id}");
        $res->assertOk();
        $body = $res->getContent();
        $this->assertStringNotContainsString('fiscal_result', $body);
        $this->assertStringNotContainsString('"dados"', $body);
        $this->assertStringNotContainsString('mensagens', $body);
    }

    public function test_office_admin_confirma_no_current_office(): void
    {
        $owner = User::factory()->asPlatformAdmin()->create(['password' => 'password']);
        $office = Office::factory()->create([
            'serpro_segregation_class' => SerproDataSegregationClass::Production->value,
        ]);
        $admin = User::factory()->forOffice($office, OfficeRole::Admin)->create(['password' => 'password']);
        $client = Client::factory()->create(['office_id' => $office->id]);
        $svc = app(SerproDteCanaryService::class);
        $req = $svc->createRequest($owner->id);
        $req = $svc->selectTarget($req, $office->id, $client->id, $owner->id);
        $req = $svc->approveAsOwner($req, $owner, true);

        $this->actingAs($admin)
            ->postJson('/api/v1/auth/confirm-password', ['password' => 'password'])
            ->assertOk();

        $admin->forceFill(['selected_office_id' => $office->id])->save();
        app(CurrentOffice::class)->clear();
        app(CurrentOffice::class)->resolve($admin);

        // office_id no body é removido pelo EnsureOfficeContext; confirmação usa CurrentOffice
        $confirm = $this->actingAs($admin)
            ->postJson("/api/v1/serpro/dte-canary/{$req->id}/confirm", ['office_id' => 99999]);
        $confirm->assertOk()
            ->assertJsonPath('data.fully_approved', true)
            ->assertJsonPath('data.office_id', $office->id)
            ->assertJsonPath('data.status', SerproDteCanaryRequestStatus::FullyApproved->value);
    }

    public function test_viewer_le_resultado_cross_tenant_bloqueado(): void
    {
        $owner = User::factory()->asPlatformAdmin()->create();
        $officeA = Office::factory()->create([
            'serpro_segregation_class' => SerproDataSegregationClass::Production->value,
        ]);
        $officeB = Office::factory()->create([
            'serpro_segregation_class' => SerproDataSegregationClass::Production->value,
        ]);
        $viewerA = User::factory()->forOffice($officeA, OfficeRole::Viewer)->create();
        $viewerB = User::factory()->forOffice($officeB, OfficeRole::Viewer)->create();
        $req = SerproDteCanaryRequest::query()->create([
            'environment' => 'PRODUCTION',
            'status' => SerproDteCanaryRequestStatus::Succeeded,
            'office_id' => $officeA->id,
            'operation_key' => DteCanaryCoordinates::OPERATION_KEY,
            'id_sistema' => DteCanaryCoordinates::ID_SISTEMA,
            'id_servico' => DteCanaryCoordinates::ID_SERVICO,
            'service_version' => '1.0',
            'functional_route' => '/Consultar',
            'required_proxy_power' => '00050',
            'result_status' => 'SUCCEEDED',
        ]);

        $viewerA->forceFill(['selected_office_id' => $officeA->id])->save();
        app(CurrentOffice::class)->clear();
        app(CurrentOffice::class)->resolve($viewerA);
        $this->actingAs($viewerA)
            ->getJson("/api/v1/serpro/dte-canary/{$req->id}/result")
            ->assertOk()
            ->assertJsonStructure(['data' => ['fiscal_result', 'status', 'correlation_id']]);

        $viewerB->forceFill(['selected_office_id' => $officeB->id])->save();
        app(CurrentOffice::class)->clear();
        app(CurrentOffice::class)->resolve($viewerB);
        $this->actingAs($viewerB)
            ->getJson("/api/v1/serpro/dte-canary/{$req->id}/result")
            ->assertForbidden();
    }

    public function test_disable_e_promote_frases(): void
    {
        $owner = User::factory()->asPlatformAdmin()->create(['password' => 'password']);
        $this->actingAs($owner)
            ->postJson('/api/v1/auth/confirm-password', ['password' => 'password'])
            ->assertOk();

        $bad = $this->actingAs($owner)->postJson('/api/v1/platform/serpro/dte-canary/disable', [
            'confirmation_phrase' => 'ERRADO',
            'reason' => 'teste',
        ]);
        $bad->assertStatus(422);

        $ok = $this->actingAs($owner)->postJson('/api/v1/platform/serpro/dte-canary/disable', [
            'confirmation_phrase' => SerproDteCanaryService::CONFIRM_DISABLE_PHRASE,
            'reason' => 'drill de desativação',
        ]);
        $ok->assertOk()->assertJsonPath('data.mode', SerproDteControlMode::Disabled->value);
    }
}

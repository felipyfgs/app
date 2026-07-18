<?php

namespace Tests\Feature\Fiscal\SimplesMei;

use App\Contracts\SecureObjectStore;
use App\Contracts\SerproOperationExecutor;
use App\DTO\Serpro\IntegraResponse;
use App\Enums\OfficeRole;
use App\Models\CcmeiIssuedCertificate;
use App\Models\Client;
use App\Models\Establishment;
use App\Models\Office;
use App\Models\User;
use App\Services\Fiscal\SimplesMei\CcmeiCertificateIssuanceProjector;
use App\Services\Fiscal\SimplesMei\CcmeiCertificateProjector;
use App\Services\Fiscal\SimplesMei\CcmeiRegistrationStatusProjector;
use App\Support\CurrentOffice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CcmeiMonitoringControllerTest extends TestCase
{
    use RefreshDatabase;

    private Office $office;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'features.global_enabled' => true,
            'features.kill_switch' => false,
            'features.modules.simples_mei.enabled' => true,
            'features.modules.simples_mei.allow_all_offices' => true,
            'fiscal_monitoring.enabled' => true,
        ]);

        $this->office = Office::factory()->create();
        $this->client = Client::factory()->forOffice($this->office)->create();
        $user = User::factory()
            ->forOffice($this->office, OfficeRole::Admin)
            ->withTwoFactorConfirmed()
            ->create();
        $this->actingAs($user);
        app(CurrentOffice::class)->clear();
        app(CurrentOffice::class)->resolve($user);
    }

    #[Test]
    public function returns_only_sanitized_local_history_for_current_office_client(): void
    {
        app(CcmeiCertificateProjector::class)->project(
            $this->office,
            $this->client,
            ['status' => 'ATIVA', 'situation' => 'UP_TO_DATE'],
            null,
            'SIMULATED',
        );

        $this->getJson("/api/v1/fiscal/simples-mei/ccmei/clients/{$this->client->id}/history")
            ->assertOk()
            ->assertJsonPath('data.client_id', $this->client->id)
            ->assertJsonPath('data.current.status', 'ATIVA')
            ->assertJsonPath('data.provenance.serpro_called', false)
            ->assertJsonMissingPath('data.current.cnpj')
            ->assertJsonMissingPath('data.current.cpf')
            ->assertJsonMissingPath('data.current.qrcode');
    }

    #[Test]
    public function refuses_foreign_client_and_client_office_id(): void
    {
        $foreign = Client::factory()->forOffice(Office::factory()->create())->create();

        $this->getJson("/api/v1/fiscal/simples-mei/ccmei/clients/{$foreign->id}/history")
            ->assertNotFound()
            ->assertJsonPath('code', 'CLIENT_NOT_FOUND');
        $this->postJson("/api/v1/fiscal/simples-mei/ccmei/clients/{$this->client->id}/consult", [
            'confirmed' => true,
            'office_id' => $this->office->id,
        ])->assertStatus(422)->assertJsonPath('code', 'CLIENT_OFFICE_ID_REJECTED');
    }

    #[Test]
    public function enqueues_explicit_confirmed_consult_without_client_tax_identifier(): void
    {
        Queue::fake();

        $this->postJson("/api/v1/fiscal/simples-mei/ccmei/clients/{$this->client->id}/consult", [
            'confirmed' => true,
        ])->assertCreated()
            ->assertJsonPath('data.client_id', $this->client->id)
            ->assertJsonPath('data.service_code', 'CCMEI')
            ->assertJsonPath('data.operation_code', 'MONITOR')
            ->assertJsonMissingPath('data.cnpj');
    }

    #[Test]
    public function exposes_only_sanitized_registration_status_history_and_enqueues_123(): void
    {
        app(CcmeiRegistrationStatusProjector::class)->project($this->office, $this->client, [
            'status' => 'ATIVA', 'enquadrado_mei' => true, 'situation' => 'UP_TO_DATE', 'count' => 1,
        ], null, 'SIMULATED');

        $this->getJson("/api/v1/fiscal/simples-mei/ccmei/registration-status/clients/{$this->client->id}/history")
            ->assertOk()->assertJsonPath('data.current.status', 'ATIVA')->assertJsonPath('data.current.enquadrado_mei', true)
            ->assertJsonMissingPath('data.current.cnpj')->assertJsonMissingPath('data.history.0.cnpj');

        Queue::fake();
        $this->postJson("/api/v1/fiscal/simples-mei/ccmei/registration-status/clients/{$this->client->id}/consult", ['confirmed' => true])
            ->assertCreated()->assertJsonPath('data.service_code', 'CCMEI')
            ->assertJsonPath('data.operation_code', 'CONSULTAR_SITUACAO_CADASTRAL')->assertJsonMissingPath('data.cnpj');
    }

    #[Test]
    public function returns_issued_certificate_history_without_vault_or_tax_identifier(): void
    {
        CcmeiIssuedCertificate::query()->create([
            'office_id' => $this->office->id,
            'client_id' => $this->client->id,
            'contributor_cnpj' => '11222333000181',
            'certificate_vault_object_id' => '01JCCMEICERTIFICATE000001',
            'certificate_sha256' => str_repeat('a', 64),
            'certificate_mime_type' => 'application/pdf',
            'certificate_byte_size' => 42,
            'source_provenance' => 'SERPRO_TRIAL',
            'observed_at' => now(),
        ]);

        $this->getJson("/api/v1/fiscal/simples-mei/ccmei/clients/{$this->client->id}/issued-certificates")
            ->assertOk()
            ->assertJsonPath('data.client_id', $this->client->id)
            ->assertJsonPath('data.certificates.0.mime_type', 'application/pdf')
            ->assertJsonPath('data.provenance.serpro_called', false)
            ->assertJsonMissingPath('data.certificates.0.certificate_vault_object_id')
            ->assertJsonMissingPath('data.certificates.0.certificate_sha256')
            ->assertJsonMissingPath('data.certificates.0.contributor_cnpj');
    }

    #[Test]
    public function requires_confirmation_before_issuing_and_projects_only_sanitized_descriptor(): void
    {
        Establishment::factory()->forClient($this->client, '11222333000181')->create();
        $operations = Mockery::mock(SerproOperationExecutor::class);
        $operations->shouldReceive('execute')->once()->withArgs(function ($office, $client, $key, $data, $idempotencyKey, $correlationId, $mutation, $entity, $module): bool {
            return $office->is($this->office)
                && $client->is($this->client)
                && $key === 'ccmei.emitirccmei'
                && $data === []
                && $idempotencyKey === "ccmei:issue:{$this->office->id}:{$this->client->id}"
                && is_string($correlationId)
                && $mutation === null
                && $entity === null
                && $module === 'simples_mei';
        })->andReturn(new IntegraResponse(
            success: true,
            httpStatus: 200,
            body: [],
            dados: ['cnpj' => '11222333000181', 'pdf' => base64_encode('%PDF-1.4')],
            sourceProvenance: 'SERPRO_TRIAL',
        ));
        $vault = Mockery::mock(SecureObjectStore::class);
        $vault->shouldReceive('put')->once()->andReturn('01JCCMEICERTIFICATE000001');
        $this->app->instance(SerproOperationExecutor::class, $operations);
        $this->app->instance(SecureObjectStore::class, $vault);

        $this->postJson("/api/v1/fiscal/simples-mei/ccmei/clients/{$this->client->id}/issued-certificates", [])
            ->assertUnprocessable();

        $response = $this->postJson("/api/v1/fiscal/simples-mei/ccmei/clients/{$this->client->id}/issued-certificates", ['confirmed' => true]);
        $response->assertStatus(202)
            ->assertJsonPath('data.success', true)
            ->assertJsonPath('data.certificate.mime_type', 'application/pdf')
            ->assertJsonMissingPath('data.certificate.cnpj')
            ->assertJsonMissingPath('data.certificate.certificate_vault_object_id');
    }

    #[Test]
    public function downloads_only_current_office_certificate_with_private_headers(): void
    {
        $certificate = CcmeiIssuedCertificate::query()->create([
            'office_id' => $this->office->id,
            'client_id' => $this->client->id,
            'contributor_cnpj' => '11222333000181',
            'certificate_vault_object_id' => '01JCCMEICERTIFICATE000001',
            'certificate_sha256' => str_repeat('a', 64),
            'certificate_mime_type' => 'application/pdf',
            'certificate_byte_size' => 8,
            'source_provenance' => 'SERPRO_TRIAL',
            'observed_at' => now(),
        ]);
        $vault = Mockery::mock(SecureObjectStore::class);
        $vault->shouldReceive('get')->once()->with(
            '01JCCMEICERTIFICATE000001',
            CcmeiCertificateIssuanceProjector::certificateAad($this->office->id, $this->client->id, str_repeat('a', 64)),
        )->andReturn('%PDF-1.4');
        $this->app->instance(SecureObjectStore::class, $vault);

        $this->get("/api/v1/fiscal/simples-mei/ccmei/clients/{$this->client->id}/issued-certificates/{$certificate->id}/download")
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('Cache-Control', 'max-age=0, no-store, private')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    #[Test]
    public function viewer_reads_local_history_but_cannot_issue_and_foreign_client_is_not_enumerated(): void
    {
        $viewer = User::factory()->forOffice($this->office, OfficeRole::Viewer)->create();
        $foreign = Client::factory()->forOffice(Office::factory()->create())->create();
        $this->actingAs($viewer);
        app(CurrentOffice::class)->clear();
        app(CurrentOffice::class)->resolve($viewer);

        $this->getJson("/api/v1/fiscal/simples-mei/ccmei/clients/{$this->client->id}/issued-certificates")
            ->assertOk()->assertJsonCount(0, 'data.certificates');
        $this->postJson("/api/v1/fiscal/simples-mei/ccmei/clients/{$this->client->id}/issued-certificates", ['confirmed' => true])
            ->assertForbidden();
        $this->getJson("/api/v1/fiscal/simples-mei/ccmei/clients/{$foreign->id}/issued-certificates")
            ->assertNotFound();
    }
}

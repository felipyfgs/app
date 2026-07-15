<?php

namespace Tests\Feature\AutXml;

use App\Enums\DocumentAcquisitionSource;
use App\Enums\OfficeRole;
use App\Enums\QuarantineReason;
use App\Enums\QuarantineResolutionStatus;
use App\Models\FiscalDocumentQuarantine;
use App\Models\Office;
use App\Models\User;
use App\Support\CurrentOffice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FiscalQuarantineApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_lista_e_resolve_sem_xml_nem_vault(): void
    {
        $office = Office::factory()->create();
        $admin = User::factory()->forOffice($office, OfficeRole::Admin)->withTwoFactorConfirmed()->create();

        $q = FiscalDocumentQuarantine::query()->create([
            'office_id' => $office->id,
            'sha256' => str_repeat('a', 64),
            'vault_object_id' => 'vault-secret-id-never-expose',
            'byte_size' => 100,
            'access_key' => '35200112345678000195550010000000011000000010',
            'issuer_cnpj' => '11222333000181',
            'reason' => QuarantineReason::UnmatchedIssuer,
            'source' => DocumentAcquisitionSource::AutXmlDistNsu,
            'resolution_status' => QuarantineResolutionStatus::Open,
        ]);

        $this->actingAs($admin);
        app(CurrentOffice::class)->resolve($admin);

        $list = $this->getJson('/api/v1/operations/quarantine')
            ->assertOk()
            ->assertJsonPath('data.0.id', $q->id)
            ->assertJsonPath('data.0.reason', 'UNMATCHED_ISSUER');

        $body = $list->getContent() ?: '';
        $this->assertStringNotContainsString('vault-secret-id', $body);
        $this->assertStringNotContainsString('vault_object_id', $body);
        $this->assertStringNotContainsString('<NFe', $body);

        $this->postJson("/api/v1/operations/quarantine/{$q->id}/resolve", [
            'resolution_status' => 'DISMISSED',
            'resolution_code' => 'CADASTRO_PENDENTE',
            'resolution_notes' => 'Cliente ainda não cadastrado',
        ])->assertOk()
            ->assertJsonPath('data.resolution_status', 'DISMISSED');
    }

    public function test_bytes_diverge_nao_aceita_resolved_cego(): void
    {
        $office = Office::factory()->create();
        $op = User::factory()->forOffice($office, OfficeRole::Operator)->withTwoFactorConfirmed()->create();

        $q = FiscalDocumentQuarantine::query()->create([
            'office_id' => $office->id,
            'sha256' => str_repeat('b', 64),
            'vault_object_id' => 'vault-x',
            'byte_size' => 50,
            'access_key' => '35200112345678000195550010000000021000000018',
            'reason' => QuarantineReason::BytesDiverge,
            'source' => DocumentAcquisitionSource::ManualXml,
            'resolution_status' => QuarantineResolutionStatus::Open,
        ]);

        $this->actingAs($op);
        app(CurrentOffice::class)->resolve($op);

        $this->postJson("/api/v1/operations/quarantine/{$q->id}/resolve", [
            'resolution_status' => 'RESOLVED',
            'resolution_code' => 'ACCEPT_BYTES',
        ])->assertStatus(422);
    }

    public function test_viewer_nao_resolve_e_cross_tenant_404(): void
    {
        $officeA = Office::factory()->create();
        $officeB = Office::factory()->create();
        $viewer = User::factory()->forOffice($officeA, OfficeRole::Viewer)->withTwoFactorConfirmed()->create();

        $qB = FiscalDocumentQuarantine::query()->create([
            'office_id' => $officeB->id,
            'sha256' => str_repeat('c', 64),
            'vault_object_id' => 'vault-b',
            'byte_size' => 10,
            'reason' => QuarantineReason::OrphanEvent,
            'source' => DocumentAcquisitionSource::AutXmlDistNsu,
            'resolution_status' => QuarantineResolutionStatus::Open,
        ]);

        $this->actingAs($viewer);
        app(CurrentOffice::class)->resolve($viewer);

        $this->getJson('/api/v1/operations/quarantine')->assertOk();
        // VIEWER: 403 antes de revelar existência de ID de outro tenant
        $this->postJson("/api/v1/operations/quarantine/{$qB->id}/resolve", [
            'resolution_status' => 'DISMISSED',
        ])->assertForbidden();
    }

    public function test_operator_cross_tenant_404(): void
    {
        $officeA = Office::factory()->create();
        $officeB = Office::factory()->create();
        $op = User::factory()->forOffice($officeA, OfficeRole::Operator)->withTwoFactorConfirmed()->create();

        $qB = FiscalDocumentQuarantine::query()->create([
            'office_id' => $officeB->id,
            'sha256' => str_repeat('d', 64),
            'vault_object_id' => 'vault-b2',
            'byte_size' => 10,
            'reason' => QuarantineReason::OrphanEvent,
            'source' => DocumentAcquisitionSource::AutXmlDistNsu,
            'resolution_status' => QuarantineResolutionStatus::Open,
        ]);

        $this->actingAs($op);
        app(CurrentOffice::class)->resolve($op);

        $this->postJson("/api/v1/operations/quarantine/{$qB->id}/resolve", [
            'resolution_status' => 'DISMISSED',
        ])->assertNotFound();
    }
}

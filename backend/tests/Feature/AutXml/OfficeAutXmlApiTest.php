<?php

namespace Tests\Feature\AutXml;

use App\Enums\OfficeAutXmlEnrollmentStatus;
use App\Enums\OfficeRole;
use App\Enums\SyncCursorStatus;
use App\Models\Client;
use App\Models\Establishment;
use App\Models\Office;
use App\Models\OfficeAutXmlEnrollment;
use App\Models\OfficeDistributionCursor;
use App\Models\OfficeFiscalIdentity;
use App\Models\User;
use App\Support\CurrentOffice;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OfficeAutXmlApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_overview_lista_checklist_e_cursor_sem_nsu_editavel(): void
    {
        $office = Office::factory()->create();
        $operator = User::factory()->forOffice($office, OfficeRole::Operator)->withTwoFactorConfirmed()->create();
        $identity = OfficeFiscalIdentity::factory()->forOffice($office)->create();
        $client = Client::factory()->forOffice($office)->create();
        $est = Establishment::factory()->forClient($client)->create(['is_active' => true]);

        OfficeDistributionCursor::factory()->forIdentity($identity)->create([
            'activated_at' => null,
            'status' => SyncCursorStatus::Idle,
        ]);

        $this->actingAs($operator);
        app(CurrentOffice::class)->resolve($operator);

        $res = $this->getJson('/api/v1/office/autxml')
            ->assertOk()
            ->assertJsonPath('data.office_cnpj', $identity->cnpj)
            ->assertJsonPath('data.stream.stream_ready', false)
            ->assertJsonPath('data.coverage.model', '55');

        $body = $res->getContent() ?: '';
        $this->assertStringNotContainsString('vault_object_id', $body);
        $this->assertStringNotContainsString('BEGIN PRIVATE', $body);

        $enrollments = $res->json('data.enrollments');
        $this->assertIsArray($enrollments);
        $this->assertNotEmpty($enrollments);
        $this->assertSame($est->id, $enrollments[0]['establishment_id']);
        $this->assertSame('NONE', $enrollments[0]['status']);
    }

    public function test_overview_pagina_estabelecimentos_ativos_com_ordem_estavel_e_isolamento(): void
    {
        $office = Office::factory()->create();
        $otherOffice = Office::factory()->create();
        $operator = User::factory()->forOffice($office, OfficeRole::Operator)->withTwoFactorConfirmed()->create();
        $identity = OfficeFiscalIdentity::factory()->forOffice($office)->create();

        $establishments = collect(range(1, 3))->map(function () use ($office) {
            $client = Client::factory()->forOffice($office)->create();

            return Establishment::factory()->forClient($client)->create(['is_active' => true]);
        });
        $inactiveClient = Client::factory()->forOffice($office)->create();
        Establishment::factory()->forClient($inactiveClient)->create(['is_active' => false]);
        $otherClient = Client::factory()->forOffice($otherOffice)->create();
        $otherEstablishment = Establishment::factory()->forClient($otherClient)->create(['is_active' => true]);

        OfficeAutXmlEnrollment::query()->create([
            'office_id' => $office->id,
            'office_fiscal_identity_id' => $identity->id,
            'establishment_id' => $establishments->first()->id,
            'status' => OfficeAutXmlEnrollmentStatus::Pending,
        ]);

        $this->actingAs($operator);
        app(CurrentOffice::class)->resolve($operator);

        $first = $this->getJson('/api/v1/office/autxml?per_page=2&page=1')
            ->assertOk()
            ->assertJsonCount(2, 'data.enrollments')
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('meta.total', 3);
        $second = $this->getJson('/api/v1/office/autxml?per_page=2&page=2')
            ->assertOk()
            ->assertJsonCount(1, 'data.enrollments')
            ->assertJsonPath('meta.current_page', 2);

        $rows = collect($first->json('data.enrollments'))
            ->concat($second->json('data.enrollments'));
        $this->assertSame(
            $establishments->sortBy('cnpj')->pluck('id')->values()->all(),
            $rows->pluck('establishment_id')->all(),
        );
        $this->assertCount(3, $rows->pluck('establishment_id')->unique());
        $this->assertFalse($rows->pluck('establishment_id')->contains($otherEstablishment->id));
        $this->assertSame('PENDING', $rows->firstWhere('establishment_id', $establishments->first()->id)['status']);
    }

    public function test_enroll_e_confirm_bloqueado_antes_do_quiet(): void
    {
        $office = Office::factory()->create();
        $admin = User::factory()->forOffice($office, OfficeRole::Admin)->withTwoFactorConfirmed()->create();
        $identity = OfficeFiscalIdentity::factory()->forOffice($office)->create();
        $client = Client::factory()->forOffice($office)->create();
        $est = Establishment::factory()->forClient($client)->create(['is_active' => true]);

        OfficeDistributionCursor::factory()->forIdentity($identity)->create([
            'activated_at' => CarbonImmutable::now()->subMinutes(10),
            'status' => SyncCursorStatus::Idle,
            'last_cstat' => '137',
        ]);

        $this->actingAs($admin);
        app(CurrentOffice::class)->resolve($admin);

        $enroll = $this->postJson('/api/v1/office/autxml/enrollments', [
            'establishment_id' => $est->id,
        ])->assertCreated()
            ->assertJsonPath('data.status', 'PENDING');

        $enrollmentId = (int) $enroll->json('data.id');

        $this->postJson("/api/v1/office/autxml/enrollments/{$enrollmentId}/confirm")
            ->assertStatus(422)
            ->assertJsonFragment(['message' => 'Confirmação bloqueada: ative o stream autXML (primeira distNSU) e aguarde o quiet mínimo de 1 hora.']);
    }

    public function test_confirm_apos_quiet_e_first_seen_visivel(): void
    {
        $office = Office::factory()->create();
        $admin = User::factory()->forOffice($office, OfficeRole::Admin)->withTwoFactorConfirmed()->create();
        $identity = OfficeFiscalIdentity::factory()->forOffice($office)->create();
        $client = Client::factory()->forOffice($office)->create();
        $est = Establishment::factory()->forClient($client)->create(['is_active' => true]);

        OfficeDistributionCursor::factory()->forIdentity($identity)->create([
            'activated_at' => CarbonImmutable::now()->subHours(2),
            'status' => SyncCursorStatus::Idle,
            'last_cstat' => '137',
            'last_nsu' => 0,
        ]);

        $enrollment = OfficeAutXmlEnrollment::query()->create([
            'office_id' => $office->id,
            'office_fiscal_identity_id' => $identity->id,
            'establishment_id' => $est->id,
            'status' => OfficeAutXmlEnrollmentStatus::Pending,
            'first_seen_at' => CarbonImmutable::now()->subHour(),
            'last_seen_at' => CarbonImmutable::now()->subMinutes(5),
        ]);

        $this->actingAs($admin);
        app(CurrentOffice::class)->resolve($admin);

        $this->postJson("/api/v1/office/autxml/enrollments/{$enrollment->id}/confirm")
            ->assertOk()
            ->assertJsonPath('data.status', 'CONFIRMED')
            ->assertJsonPath('data.observed', true);

        $this->assertNotNull($enrollment->fresh()?->first_seen_at);
    }

    public function test_viewer_nao_enrolla(): void
    {
        $office = Office::factory()->create();
        $viewer = User::factory()->forOffice($office, OfficeRole::Viewer)->withTwoFactorConfirmed()->create();
        $identity = OfficeFiscalIdentity::factory()->forOffice($office)->create();
        $client = Client::factory()->forOffice($office)->create();
        $est = Establishment::factory()->forClient($client)->create();

        $this->actingAs($viewer);
        app(CurrentOffice::class)->resolve($viewer);

        $this->getJson('/api/v1/office/autxml')->assertOk();
        $this->postJson('/api/v1/office/autxml/enrollments', [
            'establishment_id' => $est->id,
        ])->assertForbidden();

        unset($identity);
    }

    public function test_cursor_endpoint_nao_expoe_reset(): void
    {
        $office = Office::factory()->create();
        $user = User::factory()->forOffice($office, OfficeRole::Operator)->withTwoFactorConfirmed()->create();
        $identity = OfficeFiscalIdentity::factory()->forOffice($office)->create();
        OfficeDistributionCursor::factory()->forIdentity($identity)->create([
            'last_nsu' => 42,
            'max_nsu_seen' => 50,
            'last_cstat' => '656',
            'status' => SyncCursorStatus::Blocked,
            'next_sync_at' => CarbonImmutable::now()->addHour(),
        ]);

        $this->actingAs($user);
        app(CurrentOffice::class)->resolve($user);

        $res = $this->getJson('/api/v1/office/autxml/cursor')
            ->assertOk()
            ->assertJsonPath('data.cursor.last_nsu', 42)
            ->assertJsonPath('data.cursor.circuit_open', true);

        $this->assertArrayNotHasKey('reset', $res->json('data.cursor') ?? []);
        $this->assertStringNotContainsString('SOAP', $res->getContent() ?: '');
    }

    public function test_isolamento_cross_tenant_enrollment(): void
    {
        $officeA = Office::factory()->create();
        $officeB = Office::factory()->create();
        $adminA = User::factory()->forOffice($officeA, OfficeRole::Admin)->withTwoFactorConfirmed()->create();
        $identityB = OfficeFiscalIdentity::factory()->forOffice($officeB)->create();
        $clientB = Client::factory()->forOffice($officeB)->create();
        $estB = Establishment::factory()->forClient($clientB)->create();

        $enrollmentB = OfficeAutXmlEnrollment::query()->create([
            'office_id' => $officeB->id,
            'office_fiscal_identity_id' => $identityB->id,
            'establishment_id' => $estB->id,
            'status' => OfficeAutXmlEnrollmentStatus::Pending,
        ]);

        $this->actingAs($adminA);
        app(CurrentOffice::class)->resolve($adminA);

        $this->postJson("/api/v1/office/autxml/enrollments/{$enrollmentB->id}/confirm")
            ->assertNotFound();
    }
}

<?php

namespace Tests\Feature\Serpro;

use App\Enums\AuthorCertificateMode;
use App\Enums\AuthorIdentityType;
use App\Enums\OfficeRole;
use App\Enums\SerproAuthorizationStatus;
use App\Enums\SerproEnvironment;
use App\Enums\TaxProxyPowerSource;
use App\Enums\TaxProxyPowerStatus;
use App\Enums\TermRePresentationStrategy;
use App\Models\Client;
use App\Models\Establishment;
use App\Models\Office;
use App\Models\OfficeSerproAuthorization;
use App\Models\TaxProxyPower;
use App\Models\User;
use App\Services\Integra\OfficeSerproAuthorizationService;
use App\Support\CurrentOffice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ApiSecretScanner;
use Tests\Support\TermoFixtureFactory;
use Tests\TestCase;

class OfficeSerproAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_onboarding_sem_contrato_real_falha_fechado_sem_expor_segredos(): void
    {
        config(['serpro.termo_destination_cnpj' => '11222333000181']);
        config(['serpro.term_representation.TRIAL' => TermRePresentationStrategy::ReuseStoredTerm->value]);

        $office = Office::factory()->create();
        $admin = User::factory()->forOffice($office, OfficeRole::Admin)->withTwoFactorConfirmed()->create();

        $this->actingAs($admin);
        app(CurrentOffice::class)->resolve($admin);

        $this->postJson('/api/v1/office/serpro-authorization/author', [
            'environment' => 'TRIAL',
            'author_identity_type' => 'CPF',
            'author_identity' => '52998224725',
            'author_name' => 'Contador Teste',
            'certificate_mode' => 'EXTERNAL_SIGNATURE',
        ])->assertOk()
            ->assertJsonPath('data.author_identity_type', 'CPF')
            ->assertJsonMissingPath('data.author_identity')
            ->assertJsonMissingPath('data.termo_vault_object_id');

        $xml = $this->validTermoXml('52998224725', '11222333000181');
        $upload = $this->postJson('/api/v1/office/serpro-authorization/termo', [
            'environment' => 'TRIAL',
            'termo_xml' => $xml,
        ]);
        $upload->assertCreated();
        $body = (string) $upload->getContent();
        $this->assertStringNotContainsString('<termoDeAutorizacao>', $body);
        $this->assertStringNotContainsString('<TermoAutorizacao>', $body);
        $this->assertStringNotContainsString('SignatureValue', $body);
        $this->assertStringNotContainsString($xml, $body);
        $this->assertTrue($upload->json('data.has_termo'));

        $refresh = $this->postJson('/api/v1/office/serpro-authorization/refresh-token', [
            'environment' => 'TRIAL',
        ]);
        $refresh->assertStatus(422)
            ->assertJsonFragment(['message' => 'Contrato SERPRO indisponível para autenticar procurador.']);
        $this->assertStringNotContainsString('token', strtolower((string) $refresh->getContent()));
        $this->assertDatabaseMissing('office_serpro_authorizations', [
            'office_id' => $office->id,
            'status' => SerproAuthorizationStatus::TokenActive->value,
        ]);
    }

    public function test_termo_signatario_divergente_rejeita(): void
    {
        config(['serpro.termo_destination_cnpj' => '11222333000181']);
        $office = Office::factory()->create();
        $admin = User::factory()->forOffice($office, OfficeRole::Admin)->withTwoFactorConfirmed()->create();
        $this->actingAs($admin);
        app(CurrentOffice::class)->resolve($admin);

        $this->postJson('/api/v1/office/serpro-authorization/author', [
            'author_identity_type' => 'CPF',
            'author_identity' => '52998224725',
        ])->assertOk();

        $xml = $this->validTermoXml('99999999999', '11222333000181');
        $this->postJson('/api/v1/office/serpro-authorization/termo', [
            'termo_xml' => $xml,
        ])->assertStatus(422);
    }

    public function test_a3_interativo_action_required(): void
    {
        config(['serpro.termo_destination_cnpj' => '11222333000181']);
        $office = Office::factory()->create();
        $admin = User::factory()->forOffice($office, OfficeRole::Admin)->withTwoFactorConfirmed()->create();
        $svc = app(OfficeSerproAuthorizationService::class);

        $auth = $svc->configureAuthor(
            $office,
            SerproEnvironment::Trial,
            AuthorIdentityType::Cpf,
            '52998224725',
            'A3 User',
            AuthorCertificateMode::InteractiveA3,
            $admin->id,
        );

        $svc->uploadTermo($office, SerproEnvironment::Trial, $this->validTermoXml('52998224725', '11222333000181'), $admin->id);
        $auth = $svc->refreshProcuradorToken($office, SerproEnvironment::Trial, $admin->id);

        $this->assertSame(SerproAuthorizationStatus::ActionRequired, $auth->status);
        $this->assertNotEmpty($auth->action_required_reason);
    }

    public function test_pending_validation_bloqueia_reuso_de_termo(): void
    {
        config([
            'serpro.termo_destination_cnpj' => '11222333000181',
            'serpro.term_representation.TRIAL' => TermRePresentationStrategy::PendingValidation->value,
        ]);

        $office = Office::factory()->create();
        $admin = User::factory()->forOffice($office, OfficeRole::Admin)->withTwoFactorConfirmed()->create();
        $svc = app(OfficeSerproAuthorizationService::class);

        $svc->configureAuthor(
            $office,
            SerproEnvironment::Trial,
            AuthorIdentityType::Cpf,
            '52998224725',
            null,
            AuthorCertificateMode::ExternalSignature,
            $admin->id,
        );
        $svc->uploadTermo($office, SerproEnvironment::Trial, $this->validTermoXml('52998224725', '11222333000181'), $admin->id);

        // Simula token expirado
        $auth = OfficeSerproAuthorization::query()->where('office_id', $office->id)->firstOrFail();
        $auth->procurador_token_vault_object_id = '01ARZ3NDEKTSV4RRFFQ69G5FAV';
        $auth->procurador_token_expires_at = now()->subHour();
        $auth->status = SerproAuthorizationStatus::TokenActive;
        $auth->save();

        $auth = $svc->refreshProcuradorToken($office, SerproEnvironment::Trial, $admin->id);
        $this->assertSame(SerproAuthorizationStatus::ActionRequired, $auth->status);
    }

    public function test_proxy_power_e_elegibilidade_e_tenant_cruzado(): void
    {
        config(['serpro.termo_destination_cnpj' => '11222333000181']);
        config(['serpro.term_representation.TRIAL' => TermRePresentationStrategy::ReuseStoredTerm->value]);
        config(['features.global_enabled' => true]);
        config(['features.modules.simples_mei.enabled' => true]);
        config(['features.modules.simples_mei.allow_all_offices' => true]);

        $officeA = Office::factory()->create();
        $officeB = Office::factory()->create();
        $admin = User::factory()->forOffice($officeA, OfficeRole::Admin)->withTwoFactorConfirmed()->create();
        $clientA = Client::factory()->forOffice($officeA)->create();
        $clientB = Client::factory()->forOffice($officeB)->create();
        Establishment::factory()->forClient($clientA)->create();
        Establishment::factory()->forClient($clientB)->create();

        $this->actingAs($admin);
        app(CurrentOffice::class)->resolve($admin);

        $this->postJson('/api/v1/office/serpro-authorization/author', [
            'author_identity_type' => 'CPF',
            'author_identity' => '52998224725',
        ])->assertOk();
        $this->postJson('/api/v1/office/serpro-authorization/termo', [
            'termo_xml' => $this->validTermoXml('52998224725', '11222333000181'),
        ])->assertCreated();
        $this->postJson('/api/v1/office/serpro-authorization/refresh-token')
            ->assertStatus(422)
            ->assertJsonFragment(['message' => 'Contrato SERPRO indisponível para autenticar procurador.']);

        // F-3.3: importação/override manual de procuração é proibida na API tenant.
        $this->postJson('/api/v1/office/serpro-authorization/proxy-powers', [
            'client_id' => $clientA->id,
            'power_code' => 'PGDASD',
            'system_code' => 'INTEGRA_SN',
            'service_code' => 'PGDASD',
            'evidence_ref' => 'MANUAL-001',
            'valid_from' => now()->subDay()->toIso8601String(),
            'valid_to' => now()->addYear()->toIso8601String(),
        ])->assertStatus(422)
            ->assertJsonFragment(['message' => 'Override/importação manual de procuração é proibido; use sincronização oficial.']);

        // Mesmo com client de outro tenant, o endpoint recusa o override (não cria poder).
        $this->postJson('/api/v1/office/serpro-authorization/proxy-powers', [
            'client_id' => $clientB->id,
            'power_code' => 'PGDASD',
            'system_code' => 'INTEGRA_SN',
            'evidence_ref' => 'X',
        ])->assertStatus(422);

        $this->assertDatabaseMissing('tax_proxy_powers', [
            'office_id' => $officeA->id,
            'client_id' => $clientA->id,
            'power_code' => 'PGDASD',
        ]);

        // Capability desligada falha fechada: não fabrica procuração/poder.
        $sync = $this->postJson('/api/v1/office/serpro-authorization/proxy-powers/sync', [
            'client_id' => $clientA->id,
        ]);
        $sync->assertStatus(422)
            ->assertJsonFragment(['message' => 'Consulta de procurações SERPRO desabilitada.']);
        $this->assertDatabaseMissing('tax_proxy_powers', [
            'office_id' => $officeA->id,
            'client_id' => $clientA->id,
        ]);

        // Sem contrato ACTIVE a elegibilidade bloqueia
        $elig = $this->postJson('/api/v1/office/serpro-authorization/eligibility', [
            'client_id' => $clientA->id,
            'solution_code' => 'INTEGRA_SN',
            'service_code' => 'PGDASD',
            'operation_code' => 'CONSULTAR_DECLARACAO',
            'module' => 'simples_mei',
        ]);
        $elig->assertOk();
        $this->assertFalse($elig->json('data.eligible'));
        $this->assertContains('CONTRACT_UNAVAILABLE', $elig->json('data.codes'));
    }

    public function test_lista_proxy_powers_pagina_sem_corte_isola_tenant_e_ordena_com_desempate(): void
    {
        $officeA = Office::factory()->create();
        $officeB = Office::factory()->create();
        $admin = User::factory()->forOffice($officeA, OfficeRole::Admin)->withTwoFactorConfirmed()->create();
        $clientA = Client::factory()->forOffice($officeA)->create();
        $otherClientA = Client::factory()->forOffice($officeA)->create();
        $clientB = Client::factory()->forOffice($officeB)->create();
        $now = now();

        $rows = [];
        for ($index = 0; $index < 205; $index++) {
            $rows[] = [
                'office_id' => $officeA->id,
                'client_id' => $clientA->id,
                'office_serpro_authorization_id' => null,
                'author_identity' => '52998224725',
                'contributor_cnpj' => '12345678000195',
                'system_code' => 'INTEGRA',
                'service_code' => null,
                'power_code' => sprintf('POWER-%03d', $index),
                'source' => TaxProxyPowerSource::ManualOfficialEvidence->value,
                'status' => TaxProxyPowerStatus::Active->value,
                'valid_from' => null,
                'valid_to' => null,
                'evidence_ref' => sprintf('EVIDENCE-%03d', $index),
                'evidence_sha256' => null,
                'verified_at' => null,
                'last_check_result' => null,
                'metadata' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        $rows[] = [
            'office_id' => $officeA->id,
            'client_id' => $otherClientA->id,
            'office_serpro_authorization_id' => null,
            'author_identity' => '52998224725',
            'contributor_cnpj' => '98765432000198',
            'system_code' => 'OUTRO',
            'service_code' => null,
            'power_code' => 'POWER-OTHER-CLIENT',
            'source' => TaxProxyPowerSource::ManualOfficialEvidence->value,
            'status' => TaxProxyPowerStatus::Active->value,
            'valid_from' => null,
            'valid_to' => null,
            'evidence_ref' => 'EVIDENCE-OTHER-CLIENT',
            'evidence_sha256' => null,
            'verified_at' => null,
            'last_check_result' => null,
            'metadata' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ];
        $rows[] = [
            'office_id' => $officeB->id,
            'client_id' => $clientB->id,
            'office_serpro_authorization_id' => null,
            'author_identity' => '52998224725',
            'contributor_cnpj' => '12345678000195',
            'system_code' => 'INTEGRA',
            'service_code' => null,
            'power_code' => 'POWER-CROSS-TENANT',
            'source' => TaxProxyPowerSource::ManualOfficialEvidence->value,
            'status' => TaxProxyPowerStatus::Active->value,
            'valid_from' => null,
            'valid_to' => null,
            'evidence_ref' => 'EVIDENCE-CROSS-TENANT',
            'evidence_sha256' => null,
            'verified_at' => null,
            'last_check_result' => null,
            'metadata' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ];
        TaxProxyPower::withoutGlobalScopes()->insert($rows);

        $this->actingAs($admin);
        app(CurrentOffice::class)->resolve($admin);

        $firstPage = $this->getJson('/api/v1/office/serpro-authorization/proxy-powers?per_page=100');
        $firstPage->assertOk()
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.last_page', 3)
            ->assertJsonPath('meta.per_page', 100)
            ->assertJsonPath('meta.total', 206)
            ->assertJsonCount(100, 'data');
        ApiSecretScanner::assertClean((string) $firstPage->getContent(), 'proxy-powers.first-page');

        $thirdPage = $this->getJson(sprintf(
            '/api/v1/office/serpro-authorization/proxy-powers?client_id=%d&page=3&per_page=100',
            $clientA->id,
        ));
        $thirdPage->assertOk()
            ->assertJsonPath('meta.current_page', 3)
            ->assertJsonPath('meta.last_page', 3)
            ->assertJsonPath('meta.total', 205)
            ->assertJsonCount(5, 'data');
        $expectedThirdPageIds = TaxProxyPower::query()
            ->where('office_id', $officeA->id)
            ->where('client_id', $clientA->id)
            ->orderByDesc('id')
            ->skip(200)
            ->take(100)
            ->pluck('id')
            ->all();
        $this->assertSame($expectedThirdPageIds, $thirdPage->json('data.*.id'));

        $sorted = $this->getJson(sprintf(
            '/api/v1/office/serpro-authorization/proxy-powers?client_id=%d&sort=status&direction=asc&per_page=3',
            $clientA->id,
        ));
        $sorted->assertOk()->assertJsonCount(3, 'data');
        $expectedSortedIds = TaxProxyPower::query()
            ->where('office_id', $officeA->id)
            ->where('client_id', $clientA->id)
            ->orderBy('status')
            ->orderBy('id')
            ->limit(3)
            ->pluck('id')
            ->all();
        $this->assertSame($expectedSortedIds, $sorted->json('data.*.id'));

        $this->getJson(sprintf(
            '/api/v1/office/serpro-authorization/proxy-powers?client_id=%d',
            $clientB->id,
        ))->assertOk()
            ->assertJsonPath('meta.total', 0)
            ->assertJsonCount(0, 'data');

        $this->getJson('/api/v1/office/serpro-authorization/proxy-powers?sort=metadata')
            ->assertUnprocessable();
    }

    public function test_saude_tenant_sem_detalhe_comercial(): void
    {
        $office = Office::factory()->create();
        $admin = User::factory()->forOffice($office, OfficeRole::Admin)->withTwoFactorConfirmed()->create();
        $this->actingAs($admin);
        app(CurrentOffice::class)->resolve($admin);

        $response = $this->getJson('/api/v1/office/serpro-authorization/health?environment=TRIAL');
        $response->assertOk();
        $data = $response->json('data');
        $this->assertArrayNotHasKey('consumer_key_hint', $data);
        $this->assertArrayNotHasKey('contracts', $data);
        $this->assertArrayNotHasKey('fingerprint_sha256', $data);
    }

    public function test_viewer_nao_configura_autor(): void
    {
        $office = Office::factory()->create();
        $viewer = User::factory()->forOffice($office, OfficeRole::Viewer)->withTwoFactorConfirmed()->create();
        $this->actingAs($viewer);
        app(CurrentOffice::class)->resolve($viewer);

        $this->postJson('/api/v1/office/serpro-authorization/author', [
            'author_identity_type' => 'CPF',
            'author_identity' => '52998224725',
        ])->assertForbidden();
    }

    private function validTermoXml(string $signedBy, string $destination): string
    {
        return TermoFixtureFactory::signedTermo(
            authorIdentity: $signedBy,
            destinationCnpj: $destination,
        )['xml'];
    }
}

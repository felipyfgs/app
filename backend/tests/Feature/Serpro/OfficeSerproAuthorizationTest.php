<?php

namespace Tests\Feature\Serpro;

use App\Enums\AuthorCertificateMode;
use App\Enums\AuthorIdentityType;
use App\Enums\OfficeRole;
use App\Enums\SerproAuthorizationStatus;
use App\Enums\SerproEnvironment;
use App\Enums\TermRePresentationStrategy;
use App\Models\Client;
use App\Models\Office;
use App\Models\OfficeSerproAuthorization;
use App\Models\User;
use App\Services\Integra\OfficeSerproAuthorizationService;
use App\Support\CurrentOffice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RobRichards\XMLSecLibs\XMLSecurityDSig;
use RobRichards\XMLSecLibs\XMLSecurityKey;
use Tests\TestCase;

class OfficeSerproAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_onboarding_autor_termo_token_sem_expor_segredos(): void
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
            'author_identity' => '12345678901',
            'author_name' => 'Contador Teste',
            'certificate_mode' => 'EXTERNAL_SIGNATURE',
        ])->assertOk()
            ->assertJsonPath('data.author_identity_type', 'CPF')
            ->assertJsonMissingPath('data.author_identity')
            ->assertJsonMissingPath('data.termo_vault_object_id');

        $xml = $this->validTermoXml('12345678901', '11222333000181');
        $upload = $this->postJson('/api/v1/office/serpro-authorization/termo', [
            'environment' => 'TRIAL',
            'termo_xml' => $xml,
        ]);
        $upload->assertCreated();
        $body = (string) $upload->getContent();
        $this->assertStringNotContainsString('<TermoAutorizacao>', $body);
        $this->assertStringNotContainsString('SignatureValue', $body);
        $this->assertStringNotContainsString($xml, $body);
        $this->assertTrue($upload->json('data.has_termo'));

        $refresh = $this->postJson('/api/v1/office/serpro-authorization/refresh-token', [
            'environment' => 'TRIAL',
        ]);
        $refresh->assertOk();
        $this->assertSame(SerproAuthorizationStatus::TokenActive->value, $refresh->json('data.status'));
        $this->assertTrue($refresh->json('data.has_procurador_token'));
        $this->assertStringNotContainsString('fake-procurador', (string) $refresh->getContent());
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
            'author_identity' => '12345678901',
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
            '12345678901',
            'A3 User',
            AuthorCertificateMode::InteractiveA3,
            $admin->id,
        );

        $svc->uploadTermo($office, SerproEnvironment::Trial, $this->validTermoXml('12345678901', '11222333000181'), $admin->id);
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
            '12345678901',
            null,
            AuthorCertificateMode::ExternalSignature,
            $admin->id,
        );
        $svc->uploadTermo($office, SerproEnvironment::Trial, $this->validTermoXml('12345678901', '11222333000181'), $admin->id);

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

        $this->actingAs($admin);
        app(CurrentOffice::class)->resolve($admin);

        $this->postJson('/api/v1/office/serpro-authorization/author', [
            'author_identity_type' => 'CPF',
            'author_identity' => '12345678901',
        ])->assertOk();
        $this->postJson('/api/v1/office/serpro-authorization/termo', [
            'termo_xml' => $this->validTermoXml('12345678901', '11222333000181'),
        ])->assertCreated();
        $this->postJson('/api/v1/office/serpro-authorization/refresh-token')->assertOk();

        $this->postJson('/api/v1/office/serpro-authorization/proxy-powers', [
            'client_id' => $clientA->id,
            'power_code' => 'PGDASD',
            'system_code' => 'INTEGRA_SN',
            'service_code' => 'PGDASD',
            'evidence_ref' => 'MANUAL-001',
            'valid_from' => now()->subDay()->toIso8601String(),
            'valid_to' => now()->addYear()->toIso8601String(),
        ])->assertCreated()
            ->assertJsonMissingPath('data.evidence_xml');

        // Client de outro tenant → 404 (scoped) ou isolado
        $this->postJson('/api/v1/office/serpro-authorization/proxy-powers', [
            'client_id' => $clientB->id,
            'power_code' => 'PGDASD',
            'system_code' => 'INTEGRA_SN',
            'evidence_ref' => 'X',
        ])->assertNotFound();

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
            'author_identity' => '12345678901',
        ])->assertForbidden();
    }

    private function validTermoXml(string $signedBy, string $destination): string
    {
        $xml = <<<XML
<?xml version="1.0"?>
<TermoAutorizacao Id="termo-1">
  <assinadoPor>{$signedBy}</assinadoPor>
  <autorPedido>{$signedBy}</autorPedido>
  <destinatario>{$destination}</destinatario>
  <dataInicioVigencia>2026-01-01</dataInicioVigencia>
  <dataFimVigencia>2027-12-31</dataFimVigencia>
</TermoAutorizacao>
XML;

        $privateKey = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
            'digest_alg' => 'sha256',
        ]);
        $csr = openssl_csr_new([
            'commonName' => 'Autor Teste:'.$signedBy,
            'serialNumber' => $signedBy,
        ], $privateKey, ['digest_alg' => 'sha256']);
        $certificate = openssl_csr_sign($csr, null, $privateKey, 365, ['digest_alg' => 'sha256']);
        openssl_pkey_export($privateKey, $privatePem);
        openssl_x509_export($certificate, $certificatePem);

        $dom = new \DOMDocument;
        $dom->loadXML($xml, LIBXML_NONET);
        $signature = new XMLSecurityDSig;
        $signature->setCanonicalMethod(XMLSecurityDSig::C14N);
        $signature->addReference(
            $dom->documentElement,
            XMLSecurityDSig::SHA256,
            ['http://www.w3.org/2000/09/xmldsig#enveloped-signature'],
            ['id_name' => 'Id', 'overwrite' => false],
        );
        $key = new XMLSecurityKey(
            XMLSecurityKey::RSA_SHA256,
            ['type' => 'private'],
        );
        $key->loadKey($privatePem, false);
        $signature->sign($key);
        $signature->add509Cert($certificatePem, true, false);
        $signature->appendSignature($dom->documentElement);

        return $dom->saveXML() ?: $xml;
    }
}

<?php

namespace Tests\Feature\Serpro;

use App\Enums\AuthorCertificateMode;
use App\Enums\AuthorIdentityType;
use App\Enums\OfficeRole;
use App\Enums\SerproAuthorizationStatus;
use App\Enums\SerproEnvironment;
use App\Enums\TaxProxyPowerSource;
use App\Enums\TaxProxyPowerStatus;
use App\Enums\TermoAuthorizationState;
use App\Enums\TermRePresentationStrategy;
use App\Models\Client;
use App\Models\Office;
use App\Models\TaxProxyPower;
use App\Models\User;
use App\Services\Integra\OfficeSerproAuthorizationService;
use App\Support\CurrentOffice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TermoFixtureFactory;
use Tests\TestCase;

class TermoDraftAndInvalidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_draft_generate_download_e_upload_sem_expor_segredos(): void
    {
        config([
            'serpro.termo_destination_cnpj' => '11222333000181',
            'serpro.termo_destination_name' => 'CONTRATANTE TESTE LTDA',
            'serpro.term_representation.TRIAL' => TermRePresentationStrategy::ReuseStoredTerm->value,
        ]);

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
        ])->assertOk();

        $draft = $this->postJson('/api/v1/office/serpro-authorization/termo/draft', [
            'environment' => 'TRIAL',
        ]);
        $draft->assertCreated();
        $this->assertNotEmpty($draft->json('draft_sha256'));
        $this->assertStringNotContainsString('<termoDeAutorizacao>', (string) $draft->getContent());

        $download = $this->get('/api/v1/office/serpro-authorization/termo/draft?environment=TRIAL');
        $download->assertOk();
        $xml = $download->getContent();
        $this->assertStringContainsString('<termoDeAutorizacao>', $xml);
        $this->assertStringContainsString('API Integra Contador', $xml);
        $this->assertStringNotContainsString('<Signature', $xml);

        $signed = TermoFixtureFactory::signedTermo('52998224725', '11222333000181')['xml'];
        $upload = $this->postJson('/api/v1/office/serpro-authorization/termo', [
            'environment' => 'TRIAL',
            'termo_xml' => $signed,
        ]);
        $upload->assertCreated();
        $this->assertTrue($upload->json('data.has_termo'));
        $this->assertSame(TermoAuthorizationState::LocalValidated->value, $upload->json('data.termo_authorization_state'));
        $this->assertStringNotContainsString($signed, (string) $upload->getContent());
    }

    public function test_mudanca_de_autor_invalida_token_e_poderes(): void
    {
        config([
            'serpro.termo_destination_cnpj' => '11222333000181',
            'serpro.term_representation.TRIAL' => TermRePresentationStrategy::ReuseStoredTerm->value,
        ]);

        $office = Office::factory()->create();
        $admin = User::factory()->forOffice($office, OfficeRole::Admin)->withTwoFactorConfirmed()->create();
        $client = Client::factory()->forOffice($office)->create();
        $svc = app(OfficeSerproAuthorizationService::class);

        $svc->configureAuthor(
            $office,
            SerproEnvironment::Trial,
            AuthorIdentityType::Cpf,
            '52998224725',
            'Autor A',
            AuthorCertificateMode::ExternalSignature,
            $admin->id,
        );
        $auth = $svc->uploadTermo(
            $office,
            SerproEnvironment::Trial,
            TermoFixtureFactory::signedTermo('52998224725', '11222333000181')['xml'],
            $admin->id,
        );
        $auth = $svc->refreshProcuradorToken($office, SerproEnvironment::Trial, $admin->id);
        $this->assertSame(SerproAuthorizationStatus::TokenActive, $auth->status);

        TaxProxyPower::query()->create([
            'office_id' => $office->id,
            'client_id' => $client->id,
            'office_serpro_authorization_id' => $auth->id,
            'author_identity' => '52998224725',
            'contributor_cnpj' => '12345678000195',
            'system_code' => 'SITFIS',
            'service_code' => null,
            'power_code' => '00002',
            'source' => TaxProxyPowerSource::ManualOfficialEvidence,
            'status' => TaxProxyPowerStatus::Active,
            'evidence_ref' => 'EV-1',
        ]);

        // Troca de autor → invalidação atômica.
        $svc->configureAuthor(
            $office,
            SerproEnvironment::Trial,
            AuthorIdentityType::Cpf,
            '98765432100',
            'Autor B',
            AuthorCertificateMode::ExternalSignature,
            $admin->id,
        );

        $auth = $auth->refresh();
        $this->assertNull($auth->procurador_token_vault_object_id);
        $this->assertNull($auth->procurador_token_expires_at);
        $this->assertNull($auth->termo_vault_object_id);

        $power = TaxProxyPower::query()->where('office_id', $office->id)->first();
        $this->assertSame(TaxProxyPowerStatus::Revoked, $power?->status);
    }
}

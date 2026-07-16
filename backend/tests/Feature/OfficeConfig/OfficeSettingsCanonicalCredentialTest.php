<?php

namespace Tests\Feature\OfficeConfig;

use App\Contracts\PfxReaderInterface;
use App\Enums\CredentialStatus;
use App\Enums\OfficeCredentialPurpose;
use App\Enums\OfficeRole;
use App\Models\AuditLog;
use App\Models\Office;
use App\Models\OfficeCredential;
use App\Models\OfficeCredentialPurposeLink;
use App\Models\OfficeInstitutionalProfile;
use App\Models\User;
use App\Services\Auth\RecentPasswordConfirmationGate;
use App\Services\Certificates\OfficeCredentialService;
use App\Support\CurrentOffice;
use Carbon\CarbonImmutable;
use Database\Factories\EstablishmentFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Mockery;
use RuntimeException;
use Tests\TestCase;

/**
 * Task 2.2 — A1 canônico no vault, titularidade exata, vínculos SERPRO + autXML, sem segredo na API.
 */
class OfficeSettingsCanonicalCredentialTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_ativa_a1_canonico_com_vinculos_e_sem_segredo(): void
    {
        [$office, $admin] = $this->actingAsOfficeAdmin();
        OfficeInstitutionalProfile::factory()->forOffice($office)->create([
            'cnpj' => '11222333000181',
        ]);

        $this->mockPfxReaderForCnpj('11222333000181', 'fp-canonical-1', 'secret-pass');

        $file = UploadedFile::fake()->createWithContent('cert.pfx', 'fake-pfx-bytes-canonical');
        $response = $this->post('/api/v1/office/settings/credential', [
            'pfx' => $file,
            'password' => 'secret-pass',
            'office_id' => 12345,
        ], ['Accept' => 'application/json']);

        $response->assertCreated()
            ->assertJsonPath('data.credential.holder_cnpj', '11222333000181')
            ->assertJsonPath('data.credential.purpose', OfficeCredentialPurpose::CanonicalECnpjA1->value)
            ->assertJsonPath('data.credential.status', CredentialStatus::Active->value)
            ->assertJsonPath('data.credential.is_canonical', true)
            ->assertJsonPath('data.credential.fingerprint_sha256', 'fp-canonical-1');

        $body = $response->getContent() ?: '';
        $this->assertStringNotContainsString('vault_object_id', $body);
        $this->assertStringNotContainsString('secret-pass', $body);
        $this->assertStringNotContainsString('fake-pfx-bytes', $body);
        $this->assertStringNotContainsString('BEGIN', $body);

        $purposes = collect($response->json('data.purpose_links'))->pluck('purpose')->sort()->values()->all();
        $this->assertSame(
            [
                OfficeCredentialPurpose::NfeAutXmlDistDfe->value,
                OfficeCredentialPurpose::SerproTermSigning->value,
            ],
            $purposes,
        );

        $credential = OfficeCredential::query()
            ->where('office_id', $office->id)
            ->where('purpose', OfficeCredentialPurpose::CanonicalECnpjA1->value)
            ->where('status', CredentialStatus::Active)
            ->first();
        $this->assertNotNull($credential);
        $this->assertNull($credential->office_fiscal_identity_id);
        $this->assertNotEmpty($credential->vault_object_id);

        // Ambos vínculos apontam para o mesmo material (mesmo vault_object_id da canônica).
        $links = OfficeCredentialPurposeLink::query()
            ->where('office_credential_id', $credential->id)
            ->where('status', CredentialStatus::Active)
            ->get();
        $this->assertCount(2, $links);
        foreach ($links as $link) {
            $this->assertSame($credential->id, $link->office_credential_id);
            $this->assertSame($credential->vault_object_id, $link->credential->vault_object_id);
        }

        // Vault tem material; loadPfxMaterial recupera em memória.
        $material = app(OfficeCredentialService::class)->loadPfxMaterial($credential);
        $this->assertNotNull($material);
        $this->assertSame('fake-pfx-bytes-canonical', $material['pfx']);
        $this->assertSame('secret-pass', $material['password']);

        $audit = AuditLog::query()->where('action', 'office_credential.canonical.activate')->latest('id')->first();
        $this->assertNotNull($audit);
        $this->assertSame('SUCCESS', $audit->result);
        $ctx = json_encode($audit->context ?? []);
        $this->assertStringNotContainsString('secret-pass', (string) $ctx);
        $this->assertStringNotContainsString('vault_object_id', (string) $ctx);
        $this->assertSame($admin->id, $audit->user_id);
    }

    public function test_upload_cnpj_incompativel_nao_persiste_credencial(): void
    {
        [$office] = $this->actingAsOfficeAdmin();
        OfficeInstitutionalProfile::factory()->forOffice($office)->create([
            'cnpj' => '11222333000181',
        ]);

        $otherCnpj = EstablishmentFactory::cnpjWithRoot('99888777');
        $this->mockPfxReaderForCnpj($otherCnpj, 'fp-wrong', 'x');

        $file = UploadedFile::fake()->createWithContent('cert.pfx', 'wrong-cnpj-pfx');
        $this->post('/api/v1/office/settings/credential', [
            'pfx' => $file,
            'password' => 'x',
        ], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonFragment([
                'message' => 'O CNPJ titular do certificado deve ser exatamente igual ao CNPJ do perfil institucional.',
            ]);

        $this->assertSame(0, OfficeCredential::query()->where('office_id', $office->id)->count());
        $this->assertSame(0, OfficeCredentialPurposeLink::query()->where('office_id', $office->id)->count());
    }

    public function test_upload_senha_invalida_nao_persiste(): void
    {
        [$office] = $this->actingAsOfficeAdmin();
        OfficeInstitutionalProfile::factory()->forOffice($office)->create([
            'cnpj' => '11222333000181',
        ]);

        $reader = Mockery::mock(PfxReaderInterface::class);
        $reader->shouldReceive('read')->andThrow(new RuntimeException('Senha incorreta ou PFX inválido.'));
        $this->app->instance(PfxReaderInterface::class, $reader);

        $file = UploadedFile::fake()->createWithContent('cert.pfx', 'bad');
        $this->post('/api/v1/office/settings/credential', [
            'pfx' => $file,
            'password' => 'wrong',
        ], ['Accept' => 'application/json'])
            ->assertStatus(422);

        $this->assertSame(0, OfficeCredential::query()->where('office_id', $office->id)->count());
    }

    public function test_duas_finalidades_resolvem_mesma_credencial_canonica(): void
    {
        $office = Office::factory()->create();
        OfficeInstitutionalProfile::factory()->forOffice($office)->create(['cnpj' => '11222333000181']);
        $admin = User::factory()->forOffice($office, OfficeRole::Admin)->withTwoFactorConfirmed()->create();
        $this->actingAs($admin);
        app(CurrentOffice::class)->resolve($admin);

        $this->mockPfxReaderForCnpj('11222333000181', 'fp-shared', 'pw');
        $service = app(OfficeCredentialService::class);
        $created = $service->activateCanonical('pfx-bytes', 'pw', $admin->id);

        $viaTermo = $service->activeForPurpose($office->id, OfficeCredentialPurpose::SerproTermSigning);
        $viaAutXml = $service->activeForPurpose($office->id, OfficeCredentialPurpose::NfeAutXmlDistDfe);

        $this->assertNotNull($viaTermo);
        $this->assertNotNull($viaAutXml);
        $this->assertSame($created->id, $viaTermo->id);
        $this->assertSame($created->id, $viaAutXml->id);
        $this->assertSame($created->vault_object_id, $viaTermo->vault_object_id);
        $this->assertSame($created->vault_object_id, $viaAutXml->vault_object_id);

        // Nenhuma segunda cópia lógica do purpose canônico
        $this->assertSame(1, OfficeCredential::query()
            ->where('office_id', $office->id)
            ->where('purpose', OfficeCredentialPurpose::CanonicalECnpjA1)
            ->where('status', CredentialStatus::Active)
            ->count());
    }

    public function test_to_public_array_e_json_nao_expoem_vault(): void
    {
        $office = Office::factory()->create();
        $credential = OfficeCredential::factory()->forOffice($office)->canonical()->create([
            'vault_object_id' => '01SECRETVAULTOBJECTIDXXXX',
            'holder_cnpj' => '11222333000181',
        ]);

        $public = $credential->toPublicArray();
        $this->assertArrayNotHasKey('vault_object_id', $public);
        $this->assertArrayNotHasKey('password', $public);
        $this->assertArrayNotHasKey('pfx', $public);

        $serialized = $credential->toArray();
        $this->assertArrayNotHasKey('vault_object_id', $serialized);

        $json = $credential->toJson();
        $this->assertStringNotContainsString('01SECRETVAULTOBJECTIDXXXX', $json);
        $this->assertStringNotContainsString('vault_object_id', $json);
    }

    public function test_operator_nao_pode_ativar_a1(): void
    {
        $office = Office::factory()->create();
        OfficeInstitutionalProfile::factory()->forOffice($office)->create(['cnpj' => '11222333000181']);
        $operator = User::factory()->forOffice($office, OfficeRole::Operator)->withTwoFactorConfirmed()->create();
        $this->actingAs($operator);
        app(CurrentOffice::class)->resolve($operator);

        $file = UploadedFile::fake()->createWithContent('cert.pfx', 'x');
        $this->post('/api/v1/office/settings/credential', [
            'pfx' => $file,
            'password' => 'x',
        ], ['Accept' => 'application/json'])->assertForbidden();
    }

    public function test_exige_perfil_com_cnpj_antes_do_a1(): void
    {
        [$office] = $this->actingAsOfficeAdmin();
        OfficeInstitutionalProfile::factory()->forOffice($office)->incomplete()->create();

        $file = UploadedFile::fake()->createWithContent('cert.pfx', 'x');
        $this->post('/api/v1/office/settings/credential', [
            'pfx' => $file,
            'password' => 'x',
        ], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonFragment([
                'message' => 'Cadastre o CNPJ do perfil institucional antes do certificado A1.',
            ]);
    }

    public function test_sem_rota_download_ou_recover_do_a1_settings(): void
    {
        [$office] = $this->actingAsOfficeAdmin();

        $this->getJson('/api/v1/office/settings/credential/download')->assertNotFound();
        $this->getJson('/api/v1/office/settings/credential/recover')->assertNotFound();
        $this->getJson('/api/v1/office/settings/credential/export')->assertNotFound();
    }

    private function mockPfxReaderForCnpj(string $cnpj, string $fingerprint, string $password): void
    {
        $reader = Mockery::mock(PfxReaderInterface::class);
        $reader->shouldReceive('read')->andReturn([
            'pfx' => 'fake-pfx-bytes-canonical',
            'password' => $password,
            'subject_name' => 'ESCRITORIO CANONICO',
            'cnpj' => $cnpj,
            'fingerprint_sha256' => $fingerprint,
            'valid_from' => CarbonImmutable::now()->subYear(),
            'valid_to' => CarbonImmutable::now()->addYear(),
        ]);
        $this->app->instance(PfxReaderInterface::class, $reader);
    }

    /**
     * @return array{0: Office, 1: User}
     */
    private function actingAsOfficeAdmin(): array
    {
        $office = Office::factory()->create();
        $admin = User::factory()->forOffice($office, OfficeRole::Admin)->create();
        $this->actingAs($admin);
        app(CurrentOffice::class)->resolve($admin);
        app(RecentPasswordConfirmationGate::class)->markConfirmed($admin);

        return [$office, $admin];
    }
}

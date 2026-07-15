<?php

namespace Tests\Feature\Credentials;

use App\Contracts\PfxReaderInterface;
use App\Enums\CredentialStatus;
use App\Enums\OfficeRole;
use App\Enums\SyncCursorStatus;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\ClientCredential;
use App\Models\Office;
use App\Models\OfficeCredential;
use App\Models\OfficeDistributionCursor;
use App\Models\OfficeFiscalIdentity;
use App\Models\User;
use App\Services\Certificates\OfficeCredentialResolver;
use App\Services\Certificates\OfficeCredentialService;
use App\Services\Certificates\OfficeFiscalIdentityService;
use App\Support\CurrentOffice;
use Carbon\CarbonImmutable;
use Database\Factories\EstablishmentFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class OfficeFiscalCredentialTest extends TestCase
{
    use RefreshDatabase;

    public function test_normaliza_cnpj_alfanumerico_uppercase(): void
    {
        $service = app(OfficeFiscalIdentityService::class);
        // Usa CNPJ numérico válido; normalização uppercase é testada no Domain\Cnpj.
        $parsed = $service->normalizeCnpj('11.222.333/0001-81');
        $this->assertSame('11222333000181', $parsed['cnpj']);
        $this->assertSame('11222333', $parsed['root_cnpj']);
        $this->assertSame('AB12CD34', $service->assertTextCnpj('ab12cd34', 8));
    }

    public function test_viewer_le_metadados_operator_nao_muta(): void
    {
        $office = Office::factory()->create();
        $viewer = User::factory()->forOffice($office, OfficeRole::Viewer)->withTwoFactorConfirmed()->create();
        $operator = User::factory()->forOffice($office, OfficeRole::Operator)->withTwoFactorConfirmed()->create();
        OfficeFiscalIdentity::factory()->forOffice($office)->create();

        $this->actingAs($viewer);
        app(CurrentOffice::class)->resolve($viewer);
        $this->getJson('/api/v1/office/fiscal-identity')
            ->assertOk()
            ->assertJsonPath('data.identity.cnpj', '11222333000181');

        $this->actingAs($operator);
        app(CurrentOffice::class)->resolve($operator);
        $this->postJson('/api/v1/office/fiscal-identity', [
            'cnpj' => '11222333000181',
        ])->assertForbidden();
    }

    public function test_admin_cadastra_identidade_e_ativa_a1(): void
    {
        $office = Office::factory()->create();
        $admin = User::factory()->forOffice($office, OfficeRole::Admin)->withTwoFactorConfirmed()->create();
        $this->actingAs($admin);
        app(CurrentOffice::class)->resolve($admin);

        $this->postJson('/api/v1/office/fiscal-identity', [
            'cnpj' => '11.222.333/0001-81',
            'legal_name' => 'Escritório Teste',
        ])->assertCreated()
            ->assertJsonPath('data.cnpj', '11222333000181')
            ->assertJsonPath('data.root_cnpj', '11222333');

        $this->mockPfxReaderMatchingRoot('11222333');

        $file = UploadedFile::fake()->createWithContent('cert.pfx', 'fake-pfx-bytes');
        $response = $this->post('/api/v1/office/fiscal-identity/credential', [
            'pfx' => $file,
            'password' => 'secret-pass',
        ], ['Accept' => 'application/json']);

        $response->assertCreated()
            ->assertJsonPath('data.holder_cnpj', EstablishmentFactory::cnpjWithRoot('11222333'));

        $body = $response->getContent() ?: '';
        $this->assertStringNotContainsString('vault_object_id', $body);
        $this->assertStringNotContainsString('secret-pass', $body);
        $this->assertStringNotContainsString('BEGIN', $body);
        $this->assertStringNotContainsString('fake-pfx-bytes', $body);

        $audit = AuditLog::query()->where('action', 'office_credential.activate')->latest('id')->first();
        $this->assertNotNull($audit);
        $ctx = json_encode($audit->context ?? []);
        $this->assertStringNotContainsString('secret-pass', (string) $ctx);
        $this->assertStringNotContainsString('vault_object_id', (string) $ctx);
    }

    public function test_raiz_incompativel_rejeita_como_rv593(): void
    {
        $office = Office::factory()->create();
        $admin = User::factory()->forOffice($office, OfficeRole::Admin)->withTwoFactorConfirmed()->create();
        $this->actingAs($admin);
        app(CurrentOffice::class)->resolve($admin);

        OfficeFiscalIdentity::factory()->forOffice($office)->withCnpj('11222333000181')->create();
        $this->mockPfxReaderMatchingRoot('99888777');

        $file = UploadedFile::fake()->createWithContent('cert.pfx', 'fake-pfx-bytes');
        $this->post('/api/v1/office/fiscal-identity/credential', [
            'pfx' => $file,
            'password' => 'x',
        ], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonFragment(['message' => 'A raiz do CNPJ do certificado diverge da identidade fiscal do escritório (equivalente à RV 593).']);
    }

    public function test_resolver_rejeita_credencial_de_cliente(): void
    {
        $office = Office::factory()->create();
        $client = Client::factory()->forOffice($office)->create(['root_cnpj' => '11222333']);
        $clientCred = ClientCredential::query()->create([
            'office_id' => $office->id,
            'client_id' => $client->id,
            'status' => CredentialStatus::Active,
            'subject_name' => 'Cliente',
            'holder_cnpj' => '11222333000181',
            'fingerprint_sha256' => str_repeat('A', 64),
            'valid_from' => now()->subYear(),
            'valid_to' => now()->addYear(),
            'vault_object_id' => strtoupper(Str::ulid()->toBase32()),
            'activated_at' => now(),
        ]);

        $resolver = app(OfficeCredentialResolver::class);
        $this->expectException(RuntimeException::class);
        $resolver->rejectClientCredential($clientCred);
    }

    public function test_resolver_exige_identidade_e_a1_do_mesmo_office(): void
    {
        $office = Office::factory()->create();
        $this->expectException(RuntimeException::class);
        app(OfficeCredentialResolver::class)->resolveForAutXml($office->id);
    }

    public function test_expiracao_bloqueia_somente_cursores_autxml(): void
    {
        $office = Office::factory()->create();
        $identity = OfficeFiscalIdentity::factory()->forOffice($office)->create();
        $cred = OfficeCredential::factory()->forIdentity($identity)->create([
            'valid_to' => now()->subDay(),
            'status' => CredentialStatus::Active,
        ]);
        $cursor = OfficeDistributionCursor::factory()->forIdentity($identity)->create([
            'status' => SyncCursorStatus::Idle,
        ]);

        // loadPfxMaterial marca expirado e bloqueia cursores
        $material = app(OfficeCredentialService::class)->loadPfxMaterial($cred);
        $this->assertNull($material);
        $this->assertSame(CredentialStatus::Expired, $cred->fresh()->status);
        $this->assertSame(SyncCursorStatus::Blocked, $cursor->fresh()->status);
    }

    public function test_cross_tenant_nao_ve_identidade(): void
    {
        $officeA = Office::factory()->create();
        $officeB = Office::factory()->create();
        OfficeFiscalIdentity::factory()->forOffice($officeA)->create();
        $userB = User::factory()->forOffice($officeB, OfficeRole::Admin)->withTwoFactorConfirmed()->create();

        $this->actingAs($userB);
        app(CurrentOffice::class)->resolve($userB);

        $this->getJson('/api/v1/office/fiscal-identity')
            ->assertOk()
            ->assertJsonPath('data.identity', null);
    }

    public function test_sem_rota_de_recuperacao_de_pfx(): void
    {
        $office = Office::factory()->create();
        $admin = User::factory()->forOffice($office, OfficeRole::Admin)->withTwoFactorConfirmed()->create();
        $this->actingAs($admin);
        app(CurrentOffice::class)->resolve($admin);

        // Nenhuma rota GET de download de material
        $this->getJson('/api/v1/office/fiscal-identity/credential/download')->assertNotFound();
        $this->getJson('/api/v1/office/fiscal-identity/credential/recover')->assertNotFound();
    }

    private function mockPfxReaderMatchingRoot(string $root8): void
    {
        $reader = Mockery::mock(PfxReaderInterface::class);
        $reader->shouldReceive('read')->andReturn([
            'pfx' => 'fake-pfx-bytes',
            'password' => 'secret-pass',
            'subject_name' => 'ESCRITORIO TESTE',
            'cnpj' => EstablishmentFactory::cnpjWithRoot($root8),
            'fingerprint_sha256' => str_repeat('B', 64),
            'valid_from' => CarbonImmutable::now()->subYear(),
            'valid_to' => CarbonImmutable::now()->addYear(),
        ]);
        $this->app->instance(PfxReaderInterface::class, $reader);
    }
}

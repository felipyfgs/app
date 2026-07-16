<?php

namespace Tests\Feature\OfficeConfig;

use App\Contracts\PfxReaderInterface;
use App\Enums\CredentialStatus;
use App\Enums\OfficeCredentialPurpose;
use App\Enums\OfficeRole;
use App\Enums\OfficeSerproOnboardingStatus;
use App\Enums\SerproEnvironment;
use App\Enums\SyncCursorStatus;
use App\Models\AuditLog;
use App\Models\Office;
use App\Models\OfficeCredential;
use App\Models\OfficeCredentialPurposeLink;
use App\Models\OfficeDistributionCursor;
use App\Models\OfficeFiscalIdentity;
use App\Models\OfficeInstitutionalProfile;
use App\Models\OfficeSerproOnboardingState;
use App\Models\OfficeTechnicalConsent;
use App\Models\User;
use App\Services\Auth\RecentPasswordConfirmationGate;
use App\Services\Certificates\OfficeCredentialService;
use App\Support\CurrentOffice;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Mockery;
use RuntimeException;
use Tests\TestCase;

/**
 * Task 2.3 — consentir/revogar, replace validate-before-cutover, remoção, reonboarding, alertas 30/7/1.
 */
class OfficeSettingsConsentReplaceAlertsTest extends TestCase
{
    use RefreshDatabase;

    public function test_grant_e_revoke_consentimento_versionado(): void
    {
        [$office, $admin] = $this->actingAsOfficeAdmin();

        $this->getJson('/api/v1/office/settings/consent')
            ->assertOk()
            ->assertJsonPath('data.requires_consent', true)
            ->assertJsonPath('data.version_code', OfficeTechnicalConsent::VERSION_UNIFIED_A1_V1)
            ->assertJsonPath('data.active_consent', null);

        $grant = $this->postJson('/api/v1/office/settings/consent', [
            'accepted' => true,
            'office_id' => 999,
        ]);

        $grant->assertCreated()
            ->assertJsonPath('data.version_code', OfficeTechnicalConsent::VERSION_UNIFIED_A1_V1)
            ->assertJsonPath('data.active', true)
            ->assertJsonMissingPath('data.password');

        $this->assertNotNull($grant->json('data.consented_at'));
        $this->assertSame(1, OfficeTechnicalConsent::query()->where('office_id', $office->id)->count());

        $this->getJson('/api/v1/office/settings/consent')
            ->assertOk()
            ->assertJsonPath('data.requires_consent', false);

        $revoke = $this->postJson('/api/v1/office/settings/consent/revoke', [
            'office_id' => 1,
        ]);
        $revoke->assertOk()
            ->assertJsonPath('data.active', false);
        $this->assertNotNull($revoke->json('data.revoked_at'));

        $this->getJson('/api/v1/office/settings/consent')
            ->assertOk()
            ->assertJsonPath('data.requires_consent', true);

        $auditGrant = AuditLog::query()->where('action', 'office.technical_consent.grant')->first();
        $this->assertNotNull($auditGrant);
        $this->assertSame($admin->id, $auditGrant->user_id);
    }

    public function test_grant_sem_accepted_falha(): void
    {
        $this->actingAsOfficeAdmin();

        $this->postJson('/api/v1/office/settings/consent', [
            'accepted' => false,
        ])->assertStatus(422);
    }

    public function test_replace_invalido_preserva_a1_anterior(): void
    {
        [$office, $admin] = $this->actingAsOfficeAdmin();
        OfficeInstitutionalProfile::factory()->forOffice($office)->create([
            'cnpj' => '11222333000181',
        ]);

        $reader = Mockery::mock(PfxReaderInterface::class);
        $reader->shouldReceive('read')
            ->once()
            ->andReturn($this->pfxMeta('11222333000181', 'fp-old', 'secret-old', 'old-pfx-bytes'));
        $reader->shouldReceive('read')
            ->once()
            ->andThrow(new RuntimeException('PFX corrompido ou senha inválida.'));
        $this->app->instance(PfxReaderInterface::class, $reader);

        $file1 = UploadedFile::fake()->createWithContent('old.pfx', 'old-pfx-bytes');
        $this->post('/api/v1/office/settings/credential', [
            'pfx' => $file1,
            'password' => 'secret-old',
        ], ['Accept' => 'application/json'])->assertCreated();

        $activeBefore = app(OfficeCredentialService::class)->activeCanonicalForCurrentOffice();
        $this->assertNotNull($activeBefore);
        $oldId = $activeBefore->id;
        $oldFp = $activeBefore->fingerprint_sha256;
        $oldVault = $activeBefore->vault_object_id;

        $file2 = UploadedFile::fake()->createWithContent('bad.pfx', 'bad-bytes');
        $this->post('/api/v1/office/settings/credential/replace', [
            'pfx' => $file2,
            'password' => 'nope',
        ], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonPath('previous_preserved', true);

        $still = app(OfficeCredentialService::class)->activeCanonicalForCurrentOffice();
        $this->assertNotNull($still);
        $this->assertSame($oldId, $still->id);
        $this->assertSame($oldFp, $still->fingerprint_sha256);
        $this->assertSame($oldVault, $still->vault_object_id);
        $this->assertSame(CredentialStatus::Active, $still->status);

        // Material antigo ainda carregável
        $material = app(OfficeCredentialService::class)->loadPfxMaterial($still);
        $this->assertNotNull($material);
        $this->assertSame('old-pfx-bytes', $material['pfx']);

        $failAudit = AuditLog::query()
            ->where('action', 'office_credential.canonical.replace')
            ->where('result', 'FAILED')
            ->latest('id')
            ->first();
        $this->assertNotNull($failAudit);
        $this->assertTrue((bool) data_get($failAudit->context, 'previous_still_active'));
    }

    public function test_replace_valido_cutover_unico_e_vinculos_novos(): void
    {
        [$office, $admin] = $this->actingAsOfficeAdmin();
        OfficeInstitutionalProfile::factory()->forOffice($office)->create([
            'cnpj' => '11222333000181',
        ]);

        $state = OfficeSerproOnboardingState::query()->create([
            'office_id' => $office->id,
            'environment' => SerproEnvironment::Production,
            'status' => OfficeSerproOnboardingStatus::Authorized,
            'last_transition_at' => now(),
            'authorized_at' => now(),
        ]);

        $reader = Mockery::mock(PfxReaderInterface::class);
        $reader->shouldReceive('read')
            ->once()
            ->andReturn($this->pfxMeta('11222333000181', 'fp-v1', 'p1', 'pfx-v1'));
        $reader->shouldReceive('read')
            ->once()
            ->andReturn($this->pfxMeta('11222333000181', 'fp-v2', 'p2', 'pfx-v2'));
        $this->app->instance(PfxReaderInterface::class, $reader);

        $this->post('/api/v1/office/settings/credential', [
            'pfx' => UploadedFile::fake()->createWithContent('a.pfx', 'pfx-v1'),
            'password' => 'p1',
        ], ['Accept' => 'application/json'])->assertCreated();

        $old = app(OfficeCredentialService::class)->activeCanonicalForCurrentOffice();
        $this->assertNotNull($old);

        $this->post('/api/v1/office/settings/credential/replace', [
            'pfx' => UploadedFile::fake()->createWithContent('b.pfx', 'pfx-v2'),
            'password' => 'p2',
        ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.credential.fingerprint_sha256', 'fp-v2')
            ->assertJsonPath('data.credential.status', CredentialStatus::Active->value);

        $this->assertSame(CredentialStatus::Superseded, $old->fresh()->status);

        $active = app(OfficeCredentialService::class)->activeCanonicalForCurrentOffice();
        $this->assertNotNull($active);
        $this->assertSame('fp-v2', $active->fingerprint_sha256);
        $this->assertSame(
            1,
            OfficeCredential::query()
                ->where('office_id', $office->id)
                ->where('purpose', OfficeCredentialPurpose::CanonicalECnpjA1)
                ->where('status', CredentialStatus::Active)
                ->count(),
        );

        $activeLinks = OfficeCredentialPurposeLink::query()
            ->where('office_credential_id', $active->id)
            ->where('status', CredentialStatus::Active)
            ->count();
        $this->assertSame(2, $activeLinks);

        $state->refresh();
        $this->assertNotSame(OfficeSerproOnboardingStatus::Authorized, $state->status);
        $this->assertTrue(
            $state->status === OfficeSerproOnboardingStatus::ActionRequired
            || $state->status === OfficeSerproOnboardingStatus::Incomplete
        );
        $this->assertNotNull(
            AuditLog::query()->where('action', 'serpro.authorization.invalidate_derived')->first()
        );
    }

    public function test_remocao_exige_confirm_e_bloqueia_finalidades(): void
    {
        [$office] = $this->actingAsOfficeAdmin();
        OfficeInstitutionalProfile::factory()->forOffice($office)->create([
            'cnpj' => '11222333000181',
        ]);

        // Pré-cria estado AUTHORIZED; activateCanonical reage e pode alterar — reautoriza após upload.
        $state = OfficeSerproOnboardingState::query()->create([
            'office_id' => $office->id,
            'environment' => SerproEnvironment::Production,
            'status' => OfficeSerproOnboardingStatus::Authorized,
            'last_transition_at' => now(),
            'authorized_at' => now(),
        ]);

        $this->mockPfxOnce('11222333000181', 'fp-rm', 'pw', 'pfx-rm');
        $this->post('/api/v1/office/settings/credential', [
            'pfx' => UploadedFile::fake()->createWithContent('c.pfx', 'pfx-rm'),
            'password' => 'pw',
        ], ['Accept' => 'application/json'])->assertCreated();

        $credential = app(OfficeCredentialService::class)->activeCanonicalForCurrentOffice();
        $this->assertNotNull($credential);

        // Garante estado derivado sensível antes da remoção.
        $state->refresh();
        $state->status = OfficeSerproOnboardingStatus::Authorized;
        $state->authorized_at = now();
        $state->save();

        $identity = OfficeFiscalIdentity::factory()->forOffice($office)->create();
        $cursor = OfficeDistributionCursor::factory()->forIdentity($identity)->create([
            'office_id' => $office->id,
            'status' => SyncCursorStatus::Idle,
        ]);

        $this->postJson('/api/v1/office/settings/credential/remove', [])
            ->assertStatus(422);

        $this->postJson('/api/v1/office/settings/credential/remove', [
            'confirm' => false,
        ])->assertStatus(422);

        $this->postJson('/api/v1/office/settings/credential/remove', [
            'confirm' => true,
        ])->assertOk()
            ->assertJsonPath('data.removed', true)
            ->assertJsonPath('data.credential.status', CredentialStatus::Revoked->value);

        $this->assertNull(app(OfficeCredentialService::class)->activeCanonicalForCurrentOffice());
        $this->assertSame(CredentialStatus::Revoked, $credential->fresh()->status);

        $linksActive = OfficeCredentialPurposeLink::query()
            ->where('office_id', $office->id)
            ->where('status', CredentialStatus::Active)
            ->count();
        $this->assertSame(0, $linksActive);

        $this->assertSame(SyncCursorStatus::Blocked, $cursor->fresh()->status);

        $state->refresh();
        $this->assertNotSame(OfficeSerproOnboardingStatus::Authorized, $state->status);

        // Sem download após remoção
        $this->getJson('/api/v1/office/settings/credential/download')->assertNotFound();
        $show = $this->getJson('/api/v1/office/settings/credential')->assertOk();
        $this->assertNull($show->json('data.credential'));
        $body = $show->getContent() ?: '';
        $this->assertStringNotContainsString('"password"', $body);
        $this->assertStringNotContainsString('vault_object_id', $body);
    }

    public function test_alertas_painel_30_7_1_sem_email(): void
    {
        Mail::fake();
        Notification::fake();

        [$office] = $this->actingAsOfficeAdmin();
        OfficeInstitutionalProfile::factory()->forOffice($office)->create([
            'cnpj' => '11222333000181',
        ]);

        // Credencial a 5 dias → janela 7 (e 30)
        $credential = OfficeCredential::factory()->forOffice($office)->canonical()->create([
            'holder_cnpj' => '11222333000181',
            'valid_from' => now()->subYear(),
            'valid_to' => now()->addDays(5),
            'expires_alert_30' => false,
            'expires_alert_7' => false,
            'expires_alert_1' => false,
        ]);

        $service = app(OfficeCredentialService::class);
        $updated = $service->refreshExpiryAlerts();
        $this->assertGreaterThanOrEqual(1, $updated['credentials']);

        $credential->refresh();
        $this->assertTrue($credential->expires_alert_30);
        $this->assertTrue($credential->expires_alert_7);
        $this->assertFalse($credential->expires_alert_1);

        $alerts = $service->panelExpiryAlerts($credential);
        $this->assertCount(1, $alerts);
        $this->assertSame(7, $alerts[0]['window_days']);
        $this->assertSame('A1_EXPIRES_7D', $alerts[0]['code']);

        // Dedupe: segunda refresh não reenvia / não reseta
        $again = $service->refreshExpiryAlerts();
        // flags já setadas → sem mudança no credential (pode ser 0 se só este)
        $credential->refresh();
        $this->assertTrue($credential->expires_alert_7);

        $show = $this->getJson('/api/v1/office/settings/credential')->assertOk();
        $codes = collect($show->json('data.alerts'))->pluck('code')->all();
        $this->assertContains('A1_EXPIRES_7D', $codes);

        // Janela 1 dia
        $credential->valid_to = now()->addHours(12);
        $credential->expires_alert_1 = false;
        $credential->save();
        $service->refreshExpiryAlerts();
        $credential->refresh();
        $this->assertTrue($credential->expires_alert_1);
        $alerts1 = $service->panelExpiryAlerts($credential);
        $this->assertSame(1, $alerts1[0]['window_days']);

        Mail::assertNothingSent();
        Notification::assertNothingSent();
    }

    public function test_alertas_janela_30_dias(): void
    {
        [$office] = $this->actingAsOfficeAdmin();
        $credential = OfficeCredential::factory()->forOffice($office)->canonical()->create([
            'valid_to' => now()->addDays(25),
            'expires_alert_30' => false,
            'expires_alert_7' => false,
            'expires_alert_1' => false,
        ]);

        app(OfficeCredentialService::class)->refreshExpiryAlerts();
        $credential->refresh();
        $this->assertTrue($credential->expires_alert_30);
        $this->assertFalse($credential->expires_alert_7);

        $alerts = app(OfficeCredentialService::class)->panelExpiryAlerts($credential);
        $this->assertSame(30, $alerts[0]['window_days']);
    }

    /**
     * @return array{pfx: string, password: string, subject_name: string, cnpj: string, fingerprint_sha256: string, valid_from: CarbonImmutable, valid_to: CarbonImmutable}
     */
    private function pfxMeta(string $cnpj, string $fp, string $password, string $pfxBytes): array
    {
        return [
            'pfx' => $pfxBytes,
            'password' => $password,
            'subject_name' => 'ESCRITORIO',
            'cnpj' => $cnpj,
            'fingerprint_sha256' => $fp,
            'valid_from' => CarbonImmutable::now()->subYear(),
            'valid_to' => CarbonImmutable::now()->addYear(),
        ];
    }

    private function mockPfxOnce(string $cnpj, string $fp, string $password, string $pfxBytes): void
    {
        $reader = Mockery::mock(PfxReaderInterface::class);
        $reader->shouldReceive('read')->once()->andReturn($this->pfxMeta($cnpj, $fp, $password, $pfxBytes));
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

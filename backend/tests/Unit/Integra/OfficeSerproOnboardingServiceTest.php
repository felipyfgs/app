<?php

namespace Tests\Unit\Integra;

use App\Contracts\SecureObjectStore;
use App\Enums\AuthorCertificateMode;
use App\Enums\AuthorIdentityType;
use App\Enums\OfficeSerproOnboardingStatus;
use App\Enums\SecureObjectPurpose;
use App\Enums\SerproAuthorizationStatus;
use App\Enums\SerproEnvironment;
use App\Enums\TermoAuthorizationState;
use App\Jobs\Serpro\ProcessOfficeSerproOnboardingJob;
use App\Models\Office;
use App\Models\OfficeInstitutionalProfile;
use App\Models\OfficeSerproAuthorization;
use App\Models\OfficeTechnicalConsent;
use App\Models\User;
use App\Services\Integra\OfficeSerproOnboardingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class OfficeSerproOnboardingServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_incomplete_when_prerequisites_missing(): void
    {
        $office = Office::factory()->create(['name' => '']);
        $service = app(OfficeSerproOnboardingService::class);

        $result = $service->evaluateAndMaybeEnqueue($office, SerproEnvironment::Trial);

        $this->assertFalse($result['enqueued']);
        $this->assertSame(OfficeSerproOnboardingStatus::Incomplete, $result['state']->status);
        $this->assertNotNull($result['state']->actionable_code);
    }

    public function test_enqueues_once_when_ready_and_is_idempotent(): void
    {
        Queue::fake();
        config(['serpro.trial.use_fake_clients' => true]);

        $office = Office::factory()->create(['name' => 'Escritório Teste']);
        $this->seedReadyAuthorization($office);

        $service = app(OfficeSerproOnboardingService::class);

        $first = $service->evaluateAndMaybeEnqueue($office, SerproEnvironment::Trial);
        $second = $service->evaluateAndMaybeEnqueue($office, SerproEnvironment::Trial);

        $this->assertTrue($first['enqueued']);
        $this->assertFalse($second['enqueued']);
        $this->assertSame($first['state']->idempotency_key, $second['state']->idempotency_key);
        $this->assertSame(OfficeSerproOnboardingStatus::Provisioning, $second['state']->status);

        Queue::assertPushed(ProcessOfficeSerproOnboardingJob::class, 1);
    }

    public function test_process_external_signature_without_termo_is_action_required(): void
    {
        config([
            'serpro.trial.use_fake_clients' => true,
            'serpro.termo_destination_cnpj' => '11222333000181',
            'serpro.termo_destination_name' => 'CONTRATANTE TESTE',
        ]);

        $office = Office::factory()->create(['name' => 'Escritório Process']);
        $auth = $this->seedReadyAuthorization($office);

        $service = app(OfficeSerproOnboardingService::class);
        $prereq = $service->evaluatePrerequisites($office, SerproEnvironment::Trial);
        $this->assertTrue($prereq['complete'], 'prerequisites should be complete');

        $key = 'test-idem-'.hash('sha256', (string) $office->id);

        // External signature: sem Termo assinado → pendência acionável (não erro técnico OAuth)
        $auth->certificate_mode = AuthorCertificateMode::ExternalSignature;
        $auth->author_pfx_vault_object_id = null;
        $auth->termo_vault_object_id = null;
        $auth->save();

        $processed = $service->process($office, SerproEnvironment::Trial, $key);

        $this->assertSame(OfficeSerproOnboardingStatus::ActionRequired, $processed->status);
        $this->assertSame('UPLOAD_TERMO', $processed->actionable_code);
        $this->assertNull($processed->technical_code);
    }

    public function test_process_with_signed_termo_uses_fake_apoiar(): void
    {
        config([
            'serpro.trial.use_fake_clients' => true,
            'serpro.termo_destination_cnpj' => '11222333000181',
        ]);

        $office = Office::factory()->create(['name' => 'Escritório Apoiar']);
        $auth = $this->seedReadyAuthorization($office);
        $service = app(OfficeSerproOnboardingService::class);

        $store = app(SecureObjectStore::class);
        $sha = hash('sha256', '<termo>fake</termo>');
        $aad = SecureObjectPurpose::SerproTermoXml->aadBase([
            'office_id' => $office->id,
            'environment' => SerproEnvironment::Trial->value,
            'kind' => 'signed',
            'sha256' => $sha,
            'author_identity' => $auth->author_identity,
        ]);
        $vaultId = $store->put('<termo>fake</termo>', $aad);

        $auth->certificate_mode = AuthorCertificateMode::ExternalSignature;
        $auth->termo_vault_object_id = $vaultId;
        $auth->termo_sha256 = $sha;
        $auth->termo_authorization_state = TermoAuthorizationState::LocalValidated;
        $auth->status = SerproAuthorizationStatus::TermValid;
        $auth->save();

        $key = 'test-apoiar-'.hash('sha256', (string) $office->id);
        $processed = $service->process($office, SerproEnvironment::Trial, $key);

        $this->assertContains(
            $processed->status,
            [
                OfficeSerproOnboardingStatus::Authorized,
                OfficeSerproOnboardingStatus::ActionRequired,
                OfficeSerproOnboardingStatus::TechnicalError,
            ],
        );

        if ($processed->status === OfficeSerproOnboardingStatus::TechnicalError) {
            $this->assertSame('PLATFORM_UNAVAILABLE', $processed->actionable_code);
            $this->assertStringNotContainsString('Bearer ', (string) $processed->technical_message);
        }
    }

    public function test_duplicate_process_same_idempotency_does_not_throw(): void
    {
        config(['serpro.trial.use_fake_clients' => true]);

        $office = Office::factory()->create(['name' => 'Dup']);
        $this->seedReadyAuthorization($office);
        $service = app(OfficeSerproOnboardingService::class);
        $state = $service->getOrCreateState($office, SerproEnvironment::Trial);
        $state->status = OfficeSerproOnboardingStatus::Authorized;
        $state->idempotency_key = 'same-key';
        $state->authorized_at = now();
        $state->save();

        $again = $service->process($office, SerproEnvironment::Trial, 'same-key');
        $this->assertSame(OfficeSerproOnboardingStatus::Authorized, $again->status);
    }

    public function test_react_to_profile_change_invalidates_and_reenqueues(): void
    {
        Queue::fake();
        config(['serpro.trial.use_fake_clients' => true]);

        $office = Office::factory()->create(['name' => 'React Office']);
        $this->seedReadyAuthorization($office);
        $service = app(OfficeSerproOnboardingService::class);

        $state = $service->getOrCreateState($office, SerproEnvironment::Trial);
        $state->status = OfficeSerproOnboardingStatus::Authorized;
        $state->idempotency_key = 'old-key';
        $state->authorized_at = now();
        $state->save();

        $after = $service->reactToProfileOrCredentialChange(
            $office,
            SerproEnvironment::Trial,
            reason: 'profile_cnpj_changed',
        );

        $this->assertContains(
            $after->status,
            [
                OfficeSerproOnboardingStatus::Provisioning,
                OfficeSerproOnboardingStatus::Ready,
                OfficeSerproOnboardingStatus::ActionRequired,
                OfficeSerproOnboardingStatus::Incomplete,
            ],
        );
        $this->assertNotSame('old-key', $after->idempotency_key);
        Queue::assertPushed(ProcessOfficeSerproOnboardingJob::class);
    }

    public function test_job_unique_id_is_stable_per_office_env_key(): void
    {
        $job = new ProcessOfficeSerproOnboardingJob(10, 'TRIAL', 'abc-key');
        $this->assertSame('serpro-onboarding:10:TRIAL:abc-key', $job->uniqueId());
    }

    private function seedReadyAuthorization(Office $office): OfficeSerproAuthorization
    {
        OfficeInstitutionalProfile::query()->create([
            'office_id' => $office->id,
            'cnpj' => '11222333000181',
            'legal_name' => 'Escritório Teste LTDA',
            'institutional_email' => 'contato@example.test',
            'institutional_phone' => '11999999999',
        ]);

        $actor = User::factory()->create();
        OfficeTechnicalConsent::query()->create([
            'office_id' => $office->id,
            'version_code' => OfficeTechnicalConsent::VERSION_UNIFIED_A1_V1,
            'purposes_presented' => ['SERPRO_TERM_SIGNING', 'NFE_AUTXML_DISTDFE'],
            'actor_user_id' => $actor->id,
            'consented_at' => now(),
            'payload_sha256' => hash('sha256', 'consent-test'),
        ]);

        return OfficeSerproAuthorization::query()->create([
            'office_id' => $office->id,
            'environment' => SerproEnvironment::Trial,
            'status' => SerproAuthorizationStatus::PendingTerm,
            'author_identity_type' => AuthorIdentityType::Cnpj,
            'author_identity' => '11222333000181',
            'author_name' => 'Escritório Autor',
            'certificate_mode' => AuthorCertificateMode::ManagedA1,
            'managed_a1_consent' => true,
            'managed_a1_consented_at' => now(),
            'author_pfx_vault_object_id' => '01ARZ3NDEKTSV4RRFFQ69G5FAV',
            'author_fingerprint_sha256' => hash('sha256', 'fp'),
            'author_cert_valid_from' => now()->subYear(),
            'author_cert_valid_to' => now()->addYear(),
            'termo_authorization_state' => TermoAuthorizationState::Draft,
        ]);
    }
}

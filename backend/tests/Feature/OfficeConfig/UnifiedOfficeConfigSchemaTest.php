<?php

namespace Tests\Feature\OfficeConfig;

use App\Enums\CredentialStatus;
use App\Enums\OfficeCredentialPurpose;
use App\Enums\OfficeFiscalIdentityStatus;
use App\Enums\OfficeSerproOnboardingStatus;
use App\Enums\SerproEnvironment;
use App\Models\Office;
use App\Models\OfficeCredential;
use App\Models\OfficeCredentialPurposeLink;
use App\Models\OfficeFiscalIdentity;
use App\Models\OfficeInstitutionalProfile;
use App\Models\OfficeSerproOnboardingState;
use App\Models\OfficeTechnicalConsent;
use App\Models\User;
use App\Support\FeatureFlags;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Task 1.1 — schema, backfill, models e segredos da configuração unificada.
 */
class UnifiedOfficeConfigSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_tabelas_config_unificada_existem(): void
    {
        $this->assertTrue(Schema::hasTable('office_institutional_profiles'));
        $this->assertTrue(Schema::hasTable('office_credential_purpose_links'));
        $this->assertTrue(Schema::hasTable('office_technical_consents'));
        $this->assertTrue(Schema::hasTable('office_serpro_onboarding_states'));
        $this->assertTrue(Schema::hasTable('office_credentials'));

        $this->assertTrue(Schema::hasColumns('office_institutional_profiles', [
            'office_id',
            'cnpj',
            'legal_name',
            'institutional_email',
            'institutional_phone',
        ]));

        $this->assertTrue(Schema::hasColumns('office_credential_purpose_links', [
            'office_id',
            'office_credential_id',
            'purpose',
            'status',
            'linked_at',
            'revoked_at',
        ]));

        $this->assertTrue(Schema::hasColumns('office_technical_consents', [
            'office_id',
            'version_code',
            'purposes_presented',
            'actor_user_id',
            'consented_at',
            'revoked_at',
            'payload_sha256',
        ]));

        $this->assertTrue(Schema::hasColumns('office_serpro_onboarding_states', [
            'office_id',
            'environment',
            'status',
            'actionable_code',
            'technical_code',
            'correlation_id',
        ]));
    }

    public function test_feature_flag_unified_office_config_default_off(): void
    {
        $this->assertFalse(FeatureFlags::isUnifiedOfficeConfigEnabled());
        $this->assertFalse(FeatureFlags::isUnifiedOfficeConfigEnabled(1));

        $snap = FeatureFlags::snapshot();
        $this->assertArrayHasKey('unified_office_config', $snap);
        $this->assertFalse($snap['unified_office_config']);

        config([
            'features.unified_office_config.enabled' => true,
            'features.unified_office_config.allow_all_offices' => true,
            'features.kill_switch' => false,
        ]);
        $this->assertTrue(FeatureFlags::isUnifiedOfficeConfigEnabled(99));

        config(['features.kill_switch' => true]);
        $this->assertFalse(FeatureFlags::isUnifiedOfficeConfigEnabled(99));
    }

    public function test_backfill_perfil_institucional_from_identity_e_office_name(): void
    {
        $office = Office::factory()->create(['name' => 'Escritorio Gamma']);
        OfficeFiscalIdentity::factory()->forOffice($office)->create([
            'cnpj' => '11222333000181',
            'legal_name' => 'Escritorio Gamma LTDA',
            'status' => OfficeFiscalIdentityStatus::Active,
        ]);

        $officeNoIdentity = Office::factory()->create(['name' => 'Somente Nome']);

        Schema::dropIfExists('office_institutional_profiles');

        $migration = require database_path(
            'migrations/2026_07_16_900100_create_office_institutional_profiles_table.php'
        );
        $migration->up();

        $profile = DB::table('office_institutional_profiles')
            ->where('office_id', $office->id)
            ->first();
        $this->assertNotNull($profile);
        $this->assertSame('11222333000181', $profile->cnpj);
        $this->assertSame('Escritorio Gamma LTDA', $profile->legal_name);
        $this->assertNull($profile->institutional_email);
        $this->assertNull($profile->institutional_phone);

        $fallback = DB::table('office_institutional_profiles')
            ->where('office_id', $officeNoIdentity->id)
            ->first();
        $this->assertNotNull($fallback);
        $this->assertNull($fallback->cnpj);
        $this->assertSame('Somente Nome', $fallback->legal_name);

        // Rollback seguro
        $migration->down();
        $this->assertFalse(Schema::hasTable('office_institutional_profiles'));
        $migration->up();
        $this->assertTrue(Schema::hasTable('office_institutional_profiles'));
    }

    public function test_backfill_onboarding_incomplete_por_office(): void
    {
        $office = Office::factory()->create();

        // Garante ausência e reexecuta backfill da migration 900104
        DB::table('office_serpro_onboarding_states')->where('office_id', $office->id)->delete();

        $migration = require database_path(
            'migrations/2026_07_16_900104_create_office_serpro_onboarding_states_table.php'
        );
        $migration->up();

        $row = DB::table('office_serpro_onboarding_states')
            ->where('office_id', $office->id)
            ->where('environment', SerproEnvironment::Production->value)
            ->first();

        $this->assertNotNull($row);
        $this->assertSame(OfficeSerproOnboardingStatus::Incomplete->value, $row->status);
    }

    public function test_perfil_institucional_model_e_relacao_office(): void
    {
        $office = Office::factory()->create();
        $profile = OfficeInstitutionalProfile::factory()->forOffice($office)->create();

        $this->assertTrue($profile->isComplete());
        $this->assertSame($office->id, $profile->office->id);
        $this->assertSame($profile->id, $office->fresh()->institutionalProfile->id);

        $public = $profile->toPublicArray();
        $this->assertArrayHasKey('cnpj', $public);
        $this->assertArrayHasKey('institutional_email', $public);
        $this->assertTrue($public['is_complete']);
    }

    public function test_credencial_canonica_esconde_vault_e_aceita_identity_nula(): void
    {
        $office = Office::factory()->create();
        $credential = OfficeCredential::factory()
            ->forOffice($office)
            ->canonical()
            ->create();

        $this->assertTrue($credential->isCanonical());
        $this->assertSame(OfficeCredentialPurpose::CanonicalECnpjA1, $credential->purpose);
        $this->assertNull($credential->office_fiscal_identity_id);

        $array = $credential->toArray();
        $this->assertArrayNotHasKey('vault_object_id', $array);

        $public = $credential->toPublicArray();
        $this->assertArrayNotHasKey('vault_object_id', $public);
        $this->assertTrue($public['is_canonical']);
        $this->assertSame(OfficeCredentialPurpose::CanonicalECnpjA1->value, $public['purpose']);
        $this->assertSame(CredentialStatus::Active->value, $public['status']);
    }

    public function test_purpose_links_referenciam_canonica_sem_segredo(): void
    {
        $office = Office::factory()->create();
        $canonical = OfficeCredential::factory()->forOffice($office)->canonical()->create();

        $termo = OfficeCredentialPurposeLink::factory()
            ->forCredential($canonical)
            ->serproTermSigning()
            ->create();

        $autxml = OfficeCredentialPurposeLink::factory()
            ->forCredential($canonical)
            ->nfeAutXml()
            ->create();

        $this->assertSame(OfficeCredentialPurpose::SerproTermSigning, $termo->purpose);
        $this->assertSame(OfficeCredentialPurpose::NfeAutXmlDistDfe, $autxml->purpose);
        $this->assertTrue($termo->isActive());
        $this->assertSame($canonical->id, $termo->credential->id);
        $this->assertSame($canonical->id, $autxml->credential->id);

        // Mesma credencial física — sem segunda cópia de vault
        $this->assertSame($canonical->vault_object_id, $termo->credential->vault_object_id);
        $this->assertSame(1, OfficeCredential::query()
            ->where('office_id', $office->id)
            ->where('purpose', OfficeCredentialPurpose::CanonicalECnpjA1)
            ->count());

        $public = $termo->toPublicArray();
        $this->assertArrayNotHasKey('vault_object_id', $public);
        $json = json_encode($public);
        $this->assertIsString($json);
        $this->assertStringNotContainsString((string) $canonical->vault_object_id, $json);

        $this->assertCount(2, $canonical->fresh()->purposeLinks);
        $this->assertCount(2, $office->fresh()->credentialPurposeLinks);
    }

    public function test_consentimento_tecnico_versionado(): void
    {
        $office = Office::factory()->create();
        $actor = User::factory()->create();

        $consent = OfficeTechnicalConsent::factory()
            ->forOffice($office)
            ->byUser($actor)
            ->create();

        $this->assertTrue($consent->isActive());
        $this->assertSame(OfficeTechnicalConsent::VERSION_UNIFIED_A1_V1, $consent->version_code);
        $this->assertContains(
            OfficeCredentialPurpose::SerproTermSigning->value,
            $consent->purposes_presented,
        );

        $public = $consent->toPublicArray();
        $this->assertTrue($public['active']);
        $this->assertArrayNotHasKey('actor_user_id', $public);

        $revoked = OfficeTechnicalConsent::factory()
            ->forOffice($office)
            ->byUser($actor)
            ->revoked()
            ->create([
                'version_code' => 'unified-a1.v0',
            ]);
        $this->assertFalse($revoked->isActive());
        $this->assertCount(2, $office->fresh()->technicalConsents);
    }

    public function test_onboarding_state_enum_e_projecoes_seguras(): void
    {
        $office = Office::factory()->create();
        $state = OfficeSerproOnboardingState::factory()
            ->forOffice($office)
            ->actionRequired('CONSENT_REQUIRED')
            ->create([
                'metadata' => [
                    'note' => 'safe',
                    'password' => 'should-not-leak',
                    'token' => 'tok',
                ],
            ]);

        $this->assertSame(OfficeSerproOnboardingStatus::ActionRequired, $state->status);
        $this->assertSame(SerproEnvironment::Production, $state->environment);

        $tenant = $state->toTenantArray();
        $this->assertSame('action_required', $tenant['status']);
        $this->assertSame('CONSENT_REQUIRED', $tenant['actionable']['code']);
        $this->assertArrayNotHasKey('technical', $tenant);
        $this->assertArrayNotHasKey('metadata', $tenant);

        $platform = $state->toPlatformArray();
        $this->assertArrayHasKey('technical', $platform);
        $this->assertSame('safe', $platform['metadata']['note'] ?? null);
        $this->assertArrayNotHasKey('password', $platform['metadata'] ?? []);
        $this->assertArrayNotHasKey('token', $platform['metadata'] ?? []);

        $this->assertTrue($office->fresh()->serproOnboardingStates->contains('id', $state->id));
    }

    public function test_migration_purpose_links_e_consent_rollback(): void
    {
        $this->assertTrue(Schema::hasTable('office_credential_purpose_links'));
        $this->assertTrue(Schema::hasTable('office_technical_consents'));

        $linksMigration = require database_path(
            'migrations/2026_07_16_900102_create_office_credential_purpose_links_table.php'
        );
        $consentMigration = require database_path(
            'migrations/2026_07_16_900103_create_office_technical_consents_table.php'
        );

        $linksMigration->down();
        $consentMigration->down();
        $this->assertFalse(Schema::hasTable('office_credential_purpose_links'));
        $this->assertFalse(Schema::hasTable('office_technical_consents'));

        $consentMigration->up();
        $linksMigration->up();
        $this->assertTrue(Schema::hasTable('office_credential_purpose_links'));
        $this->assertTrue(Schema::hasTable('office_technical_consents'));
    }
}

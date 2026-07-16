<?php

namespace Tests\Feature\OfficeConfig;

use App\Enums\CredentialStatus;
use App\Enums\OfficeCredentialPurpose;
use App\Enums\OfficeRole;
use App\Enums\OfficeSerproOnboardingStatus;
use App\Enums\SerproEnvironment;
use App\Models\AuditLog;
use App\Models\Office;
use App\Models\OfficeCredential;
use App\Models\OfficeCredentialPurposeLink;
use App\Models\OfficeInstitutionalProfile;
use App\Models\OfficeSerproOnboardingState;
use App\Models\User;
use App\Support\CurrentOffice;
use Database\Factories\EstablishmentFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Task 2.1 — perfil institucional (4 campos), policy ADMIN, strip office_id, CNPJ confirmado.
 */
class OfficeSettingsProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_atualiza_perfil_com_quatro_campos(): void
    {
        [$office, $admin] = $this->actingAsOfficeAdmin();

        $response = $this->patchJson('/api/v1/office/settings/profile', [
            'cnpj' => '11.222.333/0001-81',
            'legal_name' => 'Escritório Alpha LTDA',
            'institutional_email' => 'contato@alpha.test',
            'institutional_phone' => '+55 11 3000-1111',
            'office_id' => 99999, // deve ser descartado / proibido
        ]);

        // office_id prohibited → 422 se ainda presente após prepareForValidation
        // prepareForValidation remove; se prohibited ainda falha quando presente no JSON remanescente
        // Com remove no prepareForValidation, office_id some antes das rules.
        $response->assertOk()
            ->assertJsonPath('data.profile.cnpj', '11222333000181')
            ->assertJsonPath('data.profile.legal_name', 'Escritório Alpha LTDA')
            ->assertJsonPath('data.profile.institutional_email', 'contato@alpha.test')
            ->assertJsonPath('data.profile.institutional_phone', '+55 11 3000-1111')
            ->assertJsonPath('data.cnpj_changed', false);

        $body = $response->getContent() ?: '';
        $this->assertStringNotContainsString('99999', $body);

        $profile = OfficeInstitutionalProfile::query()->where('office_id', $office->id)->first();
        $this->assertNotNull($profile);
        $this->assertSame('11222333000181', $profile->cnpj);
        $this->assertSame($office->id, $profile->office_id);

        $audit = AuditLog::query()->where('action', 'office.institutional_profile.update')->latest('id')->first();
        $this->assertNotNull($audit);
        $this->assertSame('SUCCESS', $audit->result);
        $this->assertSame($admin->id, $audit->user_id);
    }

    public function test_operator_e_viewer_nao_mutam_perfil(): void
    {
        $office = Office::factory()->create();
        OfficeInstitutionalProfile::factory()->forOffice($office)->create([
            'legal_name' => 'Original',
        ]);

        $operator = User::factory()->forOffice($office, OfficeRole::Operator)->withTwoFactorConfirmed()->create();
        $this->actingAs($operator);
        app(CurrentOffice::class)->resolve($operator);

        $this->patchJson('/api/v1/office/settings/profile', [
            'legal_name' => 'Hack',
        ])->assertForbidden();

        $viewer = User::factory()->forOffice($office, OfficeRole::Viewer)->withTwoFactorConfirmed()->create();
        $this->actingAs($viewer);
        app(CurrentOffice::class)->resolve($viewer);

        $this->patchJson('/api/v1/office/settings/profile', [
            'legal_name' => 'Hack',
        ])->assertForbidden();

        $this->assertSame(
            'Original',
            OfficeInstitutionalProfile::query()->where('office_id', $office->id)->value('legal_name'),
        );
    }

    public function test_viewer_pode_ler_settings(): void
    {
        $office = Office::factory()->create();
        OfficeInstitutionalProfile::factory()->forOffice($office)->create([
            'legal_name' => 'Leitura OK',
        ]);
        $viewer = User::factory()->forOffice($office, OfficeRole::Viewer)->withTwoFactorConfirmed()->create();
        $this->actingAs($viewer);
        app(CurrentOffice::class)->resolve($viewer);

        $this->getJson('/api/v1/office/settings')
            ->assertOk()
            ->assertJsonPath('data.profile.legal_name', 'Leitura OK')
            ->assertJsonStructure([
                'data' => [
                    'profile' => ['cnpj', 'legal_name', 'institutional_email', 'institutional_phone'],
                    'consent',
                    'credential',
                    'purpose_links',
                    'alerts',
                ],
            ]);
    }

    public function test_troca_cnpj_sem_confirmacao_rejeita(): void
    {
        [$office] = $this->actingAsOfficeAdmin();
        OfficeInstitutionalProfile::factory()->forOffice($office)->create([
            'cnpj' => '11222333000181',
        ]);

        $other = EstablishmentFactory::cnpjWithRoot('99888777');

        $this->patchJson('/api/v1/office/settings/profile', [
            'cnpj' => $other,
        ])->assertStatus(422)
            ->assertJsonFragment(['message' => 'A troca de CNPJ exige confirmação forte (confirm_cnpj_change=true) e invalida A1, Termo e tokens derivados.']);

        $this->assertSame(
            '11222333000181',
            OfficeInstitutionalProfile::query()->where('office_id', $office->id)->value('cnpj'),
        );
    }

    public function test_troca_cnpj_confirmada_invalida_a1_e_vinculos_atomicamente(): void
    {
        [$office, $admin] = $this->actingAsOfficeAdmin();
        OfficeInstitutionalProfile::factory()->forOffice($office)->create([
            'cnpj' => '11222333000181',
            'legal_name' => 'Antes',
        ]);

        $credential = OfficeCredential::factory()->forOffice($office)->canonical()->create([
            'holder_cnpj' => '11222333000181',
            'status' => CredentialStatus::Active,
            'purpose' => OfficeCredentialPurpose::CanonicalECnpjA1,
        ]);
        $linkTermo = OfficeCredentialPurposeLink::factory()
            ->forCredential($credential)
            ->serproTermSigning()
            ->create();
        $linkAut = OfficeCredentialPurposeLink::factory()
            ->forCredential($credential)
            ->nfeAutXml()
            ->create();

        $state = OfficeSerproOnboardingState::query()->create([
            'office_id' => $office->id,
            'environment' => SerproEnvironment::Production,
            'status' => OfficeSerproOnboardingStatus::Authorized,
            'last_transition_at' => now(),
            'authorized_at' => now(),
        ]);

        $other = EstablishmentFactory::cnpjWithRoot('99888777');

        $response = $this->patchJson('/api/v1/office/settings/profile', [
            'cnpj' => $other,
            'confirm_cnpj_change' => true,
            'legal_name' => 'Depois SA',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.profile.cnpj', $other)
            ->assertJsonPath('data.cnpj_changed', true)
            ->assertJsonPath('data.invalidated.credentials_revoked', 1)
            ->assertJsonPath('data.invalidated.purpose_links_revoked', 2)
            ->assertJsonPath('data.invalidated.reonboarding_triggered', true);

        $this->assertSame(CredentialStatus::Revoked, $credential->fresh()->status);
        $this->assertSame(CredentialStatus::Revoked, $linkTermo->fresh()->status);
        $this->assertSame(CredentialStatus::Revoked, $linkAut->fresh()->status);
        $this->assertNotNull($linkTermo->fresh()->revoked_at);

        $state->refresh();
        // reactTo* marca reonboarding e evaluate pode ir a Incomplete sem A1/prereqs.
        $this->assertNotSame(OfficeSerproOnboardingStatus::Authorized, $state->status);
        $this->assertTrue(
            $state->status === OfficeSerproOnboardingStatus::ActionRequired
            || $state->status === OfficeSerproOnboardingStatus::Incomplete
        );
        $this->assertNotNull(
            AuditLog::query()->where('action', 'serpro.authorization.invalidate_derived')->first()
        );

        // office_id do body nunca redefine escopo — perfil permanece no CurrentOffice
        $this->assertSame(
            $office->id,
            OfficeInstitutionalProfile::query()->where('cnpj', $other)->value('office_id'),
        );
        $this->assertSame($admin->id, AuditLog::query()
            ->where('action', 'office.institutional_profile.update')
            ->latest('id')
            ->value('user_id'));
    }

    public function test_office_id_no_body_nao_muda_escopo_para_outro_tenant(): void
    {
        $officeA = Office::factory()->create();
        $officeB = Office::factory()->create();
        OfficeInstitutionalProfile::factory()->forOffice($officeA)->create([
            'legal_name' => 'A Original',
        ]);
        OfficeInstitutionalProfile::factory()->forOffice($officeB)->create([
            'legal_name' => 'B Original',
        ]);

        $adminA = User::factory()->forOffice($officeA, OfficeRole::Admin)->withTwoFactorConfirmed()->create();
        $this->actingAs($adminA);
        app(CurrentOffice::class)->resolve($adminA);

        $this->patchJson('/api/v1/office/settings/profile', [
            'legal_name' => 'A Atualizado',
            'office_id' => $officeB->id,
        ])->assertOk()
            ->assertJsonPath('data.profile.legal_name', 'A Atualizado');

        $this->assertSame(
            'A Atualizado',
            OfficeInstitutionalProfile::query()->where('office_id', $officeA->id)->value('legal_name'),
        );
        // Global scope de tenant: consulta B sem CurrentOffice daquele office.
        $this->assertSame(
            'B Original',
            OfficeInstitutionalProfile::withoutGlobalScopes()
                ->where('office_id', $officeB->id)
                ->value('legal_name'),
        );
    }

    /**
     * @return array{0: Office, 1: User}
     */
    private function actingAsOfficeAdmin(): array
    {
        $office = Office::factory()->create();
        $admin = User::factory()->forOffice($office, OfficeRole::Admin)->withTwoFactorConfirmed()->create();
        $this->actingAs($admin);
        app(CurrentOffice::class)->resolve($admin);

        return [$office, $admin];
    }
}

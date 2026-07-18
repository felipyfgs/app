<?php

namespace Tests\Unit\Services;

use App\Enums\OfficeRole;
use App\Enums\TenantPermission;
use App\Models\Client;
use App\Models\Office;
use App\Models\OfficeMembership;
use App\Models\PlatformMembership;
use App\Models\TenantPermissionProfile;
use App\Models\User;
use App\Services\Authorization\TenantAuthorization;
use App\Support\CurrentOffice;
use App\Support\FeatureFlags;
use App\Support\FiscalDataModel\PrivilegedOfficeContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantAuthorizationShadowTest extends TestCase
{
    use RefreshDatabase;

    private TenantAuthorization $auth;

    private CurrentOffice $currentOffice;

    protected function setUp(): void
    {
        parent::setUp();
        $this->auth = app(TenantAuthorization::class);
        $this->currentOffice = app(CurrentOffice::class);
        config([
            'features.canonical_multitenant_rbac.enabled' => false,
            'features.kill_switch' => false,
            'features.platform_privileged_context.enabled' => false,
        ]);
    }

    public function test_flag_off_obedece_legado_e_paridade_operator(): void
    {
        $office = Office::factory()->create();
        $user = User::factory()->create();
        $membership = OfficeMembership::factory()->legacyOnly(OfficeRole::Operator)->create([
            'office_id' => $office->id,
            'user_id' => $user->id,
        ]);
        $this->currentOffice->bind($user, $membership);

        $this->assertFalse(FeatureFlags::isCanonicalMultitenantRbacEnabled());
        $this->assertTrue($this->auth->allows($user, TenantPermission::ClientsManage));
        $this->assertFalse($this->auth->allows($user, TenantPermission::CredentialsManage));
        $this->assertTrue($this->auth->legacyAllows($user, TenantPermission::ClientsManage));
        $this->assertTrue($this->auth->canonicalAllows($user, TenantPermission::ClientsManage));
    }

    public function test_viewer_somente_leitura(): void
    {
        $office = Office::factory()->create();
        $user = User::factory()->create();
        $membership = OfficeMembership::factory()->legacyOnly(OfficeRole::Viewer)->create([
            'office_id' => $office->id,
            'user_id' => $user->id,
        ]);
        $this->currentOffice->bind($user, $membership);

        $this->assertTrue($this->auth->allows($user, TenantPermission::ClientsView));
        $this->assertFalse($this->auth->allows($user, TenantPermission::ClientsManage));
        $this->assertFalse($this->auth->allows($user, TenantPermission::FiscalSyncTrigger));
    }

    public function test_tenant_admin_canonic_full_baseline(): void
    {
        $office = Office::factory()->create();
        $user = User::factory()->create();
        $membership = OfficeMembership::factory()->tenantAdmin()->create([
            'office_id' => $office->id,
            'user_id' => $user->id,
        ]);
        $this->currentOffice->bind($user, $membership);

        $this->assertTrue($this->auth->allows($user, TenantPermission::CredentialsManage));
        $this->assertTrue($this->auth->allows($user, TenantPermission::TenantPermissionProfilesManage));
    }

    public function test_sem_contexto_nega(): void
    {
        $user = User::factory()->create();
        PlatformMembership::factory()->create(['user_id' => $user->id]);
        $this->currentOffice->clear();

        $this->assertFalse($this->auth->allows($user, TenantPermission::ClientsView));
        $this->assertFalse($this->auth->canonicalAllows($user, TenantPermission::ClientsView));
    }

    public function test_target_outro_office_nega(): void
    {
        $officeA = Office::factory()->create();
        $officeB = Office::factory()->create();
        $user = User::factory()->create();
        $membership = OfficeMembership::factory()->tenantAdmin()->create([
            'office_id' => $officeA->id,
            'user_id' => $user->id,
        ]);
        $this->currentOffice->bind($user, $membership);

        $foreign = new Client(['office_id' => $officeB->id]);

        $this->assertFalse($this->auth->allows($user, TenantPermission::ClientsManage, $foreign));
    }

    public function test_tenant_user_perfil_custom_canonico(): void
    {
        $office = Office::factory()->create();
        $user = User::factory()->create();
        $profile = PrivilegedOfficeContext::run('test', fn () => TenantPermissionProfile::factory()
            ->forOffice($office)
            ->create());
        PrivilegedOfficeContext::run('test', function () use ($profile): void {
            $profile->syncPermissionKeys([
                TenantPermission::ClientsView,
                TenantPermission::ExportsCreate,
            ]);
        });

        $membership = OfficeMembership::factory()->tenantUser($profile)->create([
            'user_id' => $user->id,
        ]);
        $this->currentOffice->bind($user, $membership);

        $this->assertTrue($this->auth->canonicalAllows($user, TenantPermission::ExportsCreate));
        $this->assertFalse($this->auth->canonicalAllows($user, TenantPermission::ClientsManage));

        // Shadow: legado usa sombra VIEWER → clients.manage false; exports false no viewer.
        // Flag OFF → obedece legado.
        $this->assertFalse($this->auth->allows($user, TenantPermission::ExportsCreate));
    }

    public function test_cutover_flag_on_obedece_canonico(): void
    {
        config(['features.canonical_multitenant_rbac.enabled' => true]);

        $office = Office::factory()->create();
        $user = User::factory()->create();
        $profile = PrivilegedOfficeContext::run('test', fn () => TenantPermissionProfile::factory()
            ->forOffice($office)
            ->create());
        PrivilegedOfficeContext::run('test', function () use ($profile): void {
            $profile->syncPermissionKeys([TenantPermission::ExportsCreate]);
        });

        $membership = OfficeMembership::factory()->tenantUser($profile)->create([
            'user_id' => $user->id,
        ]);
        $this->currentOffice->bind($user, $membership);

        $this->assertTrue($this->auth->allows($user, TenantPermission::ExportsCreate));
        $this->assertFalse($this->auth->allows($user, TenantPermission::ClientsView));
    }

    public function test_usuario_inativo_nega(): void
    {
        $office = Office::factory()->create();
        $user = User::factory()->create(['is_active' => false]);
        $membership = OfficeMembership::factory()->tenantAdmin()->create([
            'office_id' => $office->id,
            'user_id' => $user->id,
        ]);
        $this->currentOffice->bind($user, $membership);

        $this->assertFalse($this->auth->allows($user, TenantPermission::ClientsView));
    }
}

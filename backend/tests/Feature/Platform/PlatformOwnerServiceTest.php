<?php

namespace Tests\Feature\Platform;

use App\Enums\OfficeRole;
use App\Enums\PlatformRole;
use App\Models\Office;
use App\Models\OfficeMembership;
use App\Models\PlatformMembership;
use App\Models\User;
use App\Services\Platform\PlatformOwnerException;
use App\Services\Platform\PlatformOwnerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PlatformOwnerServiceTest extends TestCase
{
    use RefreshDatabase;

    private PlatformOwnerService $owners;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owners = app(PlatformOwnerService::class);
    }

    public function test_create_owner_unico_e_colisao(): void
    {
        $user = User::factory()->create();
        $pm = $this->owners->createOwner($user, isActive: true);

        $this->assertSame(PlatformRole::PlatformAdmin, $pm->role);
        $this->assertSame(1, $this->owners->count());

        $other = User::factory()->create();
        try {
            $this->owners->createOwner($other);
            $this->fail('Esperava platform_owner_already_exists');
        } catch (PlatformOwnerException $e) {
            $this->assertSame('platform_owner_already_exists', $e->errorCode);
        }

        $this->assertSame(1, $this->owners->count());
        $this->assertSame(0, PlatformMembership::query()->where('user_id', $other->id)->count());
    }

    public function test_bloqueia_exclusao_do_proprietario_e_preserva_office_membership(): void
    {
        $office = Office::factory()->create();
        $owner = User::factory()->asPlatformAdmin($office->id)->forOffice($office, OfficeRole::Admin)->create();

        $this->assertTrue($owner->isPlatformAdmin());
        $this->assertSame(1, OfficeMembership::query()->where('user_id', $owner->id)->count());

        try {
            $owner->delete();
            $this->fail('Exclusão do proprietário deveria falhar');
        } catch (PlatformOwnerException $e) {
            $this->assertSame('platform_owner_cannot_remove', $e->errorCode);
        }

        $this->assertDatabaseHas('users', ['id' => $owner->id]);
        $this->assertSame(1, PlatformMembership::query()->where('user_id', $owner->id)->count());
        $this->assertSame(1, OfficeMembership::query()->where('user_id', $owner->id)->count());

        $pm = PlatformMembership::query()->where('user_id', $owner->id)->firstOrFail();
        try {
            $pm->delete();
            $this->fail('Exclusão do vínculo global deveria falhar');
        } catch (PlatformOwnerException $e) {
            $this->assertSame('platform_owner_cannot_remove', $e->errorCode);
        }
    }

    public function test_update_owner_nao_cria_segundo_vinculo(): void
    {
        $office = Office::factory()->create();
        $owner = User::factory()->asPlatformAdmin($office->id)->create([
            'name' => 'Antes',
            'email' => 'antes@example.com',
        ]);

        $result = $this->owners->updateOwner([
            'name' => 'Depois',
            'email' => 'depois@example.com',
            'default_office_id' => $office->id,
        ], $owner);

        $this->assertSame('Depois', $result['user']->name);
        $this->assertSame('depois@example.com', $result['user']->email);
        $this->assertSame(1, $this->owners->count());
        $this->assertSame($owner->id, $result['membership']->user_id);
    }

    public function test_recover_in_place_revoga_sessoes(): void
    {
        $owner = User::factory()->asPlatformAdmin()->create([
            'email' => 'owner@example.com',
            'password' => 'old-password-12',
        ]);

        $token = $owner->createToken('test')->plainTextToken;
        $this->assertNotEmpty($token);
        $this->assertSame(1, $owner->tokens()->count());

        DB::table('sessions')->insert([
            'id' => 'sess-owner-1',
            'user_id' => $owner->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'test',
            'payload' => 'x',
            'last_activity' => time(),
        ]);

        $pm = $this->owners->recoverInPlace('Novo Nome', 'novo@example.com', 'nova-senha-segura-12');

        $this->assertSame('Novo Nome', $pm->user->name);
        $this->assertSame('novo@example.com', $pm->user->email);
        $this->assertTrue(Hash::check('nova-senha-segura-12', $pm->user->password));
        $this->assertSame(0, $owner->fresh()->tokens()->count());
        $this->assertSame(0, (int) DB::table('sessions')->where('user_id', $owner->id)->count());
        $this->assertSame(1, $this->owners->count());
    }

    public function test_transfer_atomico_revoga_ambos_e_mantem_unicidade(): void
    {
        $office = Office::factory()->create();
        $previous = User::factory()->asPlatformAdmin($office->id)->create(['email' => 'prev@example.com']);
        $target = User::factory()->create(['email' => 'alvo@example.com']);

        $previous->createToken('a');
        $target->createToken('b');

        $pm = $this->owners->transferTo($target, 'senha-do-alvo-12');

        $this->assertSame($target->id, $pm->user_id);
        $this->assertSame(1, $this->owners->count());
        $this->assertFalse($previous->fresh()->isPlatformAdmin());
        $this->assertTrue($target->fresh()->isPlatformAdmin());
        $this->assertSame(0, $previous->fresh()->tokens()->count());
        $this->assertSame(0, $target->fresh()->tokens()->count());
        $this->assertTrue(Hash::check('senha-do-alvo-12', $target->fresh()->password));
    }

    public function test_consolidate_remove_excedentes_preserva_office_membership(): void
    {
        $this->dropOwnerIndex();

        $office = Office::factory()->create();
        $keep = User::factory()->forOffice($office, OfficeRole::Admin)->create();
        $drop = User::factory()->forOffice($office, OfficeRole::Operator)->create();

        DB::table('platform_memberships')->insert([
            [
                'user_id' => $keep->id,
                'role' => PlatformRole::PlatformAdmin->value,
                'is_active' => true,
                'default_office_id' => $office->id,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'user_id' => $drop->id,
                'role' => PlatformRole::PlatformAdmin->value,
                'is_active' => true,
                'default_office_id' => $office->id,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $result = $this->owners->consolidate($keep->id);

        $this->assertSame($keep->id, $result['kept_user_id']);
        $this->assertCount(1, $result['removed_membership_ids']);
        $this->assertSame(1, $this->owners->count());
        $this->assertTrue($keep->fresh()->isPlatformAdmin());
        $this->assertFalse($drop->fresh()->isPlatformAdmin());
        $this->assertSame(1, OfficeMembership::query()->where('user_id', $drop->id)->count());
        $this->assertDatabaseHas('users', ['id' => $drop->id]);

        $this->recreateOwnerIndex();
    }

    public function test_colisao_concorrente_via_indice_vira_already_exists(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();

        $this->owners->createOwner($a);

        // Força caminho QueryException → already_exists (segunda create direta no banco).
        try {
            DB::table('platform_memberships')->insert([
                'user_id' => $b->id,
                'role' => PlatformRole::PlatformAdmin->value,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->fail('Índice deveria rejeitar o segundo insert');
        } catch (\Throwable) {
            // ok
        }

        try {
            $this->owners->createOwner($b);
            $this->fail('Esperava already_exists');
        } catch (PlatformOwnerException $e) {
            $this->assertSame('platform_owner_already_exists', $e->errorCode);
        }

        $this->assertSame(1, $this->owners->count());
    }

    private function dropOwnerIndex(): void
    {
        DB::statement('DROP INDEX IF EXISTS platform_memberships_one_platform_admin');
    }

    private function recreateOwnerIndex(): void
    {
        DB::statement(
            "CREATE UNIQUE INDEX platform_memberships_one_platform_admin
             ON platform_memberships (role)
             WHERE role = 'PLATFORM_ADMIN'",
        );
    }
}

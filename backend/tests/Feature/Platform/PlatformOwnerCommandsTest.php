<?php

namespace Tests\Feature\Platform;

use App\Enums\OfficeRole;
use App\Enums\PlatformRole;
use App\Models\Office;
use App\Models\OfficeMembership;
use App\Models\PlatformMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PlatformOwnerCommandsTest extends TestCase
{
    use RefreshDatabase;

    public function test_consolidate_base_valida_noop(): void
    {
        $owner = User::factory()->asPlatformAdmin()->create();

        $this->artisan('app:platform-owner:consolidate', ['--keep' => (string) $owner->id])
            ->assertSuccessful();

        $this->assertSame(1, PlatformMembership::query()->where('role', PlatformRole::PlatformAdmin)->count());
    }

    public function test_consolidate_duplicada_e_cancelamento(): void
    {
        $this->dropOwnerIndex();

        $office = Office::factory()->create();
        $keep = User::factory()->forOffice($office, OfficeRole::Admin)->create();
        $drop = User::factory()->forOffice($office, OfficeRole::Viewer)->create();

        DB::table('platform_memberships')->insert([
            [
                'user_id' => $keep->id,
                'role' => PlatformRole::PlatformAdmin->value,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'user_id' => $drop->id,
                'role' => PlatformRole::PlatformAdmin->value,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $this->artisan('app:platform-owner:consolidate', ['--keep' => (string) $keep->id])
            ->expectsQuestion(
                'Confirme digitando CONSOLIDAR para remover 1 vínculo(s) excedente(s) e manter user_id='
                .$keep->id.' ('.$keep->email.')',
                'nao',
            )
            ->assertFailed();

        $this->assertSame(2, PlatformMembership::query()->where('role', PlatformRole::PlatformAdmin)->count());

        $this->artisan('app:platform-owner:consolidate', ['--keep' => (string) $keep->id])
            ->expectsQuestion(
                'Confirme digitando CONSOLIDAR para remover 1 vínculo(s) excedente(s) e manter user_id='
                .$keep->id.' ('.$keep->email.')',
                'CONSOLIDAR',
            )
            ->assertSuccessful();

        $this->assertSame(1, PlatformMembership::query()->where('role', PlatformRole::PlatformAdmin)->count());
        $this->assertSame(1, OfficeMembership::query()->where('user_id', $drop->id)->count());

        $this->recreateOwnerIndex();
    }

    public function test_recover_in_place(): void
    {
        $owner = User::factory()->asPlatformAdmin()->create([
            'name' => 'Velho',
            'email' => 'velho@example.com',
            'password' => 'senha-antiga-12',
        ]);
        $owner->createToken('t');

        $this->artisan('app:platform-owner:recover')
            ->expectsQuestion('Nome', 'Recuperado')
            ->expectsQuestion('E-mail', 'recuperado@example.com')
            ->expectsQuestion('Nova senha (mín. 12 caracteres)', 'senha-nova-segura')
            ->expectsQuestion('Confirme a nova senha', 'senha-nova-segura')
            ->expectsQuestion('Confirme digitando RECUPERAR para atualizar o titular atual', 'RECUPERAR')
            ->assertSuccessful();

        $owner->refresh();
        $this->assertSame('Recuperado', $owner->name);
        $this->assertSame('recuperado@example.com', $owner->email);
        $this->assertTrue(Hash::check('senha-nova-segura', $owner->password));
        $this->assertSame(0, $owner->tokens()->count());
        $this->assertSame(1, PlatformMembership::query()->count());
    }

    public function test_recover_transferencia(): void
    {
        $previous = User::factory()->asPlatformAdmin()->create(['email' => 'a@example.com']);
        $target = User::factory()->create(['email' => 'b@example.com']);

        $this->artisan('app:platform-owner:recover', ['--transfer-to' => (string) $target->id])
            ->expectsConfirmation('Definir nova senha para o alvo?', 'yes')
            ->expectsQuestion('Nova senha do alvo (mín. 12 caracteres)', 'senha-transfer-12')
            ->expectsQuestion('Confirme a nova senha', 'senha-transfer-12')
            ->expectsQuestion(
                'Confirme digitando TRANSFERIR para mover o vínculo global para user_id='.$target->id,
                'TRANSFERIR',
            )
            ->assertSuccessful();

        $this->assertFalse($previous->fresh()->isPlatformAdmin());
        $this->assertTrue($target->fresh()->isPlatformAdmin());
        $this->assertSame(1, PlatformMembership::query()->count());
        $this->assertTrue(Hash::check('senha-transfer-12', $target->fresh()->password));
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

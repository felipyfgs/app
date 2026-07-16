<?php

namespace Database\Seeders;

use App\Enums\PlatformRole;
use App\Models\AccountActivation;
use App\Models\Office;
use App\Models\OfficeMembership;
use App\Models\PlatformMembership;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use LogicException;
use RuntimeException;

/**
 * Fixture local/testing de PLATFORM_ADMIN exclusivamente global.
 * Não cria OfficeMembership, AccountActivation nem consome seats do plano.
 */
class PlatformAdminDemoSeeder extends Seeder
{
    public const NAME = 'Admin Plataforma Demo';

    public const EMAIL = 'plataforma@example.com';

    public const PASSWORD = 'password';

    public const OFFICE_SLUG = 'plataforma';

    public const OFFICE_NAME = 'Plataforma';

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new LogicException(
                'PlatformAdminDemoSeeder só pode ser executado em local/testing.',
            );
        }

        $office = Office::query()
            ->where('slug', self::OFFICE_SLUG)
            ->where('is_active', true)
            ->first();

        if ($office === null) {
            throw new RuntimeException(
                'Office da Plataforma ativo (slug "'.self::OFFICE_SLUG.'") é obrigatório. Execute o DatabaseSeeder completo antes do PlatformAdminDemoSeeder.',
            );
        }

        DB::transaction(function () use ($office): void {
            $user = User::query()->where('email', self::EMAIL)->first();

            if ($user === null) {
                $this->createFixture($office);

                return;
            }

            $this->assertCompatibleGlobalFixture($user);
            $this->ensurePlatformMembership($user, $office);
        });
    }

    private function createFixture(Office $office): void
    {
        $user = new User;
        $user->forceFill([
            'name' => self::NAME,
            'email' => self::EMAIL,
            'password' => self::PASSWORD,
            'email_verified_at' => now(),
            'is_active' => true,
            'password_change_required' => false,
            'selected_office_id' => null,
        ]);
        $user->save();

        PlatformMembership::query()->create([
            'user_id' => $user->id,
            'role' => PlatformRole::PlatformAdmin,
            'is_active' => true,
            'default_office_id' => $office->id,
        ]);
    }

    private function assertCompatibleGlobalFixture(User $user): void
    {
        if (OfficeMembership::query()->where('user_id', $user->id)->exists()) {
            throw new RuntimeException(
                'E-mail reservado '.self::EMAIL.' já possui OfficeMembership; o seed não promove conta de Office a PLATFORM_ADMIN.',
            );
        }

        if (AccountActivation::query()->where('user_id', $user->id)->exists()) {
            throw new RuntimeException(
                'E-mail reservado '.self::EMAIL.' possui AccountActivation; fixture demo exige conta global pronta sem onboarding pendente.',
            );
        }

        $membership = PlatformMembership::query()
            ->where('user_id', $user->id)
            ->where('role', PlatformRole::PlatformAdmin->value)
            ->first();

        if ($membership === null) {
            throw new RuntimeException(
                'E-mail reservado '.self::EMAIL.' existe sem PlatformMembership PLATFORM_ADMIN compatível; o seed não concede privilégio automaticamente.',
            );
        }

        $otherGrants = PlatformMembership::query()
            ->where('user_id', $user->id)
            ->where('role', '!=', PlatformRole::PlatformAdmin->value)
            ->exists();

        if ($otherGrants) {
            throw new RuntimeException(
                'E-mail reservado '.self::EMAIL.' possui grant de plataforma incompatível com a fixture exclusivamente global.',
            );
        }
    }

    private function ensurePlatformMembership(User $user, Office $office): void
    {
        if ($user->selected_office_id !== null) {
            $user->forceFill(['selected_office_id' => null])->save();
        }

        PlatformMembership::query()->updateOrCreate(
            [
                'user_id' => $user->id,
                'role' => PlatformRole::PlatformAdmin->value,
            ],
            [
                'is_active' => true,
                'default_office_id' => $office->id,
            ],
        );
    }
}

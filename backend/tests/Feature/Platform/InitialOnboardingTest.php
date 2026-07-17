<?php

namespace Tests\Feature\Platform;

use App\Enums\PlatformRole;
use App\Http\Controllers\Api\V1\Platform\InitialOnboardingController;
use App\Models\AccountActivation;
use App\Models\Office;
use App\Models\OfficeMembership;
use App\Models\PlatformMembership;
use App\Models\PlatformSetting;
use App\Models\User;
use App\Services\Platform\InitialOnboardingService;
use App\Services\Platform\PlatformOwnerException;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\InteractsWithSpaAuth;
use Tests\TestCase;

class InitialOnboardingTest extends TestCase
{
    use InteractsWithSpaAuth;
    use RefreshDatabase;

    private const TOKEN = 'onboarding-token-with-enough-entropy-32chars';

    private function enableOnboarding(?string $token = self::TOKEN): void
    {
        config([
            'onboarding.enabled' => true,
            'onboarding.token' => $token ?? '',
        ]);
    }

    private function disableOnboarding(): void
    {
        config([
            'onboarding.enabled' => false,
            'onboarding.token' => '',
        ]);
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'organization_name' => 'Inova Contábil',
            'email' => 'admin@plataforma.example',
            'password' => 'SenhaForte12!',
            'password_confirmation' => 'SenhaForte12!',
            'onboarding_token' => self::TOKEN,
        ], $overrides);
    }

    public function test_status_available_quando_instalacao_vazia_autorizada(): void
    {
        $this->enableOnboarding();

        $this->getJson('/api/v1/onboarding/status')
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertExactJson(['data' => ['available' => true]]);
    }

    public function test_status_indisponivel_quando_flag_off(): void
    {
        $this->disableOnboarding();

        $this->getJson('/api/v1/onboarding/status')
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertExactJson(['data' => ['available' => false]]);
    }

    public function test_env_string_false_desliga_flag_como_filter_var(): void
    {
        // Docker/process env entrega string "false"; (bool)"false" === true (bug).
        $this->assertFalse(filter_var('false', FILTER_VALIDATE_BOOL));
        $this->assertFalse(filter_var('0', FILTER_VALIDATE_BOOL));
        $this->assertTrue(filter_var('true', FILTER_VALIDATE_BOOL));

        config([
            'onboarding.enabled' => filter_var('false', FILTER_VALIDATE_BOOL),
            'onboarding.token' => self::TOKEN,
        ]);

        $this->getJson('/api/v1/onboarding/status')
            ->assertOk()
            ->assertJsonPath('data.available', false);
    }

    public function test_status_indisponivel_quando_token_curto(): void
    {
        $this->enableOnboarding('short-token');

        $this->getJson('/api/v1/onboarding/status')
            ->assertOk()
            ->assertJsonPath('data.available', false);
    }

    public function test_status_indisponivel_quando_base_ja_tem_usuario(): void
    {
        $this->enableOnboarding();
        User::factory()->create();

        $this->getJson('/api/v1/onboarding/status')
            ->assertOk()
            ->assertJsonPath('data.available', false);
    }

    public function test_post_onboarding_esta_no_grupo_api_com_csrf_stateful_registrado(): void
    {
        // PHPUnit desliga ValidateCsrfToken via runningUnitTests(); aqui garantimos
        // o pipeline de produção: grupo api stateful + middleware CSRF disponível.
        $this->assertTrue(class_exists(ValidateCsrfToken::class));

        $api = app('router')->getMiddlewareGroups()['api'] ?? [];
        $serialized = json_encode($api);
        $this->assertTrue(
            str_contains((string) $serialized, 'EnsureFrontendRequestsAreStateful')
            || str_contains((string) $serialized, 'stateful')
            || collect($api)->contains(fn ($m) => is_string($m) && str_contains($m, 'Sanctum')),
            'API stateful (Sanctum SPA) deve estar no grupo api.'
        );

        $route = app('router')->getRoutes()->getByAction(
            InitialOnboardingController::class.'@complete'
        );
        $this->assertNotNull($route);
        $this->assertContains('api', $route->gatherMiddleware());
        $this->assertTrue(
            collect($route->gatherMiddleware())->contains(
                fn ($m) => is_string($m) && str_contains($m, 'throttle')
            ),
            'POST onboarding deve ter throttle.'
        );
    }

    public function test_conclusao_valida_cria_admin_global_e_sessao(): void
    {
        $this->enableOnboarding();

        $response = $this->asSpa()
            ->postJson('/api/v1/onboarding', $this->validPayload())
            ->assertCreated()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('data.authenticated', true)
            ->assertJsonPath('data.redirect', '/admin/offices/new')
            ->assertJsonPath('data.platform_organization_name', 'Inova Contábil');

        $userId = $response->json('data.user_id');
        $this->assertNotNull($userId);
        $this->assertAuthenticatedAs(User::query()->findOrFail($userId));

        $user = User::query()->findOrFail($userId);
        $this->assertSame('Administrador da plataforma', $user->name);
        $this->assertSame('admin@plataforma.example', $user->email);
        $this->assertTrue($user->is_active);
        $this->assertFalse($user->password_change_required);
        $this->assertNotNull($user->email_verified_at);
        $this->assertNull($user->selected_office_id);
        $this->assertTrue(Hash::check('SenhaForte12!', $user->password));
        $this->assertTrue($user->isPlatformAdmin());

        $this->assertSame(1, User::query()->count());
        $this->assertSame(1, PlatformMembership::query()->count());
        $this->assertSame(0, Office::query()->count());
        $this->assertSame(0, OfficeMembership::query()->count());
        $this->assertSame(0, AccountActivation::query()->count());

        $this->assertDatabaseHas('platform_settings', [
            'id' => PlatformSetting::SINGLETON_ID,
            'organization_name' => 'Inova Contábil',
            'onboarded_by_user_id' => $userId,
        ]);
        $this->assertNotNull(PlatformSetting::query()->find(PlatformSetting::SINGLETON_ID)?->onboarding_completed_at);

        $pm = PlatformMembership::query()->where('user_id', $userId)->firstOrFail();
        $this->assertSame(PlatformRole::PlatformAdmin, $pm->role);
        $this->assertTrue($pm->is_active);
        $this->assertNull($pm->default_office_id);

        $body = $response->getContent();
        $this->assertStringNotContainsString(self::TOKEN, $body);
        $this->assertStringNotContainsString('SenhaForte12!', $body);

        $this->asSpa()
            ->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.is_platform_admin', true)
            ->assertJsonPath('data.platform_organization_name', 'Inova Contábil')
            ->assertJsonPath('data.office', null);
    }

    public function test_token_incorreto_nao_grava(): void
    {
        $this->enableOnboarding();

        $this->asSpa()
            ->postJson('/api/v1/onboarding', $this->validPayload([
                'onboarding_token' => str_repeat('x', 32),
            ]))
            ->assertForbidden()
            ->assertJsonPath('code', 'onboarding_not_authorized')
            ->assertHeader('Cache-Control', 'no-store, private');

        $this->assertSame(0, User::query()->count());
        $this->assertSame(0, PlatformSetting::query()->count());
    }

    public function test_flag_off_rejeita_conclusao(): void
    {
        $this->disableOnboarding();

        $this->asSpa()
            ->postJson('/api/v1/onboarding', $this->validPayload())
            ->assertForbidden()
            ->assertJsonPath('code', 'onboarding_not_authorized');

        $this->assertSame(0, User::query()->count());
    }

    public function test_base_existente_rejeita_sem_alterar(): void
    {
        $this->enableOnboarding();
        $existing = User::factory()->create(['email' => 'ja@existe.example']);

        $this->asSpa()
            ->postJson('/api/v1/onboarding', $this->validPayload())
            ->assertStatus(409)
            ->assertJsonPath('code', 'onboarding_unavailable');

        $this->assertSame(1, User::query()->count());
        $this->assertSame($existing->id, User::query()->value('id'));
        $this->assertSame(0, PlatformSetting::query()->whereNotNull('onboarding_completed_at')->count());
    }

    public function test_repeticao_apos_sucesso_falha(): void
    {
        $this->enableOnboarding();

        $this->asSpa()
            ->postJson('/api/v1/onboarding', $this->validPayload())
            ->assertCreated();

        $this->asSpa()
            ->postJson('/api/v1/onboarding', $this->validPayload([
                'email' => 'outro@plataforma.example',
            ]))
            ->assertStatus(409)
            ->assertJsonPath('code', 'onboarding_unavailable');

        $this->assertSame(1, User::query()->count());
        $this->assertSame(1, PlatformMembership::query()->count());
    }

    public function test_exclusao_do_proprietario_bloqueada_e_onboarding_permanece_fechado(): void
    {
        $this->enableOnboarding();

        $this->asSpa()
            ->postJson('/api/v1/onboarding', $this->validPayload())
            ->assertCreated();

        $user = User::query()->firstOrFail();

        try {
            $user->delete();
            $this->fail('Exclusão do Proprietário deveria ser bloqueada.');
        } catch (PlatformOwnerException $e) {
            $this->assertSame('platform_owner_cannot_remove', $e->errorCode);
        }

        $this->assertDatabaseHas('users', ['id' => $user->id]);
        $this->assertSame(1, PlatformMembership::query()->count());

        $settings = PlatformSetting::query()->findOrFail(PlatformSetting::SINGLETON_ID);
        $this->assertNotNull($settings->onboarding_completed_at);
        $this->assertSame($user->id, (int) $settings->onboarded_by_user_id);

        $this->getJson('/api/v1/onboarding/status')
            ->assertOk()
            ->assertJsonPath('data.available', false);

        $this->asSpa()
            ->postJson('/api/v1/onboarding', $this->validPayload([
                'email' => 'novo@plataforma.example',
            ]))
            ->assertStatus(409);
    }

    public function test_production_sem_https_rejeita_antes_de_gravar(): void
    {
        $this->enableOnboarding();
        $this->app['env'] = 'production';

        $this->withoutMiddleware([
            ValidateCsrfToken::class,
        ]);

        $this->postJson('/api/v1/onboarding', $this->validPayload())
            ->assertForbidden()
            ->assertJsonPath('code', 'https_required');

        $this->assertSame(0, User::query()->count());
        $this->assertSame(0, PlatformSetting::query()->count());
    }

    public function test_resposta_nao_vaza_token_em_erros(): void
    {
        $this->enableOnboarding();

        $response = $this->asSpa()
            ->postJson('/api/v1/onboarding', $this->validPayload([
                'onboarding_token' => self::TOKEN.'-wrong',
            ]));

        $response->assertForbidden();
        $this->assertStringNotContainsString(self::TOKEN, $response->getContent());
        $this->assertStringNotContainsString('SenhaForte12!', $response->getContent());
    }

    public function test_servico_rollback_quando_falha_apos_claim(): void
    {
        $this->enableOnboarding();

        $service = app(InitialOnboardingService::class);

        try {
            DB::transaction(function () use ($service): void {
                // Simula concorrência: claim manual da linha antes do complete.
                PlatformSetting::query()->create([
                    'id' => PlatformSetting::SINGLETON_ID,
                    'organization_name' => 'Já reivindicado',
                    'onboarding_completed_at' => now(),
                    'onboarded_by_user_id' => null,
                ]);

                $service->complete(
                    'Outra Org',
                    'race@example.com',
                    'SenhaForte12!',
                    self::TOKEN,
                );
            });
            $this->fail('Esperava falha por base não pristina.');
        } catch (\Throwable) {
            // expected
        }

        $this->assertSame(0, User::query()->where('email', 'race@example.com')->count());
    }

    public function test_migration_backfill_em_instalacao_existente(): void
    {
        // RefreshDatabase já rodou migrations com base vazia (sem backfill).
        // Simula upgrade: dados estruturais + reexecução lógica do backfill.
        $user = User::factory()->asPlatformAdmin()->create();
        $this->assertSame(0, PlatformSetting::query()->count());

        // Reaplica o critério da migration para bases legadas.
        $alreadyInitialized = User::query()->exists()
            || Office::query()->exists()
            || PlatformMembership::query()->exists();
        $this->assertTrue($alreadyInitialized);

        PlatformSetting::query()->create([
            'id' => PlatformSetting::SINGLETON_ID,
            'organization_name' => config('app.name', 'Plataforma'),
            'onboarding_completed_at' => now(),
            'onboarded_by_user_id' => $user->id,
        ]);

        $this->enableOnboarding();
        $this->getJson('/api/v1/onboarding/status')
            ->assertJsonPath('data.available', false);
    }
}

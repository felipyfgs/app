<?php

namespace Tests\Feature\Sefaz;

use App\Enums\OfficeRole;
use App\Models\Client;
use App\Models\CteDocument;
use App\Models\Establishment;
use App\Models\Office;
use App\Models\OfficeIntegrationToken;
use App\Models\User;
use App\Support\CurrentOffice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CteEmitterPushTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_emite_token_uma_vez_e_revoga(): void
    {
        config(['sefaz.cte_emitter_push.enabled' => true]);
        $office = Office::factory()->create();
        $user = User::factory()->forOffice($office, OfficeRole::Admin)->withTwoFactorConfirmed()->create();
        $this->actingAs($user);
        app(CurrentOffice::class)->resolve($user);

        $res = $this->postJson('/api/v1/office/integration-tokens', [
            'name' => 'ERP Piloto',
            'expires_in_days' => 30,
        ])->assertCreated();

        $plain = $res->json('data.token');
        $this->assertNotEmpty($plain);
        $this->assertStringStartsWith('cte_', $plain);

        $tokenId = $res->json('data.id');
        $this->postJson("/api/v1/office/integration-tokens/{$tokenId}/revoke")
            ->assertOk()
            ->assertJsonPath('data.status', 'REVOKED');

        // Sem rota de recuperação do plaintext
        $this->getJson('/api/v1/office/integration-tokens')
            ->assertOk()
            ->assertJsonMissingPath('data.0.token')
            ->assertJsonMissingPath('data.0.token_hash');
    }

    public function test_operator_nao_emite_nem_revoga_token(): void
    {
        config(['sefaz.cte_emitter_push.enabled' => true]);
        $office = Office::factory()->create();
        $operator = User::factory()->forOffice($office, OfficeRole::Operator)->withTwoFactorConfirmed()->create();
        $token = OfficeIntegrationToken::query()->create([
            'office_id' => $office->id,
            'name' => 'Existente',
            'token_prefix' => 'cte_prefixxx',
            'token_hash' => hash('sha256', 'cte_dummy'),
            'scope' => 'cte:ingest',
            'status' => 'ACTIVE',
            'expires_at' => now()->addDay(),
        ]);

        $this->actingAs($operator);
        app(CurrentOffice::class)->resolve($operator);

        $this->postJson('/api/v1/office/integration-tokens', ['name' => 'x'])
            ->assertForbidden();
        $this->postJson("/api/v1/office/integration-tokens/{$token->id}/revoke")
            ->assertForbidden();
    }

    public function test_admin_sem_2fa_nao_emite_token(): void
    {
        config([
            'sefaz.cte_emitter_push.enabled' => true,
            'fortify.two_factor_required' => true,
        ]);
        $office = Office::factory()->create();
        // ADMIN sem TOTP confirmado
        $user = User::factory()->forOffice($office, OfficeRole::Admin)->create([
            'two_factor_secret' => null,
            'two_factor_confirmed_at' => null,
        ]);
        $this->actingAs($user);
        app(CurrentOffice::class)->resolve($user);

        $this->postJson('/api/v1/office/integration-tokens', ['name' => 'ERP'])
            ->assertForbidden()
            ->assertJsonPath('code', 'two_factor_required');
    }

    public function test_push_importa_cte_com_token_valido(): void
    {
        config(['sefaz.cte_emitter_push.enabled' => true]);
        $office = Office::factory()->create();
        $client = Client::factory()->forOffice($office)->create();
        Establishment::factory()->forClient($client)->create(['cnpj' => '11222333000181']);

        $plain = 'cte_testtoken_'.str_repeat('a', 32);
        OfficeIntegrationToken::query()->create([
            'office_id' => $office->id,
            'name' => 'Test',
            'token_prefix' => substr($plain, 0, 12),
            'token_hash' => hash('sha256', $plain),
            'scope' => 'cte:ingest',
            'status' => 'ACTIVE',
            'expires_at' => now()->addDay(),
        ]);

        $xml = file_get_contents(base_path('tests/fixtures/cte/procCTe_57_roles_all.xml'));
        $this->call(
            'POST',
            '/api/v1/integrations/cte/push',
            [],
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer '.$plain,
                'CONTENT_TYPE' => 'application/xml',
                'HTTP_ACCEPT' => 'application/json',
            ],
            $xml,
        )->assertCreated()
            ->assertJsonPath('data.status', 'imported')
            ->assertJsonPath('data.kind', 'CTE');

        $this->assertSame(1, CteDocument::query()->where('office_id', $office->id)->count());
    }

    public function test_push_token_invalido_nao_revela_office(): void
    {
        config(['sefaz.cte_emitter_push.enabled' => true]);

        $this->postJson('/api/v1/integrations/cte/push', ['xml' => '<x/>'])
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Não autenticado.');
    }

    public function test_push_desabilitado_retorna_503(): void
    {
        config(['sefaz.cte_emitter_push.enabled' => false]);

        $this->withToken('cte_x')
            ->postJson('/api/v1/integrations/cte/push', ['xml' => '<x/>'])
            ->assertStatus(503);
    }
}

<?php

namespace Tests\Feature\Fiscal;

use App\Enums\AuthorCertificateMode;
use App\Enums\AuthorIdentityType;
use App\Enums\FiscalMutationStatus;
use App\Enums\OfficeRole;
use App\Enums\SerproAuthorizationStatus;
use App\Enums\SerproContractStatus;
use App\Enums\SerproEnvironment;
use App\Enums\TaxProxyPowerSource;
use App\Enums\TaxProxyPowerStatus;
use App\Enums\TermRePresentationStrategy;
use App\Models\Client;
use App\Models\Establishment;
use App\Models\FiscalMutationOperation;
use App\Models\Office;
use App\Models\OfficeSerproAuthorization;
use App\Models\SerproContract;
use App\Models\SerproServiceCatalogEntry;
use App\Models\TaxProxyPower;
use App\Models\User;
use App\Services\Fiscal\Mutations\RecentTwoFactorGate;
use App\Services\Integra\OfficeSerproAuthorizationService;
use App\Support\CurrentOffice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\Fakes\FakeFiscalMutationTransport;
use Tests\Support\UsesSerproTestDoubles;
use Tests\TestCase;

/**
 * Cobertura 13.8: TOTP expirado, poder revogado pós-preflight, clique duplo,
 * timeout, reconciliação e kill switch.
 */
class FiscalMutationOperationsTest extends TestCase
{
    use RefreshDatabase;
    use UsesSerproTestDoubles;

    private Office $office;

    private Client $client;

    private User $admin;

    private FakeFiscalMutationTransport $transport;

    protected function setUp(): void
    {
        parent::setUp();

        $this->enableMutationStack();

        $this->office = Office::factory()->create();
        $this->client = Client::factory()->forOffice($this->office)->create([
            'root_cnpj' => '12345678000199',
        ]);
        Establishment::factory()->forClient($this->client)->create();
        $this->admin = User::factory()
            ->forOffice($this->office, OfficeRole::Admin)
            ->withTwoFactorConfirmed()
            ->create();

        $this->transport = app(FakeFiscalMutationTransport::class);
        $this->transport->reset();

        $this->seedContractAndAuthorization();
        $this->enableCatalogOperation('INTEGRA_PAGAMENTO', 'SICALC', 'EMITIR_GUIA', 'SICALC');
        $this->importProxyPower('SICALC', 'INTEGRA_PAGAMENTO', 'SICALC');
    }

    public function test_mutacoes_desabilitadas_por_padrao_sem_flags(): void
    {
        config([
            'fiscal_mutations.enabled' => false,
            'features.mutating.enabled' => false,
            'features.modules.mutacoes.mutating_enabled' => false,
            'features.modules.guias.mutating_enabled' => false,
        ]);

        $this->actingAsAdminWithTotp();

        $response = $this->postJson('/api/v1/fiscal/mutations/preflight', $this->basePayload([
            'idempotency_key' => 'off-default-1',
        ]));

        $response->assertStatus(422);
        $codes = $response->json('data.codes') ?? [];
        $this->assertTrue(
            in_array('MUTATING_DISABLED', $codes, true)
            || in_array('OPERATION_COHORT_DISABLED', $codes, true)
            || in_array('FEATURE_DISABLED', $codes, true),
            'Esperava bloqueio por flag/coorte, got: '.json_encode($codes)
        );
    }

    public function test_senha_recente_ausente_bloqueia_preflight_e_execucao(): void
    {
        $this->actingAs($this->admin);
        app(CurrentOffice::class)->resolve($this->admin);
        // Sem markConfirmed → senha recente ausente/expirada

        $response = $this->postJson('/api/v1/fiscal/mutations/preflight', $this->basePayload([
            'idempotency_key' => 'pwd-exp-1',
        ]));

        $response->assertStatus(422);
        $codes = $response->json('data.codes') ?? [];
        $this->assertTrue(
            in_array('PASSWORD_CONFIRMATION_REQUIRED', $codes, true)
            || in_array('PASSWORD_CONFIRMATION_EXPIRED', $codes, true)
            || in_array('TOTP_EXPIRED', $codes, true),
            'Esperava código de confirmação de senha; got: '.json_encode($codes),
        );

        // Confirma (legado confirm-totp em testing) e expira
        $this->postJson('/api/v1/auth/confirm-totp', ['code' => '000000'])->assertOk();
        app(RecentTwoFactorGate::class)->expire(user: $this->admin);
        $this->withSession([
            RecentTwoFactorGate::SESSION_KEY => time() - (20 * 60),
        ]);

        $response2 = $this->postJson('/api/v1/fiscal/mutations/preflight', $this->basePayload([
            'idempotency_key' => 'pwd-exp-2',
        ]));
        $response2->assertStatus(422);
        $codes2 = $response2->json('data.codes') ?? [];
        $this->assertTrue(
            in_array('PASSWORD_CONFIRMATION_REQUIRED', $codes2, true)
            || in_array('PASSWORD_CONFIRMATION_EXPIRED', $codes2, true)
            || in_array('TOTP_EXPIRED', $codes2, true),
            'Esperava código de confirmação de senha; got: '.json_encode($codes2),
        );
    }

    public function test_poder_revogado_apos_preflight_bloqueia_envio(): void
    {
        $this->actingAsAdminWithTotp();

        $pre = $this->postJson('/api/v1/fiscal/mutations/preflight', $this->basePayload([
            'idempotency_key' => 'revoke-power-1',
            'competence_period_key' => '2026-06',
        ]));
        $pre->assertOk();
        $this->assertTrue($pre->json('data.eligible'));
        $token = $pre->json('data.preflight_token');
        $phrase = $pre->json('data.confirmation_phrase');

        // Revoga poder após preflight
        TaxProxyPower::query()
            ->where('office_id', $this->office->id)
            ->where('client_id', $this->client->id)
            ->where('power_code', 'SICALC')
            ->update(['status' => TaxProxyPowerStatus::Revoked->value]);

        $exec = $this->postJson('/api/v1/fiscal/mutations', $this->basePayload([
            'idempotency_key' => 'revoke-power-1',
            'competence_period_key' => '2026-06',
            'preflight_token' => $token,
            'confirmation_phrase' => $phrase,
            'confirmed' => true,
        ]));

        $exec->assertStatus(422);
        $this->assertSame('PROXY_POWER_REVOKED', $exec->json('code'));
        $this->assertSame(0, $this->transport->executeCalls);

        $op = FiscalMutationOperation::query()->withoutGlobalScopes()
            ->where('idempotency_key', 'revoke-power-1')->first();
        $this->assertNotNull($op);
        $this->assertSame(FiscalMutationStatus::Rejected, $op->status);
    }

    public function test_double_click_com_resposta_sintetica_nao_reenvia(): void
    {
        $this->actingAsAdminWithTotp();
        $this->transport->mode = 'success';

        $payload = $this->basePayload([
            'idempotency_key' => 'double-click-1',
            'competence_period_key' => '2026-05',
            'confirmation_phrase' => 'CONFIRMO-EMITIR_GUIA',
            'confirmed' => true,
        ]);

        $a = $this->postJson('/api/v1/fiscal/mutations', $payload);
        $a->assertCreated();
        $this->assertSame(FiscalMutationStatus::UnknownResult->value, $a->json('data.status'));
        $id = $a->json('data.id');

        $b = $this->postJson('/api/v1/fiscal/mutations', $payload);
        // Replay — mesmo id, sem segundo envio
        $this->assertSame($id, $b->json('data.id'));
        $this->assertSame(1, $this->transport->executeCalls);
    }

    public function test_timeout_gera_unknown_result_e_bloqueia_retry(): void
    {
        $this->actingAsAdminWithTotp();
        $this->transport->mode = 'timeout';

        $payload = $this->basePayload([
            'idempotency_key' => 'timeout-1',
            'competence_period_key' => '2026-04',
            'confirmation_phrase' => 'CONFIRMO-EMITIR_GUIA',
            'confirmed' => true,
        ]);

        $response = $this->postJson('/api/v1/fiscal/mutations', $payload);
        $response->assertCreated();
        $this->assertSame(FiscalMutationStatus::UnknownResult->value, $response->json('data.status'));
        $mutationId = $response->json('data.id');

        // Retry cego bloqueado
        $retry = $this->postJson('/api/v1/fiscal/mutations', $payload);
        $this->assertTrue(
            in_array($retry->status(), [200, 201, 409], true),
            'status='.$retry->status()
        );
        // Mesmo id em replay ou 409
        if ($retry->status() === 409) {
            $this->assertSame('RETRY_BLOCKED', $retry->json('code'));
        } else {
            $this->assertSame($mutationId, $retry->json('data.id'));
            $this->assertSame(FiscalMutationStatus::UnknownResult->value, $retry->json('data.status'));
        }
        $this->assertSame(1, $this->transport->executeCalls);

        // Nova chave lógica equivalente também bloqueada por UNCERTAIN_RESULT_OPEN
        $this->markTotp();
        $other = $this->postJson('/api/v1/fiscal/mutations/preflight', $this->basePayload([
            'idempotency_key' => 'timeout-1-other',
            'competence_period_key' => '2026-04',
        ]));
        $other->assertStatus(422);
        $this->assertContains('UNCERTAIN_RESULT_OPEN', $other->json('data.codes'));
    }

    public function test_reconciliacao_rejeita_resposta_sintetica_e_mantem_resultado_incerto(): void
    {
        $this->actingAsAdminWithTotp();
        $this->transport->mode = 'timeout';
        $this->transport->reconcileMode = 'confirmed';

        $payload = $this->basePayload([
            'idempotency_key' => 'recon-1',
            'competence_period_key' => '2026-03',
            'confirmation_phrase' => 'CONFIRMO-EMITIR_GUIA',
            'confirmed' => true,
        ]);

        $created = $this->postJson('/api/v1/fiscal/mutations', $payload);
        $created->assertCreated();
        $id = $created->json('data.id');
        $this->assertSame(FiscalMutationStatus::UnknownResult->value, $created->json('data.status'));

        $recon = $this->postJson("/api/v1/fiscal/mutations/{$id}/reconcile");
        $recon->assertOk();
        $this->assertSame(FiscalMutationStatus::UnknownResult->value, $recon->json('data.status'));
        $this->assertSame(1, $this->transport->reconcileCalls);
        $this->assertSame(1, $this->transport->executeCalls);
    }

    public function test_kill_switch_bloqueia_mutacao(): void
    {
        $this->actingAsAdminWithTotp();

        config(['fiscal_mutations.kill_switch' => true]);

        $response = $this->postJson('/api/v1/fiscal/mutations/preflight', $this->basePayload([
            'idempotency_key' => 'kill-1',
        ]));

        $response->assertStatus(422);
        $this->assertContains('KILL_SWITCH', $response->json('data.codes'));
        $this->assertSame(0, $this->transport->executeCalls);
    }

    public function test_preflight_retorna_efeito_custo_e_confirmacao(): void
    {
        $this->actingAsAdminWithTotp();

        $response = $this->postJson('/api/v1/fiscal/mutations/preflight', $this->basePayload([
            'idempotency_key' => 'pf-shape-1',
            'competence_period_key' => '2026-07',
            'payload' => ['valor_centavos' => 1500, 'xml' => str_repeat('A', 500)],
        ]));

        $response->assertOk()
            ->assertJsonPath('data.eligible', true)
            ->assertJsonPath('data.confirmation_required', true)
            ->assertJsonPath('data.competence', '2026-07')
            ->assertJsonStructure([
                'data' => [
                    'effect',
                    'contribuinte' => ['client_id'],
                    'cost_estimate',
                    'eligibility',
                    'preflight_token',
                    'confirmation_phrase',
                    'pre_operation_snapshot',
                    'mutation_operation_id',
                ],
            ]);

        // payload fiscal/xml não vaza na resposta
        $body = (string) $response->getContent();
        $this->assertStringNotContainsString(str_repeat('A', 500), $body);

        $op = FiscalMutationOperation::query()->withoutGlobalScopes()->find(
            $response->json('data.mutation_operation_id')
        );
        $this->assertNotNull($op);
        $this->assertSame(FiscalMutationStatus::Pending, $op->status);
        $this->assertSame('[redacted]', $op->request_sanitized['xml'] ?? null);
    }

    public function test_viewer_nao_executa_mutacao(): void
    {
        $viewer = User::factory()
            ->forOffice($this->office, OfficeRole::Viewer)
            ->withTwoFactorConfirmed()
            ->create();

        $this->actingAs($viewer);
        app(CurrentOffice::class)->clear();
        app(CurrentOffice::class)->resolve($viewer);

        $this->postJson('/api/v1/fiscal/mutations/preflight', $this->basePayload([
            'idempotency_key' => 'viewer-1',
        ]))->assertForbidden();
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function basePayload(array $extra = []): array
    {
        return array_merge([
            'client_id' => $this->client->id,
            'solution_code' => 'INTEGRA_PAGAMENTO',
            'service_code' => 'SICALC',
            'operation_code' => 'EMITIR_GUIA',
            'environment' => 'TRIAL',
            'module' => 'guias',
        ], $extra);
    }

    private function enableMutationStack(): void
    {
        config([
            'features.kill_switch' => false,
            'features.global_enabled' => true,
            'features.mutating.enabled' => true,
            'features.mutating.kill_switch' => false,
            'features.modules.mutacoes.enabled' => true,
            'features.modules.mutacoes.mutating_enabled' => true,
            'features.modules.mutacoes.allow_all_offices' => true,
            'features.modules.guias.enabled' => true,
            'features.modules.guias.mutating_enabled' => true,
            'features.modules.guias.allow_all_offices' => true,
            'fiscal_mutations.enabled' => true,
            'fiscal_mutations.kill_switch' => false,
            'fiscal_mutations.operations.INTEGRA_PAGAMENTO.SICALC.EMITIR_GUIA.enabled' => true,
            'fiscal_mutations.operations.INTEGRA_PAGAMENTO.SICALC.EMITIR_GUIA.allow_all_offices' => true,
            'fiscal_mutations.operations.INTEGRA_PAGAMENTO.SICALC.EMITIR_GUIA.office_allowlist' => [],
            'fiscal_mutations.anti_repeat_window_seconds' => 1,
            'serpro.kill_switch' => false,
            'serpro.termo_destination_cnpj' => '11222333000181',
            'serpro.term_representation.TRIAL' => TermRePresentationStrategy::ReuseStoredTerm->value,
            'serpro_usage.shadow_mode' => true,
            'serpro_usage.commercial_blocking_enabled' => false,
            'fortify.two_factor_required' => true,
        ]);
    }

    private function actingAsAdminWithTotp(): void
    {
        $this->actingAs($this->admin);
        app(CurrentOffice::class)->clear();
        app(CurrentOffice::class)->resolve($this->admin);
        $this->markTotp();
    }

    private function markTotp(): void
    {
        // withSession garante persistência entre postJson no driver array
        $this->withSession([
            RecentTwoFactorGate::SESSION_KEY => time(),
        ]);
        app(RecentTwoFactorGate::class)->markConfirmed($this->admin);
    }

    private function seedContractAndAuthorization(): void
    {
        SerproContract::query()->create([
            'environment' => SerproEnvironment::Trial->value,
            'status' => SerproContractStatus::Active->value,
            'contractor_cnpj' => '11222333000181',
            'contractor_name' => 'Software House Teste',
            'health_status' => 'HEALTHY',
            'pfx_vault_object_id' => (string) Str::ulid(),
            'oauth_vault_object_id' => (string) Str::ulid(),
            'fingerprint_sha256' => hash('sha256', 'test'),
            'cert_valid_from' => now()->subYear(),
            'cert_valid_to' => now()->addYear(),
            'activated_at' => now(),
        ]);

        $svc = app(OfficeSerproAuthorizationService::class);
        $svc->configureAuthor(
            $this->office,
            SerproEnvironment::Trial,
            AuthorIdentityType::Cpf,
            '52998224725',
            'Contador Teste',
            AuthorCertificateMode::ExternalSignature,
            $this->admin->id,
        );

        $auth = OfficeSerproAuthorization::query()
            ->where('office_id', $this->office->id)
            ->firstOrFail();
        $auth->forceFill([
            'status' => SerproAuthorizationStatus::TokenActive,
            'termo_vault_object_id' => (string) Str::ulid(),
            'termo_sha256' => hash('sha256', 'termo-fixture-mutacoes'),
            'termo_valid_from' => now()->subDay(),
            'termo_valid_to' => now()->addYear(),
            'termo_destination_cnpj' => '11222333000181',
            'termo_signed_by' => '52998224725',
            'termo_uploaded_at' => now(),
            'termo_authorization_state' => 'SIMULATED',
            'procurador_token_vault_object_id' => (string) Str::ulid(),
            'procurador_token_expires_at' => now()->addHours(6),
            'last_token_refresh_at' => now(),
        ])->save();

        $this->assertNotNull($auth);
        $this->assertSame(SerproAuthorizationStatus::TokenActive, $auth->status);
    }

    private function enableCatalogOperation(
        string $solution,
        string $service,
        string $operation,
        string $power,
    ): void {
        SerproServiceCatalogEntry::query()
            ->where('solution_code', $solution)
            ->where('service_code', $service)
            ->where('operation_code', $operation)
            ->update([
                'is_enabled' => true,
                'is_mutating' => true,
                'required_proxy_power' => $power,
            ]);
    }

    private function importProxyPower(string $power, string $system, string $service): void
    {
        $auth = OfficeSerproAuthorization::query()
            ->where('office_id', $this->office->id)
            ->firstOrFail();

        TaxProxyPower::query()->create([
            'office_id' => $this->office->id,
            'client_id' => $this->client->id,
            'office_serpro_authorization_id' => $auth->id,
            'author_identity' => $auth->author_identity,
            'contributor_cnpj' => '12345678000199',
            'system_code' => $system,
            'service_code' => $service,
            'power_code' => $power,
            'source' => TaxProxyPowerSource::ManualOfficialEvidence,
            'status' => TaxProxyPowerStatus::Active,
            'valid_from' => now()->subDay(),
            'valid_to' => now()->addYear(),
            'evidence_ref' => 'TEST-EVID-001',
            'verified_at' => now(),
            'last_check_result' => 'MANUAL_IMPORT',
        ]);
    }
}

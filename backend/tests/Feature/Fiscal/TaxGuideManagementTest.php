<?php

namespace Tests\Feature\Fiscal;

use App\Enums\OfficeRole;
use App\Enums\TaxGuideEmissionStatus;
use App\Enums\TaxGuidePaymentStatus;
use App\Models\Client;
use App\Models\Office;
use App\Models\TaxGuide;
use App\Models\TaxGuideVersion;
use App\Models\User;
use App\Services\Fiscal\Guides\FakeGuideEmissionClient;
use App\Services\Fiscal\Guides\GuideHighRiskGate;
use App\Services\Fiscal\Guides\GuideIssuanceService;
use App\Services\Fiscal\Guides\GuidePaymentService;
use App\Services\Fiscal\Mutations\RecentTwoFactorGate;
use App\Support\CurrentOffice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tasks 11.6–11.11 (guias): substituição, tenant cruzado, timeout UNKNOWN_RESULT,
 * pagamento independente de download, 2FA/confirmação reforçada.
 */
class TaxGuideManagementTest extends TestCase
{
    use RefreshDatabase;

    private Office $office;

    private Client $client;

    private User $admin;

    private FakeGuideEmissionClient $fakeClient;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'features.global_enabled' => true,
            'features.kill_switch' => false,
            'features.mutating.enabled' => true,
            'features.mutating.kill_switch' => false,
            'features.modules.guias.enabled' => true,
            'features.modules.guias.mutating_enabled' => true,
            'features.modules.guias.allow_all_offices' => true,
            'tax_guides.enabled' => true,
            'fortify.two_factor_required' => true,
            'serpro_usage.shadow_mode' => true,
        ]);

        $this->office = Office::factory()->create();
        $this->client = Client::factory()->forOffice($this->office)->create();
        $this->admin = User::factory()->forOffice($this->office, OfficeRole::Admin)->withTwoFactorConfirmed()->create();

        $this->fakeClient = app(FakeGuideEmissionClient::class);
        $this->fakeClient->emitMode = 'success';
        $this->fakeClient->paymentMode = 'NOT_PAID';
        $this->fakeClient->reconcileMode = 'FOUND';
    }

    private function enableChallenge(): void
    {
        $this->actingAs($this->admin);
        app(CurrentOffice::class)->clear();
        app(CurrentOffice::class)->resolve($this->admin);
        // markConfirmed grava sessão + cache por usuário (compatível com requests HTTP do teste)
        app(RecentTwoFactorGate::class)->markConfirmed($this->admin);
        $this->withSession([
            RecentTwoFactorGate::SESSION_KEY => time(),
        ]);
    }

    private function issuePayload(array $overrides = []): array
    {
        return array_merge([
            'client_id' => $this->client->id,
            'system_code' => 'INTEGRA_PAGAMENTO',
            'service_code' => 'SICALC',
            'operation_code' => 'EMITIR_GUIA',
            'competence_period_key' => '2026-06',
            'debit_ref' => 'DEB-001',
            'amount_cents' => 15000,
            'due_at' => now()->addDays(10)->toDateString(),
            'confirmation' => true,
            'confirmation_summary' => [
                'client_id' => $this->client->id,
                'competence_period_key' => '2026-06',
                'amount_cents' => 15000,
                'effect' => 'Emissão de guia Sicalc',
            ],
        ], $overrides);
    }

    public function test_mutacoes_desabilitadas_por_default_bloqueiam_emissao(): void
    {
        config([
            'features.modules.guias.mutating_enabled' => false,
            'features.mutating.enabled' => false,
        ]);

        $this->enableChallenge();

        $this->postJson('/api/v1/fiscal/guides', $this->issuePayload())
            ->assertForbidden()
            ->assertJsonPath('code', 'mutating_disabled');
    }

    public function test_alto_risco_sem_2fa_recente_exige_desafio_antes_de_reservar(): void
    {
        $this->actingAs($this->admin);
        app(CurrentOffice::class)->resolve($this->admin);
        // sem markConfirmed

        $this->postJson('/api/v1/fiscal/guides', $this->issuePayload())
            ->assertForbidden()
            ->assertJsonPath('code', 'high_risk_challenge_required');

        $this->assertSame(0, TaxGuide::query()->withoutGlobalScopes()->count());
    }

    public function test_emissao_sucesso_preserva_bytes_e_nao_marca_pagamento(): void
    {
        $this->enableChallenge();

        $res = $this->postJson('/api/v1/fiscal/guides', $this->issuePayload())
            ->assertCreated()
            ->assertJsonPath('data.version.emission_status', 'CONFIRMED')
            ->assertJsonPath('data.payment_status', 'NOT_CONFIRMED')
            ->assertJsonPath('data.version.has_document', true);

        $guideId = $res->json('data.guide.id');
        $sha = $res->json('data.version.content_sha256');
        $this->assertNotEmpty($sha);
        $this->assertStringNotContainsString('vault', json_encode($res->json('data')));

        $guide = TaxGuide::query()->withoutGlobalScopes()->findOrFail($guideId);
        $this->assertSame(TaxGuidePaymentStatus::NotConfirmed, $guide->payment_status);
        $this->assertNotNull($guide->currentVersion->vault_object_id);
    }

    public function test_idempotencia_reutiliza_guia_valida(): void
    {
        $this->enableChallenge();

        $first = $this->postJson('/api/v1/fiscal/guides', $this->issuePayload())->assertCreated();
        $second = $this->postJson('/api/v1/fiscal/guides', $this->issuePayload())->assertOk();

        $this->assertTrue($second->json('data.reused'));
        $this->assertSame($first->json('data.version.id'), $second->json('data.version.id'));
        $this->assertSame(1, TaxGuideVersion::query()->withoutGlobalScopes()->count());
    }

    public function test_substituicao_preserva_historico_e_marca_vigente(): void
    {
        $this->enableChallenge();

        $first = $this->postJson('/api/v1/fiscal/guides', $this->issuePayload([
            'amount_cents' => 10000,
        ]))->assertCreated();

        $guideId = $first->json('data.guide.id');
        $v1 = $first->json('data.version.id');

        // Reemissão forçada com valor diferente
        $this->fakeClient->emitMode = 'different_amount';
        $second = $this->postJson('/api/v1/fiscal/guides', $this->issuePayload([
            'amount_cents' => 20000,
            'force_reissue' => true,
        ]))->assertCreated();

        $this->assertTrue($second->json('data.substituted'));
        $this->assertNotSame($v1, $second->json('data.version.id'));
        $this->assertSame(2, TaxGuideVersion::query()->withoutGlobalScopes()->where('tax_guide_id', $guideId)->count());

        $old = TaxGuideVersion::query()->withoutGlobalScopes()->findOrFail($v1);
        $this->assertSame(TaxGuideEmissionStatus::Superseded, $old->emission_status);
        $this->assertFalse($old->is_current);
        $this->assertNotNull($old->vault_object_id); // artefato histórico preservado

        $new = TaxGuideVersion::query()->withoutGlobalScopes()->findOrFail($second->json('data.version.id'));
        $this->assertTrue($new->is_current);
        $this->assertSame(TaxGuideEmissionStatus::Confirmed, $new->emission_status);
        $this->assertSame($old->id, $new->replaces_version_id);
    }

    public function test_download_nao_altera_pagamento(): void
    {
        $this->enableChallenge();

        $issued = $this->postJson('/api/v1/fiscal/guides', $this->issuePayload())->assertCreated();
        $guideId = $issued->json('data.guide.id');

        $tokenRes = $this->postJson("/api/v1/fiscal/guides/{$guideId}/download-token")
            ->assertOk();
        $token = $tokenRes->json('data.token');
        $this->assertStringNotContainsString('storage', $tokenRes->json('data.download_path'));

        $this->get("/api/v1/fiscal/guides/downloads/{$token}")
            ->assertOk()
            ->assertHeader('X-Content-SHA256');

        $guide = TaxGuide::query()->withoutGlobalScopes()->findOrFail($guideId);
        $this->assertSame(TaxGuidePaymentStatus::NotConfirmed, $guide->payment_status);
        $this->assertNull($guide->payment_confirmed_at);
    }

    public function test_pagamento_oficial_independente_da_emissao(): void
    {
        $this->enableChallenge();

        $issued = $this->postJson('/api/v1/fiscal/guides', $this->issuePayload())->assertCreated();
        $guideId = $issued->json('data.guide.id');

        $this->fakeClient->paymentMode = 'PAID';
        $pay = $this->postJson("/api/v1/fiscal/guides/{$guideId}/payment-confirmations")
            ->assertOk()
            ->assertJsonPath('data.lookup_status', 'PAID');

        $this->assertNotNull($pay->json('data.confirmation.id'));
        $this->assertSame('CONFIRMED', $pay->json('data.guide.payment_status'));

        // Emissão histórica intacta
        $version = TaxGuideVersion::query()->withoutGlobalScopes()
            ->where('tax_guide_id', $guideId)
            ->where('is_current', true)
            ->firstOrFail();
        $this->assertSame(TaxGuideEmissionStatus::Confirmed, $version->emission_status);
        $this->assertNotNull($version->content_sha256);
    }

    public function test_timeout_pos_envio_gera_unknown_result_e_bloqueia_retry(): void
    {
        $this->enableChallenge();
        $this->fakeClient->emitMode = 'timeout';

        $res = $this->postJson('/api/v1/fiscal/guides', $this->issuePayload())
            ->assertStatus(202)
            ->assertJsonPath('data.version.emission_status', 'UNKNOWN_RESULT');

        $guideId = $res->json('data.guide.id');
        $versionId = $res->json('data.version.id');

        // Retry imediato bloqueado
        $this->fakeClient->emitMode = 'success';
        $this->postJson('/api/v1/fiscal/guides', $this->issuePayload())
            ->assertStatus(409)
            ->assertJsonPath('code', 'retry_blocked_unknown_result');

        // Reconciliação resolve
        $this->fakeClient->reconcileMode = 'FOUND';
        $this->postJson("/api/v1/fiscal/guides/{$guideId}/reconcile")
            ->assertOk()
            ->assertJsonPath('data.outcome', 'FOUND')
            ->assertJsonPath('data.version.emission_status', 'CONFIRMED');

        $version = TaxGuideVersion::query()->withoutGlobalScopes()->findOrFail($versionId);
        $this->assertTrue($version->hasStoredDocument());
    }

    public function test_tenant_cruzado_nao_encontra_guia_nem_gera_download(): void
    {
        $this->enableChallenge();
        $issued = $this->postJson('/api/v1/fiscal/guides', $this->issuePayload())->assertCreated();
        $guideId = $issued->json('data.guide.id');

        $otherOffice = Office::factory()->create();
        $otherAdmin = User::factory()->forOffice($otherOffice, OfficeRole::Admin)->withTwoFactorConfirmed()->create();

        $this->actingAs($otherAdmin);
        app(CurrentOffice::class)->clear();
        app(CurrentOffice::class)->resolve($otherAdmin);
        app(RecentTwoFactorGate::class)->markConfirmed($otherAdmin);

        $this->getJson("/api/v1/fiscal/guides/{$guideId}")
            ->assertNotFound();

        $this->postJson("/api/v1/fiscal/guides/{$guideId}/download-token")
            ->assertNotFound();
    }

    public function test_servico_emite_com_substituicao_via_service_layer(): void
    {
        $this->enableChallenge();
        $svc = app(GuideIssuanceService::class);

        $a = $svc->issue(
            office: $this->office,
            client: $this->client,
            systemCode: 'INTEGRA_PAGAMENTO',
            serviceCode: 'SICALC',
            operationCode: 'EMITIR_GUIA',
            competencePeriodKey: '2026-05',
            debitRef: 'X-1',
            amountCents: 5000,
            dueAtIso: now()->addDays(5)->toIso8601String(),
            user: $this->admin,
            explicitConfirmation: true,
            confirmationSummary: ['effect' => 'emit', 'amount_cents' => 5000],
        );
        $this->assertFalse($a['substituted']);

        $this->fakeClient->emitMode = 'different_amount';
        $b = $svc->issue(
            office: $this->office,
            client: $this->client,
            systemCode: 'INTEGRA_PAGAMENTO',
            serviceCode: 'SICALC',
            operationCode: 'EMITIR_GUIA',
            competencePeriodKey: '2026-05',
            debitRef: 'X-1',
            amountCents: 9000,
            dueAtIso: now()->addDays(8)->toIso8601String(),
            user: $this->admin,
            explicitConfirmation: true,
            confirmationSummary: ['effect' => 'reissue', 'amount_cents' => 9000],
            forceReissue: true,
        );

        $this->assertTrue($b['substituted']);
        $this->assertSame(2, $b['guide']->versions()->withoutGlobalScopes()->count());
    }

    public function test_challenge_endpoint_marca_2fa_recente(): void
    {
        $this->actingAs($this->admin);
        app(CurrentOffice::class)->resolve($this->admin);

        $this->postJson('/api/v1/fiscal/guides/challenge', ['totp_code' => '000000'])
            ->assertOk()
            ->assertJsonPath('data.confirmed', true);

        $this->assertTrue(app(GuideHighRiskGate::class)->hasRecentChallenge($this->admin));
    }

    public function test_payment_service_nao_infere_por_download(): void
    {
        $this->enableChallenge();
        $issued = app(GuideIssuanceService::class)->issue(
            office: $this->office,
            client: $this->client,
            systemCode: 'INTEGRA_CONTADOR',
            serviceCode: 'GUIAS',
            operationCode: 'EMITIR_GUIA',
            competencePeriodKey: '2026-01',
            debitRef: 'P-1',
            amountCents: 100,
            dueAtIso: null,
            user: $this->admin,
            explicitConfirmation: true,
            confirmationSummary: ['effect' => 'x'],
        );

        $status = app(GuidePaymentService::class)->assertDownloadDoesNotPay($issued['guide']);
        $this->assertFalse($status->isOfficiallyPaid());
    }
}

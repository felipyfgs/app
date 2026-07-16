<?php

namespace Tests\Feature\Serpro;

use App\Contracts\IntegraProcuracoesClient;
use App\Domain\BrazilianTaxId;
use App\Domain\Cnpj;
use App\Domain\Cpf;
use App\DTO\Serpro\ProcuracaoLookupRequest;
use App\DTO\Serpro\ProcuracaoLookupResult;
use App\Enums\OfficeRole;
use App\Enums\SerproAuthorizationStatus;
use App\Enums\SerproContractStatus;
use App\Enums\SerproEnvironment;
use App\Enums\SerproExternalGateKind;
use App\Enums\SerproExternalGateStatus;
use App\Enums\TaxProxyPowerSource;
use App\Enums\TaxProxyPowerStatus;
use App\Models\Client;
use App\Models\Establishment;
use App\Models\Office;
use App\Models\OfficeSerproAuthorization;
use App\Models\SerproContract;
use App\Models\SerproExternalGate;
use App\Models\TaxProxyPower;
use App\Models\User;
use App\Services\Integra\ProxyPowerMatrixService;
use App\Services\Integra\TaxProxyPowerService;
use App\Services\Serpro\OfficialClarificationGate;
use App\Services\Serpro\SerproLifecycleMonitor;
use App\Services\Serpro\SerproProductionOnboardingGuard;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * Coverage: 5.1–5.9 onboarding, procurações, identidades, D-1, matriz, lifecycle.
 */
class SerproEligibilityProxyTest extends TestCase
{
    use RefreshDatabase;

    public function test_manual_import_stays_pending_until_explicit_approval(): void
    {
        [$office, $client, $auth] = $this->seedOfficeClientAuth();

        $svc = app(TaxProxyPowerService::class);
        $power = $svc->importManualEvidence(
            $office,
            $client,
            $auth,
            'PGDASD',
            'INTEGRA_SN',
            'PGDASD',
            CarbonImmutable::now()->subDay(),
            CarbonImmutable::now()->addYear(),
            'EVIDENCE-OFFICIAL-REF-1',
            null,
            null,
            SerproEnvironment::Trial,
        );

        $this->assertSame(TaxProxyPowerStatus::Pending, $power->status);
        $this->assertSame(TaxProxyPowerService::PROVENANCE_MANUAL_PENDING, $power->provenance);
        $this->assertNull(
            $svc->findUsablePower($office->id, $client->id, 'PGDASD', $auth->author_identity, SerproEnvironment::Trial)
        );

        $approved = $svc->approveManualEvidence($power, actorUserId: null);
        $this->assertSame(TaxProxyPowerStatus::Active, $approved->status);
        $this->assertNotNull(
            $svc->findUsablePower($office->id, $client->id, 'PGDASD', $auth->author_identity, SerproEnvironment::Trial)
        );
    }

    public function test_simulated_sync_never_activates_and_full_sync_closes_missing(): void
    {
        [$office, $client, $auth] = $this->seedOfficeClientAuth();

        // Poder pré-existente ACTIVE da API
        TaxProxyPower::query()->create([
            'office_id' => $office->id,
            'client_id' => $client->id,
            'office_serpro_authorization_id' => $auth->id,
            'environment' => SerproEnvironment::Trial->value,
            'author_identity' => $auth->author_identity,
            'contributor_cnpj' => '11222333000181',
            'system_code' => 'INTEGRA_SN',
            'service_code' => 'PGDASD',
            'power_code' => 'OLDPOWER',
            'source' => TaxProxyPowerSource::IntegraProcuracoes,
            'provenance' => TaxProxyPowerService::PROVENANCE_API_VERIFIED,
            'segregation_class' => 'PRODUCTION',
            'status' => TaxProxyPowerStatus::Active,
            'valid_from' => now()->subMonth(),
            'valid_to' => now()->addYear(),
            'accepted_at' => now()->subDay(),
            'freshness_checked_at' => now(),
            'verified_at' => now(),
        ]);

        $this->app->instance(IntegraProcuracoesClient::class, new class implements IntegraProcuracoesClient
        {
            public function lookup(ProcuracaoLookupRequest $request): ProcuracaoLookupResult
            {
                return new ProcuracaoLookupResult(
                    success: true,
                    powers: [
                        [
                            'power_code' => 'NEWPOWER',
                            'system_code' => 'INTEGRA_SN',
                            'service_code' => 'PGDASD',
                            'valid_from' => now()->subMonth()->toIso8601String(),
                            'valid_to' => now()->addYear()->toIso8601String(),
                            'status' => 'ACTIVE',
                        ],
                    ],
                    simulated: true,
                    evidenceRef: 'SIM-1',
                );
            }
        });

        $svc = app(TaxProxyPowerService::class);
        $saved = $svc->syncFromApi($office, $client, $auth, SerproEnvironment::Trial);

        $this->assertCount(1, $saved);
        $this->assertSame(TaxProxyPowerStatus::Pending, $saved[0]->status);
        $this->assertSame(TaxProxyPowerService::PROVENANCE_SIMULATED, $saved[0]->provenance);

        // Full sync simulado NÃO fecha ausentes
        $old = TaxProxyPower::query()->where('power_code', 'OLDPOWER')->first();
        $this->assertSame(TaxProxyPowerStatus::Active, $old->status);

        // Full sync real fecha ausentes
        $this->app->instance(IntegraProcuracoesClient::class, new class implements IntegraProcuracoesClient
        {
            public function lookup(ProcuracaoLookupRequest $request): ProcuracaoLookupResult
            {
                return new ProcuracaoLookupResult(
                    success: true,
                    powers: [
                        [
                            'power_code' => 'NEWPOWER',
                            'system_code' => 'INTEGRA_SN',
                            'service_code' => 'PGDASD',
                            'valid_from' => now()->subMonth()->toIso8601String(),
                            'valid_to' => now()->addYear()->toIso8601String(),
                            'status' => 'ACTIVE',
                            'accept_status' => 'ACEITO',
                        ],
                    ],
                    simulated: false,
                    evidenceRef: 'REAL-1',
                );
            }
        });

        $svc = app(TaxProxyPowerService::class);
        $real = $svc->syncFromApi($office, $client, $auth, SerproEnvironment::Trial);
        $this->assertSame(TaxProxyPowerStatus::Active, $real[0]->status);
        $this->assertNotNull($real[0]->accepted_at);

        $old->refresh();
        $this->assertSame(TaxProxyPowerStatus::Revoked, $old->status);
        $this->assertNotNull($old->closed_at);
    }

    public function test_pending_accept_stays_ineligible(): void
    {
        [$office, $client, $auth] = $this->seedOfficeClientAuth();

        $this->app->instance(IntegraProcuracoesClient::class, new class implements IntegraProcuracoesClient
        {
            public function lookup(ProcuracaoLookupRequest $request): ProcuracaoLookupResult
            {
                return new ProcuracaoLookupResult(
                    success: true,
                    powers: [[
                        'power_code' => 'PGDASD',
                        'system_code' => 'INTEGRA_SN',
                        'service_code' => 'PGDASD',
                        'valid_from' => now()->subMonth()->toIso8601String(),
                        'valid_to' => now()->addYear()->toIso8601String(),
                        'status' => 'ACTIVE',
                        'accept_status' => 'PENDING_ACCEPT',
                    ]],
                    simulated: false,
                    evidenceRef: 'REAL-ACCEPT',
                );
            }
        });

        $svc = app(TaxProxyPowerService::class);
        $power = $svc->syncFromApi($office, $client, $auth, SerproEnvironment::Trial)[0];
        $this->assertSame(TaxProxyPowerStatus::Pending, $power->status);
        $this->assertNull($svc->findUsablePower(
            $office->id,
            $client->id,
            'PGDASD',
            $auth->author_identity,
            SerproEnvironment::Trial,
        ));
    }

    public function test_d1_coverage_rule(): void
    {
        $ref = CarbonImmutable::parse('2026-07-16 12:00:00', 'America/Sao_Paulo');

        $covering = new TaxProxyPower([
            'status' => TaxProxyPowerStatus::Active,
            'valid_from' => $ref->subDays(10),
            'valid_to' => $ref->addDays(10),
        ]);
        $this->assertTrue($covering->coversD1($ref));

        // Vigência só a partir de D0 — não cobre D-1
        $startsToday = new TaxProxyPower([
            'status' => TaxProxyPowerStatus::Active,
            'valid_from' => $ref->startOfDay(),
            'valid_to' => $ref->addDays(10),
        ]);
        $this->assertFalse($startsToday->coversD1($ref));
    }

    public function test_power_matrix_review_required_on_source_hash_change(): void
    {
        $matrix = app(ProxyPowerMatrixService::class);
        $summary = $matrix->summary();
        $this->assertSame(ProxyPowerMatrixService::REVIEW_APPROVED, $summary['review_status']);
        $this->assertGreaterThan(0, $summary['entry_count']);

        $ok = $matrix->evaluateUsability($summary['source_content_sha256']);
        $this->assertTrue($ok['usable']);

        $changed = $matrix->evaluateUsability(str_repeat('a', 64));
        $this->assertFalse($changed['usable']);
        $this->assertSame(ProxyPowerMatrixService::REVIEW_REQUIRED, $changed['review_status']);

        $powers = $matrix->requiredPowers('PGDASD', 'TRANSDECLARACAO11');
        $this->assertContains('00146', $powers);
    }

    public function test_billable_lookup_blocked_in_free_smoke_sync(): void
    {
        [$office, $client, $auth] = $this->seedOfficeClientAuth();
        $svc = app(TaxProxyPowerService::class);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('OBTERPROCURACAO41');
        $svc->syncFromApi(
            $office,
            $client,
            $auth,
            SerproEnvironment::Trial,
            null,
            null,
            allowBillableLookup: false,
        );
    }

    public function test_cnpj_cpf_round_trip_and_alphanumeric(): void
    {
        $cnpj = Cnpj::parse('11.222.333/0001-81');
        $this->assertSame('11222333000181', $cnpj->toStorageString());
        $this->assertTrue(Cnpj::fromStorageString($cnpj->toStorageString())->equals($cnpj));

        $base = '12ABC34501DE';
        $d1 = $this->cnpjDigit($base, [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]);
        $d2 = $this->cnpjDigit($base.$d1, [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]);
        $alpha = Cnpj::parse(strtolower($base.$d1.$d2));
        $this->assertSame(strtoupper($base.$d1.$d2), $alpha->value());

        $json = json_encode(['cnpj' => $alpha->value()], JSON_THROW_ON_ERROR);
        $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame($alpha->value(), Cnpj::parse($decoded['cnpj'])->value());

        $id = BrazilianTaxId::parse($alpha->value());
        $this->assertTrue($id->isCnpj());
        $this->assertSame($alpha->value(), BrazilianTaxId::fromArrayOrString($id->toArray())->value());

        // CPF válido conhecido
        $cpfRaw = $this->validCpf();
        $cpf = Cpf::parse($cpfRaw);
        $this->assertSame(11, strlen($cpf->value()));
        $this->assertSame($cpf->value(), BrazilianTaxId::parseCpf($cpfRaw)->value());
    }

    public function test_official_clarification_gate_blocks_alpha_cnpj_in_termo_production(): void
    {
        SerproExternalGate::query()->create([
            'kind' => SerproExternalGateKind::CnpjAlphanumericSerialization->value,
            'status' => SerproExternalGateStatus::Open->value,
            'title' => 'CNPJ alpha',
        ]);

        $base = '12ABC34501DE';
        $d1 = $this->cnpjDigit($base, [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]);
        $d2 = $this->cnpjDigit($base.$d1, [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]);
        $alpha = strtoupper($base.$d1.$d2);

        $gate = app(OfficialClarificationGate::class);
        $blocked = $gate->evaluateCnpjField(
            $alpha,
            OfficialClarificationGate::CONTEXT_TERMO_XML,
            SerproEnvironment::Production,
        );
        $this->assertFalse($blocked['allowed']);
        $this->assertSame('OFFICIAL_CLARIFICATION_REQUIRED', $blocked['code']?->value);

        $trial = $gate->evaluateCnpjField(
            $alpha,
            OfficialClarificationGate::CONTEXT_TERMO_XML,
            SerproEnvironment::Trial,
        );
        $this->assertTrue($trial['allowed']);

        $numeric = $gate->evaluateCnpjField(
            '11222333000181',
            OfficialClarificationGate::CONTEXT_TERMO_XML,
            SerproEnvironment::Production,
        );
        $this->assertTrue($numeric['allowed']);
    }

    public function test_demo_office_cannot_use_real_endpoint(): void
    {
        $demo = Office::factory()->create(['slug' => 'demo', 'serpro_segregation_class' => 'DEMO']);
        $guard = app(SerproProductionOnboardingGuard::class);
        $this->assertTrue($guard->isDemoOffice($demo));

        $this->expectException(\RuntimeException::class);
        $guard->assertMayUseRealEndpoint($demo, SerproEnvironment::Production);
    }

    public function test_sensitive_mutation_requires_admin_2fa_consent(): void
    {
        $office = Office::factory()->create(['slug' => 'contabil-real']);
        $operator = User::factory()->forOffice($office, OfficeRole::Operator)->create();
        $guard = app(SerproProductionOnboardingGuard::class);

        $this->expectException(\RuntimeException::class);
        $guard->assertSensitiveMutationAllowed(
            $office,
            $operator,
            SerproEnvironment::Trial,
            'termo',
            explicitConsent: true,
        );
    }

    public function test_lifecycle_scan_emits_alerts_without_mutating_powers(): void
    {
        [$office, $client, $auth] = $this->seedOfficeClientAuth();

        $auth->termo_valid_to = now()->addDays(5);
        $auth->procurador_token_expires_at = now()->addSeconds(30);
        $auth->author_cert_valid_to = now()->addDays(14);
        $auth->save();

        SerproContract::query()->where('environment', SerproEnvironment::Trial->value)->update([
            'cert_valid_to' => now()->addDays(20),
        ]);

        TaxProxyPower::query()->create([
            'office_id' => $office->id,
            'client_id' => $client->id,
            'office_serpro_authorization_id' => $auth->id,
            'environment' => SerproEnvironment::Trial->value,
            'author_identity' => $auth->author_identity,
            'contributor_cnpj' => '11222333000181',
            'system_code' => 'X',
            'power_code' => 'P1',
            'source' => TaxProxyPowerSource::IntegraProcuracoes,
            'status' => TaxProxyPowerStatus::Active,
            'valid_to' => now()->addDays(2),
            'accepted_at' => now(),
            'freshness_checked_at' => now(),
        ]);

        $result = app(SerproLifecycleMonitor::class)->scan();
        $this->assertTrue($result['lock_acquired']);
        $this->assertNotEmpty($result['alerts']);
        $kinds = array_column($result['alerts'], 'kind');
        $this->assertContains('TERMO', $kinds);
        $this->assertContains('PROXY_POWER', $kinds);

        // Powers not auto-revoked by scan
        $this->assertSame(
            TaxProxyPowerStatus::Active,
            TaxProxyPower::query()->where('power_code', 'P1')->first()->status
        );

        $exit = Artisan::call('serpro:lifecycle-scan');
        $this->assertSame(0, $exit);
    }

    /**
     * @return array{0: Office, 1: Client, 2: OfficeSerproAuthorization}
     */
    private function seedOfficeClientAuth(): array
    {
        $office = Office::factory()->create(['slug' => 'real-'.uniqid()]);
        $client = Client::factory()->forOffice($office)->create();
        Establishment::factory()->forClient($client, '11222333000181')->create();

        SerproContract::query()->create([
            'environment' => SerproEnvironment::Trial,
            'status' => SerproContractStatus::Active,
            'contractor_cnpj' => '11222333000181',
            'health_status' => 'OK',
        ]);

        $auth = OfficeSerproAuthorization::query()->create([
            'office_id' => $office->id,
            'environment' => SerproEnvironment::Trial,
            'status' => SerproAuthorizationStatus::TokenActive,
            'author_identity_type' => 'CPF',
            'author_identity' => '52998224725',
            'termo_vault_object_id' => '01ARZ3NDEKTSV4RRFFQ69G5FAV',
            'termo_valid_to' => now()->addYear(),
            'procurador_token_vault_object_id' => '01ARZ3NDEKTSV4RRFFQ69G5FAV',
            'procurador_token_expires_at' => now()->addHours(6),
        ]);

        return [$office, $client, $auth];
    }

    /**
     * @param  list<int>  $weights
     */
    private function cnpjDigit(string $base, array $weights): string
    {
        $sum = 0;
        for ($i = 0, $len = strlen($base); $i < $len; $i++) {
            $sum += (ord($base[$i]) - 48) * $weights[$i];
        }
        $mod = $sum % 11;

        return (string) ($mod < 2 ? 0 : 11 - $mod);
    }

    private function validCpf(): string
    {
        $base = '529982247';
        for ($t = 9; $t < 11; $t++) {
            $sum = 0;
            for ($i = 0; $i < $t; $i++) {
                $sum += (int) $base[$i] * (($t + 1) - $i);
            }
            $base .= (string) (((10 * $sum) % 11) % 10);
        }

        return $base;
    }
}

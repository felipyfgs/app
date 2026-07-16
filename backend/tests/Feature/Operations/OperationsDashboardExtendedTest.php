<?php

namespace Tests\Feature\Operations;

use App\Enums\FiscalFindingSeverity;
use App\Enums\FiscalMutationStatus;
use App\Enums\FiscalPendingStatus;
use App\Enums\FiscalSituation;
use App\Enums\OfficeRole;
use App\Enums\SerproAuthorizationStatus;
use App\Enums\SerproEnvironment;
use App\Enums\TaxProxyPowerSource;
use App\Enums\TaxProxyPowerStatus;
use App\Models\Client;
use App\Models\FiscalMutationOperation;
use App\Models\FiscalPendingItem;
use App\Models\Office;
use App\Models\OfficeSerproAuthorization;
use App\Models\TaxProxyPower;
use App\Models\User;
use App\Support\CurrentOffice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\ApiSecretScanner;
use Tests\TestCase;

/**
 * Tasks 14.1–14.3, 14.8: summary/inbox estendidos, saúde sanitizada, office_id forjado, secrets.
 */
class OperationsDashboardExtendedTest extends TestCase
{
    use RefreshDatabase;

    private function actingMember(Office $office, OfficeRole $role = OfficeRole::Admin): User
    {
        $user = User::factory()->forOffice($office, $role)->withTwoFactorConfirmed()->create();
        $this->actingAs($user);
        app(CurrentOffice::class)->clear();
        app(CurrentOffice::class)->resolve($user);

        return $user;
    }

    public function test_summary_inclui_blocos_fiscais_e_consumo_sem_contrato_global(): void
    {
        $office = Office::factory()->create();
        $this->actingMember($office);

        OfficeSerproAuthorization::query()->create([
            'office_id' => $office->id,
            'environment' => SerproEnvironment::Trial,
            'status' => SerproAuthorizationStatus::PendingTerm,
            'author_identity_type' => 'CPF',
            'author_identity' => '52998224725',
            'author_name' => 'Autor Teste',
            'certificate_mode' => 'EXTERNAL_SIGNATURE',
        ]);

        $data = $this->getJson('/api/v1/operations/summary')->assertOk()->json('data');

        $this->assertArrayHasKey('serpro_authorization', $data);
        $this->assertArrayHasKey('proxy_powers', $data);
        $this->assertArrayHasKey('modules', $data);
        $this->assertArrayHasKey('fiscal_pending', $data);
        $this->assertArrayHasKey('fiscal_coverage', $data);
        $this->assertArrayHasKey('usage', $data);
        $this->assertArrayHasKey('blocks', $data);
        $this->assertArrayHasKey('uncertain_results', $data);
        $this->assertArrayHasKey('platform_health', $data);
        $this->assertArrayHasKey('generated_at', $data);

        $health = $data['platform_health'];
        $this->assertArrayHasKey('available', $health);
        $this->assertArrayHasKey('status', $health);
        $this->assertArrayNotHasKey('active_contract', $health);
        $this->assertArrayNotHasKey('contracts', $health);
        $this->assertArrayNotHasKey('fingerprint_sha256', $health);
        $this->assertArrayNotHasKey('consumer_key_hint', $health);
        $this->assertArrayNotHasKey('contractor_cnpj_masked', $health);
        $this->assertArrayNotHasKey('has_pfx', $health);
        $this->assertArrayNotHasKey('fake_clients', $health);

        $usage = $data['usage'];
        $this->assertArrayNotHasKey('global_budget', $usage);
        $this->assertArrayNotHasKey('global_used', $usage);
        $this->assertArrayNotHasKey('by_tenant', $usage);
        $this->assertArrayNotHasKey('estimated_cost_micros', $usage);

        $auth = $data['serpro_authorization'];
        $this->assertTrue($auth['configured']);
        $this->assertSame(SerproAuthorizationStatus::PendingTerm->value, $auth['status']);
        $this->assertFalse($auth['has_termo']);

        $coverage = $data['fiscal_coverage'];
        $this->assertArrayHasKey('by_situation', $coverage);
        $this->assertArrayHasKey('up_to_date_full_only', $coverage);
        $this->assertStringContainsString('UNKNOWN', $coverage['note']);
    }

    public function test_inbox_termo_ausente_e_mutation_unknown_sem_retry(): void
    {
        $office = Office::factory()->create();
        $client = Client::factory()->forOffice($office)->create(['legal_name' => 'Acme Fiscal']);
        $this->actingMember($office);

        OfficeSerproAuthorization::query()->create([
            'office_id' => $office->id,
            'environment' => SerproEnvironment::Trial,
            'status' => SerproAuthorizationStatus::PendingTerm,
            'author_identity_type' => 'CPF',
            'author_identity' => '52998224725',
            'certificate_mode' => 'EXTERNAL_SIGNATURE',
        ]);

        FiscalMutationOperation::query()->create([
            'office_id' => $office->id,
            'client_id' => $client->id,
            'idempotency_key' => 'idem-'.Str::random(8),
            'logical_key' => 'log-'.Str::random(8),
            'correlation_id' => (string) Str::uuid(),
            'environment' => SerproEnvironment::Trial,
            'solution_code' => 'INTEGRA_SN',
            'service_code' => 'PGDASD',
            'operation_code' => 'RETIFICAR',
            'module_key' => 'simples_mei',
            'status' => FiscalMutationStatus::UnknownResult,
            'confirmation_required' => true,
            'confirmed_by_user' => true,
            'attempt_count' => 1,
            'reconcile_count' => 0,
            'simulated' => true,
        ]);

        FiscalPendingItem::query()->create([
            'office_id' => $office->id,
            'client_id' => $client->id,
            'code' => 'PEND_TEST',
            'title' => 'Pendência de teste',
            'detail' => 'Detalhe sanitizado',
            'severity' => FiscalFindingSeverity::High,
            'status' => FiscalPendingStatus::Open,
            'situation' => FiscalSituation::Pending,
            'logical_key' => 'pend-'.Str::random(8),
            'open_dedupe_key' => 'odk-'.Str::random(8),
        ]);

        $inbox = $this->getJson('/api/v1/operations/inbox')->assertOk();
        $types = collect($inbox->json('data'))->pluck('type');

        $this->assertTrue($types->contains('serpro_termo_missing'));
        $this->assertTrue($types->contains('mutation_unknown_result'));
        $this->assertTrue($types->contains('fiscal_pending'));

        $uncertain = collect($inbox->json('data'))->firstWhere('type', 'mutation_unknown_result');
        $this->assertSame('critical', $uncertain['severity']);
        $actionTypes = collect($uncertain['actions'] ?? [])->pluck('type');
        $this->assertTrue($actionTypes->contains('reconcile'));
        $this->assertFalse($actionTypes->contains('retry'));
        $this->assertFalse($actionTypes->contains('trigger_sync'));
    }

    public function test_office_id_forjado_no_summary_e_inbox_nao_vaza_outro_tenant(): void
    {
        $officeA = Office::factory()->create();
        $officeB = Office::factory()->create();

        $clientB = Client::factory()->forOffice($officeB)->create(['legal_name' => 'Segredo B']);

        OfficeSerproAuthorization::query()->create([
            'office_id' => $officeB->id,
            'environment' => SerproEnvironment::Trial,
            'status' => SerproAuthorizationStatus::Blocked,
            'author_identity_type' => 'CPF',
            'author_identity' => '99999999999',
            'certificate_mode' => 'EXTERNAL_SIGNATURE',
            'termo_vault_object_id' => '01ARZ3NDEKTSV4RRFFQ69G5FAV',
            'action_required_reason' => 'NUNCA_VAZAR_PARA_A',
        ]);

        FiscalPendingItem::query()->create([
            'office_id' => $officeB->id,
            'client_id' => $clientB->id,
            'code' => 'ONLY_B',
            'title' => 'Pendencia so do B',
            'detail' => 'conteudo B',
            'severity' => FiscalFindingSeverity::Critical,
            'status' => FiscalPendingStatus::Open,
            'situation' => FiscalSituation::Attention,
            'logical_key' => 'only-b',
            'open_dedupe_key' => 'only-b-open',
        ]);

        $this->actingMember($officeA);

        $summary = $this->getJson('/api/v1/operations/summary?office_id='.$officeB->id)
            ->assertOk()
            ->json('data');

        // Deve refletir A (sem auth) — não status BLOCKED de B
        $this->assertFalse($summary['serpro_authorization']['configured'] ?? true);
        $jsonSummary = json_encode($summary);
        $this->assertStringNotContainsString('NUNCA_VAZAR_PARA_A', (string) $jsonSummary);
        $this->assertStringNotContainsString('Pendencia so do B', (string) $jsonSummary);
        $this->assertStringNotContainsString('01ARZ3NDEKTSV4RRFFQ69G5FAV', (string) $jsonSummary);

        $inbox = $this->getJson('/api/v1/operations/inbox?office_id='.$officeB->id)->assertOk();
        $bodies = collect($inbox->json('data'))->pluck('body')->implode(' ');
        $titles = collect($inbox->json('data'))->pluck('title')->implode(' ');
        $this->assertStringNotContainsString('NUNCA_VAZAR_PARA_A', $bodies.$titles);
        $this->assertStringNotContainsString('Pendencia so do B', $bodies.$titles);
        $clientIds = collect($inbox->json('data'))->pluck('client_id')->filter()->unique()->values();
        $this->assertFalse($clientIds->contains($clientB->id));
    }

    public function test_varredura_automatica_de_segredos_em_summary_e_inbox(): void
    {
        $office = Office::factory()->create();
        $client = Client::factory()->forOffice($office)->create();
        $this->actingMember($office);

        OfficeSerproAuthorization::query()->create([
            'office_id' => $office->id,
            'environment' => SerproEnvironment::Trial,
            'status' => SerproAuthorizationStatus::TokenActive,
            'author_identity_type' => 'CPF',
            'author_identity' => '52998224725',
            'certificate_mode' => 'EXTERNAL_SIGNATURE',
            'termo_vault_object_id' => '01ARZ3NDEKTSV4RRFFQ69G5FAV',
            'termo_sha256' => str_repeat('a', 64),
            'termo_valid_to' => now()->addYear(),
            'procurador_token_vault_object_id' => '01ARZ3NDEKTSV4RRFFQ69G5FB0',
            'procurador_token_expires_at' => now()->addHours(2),
            'author_pfx_vault_object_id' => '01ARZ3NDEKTSV4RRFFQ69G5FB1',
        ]);

        TaxProxyPower::query()->create([
            'office_id' => $office->id,
            'client_id' => $client->id,
            'author_identity' => '52998224725',
            'contributor_cnpj' => '11222333000181',
            'system_code' => 'INTEGRA_SN',
            'service_code' => 'PGDASD',
            'power_code' => 'PGDASD',
            'source' => TaxProxyPowerSource::ManualOfficialEvidence,
            'status' => TaxProxyPowerStatus::Expired,
            'valid_from' => now()->subYear(),
            'valid_to' => now()->subDay(),
            'evidence_ref' => 'EV-1',
        ]);

        $payloads = [
            $this->getJson('/api/v1/operations/summary')->assertOk()->getContent(),
            $this->getJson('/api/v1/operations/inbox')->assertOk()->getContent(),
        ];

        foreach ($payloads as $json) {
            $this->assertNotFalse($json);
            ApiSecretScanner::assertClean((string) $json);
            $this->assertStringNotContainsString('01ARZ3NDEKTSV4RRFFQ69G5FAV', (string) $json);
            $this->assertStringNotContainsString('01ARZ3NDEKTSV4RRFFQ69G5FB0', (string) $json);
        }
    }

    public function test_proxy_power_expirada_gera_inbox_sem_instrumento(): void
    {
        $office = Office::factory()->create();
        $client = Client::factory()->forOffice($office)->create(['legal_name' => 'Cliente Proxy']);
        $this->actingMember($office);

        $auth = OfficeSerproAuthorization::query()->create([
            'office_id' => $office->id,
            'environment' => SerproEnvironment::Trial,
            'status' => SerproAuthorizationStatus::TokenActive,
            'author_identity_type' => 'CPF',
            'author_identity' => '52998224725',
            'certificate_mode' => 'EXTERNAL_SIGNATURE',
            'termo_vault_object_id' => '01ARZ3NDEKTSV4RRFFQ69G5FAV',
            'termo_valid_to' => now()->addYear(),
            'procurador_token_vault_object_id' => '01ARZ3NDEKTSV4RRFFQ69G5FB0',
            'procurador_token_expires_at' => now()->addDay(),
        ]);

        TaxProxyPower::query()->create([
            'office_id' => $office->id,
            'client_id' => $client->id,
            'office_serpro_authorization_id' => $auth->id,
            'author_identity' => '52998224725',
            'contributor_cnpj' => '11222333000181',
            'system_code' => 'INTEGRA_SN',
            'service_code' => 'PGDASD',
            'power_code' => 'PGDASD',
            'source' => TaxProxyPowerSource::ManualOfficialEvidence,
            'status' => TaxProxyPowerStatus::Expired,
            'valid_to' => now()->subDay(),
            'evidence_ref' => 'EV-EXP',
        ]);

        $items = collect($this->getJson('/api/v1/operations/inbox?type=proxy_power_expired')->assertOk()->json('data'));
        $this->assertNotEmpty($items);
        $body = $items->pluck('body')->implode(' ');
        $this->assertStringContainsString('PGDASD', $body);
        $this->assertStringNotContainsString('vault_object', strtolower($body));
        $this->assertStringNotContainsString('BEGIN ', $body);
        ApiSecretScanner::assertClean(json_encode($items->all()) ?: '');
    }
}

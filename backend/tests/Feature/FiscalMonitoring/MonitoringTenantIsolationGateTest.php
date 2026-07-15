<?php

namespace Tests\Feature\FiscalMonitoring;

use App\Enums\DctfwebTransmissionStatus;
use App\Enums\FiscalCoverage;
use App\Enums\FiscalPaymentStatus;
use App\Enums\FiscalRunStatus;
use App\Enums\FiscalSituation;
use App\Enums\FiscalTrigger;
use App\Enums\MailboxSource;
use App\Enums\MailboxTriageStatus;
use App\Enums\OfficeRole;
use App\Enums\TaxGuidePaymentStatus;
use App\Enums\TaxInstallmentModality;
use App\Models\Client;
use App\Models\DctfwebDeclaration;
use App\Models\FiscalMonitoringRun;
use App\Models\FiscalSnapshot;
use App\Models\MailboxMessage;
use App\Models\Office;
use App\Models\TaxGuide;
use App\Models\TaxInstallmentOrder;
use App\Models\User;
use App\Support\CurrentOffice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Task 1.5 — office_id forjado é ignorado; PLATFORM_ADMIN sem membership não lê fiscal.
 *
 * Cobre endpoints de leitura usados pelo monitoramento (existentes; APIs overview/clients
 * da seção 2 devem reutilizar o mesmo middleware EnsureOfficeContext).
 */
class MonitoringTenantIsolationGateTest extends TestCase
{
    use RefreshDatabase;

    private Office $officeA;

    private Office $officeB;

    private Client $clientA;

    private Client $clientB;

    private User $adminA;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'features.global_enabled' => true,
            'features.kill_switch' => false,
            'features.modules.simples_mei.enabled' => true,
            'features.modules.simples_mei.allow_all_offices' => true,
            'features.modules.dctfweb_mit.enabled' => true,
            'features.modules.dctfweb_mit.allow_all_offices' => true,
            'features.modules.parcelamentos.enabled' => true,
            'features.modules.parcelamentos.allow_all_offices' => true,
            'features.modules.sitfis.enabled' => true,
            'features.modules.sitfis.allow_all_offices' => true,
            'features.modules.mailbox.enabled' => true,
            'features.modules.mailbox.allow_all_offices' => true,
            'features.modules.declaracoes.enabled' => true,
            'features.modules.declaracoes.allow_all_offices' => true,
            'features.modules.guias.enabled' => true,
            'features.modules.guias.allow_all_offices' => true,
            'features.modules.fgts.enabled' => true,
            'features.modules.fgts.allow_all_offices' => true,
            'fiscal_monitoring.enabled' => true,
            'fiscal_monitoring.kill_switch' => false,
            'tax_guides.enabled' => true,
        ]);

        $this->officeA = Office::factory()->create(['name' => 'Office A']);
        $this->officeB = Office::factory()->create(['name' => 'Office B']);
        $this->clientA = Client::factory()->forOffice($this->officeA)->create(['legal_name' => 'Cliente A']);
        $this->clientB = Client::factory()->forOffice($this->officeB)->create(['legal_name' => 'Cliente B SEGREDO']);
        $this->adminA = User::factory()
            ->forOffice($this->officeA, OfficeRole::Admin)
            ->withTwoFactorConfirmed()
            ->create();

        $this->seedTenantBOnlyRows();
    }

    public function test_office_id_forjado_em_query_nao_altera_escopo_fiscal(): void
    {
        $this->actingAs($this->adminA);
        app(CurrentOffice::class)->clear();
        app(CurrentOffice::class)->resolve($this->adminA);

        $forged = $this->officeB->id;

        $endpoints = [
            '/api/v1/fiscal/snapshots?office_id='.$forged,
            '/api/v1/fiscal/pending-items?office_id='.$forged,
            '/api/v1/fiscal/dctfweb/declarations?office_id='.$forged,
            '/api/v1/fiscal/installments/orders?office_id='.$forged,
            '/api/v1/fiscal/mailbox/messages?office_id='.$forged,
            '/api/v1/fiscal/guides?office_id='.$forged,
            '/api/v1/fiscal/fgts/competences?office_id='.$forged,
            '/api/v1/fiscal/declarations?office_id='.$forged,
            '/api/v1/operations/summary?office_id='.$forged,
        ];

        foreach ($endpoints as $url) {
            $response = $this->getJson($url)->assertOk();
            $json = (string) json_encode($response->json());
            $this->assertStringNotContainsString(
                'Cliente B SEGREDO',
                $json,
                "Vazamento de tenant B em {$url}",
            );
            $this->assertStringNotContainsString(
                'SEGREDO-B',
                $json,
                "Vazamento de marcador B em {$url}",
            );
        }

        // Snapshot de B não aparece na listagem de A
        $snapIds = collect($this->getJson('/api/v1/fiscal/snapshots?office_id='.$forged)->json('data'))
            ->pluck('client_id')
            ->all();
        $this->assertNotContains($this->clientB->id, $snapIds);

        // Mensagens de B não listam
        $this->getJson('/api/v1/fiscal/mailbox/messages?office_id='.$forged)
            ->assertOk()
            ->assertJsonCount(0, 'data');

        // Guias de B não listam
        $this->getJson('/api/v1/fiscal/guides?office_id='.$forged)
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_office_id_forjado_em_body_de_associacao_e_ignorado_pelo_middleware(): void
    {
        $this->actingAs($this->adminA);
        app(CurrentOffice::class)->clear();
        app(CurrentOffice::class)->resolve($this->adminA);

        // Mesmo com office_id de B no body, o middleware remove o campo;
        // a associação (se categories existirem) usa office da membership.
        $category = \App\Models\FiscalCategory::query()->first();
        if ($category === null) {
            $this->markTestSkipped('FiscalCategory seed ausente neste ambiente de teste.');
        }

        $response = $this->postJson('/api/v1/fiscal/category-links', [
            'client_id' => $this->clientA->id,
            'fiscal_category_id' => $category->id,
            'office_id' => $this->officeB->id,
        ]);

        // Created ou validação de domínio — nunca grava sob office B
        $this->assertNotSame(500, $response->status());
        if ($response->status() === 201) {
            $response->assertJsonPath('data.office_id', $this->officeA->id);
            $this->assertNotSame($this->officeB->id, $response->json('data.office_id'));
        }
    }

    public function test_platform_admin_sem_membership_nao_le_endpoints_fiscais(): void
    {
        $platform = User::factory()->asPlatformAdmin()->create();
        $this->assertTrue($platform->isPlatformAdmin());
        $this->assertNull($platform->activeMembership());

        $this->actingAs($platform);
        app(CurrentOffice::class)->clear();

        $fiscalGets = [
            '/api/v1/fiscal/snapshots',
            '/api/v1/fiscal/pending-items',
            '/api/v1/fiscal/findings',
            '/api/v1/fiscal/dctfweb/declarations',
            '/api/v1/fiscal/installments/orders',
            '/api/v1/fiscal/installments/modalities',
            '/api/v1/fiscal/sitfis?client_id='.$this->clientB->id,
            '/api/v1/fiscal/mailbox/messages',
            '/api/v1/fiscal/declarations',
            '/api/v1/fiscal/guides',
            '/api/v1/fiscal/fgts/coverage',
            '/api/v1/fiscal/fgts/competences',
            '/api/v1/fiscal/simples-mei/catalog',
            '/api/v1/operations/summary',
            '/api/v1/clients',
        ];

        foreach ($fiscalGets as $url) {
            $this->getJson($url)
                ->assertForbidden()
                ->assertJsonPath('message', 'Usuário sem escritório ativo.');
        }

        // Platform routes continuam acessíveis
        $this->getJson('/api/v1/platform/tenants')->assertOk();
    }

    private function seedTenantBOnlyRows(): void
    {
        $runB = FiscalMonitoringRun::query()->create([
            'office_id' => $this->officeB->id,
            'client_id' => $this->clientB->id,
            'system_code' => 'SEGREDO-B',
            'service_code' => 'X',
            'operation_code' => 'Y',
            'trigger' => FiscalTrigger::Manual,
            'idempotency_key' => 'segredo-b-run-1',
            'status' => FiscalRunStatus::Completed,
            'situation' => FiscalSituation::Error,
            'coverage' => FiscalCoverage::Unknown,
        ]);

        FiscalSnapshot::query()->create([
            'office_id' => $this->officeB->id,
            'run_id' => $runB->id,
            'client_id' => $this->clientB->id,
            'system_code' => 'SEGREDO-B',
            'service_code' => 'X',
            'operation_code' => 'Y',
            'situation' => FiscalSituation::Error,
            'coverage' => FiscalCoverage::Unknown,
            'version' => 1,
            'is_current' => true,
            'normalized' => ['marker' => 'SEGREDO-B'],
            'observed_at' => now(),
            'created_at' => now(),
        ]);

        DctfwebDeclaration::query()->create([
            'office_id' => $this->officeB->id,
            'client_id' => $this->clientB->id,
            'period_key' => '2026-01',
            'declaration_type' => 'MENSAL',
            'transmission_status' => DctfwebTransmissionStatus::Pending,
            'situation' => FiscalSituation::Error,
            'coverage' => FiscalCoverage::Unknown,
            'receipt_number' => 'SEGREDO-B-RECEIPT',
            'payment_status' => FiscalPaymentStatus::Unknown,
            'evidence_version' => 0,
        ]);

        TaxInstallmentOrder::query()->create([
            'office_id' => $this->officeB->id,
            'client_id' => $this->clientB->id,
            'modality' => TaxInstallmentModality::Parcmei,
            'regime' => 'MEI',
            'external_order_id' => 'SEGREDO-B-ORD',
            'situation' => 'ACTIVE',
            'parcel_count' => 1,
            'total_amount_cents' => 1,
            'source_system' => 'SEGREDO-B',
            'source_service' => 'PARCMEI',
            'observed_at' => now(),
        ]);

        MailboxMessage::query()->create([
            'office_id' => $this->officeB->id,
            'client_id' => $this->clientB->id,
            'external_id' => 'msg-b-secret',
            'message_hash' => hash('sha256', 'secret-b'),
            'source' => MailboxSource::CaixaPostal,
            'subject_preview' => 'SEGREDO-B mensagem',
            'received_at_official' => now(),
            'official_read_indicator' => false,
            'triage_status' => MailboxTriageStatus::New,
            'has_body' => false,
            'attachment_count' => 0,
        ]);

        TaxGuide::query()->create([
            'office_id' => $this->officeB->id,
            'client_id' => $this->clientB->id,
            'system_code' => 'SEGREDO-B',
            'service_code' => 'X',
            'operation_code' => 'Y',
            'competence_period_key' => '2026-01',
            'logical_key' => 'guide-segredo-b',
            'payment_status' => TaxGuidePaymentStatus::Unknown,
            'amount_cents' => 999,
            'currency' => 'BRL',
        ]);
    }
}

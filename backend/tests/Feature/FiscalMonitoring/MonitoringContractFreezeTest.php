<?php

namespace Tests\Feature\FiscalMonitoring;

use App\Enums\DctfwebTransmissionStatus;
use App\Enums\FiscalCoverage;
use App\Enums\FiscalFindingSeverity;
use App\Enums\FiscalPaymentStatus;
use App\Enums\FiscalPendingStatus;
use App\Enums\FiscalRunStatus;
use App\Enums\FiscalSituation;
use App\Enums\FiscalTrigger;
use App\Enums\FgtsIndependentState;
use App\Enums\MailboxSource;
use App\Enums\MailboxTriageStatus;
use App\Enums\OfficeRole;
use App\Enums\TaxGuidePaymentStatus;
use App\Enums\TaxInstallmentModality;
use App\Models\Client;
use App\Models\DctfwebDeclaration;
use App\Models\FgtsCompetenceStatus;
use App\Models\FiscalMonitoringRun;
use App\Models\FiscalPendingItem;
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
 * Task 1.2 — congela envelopes JSON dos endpoints fiscais consumidos por /monitoring.
 *
 * Objetivo: falhar se nomes de campos ou formato de paginação mudarem sem atualizar
 * o frontend e a matriz de contratos. Não valida regras de negócio profundas.
 */
class MonitoringContractFreezeTest extends TestCase
{
    use RefreshDatabase;

    private Office $office;

    private Client $client;

    private User $admin;

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

        $this->office = Office::factory()->create();
        $this->client = Client::factory()->forOffice($this->office)->create();
        $this->admin = User::factory()
            ->forOffice($this->office, OfficeRole::Admin)
            ->withTwoFactorConfirmed()
            ->create();

        $this->actingAs($this->admin);
        app(CurrentOffice::class)->clear();
        app(CurrentOffice::class)->resolve($this->admin);

        $this->seedContractRows();
    }

    public function test_me_envelope(): void
    {
        $this->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'name',
                    'email',
                    'two_factor_confirmed',
                    'two_factor_required',
                    'requires_two_factor_setup',
                    'is_platform_admin',
                    'office' => ['id', 'name', 'slug'],
                    'role',
                    'memberships',
                ],
            ]);
    }

    public function test_operations_summary_envelope(): void
    {
        $this->getJson('/api/v1/operations/summary')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'clients',
                    'establishments',
                    'notes',
                    'exports_ready',
                    'exports_pending',
                    'sync_due',
                    'sync_blocked',
                    'sync_failures_24h',
                    'credentials_expiring_30d',
                    'inbox_critical',
                    'inbox_high',
                    'inbox_total',
                    'backup',
                    'svrs_nfce',
                    'serpro_authorization' => [
                        'configured',
                        'status',
                        'actions_required',
                        'has_termo',
                        'has_procurador_token',
                    ],
                    'proxy_powers',
                    'modules',
                    'fiscal_pending',
                    'fiscal_coverage',
                    'usage',
                    'subscription',
                    'blocks',
                    'uncertain_results',
                    'platform_health',
                    'guides_due_7d',
                    'generated_at',
                ],
            ]);
    }

    public function test_simples_mei_catalog_envelope(): void
    {
        $this->getJson('/api/v1/fiscal/simples-mei/catalog')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    ['system_code', 'service_code', 'operation_code', 'mutability'],
                ],
                'module',
                'module_enabled',
                'mutating_enabled',
            ]);
    }

    public function test_snapshots_list_envelope_campos_reais(): void
    {
        // Paginação via response()->json(LengthAwarePaginator) → envelope flat
        // (data + current_page/per_page/total/links no root; NÃO aninha em meta).
        $this->getJson('/api/v1/fiscal/snapshots?current_only=1&per_page=10')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [[
                    'id',
                    'office_id',
                    'run_id',
                    'client_id',
                    'competence_id',
                    'evidence_artifact_id',
                    'system_code',
                    'service_code',
                    'operation_code',
                    'situation',
                    'coverage',
                    'version',
                    'is_current',
                    'normalized',
                    'observed_at',
                    'created_at',
                ]],
                'current_page',
                'per_page',
                'total',
                'links',
            ])
            ->assertJsonPath('data.0.situation', FiscalSituation::Attention->value)
            ->assertJsonPath('data.0.coverage', FiscalCoverage::Partial->value);
    }

    public function test_pending_and_findings_envelopes(): void
    {
        $this->getJson('/api/v1/fiscal/pending-items?per_page=10&status=OPEN')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [[
                    'id',
                    'office_id',
                    'client_id',
                    'code',
                    'title',
                    'detail',
                    'severity',
                    'status',
                    'situation',
                    'due_at',
                    'logical_key',
                    'created_at',
                ]],
                'current_page',
                'total',
            ]);

        $this->getJson('/api/v1/fiscal/findings?per_page=10&active_only=1')
            ->assertOk()
            ->assertJsonStructure([
                'data',
                'current_page',
                'total',
            ]);
    }

    public function test_dctfweb_declarations_envelope(): void
    {
        $this->getJson('/api/v1/fiscal/dctfweb/declarations?per_page=10')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [[
                    'id',
                    'office_id',
                    'client_id',
                    'competence_id',
                    'period_key',
                    'declaration_type',
                    'transmission_status',
                    'situation',
                    'coverage',
                    'receipt_number',
                    'transmitted_at',
                    'official_at',
                    'evidence_version',
                    'payment_status',
                    'current_snapshot_id',
                ]],
                'current_page',
                'total',
            ]);
    }

    public function test_installments_orders_and_modalities_envelope(): void
    {
        $this->getJson('/api/v1/fiscal/installments/modalities')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    ['code', 'label'],
                ],
            ]);

        $this->getJson('/api/v1/fiscal/installments/orders?per_page=10')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [[
                    'id',
                    'office_id',
                    'client_id',
                    'modality',
                    'regime',
                    'external_order_id',
                    'situation',
                    'source_status',
                    'requested_at',
                    'consolidated_at',
                    'parcel_count',
                    'total_amount_cents',
                    'source_system',
                    'source_service',
                    'observed_at',
                    'created_at',
                ]],
                'current_page',
                'total',
            ]);
    }

    public function test_sitfis_show_envelope_sem_certidao(): void
    {
        $this->getJson('/api/v1/fiscal/sitfis?client_id='.$this->client->id)
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'snapshot',
                    'age_seconds',
                    'observed_at',
                    'expires_at',
                    'ttl_seconds',
                    'is_within_ttl',
                    'is_negative_certificate',
                    'disclaimer',
                    'active_run',
                    'cache_key_hint',
                ],
            ])
            ->assertJsonPath('data.is_negative_certificate', false);
    }

    public function test_mailbox_messages_envelope(): void
    {
        $this->getJson('/api/v1/fiscal/mailbox/messages?per_page=10')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [[
                    'id',
                    'office_id',
                    'client_id',
                    'external_id',
                    'source',
                    'sensitivity_class',
                    'category_code',
                    'category_label',
                    'sender_code',
                    'sender_label',
                    'subject_preview',
                    'received_at_official',
                    'due_at',
                    'severity_hint',
                    'official_read_indicator',
                    'triage_status',
                    'has_body',
                    'attachment_count',
                    'body_byte_size',
                    'created_at',
                    'updated_at',
                ]],
                'current_page',
                'total',
            ]);
    }

    public function test_declarations_list_and_summary_envelope(): void
    {
        $this->getJson('/api/v1/fiscal/declarations?per_page=10')
            ->assertOk()
            ->assertJsonStructure([
                'data',
                'current_page',
                'total',
            ]);

        $this->getJson('/api/v1/fiscal/declarations/summary')
            ->assertOk()
            ->assertJsonStructure([
                'data',
            ]);

        $this->getJson('/api/v1/fiscal/declarations/catalog')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'obligations',
                    'calendar',
                ],
            ]);
    }

    public function test_guides_list_envelope_amount_cents_payment_status(): void
    {
        $this->getJson('/api/v1/fiscal/guides?per_page=10')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [[
                    'id',
                    'office_id',
                    'client_id',
                    'establishment_id',
                    'system_code',
                    'service_code',
                    'operation_code',
                    'competence_period_key',
                    'debit_ref',
                    'logical_key',
                    'payment_status',
                    'payment_confirmed_at',
                    'payment_source',
                    'amount_cents',
                    'currency',
                    'due_at',
                    'identifier_code',
                    'current_version_id',
                    'current_version',
                    'created_at',
                ]],
                'current_page',
                'total',
            ])
            ->assertJsonPath('data.0.amount_cents', 15000)
            ->assertJsonPath('data.0.payment_status', TaxGuidePaymentStatus::NotConfirmed->value);

        $payload = $this->getJson('/api/v1/fiscal/guides?per_page=10')->json('data.0');
        $this->assertArrayNotHasKey('amount', $payload);
        $this->assertArrayNotHasKey('status', $payload);
    }

    public function test_fgts_coverage_and_competences_envelope(): void
    {
        $this->getJson('/api/v1/fiscal/fgts/coverage')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'module',
                    'coverage',
                    'coverage_label',
                    'system_code',
                    'service_code',
                    'supported_events',
                    'independent_states' => [
                        'closure',
                        'totalization',
                        'guide',
                        'payment',
                    ],
                    'limitations',
                    'declares_fgts_digital_debt',
                    'scraping_allowed',
                    'portal_fallback',
                    'totalizer_absence_window_hours',
                ],
            ])
            ->assertJsonPath('data.declares_fgts_digital_debt', false)
            ->assertJsonPath('data.scraping_allowed', false);

        $this->getJson('/api/v1/fiscal/fgts/competences?per_page=10')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [[
                    'id',
                    'office_id',
                    'client_id',
                    'establishment_id',
                    'competence_period_key',
                    'closure_status',
                    'closure_status_label',
                    'totalization_status',
                    'totalization_status_label',
                    'guide_status',
                    'guide_status_label',
                    'payment_status',
                    'payment_status_label',
                    'coverage',
                    'situation',
                    'partial_coverage',
                    'declares_fgts_digital_debt',
                    'limitations',
                ]],
                'current_page',
                'total',
            ])
            ->assertJsonPath('data.0.guide_status', FgtsIndependentState::Unsupported->value)
            ->assertJsonPath('data.0.payment_status', FgtsIndependentState::Unsupported->value)
            ->assertJsonPath('data.0.partial_coverage', true);
    }

    private function seedContractRows(): void
    {
        $run = FiscalMonitoringRun::query()->create([
            'office_id' => $this->office->id,
            'client_id' => $this->client->id,
            'system_code' => 'INTEGRA_SIMPLES',
            'service_code' => 'PGDAS',
            'operation_code' => 'CONSULTAR',
            'trigger' => FiscalTrigger::Manual,
            'idempotency_key' => 'contract-freeze-run-1',
            'status' => FiscalRunStatus::Completed,
            'situation' => FiscalSituation::Attention,
            'coverage' => FiscalCoverage::Partial,
        ]);

        FiscalSnapshot::query()->create([
            'office_id' => $this->office->id,
            'run_id' => $run->id,
            'client_id' => $this->client->id,
            'competence_id' => null,
            'evidence_artifact_id' => null,
            'system_code' => 'INTEGRA_SIMPLES',
            'service_code' => 'PGDAS',
            'operation_code' => 'CONSULTAR',
            'situation' => FiscalSituation::Attention,
            'coverage' => FiscalCoverage::Partial,
            'version' => 1,
            'is_current' => true,
            'normalized' => ['demo' => true],
            'observed_at' => now(),
            'created_at' => now(),
        ]);

        FiscalPendingItem::query()->create([
            'office_id' => $this->office->id,
            'client_id' => $this->client->id,
            'code' => 'CONTRACT_FREEZE_PENDING',
            'title' => 'Pendência contrato',
            'detail' => 'Fixture de contrato',
            'severity' => FiscalFindingSeverity::Medium,
            'status' => FiscalPendingStatus::Open,
            'situation' => FiscalSituation::Pending,
            'logical_key' => 'contract-freeze-pending-1',
            'open_dedupe_key' => 'contract-freeze-pending-1-open',
        ]);

        DctfwebDeclaration::query()->create([
            'office_id' => $this->office->id,
            'client_id' => $this->client->id,
            'competence_id' => null,
            'period_key' => '2026-06',
            'declaration_type' => 'MENSAL',
            'transmission_status' => DctfwebTransmissionStatus::Pending,
            'situation' => FiscalSituation::Pending,
            'coverage' => FiscalCoverage::Partial,
            'receipt_number' => null,
            'payment_status' => FiscalPaymentStatus::Unknown,
            'evidence_version' => 0,
        ]);

        TaxInstallmentOrder::query()->create([
            'office_id' => $this->office->id,
            'client_id' => $this->client->id,
            'modality' => TaxInstallmentModality::Parcsn,
            'regime' => 'SN',
            'external_order_id' => 'ORD-CONTRACT-1',
            'situation' => 'ACTIVE',
            'source_status' => 'OK',
            'parcel_count' => 12,
            'total_amount_cents' => 120000,
            'source_system' => 'INTEGRA_PARCELAMENTO',
            'source_service' => 'PARCSN',
            'observed_at' => now(),
        ]);

        MailboxMessage::query()->create([
            'office_id' => $this->office->id,
            'client_id' => $this->client->id,
            'external_id' => 'msg-contract-1',
            'message_hash' => hash('sha256', 'contract-msg-1'),
            'source' => MailboxSource::CaixaPostal,
            'sensitivity_class' => 'NORMAL',
            'category_code' => 'AVISO',
            'category_label' => 'Aviso',
            'sender_code' => 'RFB',
            'sender_label' => 'Receita Federal',
            'subject_preview' => 'Assunto demonstrativo contrato',
            'received_at_official' => now()->subDay(),
            'severity_hint' => 'MEDIUM',
            'official_read_indicator' => false,
            'triage_status' => MailboxTriageStatus::New,
            'has_body' => false,
            'attachment_count' => 0,
            'body_byte_size' => 0,
        ]);

        TaxGuide::query()->create([
            'office_id' => $this->office->id,
            'client_id' => $this->client->id,
            'system_code' => 'INTEGRA_PAGAMENTO',
            'service_code' => 'SICALC',
            'operation_code' => 'EMITIR_GUIA',
            'competence_period_key' => '2026-06',
            'debit_ref' => 'DEB-CONTRACT-1',
            'logical_key' => 'guide-contract-1',
            'payment_status' => TaxGuidePaymentStatus::NotConfirmed,
            'amount_cents' => 15000,
            'currency' => 'BRL',
            'due_at' => now()->addDays(7),
        ]);

        FgtsCompetenceStatus::query()->create([
            'office_id' => $this->office->id,
            'client_id' => $this->client->id,
            'competence_period_key' => '2026-06',
            'closure_status' => FgtsIndependentState::Unknown,
            'totalization_status' => FgtsIndependentState::Unknown,
            'guide_status' => FgtsIndependentState::Unsupported,
            'payment_status' => FgtsIndependentState::Unsupported,
            'coverage' => FiscalCoverage::Partial,
            'situation' => FiscalSituation::Unknown,
            'limitations' => ['FGTS Digital portal sem API M2M'],
            'last_synced_at' => now(),
        ]);
    }
}

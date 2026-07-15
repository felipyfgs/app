<?php

namespace Tests\Feature\Sefaz;

use App\Contracts\SecureObjectStore;
use App\Enums\AdnDocumentType;
use App\Enums\CaptureChannel;
use App\Enums\DocumentAcquisitionSource;
use App\Enums\DocumentDirection;
use App\Enums\FiscalRole;
use App\Enums\OfficeRole;
use App\Enums\QuarantineReason;
use App\Enums\QuarantineResolutionStatus;
use App\Enums\SyncCursorStatus;
use App\Models\ChannelSyncCursor;
use App\Models\Client;
use App\Models\CteDocument;
use App\Models\CteEvent;
use App\Models\DfeDocument;
use App\Models\Establishment;
use App\Models\FiscalDocumentQuarantine;
use App\Models\Office;
use App\Models\User;
use App\Support\CurrentOffice;
use App\Support\LogSanitizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ApiSecretScanner;
use Tests\TestCase;

class CteObservabilityAndReprocessTest extends TestCase
{
    use RefreshDatabase;

    public function test_readiness_e_inspect_sem_rede_e_sem_segredo(): void
    {
        $this->artisan('sefaz:cte-readiness', ['--json' => true])
            ->assertSuccessful();

        $office = Office::factory()->create();
        $client = Client::factory()->forOffice($office)->create();
        $est = Establishment::factory()->forClient($client)->create();
        ChannelSyncCursor::query()->create([
            'office_id' => $office->id,
            'establishment_id' => $est->id,
            'environment' => 'production',
            'source' => 'SEFAZ',
            'channel' => CaptureChannel::CteDistDfe,
            'last_nsu' => 5,
            'status' => SyncCursorStatus::Idle,
        ]);

        $this->artisan('sefaz:cte-inspect', ['--office' => $office->id, '--json' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('client_streams');
    }

    public function test_reconcile_orphans_batch_e_reprocess_dry_run(): void
    {
        $office = Office::factory()->create();
        $xml = '<cteProc><chCTe>35260711222333000181570010000000011234567999</chCTe></cteProc>';
        $sha = hash('sha256', $xml);
        $store = app(SecureObjectStore::class);
        $objectId = $store->put($xml, ['office_id' => $office->id, 'sha256' => $sha]);

        $dfe = DfeDocument::query()->create([
            'office_id' => $office->id,
            'sha256' => $sha,
            'document_type' => AdnDocumentType::Cte,
            'schema_version' => 'procCTe_v4.00.xsd',
            'access_key' => '35260711222333000181570010000000011234567999',
            'vault_object_id' => $objectId,
            'byte_size' => strlen($xml),
            'parse_status' => 'OK',
        ]);
        $cte = CteDocument::query()->create([
            'office_id' => $office->id,
            'dfe_document_id' => $dfe->id,
            'access_key' => '35260711222333000181570010000000011234567999',
            'number' => '1',
            'model' => '57',
            'issuer_cnpj' => '11222333000181',
            'fiscal_role' => FiscalRole::Taker,
            'direction' => DocumentDirection::In,
            'issued_at' => '2026-07-01',
            'status' => 'ACTIVE',
            'is_summary' => false,
        ]);
        $eventDfe = DfeDocument::query()->create([
            'office_id' => $office->id,
            'sha256' => str_repeat('c', 64),
            'document_type' => 'UNKNOWN',
            'schema_version' => 'procEventoCTe',
            'access_key' => $cte->access_key,
            'vault_object_id' => 'evt-obj',
            'byte_size' => 10,
            'parse_status' => 'OK',
        ]);
        CteEvent::query()->create([
            'office_id' => $office->id,
            'dfe_document_id' => $eventDfe->id,
            'cte_document_id' => null,
            'access_key' => $cte->access_key,
            'event_type' => '110111',
            'sequence' => 1,
            'status' => 'CANCELLED',
        ]);
        FiscalDocumentQuarantine::query()->create([
            'office_id' => $office->id,
            'sha256' => str_repeat('d', 64),
            'vault_object_id' => 'q-obj',
            'byte_size' => 10,
            'access_key' => $cte->access_key,
            'issuer_cnpj' => '11222333000181',
            'model' => '57',
            'schema_family' => 'cteProc',
            'reason' => QuarantineReason::PendingImport,
            'source' => DocumentAcquisitionSource::ManualXml,
            'resolution_status' => QuarantineResolutionStatus::Open,
        ]);

        $this->artisan('cte:reconcile-orphans', ['--office' => $office->id])
            ->assertSuccessful();

        $this->assertNotNull(CteEvent::query()->where('access_key', $cte->access_key)->first()->cte_document_id);
        $this->assertSame(
            QuarantineResolutionStatus::Resolved,
            FiscalDocumentQuarantine::query()->where('access_key', $cte->access_key)->first()->resolution_status
        );

        $this->artisan('cte:reprocess-projections', [
            '--office' => $office->id,
            '--dry-run' => true,
        ])->assertSuccessful();
    }

    public function test_health_onboarding_e_logs_sem_vazamento_de_segredo(): void
    {
        $office = Office::factory()->create();
        $user = User::factory()->forOffice($office, OfficeRole::Admin)->withTwoFactorConfirmed()->create();
        $this->actingAs($user);
        app(CurrentOffice::class)->resolve($user);

        $onboarding = $this->getJson('/api/v1/cte/onboarding')->assertOk();
        ApiSecretScanner::assertClean((string) $onboarding->getContent(), 'cte.onboarding');

        $health = $this->getJson('/api/v1/cte/health')->assertOk();
        ApiSecretScanner::assertClean((string) $health->getContent(), 'cte.health');

        $leaky = LogSanitizer::redact([
            'xml' => '<cteProc>SECRET</cteProc>',
            'pfx' => 'BINARY',
            'password' => 'secret',
            'channel' => CaptureChannel::CteDistDfe->value,
            'cstat' => '138',
        ]);
        $this->assertSame('[redacted]', $leaky['xml']);
        $this->assertSame('[redacted]', $leaky['pfx']);
        $this->assertSame(CaptureChannel::CteDistDfe->value, $leaky['channel']);

        $labels = LogSanitizer::metricLabels([
            'channel' => 'CTE_DISTDFE',
            'cstat' => '656',
            'quality' => 'ORIGINAL',
            'access_key' => '35260711222333000181570010000000011234567890',
            'cnpj' => '11222333000181',
        ]);
        $this->assertArrayHasKey('cstat', $labels);
        $this->assertArrayHasKey('quality', $labels);
        $this->assertArrayNotHasKey('access_key', $labels);
        $this->assertArrayNotHasKey('cnpj', $labels);
    }

    public function test_repair_command_recusa_quiet_period(): void
    {
        $office = Office::factory()->create();
        $client = Client::factory()->forOffice($office)->create();
        $est = Establishment::factory()->forClient($client)->create();
        $cursor = ChannelSyncCursor::query()->create([
            'office_id' => $office->id,
            'establishment_id' => $est->id,
            'environment' => 'production',
            'source' => 'SEFAZ',
            'channel' => CaptureChannel::CteDistDfe,
            'last_nsu' => 10,
            'status' => SyncCursorStatus::Idle,
            'next_sync_at' => now()->addHour(),
        ]);

        $this->artisan('sefaz:cte-repair-nsu', [
            'cursor' => $cursor->id,
            'nsu' => 5,
        ])->assertFailed();
    }
}

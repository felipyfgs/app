<?php

namespace Tests\Unit\Fiscal\SimplesMei\Pgdasd;

use App\Models\Client;
use App\Models\FiscalMonitoringRun;
use App\Models\Office;
use App\Services\Fiscal\Declarations\TaxObligationCatalogService;
use App\Services\Fiscal\Declarations\TaxObligationProjectionService;
use App\Services\Fiscal\SimplesMei\Pgdasd\PgdasdDocumentSanitizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PgdasdDocumentPersistenceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function replay_reuses_evidence_and_artifacts_without_persisting_base64(): void
    {
        $office = Office::factory()->create();
        $client = Client::factory()->forOffice($office)->create(['tax_regime' => 'SIMPLES_NACIONAL']);
        $obligation = app(TaxObligationCatalogService::class)->findByCode('PGDAS_D');
        $projection = app(TaxObligationProjectionService::class)->project(
            $office,
            $client,
            $obligation,
            '2026-06',
        );
        $run = FiscalMonitoringRun::query()->create([
            'office_id' => $office->id,
            'client_id' => $client->id,
            'system_code' => 'INTEGRA_SN',
            'service_code' => 'PGDASD',
            'operation_code' => 'CONSULTAR_ULTIMA_DECLARACAO_RECIBO',
            'operation_key' => 'pgdasd.consultimadecrec',
            'source_provenance' => 'SERPRO_REAL',
            'trigger' => 'MANUAL',
            'idempotency_key' => 'document-replay-test',
            'status' => 'QUEUED',
            'situation' => 'UNKNOWN',
            'coverage' => 'FULL',
            'mutability' => 'READ_ONLY',
        ]);
        $fixturePath = dirname(__DIR__, 4).'/fixtures/serpro/pgdasd/14.json';
        $fixture = json_decode((string) file_get_contents($fixturePath), true, flags: JSON_THROW_ON_ERROR);
        $sanitizer = app(PgdasdDocumentSanitizer::class);

        $first = $sanitizer->sanitizeAndStore(
            $run,
            (int) $client->id,
            $fixture['response_dados'],
            'pgdasd.consultimadecrec',
            (int) $projection->id,
            '2026-06',
            '202606',
        );
        $replay = $sanitizer->sanitizeAndStore(
            $run,
            (int) $client->id,
            $fixture['response_dados'],
            'pgdasd.consultimadecrec',
            (int) $projection->id,
            '2026-06',
            '202606',
        );

        $this->assertCount(4, $first['artifacts']);
        $this->assertCount(4, $replay['artifacts']);
        $this->assertDatabaseCount('fiscal_evidence_artifacts', 1);
        $this->assertDatabaseCount('pgdasd_artifacts', 4);
        $this->assertSame(
            array_map(static fn ($artifact): int => (int) $artifact->id, $first['artifacts']),
            array_map(static fn ($artifact): int => (int) $artifact->id, $replay['artifacts']),
        );
        $serialized = json_encode($replay['sanitized_dados'], JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('JVBER', $serialized);
        $this->assertStringNotContainsString('content_sha256', $serialized);
        $this->assertStringNotContainsString('evidence_artifact_id', $serialized);
    }
}

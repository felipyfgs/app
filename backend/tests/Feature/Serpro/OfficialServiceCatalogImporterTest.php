<?php

namespace Tests\Feature\Serpro;

use App\Services\Serpro\Catalog\OfficialServiceCatalogImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class OfficialServiceCatalogImporterTest extends TestCase
{
    use RefreshDatabase;

    public function test_imports_official_snapshot_and_versions_canonical_catalog_idempotently(): void
    {
        $importer = app(OfficialServiceCatalogImporter::class);

        $first = $importer->import();
        $this->assertTrue($first['valid'], implode('; ', $first['errors']));
        $this->assertSame(119, $first['imported']);

        $this->assertSame(
            119,
            DB::table('serpro_service_catalog_entries')
                ->where('catalog_version', 20260716)
                ->count(),
        );
        $activeOfficial = DB::table('serpro_operation_versions')
            ->where('source_catalog', 'official_manifest')
            ->whereNull('effective_to')
            ->count();
        $missing = DB::table('serpro_service_catalog_entries as catalog')
            ->leftJoin('serpro_operations as operation', 'operation.operation_key', '=', 'catalog.operation_key')
            ->leftJoin('serpro_operation_versions as version', function ($join): void {
                $join->on('version.serpro_operation_id', '=', 'operation.id')
                    ->where('version.source_catalog', '=', 'official_manifest')
                    ->whereNull('version.effective_to');
            })
            ->where('catalog.catalog_version', 20260716)
            ->whereNull('version.id')
            ->pluck('catalog.operation_key')
            ->all();
        $this->assertSame(119, $activeOfficial, 'Versões sem vigência: '.implode(', ', $missing));

        $sitfis = DB::table('serpro_operation_versions')
            ->where('id_sistema', 'SITFIS')
            ->where('id_servico', 'SOLICITARPROTOCOLO91')
            ->where('source_catalog', 'official_manifest')
            ->first();
        $this->assertNotNull($sitfis);
        $this->assertSame('PROTOCOL_POLLING', $sitfis->async_policy);
        $this->assertSame('PRODUCTION', $sitfis->official_state);
        $this->assertSame(['00002'], json_decode((string) $sitfis->required_proxy_powers, true));

        $second = $importer->import();
        $this->assertTrue($second['valid']);
        $this->assertSame(0, $second['imported']);
        $this->assertSame(119, $second['skipped']);
    }
}

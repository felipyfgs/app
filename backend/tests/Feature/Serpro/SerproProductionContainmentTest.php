<?php

namespace Tests\Feature\Serpro;

use App\Enums\SerproContractStatus;
use App\Enums\SerproCredentialVersionStatus;
use App\Enums\SerproDataSegregationClass;
use App\Enums\SerproEnvironment;
use App\Enums\SerproFunctionalRoute;
use App\Models\Office;
use App\Models\SerproContract;
use App\Models\SerproCredentialVersion;
use App\Models\SerproDocumentSnapshot;
use App\Models\SerproExternalGate;
use App\Services\Serpro\SerproCredentialVersionService;
use App\Services\Serpro\SerproDemoInventoryService;
use App\Services\Serpro\SerproDocumentRegistry;
use App\Services\Serpro\SerproExternalGateService;
use App\Services\Serpro\SerproProductionEgressGate;
use App\Services\Serpro\SerproReadinessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class SerproProductionContainmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_prod_check_blocks_when_exposed_credential_not_terminal(): void
    {
        $contract = SerproContract::query()->create([
            'environment' => SerproEnvironment::Production,
            'status' => SerproContractStatus::Active,
            'contractor_cnpj' => '11222333000181',
            'contractor_name' => 'SH',
            'credentials_exposed' => true,
            'segregation_class' => SerproDataSegregationClass::HistoricalUnverified->value,
            'health_status' => 'OK',
        ]);

        $version = app(SerproCredentialVersionService::class)
            ->markContractCredentialsExposed($contract, 'teste exposição');

        $this->assertTrue($version->was_exposed);
        $this->assertSame(SerproCredentialVersionStatus::Active, $version->status);
        $this->assertTrue($version->blocksBillableEgress());

        $gate = app(SerproProductionEgressGate::class);
        $eval = $gate->evaluateBillableEgress(
            route: SerproFunctionalRoute::Consultar,
            environment: SerproEnvironment::Production,
        );

        $this->assertFalse($eval['allowed']);
        $this->assertSame('EXPOSED_CREDENTIALS', $eval['code']);

        $this->artisan('serpro:prod-check', ['--serpro-env' => 'PRODUCTION'])
            ->assertFailed();
    }

    public function test_prod_check_passes_after_exposed_credential_compromised(): void
    {
        $contract = SerproContract::query()->create([
            'environment' => SerproEnvironment::Trial,
            'status' => SerproContractStatus::Active,
            'contractor_cnpj' => '11222333000181',
            'credentials_exposed' => true,
            'health_status' => 'OK',
        ]);

        $versions = app(SerproCredentialVersionService::class);
        $version = $versions->markContractCredentialsExposed($contract, 'exposta');
        $versions->markCompromised($version, 'rotacionada');

        $this->assertFalse($version->fresh()->blocksBillableEgress());

        // Contrato ainda com flag: limpar após rotação simulada
        $contract->forceFill(['credentials_exposed' => false])->save();

        $this->artisan('serpro:prod-check', ['--serpro-env' => 'TRIAL'])
            ->assertSuccessful();
    }

    public function test_prod_check_allows_explicit_containment_without_exposed_credentials(): void
    {
        config([
            'serpro.kill_switch' => true,
            'serpro.prod_check_strict' => true,
        ]);

        $this->artisan('serpro:prod-check', ['--serpro-env' => 'PRODUCTION'])
            ->assertFailed();

        $this->artisan('serpro:prod-check', [
            '--serpro-env' => 'PRODUCTION',
            '--allow-containment' => true,
        ])->assertSuccessful();
    }

    public function test_document_registry_syncs_official_sources(): void
    {
        $registry = app(SerproDocumentRegistry::class);
        $result = $registry->syncFromManifest();

        $this->assertSame(8, $result['created']);
        $this->assertSame(8, $result['total']);
        $this->assertSame($result['total'], SerproDocumentSnapshot::query()->count());
        $this->assertDatabaseMissing('serpro_document_snapshots', [
            'source_key' => 'cnpj_alphanumeric_rfb',
        ]);
        $this->assertDatabaseMissing('serpro_document_snapshots', [
            'source_key' => 'tls_chain_validation',
        ]);
        $this->assertDatabaseMissing('serpro_document_snapshots', [
            'source_key' => 'oauth_portal_curl_divergence',
        ]);

        $again = $registry->syncFromManifest();
        $this->assertSame(0, $again['created']);
        $this->assertSame(8, $again['existing']);
    }

    public function test_prod_check_fails_on_invalid_registry_without_partial_snapshot_write(): void
    {
        $path = sys_get_temp_dir().'/serpro-sources-'.bin2hex(random_bytes(8)).'.json';
        $manifest = json_decode(
            (string) File::get(resource_path('serpro/official-sources.v2026-07-18.json')),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
        $manifest['sources'][7]['content_sha256'] = str_repeat('ab', 32);
        File::put($path, json_encode(
            $manifest,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
        ));

        try {
            config(['serpro.official_sources_manifest' => $path]);

            $this->artisan('serpro:prod-check', ['--serpro-env' => 'TRIAL'])
                ->expectsOutput('FAIL: integridade das fontes oficiais SERPRO não comprovada.')
                ->assertFailed();
            $this->assertSame(0, SerproDocumentSnapshot::query()->count());
        } finally {
            File::delete($path);
        }
    }

    public function test_external_gates_seed_and_block_production(): void
    {
        $service = app(SerproExternalGateService::class);
        $service->ensureBaselineGates();

        $this->assertGreaterThanOrEqual(6, SerproExternalGate::query()->count());
        $this->assertTrue($service->anyBlockingProduction());

        $this->artisan('serpro:external-gates', ['action' => 'list'])->assertSuccessful();
    }

    public function test_demo_inventory_segregates_without_delete(): void
    {
        $demo = Office::factory()->create(['slug' => 'demo', 'name' => 'Demo Office']);
        $real = Office::factory()->create(['slug' => 'acme', 'name' => 'Acme']);

        $inventory = app(SerproDemoInventoryService::class);
        $before = $inventory->inventory(applySegregation: false);
        $this->assertNotEmpty($before['offices']);

        $after = $inventory->inventory(applySegregation: true);
        $demo->refresh();
        $real->refresh();

        $this->assertSame(SerproDataSegregationClass::Demo->value, $demo->serpro_segregation_class);
        $this->assertTrue(
            $real->serpro_segregation_class === null || $real->serpro_segregation_class === ''
        );
        $this->assertContains("office:{$demo->id}:segregation=DEMO", $after['actions_applied']);
        $this->assertSame(2, Office::query()->count());
    }

    public function test_readiness_offline_does_not_require_live_http(): void
    {
        $run = app(SerproReadinessService::class)->evaluateGlobal(
            SerproEnvironment::Trial,
            persist: true,
        );

        $this->assertNotNull($run->id);
        $this->assertFalse($run->live_evidence);
        $this->assertNotEmpty($run->evidences);
        $this->assertArrayHasKey('note', $run->summary);

        $this->artisan('serpro:readiness', [
            '--serpro-env' => 'TRIAL',
            '--no-persist' => true,
            '--json' => true,
        ])->assertSuccessful();
    }

    public function test_credential_version_sanitized_payload_hides_vault_ids(): void
    {
        $version = SerproCredentialVersion::query()->create([
            'environment' => SerproEnvironment::Trial,
            'version_number' => 1,
            'status' => SerproCredentialVersionStatus::Pending,
            'was_exposed' => false,
            'pfx_vault_object_id' => '01HXYZVAULTOBJECT000001',
            'oauth_vault_object_id' => '01HXYZVAULTOBJECT000002',
            'contractor_cnpj' => '11222333000181',
            'segregation_class' => SerproDataSegregationClass::HistoricalUnverified,
        ]);

        $json = $version->toSanitizedArray();
        $this->assertArrayNotHasKey('pfx_vault_object_id', $json);
        $this->assertArrayNotHasKey('oauth_vault_object_id', $json);
        $this->assertTrue($json['has_pfx']);
        $this->assertTrue($json['has_oauth']);
    }

    public function test_migrations_create_readiness_and_budget_tables(): void
    {
        $this->assertTrue(\Schema::hasTable('serpro_credential_versions'));
        $this->assertTrue(\Schema::hasTable('serpro_credential_approvals'));
        $this->assertTrue(\Schema::hasTable('serpro_readiness_runs'));
        $this->assertTrue(\Schema::hasTable('serpro_readiness_evidences'));
        $this->assertTrue(\Schema::hasTable('serpro_document_snapshots'));
        $this->assertTrue(\Schema::hasTable('serpro_external_gates'));
        $this->assertTrue(\Schema::hasTable('serpro_term_versions'));
        $this->assertTrue(\Schema::hasTable('serpro_usage_budgets'));
        $this->assertTrue(\Schema::hasTable('vault_object_journal'));
        $this->assertTrue(\Schema::hasColumn('serpro_contracts', 'credentials_exposed'));
        $this->assertTrue(\Schema::hasColumn('offices', 'serpro_segregation_class'));
        $this->assertTrue(\Schema::hasColumn('tax_proxy_powers', 'freshness_checked_at'));
    }
}

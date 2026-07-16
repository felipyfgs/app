<?php

namespace Tests\Feature\Backup;

use App\Models\InstanceBackupRun;
use App\Services\Backup\InstanceBackupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class InstanceBackupTest extends TestCase
{
    use RefreshDatabase;

    private string $backupRoot;

    private string $vaultRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->backupRoot = sys_get_temp_dir().'/nfse-backup-test-'.uniqid('', true);
        $this->vaultRoot = sys_get_temp_dir().'/nfse-vault-test-'.uniqid('', true);
        File::ensureDirectoryExists($this->backupRoot, 0700);
        File::ensureDirectoryExists($this->vaultRoot, 0700);
        File::put($this->vaultRoot.'/object-a.enc', 'ciphertext-envelope-sample');

        config([
            'backup.disk_root' => $this->backupRoot,
            'backup.retention_runs' => 7,
            'vault.disk_root' => $this->vaultRoot,
            'vault.master_key' => 'AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=',
        ]);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->backupRoot)) {
            File::deleteDirectory($this->backupRoot);
        }
        if (is_dir($this->vaultRoot)) {
            File::deleteDirectory($this->vaultRoot);
        }
        parent::tearDown();
    }

    public function test_full_backup_sucesso_e_manifesto_sem_secrets(): void
    {
        $result = app(InstanceBackupService::class)->run(InstanceBackupRun::KIND_FULL);
        $run = $result['run'];

        $this->assertTrue($result['acquired']);
        $this->assertSame(InstanceBackupRun::STATUS_SUCCESS, $run->status);
        $this->assertNotNull($run->finished_at);
        $this->assertNotNull($run->manifest_path);
        $this->assertNotNull($run->checksum);
        $this->assertGreaterThan(0, $run->byte_size);

        $manifestPath = $this->backupRoot.'/'.str_replace('/', DIRECTORY_SEPARATOR, $run->manifest_path);
        $this->assertFileExists($manifestPath);
        $json = (string) file_get_contents($manifestPath);

        $this->assertStringNotContainsString('VAULT_MASTER_KEY', $json);
        $this->assertStringNotContainsString('vault_master_key', $json);
        $this->assertStringNotContainsString('BEGIN ', $json);
        $this->assertStringNotContainsString('PRIVATE KEY', $json);
        $this->assertStringNotContainsString('AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=', $json);

        $manifest = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        $this->assertFalse($manifest['master_key_included']);
        // full = database + vault + private
        $this->assertGreaterThanOrEqual(3, count($manifest['components']));
        $names = array_column($manifest['components'], 'name');
        $this->assertContains('database', $names);
        $this->assertContains('vault', $names);
        $this->assertContains('private', $names);
        $this->assertFalse($manifest['package_encrypted'] ?? false);
    }

    public function test_full_backup_cifra_pacote_quando_chave_externa_configurada(): void
    {
        $packageKey = base64_encode(random_bytes(32));
        config(['backup.package_key' => $packageKey]);

        $result = app(InstanceBackupService::class)->run(InstanceBackupRun::KIND_FULL);
        $run = $result['run'];
        $this->assertSame(InstanceBackupRun::STATUS_SUCCESS, $run->status);

        $manifestPath = $this->backupRoot.'/'.str_replace('/', DIRECTORY_SEPARATOR, $run->manifest_path);
        $manifest = json_decode((string) file_get_contents($manifestPath), true, 512, JSON_THROW_ON_ERROR);
        $this->assertTrue($manifest['package_encrypted']);
        $this->assertSame('nfse-adn-backup-v3', $manifest['format']);
        $names = array_column($manifest['components'], 'name');
        $this->assertContains('package', $names);

        $drill = app(InstanceBackupService::class)->restoreDrill($run->id)['run'];
        $this->assertSame(InstanceBackupRun::STATUS_SUCCESS, $drill->status);
        $this->assertStringContainsString('decrypt=ok', (string) $drill->message);

        // Chave errada no drill do pacote.
        config(['backup.package_key' => base64_encode(random_bytes(32))]);
        $failed = app(InstanceBackupService::class)->restoreDrill($run->id)['run'];
        $this->assertSame(InstanceBackupRun::STATUS_FAILED, $failed->status);
    }

    public function test_falha_parcial_marca_failed(): void
    {
        // Cofre inexistente força falha no componente vault.
        config(['vault.disk_root' => $this->vaultRoot.'/missing-dir']);

        $result = app(InstanceBackupService::class)->run(InstanceBackupRun::KIND_FULL);
        $run = $result['run'];

        $this->assertSame(InstanceBackupRun::STATUS_FAILED, $run->status);
        $this->assertNotNull($run->message);
        $this->assertStringNotContainsString('VAULT_MASTER_KEY', (string) $run->message);
        $this->assertStringNotContainsString('AAAAAAAAAAAAAAAA', (string) $run->message);
    }

    public function test_concorrencia_rejeitada(): void
    {
        $lock = Cache::lock(config('backup.lock_name'), 30);
        $this->assertTrue($lock->get());

        try {
            $result = app(InstanceBackupService::class)->run(InstanceBackupRun::KIND_DATABASE);
            $this->assertFalse($result['acquired']);
            $this->assertSame(InstanceBackupRun::STATUS_FAILED, $result['run']->status);
            $this->assertStringContainsString('lock', strtolower((string) $result['run']->message));
        } finally {
            $lock->release();
        }
    }

    public function test_restore_drill_sucesso_e_corrompido(): void
    {
        $backup = app(InstanceBackupService::class)->run(InstanceBackupRun::KIND_FULL)['run'];
        $this->assertSame(InstanceBackupRun::STATUS_SUCCESS, $backup->status);

        $ok = app(InstanceBackupService::class)->restoreDrill($backup->id)['run'];
        $this->assertSame(InstanceBackupRun::STATUS_SUCCESS, $ok->status);
        $this->assertSame(InstanceBackupRun::KIND_RESTORE_DRILL, $ok->kind);

        // Corrompe um componente referenciado no manifesto.
        $manifestPath = $this->backupRoot.'/'.str_replace('/', DIRECTORY_SEPARATOR, (string) $backup->manifest_path);
        $manifest = json_decode((string) file_get_contents($manifestPath), true, 512, JSON_THROW_ON_ERROR);
        $componentPath = $this->backupRoot.'/'.str_replace('/', DIRECTORY_SEPARATOR, $manifest['components'][0]['path']);
        File::put($componentPath, 'corrupted-payload');

        $failed = app(InstanceBackupService::class)->restoreDrill($backup->id)['run'];
        $this->assertSame(InstanceBackupRun::STATUS_FAILED, $failed->status);

        // Backup original permanece SUCCESS.
        $this->assertSame(InstanceBackupRun::STATUS_SUCCESS, $backup->fresh()->status);
    }

    public function test_comando_artisan_backup_run(): void
    {
        $this->artisan('ops:backup-run', ['--kind' => 'full'])
            ->assertSuccessful();

        $this->assertDatabaseHas('instance_backup_runs', [
            'kind' => InstanceBackupRun::KIND_FULL,
            'status' => InstanceBackupRun::STATUS_SUCCESS,
        ]);
    }

    public function test_status_summary_never_e_stale(): void
    {
        $never = InstanceBackupRun::statusSummary();
        $this->assertTrue($never['never']);
        $this->assertTrue($never['stale']);

        InstanceBackupRun::factory()->create([
            'kind' => InstanceBackupRun::KIND_FULL,
            'status' => InstanceBackupRun::STATUS_SUCCESS,
            'finished_at' => now()->subHours(30),
            'manifest_path' => 'runs/old/manifest.json',
        ]);

        $stale = InstanceBackupRun::statusSummary();
        $this->assertFalse($stale['never']);
        $this->assertTrue($stale['stale']);

        InstanceBackupRun::factory()->create([
            'kind' => InstanceBackupRun::KIND_FULL,
            'status' => InstanceBackupRun::STATUS_SUCCESS,
            'finished_at' => now()->subHour(),
            'manifest_path' => 'runs/new/manifest.json',
        ]);

        $fresh = InstanceBackupRun::statusSummary();
        $this->assertFalse($fresh['never']);
        $this->assertFalse($fresh['stale']);
        $this->assertNotNull($fresh['last_full_success_at']);
    }

    public function test_kind_vault_sozinho_nao_limpa_never_de_full(): void
    {
        InstanceBackupRun::factory()->create([
            'kind' => InstanceBackupRun::KIND_VAULT,
            'status' => InstanceBackupRun::STATUS_SUCCESS,
            'finished_at' => now()->subHour(),
            'manifest_path' => 'runs/vault-only/manifest.json',
        ]);

        $summary = InstanceBackupRun::statusSummary();
        $this->assertTrue($summary['never']);
        $this->assertTrue($summary['stale']);
        $this->assertNotNull($summary['last_success_at']);
        $this->assertNull($summary['last_full_success_at']);
    }

    public function test_retencao_preserva_success_e_poda_failed(): void
    {
        config([
            'backup.retention_runs' => 1,
            'backup.retention_failed_runs' => 1,
        ]);

        $first = app(InstanceBackupService::class)->run(InstanceBackupRun::KIND_FULL)['run'];
        $this->assertSame(InstanceBackupRun::STATUS_SUCCESS, $first->status);
        $firstDir = dirname($this->backupRoot.'/'.str_replace('/', DIRECTORY_SEPARATOR, (string) $first->manifest_path));
        $this->assertDirectoryExists($firstDir);

        // Segundo SUCCESS: retenção 1 deve podar artefato do primeiro SUCCESS.
        $second = app(InstanceBackupService::class)->run(InstanceBackupRun::KIND_FULL)['run'];
        $this->assertSame(InstanceBackupRun::STATUS_SUCCESS, $second->status);
        $this->assertDirectoryDoesNotExist($firstDir);
        $this->assertNull($first->fresh()->manifest_path);

        // Falhas parciais não devem apagar o único SUCCESS restante.
        config(['vault.disk_root' => $this->vaultRoot.'/missing']);
        app(InstanceBackupService::class)->run(InstanceBackupRun::KIND_FULL);
        app(InstanceBackupService::class)->run(InstanceBackupRun::KIND_FULL);

        $secondFresh = $second->fresh();
        $this->assertSame(InstanceBackupRun::STATUS_SUCCESS, $secondFresh->status);
        $this->assertNotNull($secondFresh->manifest_path);
        $secondDir = dirname($this->backupRoot.'/'.str_replace('/', DIRECTORY_SEPARATOR, (string) $secondFresh->manifest_path));
        $this->assertDirectoryExists($secondDir);
    }

    public function test_drill_detecta_inventario_de_cofre_corrompido(): void
    {
        // Força fallback directory_inventory (sem tar bem-sucedido).
        $service = app(InstanceBackupService::class);
        $ref = new \ReflectionClass($service);
        // Simula tar ausente renomeando PATH temporariamente no processo filho via binary check:
        // Copia árvore manualmente e monta run como o fallback faria.
        $run = InstanceBackupRun::factory()->create([
            'kind' => InstanceBackupRun::KIND_VAULT,
            'status' => InstanceBackupRun::STATUS_RUNNING,
            'finished_at' => null,
            'manifest_path' => null,
        ]);
        $relativeRunDir = 'runs/'.$run->id.'-drill-inv';
        $absoluteRunDir = $this->backupRoot.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relativeRunDir);
        File::ensureDirectoryExists($absoluteRunDir.DIRECTORY_SEPARATOR.'vault', 0700);
        File::copy($this->vaultRoot.'/object-a.enc', $absoluteRunDir.DIRECTORY_SEPARATOR.'vault'.DIRECTORY_SEPARATOR.'object-a.enc');

        $inventoryMethod = $ref->getMethod('inventoryChecksum');
        $inventoryMethod->setAccessible(true);
        $inventory = $inventoryMethod->invoke($service, $absoluteRunDir.DIRECTORY_SEPARATOR.'vault');
        $marker = $absoluteRunDir.DIRECTORY_SEPARATOR.'vault.sha256';
        File::put($marker, $inventory."\n");

        $manifest = [
            'format' => 'nfse-adn-backup-v1',
            'kind' => 'vault',
            'created_at' => now()->toIso8601String(),
            'components' => [[
                'name' => 'vault',
                'path' => $relativeRunDir.'/vault.sha256',
                'sha256' => hash_file('sha256', $marker),
                'byte_size' => 10,
                'format' => 'directory_inventory',
            ]],
            'master_key_included' => false,
        ];
        $manifestPath = $absoluteRunDir.DIRECTORY_SEPARATOR.'manifest.json';
        File::put($manifestPath, json_encode($manifest, JSON_THROW_ON_ERROR));
        $run->fill([
            'status' => InstanceBackupRun::STATUS_SUCCESS,
            'finished_at' => now(),
            'manifest_path' => $relativeRunDir.'/manifest.json',
            'checksum' => hash_file('sha256', $manifestPath),
        ])->save();

        $ok = app(InstanceBackupService::class)->restoreDrill($run->id)['run'];
        $this->assertSame(InstanceBackupRun::STATUS_SUCCESS, $ok->status);

        File::put($absoluteRunDir.DIRECTORY_SEPARATOR.'vault'.DIRECTORY_SEPARATOR.'object-a.enc', 'tampered');
        $failed = app(InstanceBackupService::class)->restoreDrill($run->id)['run'];
        $this->assertSame(InstanceBackupRun::STATUS_FAILED, $failed->status);
        $this->assertStringContainsString('Inventário', (string) $failed->message);
    }
}

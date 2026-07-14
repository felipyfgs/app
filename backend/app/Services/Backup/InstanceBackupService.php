<?php

namespace App\Services\Backup;

use App\Models\InstanceBackupRun;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

final class InstanceBackupService
{
    /** Substrings proibidas no conteúdo do manifesto (não nos nomes de campos de declaração). */
    private const FORBIDDEN_MANIFEST_NEEDLES = [
        'VAULT_MASTER_KEY',
        'BEGIN PRIVATE',
        'BEGIN RSA',
        '-----BEGIN',
        'PRIVATE KEY',
    ];

    /**
     * @return array{run: InstanceBackupRun, acquired: bool}
     */
    public function run(string $kind): array
    {
        $kind = strtolower($kind);
        if (! in_array($kind, [
            InstanceBackupRun::KIND_FULL,
            InstanceBackupRun::KIND_DATABASE,
            InstanceBackupRun::KIND_VAULT,
        ], true)) {
            throw new RuntimeException('Kind de backup inválido.');
        }

        $lock = Cache::lock(
            (string) config('backup.lock_name', 'ops.backup-run'),
            (int) config('backup.lock_seconds', 3600),
        );

        try {
            $lock->block(0);
        } catch (LockTimeoutException) {
            return [
                'run' => InstanceBackupRun::query()->create([
                    'kind' => $kind,
                    'status' => InstanceBackupRun::STATUS_FAILED,
                    'started_at' => now(),
                    'finished_at' => now(),
                    'message' => 'Backup já em andamento (lock).',
                ]),
                'acquired' => false,
            ];
        }

        $run = InstanceBackupRun::query()->create([
            'kind' => $kind,
            'status' => InstanceBackupRun::STATUS_RUNNING,
            'started_at' => now(),
        ]);

        $relativeRunDir = 'runs/'.$run->id.'-'.now()->format('Ymd\THis\Z');
        $absoluteRunDir = $this->diskRoot().DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relativeRunDir);

        try {
            File::ensureDirectoryExists($absoluteRunDir, 0700);

            $components = [];
            $errors = [];

            if ($kind === InstanceBackupRun::KIND_FULL || $kind === InstanceBackupRun::KIND_DATABASE) {
                try {
                    $components[] = $this->backupDatabase($absoluteRunDir, $relativeRunDir);
                } catch (Throwable $e) {
                    $errors[] = $this->sanitizeMessage($e->getMessage());
                }
            }

            if ($kind === InstanceBackupRun::KIND_FULL || $kind === InstanceBackupRun::KIND_VAULT) {
                try {
                    $components[] = $this->backupVault($absoluteRunDir, $relativeRunDir);
                } catch (Throwable $e) {
                    $errors[] = $this->sanitizeMessage($e->getMessage());
                }
            }

            $required = $kind === InstanceBackupRun::KIND_FULL ? 2 : 1;
            $ok = count($components) >= $required && $errors === [];

            $manifestRelative = $relativeRunDir.'/manifest.json';
            $manifestAbsolute = $absoluteRunDir.DIRECTORY_SEPARATOR.'manifest.json';
            $manifest = [
                'format' => 'nfse-adn-backup-v1',
                'kind' => $kind,
                'created_at' => now()->toIso8601String(),
                'app' => config('app.name'),
                'components' => $components,
                'master_key_included' => false,
            ];

            $this->assertManifestSafe($manifest);
            File::put($manifestAbsolute, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
            @chmod($manifestAbsolute, 0600);

            $checksum = hash_file('sha256', $manifestAbsolute);
            $byteSize = $this->directoryByteSize($absoluteRunDir);

            $run->fill([
                'status' => $ok ? InstanceBackupRun::STATUS_SUCCESS : InstanceBackupRun::STATUS_FAILED,
                'finished_at' => now(),
                'byte_size' => $byteSize,
                'manifest_path' => $manifestRelative,
                'checksum' => $checksum,
                'message' => $ok ? null : (implode(' ', $errors) ?: 'Falha parcial no backup.'),
            ]);
            $run->save();

            // Poda sempre (SUCCESS e FAILED) para não acumular disco nem apagar SUCCESS por retenção cega.
            $this->pruneOldRuns();

            Log::info('ops.backup_run', [
                'kind' => $kind,
                'status' => $run->status,
                'byte_size' => $run->byte_size,
                'manifest_path' => $run->manifest_path,
                'duration_ms' => $run->started_at
                    ? (int) $run->started_at->diffInMilliseconds($run->finished_at)
                    : null,
            ]);

            return ['run' => $run->refresh(), 'acquired' => true];
        } catch (Throwable $e) {
            $run->fill([
                'status' => InstanceBackupRun::STATUS_FAILED,
                'finished_at' => now(),
                'message' => $this->sanitizeMessage($e->getMessage()),
            ]);
            $run->save();

            Log::error('ops.backup_run', [
                'kind' => $kind,
                'status' => InstanceBackupRun::STATUS_FAILED,
                'message' => $run->message,
            ]);

            return ['run' => $run->refresh(), 'acquired' => true];
        } finally {
            $lock->release();
        }
    }

    /**
     * Valida manifesto + checksums do artefato de um backup SUCCESS.
     *
     * @return array{run: InstanceBackupRun}
     */
    public function restoreDrill(?int $backupRunId = null): array
    {
        $source = $this->resolveSourceRun($backupRunId);

        $drill = InstanceBackupRun::query()->create([
            'kind' => InstanceBackupRun::KIND_RESTORE_DRILL,
            'status' => InstanceBackupRun::STATUS_RUNNING,
            'started_at' => now(),
            'message' => null,
        ]);

        try {
            if ($source === null) {
                throw new RuntimeException('Nenhum backup SUCCESS disponível para drill.');
            }

            if ($source->manifest_path === null || $source->checksum === null) {
                throw new RuntimeException('Backup sem manifesto ou checksum.');
            }

            $manifestAbsolute = $this->absoluteFromRelative($source->manifest_path);
            if (! is_file($manifestAbsolute)) {
                throw new RuntimeException('Arquivo de manifesto ausente.');
            }

            $actualChecksum = hash_file('sha256', $manifestAbsolute);
            if (! hash_equals($source->checksum, $actualChecksum)) {
                throw new RuntimeException('Checksum do manifesto não confere.');
            }

            /** @var array<string, mixed> $manifest */
            $manifest = json_decode((string) file_get_contents($manifestAbsolute), true, 512, JSON_THROW_ON_ERROR);
            $this->assertManifestSafe($manifest);

            $components = $manifest['components'] ?? null;
            if (! is_array($components) || $components === []) {
                throw new RuntimeException('Manifesto sem componentes.');
            }

            foreach ($components as $component) {
                if (! is_array($component)) {
                    throw new RuntimeException('Componente inválido no manifesto.');
                }
                $path = $component['path'] ?? null;
                $sha = $component['sha256'] ?? null;
                if (! is_string($path) || ! is_string($sha)) {
                    throw new RuntimeException('Componente sem path/sha256.');
                }
                $absolute = $this->absoluteFromRelative($path);
                if (! is_file($absolute)) {
                    throw new RuntimeException('Arquivo de componente ausente.');
                }
                $fileSha = hash_file('sha256', $absolute);
                if (! hash_equals($sha, $fileSha)) {
                    throw new RuntimeException('Checksum de componente não confere.');
                }

                $format = $component['format'] ?? null;
                if ($format === 'directory_inventory') {
                    // Marker guarda o hash do inventário dos blobs em runs/.../vault/.
                    $vaultDir = dirname($absolute).DIRECTORY_SEPARATOR.'vault';
                    if (! is_dir($vaultDir)) {
                        throw new RuntimeException('Diretório do cofre do artefato ausente.');
                    }
                    $expectedInventory = trim((string) file_get_contents($absolute));
                    $actualInventory = $this->inventoryChecksum($vaultDir);
                    if ($expectedInventory === '' || ! hash_equals($expectedInventory, $actualInventory)) {
                        throw new RuntimeException('Inventário do cofre não confere com o marker.');
                    }
                }

                if (($component['name'] ?? null) === 'database') {
                    $this->assertDatabaseArtifactLooksRestorable($absolute, is_string($format) ? $format : null);
                }
            }

            $drill->fill([
                'status' => InstanceBackupRun::STATUS_SUCCESS,
                'finished_at' => now(),
                'byte_size' => $source->byte_size,
                'manifest_path' => $source->manifest_path,
                'checksum' => $source->checksum,
                'message' => 'Drill OK no backup #'.$source->id.'.',
            ]);
            $drill->save();
        } catch (Throwable $e) {
            $drill->fill([
                'status' => InstanceBackupRun::STATUS_FAILED,
                'finished_at' => now(),
                'message' => $this->sanitizeMessage($e->getMessage()),
            ]);
            $drill->save();
        }

        Log::info('ops.restore_drill', [
            'status' => $drill->status,
            'source_run_id' => $source?->id,
            'message' => $drill->message,
        ]);

        return ['run' => $drill->refresh()];
    }

    private function resolveSourceRun(?int $backupRunId): ?InstanceBackupRun
    {
        $query = InstanceBackupRun::query()
            ->whereIn('kind', [
                InstanceBackupRun::KIND_FULL,
                InstanceBackupRun::KIND_DATABASE,
                InstanceBackupRun::KIND_VAULT,
            ])
            ->where('status', InstanceBackupRun::STATUS_SUCCESS);

        if ($backupRunId !== null) {
            return $query->where('id', $backupRunId)->first();
        }

        return $query->orderByDesc('finished_at')->orderByDesc('id')->first();
    }

    /**
     * @return array{name: string, path: string, sha256: string, byte_size: int}
     */
    private function backupDatabase(string $absoluteRunDir, string $relativeRunDir): array
    {
        $fileName = 'database.sql.gz';
        $absolute = $absoluteRunDir.DIRECTORY_SEPARATOR.$fileName;
        $connection = (string) config('database.default');
        $driver = (string) config("database.connections.{$connection}.driver");
        $format = 'pg_dump_gzip';

        if ($driver === 'pgsql') {
            // Em produção PostgreSQL, inventário de tabelas NÃO conta como backup restaurável.
            if (! $this->binaryExists('pg_dump')) {
                throw new RuntimeException(
                    'pg_dump indisponível: backup de banco PostgreSQL recusado (evita SUCCESS falso).'
                );
            }
            $this->pgDumpToGzip($absolute, $connection);
        } elseif ($driver === 'sqlite') {
            // Somente testes/dev SQLite: dump lógico de inventário (não é pg_restore).
            $sql = $this->logicalSqlDump($connection, $driver);
            $gz = gzencode($sql, 9);
            if ($gz === false) {
                throw new RuntimeException('Falha ao compactar dump do banco.');
            }
            File::put($absolute, $gz);
            $format = 'sqlite_inventory';
        } else {
            throw new RuntimeException('Driver de banco sem suporte a dump de backup.');
        }

        @chmod($absolute, 0600);

        return [
            'name' => 'database',
            'path' => $relativeRunDir.'/'.$fileName,
            'sha256' => hash_file('sha256', $absolute),
            'byte_size' => (int) filesize($absolute),
            'format' => $format,
        ];
    }

    /**
     * @return array{name: string, path: string, sha256: string, byte_size: int}
     */
    private function backupVault(string $absoluteRunDir, string $relativeRunDir): array
    {
        $vaultRoot = (string) config('vault.disk_root');
        if ($vaultRoot === '' || ! is_dir($vaultRoot)) {
            throw new RuntimeException('Diretório do cofre indisponível.');
        }

        $fileName = 'vault.tar';
        $absolute = $absoluteRunDir.DIRECTORY_SEPARATOR.$fileName;

        // Cópia em tar sem compressão pesada; objetos já são envelope cifrado.
        $result = Process::path($vaultRoot)->run([
            'tar', '-cf', $absolute, '-C', $vaultRoot, '.',
        ]);

        if (! $result->successful()) {
            // Fallback portável: copiar árvore se tar falhar (ambientes mínimos de teste).
            if (is_file($absolute)) {
                @unlink($absolute);
            }
            $this->copyVaultTree($vaultRoot, $absoluteRunDir.DIRECTORY_SEPARATOR.'vault');
            $fileName = 'vault';
            $absolute = $absoluteRunDir.DIRECTORY_SEPARATOR.'vault';
            // Marcar como diretório no manifesto via arquivo marker + checksum do inventário.
            $inventory = $this->inventoryChecksum($absolute);
            $marker = $absoluteRunDir.DIRECTORY_SEPARATOR.'vault.sha256';
            File::put($marker, $inventory."\n");
            @chmod($marker, 0600);

            return [
                'name' => 'vault',
                'path' => $relativeRunDir.'/vault.sha256',
                'sha256' => hash_file('sha256', $marker),
                'byte_size' => $this->directoryByteSize($absolute),
                'format' => 'directory_inventory',
            ];
        }

        @chmod($absolute, 0600);

        return [
            'name' => 'vault',
            'path' => $relativeRunDir.'/'.$fileName,
            'sha256' => hash_file('sha256', $absolute),
            'byte_size' => (int) filesize($absolute),
        ];
    }

    private function pgDumpToGzip(string $absoluteGzipPath, string $connection): void
    {
        $cfg = config("database.connections.{$connection}");
        $host = (string) ($cfg['host'] ?? '127.0.0.1');
        $port = (string) ($cfg['port'] ?? '5432');
        $database = (string) ($cfg['database'] ?? '');
        $username = (string) ($cfg['username'] ?? '');
        $password = (string) ($cfg['password'] ?? '');

        // Stream pg_dump | gzip — evita materializar o SQL inteiro em memória PHP.
        $cmd = sprintf(
            'pg_dump --clean --if-exists --no-owner --no-privileges -h %s -p %s -U %s %s | gzip -c > %s',
            escapeshellarg($host),
            escapeshellarg($port),
            escapeshellarg($username),
            escapeshellarg($database),
            escapeshellarg($absoluteGzipPath),
        );

        $result = Process::env([
            'PGPASSWORD' => $password,
        ])->run(['sh', '-c', $cmd]);

        if (! $result->successful() || ! is_file($absoluteGzipPath) || filesize($absoluteGzipPath) === 0) {
            @unlink($absoluteGzipPath);
            throw new RuntimeException('pg_dump falhou.');
        }

        // Sanity: gzip magic bytes.
        $fh = fopen($absoluteGzipPath, 'rb');
        $magic = $fh !== false ? (string) fread($fh, 2) : '';
        if ($fh !== false) {
            fclose($fh);
        }
        if ($magic !== "\x1f\x8b") {
            @unlink($absoluteGzipPath);
            throw new RuntimeException('Artefato de banco não é gzip válido.');
        }
    }

    private function logicalSqlDump(string $connection, string $driver): string
    {
        $parts = ["-- nfse logical dump\n-- master_key_included=false\n-- driver={$driver}\n"];

        if ($driver === 'sqlite') {
            $tables = DB::connection($connection)->select(
                "SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name"
            );
            foreach ($tables as $row) {
                $name = $row->name ?? null;
                if (! is_string($name) || $name === '') {
                    continue;
                }
                $parts[] = '-- table: '.$name."\n";
                $parts[] = '-- rows: '.DB::connection($connection)->table($name)->count()."\n";
            }

            return implode('', $parts);
        }

        throw new RuntimeException('Dump lógico de inventário permitido apenas em sqlite (testes).');
    }

    private function assertDatabaseArtifactLooksRestorable(string $absoluteGzipPath, ?string $format): void
    {
        if ($format === 'sqlite_inventory') {
            // Aceito apenas em ambientes de teste; não é pg_restore.
            return;
        }

        if ($format === 'inventory' || $format === 'logical_inventory') {
            throw new RuntimeException('Artefato de inventário não é dump restaurável.');
        }

        $fh = fopen($absoluteGzipPath, 'rb');
        if ($fh === false) {
            throw new RuntimeException('Não foi possível ler dump do banco.');
        }
        $magic = (string) fread($fh, 2);
        fclose($fh);
        if ($magic !== "\x1f\x8b") {
            throw new RuntimeException('Dump do banco não é gzip válido.');
        }

        // Amostra do início do SQL sem carregar o dump inteiro em memória.
        $zh = @gzopen($absoluteGzipPath, 'rb');
        if ($zh === false) {
            throw new RuntimeException('Dump do banco não descompacta.');
        }
        $head = (string) gzread($zh, 2048);
        gzclose($zh);
        if (trim($head) === '') {
            throw new RuntimeException('Dump do banco vazio após descompactar.');
        }
    }

    private function binaryExists(string $binary): bool
    {
        $result = Process::run(['sh', '-c', 'command -v '.escapeshellarg($binary)]);

        return $result->successful() && trim($result->output()) !== '';
    }

    private function copyVaultTree(string $source, string $destination): void
    {
        File::ensureDirectoryExists($destination, 0700);
        foreach (File::allFiles($source) as $file) {
            $relative = ltrim(str_replace($source, '', $file->getPathname()), DIRECTORY_SEPARATOR);
            $target = $destination.DIRECTORY_SEPARATOR.$relative;
            File::ensureDirectoryExists(dirname($target), 0700);
            File::copy($file->getPathname(), $target);
            @chmod($target, 0600);
        }
    }

    private function inventoryChecksum(string $directory): string
    {
        $lines = [];
        if (is_dir($directory)) {
            foreach (File::allFiles($directory) as $file) {
                $relative = ltrim(str_replace($directory, '', $file->getPathname()), DIRECTORY_SEPARATOR);
                $lines[] = hash_file('sha256', $file->getPathname()).'  '.$relative;
            }
        }
        sort($lines);

        return hash('sha256', implode("\n", $lines));
    }

    private function pruneOldRuns(): void
    {
        $keepSuccess = max(1, (int) config('backup.retention_runs', 7));
        $keepFailed = max(1, (int) config('backup.retention_failed_runs', 2));

        $kinds = [
            InstanceBackupRun::KIND_FULL,
            InstanceBackupRun::KIND_DATABASE,
            InstanceBackupRun::KIND_VAULT,
        ];

        // Retenção por status: N SUCCESS (artefatos restauráveis) + poucos FAILED para diagnóstico.
        $protectedSuccess = InstanceBackupRun::query()
            ->whereIn('kind', $kinds)
            ->where('status', InstanceBackupRun::STATUS_SUCCESS)
            ->orderByDesc('id')
            ->limit($keepSuccess)
            ->pluck('id')
            ->all();

        $protectedFailed = InstanceBackupRun::query()
            ->whereIn('kind', $kinds)
            ->where('status', InstanceBackupRun::STATUS_FAILED)
            ->orderByDesc('id')
            ->limit($keepFailed)
            ->pluck('id')
            ->all();

        $protected = array_values(array_unique(array_merge($protectedSuccess, $protectedFailed)));

        $victims = InstanceBackupRun::query()
            ->whereIn('kind', $kinds)
            ->whereIn('status', [
                InstanceBackupRun::STATUS_SUCCESS,
                InstanceBackupRun::STATUS_FAILED,
            ])
            ->when($protected !== [], fn ($q) => $q->whereNotIn('id', $protected))
            ->orderBy('id')
            ->get();

        $rootReal = realpath($this->diskRoot()) ?: '';

        foreach ($victims as $old) {
            if ($old->manifest_path) {
                $dir = dirname($this->absoluteFromRelative($old->manifest_path));
                $dirReal = realpath($dir) ?: '';
                if ($dirReal !== '' && $rootReal !== '' && str_starts_with($dirReal, $rootReal) && is_dir($dirReal)) {
                    File::deleteDirectory($dirReal);
                }
            }
            // Mantém a linha no banco para histórico; limpa paths órfãos.
            if ($old->status === InstanceBackupRun::STATUS_SUCCESS && $old->manifest_path !== null) {
                $old->fill([
                    'manifest_path' => null,
                    'checksum' => null,
                    'message' => trim(((string) $old->message).' [artefato podado por retenção]'),
                ]);
                $old->save();
            }
        }
    }

    private function diskRoot(): string
    {
        $root = (string) config('backup.disk_root', storage_path('app/backups'));
        File::ensureDirectoryExists($root, 0700);

        return rtrim($root, DIRECTORY_SEPARATOR);
    }

    private function absoluteFromRelative(string $relative): string
    {
        $relative = ltrim(str_replace(['..', '\\'], ['', '/'], $relative), '/');

        return $this->diskRoot().DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);
    }

    private function directoryByteSize(string $path): int
    {
        if (is_file($path)) {
            return (int) filesize($path);
        }
        $total = 0;
        if (! is_dir($path)) {
            return 0;
        }
        foreach (File::allFiles($path) as $file) {
            $total += $file->getSize();
        }

        return $total;
    }

    /**
     * @param  array<string, mixed>  $manifest
     */
    private function assertManifestSafe(array $manifest): void
    {
        if (array_key_exists('master_key_included', $manifest) && $manifest['master_key_included'] !== false) {
            throw new RuntimeException('Manifesto não pode incluir chave mestra.');
        }

        $encoded = json_encode($manifest) ?: '';
        foreach (self::FORBIDDEN_MANIFEST_NEEDLES as $needle) {
            if (Str::contains($encoded, $needle, true)) {
                throw new RuntimeException('Manifesto contém material proibido.');
            }
        }

        // Chave mestra real de config nunca pode aparecer no JSON.
        $masterKey = (string) config('vault.master_key');
        if ($masterKey !== '' && Str::contains($encoded, $masterKey)) {
            throw new RuntimeException('Manifesto contém material proibido.');
        }
    }

    private function sanitizeMessage(string $message): string
    {
        $message = preg_replace('/\s+/', ' ', trim($message)) ?? '';
        $message = Str::limit($message, 500, '…');
        $message = str_ireplace([
            (string) config('vault.master_key'),
            'VAULT_MASTER_KEY',
        ], '[redacted]', $message);

        return $message;
    }
}

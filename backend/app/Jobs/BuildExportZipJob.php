<?php

namespace App\Jobs;

use App\Contracts\SecureObjectStore;
use App\Models\Export;
use App\Models\NfseEvent;
use App\Models\NfseNote;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\File;
use Throwable;
use ZipArchive;

class BuildExportZipJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $exportId) {}

    public function handle(SecureObjectStore $store): void
    {
        $export = Export::query()->find($this->exportId);
        if ($export === null) {
            return;
        }

        $export->status = 'PROCESSING';
        $export->save();

        $dir = storage_path('app/private/exports/'.$export->office_id);
        File::ensureDirectoryExists($dir, 0700);
        $path = $dir.'/export-'.$export->id.'.zip';

        try {
            $zip = new ZipArchive;
            if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new \RuntimeException('Não foi possível criar ZIP.');
            }

            $filters = $export->filters ?? [];
            $query = NfseNote::query()->where('office_id', $export->office_id)->with('document');
            if (! empty($filters['competence'])) {
                $query->where('competence', $filters['competence']);
            }
            if (! empty($filters['access_key'])) {
                $query->where('access_key', $filters['access_key']);
            }
            if (! empty($filters['issuer_cnpj'])) {
                $query->where('issuer_cnpj', $this->normalizeIdentifier($filters['issuer_cnpj']));
            }
            if (! empty($filters['taker_cnpj'])) {
                $query->where('taker_cnpj', $this->normalizeIdentifier($filters['taker_cnpj']));
            }
            if (! empty($filters['fiscal_role'])) {
                $query->where('fiscal_role', $filters['fiscal_role']);
            }
            if (! empty($filters['status'])) {
                $query->where('status', $filters['status']);
            }
            if (! empty($filters['issued_from'])) {
                $query->whereDate('issued_at', '>=', $filters['issued_from']);
            }
            if (! empty($filters['issued_to'])) {
                $query->whereDate('issued_at', '<=', $filters['issued_to']);
            }

            $count = 0;
            $seen = [];
            $query->orderBy('id')->chunkById(100, function ($notes) use ($store, $zip, &$count, &$seen, $export): void {
                foreach ($notes as $note) {
                    if (isset($seen[$note->access_key])) {
                        continue;
                    }
                    $seen[$note->access_key] = true;
                    $doc = $note->document;
                    $bytes = $store->get($doc->vault_object_id, [
                        'office_id' => $doc->office_id,
                        'sha256' => $doc->sha256,
                    ]);
                    $cnpj = $this->safeSegment($note->issuer_cnpj ?: 'sem-cnpj');
                    $comp = preg_match('/^\d{4}-\d{2}$/', (string) $note->competence)
                        ? $note->competence
                        : 'sem-competencia';
                    $role = $this->safeSegment($note->fiscal_role?->value ?: 'sem-papel');
                    $accessKey = $this->safeSegment($note->access_key);
                    $entry = sprintf('%s/%s/%s/%s.xml', $cnpj, $comp, $role, $accessKey);
                    $zip->addFromString($entry, $bytes);
                    $count++;

                    if ($export->include_events) {
                        $events = NfseEvent::query()
                            ->where('office_id', $export->office_id)
                            ->where('access_key', $note->access_key)
                            ->with('document')
                            ->get();
                        foreach ($events as $i => $event) {
                            $edoc = $event->document;
                            $ebytes = $store->get($edoc->vault_object_id, [
                                'office_id' => $edoc->office_id,
                                'sha256' => $edoc->sha256,
                            ]);
                            $zip->addFromString(sprintf('%s/%s/%s/%s-event-%d.xml', $cnpj, $comp, $role, $accessKey, $i + 1), $ebytes);
                            $count++;
                        }
                    }
                }
            });

            $zip->close();

            $export->status = 'READY';
            $export->storage_path = $path;
            $export->byte_size = filesize($path) ?: 0;
            $export->files_count = $count;
            $export->expires_at = now()->addHours(24);
            $export->completed_at = now();
            $export->save();
        } catch (Throwable $e) {
            if (is_file($path)) {
                @unlink($path);
            }
            $export->status = 'FAILED';
            $export->error_message = 'Falha ao gerar exportação.';
            $export->storage_path = null;
            $export->save();
            report($e);
        }
    }

    private function normalizeIdentifier(string $value): string
    {
        return strtoupper(preg_replace('/[^A-Z0-9]/i', '', $value) ?? '');
    }

    private function safeSegment(string $value): string
    {
        $safe = preg_replace('/[^A-Z0-9_-]/i', '-', $value) ?? '';

        return trim($safe, '.-') ?: 'desconhecido';
    }
}

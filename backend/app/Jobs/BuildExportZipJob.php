<?php

namespace App\Jobs;

use App\Contracts\SecureObjectStore;
use App\Enums\DocumentKind;
use App\Models\CteDocument;
use App\Models\Export;
use App\Models\NfeDocument;
use App\Models\NfseEvent;
use App\Models\NfseNote;
use App\Support\NfseNoteStatus;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\File;
use Throwable;
use ZipArchive;

class BuildExportZipJob implements ShouldQueue
{
    use Queueable;

    /** Teto de chaves em exportação por seleção (catálogo / multi-select). */
    public const MAX_ACCESS_KEYS = 100;

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
            $kinds = $this->resolveKinds($filters);
            $count = 0;
            $seen = [];

            if ($kinds === [] || in_array(DocumentKind::Nfse, $kinds, true)) {
                $query = NfseNote::query()->where('office_id', $export->office_id)->with('document');
                $this->applySharedFilters($query, $filters, nfse: true);
                $query->orderBy('id')->chunkById(100, function ($notes) use ($store, $zip, &$count, &$seen, $export): void {
                    foreach ($notes as $note) {
                        if (isset($seen['NFSE:'.$note->access_key])) {
                            continue;
                        }
                        $seen['NFSE:'.$note->access_key] = true;
                        $doc = $note->document;
                        $bytes = $store->get($doc->vault_object_id, [
                            'office_id' => $doc->office_id,
                            'sha256' => $doc->sha256,
                        ]);
                        $entry = $this->zipEntryPath(
                            $note->direction?->value,
                            'nfse',
                            $note->issuer_cnpj,
                            $note->competence,
                            $note->fiscal_role?->value,
                            $note->access_key,
                        );
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
                                $zip->addFromString(
                                    $this->zipEntryPath(
                                        $note->direction?->value,
                                        'nfse',
                                        $note->issuer_cnpj,
                                        $note->competence,
                                        $note->fiscal_role?->value,
                                        $note->access_key,
                                        'event-'.($i + 1),
                                    ),
                                    $ebytes
                                );
                                $count++;
                            }
                        }
                    }
                });
            }

            if ($kinds === [] || in_array(DocumentKind::Nfe, $kinds, true) || in_array(DocumentKind::Nfce, $kinds, true)) {
                $query = NfeDocument::query()->where('office_id', $export->office_id)->with('document');
                // Prefer full over summary for same key
                $query->where(function (Builder $q): void {
                    $q->where('is_summary', false)
                        ->orWhereNotExists(function ($sub): void {
                            $sub->selectRaw('1')
                                ->from('nfe_documents as full')
                                ->whereColumn('full.office_id', 'nfe_documents.office_id')
                                ->whereColumn('full.access_key', 'nfe_documents.access_key')
                                ->where('full.is_summary', false);
                        });
                });
                if (in_array(DocumentKind::Nfe, $kinds, true) && ! in_array(DocumentKind::Nfce, $kinds, true) && $kinds !== []) {
                    $query->where(function ($q): void {
                        $q->where('model', '55')->orWhereNull('model');
                    });
                } elseif (in_array(DocumentKind::Nfce, $kinds, true) && ! in_array(DocumentKind::Nfe, $kinds, true) && $kinds !== []) {
                    $query->where('model', '65');
                }
                $this->applySharedFilters($query, $filters, nfse: false);
                $query->orderBy('id')->chunkById(100, function ($rows) use ($store, $zip, &$count, &$seen): void {
                    foreach ($rows as $row) {
                        $kindSeg = ($row->model === '65') ? 'nfce' : 'nfe';
                        $dedupe = strtoupper($kindSeg).':'.$row->access_key;
                        if (isset($seen[$dedupe])) {
                            continue;
                        }
                        $seen[$dedupe] = true;
                        $doc = $row->document;
                        $bytes = $store->get($doc->vault_object_id, [
                            'office_id' => $doc->office_id,
                            'sha256' => $doc->sha256,
                        ]);
                        $comp = $row->issued_at?->format('Y-m') ?? 'sem-competencia';
                        $entry = $this->zipEntryPath(
                            $row->direction?->value,
                            $kindSeg,
                            $row->issuer_cnpj,
                            $comp,
                            $row->fiscal_role?->value,
                            $row->access_key,
                            $row->is_summary ? 'resumo' : null,
                        );
                        $zip->addFromString($entry, $bytes);
                        $count++;
                    }
                });
            }

            if ($kinds === [] || in_array(DocumentKind::Cte, $kinds, true)) {
                $query = CteDocument::query()->where('office_id', $export->office_id)->with('document');
                // Prefer full over summary for same access_key (espelho NF-e).
                $query->where(function (Builder $q): void {
                    $q->where('is_summary', false)
                        ->orWhereNotExists(function ($sub): void {
                            $sub->selectRaw('1')
                                ->from('cte_documents as full')
                                ->whereColumn('full.office_id', 'cte_documents.office_id')
                                ->whereColumn('full.access_key', 'cte_documents.access_key')
                                ->where('full.is_summary', false);
                        });
                });
                $this->applySharedFilters($query, $filters, nfse: false);
                $query->orderBy('id')->chunkById(100, function ($rows) use ($store, $zip, &$count, &$seen): void {
                    foreach ($rows as $row) {
                        if (isset($seen['CTE:'.$row->access_key])) {
                            continue;
                        }
                        $seen['CTE:'.$row->access_key] = true;
                        $doc = $row->document;
                        if ($doc === null) {
                            continue;
                        }
                        $bytes = $store->get($doc->vault_object_id, [
                            'office_id' => $doc->office_id,
                            'sha256' => $doc->sha256,
                        ]);
                        $comp = $row->issued_at?->format('Y-m') ?? 'sem-competencia';
                        $entry = $this->zipEntryPath(
                            $row->direction?->value,
                            'cte',
                            $row->issuer_cnpj,
                            $comp,
                            $row->fiscal_role?->value,
                            $row->access_key,
                            $row->is_summary ? 'resumo' : null,
                        );
                        $zip->addFromString($entry, $bytes);
                        $count++;
                    }
                });
            }

            // MDF-e legado nunca entra nos ramos de consulta ou no vault.

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

    /**
     * @param  array<string, mixed>  $filters
     * @return list<DocumentKind>
     */
    private function resolveKinds(array $filters): array
    {
        $raw = $filters['kind'] ?? $filters['kinds'] ?? null;
        if ($raw === null || $raw === '' || $raw === 'all') {
            return [];
        }
        $values = is_array($raw) ? $raw : [$raw];
        $out = [];
        foreach ($values as $v) {
            $kind = DocumentKind::tryFromRequest(is_string($v) ? $v : null);
            if ($kind !== null) {
                $out[$kind->value] = $kind;
            }
        }

        return array_values($out);
    }

    /**
     * @param  Builder<Model>  $query
     * @param  array<string, mixed>  $filters
     */
    private function applySharedFilters(Builder $query, array $filters, bool $nfse): void
    {
        if ($nfse && ! empty($filters['competence'])) {
            $query->where('competence', $filters['competence']);
        }
        $accessKeys = $filters['access_keys'] ?? null;
        if (is_array($accessKeys) && $accessKeys !== []) {
            $keys = array_values(array_unique(array_filter(array_map(
                static fn ($k) => is_string($k) ? trim($k) : '',
                $accessKeys,
            ))));
            $query->whereIn('access_key', $keys);
        } elseif (! empty($filters['access_key'])) {
            $query->where('access_key', $filters['access_key']);
        }
        if (! empty($filters['issuer_cnpj'])) {
            $query->where('issuer_cnpj', $this->normalizeIdentifier($filters['issuer_cnpj']));
        }
        if ($nfse && ! empty($filters['taker_cnpj'])) {
            $query->where('taker_cnpj', $this->normalizeIdentifier($filters['taker_cnpj']));
        }
        if (! empty($filters['fiscal_role'])) {
            $query->where('fiscal_role', $filters['fiscal_role']);
        }
        if (! empty($filters['direction'])) {
            $query->where('direction', strtoupper((string) $filters['direction']));
        }
        if ($nfse && ! empty($filters['status'])) {
            $statuses = NfseNoteStatus::statusesForFilter((string) $filters['status']);
            if (count($statuses) === 1) {
                $query->where('status', $statuses[0]);
            } elseif (count($statuses) > 1) {
                $query->whereIn('status', $statuses);
            }
        } elseif (! $nfse && ! empty($filters['status'])) {
            $query->where('status', strtoupper((string) $filters['status']));
        }
        if (! empty($filters['issued_from'])) {
            $query->whereDate('issued_at', '>=', $filters['issued_from']);
        }
        if (! empty($filters['issued_to'])) {
            $query->whereDate('issued_at', '<=', $filters['issued_to']);
        }
        if (! empty($filters['client_id'])) {
            $clientId = (int) $filters['client_id'];
            $query->whereHas('document.interests.establishment', function ($q) use ($clientId): void {
                $q->where('client_id', $clientId);
            });
        }
        if (! empty($filters['establishment_id'])) {
            $establishmentId = (int) $filters['establishment_id'];
            $query->whereHas('document.interests', function ($q) use ($establishmentId): void {
                $q->where('establishment_id', $establishmentId);
            });
        }
    }

    private function zipEntryPath(
        ?string $directionValue,
        string $kind,
        ?string $cnpj,
        ?string $competence,
        ?string $role,
        string $accessKey,
        ?string $suffix = null,
    ): string {
        $direction = match ($directionValue) {
            'OUT' => 'saida',
            'IN' => 'entrada',
            default => 'indefinida',
        };
        $comp = preg_match('/^\d{4}-\d{2}$/', (string) $competence)
            ? $competence
            : 'sem-competencia';
        $base = sprintf(
            '%s/%s/%s/%s/%s/%s',
            $direction,
            $kind,
            $this->safeSegment($cnpj ?: 'sem-cnpj'),
            $comp,
            $this->safeSegment($role ?: 'sem-papel'),
            $this->safeSegment($accessKey),
        );
        if ($suffix) {
            return $base.'-'.$this->safeSegment($suffix).'.xml';
        }

        return $base.'.xml';
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

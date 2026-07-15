<?php

namespace App\Http\Controllers\Api\V1;

use App\Contracts\SecureObjectStore;
use App\Enums\DocumentDirection;
use App\Enums\DocumentKind;
use App\Enums\FiscalRole;
use App\Enums\NfeManifestationType;
use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\CteDocument;
use App\Models\NfeDocument;
use App\Models\NfseEvent;
use App\Models\NfseNote;
use App\Services\Audit\AuditLogger;
use App\Services\Sefaz\NfeManifestationService;
use App\Services\Sefaz\NfeXmlUnlockService;
use App\Support\CurrentOffice;
use App\Support\DocumentCatalogCursor;
use App\Support\NfseNoteStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class NoteController extends Controller
{
    public function index(Request $request, CurrentOffice $currentOffice): JsonResponse
    {
        $kinds = DocumentKind::listFromRequest($request);
        $wantNfse = DocumentKind::includesNfse($kinds);
        $wantNfe = DocumentKind::includes($kinds, DocumentKind::Nfe);
        $wantNfce = DocumentKind::includes($kinds, DocumentKind::Nfce);
        $wantCte = DocumentKind::includes($kinds, DocumentKind::Cte);
        // MDF-e fora do escopo operacional (kind=MDFE retorna vazio).
        $wantSefazProjection = $wantNfe || $wantNfce;
        // kind exclusivo sem implementação/fonte → vazio
        if (! $wantNfse && ! $wantSefazProjection && ! $wantCte) {
            return response()->json([
                'data' => [],
                'meta' => ['next_cursor' => null],
            ]);
        }

        $limit = min(max((int) $request->input('limit', 25), 1), 100);
        try {
            $cursor = DocumentCatalogCursor::fromToken($request->string('cursor')->toString() ?: null);
        } catch (InvalidArgumentException) {
            return response()->json([
                'message' => 'Cursor do catálogo inválido.',
                'errors' => ['cursor' => ['Informe um cursor retornado pela API.']],
            ], 422);
        }
        $rows = collect();

        if ($wantNfse) {
            $q = NfseNote::query()->orderByDesc('id');
            if ($beforeId = $cursor->beforeId(DocumentKind::Nfse)) {
                $q->where('id', '<', $beforeId);
            }
            $this->applyCatalogFilters($q, $request);
            $rows = $rows->merge(
                $q->limit($limit + 1)->get()->map(fn (NfseNote $n) => $this->serializeNoteListItem($n))
            );
        }

        if ($wantSefazProjection) {
            $q = NfeDocument::query()->orderByDesc('id');
            if ($beforeId = $cursor->beforeId(DocumentKind::Nfe)) {
                $q->where('id', '<', $beforeId);
            }
            $this->applyNfeCatalogFilters($q, $request);
            // Filtro de modelo quando kind é exclusivo NFE ou NFCE
            if ($wantNfe && ! $wantNfce && $kinds !== []) {
                $q->where(function ($inner): void {
                    $inner->where('model', '55')->orWhereNull('model');
                });
            } elseif ($wantNfce && ! $wantNfe && $kinds !== []) {
                $q->where('model', '65');
            }
            $nfeModels = $q->limit($limit + 1)->get();
            $fullKeys = $this->fullAccessKeysForNfe($nfeModels);
            $rows = $rows->merge(
                $nfeModels->map(fn (NfeDocument $n) => $this->serializeNfeListItem($n, $fullKeys))
            );
        }

        if ($wantCte) {
            $q = CteDocument::query()->orderByDesc('id');
            if ($beforeId = $cursor->beforeId(DocumentKind::Cte)) {
                $q->where('id', '<', $beforeId);
            }
            $this->applyCteCatalogFilters($q, $request);
            $cteModels = $q->limit($limit + 1)->get();
            $fullCteKeys = $this->fullAccessKeysForCte($cteModels);
            $rows = $rows->merge(
                $cteModels->map(fn (CteDocument $n) => $this->serializeCteListItem($n, $fullCteKeys))
            );
        }

        // Ordenação estável; o cursor mantém uma posição independente por fonte.
        $sorted = $rows->sortByDesc(fn (array $r) => (int) ($r['id'] ?? 0))->values();
        $hasMore = $sorted->count() > $limit;
        $page = $hasMore ? $sorted->take($limit) : $sorted;
        $nextCursor = $hasMore ? $cursor->advance($page)->toToken() : null;

        return response()->json([
            'data' => $page->values(),
            'meta' => [
                'next_cursor' => $nextCursor,
            ],
        ]);
    }

    /**
     * Contagens de triagem no escopo dos filtros (chips clicáveis).
     * Facetas de status/competência ignoram o próprio campo para o operador ver o recorte.
     */
    public function insights(Request $request, CurrentOffice $currentOffice): JsonResponse
    {
        $currentCompetence = now()->format('Y-m');
        $kinds = DocumentKind::listFromRequest($request);
        $byKind = [
            'NFSE' => DocumentKind::includesNfse($kinds)
                ? NfseNote::query()->count()
                : 0,
            'NFE' => DocumentKind::includes($kinds, DocumentKind::Nfe)
                ? NfeDocument::query()->where(function ($q): void {
                    $q->where('model', '55')->orWhereNull('model');
                })->count()
                : 0,
            'NFCE' => DocumentKind::includes($kinds, DocumentKind::Nfce)
                ? NfeDocument::query()->where('model', '65')->count()
                : 0,
            'CTE' => DocumentKind::includes($kinds, DocumentKind::Cte)
                ? CteDocument::query()->count()
                : 0,
        ];

        if (! DocumentKind::includesNfse($kinds)) {
            return response()->json([
                'data' => [
                    'total' => array_sum($byKind),
                    'active' => 0,
                    'cancelled' => 0,
                    'superseded' => 0,
                    'substitute' => 0,
                    'review' => 0,
                    'missing_party_name' => 0,
                    'competence_current' => 0,
                    'competence_current_label' => $currentCompetence,
                    'by_kind' => $byKind,
                ],
            ]);
        }

        $scoped = NfseNote::query();
        $this->applyCatalogFilters($scoped, $request);

        $withoutStatus = NfseNote::query();
        $this->applyCatalogFilters($withoutStatus, $request, ignoreClientId: false, ignoreStatus: true);

        $withoutCompetence = NfseNote::query();
        $this->applyCatalogFilters($withoutCompetence, $request, ignoreClientId: false, ignoreStatus: false, ignoreCompetence: true);

        $missingParty = (clone $scoped)->where(function ($q): void {
            $q->where(function ($inner): void {
                $inner->whereNull('issuer_name')->orWhere('issuer_name', '');
            })->orWhere(function ($inner): void {
                $inner->whereNull('taker_name')->orWhere('taker_name', '');
            });
        });

        $authorizedStatuses = NfseNoteStatus::statusesInGroup(NfseNoteStatus::GROUP_AUTHORIZED);
        $cancelledStatuses = NfseNoteStatus::statusesInGroup(NfseNoteStatus::GROUP_CANCELLED);
        $reviewStatuses = NfseNoteStatus::statusesInGroup(NfseNoteStatus::GROUP_REVIEW);

        return response()->json([
            'data' => [
                'total' => (clone $scoped)->count(),
                // Grupo operacional Autorizada (ACTIVE + SUBSTITUTE + JUDICIAL)
                'active' => (clone $withoutStatus)->whereIn('status', $authorizedStatuses)->count(),
                // Grupo operacional Cancelada (CANCELLED + SUPERSEDED)
                'cancelled' => (clone $withoutStatus)->whereIn('status', $cancelledStatuses)->count(),
                'superseded' => (clone $withoutStatus)->whereIn('status', ['SUPERSEDED', 'REPLACED'])->count(),
                'substitute' => (clone $withoutStatus)->where('status', 'SUBSTITUTE')->count(),
                // Em revisão = situação indefinida
                'review' => (clone $withoutStatus)->whereIn('status', $reviewStatuses)->count(),
                'missing_party_name' => $missingParty->count(),
                'competence_current' => (clone $withoutCompetence)->where('competence', $currentCompetence)->count(),
                'competence_current_label' => $currentCompetence,
                'by_kind' => $byKind,
            ],
        ]);
    }

    /**
     * Agregação por cliente do escritório (aba "Por empresa").
     * Conta notas distintas com interesse em estabelecimento do cliente, no escopo dos filtros.
     */
    public function byClient(Request $request, CurrentOffice $currentOffice): JsonResponse
    {
        $officeId = $currentOffice->office()->id;

        if (! DocumentKind::includesNfse(DocumentKind::listFromRequest($request))) {
            return response()->json([
                'data' => [],
                'meta' => ['total_clients' => 0],
            ]);
        }

        $notesQuery = NfseNote::query()->where('office_id', $officeId);
        $this->applyCatalogFilters($notesQuery, $request, ignoreClientId: true);
        $noteIds = $notesQuery->pluck('id');

        if ($noteIds->isEmpty()) {
            return response()->json([
                'data' => [],
                'meta' => ['total_clients' => 0],
            ]);
        }

        // Uma linha por (nota, cliente) — evita multiplicar valor por vários interests no mesmo cliente.
        $pairs = DB::table('document_interests as di')
            ->join('dfe_documents as d', 'd.id', '=', 'di.dfe_document_id')
            ->join('nfse_notes as n', 'n.dfe_document_id', '=', 'd.id')
            ->join('establishments as e', 'e.id', '=', 'di.establishment_id')
            ->where('n.office_id', $officeId)
            ->whereNull('e.deleted_at')
            ->whereIn('n.id', $noteIds)
            ->select([
                'e.client_id',
                'n.id as note_id',
                'n.service_amount',
                'n.status',
                'n.issued_at',
            ])
            ->distinct()
            ->get();

        $byClient = $pairs->groupBy('client_id');
        $clientIds = $byClient->keys()->map(fn ($id) => (int) $id)->all();
        $clients = Client::query()
            ->where('office_id', $officeId)
            ->whereIn('id', $clientIds)
            ->with(['establishments' => fn ($q) => $q->orderBy('id')->limit(1)])
            ->get()
            ->keyBy('id');

        $data = $byClient->map(function ($rows, $clientId) use ($clients) {
            $client = $clients->get((int) $clientId);
            if ($client === null) {
                return null;
            }
            $unique = $rows->unique('note_id');
            $notesCount = $unique->count();
            $amountSum = $unique->sum(fn ($r) => (float) $r->service_amount);
            $cancelledStatuses = NfseNoteStatus::statusesInGroup(NfseNoteStatus::GROUP_CANCELLED);
            $cancelled = $unique->filter(fn ($r) => in_array((string) $r->status, $cancelledStatuses, true))->count();
            $review = $unique->where('status', 'UNKNOWN')->count();
            $lastIssued = $unique
                ->pluck('issued_at')
                ->filter()
                ->map(fn ($d) => (string) $d)
                ->sort()
                ->last();
            $primary = $client->establishments->first();

            return [
                'client_id' => $client->id,
                'legal_name' => $client->legal_name,
                'display_name' => $client->display_name,
                'name' => $client->displayLabel(),
                'root_cnpj' => $client->root_cnpj,
                'cnpj' => $primary?->cnpj,
                'notes_count' => $notesCount,
                'service_amount_sum' => number_format($amountSum, 2, '.', ''),
                'cancelled_count' => $cancelled,
                'review_count' => $review,
                'last_issued_at' => $lastIssued ? (new CarbonImmutable($lastIssued))->toIso8601String() : null,
            ];
        })
            ->filter()
            // Problemas primeiro, depois volume
            ->sortBy([
                fn ($row) => -((int) ($row['review_count'] ?? 0) + (int) ($row['cancelled_count'] ?? 0)),
                fn ($row) => -((int) ($row['notes_count'] ?? 0)),
                fn ($row) => mb_strtolower($row['legal_name'] ?? ''),
            ])
            ->values();

        return response()->json([
            'data' => $data,
            'meta' => [
                'total_clients' => $data->count(),
            ],
        ]);
    }

    public function show(string $accessKey): JsonResponse
    {
        $note = NfseNote::query()->where('access_key', $accessKey)->with('document')->first();
        if ($note !== null) {
            $events = NfseEvent::query()
                ->where('access_key', $accessKey)
                ->orderBy('event_at')
                ->orderBy('id')
                ->get();

            $notePayload = $this->serializeNoteListItem($note);
            $notePayload['dfe_document_id'] = $note->dfe_document_id;
            $notePayload['office_id'] = $note->office_id;
            $notePayload['has_full_xml'] = true;
            $notePayload['xml_completeness'] = 'FULL';

            return response()->json([
                'data' => [
                    'note' => $notePayload,
                    'events' => $events,
                    'document' => [
                        'id' => $note->document?->id,
                        'sha256' => $note->document?->sha256,
                        'schema_version' => $note->document?->schema_version,
                        'parse_status' => $note->document?->parse_status,
                        'parse_alert' => $note->document?->parse_alert,
                        'byte_size' => $note->document?->byte_size,
                        'document_type' => $note->document?->document_type,
                    ],
                ],
            ]);
        }

        // Prefer full (is_summary=false) sobre resumo para entrega de XML.
        $nfe = NfeDocument::query()
            ->where('access_key', $accessKey)
            ->orderBy('is_summary') // false first
            ->with('document')
            ->first();

        if ($nfe !== null) {
            $hasFull = NfeDocument::query()
                ->where('access_key', $accessKey)
                ->where('is_summary', false)
                ->exists();

            $payload = $this->serializeNfeListItem($nfe);
            $payload['dfe_document_id'] = $nfe->dfe_document_id;
            $payload['office_id'] = $nfe->office_id;
            $payload['has_full_xml'] = $hasFull;
            $payload['xml_completeness'] = $hasFull ? 'FULL' : 'SUMMARY_ONLY';

            return response()->json([
                'data' => [
                    'note' => $payload,
                    'events' => [],
                    'document' => [
                        'id' => $nfe->document?->id,
                        'sha256' => $nfe->document?->sha256,
                        'schema_version' => $nfe->document?->schema_version,
                        'parse_status' => $nfe->document?->parse_status,
                        'parse_alert' => $nfe->document?->parse_alert,
                        'byte_size' => $nfe->document?->byte_size,
                        'document_type' => $nfe->document?->document_type,
                    ],
                ],
            ]);
        }

        $cte = CteDocument::query()
            ->where('access_key', $accessKey)
            ->orderBy('is_summary')
            ->with('document')
            ->first();

        if ($cte !== null) {
            $hasFull = CteDocument::query()
                ->where('access_key', $accessKey)
                ->where('is_summary', false)
                ->exists();

            $payload = $this->serializeCteListItem($cte);
            $payload['dfe_document_id'] = $cte->dfe_document_id;
            $payload['office_id'] = $cte->office_id;
            $payload['has_full_xml'] = $hasFull;
            $payload['xml_completeness'] = $hasFull ? 'FULL' : 'SUMMARY_ONLY';

            return response()->json([
                'data' => [
                    'note' => $payload,
                    'events' => [],
                    'document' => [
                        'id' => $cte->document?->id,
                        'sha256' => $cte->document?->sha256,
                        'schema_version' => $cte->document?->schema_version,
                        'parse_status' => $cte->document?->parse_status,
                        'parse_alert' => $cte->document?->parse_alert,
                        'byte_size' => $cte->document?->byte_size,
                        'document_type' => $cte->document?->document_type,
                    ],
                ],
            ]);
        }

        abort(404, 'Documento não encontrado.');
    }

    /**
     * Solicita desbloqueio de XML completo (ciência unlock) para NF-e só-resumo.
     * VIEWER: 403. Full já presente: no-op 200.
     */
    public function unlockXml(
        string $accessKey,
        CurrentOffice $currentOffice,
        NfeXmlUnlockService $unlock,
        AuditLogger $audit,
    ): JsonResponse {
        $role = $currentOffice->role();
        if ($role === null || ! $role->canManifestNfe()) {
            return response()->json(['message' => 'Ação não autorizada para o perfil atual.'], 403);
        }

        $result = $unlock->unlock($accessKey, $currentOffice->office()->id);

        $audit->record(
            'nfe.xml_unlock',
            in_array($result['status'], ['already_full', 'accepted'], true) ? 'SUCCESS' : 'INFO',
            null,
            [
                'access_key' => $accessKey,
                'status' => $result['status'],
                'c_stat' => $result['c_stat'] ?? null,
            ]
        );

        $http = match ($result['status']) {
            'already_full', 'accepted' => 200,
            'not_found' => 404,
            'flag_off', 'pending_integration', 'no_credential', 'rejected_local',
            'rejected_sefaz', 'validation_error', 'error' => 422,
            default => 422,
        };

        return response()->json(['data' => $result], $http);
    }

    /**
     * Manifestação do destinatário (ciência / conclusivas). VIEWER: 403.
     */
    public function manifest(
        string $accessKey,
        Request $request,
        CurrentOffice $currentOffice,
        NfeManifestationService $manifestation,
        AuditLogger $audit,
    ): JsonResponse {
        $role = $currentOffice->role();
        if ($role === null || ! $role->canManifestNfe()) {
            return response()->json(['message' => 'Ação não autorizada para o perfil atual.'], 403);
        }

        $validated = $request->validate([
            'type' => ['required', 'string'],
            'justification' => ['nullable', 'string', 'max:255'],
            'purpose' => ['nullable', 'string', 'in:UNLOCK_XML,FISCAL'],
        ]);

        $type = NfeManifestationType::tryFromInput((string) $validated['type']);
        if ($type === null) {
            return response()->json([
                'message' => 'Tipo de manifestação inválido.',
                'errors' => ['type' => ['Use CIENCIA, CONFIRMACAO, DESCONHECIMENTO ou NAO_REALIZADA.']],
            ], 422);
        }

        $result = $manifestation->manifest(
            $accessKey,
            $currentOffice->office()->id,
            $type,
            $validated['justification'] ?? null,
            (string) ($validated['purpose'] ?? 'UNLOCK_XML'),
        );

        $audit->record(
            'nfe.manifestation',
            $result['status'] === 'accepted' ? 'SUCCESS' : 'INFO',
            null,
            [
                'access_key' => $accessKey,
                'type' => $type->value,
                'tp_evento' => $type->tpEvento(),
                'purpose' => $validated['purpose'] ?? 'UNLOCK_XML',
                'status' => $result['status'],
                'c_stat' => $result['c_stat'] ?? null,
                // Nunca auditar justificativa completa se contiver dados sensíveis demais — só tamanho
                'justification_len' => isset($validated['justification'])
                    ? mb_strlen((string) $validated['justification'])
                    : 0,
            ]
        );

        $http = match ($result['status']) {
            'already_full', 'accepted' => 200,
            'not_found' => 404,
            default => 422,
        };

        return response()->json(['data' => $result], $http);
    }

    public function downloadXml(
        string $accessKey,
        SecureObjectStore $store,
        CurrentOffice $currentOffice,
        AuditLogger $audit,
    ): StreamedResponse {
        $note = NfseNote::query()->where('access_key', $accessKey)->with('document')->first();
        if ($note !== null) {
            $doc = $note->document;
            $bytes = $store->get($doc->vault_object_id, [
                'office_id' => $doc->office_id,
                'sha256' => $doc->sha256,
            ]);
            $audit->record('xml.download', 'SUCCESS', $note, [
                'access_key' => $accessKey,
                'sha256' => $doc->sha256,
                'byte_size' => $doc->byte_size,
                'xml_kind' => 'NFSE',
            ]);

            return response()->streamDownload(function () use ($bytes): void {
                echo $bytes;
            }, $accessKey.'.xml', [
                'Content-Type' => 'application/xml',
            ]);
        }

        // Prefer full over summary for the same access_key.
        $nfe = NfeDocument::query()
            ->where('access_key', $accessKey)
            ->orderBy('is_summary') // false (0) before true (1)
            ->with('document')
            ->first();

        if ($nfe !== null) {
            $doc = $nfe->document;
            $bytes = $store->get($doc->vault_object_id, [
                'office_id' => $doc->office_id,
                'sha256' => $doc->sha256,
            ]);

            $audit->record('xml.download', 'SUCCESS', $nfe, [
                'access_key' => $accessKey,
                'sha256' => $doc->sha256,
                'byte_size' => $doc->byte_size,
                'xml_kind' => $nfe->is_summary ? 'NFE_SUMMARY' : 'NFE_FULL',
                'is_summary' => $nfe->is_summary,
            ]);

            $suffix = $nfe->is_summary ? '-resumo' : '';

            return response()->streamDownload(function () use ($bytes): void {
                echo $bytes;
            }, $accessKey.$suffix.'.xml', [
                'Content-Type' => 'application/xml',
                'X-Xml-Completeness' => $nfe->is_summary ? 'SUMMARY' : 'FULL',
            ]);
        }

        $cte = CteDocument::query()
            ->where('access_key', $accessKey)
            ->orderBy('is_summary')
            ->with('document')
            ->first();

        if ($cte !== null) {
            $doc = $cte->document;
            $bytes = $store->get($doc->vault_object_id, [
                'office_id' => $doc->office_id,
                'sha256' => $doc->sha256,
            ]);

            $audit->record('xml.download', 'SUCCESS', $cte, [
                'access_key' => $accessKey,
                'sha256' => $doc->sha256,
                'byte_size' => $doc->byte_size,
                'xml_kind' => $cte->is_summary ? 'CTE_SUMMARY' : 'CTE_FULL',
                'is_summary' => $cte->is_summary,
            ]);

            $suffix = $cte->is_summary ? '-resumo' : '';

            return response()->streamDownload(function () use ($bytes): void {
                echo $bytes;
            }, $accessKey.$suffix.'.xml', [
                'Content-Type' => 'application/xml',
                'X-Xml-Completeness' => $cte->is_summary ? 'SUMMARY' : 'FULL',
            ]);
        }

        abort(404, 'Documento não encontrado.');
    }

    /**
     * Filtros compartilhados entre listagem, insights e agregação por cliente.
     *
     * @param  Builder<NfseNote>  $query
     */
    private function applyCatalogFilters(
        Builder $query,
        Request $request,
        bool $ignoreClientId = false,
        bool $ignoreStatus = false,
        bool $ignoreCompetence = false,
    ): void {
        // Busca de triagem: número, nomes, CNPJ (parcial) ou chave.
        $search = $request->string('q')->toString();
        if ($search === '') {
            $search = $request->string('access_key')->toString();
        }
        if ($search !== '') {
            $needle = '%'.mb_strtolower($search).'%';
            $cnpjNeedle = '%'.strtoupper(preg_replace('/[^a-zA-Z0-9]/', '', $search) ?? $search).'%';
            $query->where(function ($q) use ($needle, $cnpjNeedle, $search): void {
                $q->whereRaw('LOWER(access_key) LIKE ?', [$needle])
                    ->orWhereRaw('LOWER(COALESCE(number, \'\')) LIKE ?', [$needle])
                    ->orWhereRaw('LOWER(COALESCE(issuer_name, \'\')) LIKE ?', [$needle])
                    ->orWhereRaw('LOWER(COALESCE(taker_name, \'\')) LIKE ?', [$needle])
                    ->orWhereRaw('LOWER(COALESCE(intermediary_name, \'\')) LIKE ?', [$needle])
                    ->orWhere('issuer_cnpj', 'like', $cnpjNeedle)
                    ->orWhere('taker_cnpj', 'like', $cnpjNeedle)
                    ->orWhere('intermediary_cnpj', 'like', $cnpjNeedle)
                    ->orWhere('access_key', $search);
            });
        }
        if ($v = $request->string('issuer_cnpj')->toString()) {
            $query->where('issuer_cnpj', strtoupper(preg_replace('/\W/', '', $v) ?? ''));
        }
        if ($v = $request->string('taker_cnpj')->toString()) {
            $query->where('taker_cnpj', strtoupper(preg_replace('/\W/', '', $v) ?? ''));
        }
        if (! $ignoreCompetence && ($v = $request->string('competence')->toString())) {
            $query->where('competence', $v);
        }
        if (! $ignoreStatus && ($v = $request->string('status')->toString())) {
            $statuses = NfseNoteStatus::statusesForFilter($v);
            if (count($statuses) === 1) {
                $query->where('status', $statuses[0]);
            } elseif (count($statuses) > 1) {
                $query->whereIn('status', $statuses);
            }
        }
        if ($v = $request->string('fiscal_role')->toString()) {
            $query->where('fiscal_role', $v);
        }
        if ($direction = DocumentDirection::tryFromRequest($request->string('direction')->toString())) {
            $query->where('direction', $direction->value);
        }
        if (! $ignoreClientId && ($clientId = $request->integer('client_id'))) {
            $query->whereHas('document.interests.establishment', function ($interest) use ($clientId): void {
                $interest->where('client_id', $clientId);
            });
        }
        if ($establishmentId = $request->integer('establishment_id')) {
            $query->whereHas('document.interests', function ($interest) use ($establishmentId): void {
                $interest->where('establishment_id', $establishmentId);
            });
        }
        if ($from = $request->string('issued_from')->toString()) {
            $query->whereDate('issued_at', '>=', $from);
        }
        if ($to = $request->string('issued_to')->toString()) {
            $query->whereDate('issued_at', '<=', $to);
        }
        // Fila de triagem: falta nome de emitente ou tomador.
        if ($request->boolean('missing_party_name')) {
            $query->where(function ($q): void {
                $q->where(function ($inner): void {
                    $inner->whereNull('issuer_name')->orWhere('issuer_name', '');
                })->orWhere(function ($inner): void {
                    $inner->whereNull('taker_name')->orWhere('taker_name', '');
                });
            });
        }
    }

    /**
     * Projeção legível para triagem — sem XML, vault ou metadados sensíveis.
     *
     * @return array<string, mixed>
     */
    private function serializeNoteListItem(NfseNote $note): array
    {
        $cStat = $note->official_status_code;

        $kind = DocumentKind::Nfse;

        return [
            'id' => $note->id,
            'kind' => $kind->value,
            'kind_label' => $kind->label(),
            'source' => $kind->defaultSource(),
            'capture_available' => $kind->captureAvailable(),
            'access_key' => $note->access_key,
            'number' => $note->number,
            'issuer_cnpj' => $note->issuer_cnpj,
            'issuer_name' => $note->issuer_name,
            'taker_cnpj' => $note->taker_cnpj,
            'taker_name' => $note->taker_name,
            'intermediary_cnpj' => $note->intermediary_cnpj,
            'intermediary_name' => $note->intermediary_name,
            'fiscal_role' => $note->fiscal_role?->value ?? $note->fiscal_role,
            'direction' => ($dir = $note->direction ?? DocumentDirection::fromFiscalRole(
                $note->fiscal_role instanceof FiscalRole ? $note->fiscal_role : null
            ))->value,
            'direction_label' => $dir->label(),
            'competence' => $note->competence,
            'issued_at' => $note->issued_at?->toIso8601String(),
            'service_amount' => $note->service_amount,
            'issue_location' => $note->issue_location,
            'service_location' => $note->service_location,
            'status' => $note->status,
            'status_label' => NfseNoteStatus::label((string) $note->status),
            'official_status_code' => $cStat,
            'official_status_label' => NfseNoteStatus::officialDescription(
                (string) $note->status,
                $cStat
            ),
        ];
    }

    /**
     * @param  array<string, true>  $fullKeys  access_keys com full irmão (para linhas resumo)
     * @return array<string, mixed>
     */
    private function serializeNfeListItem(NfeDocument $doc, array $fullKeys = []): array
    {
        $kind = ($doc->model === '65') ? DocumentKind::Nfce : DocumentKind::Nfe;
        $isSummary = (bool) $doc->is_summary;
        $hasFull = ! $isSummary || isset($fullKeys[$doc->access_key]);
        $source = str_starts_with((string) $doc->schema_hint, 'import:') ? 'IMPORT' : $kind->defaultSource();

        return [
            'id' => $doc->id,
            'kind' => $kind->value,
            'kind_label' => $kind->label(),
            'source' => $source,
            'capture_available' => $kind->captureAvailable(),
            'access_key' => $doc->access_key,
            'number' => $doc->number,
            'issuer_cnpj' => $doc->issuer_cnpj,
            'issuer_name' => $doc->issuer_name,
            'taker_cnpj' => $doc->recipient_cnpj,
            'taker_name' => $doc->recipient_name,
            'intermediary_cnpj' => null,
            'intermediary_name' => null,
            'fiscal_role' => $doc->fiscal_role?->value ?? $doc->fiscal_role,
            'direction' => $doc->direction?->value ?? DocumentDirection::In->value,
            'direction_label' => ($doc->direction ?? DocumentDirection::In)->label(),
            'competence' => $doc->issued_at?->format('Y-m'),
            'issued_at' => $doc->issued_at?->toIso8601String(),
            'service_amount' => $doc->total_amount,
            'issue_location' => null,
            'service_location' => null,
            'status' => $doc->status,
            'status_label' => ($isSummary && ! $hasFull) ? 'Somente resumo' : 'XML completo',
            'official_status_code' => $doc->official_status_code,
            'official_status_label' => ($isSummary && ! $hasFull)
                ? 'Resumo NF-e — XML completo ainda não disponível'
                : 'Documento completo (procNFe)',
            'is_summary' => $isSummary,
            'has_full_xml' => $hasFull,
            'xml_completeness' => $hasFull ? 'FULL' : 'SUMMARY_ONLY',
            'manifestation_status' => $doc->manifestation_status,
        ];
    }

    /**
     * @param  array<string, true>  $fullKeys
     * @return array<string, mixed>
     */
    private function serializeCteListItem(CteDocument $doc, array $fullKeys = []): array
    {
        $kind = DocumentKind::Cte;
        $isSummary = (bool) $doc->is_summary;
        $hasFull = ! $isSummary || isset($fullKeys[$doc->access_key]);

        return [
            'id' => $doc->id,
            'kind' => $kind->value,
            'kind_label' => $kind->label(),
            'source' => $kind->defaultSource(),
            'capture_available' => $kind->captureAvailable(),
            'access_key' => $doc->access_key,
            'number' => $doc->number,
            'issuer_cnpj' => $doc->issuer_cnpj,
            'issuer_name' => $doc->issuer_name,
            'taker_cnpj' => $doc->taker_cnpj,
            'taker_name' => $doc->taker_name,
            'intermediary_cnpj' => null,
            'intermediary_name' => null,
            'fiscal_role' => $doc->fiscal_role?->value ?? $doc->fiscal_role,
            'direction' => $doc->direction?->value ?? DocumentDirection::In->value,
            'direction_label' => ($doc->direction ?? DocumentDirection::In)->label(),
            'competence' => $doc->issued_at?->format('Y-m'),
            'issued_at' => $doc->issued_at?->toIso8601String(),
            'service_amount' => $doc->total_amount,
            'issue_location' => null,
            'service_location' => null,
            'status' => $doc->status,
            'status_label' => ($isSummary && ! $hasFull) ? 'Somente resumo' : 'XML completo',
            'official_status_code' => $doc->official_status_code,
            'official_status_label' => ($isSummary && ! $hasFull) ? 'Resumo CT-e' : 'Documento completo (procCTe)',
            'is_summary' => $isSummary,
            'has_full_xml' => $hasFull,
            'xml_completeness' => $hasFull ? 'FULL' : 'SUMMARY_ONLY',
        ];
    }

    /**
     * @param  Builder<CteDocument>  $query
     */
    private function applyCteCatalogFilters(Builder $query, Request $request): void
    {
        $search = $request->string('q')->toString();
        if ($search === '') {
            $search = $request->string('access_key')->toString();
        }
        if ($search !== '') {
            $needle = '%'.mb_strtolower($search).'%';
            $cnpjNeedle = '%'.strtoupper(preg_replace('/[^a-zA-Z0-9]/', '', $search) ?? $search).'%';
            $query->where(function ($q) use ($needle, $cnpjNeedle, $search): void {
                $q->whereRaw('LOWER(access_key) LIKE ?', [$needle])
                    ->orWhereRaw('LOWER(COALESCE(number, \'\')) LIKE ?', [$needle])
                    ->orWhereRaw('LOWER(COALESCE(issuer_name, \'\')) LIKE ?', [$needle])
                    ->orWhereRaw('LOWER(COALESCE(taker_name, \'\')) LIKE ?', [$needle])
                    ->orWhere('issuer_cnpj', 'like', $cnpjNeedle)
                    ->orWhere('taker_cnpj', 'like', $cnpjNeedle)
                    ->orWhere('access_key', $search);
            });
        }
        if ($v = $request->string('issuer_cnpj')->toString()) {
            $query->where('issuer_cnpj', strtoupper(preg_replace('/\W/', '', $v) ?? ''));
        }
        if ($v = $request->string('taker_cnpj')->toString()) {
            $query->where('taker_cnpj', strtoupper(preg_replace('/\W/', '', $v) ?? ''));
        }
        if ($v = $request->string('status')->toString()) {
            $query->where('status', strtoupper($v));
        }
        if ($direction = DocumentDirection::tryFromRequest($request->string('direction')->toString())) {
            $query->where('direction', $direction->value);
        }
        if ($clientId = $request->integer('client_id')) {
            $query->where(function ($q) use ($clientId): void {
                $q->whereHas('document.interests.establishment', function ($interest) use ($clientId): void {
                    $interest->where('client_id', $clientId);
                })->orWhereIn('issuer_cnpj', function ($sub) use ($clientId): void {
                    $sub->select('cnpj')
                        ->from('establishments')
                        ->where('client_id', $clientId)
                        ->whereNull('deleted_at');
                })->orWhereIn('taker_cnpj', function ($sub) use ($clientId): void {
                    $sub->select('cnpj')
                        ->from('establishments')
                        ->where('client_id', $clientId)
                        ->whereNull('deleted_at');
                });
            });
        }
        if ($establishmentId = $request->integer('establishment_id')) {
            $query->where(function ($q) use ($establishmentId): void {
                $q->whereHas('document.interests', function ($interest) use ($establishmentId): void {
                    $interest->where('establishment_id', $establishmentId);
                })->orWhereIn('issuer_cnpj', function ($sub) use ($establishmentId): void {
                    $sub->select('cnpj')->from('establishments')->where('id', $establishmentId);
                })->orWhereIn('taker_cnpj', function ($sub) use ($establishmentId): void {
                    $sub->select('cnpj')->from('establishments')->where('id', $establishmentId);
                });
            });
        }
        if ($from = $request->string('issued_from')->toString()) {
            $query->whereDate('issued_at', '>=', $from);
        }
        if ($to = $request->string('issued_to')->toString()) {
            $query->whereDate('issued_at', '<=', $to);
        }
    }

    /**
     * @param  Builder<NfeDocument>  $query
     */
    private function applyNfeCatalogFilters(Builder $query, Request $request): void
    {
        $search = $request->string('q')->toString();
        if ($search === '') {
            $search = $request->string('access_key')->toString();
        }
        if ($search !== '') {
            $needle = '%'.mb_strtolower($search).'%';
            $cnpjNeedle = '%'.strtoupper(preg_replace('/[^a-zA-Z0-9]/', '', $search) ?? $search).'%';
            $query->where(function ($q) use ($needle, $cnpjNeedle, $search): void {
                $q->whereRaw('LOWER(access_key) LIKE ?', [$needle])
                    ->orWhereRaw('LOWER(COALESCE(number, \'\')) LIKE ?', [$needle])
                    ->orWhereRaw('LOWER(COALESCE(issuer_name, \'\')) LIKE ?', [$needle])
                    ->orWhereRaw('LOWER(COALESCE(recipient_name, \'\')) LIKE ?', [$needle])
                    ->orWhere('issuer_cnpj', 'like', $cnpjNeedle)
                    ->orWhere('recipient_cnpj', 'like', $cnpjNeedle)
                    ->orWhere('access_key', $search);
            });
        }
        if ($v = $request->string('issuer_cnpj')->toString()) {
            $query->where('issuer_cnpj', strtoupper(preg_replace('/\W/', '', $v) ?? ''));
        }
        if ($v = $request->string('taker_cnpj')->toString()) {
            $query->where('recipient_cnpj', strtoupper(preg_replace('/\W/', '', $v) ?? ''));
        }
        if ($v = $request->string('status')->toString()) {
            $query->where('status', strtoupper($v));
        }
        if ($direction = DocumentDirection::tryFromRequest($request->string('direction')->toString())) {
            $query->where('direction', $direction->value);
        }
        if ($clientId = $request->integer('client_id')) {
            $query->where(function ($q) use ($clientId): void {
                $q->whereHas('document.interests.establishment', function ($interest) use ($clientId): void {
                    $interest->where('client_id', $clientId);
                })->orWhereIn('issuer_cnpj', function ($sub) use ($clientId): void {
                    $sub->select('cnpj')
                        ->from('establishments')
                        ->where('client_id', $clientId)
                        ->whereNull('deleted_at');
                })->orWhereIn('recipient_cnpj', function ($sub) use ($clientId): void {
                    $sub->select('cnpj')
                        ->from('establishments')
                        ->where('client_id', $clientId)
                        ->whereNull('deleted_at');
                });
            });
        }
        if ($establishmentId = $request->integer('establishment_id')) {
            $query->where(function ($q) use ($establishmentId): void {
                $q->whereHas('document.interests', function ($interest) use ($establishmentId): void {
                    $interest->where('establishment_id', $establishmentId);
                })->orWhereIn('issuer_cnpj', function ($sub) use ($establishmentId): void {
                    $sub->select('cnpj')->from('establishments')->where('id', $establishmentId);
                })->orWhereIn('recipient_cnpj', function ($sub) use ($establishmentId): void {
                    $sub->select('cnpj')->from('establishments')->where('id', $establishmentId);
                });
            });
        }
        if ($from = $request->string('issued_from')->toString()) {
            $query->whereDate('issued_at', '>=', $from);
        }
        if ($to = $request->string('issued_to')->toString()) {
            $query->whereDate('issued_at', '<=', $to);
        }
    }

    /**
     * @param  \Illuminate\Support\Collection<int, NfeDocument>  $models
     * @return array<string, true>
     */
    private function fullAccessKeysForNfe($models): array
    {
        $summaryKeys = $models->where('is_summary', true)->pluck('access_key')->filter()->unique()->values()->all();
        if ($summaryKeys === []) {
            return [];
        }
        $officeIds = $models->pluck('office_id')->unique()->all();

        return NfeDocument::query()
            ->whereIn('office_id', $officeIds)
            ->whereIn('access_key', $summaryKeys)
            ->where('is_summary', false)
            ->pluck('access_key')
            ->flip()
            ->map(fn () => true)
            ->all();
    }

    /**
     * @param  \Illuminate\Support\Collection<int, CteDocument>  $models
     * @return array<string, true>
     */
    private function fullAccessKeysForCte($models): array
    {
        $summaryKeys = $models->where('is_summary', true)->pluck('access_key')->filter()->unique()->values()->all();
        if ($summaryKeys === []) {
            return [];
        }
        $officeIds = $models->pluck('office_id')->unique()->all();

        return CteDocument::query()
            ->whereIn('office_id', $officeIds)
            ->whereIn('access_key', $summaryKeys)
            ->where('is_summary', false)
            ->pluck('access_key')
            ->flip()
            ->map(fn () => true)
            ->all();
    }
}

<?php

namespace App\Http\Controllers\Api\V1;

use App\Contracts\SecureObjectStore;
use App\Http\Controllers\Controller;
use App\Models\NfseNote;
use App\Support\CurrentOffice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;

class NoteController extends Controller
{
    public function index(Request $request, CurrentOffice $currentOffice): JsonResponse
    {
        $query = NfseNote::query()->orderByDesc('id');

        if ($v = $request->string('access_key')->toString()) {
            $query->where('access_key', $v);
        }
        if ($v = $request->string('issuer_cnpj')->toString()) {
            $query->where('issuer_cnpj', strtoupper(preg_replace('/\W/', '', $v) ?? ''));
        }
        if ($v = $request->string('taker_cnpj')->toString()) {
            $query->where('taker_cnpj', strtoupper(preg_replace('/\W/', '', $v) ?? ''));
        }
        if ($v = $request->string('competence')->toString()) {
            $query->where('competence', $v);
        }
        if ($v = $request->string('status')->toString()) {
            $query->where('status', $v);
        }
        if ($v = $request->string('fiscal_role')->toString()) {
            $query->where('fiscal_role', $v);
        }
        if ($clientId = $request->integer('client_id')) {
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
        if ($cursor = $request->string('cursor')->toString()) {
            $query->where('id', '<', (int) $cursor);
        }

        $limit = min(max((int) $request->input('limit', 25), 1), 100);
        $items = $query->limit($limit + 1)->get();
        $hasMore = $items->count() > $limit;
        if ($hasMore) {
            $items = $items->take($limit);
        }

        return response()->json([
            'data' => $items->values(),
            'meta' => [
                'next_cursor' => $hasMore ? (string) $items->last()->id : null,
            ],
        ]);
    }

    public function show(string $accessKey): JsonResponse
    {
        $note = NfseNote::query()->where('access_key', $accessKey)->firstOrFail();

        return response()->json(['data' => $note->load('document')]);
    }

    public function downloadXml(string $accessKey, SecureObjectStore $store, CurrentOffice $currentOffice): StreamedResponse
    {
        $note = NfseNote::query()->where('access_key', $accessKey)->with('document')->firstOrFail();
        $doc = $note->document;

        Log::info('xml.download', [
            'user_id' => auth()->id(),
            'office_id' => $currentOffice->id(),
            'access_key' => $accessKey,
        ]);

        $bytes = $store->get($doc->vault_object_id, [
            'office_id' => $doc->office_id,
            'sha256' => $doc->sha256,
        ]);

        return response()->streamDownload(function () use ($bytes): void {
            echo $bytes;
        }, $accessKey.'.xml', [
            'Content-Type' => 'application/xml',
        ]);
    }
}

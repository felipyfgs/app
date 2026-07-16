<?php

namespace App\Http\Controllers\Api\V1\Fiscal;

use App\Enums\OfficeRole;
use App\Http\Controllers\Controller;
use App\Jobs\Fiscal\RefreshTaxProcessesJob;
use App\Models\Client;
use App\Models\FiscalTaxProcess;
use App\Support\CurrentOffice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * APIs tenant-scoped de Processos fiscais (office da sessão).
 */
final class TaxProcessController extends Controller
{
    public function index(Request $request, CurrentOffice $currentOffice): JsonResponse
    {
        $office = $currentOffice->office();
        abort_if($office === null, 403);

        $query = FiscalTaxProcess::query()
            ->where('office_id', $office->id)
            ->orderByDesc('refreshed_at')
            ->orderByDesc('id');

        if ($request->filled('client_id')) {
            $query->where('client_id', (int) $request->query('client_id'));
        }
        if ($request->filled('status')) {
            $query->where('status', (string) $request->query('status'));
        }

        $perPage = min(100, max(1, (int) $request->query('per_page', 25)));
        $page = $query->paginate($perPage);

        return response()->json([
            'data' => array_map(
                static fn (FiscalTaxProcess $row) => $row->toPublicArray(),
                $page->items(),
            ),
            'meta' => [
                'current_page' => $page->currentPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'last_page' => $page->lastPage(),
            ],
        ]);
    }

    public function showForClient(int $clientId, CurrentOffice $currentOffice): JsonResponse
    {
        $office = $currentOffice->office();
        abort_if($office === null, 403);

        $client = Client::query()
            ->where('office_id', $office->id)
            ->whereKey($clientId)
            ->firstOrFail();

        $rows = FiscalTaxProcess::query()
            ->where('office_id', $office->id)
            ->where('client_id', $client->id)
            ->orderByDesc('refreshed_at')
            ->get();

        return response()->json([
            'data' => [
                'client_id' => $client->id,
                'processes' => $rows->map(static fn (FiscalTaxProcess $r) => $r->toPublicArray())->values(),
            ],
        ]);
    }

    public function show(int $id, CurrentOffice $currentOffice): JsonResponse
    {
        $office = $currentOffice->office();
        abort_if($office === null, 403);

        $row = FiscalTaxProcess::query()
            ->where('office_id', $office->id)
            ->whereKey($id)
            ->first();

        // Inacessível de outro office — 404 sem revelar existência
        abort_if($row === null, 404);

        return response()->json(['data' => $row->toPublicArray()]);
    }

    public function refresh(int $clientId, CurrentOffice $currentOffice): JsonResponse
    {
        $office = $currentOffice->office();
        abort_if($office === null, 403);
        $role = $currentOffice->role();
        if ($role === null || ! in_array($role, [OfficeRole::Admin, OfficeRole::Operator], true)) {
            abort(403);
        }

        $client = Client::query()
            ->where('office_id', $office->id)
            ->whereKey($clientId)
            ->firstOrFail();

        $job = RefreshTaxProcessesJob::dispatchIfAllowed(
            (int) $office->id,
            (int) $client->id,
            bin2hex(random_bytes(8)),
        );

        if ($job === null) {
            return response()->json([
                'message' => 'Capability tax_processes desabilitada ou kill switch ativo.',
                'data' => ['queued' => false, 'client_id' => $client->id],
            ], 423);
        }

        return response()->json([
            'data' => [
                'queued' => true,
                'client_id' => $client->id,
            ],
        ], 202);
    }
}

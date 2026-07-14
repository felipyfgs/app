<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Cnpj;
use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Support\CurrentOffice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClientController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Client::class);

        $query = Client::query()->withCount('establishments')->orderBy('name');

        if ($search = $request->string('q')->toString()) {
            $query->where(function ($q) use ($search): void {
                $q->whereRaw('LOWER(name) LIKE ?', ['%'.mb_strtolower($search).'%'])
                    ->orWhere('root_cnpj', 'like', '%'.strtoupper($search).'%');
            });
        }

        $perPage = min(max((int) $request->input('per_page', 20), 1), 100);
        $paginator = $query->paginate($perPage);

        return response()->json([
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function store(Request $request, CurrentOffice $currentOffice): JsonResponse
    {
        $this->authorize('create', Client::class);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'cnpj' => ['required', 'string'],
            'notes' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        try {
            $cnpj = Cnpj::parse($data['cnpj']);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'errors' => ['cnpj' => [$e->getMessage()]],
            ], 422);
        }

        $officeId = $currentOffice->office()->id;

        $exists = Client::query()
            ->where('office_id', $officeId)
            ->where('root_cnpj', $cnpj->root())
            ->exists();

        if ($exists) {
            return response()->json([
                'message' => 'Já existe cliente com esta raiz de CNPJ neste escritório.',
                'errors' => ['cnpj' => ['Raiz de CNPJ duplicada.']],
            ], 422);
        }

        $client = Client::query()->create([
            'office_id' => $officeId,
            'name' => $data['name'],
            'root_cnpj' => $cnpj->root(),
            'notes' => $data['notes'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]);

        return response()->json(['data' => $client], 201);
    }

    public function show(Client $client): JsonResponse
    {
        $this->authorize('view', $client);

        $client->load(['establishments' => fn ($q) => $q->orderBy('cnpj')]);

        return response()->json(['data' => $client]);
    }

    public function update(Request $request, Client $client): JsonResponse
    {
        $this->authorize('update', $client);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $client->fill($data);
        $client->save();

        return response()->json(['data' => $client->fresh()]);
    }
}

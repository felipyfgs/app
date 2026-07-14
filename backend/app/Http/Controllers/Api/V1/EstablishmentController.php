<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Cnpj;
use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Establishment;
use App\Support\CurrentOffice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EstablishmentController extends Controller
{
    public function store(Request $request, Client $client, CurrentOffice $currentOffice): JsonResponse
    {
        $this->authorize('create', Establishment::class);
        $this->authorize('view', $client);

        $data = $request->validate([
            'cnpj' => ['required', 'string'],
            'trade_name' => ['nullable', 'string', 'max:255'],
            'is_matrix' => ['sometimes', 'boolean'],
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

        if ($cnpj->root() !== $client->root_cnpj) {
            return response()->json([
                'message' => 'A raiz do CNPJ do estabelecimento é incompatível com o cliente.',
                'errors' => ['cnpj' => ['Raiz incompatível com o cliente.']],
            ], 422);
        }

        $officeId = $currentOffice->office()->id;

        $duplicate = Establishment::query()
            ->where('office_id', $officeId)
            ->where('cnpj', $cnpj->value())
            ->exists();

        if ($duplicate) {
            return response()->json([
                'message' => 'Já existe estabelecimento com este CNPJ neste escritório.',
                'errors' => ['cnpj' => ['CNPJ duplicado no escritório.']],
            ], 422);
        }

        $establishment = Establishment::query()->create([
            'office_id' => $officeId,
            'client_id' => $client->id,
            'cnpj' => $cnpj->value(),
            'trade_name' => $data['trade_name'] ?? null,
            'is_matrix' => $data['is_matrix'] ?? false,
            'is_active' => $data['is_active'] ?? true,
        ]);

        return response()->json(['data' => $establishment], 201);
    }

    public function update(Request $request, Establishment $establishment): JsonResponse
    {
        $this->authorize('update', $establishment);

        $data = $request->validate([
            'trade_name' => ['nullable', 'string', 'max:255'],
            'is_matrix' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $establishment->fill($data);
        $establishment->save();

        return response()->json(['data' => $establishment->fresh()]);
    }
}

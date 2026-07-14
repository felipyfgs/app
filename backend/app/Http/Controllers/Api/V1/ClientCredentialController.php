<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Policies\ClientCredentialPolicy;
use App\Services\Certificates\CredentialService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Throwable;

class ClientCredentialController extends Controller
{
    public function show(Client $client, CredentialService $credentials): JsonResponse
    {
        $this->authorize('view', $client);
        $this->assertAdmin($client);

        $active = $credentials->activeFor($client);

        return response()->json([
            'data' => $active?->toPublicArray(),
        ]);
    }

    public function store(Request $request, Client $client, CredentialService $credentials): JsonResponse
    {
        $this->authorize('update', $client);
        $this->assertAdmin($client);

        $data = $request->validate([
            'pfx' => ['required', 'file', 'max:5120'],
            'password' => ['required', 'string'],
        ]);

        try {
            $binary = file_get_contents($data['pfx']->getRealPath());
            if ($binary === false) {
                throw new RuntimeException('Falha ao ler arquivo PFX.');
            }

            $credential = $credentials->activate($client, $binary, $data['password']);
        } catch (RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage() ?: 'Falha ao ativar certificado.',
            ], 422);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'message' => 'Falha ao ativar certificado.',
            ], 422);
        }

        return response()->json([
            'data' => $credential->toPublicArray(),
        ], 201);
    }

    private function assertAdmin(Client $client): void
    {
        $policy = app(ClientCredentialPolicy::class);
        if (! $policy->manage(auth()->user(), $client)) {
            abort(403, 'Apenas administradores podem gerenciar certificados.');
        }
    }
}

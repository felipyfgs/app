<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Clients\StoreClientRequest;
use App\Http\Requests\Clients\UpdateClientRequest;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Establishment;
use App\Services\Audit\AuditLogger;
use App\Services\Clients\CaptureEligibilityService;
use App\Services\Clients\ClientRootConflictException;
use App\Services\Clients\CreateClientWithEstablishment;
use App\Support\CurrentOffice;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class ClientController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Client::class);

        $base = Client::query();

        if ($search = $request->string('q')->toString()) {
            $needle = '%'.mb_strtolower($search).'%';
            $cnpjNeedle = '%'.strtoupper(preg_replace('/[^a-zA-Z0-9]/', '', $search) ?? $search).'%';
            $base->where(function ($q) use ($needle, $cnpjNeedle): void {
                $q->whereRaw('LOWER(legal_name) LIKE ?', [$needle])
                    ->orWhereRaw('LOWER(COALESCE(display_name, \'\')) LIKE ?', [$needle])
                    ->orWhere('root_cnpj', 'like', $cnpjNeedle)
                    ->orWhereHas('establishments', function ($est) use ($cnpjNeedle): void {
                        $est->where('cnpj', 'like', $cnpjNeedle);
                    });
            });
        }

        // KPIs do escritório (escopo da busca textual, sem filtro de estado da tabela)
        $statsQuery = (clone $base);
        $total = (clone $statsQuery)->count();
        $active = (clone $statsQuery)->where('is_active', true)->count();
        // credentials() (hasMany, qualquer status): KPIs que filtram além de ACTIVE.
        // credential() (hasOne ACTIVE) é só para resumo operacional na lista.
        $withoutCredential = (clone $statsQuery)
            ->whereDoesntHave('credentials', function ($q): void {
                $q->whereIn('status', ['ACTIVE', 'PENDING']);
            })
            ->count();
        $credentialExpiring = (clone $statsQuery)
            ->whereHas('credentials', function ($q): void {
                $q->where('status', 'ACTIVE')
                    ->where(function ($inner): void {
                        $inner->where('expires_alert_30', true)
                            ->orWhere('expires_alert_7', true)
                            ->orWhere('expires_alert_1', true)
                            ->orWhereBetween('valid_to', [now(), now()->addDays(30)]);
                    });
            })
            ->count();
        $credentialExpired = (clone $statsQuery)
            ->whereHas('credentials', function ($q): void {
                $q->where(function ($inner): void {
                    $inner->where('status', 'EXPIRED')
                        ->orWhere(function ($expired): void {
                            $expired->where('status', 'ACTIVE')->where('valid_to', '<', now());
                        });
                });
            })
            ->count();

        // Filtro de estado só na lista (USelect do template)
        if ($request->filled('is_active')) {
            $base->where('is_active', $request->boolean('is_active'));
        }

        $perPage = min(max((int) $request->input('per_page', 20), 1), 100);
        $paginator = (clone $base)
            ->withCount('establishments')
            ->with([
                'credential',
                // 1 cliente = 1 estabelecimento: carrega o CNPJ completo para a lista
                'establishments' => fn ($q) => $q->orderBy('id')->limit(1),
            ])
            ->orderBy('legal_name')
            ->paginate($perPage);

        $items = collect($paginator->items())->map(function (Client $client) {
            $payload = $this->serializeClient($client);
            $primary = $client->establishments->first();
            $payload['cnpj'] = $primary?->cnpj;
            $payload['trade_name'] = $primary?->trade_name;
            $credential = $client->credential;
            $payload['credential_summary'] = $credential === null
                ? null
                : [
                    'status' => $credential->status?->value ?? $credential->status,
                    'valid_to' => $credential->valid_to?->toIso8601String(),
                    'expires_alert_30' => (bool) $credential->expires_alert_30,
                    'expires_alert_7' => (bool) $credential->expires_alert_7,
                    'expires_alert_1' => (bool) $credential->expires_alert_1,
                ];

            return $payload;
        });

        return response()->json([
            'data' => $items,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'stats' => [
                    'total' => $total,
                    'active' => $active,
                    'without_credential' => $withoutCredential,
                    'credential_expiring_30d' => $credentialExpiring,
                    'credential_expired' => $credentialExpired,
                ],
            ],
        ]);
    }

    public function store(
        StoreClientRequest $request,
        CurrentOffice $currentOffice,
        CreateClientWithEstablishment $creator,
    ): JsonResponse {
        $this->authorize('create', Client::class);

        $officeId = $currentOffice->office()->id;

        try {
            $result = $creator->handle($officeId, $request->validated());
        } catch (ClientRootConflictException $e) {
            $payload = [
                'message' => $e->getMessage(),
                'errors' => ['cnpj' => ['CNPJ já cadastrado neste escritório.']],
            ];
            if ($e->existingClient !== null) {
                $payload['data'] = [
                    'existing_client_id' => $e->existingClient->id,
                    'existing_client' => [
                        'id' => $e->existingClient->id,
                        'legal_name' => $e->existingClient->legal_name,
                        'root_cnpj' => $e->existingClient->root_cnpj,
                    ],
                ];
            }

            return response()->json($payload, 409);
        } catch (UniqueConstraintViolationException $e) {
            // Corrida / unique físico (office_id, cnpj) — 409 genérico do escritório.
            return response()->json([
                'message' => 'CNPJ já cadastrado neste escritório.',
                'errors' => ['cnpj' => ['CNPJ já cadastrado neste escritório.']],
            ], 409);
        } catch (QueryException $e) {
            if ($this->isUniqueConstraintViolation($e)) {
                return response()->json([
                    'message' => 'CNPJ já cadastrado neste escritório.',
                    'errors' => ['cnpj' => ['CNPJ já cadastrado neste escritório.']],
                ], 409);
            }

            throw $e;
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'errors' => ['cnpj' => [$e->getMessage()]],
            ], 422);
        }

        return response()->json([
            'data' => [
                'client' => $this->serializeClient($result['client']),
                'establishment' => $this->serializeEstablishment($result['establishment']),
                'contact' => $result['contact'] !== null
                    ? $this->serializeContact($result['contact'])
                    : null,
                'custom_fields' => collect($result['custom_fields'])
                    ->map(fn ($field) => $field->toPublicArray())
                    ->values(),
            ],
        ], 201);
    }

    public function show(Client $client, CaptureEligibilityService $eligibility): JsonResponse
    {
        $this->authorize('view', $client);

        $client->load([
            'credential',
            'establishments' => fn ($q) => $q->orderBy('cnpj'),
            'contacts' => fn ($q) => $q->orderByDesc('is_primary')->orderBy('name'),
            'customFields' => fn ($q) => $q->orderBy('id'),
            'matrix.establishments' => fn ($q) => $q->orderBy('id')->limit(1),
            'branches' => fn ($q) => $q->orderBy('legal_name')
                ->with(['establishments' => fn ($eq) => $eq->orderBy('id')->limit(1), 'credential']),
        ]);

        $establishments = $client->establishments->map(function ($est) use ($eligibility) {
            $payload = $this->serializeEstablishment($est);
            $eval = $eligibility->evaluate($est);
            $payload['capture_eligibility'] = $eval;

            return $payload;
        });

        $data = $this->serializeClient($client);
        $primary = $client->establishments->first();
        $data['cnpj'] = $primary?->cnpj;
        $data['trade_name'] = $primary?->trade_name;
        $data['establishments'] = $establishments;
        $data['matrix'] = $client->matrix !== null
            ? $this->serializeLinkedClient($client->matrix)
            : null;
        $data['branches'] = $client->branches
            ->map(fn (Client $branch) => $this->serializeLinkedClient($branch))
            ->values();
        $data['contacts'] = $client->contacts->map(fn ($c) => $this->serializeContact($c))->values();
        $data['custom_fields'] = $client->customFields->map(fn ($field) => $field->toPublicArray())->values();
        // Resumo seguro do A1 (status/validade/alertas) — sem vault/PFX — para OPERATOR/VIEWER.
        // Espelha o payload da listagem; detalhe completo continua em GET /credential (ADMIN+2FA).
        $credential = $client->credential;
        $data['credential_summary'] = $credential === null
            ? null
            : [
                'status' => $credential->status?->value ?? $credential->status,
                'valid_to' => $credential->valid_to?->toIso8601String(),
                'expires_alert_30' => (bool) $credential->expires_alert_30,
                'expires_alert_7' => (bool) $credential->expires_alert_7,
                'expires_alert_1' => (bool) $credential->expires_alert_1,
            ];

        return response()->json(['data' => $data]);
    }

    public function update(UpdateClientRequest $request, Client $client, AuditLogger $audit): JsonResponse
    {
        $this->authorize('update', $client);

        $data = $request->validated();
        $client->fill($data);
        $changed = array_keys($client->getDirty());
        $client->save();

        $audit->record('client.update', 'SUCCESS', $client, [
            'fields' => $changed,
        ]);

        return response()->json(['data' => $this->serializeClient($client->fresh() ?? $client)]);
    }

    /**
     * Detecta violação de unique (PostgreSQL 23505 / SQLite) quando a exceção tipada não for lançada.
     */
    private function isUniqueConstraintViolation(QueryException $e): bool
    {
        $sqlState = (string) ($e->errorInfo[0] ?? '');
        if ($sqlState === '23505' || $sqlState === '23000') {
            return true;
        }

        $message = strtolower($e->getMessage());

        return str_contains($message, 'unique')
            || str_contains($message, 'duplicate');
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeClient(Client $client): array
    {
        return [
            'id' => $client->id,
            'office_id' => $client->office_id,
            'legal_name' => $client->legal_name,
            'display_name' => $client->display_name,
            'name' => $client->displayLabel(), // compat UI legada
            'root_cnpj' => $client->root_cnpj,
            'matrix_client_id' => $client->matrix_client_id,
            'legal_nature_code' => $client->legal_nature_code,
            'legal_nature_name' => $client->legal_nature_name,
            'company_size_code' => $client->company_size_code,
            'company_size_name' => $client->company_size_name,
            'tax_regime' => $client->tax_regime,
            'notes' => $client->notes,
            'is_active' => $client->is_active,
            'inactive_reason' => $client->inactive_reason,
            'registration_source' => $client->registration_source?->value ?? $client->registration_source,
            'registration_refreshed_at' => $client->registration_refreshed_at?->toIso8601String(),
            'establishments_count' => $client->establishments_count ?? null,
            'created_at' => $client->created_at?->toIso8601String(),
            'updated_at' => $client->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Resumo de cliente vinculado (matriz ou filial) para a aba Estabelecimentos.
     *
     * @return array<string, mixed>
     */
    private function serializeLinkedClient(Client $client): array
    {
        $primary = $client->relationLoaded('establishments')
            ? $client->establishments->first()
            : $client->establishments()->orderBy('id')->first();

        $credential = $client->relationLoaded('credential')
            ? $client->credential
            : null;

        return [
            'id' => $client->id,
            'legal_name' => $client->legal_name,
            'display_name' => $client->display_name,
            'name' => $client->displayLabel(),
            'root_cnpj' => $client->root_cnpj,
            'matrix_client_id' => $client->matrix_client_id,
            'cnpj' => $primary?->cnpj,
            'trade_name' => $primary?->trade_name,
            'is_matrix' => (bool) ($primary?->is_matrix ?? $client->matrix_client_id === null),
            'is_active' => $client->is_active,
            'credential_summary' => $credential === null
                ? null
                : [
                    'status' => $credential->status?->value ?? $credential->status,
                    'valid_to' => $credential->valid_to?->toIso8601String(),
                ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeEstablishment(Establishment $est): array
    {
        return [
            'id' => $est->id,
            'office_id' => $est->office_id,
            'client_id' => $est->client_id,
            'cnpj' => $est->cnpj,
            'trade_name' => $est->trade_name,
            'is_matrix' => $est->is_matrix,
            'is_active' => $est->is_active,
            'registration_status' => $est->registration_status?->value ?? $est->registration_status,
            'registration_status_at' => $est->registration_status_at?->toDateString(),
            'registration_status_reason' => $est->registration_status_reason,
            'activity_started_at' => $est->activity_started_at?->toDateString(),
            'main_cnae_code' => $est->main_cnae_code,
            'main_cnae_name' => $est->main_cnae_name,
            'address' => $est->addressPayload(),
            'public_email' => $est->public_email,
            'public_phone' => $est->public_phone,
            'capture_enabled' => $est->capture_enabled,
            'registration_source' => $est->registration_source?->value ?? $est->registration_source,
            'registration_refreshed_at' => $est->registration_refreshed_at?->toIso8601String(),
            'created_at' => $est->created_at?->toIso8601String(),
            'updated_at' => $est->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeContact(ClientContact $contact): array
    {
        return [
            'id' => $contact->id,
            'client_id' => $contact->client_id,
            'name' => $contact->name,
            'role' => $contact->role,
            'email' => $contact->email,
            'phone' => $contact->phone,
            'is_whatsapp' => $contact->is_whatsapp,
            'is_primary' => $contact->is_primary,
            'receives_alerts' => $contact->receives_alerts,
            'notes' => $contact->notes,
            'is_active' => $contact->is_active,
            'created_at' => $contact->created_at?->toIso8601String(),
            'updated_at' => $contact->updated_at?->toIso8601String(),
        ];
    }
}

<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\AuthorCertificateMode;
use App\Enums\AuthorIdentityType;
use App\Enums\OfficeRole;
use App\Enums\SerproEnvironment;
use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Services\Audit\AuditLogger;
use App\Services\Integra\IntegraEligibilityService;
use App\Services\Integra\OfficeSerproAuthorizationService;
use App\Services\Integra\TaxProxyPowerService;
use App\Services\Integra\TenantIntegraHealthService;
use App\Support\CurrentOffice;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;
use Throwable;

/**
 * Onboarding tenant-scoped: Autor, Termo, procurações, saúde sanitizada.
 * NÃO importa clients HTTP globais nem models de contrato global.
 * Nunca retorna XML, PFX ou tokens.
 */
class OfficeSerproAuthorizationController extends Controller
{
    public function __construct(
        private readonly CurrentOffice $currentOffice,
        private readonly OfficeSerproAuthorizationService $authorizations,
        private readonly TaxProxyPowerService $proxyPowers,
        private readonly IntegraEligibilityService $eligibility,
        private readonly TenantIntegraHealthService $health,
        private readonly AuditLogger $audit,
    ) {}

    public function show(Request $request): JsonResponse
    {
        $this->assertAdminOrOperator();
        $office = $this->currentOffice->office();
        $env = $this->environment($request);

        $auth = $this->authorizations->getOrCreate($office, $env);

        return response()->json([
            'data' => $auth->toPublicArray(),
            'platform_health' => $this->health->forEnvironment($env),
            'term_representation_strategy' => $this->authorizations->representationStrategy($env)->value,
        ]);
    }

    public function configureAuthor(Request $request): JsonResponse
    {
        $this->assertAdmin();
        $office = $this->currentOffice->office();

        $data = $request->validate([
            'environment' => ['sometimes', 'string', Rule::enum(SerproEnvironment::class)],
            'author_identity_type' => ['required', 'string', Rule::enum(AuthorIdentityType::class)],
            'author_identity' => ['required', 'string', 'max:14'],
            'author_name' => ['nullable', 'string', 'max:255'],
            'certificate_mode' => ['sometimes', 'string', Rule::enum(AuthorCertificateMode::class)],
        ]);

        $env = isset($data['environment'])
            ? SerproEnvironment::from($data['environment'])
            : SerproEnvironment::from((string) config('serpro.default_environment', 'TRIAL'));

        try {
            $auth = $this->authorizations->configureAuthor(
                $office,
                $env,
                AuthorIdentityType::from($data['author_identity_type']),
                $data['author_identity'],
                $data['author_name'] ?? null,
                isset($data['certificate_mode'])
                    ? AuthorCertificateMode::from($data['certificate_mode'])
                    : AuthorCertificateMode::ExternalSignature,
                $request->user()?->id,
            );
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $auth->toPublicArray()]);
    }

    public function uploadTermo(Request $request): JsonResponse
    {
        $this->assertAdmin();
        $office = $this->currentOffice->office();

        $data = $request->validate([
            'environment' => ['sometimes', 'string', Rule::enum(SerproEnvironment::class)],
            'termo_xml' => ['required_without:termo_file', 'string'],
            'termo_file' => ['required_without:termo_xml', 'file', 'max:2048'],
        ]);

        $env = isset($data['environment'])
            ? SerproEnvironment::from($data['environment'])
            : SerproEnvironment::from((string) config('serpro.default_environment', 'TRIAL'));

        $xml = $data['termo_xml'] ?? null;
        if ($xml === null && isset($data['termo_file'])) {
            $xml = file_get_contents($data['termo_file']->getRealPath()) ?: '';
        }

        try {
            $auth = $this->authorizations->uploadTermo($office, $env, (string) $xml, $request->user()?->id);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            report($e);

            return response()->json(['message' => 'Falha ao processar Termo.'], 422);
        }

        return response()->json(['data' => $auth->toPublicArray()], 201);
    }

    public function storeAuthorA1(Request $request): JsonResponse
    {
        $this->assertAdmin();
        $office = $this->currentOffice->office();

        $data = $request->validate([
            'environment' => ['sometimes', 'string', Rule::enum(SerproEnvironment::class)],
            'pfx' => ['required', 'file', 'max:5120'],
            'password' => ['required', 'string'],
            'consent' => ['required', 'accepted'],
        ]);

        $env = isset($data['environment'])
            ? SerproEnvironment::from($data['environment'])
            : SerproEnvironment::from((string) config('serpro.default_environment', 'TRIAL'));

        try {
            $binary = file_get_contents($data['pfx']->getRealPath());
            if ($binary === false) {
                throw new RuntimeException('Falha ao ler PFX.');
            }
            $auth = $this->authorizations->storeManagedAuthorA1(
                $office,
                $env,
                $binary,
                $data['password'],
                true,
                $request->user()?->id,
            );
        } catch (RuntimeException $e) {
            $this->audit->record('serpro.authorization.author_a1', 'FAILED', null, [
                'message' => $e->getMessage(),
            ], $request->user()?->id, $office->id);

            return response()->json(['message' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            report($e);

            return response()->json(['message' => 'Falha ao armazenar A1 do Autor.'], 422);
        }

        return response()->json(['data' => $auth->toPublicArray()], 201);
    }

    public function refreshToken(Request $request): JsonResponse
    {
        $this->assertAdmin();
        $office = $this->currentOffice->office();
        $env = $this->environment($request);

        try {
            $auth = $this->authorizations->refreshProcuradorToken($office, $env, $request->user()?->id);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $auth->toPublicArray()]);
    }

    public function listProxyPowers(Request $request): JsonResponse
    {
        $this->assertAdminOrOperator();
        $office = $this->currentOffice->office();

        $query = \App\Models\TaxProxyPower::query()
            ->where('office_id', $office->id)
            ->orderByDesc('id');

        if ($request->query('client_id')) {
            $query->where('client_id', (int) $request->query('client_id'));
        }

        $items = $query->limit(200)->get()->map->toPublicArray();

        return response()->json(['data' => $items]);
    }

    public function importProxyPower(Request $request): JsonResponse
    {
        $this->assertAdmin();
        $office = $this->currentOffice->office();

        $data = $request->validate([
            'environment' => ['sometimes', 'string', Rule::enum(SerproEnvironment::class)],
            'client_id' => ['required', 'integer'],
            'power_code' => ['required', 'string', 'max:120'],
            'system_code' => ['required', 'string', 'max:80'],
            'service_code' => ['nullable', 'string', 'max:120'],
            'valid_from' => ['nullable', 'date'],
            'valid_to' => ['nullable', 'date'],
            'evidence_ref' => ['required', 'string', 'max:120'],
            'evidence_sha256' => ['nullable', 'string', 'size:64'],
        ]);

        $env = isset($data['environment'])
            ? SerproEnvironment::from($data['environment'])
            : SerproEnvironment::from((string) config('serpro.default_environment', 'TRIAL'));

        $client = Client::query()->where('office_id', $office->id)->findOrFail($data['client_id']);
        $auth = $this->authorizations->getOrCreate($office, $env);

        try {
            $power = $this->proxyPowers->importManualEvidence(
                $office,
                $client,
                $auth,
                $data['power_code'],
                $data['system_code'],
                $data['service_code'] ?? null,
                isset($data['valid_from']) ? CarbonImmutable::parse($data['valid_from']) : null,
                isset($data['valid_to']) ? CarbonImmutable::parse($data['valid_to']) : null,
                $data['evidence_ref'],
                $data['evidence_sha256'] ?? null,
                $request->user()?->id,
            );
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $power->toPublicArray()], 201);
    }

    public function syncProxyPowers(Request $request): JsonResponse
    {
        $this->assertAdmin();
        $office = $this->currentOffice->office();

        $data = $request->validate([
            'environment' => ['sometimes', 'string', Rule::enum(SerproEnvironment::class)],
            'client_id' => ['required', 'integer'],
            'power_code' => ['nullable', 'string', 'max:120'],
        ]);

        $env = isset($data['environment'])
            ? SerproEnvironment::from($data['environment'])
            : SerproEnvironment::from((string) config('serpro.default_environment', 'TRIAL'));

        $client = Client::query()->where('office_id', $office->id)->findOrFail($data['client_id']);
        $auth = $this->authorizations->getOrCreate($office, $env);

        try {
            $powers = $this->proxyPowers->syncFromApi(
                $office,
                $client,
                $auth,
                $env,
                $data['power_code'] ?? null,
                $request->user()?->id,
            );
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'data' => array_map(fn ($p) => $p->toPublicArray(), $powers),
        ]);
    }

    public function eligibility(Request $request): JsonResponse
    {
        $this->assertAdminOrOperator();
        $office = $this->currentOffice->office();

        $data = $request->validate([
            'environment' => ['sometimes', 'string', Rule::enum(SerproEnvironment::class)],
            'client_id' => ['required', 'integer'],
            'solution_code' => ['required', 'string', 'max:80'],
            'service_code' => ['required', 'string', 'max:120'],
            'operation_code' => ['required', 'string', 'max:120'],
            'module' => ['nullable', 'string', 'max:40'],
        ]);

        $env = isset($data['environment'])
            ? SerproEnvironment::from($data['environment'])
            : SerproEnvironment::from((string) config('serpro.default_environment', 'TRIAL'));

        $client = Client::query()->where('office_id', $office->id)->findOrFail($data['client_id']);

        $result = $this->eligibility->evaluate(
            $office,
            $client,
            $data['solution_code'],
            $data['service_code'],
            $data['operation_code'],
            $env,
            $request->user(),
            $data['module'] ?? null,
        );

        return response()->json(['data' => $result->toArray()]);
    }

    public function platformHealth(Request $request): JsonResponse
    {
        $this->assertAdminOrOperator();
        $env = $this->environment($request);

        return response()->json([
            'data' => $this->health->forEnvironment($env),
        ]);
    }

    private function environment(Request $request): SerproEnvironment
    {
        $raw = $request->query('environment') ?? $request->input('environment');
        if (is_string($raw) && $raw !== '') {
            return SerproEnvironment::tryFrom(strtoupper($raw))
                ?? SerproEnvironment::from((string) config('serpro.default_environment', 'TRIAL'));
        }

        return SerproEnvironment::from((string) config('serpro.default_environment', 'TRIAL'));
    }

    private function assertAdmin(): void
    {
        $role = $this->currentOffice->role();
        if ($role !== OfficeRole::Admin) {
            abort(403, 'Ação restrita a ADMIN do escritório.');
        }
    }

    private function assertAdminOrOperator(): void
    {
        $role = $this->currentOffice->role();
        if (! in_array($role, [OfficeRole::Admin, OfficeRole::Operator], true)) {
            abort(403, 'Ação restrita a ADMIN/OPERATOR do escritório.');
        }
    }
}

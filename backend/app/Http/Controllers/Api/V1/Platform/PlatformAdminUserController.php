<?php

namespace App\Http\Controllers\Api\V1\Platform;

use App\Enums\ActivationMethod;
use App\Enums\ActivationPurpose;
use App\Enums\PlatformRole;
use App\Http\Controllers\Controller;
use App\Models\AccountActivation;
use App\Models\PlatformMembership;
use App\Models\User;
use App\Services\Activation\ActivationException;
use App\Services\Activation\CorrectPendingRecipientService;
use App\Services\Activation\CreatePendingPlatformAdminService;
use App\Services\Activation\RegenerateActivationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Administração de PLATFORM_ADMIN pendentes/ativos.
 */
class PlatformAdminUserController extends Controller
{
    public function __construct(
        private readonly CreatePendingPlatformAdminService $createAdmin,
        private readonly RegenerateActivationService $regenerate,
        private readonly CorrectPendingRecipientService $correctRecipient,
    ) {}

    public function index(): JsonResponse
    {
        return response()->json([
            'data' => $this->createAdmin->listAdmins(),
        ]);
    }

    public function show(User $user): JsonResponse
    {
        try {
            $data = $this->createAdmin->showAdmin($user);
        } catch (ActivationException $e) {
            return $this->activationError($e);
        }

        return response()->json(['data' => $data]);
    }

    public function store(Request $request): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'method' => ['required', 'string', Rule::enum(ActivationMethod::class)],
            'default_office_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
        ]);

        try {
            $payload = $this->createAdmin->create($validated, $actor);
        } catch (ActivationException $e) {
            return $this->activationError($e);
        }

        return $this->noStoreJson(['data' => $payload], 201);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'method' => ['required', 'string', Rule::enum(ActivationMethod::class)],
            'default_office_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
        ]);

        try {
            $payload = $this->correctRecipient->correctPlatformAdmin(
                $user,
                $validated['name'],
                $validated['email'],
                ActivationMethod::from($validated['method']),
                $actor,
                isset($validated['default_office_id']) ? (int) $validated['default_office_id'] : null,
            );
        } catch (ActivationException $e) {
            return $this->activationError($e);
        }

        return $this->noStoreJson(['data' => $payload]);
    }

    public function regenerateActivation(Request $request, User $user): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        $validated = $request->validate([
            'method' => ['required', 'string', Rule::enum(ActivationMethod::class)],
        ]);

        $pm = PlatformMembership::query()
            ->where('user_id', $user->id)
            ->where('role', PlatformRole::PlatformAdmin)
            ->first();

        if ($pm === null) {
            return response()->json(['message' => 'Administrador global não encontrado.', 'code' => 'not_found'], 404);
        }

        $activation = AccountActivation::query()
            ->where('platform_membership_id', $pm->id)
            ->where('purpose', ActivationPurpose::PlatformAdmin)
            ->whereNull('consumed_at')
            ->orderByDesc('generation')
            ->orderByDesc('id')
            ->first();

        if ($activation === null) {
            return response()->json(['message' => 'Nenhuma ativação pendente.', 'code' => 'not_found'], 404);
        }

        try {
            $payload = $this->regenerate->regenerate(
                $activation,
                ActivationMethod::from($validated['method']),
                $actor,
            );
        } catch (ActivationException $e) {
            return $this->activationError($e);
        }

        return $this->noStoreJson(['data' => $payload]);
    }

    private function activationError(ActivationException $e): JsonResponse
    {
        return response()->json([
            'message' => $e->getMessage(),
            'code' => $e->errorCode,
        ], $e->status);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function noStoreJson(array $payload, int $status = 200): JsonResponse
    {
        return response()
            ->json($payload, $status)
            ->header('Cache-Control', 'no-store');
    }
}

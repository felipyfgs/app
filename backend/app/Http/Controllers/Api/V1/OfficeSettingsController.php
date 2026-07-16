<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Office\GrantOfficeTechnicalConsentRequest;
use App\Http\Requests\Office\RemoveOfficeCanonicalCredentialRequest;
use App\Http\Requests\Office\UpdateOfficeInstitutionalProfileRequest;
use App\Models\OfficeCredential;
use App\Models\OfficeCredentialPurposeLink;
use App\Policies\OfficeSettingsPolicy;
use App\Services\Audit\AuditLogger;
use App\Services\Certificates\OfficeCredentialService;
use App\Services\Certificates\OfficeInstitutionalProfileService;
use App\Services\Certificates\OfficeTechnicalConsentService;
use App\Support\CurrentOffice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * Superfície tenant-scoped de /settings: perfil, consentimento e A1 canônico.
 * Sem download/recuperação de PFX/senha; office_id só via CurrentOffice.
 */
class OfficeSettingsController extends Controller
{
    public function __construct(
        private readonly CurrentOffice $currentOffice,
        private readonly OfficeInstitutionalProfileService $profiles,
        private readonly OfficeCredentialService $credentials,
        private readonly OfficeTechnicalConsentService $consents,
        private readonly AuditLogger $audit,
    ) {}

    public function show(): JsonResponse
    {
        $this->authorizeView();

        $profile = $this->profiles->forCurrentOffice();
        $credential = $this->credentials->activeCanonicalForCurrentOffice();
        // Atualiza flags de alerta (dedupe) antes de expor no painel.
        $this->credentials->refreshExpiryAlerts();
        $credential = $credential?->fresh() ?? $this->credentials->activeCanonicalForCurrentOffice();

        $links = [];
        if ($credential !== null) {
            $links = OfficeCredentialPurposeLink::query()
                ->where('office_credential_id', $credential->id)
                ->where('status', 'ACTIVE')
                ->orderBy('purpose')
                ->get()
                ->map(fn (OfficeCredentialPurposeLink $link) => $link->toPublicArray())
                ->values()
                ->all();
        }

        return response()->json([
            'data' => [
                'profile' => $profile->toPublicArray(),
                'consent' => $this->consents->currentStatus(),
                'credential' => $credential?->toPublicArray(),
                'purpose_links' => $links,
                'alerts' => $this->credentials->panelExpiryAlerts($credential),
            ],
        ]);
    }

    public function updateProfile(UpdateOfficeInstitutionalProfileRequest $request): JsonResponse
    {
        $this->authorizeManage();

        try {
            $result = $this->profiles->update(
                $request->validated(),
                $request->user()?->id,
            );
        } catch (InvalidArgumentException|RuntimeException $e) {
            $this->audit->record('office.institutional_profile.update', 'FAILED', null, [
                'message' => $e->getMessage(),
            ], $request->user()?->id);

            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'data' => [
                'profile' => $result['profile']->toPublicArray(),
                'cnpj_changed' => $result['cnpj_changed'],
                'invalidated' => $result['invalidated'],
            ],
        ]);
    }

    public function showConsent(): JsonResponse
    {
        $this->authorizeView();

        return response()->json([
            'data' => $this->consents->currentStatus(),
        ]);
    }

    public function grantConsent(GrantOfficeTechnicalConsentRequest $request): JsonResponse
    {
        $this->authorizeManage();

        $data = $request->validated();

        try {
            $consent = $this->consents->grant(
                accepted: true,
                actorUserId: $request->user()?->id,
                versionCode: $data['version_code'] ?? null,
            );
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'data' => $consent->toPublicArray(),
        ], 201);
    }

    public function revokeConsent(Request $request): JsonResponse
    {
        $this->authorizeManage();
        $request->request->remove('office_id');

        $consent = $this->consents->revoke($request->user()?->id);
        if ($consent === null) {
            return response()->json([
                'message' => 'Não há consentimento ativo para revogar.',
            ], 422);
        }

        return response()->json([
            'data' => $consent->toPublicArray(),
        ]);
    }

    public function showCredential(): JsonResponse
    {
        $this->authorizeView();

        $this->credentials->refreshExpiryAlerts();
        $credential = $this->credentials->activeCanonicalForCurrentOffice();

        return response()->json([
            'data' => [
                'credential' => $credential?->toPublicArray(),
                'alerts' => $this->credentials->panelExpiryAlerts($credential),
            ],
        ]);
    }

    public function storeCredential(Request $request): JsonResponse
    {
        $this->authorizeManage();
        $request->request->remove('office_id');

        $data = $request->validate([
            'pfx' => ['required', 'file', 'max:5120'],
            'password' => ['required', 'string'],
            'office_id' => ['prohibited'],
        ]);

        try {
            $binary = file_get_contents($data['pfx']->getRealPath());
            if ($binary === false) {
                throw new RuntimeException('Falha ao ler arquivo PFX.');
            }

            $credential = $this->credentials->activateCanonical(
                $binary,
                $data['password'],
                $request->user()?->id,
            );
            unset($binary, $data['password']);
        } catch (RuntimeException $e) {
            $this->audit->record('office_credential.canonical.activate', 'FAILED', null, [
                'message' => $e->getMessage() ?: 'Falha ao ativar certificado canônico.',
            ], $request->user()?->id);

            return response()->json([
                'message' => $e->getMessage() ?: 'Falha ao ativar certificado canônico.',
            ], 422);
        } catch (Throwable $e) {
            report($e);
            $this->audit->record('office_credential.canonical.activate', 'FAILED', null, [
                'message' => 'Falha ao ativar certificado canônico.',
            ], $request->user()?->id);

            return response()->json([
                'message' => 'Falha ao ativar certificado canônico.',
            ], 422);
        }

        $this->audit->record('office_credential.canonical.activate', 'SUCCESS', $credential, [
            'fingerprint_sha256' => $credential->fingerprint_sha256,
            'holder_cnpj' => $credential->holder_cnpj,
            'valid_to' => $credential->valid_to?->toIso8601String(),
            'purpose' => $credential->purpose->value,
        ], $request->user()?->id);

        return response()->json([
            'data' => $this->credentialPayload($credential),
        ], 201);
    }

    public function replaceCredential(Request $request): JsonResponse
    {
        $this->authorizeManage();
        $request->request->remove('office_id');

        $data = $request->validate([
            'pfx' => ['required', 'file', 'max:5120'],
            'password' => ['required', 'string'],
            'office_id' => ['prohibited'],
        ]);

        $previous = $this->credentials->activeCanonicalForCurrentOffice();
        $previousFingerprint = $previous?->fingerprint_sha256;

        try {
            $binary = file_get_contents($data['pfx']->getRealPath());
            if ($binary === false) {
                throw new RuntimeException('Falha ao ler arquivo PFX.');
            }

            $credential = $this->credentials->replaceCanonical(
                $binary,
                $data['password'],
                $request->user()?->id,
            );
            unset($binary, $data['password']);
        } catch (RuntimeException $e) {
            // Validate-before-cutover: credencial anterior permanece.
            $stillActive = $this->credentials->activeCanonicalForCurrentOffice();
            $this->audit->record('office_credential.canonical.replace', 'FAILED', $stillActive, [
                'message' => $e->getMessage() ?: 'Falha ao substituir certificado canônico.',
                'previous_fingerprint_sha256' => $previousFingerprint,
                'previous_still_active' => $stillActive !== null
                    && $stillActive->fingerprint_sha256 === $previousFingerprint,
            ], $request->user()?->id);

            return response()->json([
                'message' => $e->getMessage() ?: 'Falha ao substituir certificado canônico.',
                'previous_preserved' => true,
            ], 422);
        } catch (Throwable $e) {
            report($e);
            $this->audit->record('office_credential.canonical.replace', 'FAILED', null, [
                'message' => 'Falha ao substituir certificado canônico.',
            ], $request->user()?->id);

            return response()->json([
                'message' => 'Falha ao substituir certificado canônico.',
                'previous_preserved' => true,
            ], 422);
        }

        $this->audit->record('office_credential.canonical.replace', 'SUCCESS', $credential, [
            'fingerprint_sha256' => $credential->fingerprint_sha256,
            'previous_fingerprint_sha256' => $previousFingerprint,
            'holder_cnpj' => $credential->holder_cnpj,
            'valid_to' => $credential->valid_to?->toIso8601String(),
        ], $request->user()?->id);

        return response()->json([
            'data' => $this->credentialPayload($credential),
        ]);
    }

    public function removeCredential(RemoveOfficeCanonicalCredentialRequest $request): JsonResponse
    {
        $this->authorizeManage();

        try {
            $credential = $this->credentials->removeCanonical(
                confirmed: true,
                actorUserId: $request->user()?->id,
            );
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        if ($credential === null) {
            return response()->json([
                'message' => 'Não há certificado A1 ativo para remover.',
            ], 422);
        }

        $this->audit->record('office_credential.canonical.remove', 'SUCCESS', $credential, [
            'fingerprint_sha256' => $credential->fingerprint_sha256,
            'holder_cnpj' => $credential->holder_cnpj,
        ], $request->user()?->id);

        return response()->json([
            'data' => [
                'credential' => $credential->toPublicArray(),
                'removed' => true,
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function credentialPayload(OfficeCredential $credential): array
    {
        $links = OfficeCredentialPurposeLink::query()
            ->where('office_credential_id', $credential->id)
            ->where('status', 'ACTIVE')
            ->orderBy('purpose')
            ->get()
            ->map(fn (OfficeCredentialPurposeLink $link) => $link->toPublicArray())
            ->values()
            ->all();

        return [
            'credential' => $credential->toPublicArray(),
            'purpose_links' => $links,
            'alerts' => $this->credentials->panelExpiryAlerts($credential),
        ];
    }

    private function authorizeView(): void
    {
        $policy = app(OfficeSettingsPolicy::class);
        if (! $policy->view(auth()->user())) {
            abort(403, 'Ação não autorizada para o perfil atual.');
        }
    }

    private function authorizeManage(): void
    {
        $policy = app(OfficeSettingsPolicy::class);
        if (! $policy->manage(auth()->user())) {
            abort(403, 'Apenas administradores do escritório podem alterar a configuração.');
        }
    }
}

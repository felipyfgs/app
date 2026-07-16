<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PlatformSetting;
use App\Models\User;
use App\Services\Platform\TenantSwitchService;
use App\Support\CurrentOffice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MeController extends Controller
{
    public function __invoke(
        Request $request,
        CurrentOffice $currentOffice,
        TenantSwitchService $tenantSwitch,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $office = $currentOffice->resolve($user);
        $role = $currentOffice->role();
        $realRole = $currentOffice->realOfficeRole();
        $contextStatus = $currentOffice->contextStatus()
            ?? ($office !== null ? CurrentOffice::CONTEXT_STATUS_OK : CurrentOffice::CONTEXT_STATUS_REQUIRED);

        $organizationName = PlatformSetting::query()
            ->whereKey(PlatformSetting::SINGLETON_ID)
            ->value('organization_name');

        return response()->json([
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                // Campos 2FA legados mantidos por compatibilidade de payload; sempre false (TOTP removido).
                'two_factor_confirmed' => false,
                'two_factor_required' => false,
                'requires_two_factor_setup' => false,
                'is_platform_admin' => $user->isPlatformAdmin(),
                'access_mode' => $currentOffice->accessMode()?->value,
                'real_office_role' => $realRole?->value,
                'has_real_membership' => $currentOffice->hasRealMembership(),
                'context_status' => $contextStatus,
                'platform_organization_name' => is_string($organizationName) && $organizationName !== ''
                    ? $organizationName
                    : null,
                // Alias histórico: office === current_office
                'office' => $office === null ? null : [
                    'id' => $office->id,
                    'name' => $office->name,
                    'slug' => $office->slug,
                ],
                'current_office' => $office === null ? null : [
                    'id' => $office->id,
                    'name' => $office->name,
                    'slug' => $office->slug,
                ],
                'role' => $role?->value,
                'default_office_id' => $user->isPlatformAdmin()
                    ? $currentOffice->defaultOfficeId($user)
                    : null,
                'memberships' => $tenantSwitch->listMemberships($user),
            ],
        ]);
    }
}

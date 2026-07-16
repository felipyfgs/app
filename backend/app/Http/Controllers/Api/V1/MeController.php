<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
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

        return response()->json([
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'two_factor_confirmed' => $user->hasConfirmedTwoFactor(),
                'two_factor_required' => (bool) config('fortify.two_factor_required', true),
                'requires_two_factor_setup' => $user->requiresTwoFactorForAdmin(),
                'is_platform_admin' => $user->isPlatformAdmin(),
                'access_mode' => $currentOffice->accessMode()?->value,
                'office' => $office === null ? null : [
                    'id' => $office->id,
                    'name' => $office->name,
                    'slug' => $office->slug,
                ],
                'role' => $role?->value,
                'memberships' => $tenantSwitch->listMemberships($user),
            ],
        ]);
    }
}

<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\CurrentOffice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MeController extends Controller
{
    public function __invoke(Request $request, CurrentOffice $currentOffice): JsonResponse
    {
        $user = $request->user();
        $office = $currentOffice->resolve($user);
        $role = $currentOffice->role();

        return response()->json([
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'two_factor_confirmed' => $user->hasConfirmedTwoFactor(),
                'requires_two_factor_setup' => $user->requiresTwoFactorForAdmin(),
                'office' => $office === null ? null : [
                    'id' => $office->id,
                    'name' => $office->name,
                    'slug' => $office->slug,
                ],
                'role' => $role?->value,
            ],
        ]);
    }
}

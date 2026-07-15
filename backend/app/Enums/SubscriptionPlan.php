<?php

namespace App\Enums;

enum SubscriptionPlan: string
{
    case Starter = 'STARTER';
    case Professional = 'PROFESSIONAL';
    case Enterprise = 'ENTERPRISE';

    /**
     * Defaults de franquia/limites por plano (MVP comercial).
     *
     * @return array{monthly_api_quota: int, max_clients: int, max_users: int}
     */
    public function defaultLimits(): array
    {
        return match ($this) {
            self::Starter => [
                'monthly_api_quota' => 1_000,
                'max_clients' => 50,
                'max_users' => 5,
            ],
            self::Professional => [
                'monthly_api_quota' => 10_000,
                'max_clients' => 500,
                'max_users' => 25,
            ],
            self::Enterprise => [
                'monthly_api_quota' => 100_000,
                'max_clients' => 5_000,
                'max_users' => 200,
            ],
        };
    }
}

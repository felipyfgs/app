<?php

namespace App\Services\Serpro;

use App\Contracts\SerproContractAuthenticator;
use App\DTO\Serpro\SerproAuthToken;
use App\Models\SerproContract;
use Carbon\CarbonImmutable;

/**
 * Authenticator de trial/CI — emite o par access_token + jwt_token (simulado).
 */
final class FakeSerproContractAuthenticator implements SerproContractAuthenticator
{
    public function __construct(
        private readonly SerproTokenCache $tokenCache,
    ) {}

    public function authenticate(SerproContract $contract): SerproAuthToken
    {
        $cached = $this->tokenCache->get($contract);
        if ($cached !== null) {
            try {
                $cached->assertComplete();

                return $cached;
            } catch (\RuntimeException) {
                $this->tokenCache->invalidate($contract);
            }
        }

        return $this->tokenCache->withRefreshLock($contract, function () use ($contract): SerproAuthToken {
            $cached = $this->tokenCache->get($contract);
            if ($cached !== null) {
                try {
                    $cached->assertComplete();

                    return $cached;
                } catch (\RuntimeException) {
                    $this->tokenCache->invalidate($contract);
                }
            }

            $jwt = 'fake-jwt-'.$contract->id.'-'.bin2hex(random_bytes(12));
            $token = new SerproAuthToken(
                accessToken: 'fake-serpro-token-'.$contract->id.'-'.bin2hex(random_bytes(8)),
                tokenType: 'Bearer',
                expiresAt: CarbonImmutable::now()->addHour(),
                jwtToken: $jwt,
                fromCache: false,
                jwt: $jwt,
            );

            $this->tokenCache->put($contract, $token);

            $contract->health_status = 'OK';
            $contract->health_message = 'Auth simulada (trial) com jwt_token.';
            $contract->last_verified_at = now();
            $contract->save();

            return $token;
        });
    }

    public function invalidate(SerproContract $contract): void
    {
        $this->tokenCache->invalidate($contract);
    }
}

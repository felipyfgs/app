<?php

namespace App\Services\Serpro;

use App\Contracts\SerproContractAuthenticator;
use App\DTO\Serpro\SerproAuthToken;
use App\Models\SerproContract;
use Carbon\CarbonImmutable;

/**
 * Authenticator de trial/CI — não chama SERPRO real.
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
            return $cached;
        }

        return $this->tokenCache->withRefreshLock($contract, function () use ($contract): SerproAuthToken {
            $cached = $this->tokenCache->get($contract);
            if ($cached !== null) {
                return $cached;
            }

            $token = new SerproAuthToken(
                accessToken: 'fake-serpro-token-'.$contract->id.'-'.bin2hex(random_bytes(8)),
                tokenType: 'Bearer',
                expiresAt: CarbonImmutable::now()->addHour(),
                jwt: null,
                fromCache: false,
            );

            $this->tokenCache->put($contract, $token);

            $contract->health_status = 'OK';
            $contract->health_message = 'Auth simulada (trial).';
            $contract->last_verified_at = now();
            $contract->save();

            return new SerproAuthToken(
                accessToken: $token->accessToken,
                tokenType: $token->tokenType,
                expiresAt: $token->expiresAt,
                jwt: $token->jwt,
                fromCache: false,
            );
        });
    }

    public function invalidate(SerproContract $contract): void
    {
        $this->tokenCache->invalidate($contract);
    }
}

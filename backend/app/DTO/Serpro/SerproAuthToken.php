<?php

namespace App\DTO\Serpro;

use Carbon\CarbonImmutable;

/**
 * Token de acesso do contratante — valor sensível; não serializar em log/API.
 */
final class SerproAuthToken
{
    public function __construct(
        public readonly string $accessToken,
        public readonly string $tokenType,
        public readonly CarbonImmutable $expiresAt,
        public readonly ?string $jwt = null,
        public readonly bool $fromCache = false,
    ) {}

    public function isExpired(int $skewSeconds = 0): bool
    {
        return $this->expiresAt->lessThanOrEqualTo(now()->addSeconds($skewSeconds));
    }

    /**
     * @return array{token_type: string, expires_at: string, from_cache: bool, has_jwt: bool}
     */
    public function toSanitizedArray(): array
    {
        return [
            'token_type' => $this->tokenType,
            'expires_at' => $this->expiresAt->toIso8601String(),
            'from_cache' => $this->fromCache,
            'has_jwt' => $this->jwt !== null && $this->jwt !== '',
        ];
    }
}

<?php

namespace App\Services\Communication\Pairing;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Crypt;
use Throwable;

final readonly class CommunicationPairingStateStore
{
    public function __construct(private Repository $cache) {}

    /** @param array<string, mixed> $payload */
    public function put(int $inboxId, array $payload): void
    {
        $expiresAt = rescue(
            static fn () => isset($payload['expires_at']) ? now()->parse($payload['expires_at']) : now()->addMinutes(2),
            now()->addMinutes(2),
            report: false,
        );
        $seconds = max(1, min(300, now()->diffInSeconds($expiresAt, false)));
        $this->cache->put(
            $this->key($inboxId),
            Crypt::encryptString(json_encode($payload, JSON_THROW_ON_ERROR)),
            $seconds,
        );
    }

    /** @return array<string, mixed>|null */
    public function get(int $inboxId): ?array
    {
        $encrypted = $this->cache->get($this->key($inboxId));
        if (! is_string($encrypted)) {
            return null;
        }
        try {
            $payload = json_decode(Crypt::decryptString($encrypted), true, 16, JSON_THROW_ON_ERROR);

            return is_array($payload) ? $payload : null;
        } catch (Throwable) {
            $this->forget($inboxId);

            return null;
        }
    }

    public function forget(int $inboxId): void
    {
        $this->cache->forget($this->key($inboxId));
    }

    private function key(int $inboxId): string
    {
        return 'communication:pairing:inbox:'.$inboxId;
    }
}

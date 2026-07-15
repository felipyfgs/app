<?php

namespace App\Services\Outbound;

use App\Contracts\SvrsPortalEgressGovernor;
use Illuminate\Support\Facades\Log;

/**
 * Validação de bootstrap quando qualquer canal portal SVRS está habilitado.
 */
final class SvrsPortalChannelBootstrap
{
    public function __construct(
        private readonly SvrsPortalEgressConfig $config,
        private readonly SvrsPortalEgressGovernor $governor,
    ) {}

    public function validateIfEnabled(): void
    {
        if (! $this->config->anyPortalChannelEnabled()) {
            return;
        }

        try {
            $this->governor->assertChannelMayEnable();
        } catch (\Throwable $e) {
            Log::error('svrs_portal.bootstrap.blocked', [
                'error' => mb_substr($e->getMessage(), 0, 200),
            ]);
            throw $e;
        }
    }
}

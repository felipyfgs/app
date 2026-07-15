<?php

namespace App\Services\Serpro\Usage;

/**
 * Política de shadow mode e bloqueio comercial do ledger.
 *
 * Defaults: shadow ON, commercial blocking OFF.
 * Shadow mode vence: com shadow ativo, bloqueio comercial nunca aplica.
 */
final class UsageShadowPolicy
{
    public function isShadowMode(): bool
    {
        return (bool) config('serpro_usage.shadow_mode', true);
    }

    public function isCommercialBlockingEnabled(): bool
    {
        if ($this->isShadowMode()) {
            return false;
        }

        return (bool) config('serpro_usage.commercial_blocking_enabled', false);
    }

    /**
     * @return array{shadow_mode: bool, commercial_blocking_enabled: bool, effective_blocking: bool}
     */
    public function snapshot(): array
    {
        $shadow = $this->isShadowMode();
        $configuredBlocking = (bool) config('serpro_usage.commercial_blocking_enabled', false);

        return [
            'shadow_mode' => $shadow,
            'commercial_blocking_enabled' => $configuredBlocking,
            'effective_blocking' => $this->isCommercialBlockingEnabled(),
        ];
    }
}

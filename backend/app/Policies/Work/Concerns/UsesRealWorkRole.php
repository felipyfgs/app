<?php

namespace App\Policies\Work\Concerns;

use App\Enums\OfficeRole;
use App\Support\CurrentOffice;

/**
 * Policies Work: leitura usa papel efetivo.
 * Mutação/export: membership real, ou PLATFORM_ADMIN em contexto privilegiado
 * (papel efetivo ADMIN — paridade de superfície tenant).
 */
trait UsesRealWorkRole
{
    protected function currentOffice(): CurrentOffice
    {
        return app(CurrentOffice::class);
    }

    /** Papel efetivo (inclui ADMIN privilegiado) — leitura. */
    protected function effectiveRole(): ?OfficeRole
    {
        return $this->currentOffice()->role();
    }

    /**
     * Papel autorizado a mutar Work no office corrente.
     * Preferência: membership real; fallback: papel efetivo em platform_privileged.
     */
    protected function realRole(): ?OfficeRole
    {
        $real = $this->currentOffice()->realOfficeRole();
        if ($real !== null) {
            return $real;
        }

        if ($this->currentOffice()->isPlatformPrivileged()) {
            return $this->currentOffice()->role();
        }

        return null;
    }

    protected function sameOfficeId(int $modelOfficeId): bool
    {
        $officeId = $this->currentOffice()->id();

        return $officeId !== null && $officeId === $modelOfficeId;
    }
}

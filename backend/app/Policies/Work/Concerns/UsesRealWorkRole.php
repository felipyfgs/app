<?php

namespace App\Policies\Work\Concerns;

use App\Enums\OfficeRole;
use App\Support\CurrentOffice;

/**
 * Policies Work: leitura usa papel efetivo; mutação/export usa papel real da membership.
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
     * Papel real da membership no Office corrente.
     * Null se admin global puro → mutações negadas.
     */
    protected function realRole(): ?OfficeRole
    {
        return $this->currentOffice()->realOfficeRole();
    }

    protected function sameOfficeId(int $modelOfficeId): bool
    {
        $officeId = $this->currentOffice()->id();

        return $officeId !== null && $officeId === $modelOfficeId;
    }
}

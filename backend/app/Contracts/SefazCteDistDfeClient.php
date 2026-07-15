<?php

namespace App\Contracts;

use App\Domain\Sefaz\DistDfePageDto;

/**
 * Distribuição CT-e (CTeDistribuicaoDFe) — cursor NSU independente de NF-e.
 */
interface SefazCteDistDfeClient
{
    /**
     * @param  array{pfx: string, password: string}  $certificate
     */
    public function distByNsu(
        array $certificate,
        string $cnpjConsulta,
        int $ultNsu,
        string $cUfAutor,
    ): DistDfePageDto;
}

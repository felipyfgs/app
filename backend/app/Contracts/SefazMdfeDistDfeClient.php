<?php

namespace App\Contracts;

use App\Domain\Sefaz\DistDfePageDto;

/**
 * Distribuição MDF-e (MDFeDistribuicaoDFe) — cursor NSU independente.
 */
interface SefazMdfeDistDfeClient
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

<?php

namespace App\Contracts;

use App\DTO\Cnpj\CnpjRegistrationLookupResult;

interface CnpjRegistrationLookup
{
    /**
     * Consulta pública sanitizada. Nunca deve retornar QSA, CPF, capital, payload bruto
     * nem campos fora da lista permitida.
     *
     * @throws \RuntimeException falha sanitizada (indisponível, rate limit, 404, incompleto)
     */
    public function find(string $cnpj): CnpjRegistrationLookupResult;
}

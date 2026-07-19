<?php

namespace App\Services\Esocial;

use App\Contracts\EsocialEventClient;
use App\DTO\Esocial\EsocialFetchRequest;
use App\DTO\Esocial\EsocialFetchResult;

/**
 * Default fail-closed enquanto não houver uma integração M2M eSocial oficial
 * aprovada. Não realiza transporte, não consulta portal e não simula eventos.
 */
final class DisabledEsocialEventClient implements EsocialEventClient
{
    public const UNAVAILABLE_MESSAGE = 'Integração FGTS/eSocial indisponível: não há provider M2M oficial habilitado.';

    public function fetchEvents(EsocialFetchRequest $request): EsocialFetchResult
    {
        return EsocialFetchResult::failed(
            self::UNAVAILABLE_MESSAGE,
            'ESOCIAL_SOURCE_UNAVAILABLE',
        );
    }
}

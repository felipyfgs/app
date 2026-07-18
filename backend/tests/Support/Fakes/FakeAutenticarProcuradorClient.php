<?php

namespace Tests\Support\Fakes;

use App\Contracts\AutenticarProcuradorClient;
use App\DTO\Serpro\ProcuradorAuthRequest;
use App\DTO\Serpro\ProcuradorAuthResult;
use App\Enums\TermoAuthorizationState;
use Carbon\CarbonImmutable;

final class FakeAutenticarProcuradorClient implements AutenticarProcuradorClient
{
    public function authenticate(ProcuradorAuthRequest $request): ProcuradorAuthResult
    {
        // Expira no horário de Brasília (fim do dia + margem), simulado em +12h.
        $expires = CarbonImmutable::now('America/Sao_Paulo')->addHours(12);

        return new ProcuradorAuthResult(
            success: true,
            token: 'fake-procurador-'.$request->officeId.'-'.bin2hex(random_bytes(8)),
            expiresAt: $expires,
            simulated: true,
            // Fake/simulado NUNCA produz SERPRO_ACCEPTED.
            authorizationState: TermoAuthorizationState::Simulated->value,
        );
    }
}

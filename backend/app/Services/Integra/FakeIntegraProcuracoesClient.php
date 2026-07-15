<?php

namespace App\Services\Integra;

use App\Contracts\IntegraProcuracoesClient;
use App\DTO\Serpro\ProcuracaoLookupRequest;
use App\DTO\Serpro\ProcuracaoLookupResult;
use Carbon\CarbonImmutable;

final class FakeIntegraProcuracoesClient implements IntegraProcuracoesClient
{
    public function lookup(ProcuracaoLookupRequest $request): ProcuracaoLookupResult
    {
        $power = $request->powerCode ?? 'PGDASD';

        return new ProcuracaoLookupResult(
            success: true,
            powers: [
                [
                    'power_code' => $power,
                    'system_code' => 'INTEGRA_SN',
                    'service_code' => 'PGDASD',
                    'valid_from' => CarbonImmutable::now()->subMonth()->toIso8601String(),
                    'valid_to' => CarbonImmutable::now()->addYear()->toIso8601String(),
                    'status' => 'ACTIVE',
                ],
            ],
            simulated: true,
            evidenceRef: 'SIMULATED-PROCURACAO-'.$request->officeId,
        );
    }
}

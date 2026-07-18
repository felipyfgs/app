<?php

namespace App\Services\Serpro;

use RuntimeException;

/**
 * Credenciais efêmeras do gateway oficial de demonstração (Trial) da SERPRO.
 *
 * Não há OAuth mTLS nem credenciais de contrato neste fluxo: bearer e JWT são
 * fornecidos exclusivamente pelas variáveis SERPRO_TRIAL_BEARER_TOKEN e
 * SERPRO_TRIAL_JWT_TOKEN, mapeadas em serpro.environments.TRIAL.
 */
final class TrialSerproGatewayCredentials
{
    /**
     * @return array{base_url: string, bearer_token: string, jwt_token: string}
     */
    public function resolve(): array
    {
        $environment = config('serpro.environments.'.'TRIAL', []);
        $baseUrl = rtrim(trim((string) ($environment['base_url'] ?? '')), '/');
        $bearerToken = trim((string) ($environment['bearer_token'] ?? ''));
        $jwtToken = trim((string) ($environment['jwt_token'] ?? ''));

        if ($baseUrl === '' || $bearerToken === '' || $jwtToken === '') {
            throw new RuntimeException('Credenciais ou endpoint do Trial SERPRO não configurados.');
        }

        return [
            'base_url' => $baseUrl,
            'bearer_token' => $bearerToken,
            'jwt_token' => $jwtToken,
        ];
    }
}

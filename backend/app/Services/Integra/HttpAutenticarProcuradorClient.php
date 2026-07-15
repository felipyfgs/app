<?php

namespace App\Services\Integra;

use App\Contracts\AutenticarProcuradorClient;
use App\Contracts\IntegraContadorClient;
use App\DTO\Serpro\IntegraRequest;
use App\DTO\Serpro\ProcuradorAuthRequest;
use App\DTO\Serpro\ProcuradorAuthResult;
use App\Enums\TermoAuthorizationState;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

/**
 * Cliente real Autentica Procurador (ENVIOXMLASSINADO81) com cache ETag/Expires/304.
 */
final class HttpAutenticarProcuradorClient implements AutenticarProcuradorClient
{
    public function __construct(
        private readonly IntegraContadorClient $integra,
    ) {}

    public function authenticate(ProcuradorAuthRequest $request): ProcuradorAuthResult
    {
        $cacheKey = sprintf(
            'serpro:procurador:etag:%d:%s:%s',
            $request->officeId,
            $request->environment,
            substr(hash('sha256', $request->authorIdentity), 0, 16),
        );

        $cached = Cache::get($cacheKey);
        if (is_array($cached)
            && ! empty($cached['token'])
            && ! empty($cached['expires_at'])
            && CarbonImmutable::parse($cached['expires_at'])->isFuture()
        ) {
            return new ProcuradorAuthResult(
                success: true,
                token: (string) $cached['token'],
                expiresAt: CarbonImmutable::parse($cached['expires_at']),
                simulated: (bool) ($cached['simulated'] ?? false),
                authorizationState: (string) ($cached['state'] ?? TermoAuthorizationState::SerproAccepted->value),
                etag: isset($cached['etag']) ? (string) $cached['etag'] : null,
            );
        }

        $headers = [];
        if (is_array($cached) && ! empty($cached['etag'])) {
            $headers['If-None-Match'] = (string) $cached['etag'];
        }

        $integraRequest = new IntegraRequest(
            officeId: $request->officeId,
            clientId: 0,
            environment: $request->environment,
            contractorCnpj: '00000000000000',
            authorIdentity: $request->authorIdentity,
            contributorCnpj: $request->authorIdentity,
            operationKey: 'autentica_procurador.envio_xml_assinado',
            businessData: [
                'xmlAssinado' => $request->termoXml,
            ],
            headers: $headers,
            correlationId: $request->correlationId,
        );

        $response = $this->integra->execute($integraRequest);

        if ($response->httpStatus === 304 && is_array($cached) && ! empty($cached['token'])) {
            return new ProcuradorAuthResult(
                success: true,
                token: (string) $cached['token'],
                expiresAt: CarbonImmutable::parse($cached['expires_at']),
                simulated: (bool) ($cached['simulated'] ?? false),
                authorizationState: TermoAuthorizationState::SerproAccepted->value,
                etag: $response->etag ?? (string) ($cached['etag'] ?? ''),
            );
        }

        if (! $response->success) {
            return new ProcuradorAuthResult(
                success: false,
                token: null,
                expiresAt: null,
                errorCode: $response->errorCode ?? 'AUTH_PROCURADOR_FAILED',
                errorMessage: $response->errorMessage ?? 'Falha Autentica Procurador.',
                simulated: $response->simulated,
                authorizationState: TermoAuthorizationState::Rejected->value,
            );
        }

        $token = $this->extractToken($response->body, $response->dados);
        if ($token === null || $token === '') {
            return new ProcuradorAuthResult(
                success: false,
                errorCode: 'TOKEN_MISSING',
                errorMessage: 'Resposta sem token do procurador.',
                simulated: $response->simulated,
                authorizationState: TermoAuthorizationState::Rejected->value,
            );
        }

        $expires = CarbonImmutable::now('America/Sao_Paulo')->addHours(12);
        if ($response->expiresHeader) {
            try {
                $expires = CarbonImmutable::parse($response->expiresHeader);
            } catch (\Throwable) {
            }
        }

        $state = $response->simulated
            ? TermoAuthorizationState::Simulated->value
            : TermoAuthorizationState::SerproAccepted->value;

        Cache::put($cacheKey, [
            'token' => $token,
            'expires_at' => $expires->toIso8601String(),
            'etag' => $response->etag,
            'simulated' => $response->simulated,
            'state' => $state,
        ], $expires);

        return new ProcuradorAuthResult(
            success: true,
            token: $token,
            expiresAt: $expires,
            simulated: $response->simulated,
            authorizationState: $state,
            etag: $response->etag,
        );
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function extractToken(array $body, mixed $dados): ?string
    {
        foreach (['token', 'access_token', 'autenticar_procurador_token'] as $k) {
            if (! empty($body[$k]) && is_scalar($body[$k])) {
                return (string) $body[$k];
            }
        }
        if (is_array($dados)) {
            foreach (['token', 'access_token'] as $k) {
                if (! empty($dados[$k]) && is_scalar($dados[$k])) {
                    return (string) $dados[$k];
                }
            }
        }
        if (is_string($dados) && $dados !== '') {
            $decoded = json_decode($dados, true);
            if (is_array($decoded) && ! empty($decoded['token'])) {
                return (string) $decoded['token'];
            }
        }

        return null;
    }
}

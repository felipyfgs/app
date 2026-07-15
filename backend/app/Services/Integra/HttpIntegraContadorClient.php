<?php

namespace App\Services\Integra;

use App\Contracts\IntegraContadorClient;
use App\Contracts\SecureObjectStore;
use App\Contracts\SerproContractAuthenticator;
use App\DTO\Serpro\IntegraRequest;
use App\DTO\Serpro\IntegraResponse;
use App\Enums\SecureObjectPurpose;
use App\Enums\SerproEnvironment;
use App\Models\OfficeSerproAuthorization;
use App\Services\Serpro\SerproCircuitBreaker;
use App\Services\Serpro\SerproContractService;
use App\Services\Serpro\SerproHttpTransport;
use App\Services\Serpro\SerproKillSwitchService;
use RuntimeException;
use Throwable;

/**
 * Client HTTP real — monta envelope a partir de identidades persistidas.
 * Cadeia: Contratante (OAuth/mTLS) → Autor (jwt_token do procurador) → Contribuinte.
 */
final class HttpIntegraContadorClient implements IntegraContadorClient
{
    public function __construct(
        private readonly SerproContractService $contracts,
        private readonly SerproContractAuthenticator $authenticator,
        private readonly SerproHttpTransport $transport,
        private readonly SerproKillSwitchService $killSwitch,
        private readonly SerproCircuitBreaker $breaker,
        private readonly SecureObjectStore $store,
    ) {}

    public function execute(IntegraRequest $request): IntegraResponse
    {
        if ($this->killSwitch->isSolutionBlocked($request->solutionCode)) {
            return new IntegraResponse(
                success: false,
                httpStatus: 503,
                body: [],
                errorCode: 'KILL_SWITCH',
                errorMessage: 'Integra Contador temporariamente desabilitado.',
                correlationId: $request->correlationId,
            );
        }

        if (! $this->breaker->isCallAllowed($request->solutionCode)) {
            return new IntegraResponse(
                success: false,
                httpStatus: 503,
                body: [],
                errorCode: 'CIRCUIT_OPEN',
                errorMessage: 'Circuit breaker aberto para a solução.',
                correlationId: $request->correlationId,
            );
        }

        $env = SerproEnvironment::from($request->environment);
        $contract = $this->contracts->activeFor($env);
        if ($contract === null || ! $contract->isUsable()) {
            return new IntegraResponse(
                success: false,
                httpStatus: 503,
                body: [],
                errorCode: 'CONTRACT_UNAVAILABLE',
                errorMessage: 'Contrato SERPRO indisponível.',
                correlationId: $request->correlationId,
            );
        }

        if ($contract->contractor_cnpj !== strtoupper($request->contractorCnpj)) {
            return new IntegraResponse(
                success: false,
                httpStatus: 422,
                body: [],
                errorCode: 'CONTRACTOR_MISMATCH',
                errorMessage: 'Identidade contratante diverge do contrato ativo.',
                correlationId: $request->correlationId,
            );
        }

        // Token do procurador (Autor) — obrigatório na cadeia real; fail-closed.
        $procuradorToken = $this->loadProcuradorToken($request, $env);
        if ($procuradorToken === null) {
            return new IntegraResponse(
                success: false,
                httpStatus: 422,
                body: [],
                errorCode: 'PROCURADOR_TOKEN_MISSING',
                errorMessage: 'Token do procurador ausente ou expirado para o escritório.',
                correlationId: $request->correlationId,
            );
        }

        $token = $this->authenticator->authenticate($contract);
        $baseUrl = rtrim((string) config('serpro.api.base_url'), '/');
        $path = sprintf(
            '/%s/%s/%s',
            rawurlencode($request->solutionCode),
            rawurlencode($request->serviceCode),
            rawurlencode($request->operationCode),
        );

        $envelope = [
            'contratante' => ['numero' => $contract->contractor_cnpj, 'tipo' => 2],
            'autorPedidoDados' => ['numero' => $request->authorIdentity, 'tipo' => strlen($request->authorIdentity) === 11 ? 1 : 2],
            'contribuinte' => ['numero' => $request->contributorCnpj, 'tipo' => 2],
            'pedidoDados' => $request->payload,
        ];

        $body = json_encode($envelope, JSON_THROW_ON_ERROR);
        $headers = [
            'Authorization: '.$token->tokenType.' '.$token->accessToken,
            // Header oficial Integra Contador: JWT do autenticar_procurador (Autor).
            'jwt_token: '.$procuradorToken,
            'Content-Type: application/json',
            'Accept: application/json',
        ];
        foreach ($request->headers as $name => $value) {
            // Não permitir sobrescrever Authorization/jwt_token via headers do pedido.
            if (strcasecmp((string) $name, 'Authorization') === 0
                || strcasecmp((string) $name, 'jwt_token') === 0) {
                continue;
            }
            $headers[] = $name.': '.$value;
        }

        unset($procuradorToken);

        try {
            $response = $this->transport->request(
                'POST',
                $baseUrl.$path,
                null,
                $body,
                $headers,
                $request->correlationId,
            );
        } catch (\Throwable $e) {
            $this->breaker->recordFailure($request->solutionCode, 'transport');
            throw new RuntimeException('Falha de transporte Integra Contador.', 0, $e);
        }

        if ($response['status'] === 429) {
            $this->breaker->recordFailure($request->solutionCode, '429');

            return new IntegraResponse(
                success: false,
                httpStatus: 429,
                body: [],
                errorCode: 'RATE_LIMITED',
                errorMessage: 'Rate limit SERPRO.',
                retryAfterSeconds: $response['retry_after'],
                correlationId: $request->correlationId,
                latencyMs: $response['latency_ms'],
            );
        }

        if ($response['status'] >= 500) {
            $this->breaker->recordFailure($request->solutionCode, '5xx');

            return new IntegraResponse(
                success: false,
                httpStatus: $response['status'],
                body: [],
                errorCode: 'UPSTREAM_ERROR',
                errorMessage: 'Erro upstream SERPRO.',
                correlationId: $request->correlationId,
                latencyMs: $response['latency_ms'],
            );
        }

        /** @var array<string, mixed>|null $decoded */
        $decoded = json_decode($response['body'], true);
        if (! is_array($decoded)) {
            $decoded = ['_raw_type' => 'non_json'];
        }

        $success = $response['status'] >= 200 && $response['status'] < 300;
        if ($success) {
            $this->breaker->recordSuccess($request->solutionCode);
        } else {
            $this->breaker->recordFailure($request->solutionCode, '4xx');
        }

        return new IntegraResponse(
            success: $success,
            httpStatus: $response['status'],
            body: $decoded,
            headers: $response['headers'],
            errorCode: $success ? null : 'REQUEST_FAILED',
            errorMessage: $success ? null : 'Chamada Integra Contador rejeitada.',
            simulated: false,
            retryAfterSeconds: $response['retry_after'],
            correlationId: $request->correlationId,
            latencyMs: $response['latency_ms'],
        );
    }

    /**
     * Abre o token do procurador no vault (purpose SERPRO_PROCURADOR_TOKEN).
     * Nunca retorna o token em logs; null se ausente/expirado/inválido.
     */
    private function loadProcuradorToken(IntegraRequest $request, SerproEnvironment $env): ?string
    {
        $auth = OfficeSerproAuthorization::query()
            ->where('office_id', $request->officeId)
            ->where('environment', $env->value)
            ->first();

        if ($auth === null
            || $auth->procurador_token_vault_object_id === null
            || $auth->procurador_token_expires_at === null
            || $auth->procurador_token_expires_at->isPast()
        ) {
            return null;
        }

        // Identidade do autor do pedido deve bater com a autorização persistida.
        $author = strtoupper((string) $auth->author_identity);
        if ($author === '' || $author === '00000000000000') {
            return null;
        }
        if (strtoupper($request->authorIdentity) !== $author) {
            return null;
        }

        $aad = SecureObjectPurpose::SerproProcuradorToken->aadBase([
            'office_id' => $auth->office_id,
            'environment' => $env->value,
            'author_identity' => $auth->author_identity,
        ]);

        try {
            $raw = $this->store->get($auth->procurador_token_vault_object_id, $aad);
            /** @var array{token?: string}|null $payload */
            $payload = json_decode($raw, true);
            $token = is_array($payload) ? (string) ($payload['token'] ?? '') : '';
            if ($token === '') {
                return null;
            }

            return $token;
        } catch (Throwable) {
            return null;
        }
    }
}

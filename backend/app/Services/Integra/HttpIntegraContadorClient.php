<?php

namespace App\Services\Integra;

use App\Contracts\IntegraContadorClient;
use App\Contracts\SecureObjectStore;
use App\Contracts\SerproContractAuthenticator;
use App\DTO\Serpro\IntegraRequest;
use App\DTO\Serpro\IntegraResponse;
use App\Enums\FiscalSourceProvenance;
use App\Enums\SecureObjectPurpose;
use App\Enums\SerproEnvironment;
use App\Models\OfficeSerproAuthorization;
use App\Services\Serpro\Catalog\OperationCoordinateResolver;
use App\Services\Serpro\SerproCircuitBreaker;
use App\Services\Serpro\SerproContractService;
use App\Services\Serpro\SerproHttpTransport;
use App\Services\Serpro\SerproKillSwitchService;
use RuntimeException;
use Throwable;

/**
 * Client HTTP real — rotas funcionais, envelope oficial, headers sanitizados.
 * Cadeia: Contratante (Bearer+jwt_token) → Autor (autenticar_procurador_token) → Contribuinte.
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
        private readonly ?OperationCoordinateResolver $coordinates = null,
    ) {}

    public function execute(IntegraRequest $request): IntegraResponse
    {
        $operationKey = $request->operationKey;
        $coords = null;
        $solutionForBreaker = $request->solutionCode ?? 'INTEGRA';

        if ($operationKey !== null) {
            try {
                $coords = ($this->coordinates ?? app(OperationCoordinateResolver::class))
                    ->resolveExecutable($operationKey);
                $solutionForBreaker = $coords['id_sistema'];
            } catch (RuntimeException $e) {
                if (str_contains($e->getMessage(), 'CAPABILITY_NOT_IMPLEMENTED')) {
                    return $this->fail($request, 422, 'CAPABILITY_NOT_IMPLEMENTED', $e->getMessage(), $operationKey);
                }
                throw $e;
            }
        }

        if ($this->killSwitch->isSolutionBlocked($solutionForBreaker)) {
            return $this->fail($request, 503, 'KILL_SWITCH', 'Integra Contador temporariamente desabilitado.', $operationKey);
        }

        if (! $this->breaker->isCallAllowed($solutionForBreaker)) {
            return $this->fail($request, 503, 'CIRCUIT_OPEN', 'Circuit breaker aberto para a solução.', $operationKey);
        }

        $env = SerproEnvironment::from($request->environment);
        $contract = $this->contracts->activeFor($env);
        if ($contract === null || ! $contract->isUsable()) {
            return $this->fail($request, 503, 'CONTRACT_UNAVAILABLE', 'Contrato SERPRO indisponível.', $operationKey);
        }

        if ($contract->contractor_cnpj !== strtoupper($request->contractorCnpj)) {
            return $this->fail($request, 422, 'CONTRACTOR_MISMATCH', 'Identidade contratante diverge do contrato ativo.', $operationKey);
        }

        $procuradorToken = $this->loadProcuradorToken($request, $env);
        if ($procuradorToken === null) {
            return $this->fail($request, 422, 'PROCURADOR_TOKEN_MISSING', 'Token do procurador ausente ou expirado para o escritório.', $operationKey);
        }

        try {
            $token = $this->authenticator->authenticate($contract);
            $token->assertComplete();
        } catch (Throwable $e) {
            return $this->fail($request, 503, 'CONTRACT_UNHEALTHY', 'Falha de autenticação do contrato: '.$e->getMessage(), $operationKey);
        }

        $idSistema = $coords['id_sistema'] ?? (string) $request->solutionCode;
        $idServico = $coords['id_servico'] ?? (string) $request->operationCode;
        $versao = $coords['versao_sistema'] ?? '1.0';
        $route = $coords !== null ? $coords['route']->path() : '/Consultar';
        $dadosMode = $coords['dados_mode'] ?? 'JSON_STRING';
        $requestTag = $request->resolvedRequestTag();

        $dadosString = $this->serializeDados($request, $dadosMode);

        $envelope = [
            'contratante' => [
                'numero' => $contract->contractor_cnpj,
                'tipo' => 2,
            ],
            'autorPedidoDados' => [
                'numero' => $request->authorIdentity,
                'tipo' => strlen($request->authorIdentity) === 11 ? 1 : 2,
            ],
            'contribuinte' => [
                'numero' => $request->contributorCnpj,
                'tipo' => strlen($request->contributorCnpj) === 11 ? 1 : 2,
            ],
            'pedidoDados' => [
                'idSistema' => $idSistema,
                'idServico' => $idServico,
                'versaoSistema' => $versao,
                'dados' => $dadosString,
            ],
        ];

        $body = json_encode($envelope, JSON_THROW_ON_ERROR);
        $jwt = $token->officialJwt();
        $headers = [
            'Authorization: '.$token->tokenType.' '.$token->accessToken,
            'jwt_token: '.$jwt,
            'autenticar_procurador_token: '.$procuradorToken,
            'X-Request-Tag: '.$requestTag,
            'Content-Type: application/json',
            'Accept: application/json',
        ];
        foreach ($request->headers as $name => $value) {
            $ln = strtolower((string) $name);
            if (in_array($ln, ['authorization', 'jwt_token', 'autenticar_procurador_token', 'x-request-tag'], true)) {
                continue;
            }
            $headers[] = $name.': '.$value;
        }

        unset($procuradorToken, $jwt);

        $baseUrl = rtrim((string) config('serpro.api.base_url'), '/');
        $url = $baseUrl.$route;

        try {
            $response = $this->transport->request(
                'POST',
                $url,
                null,
                $body,
                $headers,
                $request->correlationId,
            );
        } catch (Throwable $e) {
            $this->breaker->recordFailure($solutionForBreaker, 'transport');
            throw new RuntimeException('Falha de transporte Integra Contador.', 0, $e);
        }

        return $this->normalizeResponse(
            $response,
            $request,
            $operationKey,
            $requestTag,
            ltrim($route, '/'),
            $solutionForBreaker,
        );
    }

    /**
     * @param  array{status: int, body: string, headers: array<string, string>, retry_after: ?int, latency_ms: ?int}  $response
     */
    private function normalizeResponse(
        array $response,
        IntegraRequest $request,
        ?string $operationKey,
        string $requestTag,
        string $route,
        string $solutionForBreaker,
    ): IntegraResponse {
        $status = $response['status'];
        $headers = $response['headers'] ?? [];
        $etag = $this->headerValue($headers, 'etag');
        $expires = $this->headerValue($headers, 'expires');

        if ($status === 429) {
            $this->breaker->recordFailure($solutionForBreaker, '429');

            return new IntegraResponse(
                success: false,
                httpStatus: 429,
                body: [],
                headers: $headers,
                errorCode: 'RATE_LIMITED',
                errorMessage: 'Rate limit SERPRO.',
                simulated: false,
                retryAfterSeconds: $response['retry_after'],
                correlationId: $request->correlationId,
                latencyMs: $response['latency_ms'],
                etag: $etag,
                expiresHeader: $expires,
                operationKey: $operationKey,
                requestTag: $requestTag,
                functionalRoute: $route,
                sourceProvenance: FiscalSourceProvenance::SerproReal->value,
            );
        }

        if ($status >= 500) {
            $this->breaker->recordFailure($solutionForBreaker, '5xx');

            return new IntegraResponse(
                success: false,
                httpStatus: $status,
                body: [],
                headers: $headers,
                errorCode: 'UPSTREAM_ERROR',
                errorMessage: 'Erro upstream SERPRO.',
                correlationId: $request->correlationId,
                latencyMs: $response['latency_ms'],
                operationKey: $operationKey,
                requestTag: $requestTag,
                functionalRoute: $route,
                sourceProvenance: FiscalSourceProvenance::SerproReal->value,
            );
        }

        /** @var array<string, mixed>|null $decoded */
        $decoded = json_decode($response['body'], true);
        if (! is_array($decoded)) {
            $decoded = $response['body'] !== '' ? ['_raw_type' => 'non_json'] : [];
        }

        $dados = $this->parseDados($decoded);
        $mensagens = $this->extractMensagens($decoded);
        $businessStatus = isset($decoded['status']) ? (string) $decoded['status'] : null;
        $tempoEspera = null;
        foreach (['tempoEspera', 'tempo_espera'] as $k) {
            if (isset($decoded[$k]) && is_numeric($decoded[$k])) {
                $tempoEspera = max(1, (int) $decoded[$k]);
            }
        }
        if ($tempoEspera === null && $etag !== null && preg_match('/tempoEspera[=:](\d+)/i', $etag, $m)) {
            $tempoEspera = max(1, (int) $m[1]);
        }
        if ($tempoEspera === null && $response['retry_after'] !== null) {
            $tempoEspera = $response['retry_after'];
        }

        if (in_array($status, [202, 204], true)) {
            return new IntegraResponse(
                success: false,
                httpStatus: $status,
                body: $decoded,
                headers: $headers,
                errorCode: 'STILL_PROCESSING',
                errorMessage: 'Operação em processamento na fonte.',
                simulated: false,
                retryAfterSeconds: $tempoEspera ?? 30,
                correlationId: $request->correlationId,
                latencyMs: $response['latency_ms'],
                etag: $etag,
                expiresHeader: $expires,
                businessStatus: $businessStatus ?? 'PROCESSANDO',
                mensagens: $mensagens,
                dados: $dados,
                operationKey: $operationKey,
                requestTag: $requestTag,
                functionalRoute: $route,
                sourceProvenance: FiscalSourceProvenance::SerproReal->value,
            );
        }

        $success = $status >= 200 && $status < 300 && $status !== 204;
        if ($success) {
            $this->breaker->recordSuccess($solutionForBreaker);
        } else {
            $this->breaker->recordFailure($solutionForBreaker, '4xx');
        }

        return new IntegraResponse(
            success: $success,
            httpStatus: $status,
            body: $decoded,
            headers: $headers,
            errorCode: $success ? null : 'REQUEST_FAILED',
            errorMessage: $success ? null : 'Chamada Integra Contador rejeitada.',
            simulated: false,
            retryAfterSeconds: $tempoEspera,
            correlationId: $request->correlationId,
            latencyMs: $response['latency_ms'],
            etag: $etag,
            expiresHeader: $expires,
            businessStatus: $businessStatus,
            mensagens: $mensagens,
            dados: $dados,
            operationKey: $operationKey,
            requestTag: $requestTag,
            functionalRoute: $route,
            sourceProvenance: FiscalSourceProvenance::SerproReal->value,
        );
    }

    private function serializeDados(IntegraRequest $request, string $dadosMode): string
    {
        if ($dadosMode === 'EMPTY') {
            return '';
        }

        // Preferir businessData; payload legado pode já trazer pedidoDados.dados
        if ($request->businessData !== []) {
            $data = $request->businessData;
            unset($data['__scenario']);

            return json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        }

        if (isset($request->payload['dados']) && is_string($request->payload['dados'])) {
            // Já serializado uma vez — não re-escapar
            return $request->payload['dados'];
        }

        if (isset($request->payload['pedidoDados']['dados']) && is_string($request->payload['pedidoDados']['dados'])) {
            return $request->payload['pedidoDados']['dados'];
        }

        // Legado: payload com chaves de negócio (protocolo, contribuinte…)
        $legacy = $request->payload;
        unset($legacy['idSistema'], $legacy['idServico'], $legacy['versaoSistema'], $legacy['dados']);
        if ($legacy === [] && isset($request->payload['dados']) && is_array($request->payload['dados'])) {
            $legacy = $request->payload['dados'];
        }

        if ($legacy === []) {
            return '';
        }

        return json_encode($legacy, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }

    /**
     * @param  array<string, mixed>  $decoded
     */
    private function parseDados(array $decoded): mixed
    {
        if (! array_key_exists('dados', $decoded)) {
            return null;
        }
        $raw = $decoded['dados'];
        if (is_array($raw)) {
            return $raw;
        }
        if (! is_string($raw) || $raw === '') {
            return $raw;
        }
        $parsed = json_decode($raw, true);

        return is_array($parsed) ? $parsed : $raw;
    }

    /**
     * @param  array<string, mixed>  $decoded
     * @return list<array{codigo?: string, texto?: string}>
     */
    private function extractMensagens(array $decoded): array
    {
        $raw = $decoded['mensagens'] ?? $decoded['messages'] ?? [];
        if (! is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $m) {
            if (! is_array($m)) {
                continue;
            }
            $out[] = [
                'codigo' => isset($m['codigo']) ? (string) $m['codigo'] : (isset($m['code']) ? (string) $m['code'] : null),
                'texto' => isset($m['texto']) ? (string) $m['texto'] : (isset($m['mensagem']) ? (string) $m['mensagem'] : null),
            ];
        }

        return $out;
    }

    /**
     * @param  array<string, string>  $headers
     */
    private function headerValue(array $headers, string $name): ?string
    {
        foreach ($headers as $k => $v) {
            if (strcasecmp((string) $k, $name) === 0) {
                return (string) $v;
            }
        }

        return null;
    }

    private function fail(
        IntegraRequest $request,
        int $http,
        string $code,
        string $message,
        ?string $operationKey,
    ): IntegraResponse {
        return new IntegraResponse(
            success: false,
            httpStatus: $http,
            body: [],
            errorCode: $code,
            errorMessage: $message,
            correlationId: $request->correlationId,
            operationKey: $operationKey,
            requestTag: $request->resolvedRequestTag(),
            sourceProvenance: FiscalSourceProvenance::SerproReal->value,
        );
    }

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

            return $token !== '' ? $token : null;
        } catch (Throwable) {
            return null;
        }
    }
}

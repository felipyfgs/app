<?php

namespace App\Services\Integra;

use App\Contracts\IntegraContadorClient;
use App\Contracts\SecureObjectStore;
use App\Contracts\SerproContractAuthenticator;
use App\DTO\Serpro\IntegraRequest;
use App\DTO\Serpro\IntegraResponse;
use App\Enums\ClientProcuracaoSyncStatus;
use App\Enums\FiscalSourceProvenance;
use App\Enums\SecureObjectPurpose;
use App\Enums\SerproEnvironment;
use App\Enums\SerproFunctionalRoute;
use App\Models\ClientProcuracaoSnapshot;
use App\Models\OfficeSerproAuthorization;
use App\Models\TaxProxyPower;
use App\Services\Serpro\Catalog\OperationCoordinateResolver;
use App\Services\Serpro\SerproCircuitBreaker;
use App\Services\Serpro\SerproContractService;
use App\Services\Serpro\SerproHttpTransport;
use App\Services\Serpro\SerproKillSwitchService;
use App\Services\Serpro\SerproRateLimiter;
use App\Support\LogSanitizer;
use RuntimeException;
use Throwable;

/**
 * Client HTTP real — rotas funcionais, envelope oficial, headers sanitizados.
 * Cadeia: Contratante (Bearer+jwt_token) → Autor (autenticar_procurador_token) → Contribuinte.
 */
final class HttpIntegraContadorClient implements IntegraContadorClient
{
    /** Headers permitidos além dos oficiais do contrato/procurador. */
    private const HEADER_ALLOWLIST = [
        'if-none-match',
        'accept',
        'content-type',
        'x-correlation-id',
    ];

    private const OFFICIAL_HEADER_NAMES = [
        'authorization',
        'jwt_token',
        'autenticar_procurador_token',
        'x-request-tag',
        'content-type',
        'accept',
    ];

    public function __construct(
        private readonly SerproContractService $contracts,
        private readonly SerproContractAuthenticator $authenticator,
        private readonly SerproHttpTransport $transport,
        private readonly SerproKillSwitchService $killSwitch,
        private readonly SerproCircuitBreaker $breaker,
        private readonly SecureObjectStore $store,
        private readonly ?OperationCoordinateResolver $coordinates = null,
        private readonly ?SerproRateLimiter $rateLimiter = null,
    ) {}

    public function execute(IntegraRequest $request): IntegraResponse
    {
        $operationKey = $request->operationKey;
        $requestTag = $request->resolvedRequestTag();

        try {
            $coords = ($this->coordinates ?? app(OperationCoordinateResolver::class))
                ->resolveExecutable($operationKey);
        } catch (RuntimeException $e) {
            if (str_contains($e->getMessage(), 'CAPABILITY_NOT_IMPLEMENTED')
                || str_contains($e->getMessage(), 'CAPABILITY_NOT_EXECUTABLE')
            ) {
                return $this->fail($request, 422, 'CAPABILITY_NOT_IMPLEMENTED', $e->getMessage());
            }
            throw $e;
        }

        $solutionForBreaker = (string) $coords['id_sistema'];
        $routeEnum = $coords['route'] instanceof SerproFunctionalRoute
            ? $coords['route']
            : SerproFunctionalRoute::from((string) $coords['route']);
        $routePath = $routeEnum->path();
        $routeName = $routeEnum->value;
        $isMutating = (bool) ($coords['is_mutating'] || $request->isMutating);

        if ($this->killSwitch->isSolutionBlocked($solutionForBreaker)) {
            return $this->fail($request, 503, 'KILL_SWITCH', 'Integra Contador temporariamente desabilitado.');
        }

        if (! $this->breaker->isCallAllowed($solutionForBreaker)) {
            return $this->fail($request, 503, 'CIRCUIT_OPEN', 'Circuit breaker aberto para a solução.');
        }

        try {
            ($this->rateLimiter ?? app(SerproRateLimiter::class))->attempt($request->officeId, $operationKey);
        } catch (RuntimeException $e) {
            return $this->fail($request, 429, 'RATE_LIMIT_LOCAL', $e->getMessage());
        }

        $env = SerproEnvironment::from($request->environment);
        $contract = $this->contracts->activeFor($env);
        if ($contract === null || ! $contract->isUsable()) {
            return $this->fail($request, 503, 'CONTRACT_UNAVAILABLE', 'Contrato SERPRO indisponível.');
        }

        $authMode = (string) ($coords['auth_mode'] ?? 'PROCURATOR_WHEN_REPRESENTING');
        $proxyRule = (string) ($coords['proxy_rule'] ?? 'NOT_APPLICABLE');
        $isAutenticaProcurador = $authMode === 'CONTRACT_ONLY'
            || $operationKey === 'autentica_procurador.envio_xml_assinado';

        if (! $isAutenticaProcurador
            && $contract->contractor_cnpj !== $request->contractorCnpj
        ) {
            return $this->fail($request, 422, 'CONTRACTOR_MISMATCH', 'Identidade contratante diverge do contrato ativo.');
        }

        // Poder e-CAC antes do transporte, quando exigido
        $powerCheck = $this->assertProxyPower($request, $coords, $proxyRule);
        if ($powerCheck !== null) {
            return $powerCheck;
        }

        /** @var list<string> $requiredPowers */
        $requiredPowers = $coords['required_proxy_powers'] ?? [];
        $needsProcuradorToken = $this->requiresProcuradorToken(
            $authMode,
            $proxyRule,
            $request,
            $isAutenticaProcurador,
            $requiredPowers,
        );

        $procuradorToken = null;
        if ($needsProcuradorToken) {
            $procuradorToken = $this->loadProcuradorToken($request, $env);
            if ($procuradorToken === null) {
                return $this->fail(
                    $request,
                    422,
                    'PROCURADOR_TOKEN_MISSING',
                    'Token do procurador ausente ou expirado para o escritório.',
                );
            }
        }

        try {
            $token = $this->authenticator->authenticate($contract);
            $token->assertComplete();
        } catch (Throwable $e) {
            return $this->fail(
                $request,
                503,
                'CONTRACT_UNHEALTHY',
                'Falha de autenticação do contrato: '.$e->getMessage(),
            );
        }

        $idSistema = (string) $coords['id_sistema'];
        $idServico = (string) $coords['id_servico'];
        $versao = (string) $coords['versao_sistema'];
        $dadosMode = (string) ($coords['dados_mode'] ?? 'JSON_STRING');

        $dadosString = $this->serializeDados($request, $dadosMode);

        $envelope = [
            'contratante' => [
                'numero' => $contract->contractor_cnpj,
                'tipo' => 2,
            ],
            'autorPedidoDados' => $request->author->toEnvelope(),
            'contribuinte' => $request->contributor->toEnvelope(),
            'pedidoDados' => [
                'idSistema' => $idSistema,
                'idServico' => $idServico,
                'versaoSistema' => $versao,
                'dados' => $dadosString,
            ],
        ];

        $body = json_encode($envelope, JSON_THROW_ON_ERROR);
        $baseUrl = rtrim((string) config('serpro.api.base_url'), '/');
        $url = $baseUrl.$routePath;

        $attempt = 0;
        $maxAttempts = 2; // 1 tentativa + 1 renovação OAuth após 401
        $lastResponse = null;

        while ($attempt < $maxAttempts) {
            $attempt++;
            $jwt = $token->officialJwt();
            $headers = $this->buildHeaders($token->tokenType, $token->accessToken, $jwt, $requestTag, $procuradorToken, $request->headers);
            unset($jwt);

            try {
                $lastResponse = $this->transport->request(
                    'POST',
                    $url,
                    null,
                    $body,
                    $headers,
                    $request->correlationId,
                );
            } catch (Throwable $e) {
                $this->breaker->recordFailure($solutionForBreaker, 'transport');
                // Timeout/falha ambígua em mutação: NÃO retry automático
                if ($isMutating) {
                    return new IntegraResponse(
                        success: false,
                        httpStatus: 0,
                        body: [],
                        errorCode: 'MUTATION_TIMEOUT_PENDING',
                        errorMessage: 'Timeout ambíguo em mutação — pendente de conciliação.',
                        correlationId: $request->correlationId,
                        operationKey: $operationKey,
                        requestTag: $requestTag,
                        functionalRoute: $routeName,
                        sourceProvenance: FiscalSourceProvenance::SerproReal->value,
                    );
                }
                throw new RuntimeException('Falha de transporte Integra Contador.', 0, $e);
            }

            if ($lastResponse['status'] === 401 && $attempt < $maxAttempts) {
                $this->authenticator->invalidate($contract);
                try {
                    $token = $this->authenticator->authenticate($contract);
                    $token->assertComplete();
                } catch (Throwable $e) {
                    return $this->fail(
                        $request,
                        503,
                        'CONTRACT_UNHEALTHY',
                        'Falha ao renovar OAuth após 401: '.$e->getMessage(),
                    );
                }

                continue; // mesma tag, mesma body
            }

            break;
        }

        return $this->normalizeResponse(
            $lastResponse ?? ['status' => 0, 'body' => '', 'headers' => [], 'retry_after' => null, 'latency_ms' => null],
            $request,
            $operationKey,
            $requestTag,
            $routeName,
            $solutionForBreaker,
        );
    }

    /**
     * @param  list<string>  $requiredPowers
     */
    private function requiresProcuradorToken(
        string $authMode,
        string $proxyRule,
        IntegraRequest $request,
        bool $isAutenticaProcurador,
        array $requiredPowers = [],
    ): bool {
        if ($isAutenticaProcurador || $authMode === 'CONTRACT_ONLY') {
            return false;
        }

        if ($authMode === 'PROCURATOR_REQUIRED' || $proxyRule === 'REQUIRED') {
            return true;
        }

        $isRepresenting = $request->author->numero !== $request->contributor->numero;
        if (! $isRepresenting) {
            return false;
        }

        // Representação: token quando catálogo exige poder/proxy ou auth_mode de procurador
        if ($proxyRule === 'NOT_APPLICABLE' && $requiredPowers === [] && $authMode === '') {
            return false;
        }

        if ($proxyRule === 'NOT_APPLICABLE' && $requiredPowers === []
            && ! in_array($authMode, ['PROCURATOR_WHEN_REPRESENTING', 'PROCURATOR_REQUIRED'], true)
        ) {
            return false;
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $coords
     */
    private function assertProxyPower(IntegraRequest $request, array $coords, string $proxyRule): ?IntegraResponse
    {
        if (in_array($proxyRule, ['NOT_APPLICABLE', 'EVENT_DEPENDENT'], true)) {
            return null;
        }

        /** @var list<string> $powers */
        $powers = $coords['required_proxy_powers'] ?? [];
        if ($powers === [] && ! empty($coords['required_proxy_power'])) {
            $powers = preg_split('/[\s,]+/', (string) $coords['required_proxy_power']) ?: [];
        }
        $powers = array_values(array_filter(array_map('strval', $powers)));
        if ($powers === []) {
            return null;
        }

        // Só exige poder quando a relação autor–contribuinte implica representação
        if ($proxyRule === 'REQUIRED_WHEN_REPRESENTING'
            && $request->author->numero === $request->contributor->numero
        ) {
            return null;
        }

        // Preferir projeção oficial ClientProcuracaoSnapshot quando existir.
        try {
            $snapshot = ClientProcuracaoSnapshot::query()
                ->where('office_id', $request->officeId)
                ->where('client_id', $request->clientId)
                ->where('environment', $request->environment)
                ->first();

            if ($snapshot !== null) {
                if ($snapshot->status === ClientProcuracaoSyncStatus::Expired) {
                    return $this->fail(
                        $request,
                        422,
                        'PROXY_POWER_EXPIRED',
                        'Procuração vencida para a operação.',
                    );
                }
                if ($snapshot->status === ClientProcuracaoSyncStatus::Missing) {
                    return $this->fail(
                        $request,
                        422,
                        'PROXY_POWER_MISSING',
                        'Poder e-CAC obrigatório ausente: '.implode(',', $powers),
                    );
                }
                if ($snapshot->status === ClientProcuracaoSyncStatus::Authorized
                    && $snapshot->isUsableForRequiredPower()
                ) {
                    return null;
                }
            }

            $has = TaxProxyPower::query()
                ->where('office_id', $request->officeId)
                ->where('client_id', $request->clientId)
                ->whereIn('power_code', $powers)
                ->where(function ($q): void {
                    $q->whereNull('valid_to')->orWhere('valid_to', '>', now());
                })
                ->exists();
        } catch (Throwable) {
            return $this->fail(
                $request,
                503,
                'PROXY_POWER_UNAVAILABLE',
                'Não foi possível validar o poder e-CAC obrigatório.',
            );
        }

        if (! $has) {
            return $this->fail(
                $request,
                422,
                'PROXY_POWER_MISSING',
                'Poder e-CAC obrigatório ausente: '.implode(',', $powers),
            );
        }

        return null;
    }

    /**
     * @param  array<string, string>  $extraHeaders
     * @return list<string>
     */
    private function buildHeaders(
        string $tokenType,
        string $accessToken,
        string $jwt,
        string $requestTag,
        ?string $procuradorToken,
        array $extraHeaders,
    ): array {
        $headers = [
            'Authorization: '.$tokenType.' '.$accessToken,
            'jwt_token: '.$jwt,
            'X-Request-Tag: '.substr($requestTag, 0, 32),
            'Content-Type: application/json',
            'Accept: application/json',
        ];
        if ($procuradorToken !== null && $procuradorToken !== '') {
            $headers[] = 'autenticar_procurador_token: '.$procuradorToken;
        }

        foreach ($extraHeaders as $name => $value) {
            $ln = strtolower((string) $name);
            if (in_array($ln, self::OFFICIAL_HEADER_NAMES, true)) {
                continue; // não sobrescreve oficiais
            }
            if (! in_array($ln, self::HEADER_ALLOWLIST, true)) {
                continue; // descarta header arbitrário
            }
            $headers[] = $name.': '.$value;
        }

        return $headers;
    }

    /**
     * @param  array{status: int, body: string, headers: array<string, string>, retry_after: ?int, latency_ms: ?int}  $response
     */
    private function normalizeResponse(
        array $response,
        IntegraRequest $request,
        string $operationKey,
        string $requestTag,
        string $route,
        string $solutionForBreaker,
    ): IntegraResponse {
        $status = $response['status'];
        $headers = $response['headers'] ?? [];
        $etag = $this->sanitizeEtag($this->headerValue($headers, 'etag'));
        $expires = $this->headerValue($headers, 'expires');

        if ($status === 429) {
            $this->breaker->recordFailure($solutionForBreaker, '429');

            return new IntegraResponse(
                success: false,
                httpStatus: 429,
                body: [],
                headers: $this->publicHeaders($headers),
                errorCode: 'RATE_LIMITED',
                errorMessage: 'Rate limit SERPRO.',
                simulated: false,
                retryAfterSeconds: $response['retry_after'] ?? 60,
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

        if ($status === 503) {
            $this->breaker->recordFailure($solutionForBreaker, '503');

            return new IntegraResponse(
                success: false,
                httpStatus: 503,
                body: [],
                headers: $this->publicHeaders($headers),
                errorCode: 'UPSTREAM_UNAVAILABLE',
                errorMessage: 'SERPRO temporariamente indisponível.',
                simulated: false,
                retryAfterSeconds: $response['retry_after'] ?? 30,
                correlationId: $request->correlationId,
                latencyMs: $response['latency_ms'],
                operationKey: $operationKey,
                requestTag: $requestTag,
                functionalRoute: $route,
                sourceProvenance: FiscalSourceProvenance::SerproReal->value,
            );
        }

        if ($status === 304) {
            return new IntegraResponse(
                success: true,
                httpStatus: 304,
                body: [],
                headers: $this->publicHeaders($headers),
                errorCode: 'NOT_MODIFIED',
                errorMessage: 'Conteúdo não modificado (cache/ETag).',
                simulated: false,
                correlationId: $request->correlationId,
                latencyMs: $response['latency_ms'],
                etag: $etag,
                expiresHeader: $expires,
                businessStatus: 'NOT_MODIFIED',
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
                headers: $this->publicHeaders($headers),
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
                body: $this->sanitizeBodyKeys($decoded),
                headers: $this->publicHeaders($headers),
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
            body: $this->sanitizeBodyKeys($decoded),
            headers: $this->publicHeaders($headers),
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

        // Preferir businessData — serializado exatamente uma vez
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

    /**
     * ETag pode transportar token/protocolo. Permanece apenas em memória para o
     * fluxo assíncrono; persistência é responsabilidade de um cofre criptografado.
     */
    private function sanitizeEtag(?string $etag): ?string
    {
        if ($etag === null || $etag === '') {
            return null;
        }

        return $etag;
    }

    /**
     * @param  array<string, string>  $headers
     * @return array<string, string>
     */
    private function publicHeaders(array $headers): array
    {
        $allowed = ['retry-after', 'expires', 'content-type', 'x-request-id'];
        $out = [];
        foreach ($headers as $k => $v) {
            $lk = strtolower((string) $k);
            if (in_array($lk, $allowed, true)) {
                $out[$lk] = (string) $v;
            }
            // etag intencionalmente omitido da superfície pública default
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    private function sanitizeBodyKeys(array $body): array
    {
        foreach ($body as $key => $value) {
            $lower = strtolower((string) $key);
            $blocked = ['access_token', 'jwt_token', 'autenticar_procurador_token', 'xmlassinado', 'pfx', 'password', 'private_key', 'consumer_secret', 'termo_xml'];
            if (in_array($lower, $blocked, true)) {
                unset($body[$key]);

                continue;
            }
            if (is_array($value)) {
                $body[$key] = $this->sanitizeBodyKeys($value);
            } elseif (is_string($value) && LogSanitizer::looksLikeSecret($value)) {
                $body[$key] = '[redacted]';
            }
        }

        return $body;
    }

    private function fail(
        IntegraRequest $request,
        int $http,
        string $code,
        string $message,
    ): IntegraResponse {
        return new IntegraResponse(
            success: false,
            httpStatus: $http,
            body: [],
            errorCode: $code,
            errorMessage: $message,
            correlationId: $request->correlationId,
            operationKey: $request->operationKey,
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
        if ($request->authorIdentity !== $author) {
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

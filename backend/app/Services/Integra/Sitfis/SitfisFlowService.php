<?php

namespace App\Services\Integra\Sitfis;

use App\Contracts\IntegraContadorClient;
use App\DTO\Fiscal\FiscalAdapterRequest;
use App\DTO\Fiscal\FiscalAdapterResult;
use App\DTO\Serpro\IntegraRequest;
use App\DTO\Serpro\IntegraResponse;
use App\Enums\FiscalCoverage;
use App\Enums\FiscalFindingSeverity;
use App\Enums\FiscalRunResult;
use App\Enums\FiscalSituation;
use App\Enums\SerproUsageResult;
use App\Services\Integra\IntegraEligibilityService;
use App\Services\Serpro\Usage\UsageLedgerService;
use App\Services\Serpro\Usage\UsageReserveRequest;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Orquestra SITFIS: solicitação → protocolo → espera mínima → emissão com polling respeitoso.
 * Correlação do protocolo fica em progress da run (e correlation_id da run).
 * Gates: elegibilidade Integra + ledger de uso (mesmo pipeline de Simples/MEI).
 */
final class SitfisFlowService
{
    public function __construct(
        private readonly IntegraContadorClient $integra,
        private readonly SitfisIdentityResolver $identities,
        private readonly SitfisReportParser $parser,
        private readonly IntegraEligibilityService $eligibility,
        private readonly UsageLedgerService $ledger,
    ) {}

    public function execute(FiscalAdapterRequest $request): FiscalAdapterResult
    {
        $cfg = $this->config();
        $state = SitfisProtocolState::fromProgress($request->progress);
        $now = CarbonImmutable::now();

        try {
            $ids = $this->identities->resolve($request->office, $request->client);
        } catch (RuntimeException $e) {
            return FiscalAdapterResult::blocked($e->getMessage(), 'SITFIS_IDENTITY');
        }

        // Fase 1: solicitar protocolo se ainda não houver
        if (! $state->hasProtocol()) {
            return $this->solicit($request, $ids, $cfg, $now);
        }

        // Fase 2: espera mínima oficial — sem chamada faturável/agressiva
        if (! $state->canAttemptEmit($now)) {
            $wait = $state->secondsUntilEmitAllowed($now);
            $wait = max(1, $wait);
            $next = $state->with(
                phase: SitfisProtocolState::PHASE_WAITING_MIN_PERIOD,
                requeueAfterSeconds: $wait,
            );

            return $this->processingResult(
                state: $next,
                message: 'Aguardando prazo mínimo oficial antes da emissão do relatório SITFIS.',
                requeueAfter: $wait,
            );
        }

        // Fase 3: emitir / consultar resultado
        return $this->emit($request, $ids, $cfg, $state, $now);
    }

    /**
     * @param  array{environment: \App\Enums\SerproEnvironment, contract: \App\Models\SerproContract, contractor_cnpj: string, author_identity: string, contributor_cnpj: string}  $ids
     * @param  array<string, mixed>  $cfg
     */
    private function solicit(FiscalAdapterRequest $request, array $ids, array $cfg, CarbonImmutable $now): FiscalAdapterResult
    {
        $correlation = $request->run->correlation_id ?? (string) Str::uuid();
        $response = $this->call(
            $request,
            $ids,
            (string) $cfg['solicit_operation'],
            [
                'idSistema' => 'SITFIS',
                'idServico' => (string) $cfg['solicit_operation'],
                'versaoSistema' => '1.0',
                'dados' => json_encode([
                    'contribuinte' => $ids['contributor_cnpj'],
                ], JSON_THROW_ON_ERROR),
            ],
            $correlation,
        );

        if (! $response->success) {
            if ($this->isGateBlock($response)) {
                return FiscalAdapterResult::blocked(
                    $response->errorMessage ?? 'Elegibilidade/orçamento SITFIS negado.',
                    $response->errorCode ?? 'SITFIS_GATE',
                );
            }

            return FiscalAdapterResult::failed(
                $response->errorMessage ?? 'Falha ao solicitar relatório SITFIS.',
                $response->errorCode ?? 'SITFIS_SOLICIT_FAILED',
                FiscalCoverage::Full,
            );
        }

        $protocol = $this->extractProtocol($response->body);
        if ($protocol === null || $protocol === '') {
            return FiscalAdapterResult::failed(
                'Solicitação SITFIS sem protocolo correlacionável.',
                'SITFIS_PROTOCOL_MISSING',
                FiscalCoverage::Full,
            );
        }

        $minWait = max(1, (int) $cfg['min_wait_seconds']);
        $notBefore = $now->addSeconds($minWait);
        $state = new SitfisProtocolState(
            phase: SitfisProtocolState::PHASE_WAITING_MIN_PERIOD,
            protocol: $protocol,
            requestedAt: $now,
            notBefore: $notBefore,
            pollCount: 0,
            lastPollAt: null,
            correlationId: $correlation,
            requeueAfterSeconds: $minWait,
            simulated: $response->simulated,
        );

        return $this->processingResult(
            state: $state,
            message: 'Protocolo SITFIS obtido; aguardando prazo mínimo oficial.',
            requeueAfter: $minWait,
            // Evidência leve da aceitação (não é o relatório final)
            evidenceBytes: null,
        );
    }

    /**
     * @param  array{environment: \App\Enums\SerproEnvironment, contract: \App\Models\SerproContract, contractor_cnpj: string, author_identity: string, contributor_cnpj: string}  $ids
     * @param  array<string, mixed>  $cfg
     */
    private function emit(
        FiscalAdapterRequest $request,
        array $ids,
        array $cfg,
        SitfisProtocolState $state,
        CarbonImmutable $now,
    ): FiscalAdapterResult {
        $maxPolls = max(1, (int) $cfg['max_polls']);
        $pollInterval = max(5, (int) $cfg['poll_interval_seconds']);

        if ($state->pollCount >= $maxPolls) {
            return new FiscalAdapterResult(
                result: FiscalRunResult::Failed,
                situation: FiscalSituation::Error,
                coverage: FiscalCoverage::Full,
                normalized: [
                    'protocol' => $state->protocol,
                    'poll_count' => $state->pollCount,
                    'is_negative_certificate' => false,
                ],
                progress: $state->with(
                    phase: SitfisProtocolState::PHASE_FAILED,
                )->toProgress(),
                errorCode: 'SITFIS_POLL_EXHAUSTED',
                errorMessage: 'Emissão SITFIS esgotou tentativas de polling respeitoso.',
            );
        }

        $correlation = $state->correlationId ?? $request->run->correlation_id ?? (string) Str::uuid();
        $response = $this->call(
            $request,
            $ids,
            (string) $cfg['emit_operation'],
            [
                'idSistema' => 'SITFIS',
                'idServico' => (string) $cfg['emit_operation'],
                'versaoSistema' => '1.0',
                'dados' => json_encode([
                    'protocolo' => $state->protocol,
                    'contribuinte' => $ids['contributor_cnpj'],
                ], JSON_THROW_ON_ERROR),
            ],
            $correlation,
        );

        $pollCount = $state->pollCount + 1;
        $stateAfterPoll = $state->with(
            phase: SitfisProtocolState::PHASE_POLLING_EMIT,
            pollCount: $pollCount,
            lastPollAt: $now,
            correlationId: $correlation,
            simulated: $state->simulated || $response->simulated,
            requeueAfterSeconds: $pollInterval,
        );

        if (! $response->success) {
            if ($this->isGateBlock($response)) {
                return FiscalAdapterResult::blocked(
                    $response->errorMessage ?? 'Elegibilidade/orçamento SITFIS negado.',
                    $response->errorCode ?? 'SITFIS_GATE',
                );
            }

            // 202 / ainda processando mapeado pelo client
            if (in_array($response->httpStatus, [202, 204], true)
                || $response->errorCode === 'STILL_PROCESSING'
                || $this->bodySaysProcessing($response->body)) {
                return $this->processingResult(
                    state: $stateAfterPoll,
                    message: 'Relatório SITFIS ainda em processamento na fonte.',
                    requeueAfter: $pollInterval,
                );
            }

            return FiscalAdapterResult::failed(
                $response->errorMessage ?? 'Falha na emissão do relatório SITFIS.',
                $response->errorCode ?? 'SITFIS_EMIT_FAILED',
                FiscalCoverage::Full,
            );
        }

        if ($this->bodySaysProcessing($response->body)) {
            return $this->processingResult(
                state: $stateAfterPoll,
                message: 'Relatório SITFIS ainda em processamento na fonte.',
                requeueAfter: $pollInterval,
            );
        }

        $reportPayload = $this->extractReportPayload($response->body);
        if ($reportPayload === null) {
            // Sucesso HTTP sem corpo de relatório — trata como ainda processando se marcado
            return $this->processingResult(
                state: $stateAfterPoll,
                message: 'Resposta de emissão sem relatório; reagendando poll respeitoso.',
                requeueAfter: $pollInterval,
            );
        }

        $evidenceBytes = is_string($reportPayload)
            ? $reportPayload
            : json_encode($reportPayload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

        // Parser versionado — layout desconhecido ainda preserva artefato
        $parsed = $this->parser->parse(
            is_string($reportPayload)
                ? $reportPayload
                : $reportPayload,
        );

        $doneState = $stateAfterPoll->with(
            phase: SitfisProtocolState::PHASE_DONE,
            requeueAfterSeconds: 0,
        );

        $normalized = array_merge($parsed->normalized, [
            'protocol' => $state->protocol,
            'correlation_id' => $correlation,
            'poll_count' => $pollCount,
            'simulated' => $response->simulated,
            'is_negative_certificate' => false,
            'claims_negative_certificate' => false,
        ]);

        // Evidência produtiva: relatório completo (simulado ainda pode ser guardado em trial com flag)
        return new FiscalAdapterResult(
            result: FiscalRunResult::Success,
            situation: $parsed->situation,
            coverage: FiscalCoverage::Full,
            evidenceBytes: $evidenceBytes,
            evidenceContentType: is_string($reportPayload) && ! $this->isJson($reportPayload)
                ? 'application/octet-stream'
                : 'application/json',
            sourceVersion: $parsed->parserVersion,
            normalized: $normalized,
            findings: $parsed->findings,
            shouldRequeue: false,
            progressCursor: 'protocol:'.$state->protocol,
            progress: $doneState->toProgress(),
            itemsProcessed: count($parsed->findings),
            pagesProcessed: 1,
        );
    }

    /**
     * @param  array{environment: \App\Enums\SerproEnvironment, contract: \App\Models\SerproContract, contractor_cnpj: string, author_identity: string, contributor_cnpj: string}  $ids
     * @param  array<string, mixed>  $payload
     */
    private function call(
        FiscalAdapterRequest $request,
        array $ids,
        string $operation,
        array $payload,
        string $correlation,
    ): IntegraResponse {
        $cfg = $this->config();
        $system = (string) $cfg['system_code'];
        $service = (string) $cfg['service_code'];
        $env = $ids['environment'];

        $elig = $this->eligibility->evaluate(
            $request->office,
            $request->client,
            $system,
            $service,
            $operation,
            $env,
            null,
            'sitfis',
        );

        if (! $elig->eligible) {
            $code = $elig->primaryCode()->value;

            return new IntegraResponse(
                success: false,
                httpStatus: 422,
                body: [],
                errorCode: $code,
                errorMessage: 'Elegibilidade Integra negada: '.$code,
                correlationId: $correlation,
            );
        }

        $this->eligibility->touchRateLimit((int) $request->office->id);

        $idempotencyKey = $request->run->idempotency_key.':'.$operation.':'.($request->progressCursor ?? '0');
        $reserve = $this->ledger->reserve(new UsageReserveRequest(
            officeId: (int) $request->office->id,
            idempotencyKey: $idempotencyKey,
            systemCode: $system,
            serviceCode: $service,
            operationCode: $operation,
            quantity: 1,
            clientId: (int) $request->client->id,
            contributorRef: substr(hash('sha256', $ids['contributor_cnpj']), 0, 16),
            correlationId: $correlation,
        ));

        if (! $reserve->allowed) {
            return new IntegraResponse(
                success: false,
                httpStatus: 422,
                body: [],
                errorCode: 'BUDGET_EXCEEDED',
                errorMessage: 'Orçamento SERPRO bloqueou a operação.',
                correlationId: $correlation,
            );
        }

        $integraRequest = new IntegraRequest(
            officeId: (int) $request->office->id,
            clientId: (int) $request->client->id,
            environment: $env->value,
            solutionCode: $system,
            serviceCode: $service,
            operationCode: $operation,
            contractorCnpj: $ids['contractor_cnpj'],
            authorIdentity: $ids['author_identity'],
            contributorCnpj: $ids['contributor_cnpj'],
            payload: $payload,
            idempotencyKey: $idempotencyKey,
            correlationId: $correlation,
        );

        try {
            $response = $this->integra->execute($integraRequest);
        } catch (Throwable $e) {
            $this->ledger->finalize(
                $reserve->reservation,
                SerproUsageResult::TransportError,
                possiblyBillable: true,
            );

            return new IntegraResponse(
                success: false,
                httpStatus: 0,
                body: [],
                errorCode: 'SITFIS_TRANSPORT',
                errorMessage: $e->getMessage(),
                correlationId: $correlation,
            );
        }

        $this->ledger->finalize(
            $reserve->reservation,
            $this->ledger->mapIntegraResponse($response),
            latencyMs: $response->latencyMs,
            httpStatus: $response->httpStatus > 0 ? $response->httpStatus : null,
        );

        return $response;
    }

    private function isGateBlock(IntegraResponse $response): bool
    {
        $code = (string) ($response->errorCode ?? '');

        return in_array($code, [
            'BUDGET_EXCEEDED',
            'FEATURE_DISABLED',
            'KILL_SWITCH',
            'CIRCUIT_OPEN',
            'SUBSCRIPTION_BLOCKED',
            'CONTRACT_UNAVAILABLE',
            'CONTRACT_UNHEALTHY',
            'AUTHORIZATION_MISSING',
            'AUTHORIZATION_ACTION_REQUIRED',
            'AUTHORIZATION_EXPIRED',
            'TOKEN_MISSING',
            'TOKEN_EXPIRED',
            'CONTRIBUTOR_CROSS_TENANT',
            'PROXY_POWER_MISSING',
            'PROXY_POWER_INSUFFICIENT',
            'PROXY_POWER_EXPIRED',
            'COVERAGE_UNSUPPORTED',
            'ROLE_FORBIDDEN',
            'RATE_LIMITED',
            'SERVICE_NOT_CATALOGED',
            'MUTATING_DISABLED',
        ], true);
    }

    private function processingResult(
        SitfisProtocolState $state,
        string $message,
        int $requeueAfter,
        ?string $evidenceBytes = null,
    ): FiscalAdapterResult {
        $progress = $state->with(requeueAfterSeconds: $requeueAfter)->toProgress();

        return new FiscalAdapterResult(
            result: FiscalRunResult::Partial,
            situation: FiscalSituation::Processing,
            coverage: FiscalCoverage::Full,
            evidenceBytes: $evidenceBytes,
            sourceVersion: (string) config('fiscal_monitoring.sitfis.parser_version', SitfisReportParser::VERSION),
            normalized: [
                'phase' => $state->phase,
                'protocol' => $state->protocol,
                'message' => $message,
                'is_negative_certificate' => false,
            ],
            findings: [[
                'code' => 'SITFIS_PROCESSING',
                'severity' => FiscalFindingSeverity::Info->value,
                'title' => 'Situação fiscal em processamento',
                'detail' => $message,
                'situation' => FiscalSituation::Processing->value,
                'creates_pending' => false,
            ]],
            shouldRequeue: true,
            progressCursor: $state->protocol !== null ? 'protocol:'.$state->protocol : 'solicit',
            progress: $progress,
            requeueAfterSeconds: $requeueAfter,
        );
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function extractProtocol(array $body): ?string
    {
        foreach (['protocolo', 'protocol', 'numeroProtocolo', 'protocolNumber'] as $key) {
            if (! empty($body[$key]) && is_scalar($body[$key])) {
                return (string) $body[$key];
            }
        }
        foreach (['dados', 'resultado', 'data'] as $wrap) {
            if (! isset($body[$wrap])) {
                continue;
            }
            $inner = $body[$wrap];
            if (is_string($inner)) {
                $decoded = json_decode($inner, true);
                if (is_array($decoded)) {
                    $inner = $decoded;
                }
            }
            if (! is_array($inner)) {
                continue;
            }
            foreach (['protocolo', 'protocol', 'numeroProtocolo'] as $key) {
                if (! empty($inner[$key]) && is_scalar($inner[$key])) {
                    return (string) $inner[$key];
                }
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function bodySaysProcessing(array $body): bool
    {
        $status = strtoupper((string) ($body['status'] ?? $body['situacao'] ?? $body['estado'] ?? ''));
        if (in_array($status, ['PROCESSING', 'PROCESSANDO', 'PENDENTE', 'EM_PROCESSAMENTO', 'AGUARDANDO'], true)) {
            return true;
        }

        foreach (['dados', 'resultado', 'data'] as $wrap) {
            if (! isset($body[$wrap])) {
                continue;
            }
            $inner = $body[$wrap];
            if (is_string($inner)) {
                $decoded = json_decode($inner, true);
                $inner = is_array($decoded) ? $decoded : null;
            }
            if (! is_array($inner)) {
                continue;
            }
            $s = strtoupper((string) ($inner['status'] ?? $inner['situacao'] ?? ''));
            if (in_array($s, ['PROCESSING', 'PROCESSANDO', 'PENDENTE', 'EM_PROCESSAMENTO', 'AGUARDANDO'], true)) {
                return true;
            }
            if (! empty($inner['processando']) || ! empty($inner['processing'])) {
                return true;
            }
        }

        return ! empty($body['processando']) || ! empty($body['processing']);
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>|string|null
     */
    private function extractReportPayload(array $body): array|string|null
    {
        // Relatório embutido
        foreach (['relatorio', 'report', 'conteudo', 'pdfBase64', 'arquivo'] as $key) {
            if (! array_key_exists($key, $body)) {
                continue;
            }
            $v = $body[$key];
            if (is_string($v) && $v !== '') {
                // tenta JSON
                $decoded = json_decode($v, true);
                if (is_array($decoded)) {
                    return $decoded;
                }

                return $v;
            }
            if (is_array($v) && $v !== []) {
                return $v;
            }
        }

        foreach (['dados', 'resultado', 'data'] as $wrap) {
            if (! isset($body[$wrap])) {
                continue;
            }
            $inner = $body[$wrap];
            if (is_string($inner) && $inner !== '') {
                $decoded = json_decode($inner, true);
                if (is_array($decoded)) {
                    if ($this->bodySaysProcessing($decoded)) {
                        return null;
                    }

                    return $decoded;
                }

                return $inner;
            }
            if (is_array($inner) && $inner !== []) {
                if ($this->bodySaysProcessing($inner)) {
                    return null;
                }
                // Se for só status, não é relatório
                if (count($inner) === 1 && isset($inner['status'])) {
                    return null;
                }

                return $inner;
            }
        }

        // Corpo inteiro parece relatório (tem pendencias/itens/layout)
        if (isset($body['pendencias']) || isset($body['itens']) || isset($body['layoutVersion'])
            || isset($body['layout_version']) || isset($body['__unknown_layout'])) {
            return $body;
        }

        return null;
    }

    private function isJson(string $value): bool
    {
        json_decode($value);

        return json_last_error() === JSON_ERROR_NONE;
    }

    /**
     * @return array<string, mixed>
     */
    private function config(): array
    {
        return (array) config('fiscal_monitoring.sitfis', []);
    }
}

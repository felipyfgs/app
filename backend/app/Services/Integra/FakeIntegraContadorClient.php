<?php

namespace App\Services\Integra;

use App\Contracts\IntegraContadorClient;
use App\DTO\Serpro\IntegraRequest;
use App\DTO\Serpro\IntegraResponse;
use App\Enums\FiscalSourceProvenance;
use App\Services\Integra\Dctfweb\DctfwebCodes;
use App\Services\Serpro\Catalog\OperationKeyMap;

/**
 * Client trial/CI — resultados SIMULATED por padrão (não viram evidência produtiva).
 * Testes podem enfileirar respostas produtivas via queue().
 */
final class FakeIntegraContadorClient implements IntegraContadorClient
{
    /** @var array<string, list<IntegraResponse|callable(IntegraRequest): IntegraResponse>> */
    private array $queue = [];

    public int $calls = 0;

    /** @var list<IntegraRequest> */
    public array $history = [];

    public function reset(): void
    {
        $this->queue = [];
        $this->calls = 0;
        $this->history = [];
    }

    /**
     * @param  IntegraResponse|callable(IntegraRequest): IntegraResponse  $response
     */
    public function queue(
        string $solution,
        string $service,
        string $operation,
        IntegraResponse|callable $response,
    ): void {
        $key = $this->key($solution, $service, $operation);
        $this->queue[$key] ??= [];
        $this->queue[$key][] = $response;
    }

    public function execute(IntegraRequest $request): IntegraResponse
    {
        $this->calls++;
        $this->history[] = $request;

        $queueKey = $this->resolveQueueKey($request);
        if ($queueKey !== null && ! empty($this->queue[$queueKey])) {
            $next = array_shift($this->queue[$queueKey]);
            $response = is_callable($next) ? $next($request) : $next;

            return $this->withProvenance($response, $request);
        }

        return $this->withProvenance($this->defaultResponse($request), $request);
    }

    /**
     * Compatibiliza fila legada (SYSTEM|SERVICE|OP) com executor central (só operation_key).
     */
    private function resolveQueueKey(IntegraRequest $request): ?string
    {
        $direct = $this->key(
            (string) ($request->solutionCode ?? ''),
            (string) ($request->serviceCode ?? ''),
            (string) ($request->operationCode ?? ''),
        );
        if (! empty($this->queue[$direct])) {
            return $direct;
        }

        // Filas enfileiradas com solution/service/op vazios (testes de projeção).
        $wildcard = $this->key('', '', '');
        if (! empty($this->queue[$wildcard])) {
            return $wildcard;
        }

        $opKey = trim((string) ($request->operationKey ?? ''));
        if ($opKey === '') {
            return null;
        }

        foreach (array_keys($this->queue) as $queued) {
            if ($this->queue[$queued] === []) {
                continue;
            }
            $parts = explode('|', $queued);
            if (count($parts) !== 3) {
                continue;
            }
            $mapped = OperationKeyMap::resolve(null, $parts[0], $parts[1], $parts[2]);
            if ($mapped === $opKey) {
                return $queued;
            }
        }

        return null;
    }

    private function withProvenance(IntegraResponse $response, IntegraRequest $request): IntegraResponse
    {
        if ($response->sourceProvenance !== null) {
            return $response;
        }

        // Reconstroi com proveniência SIMULATED se o fake devolveu legado sem o campo
        return new IntegraResponse(
            success: $response->success,
            httpStatus: $response->httpStatus,
            body: $response->body,
            headers: $response->headers,
            errorCode: $response->errorCode,
            errorMessage: $response->errorMessage,
            simulated: $response->simulated,
            retryAfterSeconds: $response->retryAfterSeconds,
            correlationId: $response->correlationId ?? $request->correlationId,
            latencyMs: $response->latencyMs,
            etag: $response->etag,
            expiresHeader: $response->expiresHeader,
            businessStatus: $response->businessStatus,
            mensagens: $response->mensagens,
            dados: $response->dados,
            operationKey: $response->operationKey ?? $request->operationKey,
            requestTag: $response->requestTag ?? $request->resolvedRequestTag(),
            functionalRoute: $response->functionalRoute,
            sourceProvenance: $response->sourceProvenance
                ?? ($response->simulated
                    ? FiscalSourceProvenance::Simulated->value
                    : FiscalSourceProvenance::SerproReal->value),
        );
    }

    private function defaultResponse(IntegraRequest $request): IntegraResponse
    {
        $opKey = (string) ($request->operationKey ?? '');
        if (str_starts_with($opKey, 'sitfis.')) {
            return $this->sitfisResponse($request);
        }

        $op = strtoupper((string) ($request->operationCode ?? ''));
        $svc = strtoupper((string) ($request->serviceCode ?? ''));
        $solution = strtoupper((string) ($request->solutionCode ?? ''));
        $period = (string) ($request->businessData['competencia']
            ?? $request->businessData['period_key']
            ?? $request->payload['competencia']
            ?? $request->payload['periodo']
            ?? $request->payload['ano']
            ?? '2026-01');
        $force = strtoupper((string) ($request->businessData['force_status']
            ?? $request->payload['force_status']
            ?? $request->payload['scenario']
            ?? ''));

        // operation_key canônico → domínio legado para corpos versionados
        if (str_starts_with($opKey, 'pgdasd.') || str_starts_with($opKey, 'defis.')
            || str_starts_with($opKey, 'regimeapuracao.') || str_starts_with($opKey, 'pgmei.')
            || str_starts_with($opKey, 'ccmei.') || str_starts_with($opKey, 'dasnsimei.')) {
            [$solution, $svc, $op] = $this->domainTripleFromOperationKey($opKey);
        }

        // Integra-SN / Integra-MEI — corpos versionados (dto_version=1) para adapters SimplesMei
        if ($solution === 'INTEGRA_SN' || $solution === 'INTEGRA_MEI'
            || in_array($svc, ['PGDASD', 'DEFIS', 'REGIME_APURACAO', 'PGMEI', 'CCMEI', 'DASN_SIMEI'], true)) {
            if ($solution === '' || $solution === 'PGDASD' || $solution === 'DEFIS') {
                $solution = str_starts_with($opKey, 'pgmei.') || str_starts_with($opKey, 'ccmei.') || str_starts_with($opKey, 'dasnsimei.')
                    ? 'INTEGRA_MEI'
                    : 'INTEGRA_SN';
            }

            return new IntegraResponse(
                success: true,
                httpStatus: 200,
                body: $this->simplesMeiBody($solution, $svc !== '' ? $svc : 'PGDASD', $op !== '' ? $op : 'CONSULTAR_DECLARACAO', $period, $force),
                headers: ['x-simulated' => '1'],
                simulated: true,
                correlationId: $request->correlationId,
                latencyMs: 1,
                operationKey: $opKey !== '' ? $opKey : null,
            );
        }

        // DCTF/MIT por operation_key canônico
        if (str_starts_with($opKey, 'dctfweb.') || str_starts_with($opKey, 'mit.')) {
            if (str_contains($opKey, 'trans') || str_contains($opKey, 'encapuracao') || str_contains($opKey, 'gerarguia')) {
                $op = str_contains($opKey, 'encapuracao')
                    ? DctfwebCodes::OP_MIT_ENCERRAR
                    : (str_contains($opKey, 'gerarguia') ? DctfwebCodes::OP_EMITIR_DARF : DctfwebCodes::OP_TRANSMITIR);
            }
            if (str_starts_with($opKey, 'mit.')) {
                $svc = DctfwebCodes::SERVICE_MIT;
                $solution = DctfwebCodes::SYSTEM_MIT;
            } else {
                $svc = DctfwebCodes::SERVICE_DCTFWEB;
                $solution = DctfwebCodes::SYSTEM_DCTFWEB;
            }
        }

        // Mutantes: simulado sem efeito real
        if (in_array($op, [DctfwebCodes::OP_TRANSMITIR, DctfwebCodes::OP_MIT_ENCERRAR], true)
            || str_contains($opKey, 'transdeclaracao')
            || str_contains($opKey, 'encapuracao')) {
            return new IntegraResponse(
                success: true,
                httpStatus: 200,
                body: [
                    'simulated' => true,
                    'message' => 'Mutação simulada (trial) — sem efeito fiscal real.',
                    'competencia' => $period,
                ],
                headers: ['x-simulated' => '1'],
                simulated: true,
                correlationId: $request->correlationId,
                latencyMs: 1,
            );
        }

        if ($svc === DctfwebCodes::SERVICE_MIT || str_contains(strtoupper($request->solutionCode), 'MIT')) {
            return new IntegraResponse(
                success: true,
                httpStatus: 200,
                body: [
                    'simulated' => true,
                    'competencia' => $period,
                    'status' => 'DESCONHECIDO',
                    'encerrado' => false,
                    'solution' => $request->solutionCode,
                    'service' => $request->serviceCode,
                    'operation' => $request->operationCode,
                ],
                headers: ['x-simulated' => '1'],
                simulated: true,
                correlationId: $request->correlationId,
                latencyMs: 1,
            );
        }

        if ($svc === DctfwebCodes::SERVICE_DCTFWEB || str_contains(strtoupper($request->solutionCode), 'DCTF')) {
            return new IntegraResponse(
                success: true,
                httpStatus: 200,
                body: [
                    'simulated' => true,
                    'competencia' => $period,
                    'status' => 'DESCONHECIDO',
                    'transmitida' => false,
                    'solution' => $request->solutionCode,
                    'service' => $request->serviceCode,
                    'operation' => $request->operationCode,
                ],
                headers: ['x-simulated' => '1'],
                simulated: true,
                correlationId: $request->correlationId,
                latencyMs: 1,
            );
        }

        return new IntegraResponse(
            success: true,
            httpStatus: 200,
            body: [
                'simulated' => true,
                'solution' => $request->solutionCode,
                'service' => $request->serviceCode,
                'operation' => $request->operationCode,
                'message' => 'Resposta simulada do Integra Contador (trial).',
            ],
            headers: ['x-simulated' => '1'],
            simulated: true,
            correlationId: $request->correlationId,
            latencyMs: 1,
        );
    }

    private function sitfisResponse(IntegraRequest $request): IntegraResponse
    {
        $scenario = strtolower((string) ($request->businessData['__scenario'] ?? 'success'));
        $base = ['simulated' => true, 'operation_key' => $request->operationKey];

        if ($scenario === 'rate_limit') {
            return new IntegraResponse(false, 429, $base, errorCode: 'RATE_LIMITED', errorMessage: 'Limite simulado.', simulated: true, retryAfterSeconds: 60, correlationId: $request->correlationId, operationKey: $request->operationKey);
        }
        if ($scenario === 'unavailable') {
            return new IntegraResponse(false, 503, $base, errorCode: 'UPSTREAM_ERROR', errorMessage: 'Indisponibilidade simulada.', simulated: true, correlationId: $request->correlationId, operationKey: $request->operationKey);
        }
        if ($scenario === 'processing' || $scenario === 'cache') {
            return new IntegraResponse(false, $scenario === 'cache' ? 304 : 204, $base + ['tempoEspera' => 30], errorCode: 'STILL_PROCESSING', errorMessage: 'Processamento simulado.', simulated: true, retryAfterSeconds: 30, correlationId: $request->correlationId, etag: 'tempoEspera=30', operationKey: $request->operationKey);
        }
        if ($request->operationKey === 'sitfis.solicitar_protocolo') {
            return new IntegraResponse(true, 200, $base + [
                'protocolo' => 'SIM-'.substr(hash('sha256', $request->resolvedRequestTag()), 0, 20),
                'tempoEspera' => 30,
            ], simulated: true, retryAfterSeconds: 30, correlationId: $request->correlationId, operationKey: $request->operationKey);
        }

        return new IntegraResponse(true, 200, $base + [
            'status' => 'CONCLUIDO',
            'dados' => ['versao' => '1.0', 'situacao' => 'REGULAR', 'itens' => []],
        ], simulated: true, correlationId: $request->correlationId, operationKey: $request->operationKey);
    }

    /**
     * @return array<string, mixed>
     */
    private function simplesMeiBody(
        string $solution,
        string $service,
        string $operation,
        string $period,
        string $force,
    ): array {
        if ($service === 'PGDASD' && $operation === 'GERAR_DAS') {
            return [
                'dto_version' => '1',
                'data' => [
                    'competence' => $period !== '' ? $period : now()->format('Y-m'),
                    'document_number' => 'DAS-SN-SIM-001',
                    'due_date' => now()->addDays(20)->toDateString(),
                    'amount' => 150.75,
                    'emission_status' => 'ISSUED',
                ],
            ];
        }

        if ($service === 'PGDASD' && $operation === 'TRANSMITIR') {
            return [
                'dto_version' => '1',
                'status' => 'TRANSMITIDA',
                'data' => ['status' => 'TRANSMITIDA'],
            ];
        }

        if ($service === 'PGDASD') {
            $status = match ($force) {
                'PENDING', 'PENDENTE', 'OMISSA' => 'PENDENTE',
                'INCONCLUSIVO', 'UNKNOWN' => 'INCONCLUSIVO',
                'NO_RECEIPT' => 'ENTREGUE',
                default => 'ENTREGUE',
            };
            $receipt = ($status === 'ENTREGUE' && $force !== 'NO_RECEIPT')
                ? 'REC-PGDASD-'.str_replace('-', '', $period !== '' ? $period : '000000')
                : null;

            return [
                'dto_version' => '1',
                'data' => [
                    'competence' => $period !== '' ? $period : now()->format('Y-m'),
                    'status' => $status,
                    'receipt_number' => $receipt,
                    'declaration_id' => 'DECL-PGDASD-1',
                    'transmitted_at' => $receipt ? now()->toIso8601String() : null,
                ],
            ];
        }

        if ($service === 'DEFIS') {
            $year = strlen($period) >= 4 ? substr($period, 0, 4) : (string) now()->year;
            $status = match ($force) {
                'PENDING', 'PENDENTE' => 'PENDENTE',
                'INCONCLUSIVO' => 'INCONCLUSIVO',
                default => 'ENTREGUE',
            };

            return [
                'dto_version' => '1',
                'data' => [
                    'year' => $year,
                    'status' => $status,
                    'receipt_number' => $status === 'ENTREGUE' ? 'REC-DEFIS-'.$year : null,
                ],
            ];
        }

        if ($service === 'REGIME_APURACAO') {
            if ($force === 'MEI_ONLY') {
                return [
                    'dto_version' => '1',
                    'data' => [
                        'current_regime' => 'MEI',
                        'periods' => [[
                            'regime' => 'MEI',
                            'effective_from' => '2024-01-01',
                            'effective_to' => null,
                        ]],
                    ],
                ];
            }
            if ($force === 'REGIME_CHANGE') {
                return [
                    'dto_version' => '1',
                    'data' => [
                        'current_regime' => 'SIMPLES_NACIONAL',
                        'periods' => [
                            ['regime' => 'MEI', 'effective_from' => '2023-01-01', 'effective_to' => '2023-12-31'],
                            ['regime' => 'SIMPLES_NACIONAL', 'effective_from' => '2024-01-01', 'effective_to' => null],
                        ],
                    ],
                ];
            }

            return [
                'dto_version' => '1',
                'data' => [
                    'current_regime' => 'SIMPLES_NACIONAL',
                    'periods' => [[
                        'regime' => 'SIMPLES_NACIONAL',
                        'effective_from' => '2020-01-01',
                        'effective_to' => null,
                    ]],
                ],
            ];
        }

        if ($service === 'PGMEI' && $operation === 'GERAR_DAS') {
            return [
                'dto_version' => '1',
                'data' => [
                    'competence' => $period !== '' ? $period : now()->format('Y-m'),
                    'document_number' => 'DAS-MEI-SIM-001',
                    'due_date' => now()->addDays(20)->toDateString(),
                    'amount' => 71.60,
                    'emission_status' => 'ISSUED',
                ],
            ];
        }

        if ($service === 'PGMEI') {
            $status = match ($force) {
                'PENDING', 'PENDENTE' => 'PENDENTE',
                'INCONCLUSIVO' => 'INCONCLUSIVO',
                default => 'EMITIDO',
            };

            return [
                'dto_version' => '1',
                'data' => [
                    'competence' => $period !== '' ? $period : now()->format('Y-m'),
                    'status' => $status,
                    'das_number' => $status === 'EMITIDO' ? 'DAS-MEI-1' : null,
                    'due_date' => now()->addDays(10)->toDateString(),
                    'amount' => 71.60,
                ],
            ];
        }

        if ($service === 'CCMEI') {
            $status = match ($force) {
                'INATIVO' => 'INATIVO',
                'INCONCLUSIVO' => 'INCONCLUSIVO',
                default => 'ATIVO',
            };

            return [
                'dto_version' => '1',
                'data' => [
                    'status' => $status,
                    'certificate_number' => $status === 'ATIVO' ? 'CCMEI-SIM-1' : null,
                    'issued_at' => now()->toIso8601String(),
                ],
            ];
        }

        if ($service === 'DASN_SIMEI') {
            $year = strlen($period) >= 4 ? substr($period, 0, 4) : (string) now()->year;
            $status = match ($force) {
                'PENDING', 'PENDENTE' => 'PENDENTE',
                'INCONCLUSIVO' => 'INCONCLUSIVO',
                default => 'ENTREGUE',
            };

            return [
                'dto_version' => '1',
                'data' => [
                    'year' => $year,
                    'status' => $status,
                    'receipt_number' => $status === 'ENTREGUE' ? 'REC-DASN-'.$year : null,
                ],
            ];
        }

        return [
            'dto_version' => '1',
            'simulated' => true,
            'solution' => $solution,
            'service' => $service,
            'operation' => $operation,
            'message' => 'Resposta simulada SN/MEI genérica.',
        ];
    }

    private function key(string $solution, string $service, string $operation): string
    {
        return strtoupper($solution).'|'.strtoupper($service).'|'.strtoupper($operation);
    }

    /**
     * @return array{0: string, 1: string, 2: string} solution, service, operation
     */
    private function domainTripleFromOperationKey(string $operationKey): array
    {
        return match (true) {
            str_starts_with($operationKey, 'pgdasd.gerardas') => ['INTEGRA_SN', 'PGDASD', 'GERAR_DAS'],
            str_starts_with($operationKey, 'pgdasd.trans') => ['INTEGRA_SN', 'PGDASD', 'TRANSMITIR'],
            str_starts_with($operationKey, 'pgdasd.') => ['INTEGRA_SN', 'PGDASD', 'CONSULTAR_DECLARACAO'],
            str_starts_with($operationKey, 'defis.trans') => ['INTEGRA_SN', 'DEFIS', 'TRANSMITIR'],
            str_starts_with($operationKey, 'defis.') => ['INTEGRA_SN', 'DEFIS', 'CONSULTAR'],
            str_starts_with($operationKey, 'regimeapuracao.') => ['INTEGRA_SN', 'REGIME_APURACAO', 'CONSULTAR'],
            str_starts_with($operationKey, 'pgmei.gerardas') => ['INTEGRA_MEI', 'PGMEI', 'GERAR_DAS'],
            str_starts_with($operationKey, 'pgmei.') => ['INTEGRA_MEI', 'PGMEI', 'CONSULTAR'],
            str_starts_with($operationKey, 'ccmei.') => ['INTEGRA_MEI', 'CCMEI', 'CONSULTAR'],
            str_starts_with($operationKey, 'dasnsimei.') => ['INTEGRA_MEI', 'DASN_SIMEI', 'CONSULTAR'],
            default => ['', '', ''],
        };
    }

    /** Helper de teste: recibo transmitido produtivo. */
    public static function productiveRecibo(
        string $periodKey = '2026-01',
        string $receipt = 'REC-001',
        bool $retificadora = false,
        ?string $xmlHint = null,
    ): IntegraResponse {
        $body = [
            'competencia' => $periodKey,
            'status' => 'TRANSMITIDA',
            'transmitida' => true,
            'recibo' => $receipt,
            'tipo' => $retificadora ? 'RETIFICADORA' : 'ORIGINAL',
            'dataHoraTransmissao' => '2026-02-10T15:30:00-03:00',
            'versao' => $retificadora ? '2' : '1',
        ];
        if ($xmlHint !== null) {
            $body['xml'] = $xmlHint;
        }

        return new IntegraResponse(
            success: true,
            httpStatus: 200,
            body: $body,
            simulated: false,
            correlationId: null,
            latencyMs: 5,
            sourceProvenance: FiscalSourceProvenance::SerproReal->value,
        );
    }

    public static function productiveMitEncerrado(string $periodKey = '2026-01'): IntegraResponse
    {
        return new IntegraResponse(
            success: true,
            httpStatus: 200,
            body: [
                'competencia' => $periodKey,
                'status' => 'ENCERRADO',
                'encerrado' => true,
                'situacao' => 'ENCERRADO',
                'dataEncerramento' => '2026-02-05T10:00:00-03:00',
            ],
            simulated: false,
            latencyMs: 5,
            sourceProvenance: FiscalSourceProvenance::SerproReal->value,
        );
    }

    public static function uncertainTimeout(): IntegraResponse
    {
        return new IntegraResponse(
            success: false,
            httpStatus: 504,
            body: [],
            errorCode: 'UNCERTAIN_TIMEOUT',
            errorMessage: 'Timeout após envio — resultado incerto.',
            simulated: false,
            latencyMs: 30000,
            sourceProvenance: FiscalSourceProvenance::SerproReal->value,
        );
    }
}

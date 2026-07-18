<?php

namespace Tests\Support;

use App\Contracts\IntegraContadorClient;
use App\DTO\Serpro\IntegraRequest;
use App\DTO\Serpro\IntegraResponse;

/**
 * Client Integra programável para testes de adapters (SITFIS etc.).
 */
final class ProgrammableIntegraContadorClient implements IntegraContadorClient
{
    /** @var list<IntegraRequest> */
    public array $requests = [];

    /** @var list<callable(IntegraRequest): IntegraResponse> */
    private array $handlers = [];

    /** @var callable(IntegraRequest): IntegraResponse|null */
    private $defaultHandler = null;

    /**
     * @param  callable(IntegraRequest): IntegraResponse  $handler
     */
    public function push(callable $handler): self
    {
        $this->handlers[] = $handler;

        return $this;
    }

    /**
     * @param  callable(IntegraRequest): IntegraResponse  $handler
     */
    public function setDefault(callable $handler): self
    {
        $this->defaultHandler = $handler;

        return $this;
    }

    public function queueSolicit(string $protocol = 'PROT-SITFIS-1', bool $simulated = false): self
    {
        return $this->push(function (IntegraRequest $req) use ($protocol, $simulated) {
            // Shape oficial: protocoloRelatorio em dados (+ tempoEspera em ms).
            return new IntegraResponse(
                success: true,
                httpStatus: 200,
                body: [
                    'status' => 200,
                    'dados' => [
                        'protocoloRelatorio' => $protocol,
                        'tempoEspera' => 4000,
                    ],
                ],
                simulated: $simulated,
                correlationId: $req->correlationId,
                latencyMs: 1,
                dados: [
                    'protocoloRelatorio' => $protocol,
                    'tempoEspera' => 4000,
                ],
                operationKey: $req->operationKey,
            );
        });
    }

    public function queueSolicitNotModifiedThenOk(string $protocol = 'PROT-SITFIS-304'): self
    {
        $this->push(function (IntegraRequest $req) {
            return new IntegraResponse(
                success: true,
                httpStatus: 304,
                body: [],
                errorCode: 'NOT_MODIFIED',
                errorMessage: 'Conteúdo não modificado (cache/ETag).',
                correlationId: $req->correlationId,
                latencyMs: 1,
                businessStatus: 'NOT_MODIFIED',
                operationKey: $req->operationKey,
            );
        });

        return $this->queueSolicit($protocol);
    }

    public function queueProcessing(): self
    {
        return $this->push(function (IntegraRequest $req) {
            $protocol = $this->lastProtocol($req);

            return new IntegraResponse(
                success: false,
                httpStatus: 202,
                body: [
                    'status' => 202,
                    'dados' => [
                        'tempoEspera' => 60000,
                        'protocoloRelatorio' => $protocol,
                    ],
                ],
                errorCode: 'STILL_PROCESSING',
                errorMessage: 'Relatório ainda em processamento.',
                simulated: false,
                retryAfterSeconds: 60,
                correlationId: $req->correlationId,
                latencyMs: 1,
                businessStatus: 'PROCESSANDO',
                dados: [
                    'tempoEspera' => 60000,
                    'protocoloRelatorio' => $protocol,
                ],
                operationKey: $req->operationKey,
            );
        });
    }

    /**
     * @param  array<string, mixed>  $report
     */
    public function queueReport(array $report, bool $simulated = false): self
    {
        return $this->push(function (IntegraRequest $req) use ($report, $simulated) {
            $protocol = $this->lastProtocol($req);

            return new IntegraResponse(
                success: true,
                httpStatus: 200,
                body: [
                    'status' => 200,
                    'protocoloRelatorio' => $protocol,
                    'relatorio' => $report,
                    'dados' => $report,
                ],
                simulated: $simulated,
                correlationId: $req->correlationId,
                latencyMs: 1,
                dados: $report,
                operationKey: $req->operationKey,
            );
        });
    }

    /**
     * Shape oficial RELATORIOSITFIS92: dados = [{"pdf":"<base64>"}].
     */
    public function queueReportPdf(string $base64Pdf = 'JVBERi0xLjQK', bool $simulated = false): self
    {
        return $this->push(function (IntegraRequest $req) use ($base64Pdf, $simulated) {
            $dados = [['pdf' => $base64Pdf]];

            return new IntegraResponse(
                success: true,
                httpStatus: 200,
                body: [
                    'status' => 200,
                    'dados' => json_encode($dados, JSON_THROW_ON_ERROR),
                ],
                simulated: $simulated,
                correlationId: $req->correlationId,
                latencyMs: 1,
                dados: $dados,
                operationKey: $req->operationKey,
            );
        });
    }

    public function execute(IntegraRequest $request): IntegraResponse
    {
        $this->requests[] = $request;

        if ($this->handlers !== []) {
            $handler = array_shift($this->handlers);

            return $handler($request);
        }

        if ($this->defaultHandler !== null) {
            return ($this->defaultHandler)($request);
        }

        return new IntegraResponse(
            success: false,
            httpStatus: 500,
            body: [],
            errorCode: 'NO_HANDLER',
            errorMessage: 'Nenhum handler programado no ProgrammableIntegraContadorClient.',
            correlationId: $request->correlationId,
        );
    }

    public function callCount(): int
    {
        return count($this->requests);
    }

    /**
     * @return list<string>
     */
    public function operations(): array
    {
        return array_map(
            fn (IntegraRequest $r) => (string) ($r->operationKey ?: ($r->operationCode ?? '')),
            $this->requests,
        );
    }

    private function lastProtocol(IntegraRequest $req): string
    {
        foreach (['protocoloRelatorio', 'protocolo'] as $key) {
            if (! empty($req->businessData[$key]) && is_scalar($req->businessData[$key])) {
                return (string) $req->businessData[$key];
            }
        }
        $dados = $req->payload['dados'] ?? null;
        if (is_string($dados)) {
            $decoded = json_decode($dados, true);
            if (is_array($decoded)) {
                foreach (['protocoloRelatorio', 'protocolo'] as $key) {
                    if (! empty($decoded[$key]) && is_scalar($decoded[$key])) {
                        return (string) $decoded[$key];
                    }
                }
            }
        }

        return 'PROT-UNKNOWN';
    }
}

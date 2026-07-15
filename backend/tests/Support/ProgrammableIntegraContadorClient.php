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
            return new IntegraResponse(
                success: true,
                httpStatus: 200,
                body: [
                    'protocolo' => $protocol,
                    'status' => 'ACEITO',
                ],
                simulated: $simulated,
                correlationId: $req->correlationId,
                latencyMs: 1,
            );
        });
    }

    public function queueProcessing(): self
    {
        return $this->push(function (IntegraRequest $req) {
            return new IntegraResponse(
                success: true,
                httpStatus: 200,
                body: [
                    'status' => 'PROCESSANDO',
                    'protocolo' => $this->lastProtocol($req),
                ],
                simulated: false,
                correlationId: $req->correlationId,
                latencyMs: 1,
            );
        });
    }

    /**
     * @param  array<string, mixed>  $report
     */
    public function queueReport(array $report, bool $simulated = false): self
    {
        return $this->push(function (IntegraRequest $req) use ($report, $simulated) {
            return new IntegraResponse(
                success: true,
                httpStatus: 200,
                body: [
                    'status' => 'PRONTO',
                    'protocolo' => $this->lastProtocol($req),
                    'relatorio' => $report,
                ],
                simulated: $simulated,
                correlationId: $req->correlationId,
                latencyMs: 1,
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
        return array_map(fn (IntegraRequest $r) => $r->operationCode, $this->requests);
    }

    private function lastProtocol(IntegraRequest $req): string
    {
        $dados = $req->payload['dados'] ?? null;
        if (is_string($dados)) {
            $decoded = json_decode($dados, true);
            if (is_array($decoded) && isset($decoded['protocolo'])) {
                return (string) $decoded['protocolo'];
            }
        }

        return 'PROT-UNKNOWN';
    }
}

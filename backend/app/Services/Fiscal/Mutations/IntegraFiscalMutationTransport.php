<?php

namespace App\Services\Fiscal\Mutations;

use App\Contracts\FiscalMutationTransport;
use App\Contracts\IntegraContadorClient;
use App\DTO\Serpro\IntegraRequest;
use App\DTO\Serpro\IntegraResponse;

/**
 * Transporte mutante via Integra Contador (chamada real/fake conforme binding).
 * Reconciliação usa a mesma fachada com operation_code de consulta.
 */
final class IntegraFiscalMutationTransport implements FiscalMutationTransport
{
    public function __construct(
        private readonly IntegraContadorClient $client,
    ) {}

    public function execute(IntegraRequest $request): IntegraResponse
    {
        return $this->client->execute($request);
    }

    public function reconcile(IntegraRequest $request): IntegraResponse
    {
        // Consulta de reconciliação — nunca reenvia a mutação original.
        $reconcileOp = $this->reconcileOperationCode($request->operationCode);
        $query = new IntegraRequest(
            officeId: $request->officeId,
            clientId: $request->clientId,
            environment: $request->environment,
            solutionCode: $request->solutionCode,
            serviceCode: $request->serviceCode,
            operationCode: $reconcileOp,
            contractorCnpj: $request->contractorCnpj,
            authorIdentity: $request->authorIdentity,
            contributorCnpj: $request->contributorCnpj,
            payload: array_merge($request->payload, [
                'reconcile' => true,
                'original_operation' => $request->operationCode,
            ]),
            headers: $request->headers,
            idempotencyKey: ($request->idempotencyKey ?? '').':reconcile',
            correlationId: $request->correlationId,
        );

        return $this->client->execute($query);
    }

    private function reconcileOperationCode(string $operationCode): string
    {
        $map = [
            'TRANSMITIR' => 'CONSULTAR_RECIBO',
            'EMITIR_GUIA' => 'CONSULTAR',
            'ENCERRAR' => 'CONSULTAR_SITUACAO',
            'ADERIR' => 'CONSULTAR_PEDIDO',
        ];

        $upper = strtoupper($operationCode);

        return $map[$upper] ?? 'CONSULTAR';
    }
}

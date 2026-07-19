<?php

namespace App\Services\Fiscal\Mutations;

use App\Contracts\FiscalMutationTransport;
use App\DTO\Serpro\IntegraRequest;
use App\DTO\Serpro\IntegraResponse;
use App\DTO\Serpro\MutationAuthorization;
use App\Services\Serpro\Catalog\OperationKeyMap;
use App\Services\Serpro\SerproOperationService;

/**
 * Transporte mutante via executor central.
 * Nesta change, mutações permanecem bloqueadas por MutationAuthorization tipada.
 */
final class IntegraFiscalMutationTransport implements FiscalMutationTransport
{
    public function __construct(
        private readonly SerproOperationService $operations,
    ) {}

    public function execute(IntegraRequest $request): IntegraResponse
    {
        return $this->operations->executeRequest(
            $request,
            mutationAuth: MutationAuthorization::none(),
        );
    }

    public function reconcile(IntegraRequest $request): IntegraResponse
    {
        // Consulta de reconciliação — nunca reenvia a mutação original.
        $reconcileOp = $this->reconcileOperationCode((string) ($request->operationCode ?? ''));
        $reconcileKey = OperationKeyMap::resolve(
            null,
            $request->solutionCode,
            $request->serviceCode,
            $reconcileOp,
        ) ?? $request->operationKey;

        $query = new IntegraRequest(
            officeId: $request->officeId,
            clientId: $request->clientId,
            environment: $request->environment,
            contractorCnpj: $request->contractorCnpj,
            authorIdentity: $request->authorIdentity,
            contributorCnpj: $request->contributorCnpj,
            operationKey: $reconcileKey,
            payload: array_merge($request->payload, [
                'reconcile' => true,
                'original_operation' => $request->operationCode,
            ]),
            headers: $request->headers,
            idempotencyKey: ($request->idempotencyKey ?? '').':reconcile',
            correlationId: $request->correlationId,
            isMutating: false,
            solutionCode: $request->solutionCode,
            serviceCode: $request->serviceCode,
            operationCode: $reconcileOp,
        );

        return $this->operations->executeRequest($query, mutationAuth: MutationAuthorization::none());
    }

    private function reconcileOperationCode(string $operationCode): string
    {
        $map = [
            'TRANSMITIR' => 'CONSULTAR_RECIBO',
            'TRANSMITIR_DECLARACAO' => 'CONSULTAR_RECIBO',
            'EMITIR_GUIA' => 'CONSULTAR',
            'ENCERRAR' => 'CONSULTAR_SITUACAO',
            'ADERIR' => 'CONSULTAR_PEDIDO',
        ];

        $upper = strtoupper($operationCode);

        return $map[$upper] ?? 'CONSULTAR_RECIBO';
    }
}

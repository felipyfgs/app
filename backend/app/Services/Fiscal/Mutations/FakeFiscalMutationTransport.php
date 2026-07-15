<?php

namespace App\Services\Fiscal\Mutations;

use App\Contracts\FiscalMutationTransport;
use App\DTO\Serpro\IntegraRequest;
use App\DTO\Serpro\IntegraResponse;
use RuntimeException;

/**
 * Transporte controlável para testes (timeout, rejeição, sucesso, reconciliação).
 */
final class FakeFiscalMutationTransport implements FiscalMutationTransport
{
    public string $mode = 'success'; // success|reject|timeout|uncertain

    public string $reconcileMode = 'confirmed'; // confirmed|rejected|still_unknown

    public int $executeCalls = 0;

    public int $reconcileCalls = 0;

    public function execute(IntegraRequest $request): IntegraResponse
    {
        $this->executeCalls++;

        return match ($this->mode) {
            'timeout' => throw new RuntimeException('TRANSPORT_TIMEOUT'),
            'uncertain' => new IntegraResponse(
                success: false,
                httpStatus: 504,
                body: ['status' => 'UNKNOWN'],
                errorCode: 'GATEWAY_TIMEOUT',
                errorMessage: 'Timeout após envio — resultado incerto.',
                simulated: true,
                correlationId: $request->correlationId,
                latencyMs: 60_000,
            ),
            'reject' => new IntegraResponse(
                success: false,
                httpStatus: 422,
                body: ['status' => 'REJECTED', 'reason' => 'business_rule'],
                errorCode: 'BUSINESS_REJECT',
                errorMessage: 'Operação rejeitada pela fonte.',
                simulated: true,
                correlationId: $request->correlationId,
                latencyMs: 5,
            ),
            default => new IntegraResponse(
                success: true,
                httpStatus: 200,
                body: [
                    'status' => 'CONFIRMED',
                    'protocol' => 'SIM-'.substr(hash('sha256', $request->idempotencyKey ?? 'x'), 0, 12),
                    'simulated' => true,
                ],
                simulated: true,
                correlationId: $request->correlationId,
                latencyMs: 3,
            ),
        };
    }

    public function reconcile(IntegraRequest $request): IntegraResponse
    {
        $this->reconcileCalls++;

        return match ($this->reconcileMode) {
            'rejected' => new IntegraResponse(
                success: true,
                httpStatus: 200,
                body: ['status' => 'REJECTED', 'found' => true],
                simulated: true,
                correlationId: $request->correlationId,
                latencyMs: 2,
            ),
            'still_unknown' => new IntegraResponse(
                success: false,
                httpStatus: 404,
                body: ['status' => 'NOT_FOUND'],
                errorCode: 'NOT_FOUND',
                errorMessage: 'Ainda sem resultado na fonte.',
                simulated: true,
                correlationId: $request->correlationId,
                latencyMs: 2,
            ),
            default => new IntegraResponse(
                success: true,
                httpStatus: 200,
                body: [
                    'status' => 'CONFIRMED',
                    'found' => true,
                    'protocol' => 'REC-'.substr(hash('sha256', $request->idempotencyKey ?? 'r'), 0, 12),
                ],
                simulated: true,
                correlationId: $request->correlationId,
                latencyMs: 2,
            ),
        };
    }

    public function reset(): void
    {
        $this->mode = 'success';
        $this->reconcileMode = 'confirmed';
        $this->executeCalls = 0;
        $this->reconcileCalls = 0;
    }
}

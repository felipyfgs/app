<?php

namespace App\Services\Serpro\Usage;

/**
 * Pedido de reserva de orçamento antes da chamada HTTP SERPRO.
 * Sem payload fiscal.
 */
final class UsageReserveRequest
{
    public function __construct(
        public readonly int $officeId,
        public readonly string $idempotencyKey,
        public readonly string $systemCode,
        public readonly string $serviceCode,
        public readonly string $operationCode,
        public readonly int $quantity = 1,
        public readonly ?int $clientId = null,
        public readonly ?string $contributorRef = null,
        public readonly ?string $correlationId = null,
        public readonly ?bool $forceEssential = null,
    ) {
        if ($this->officeId <= 0) {
            throw new \InvalidArgumentException('office_id obrigatório para reserva de uso SERPRO.');
        }
        if ($this->idempotencyKey === '') {
            throw new \InvalidArgumentException('idempotency_key obrigatório.');
        }
        if ($this->quantity < 1) {
            throw new \InvalidArgumentException('quantity deve ser >= 1.');
        }
    }
}

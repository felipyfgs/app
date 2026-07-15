<?php

namespace App\DTO\Serpro;

/**
 * Pedido de domínio para Integra Contador.
 * Identidades vêm de registros persistidos — nunca do frontend como autoridade.
 *
 * @phpstan-type Payload array<string, mixed>
 */
final class IntegraRequest
{
    /**
     * @param  array<string, mixed>  $payload  Dados de negócio (não credenciais)
     * @param  array<string, string>  $headers  Headers extras não secretos
     */
    public function __construct(
        public readonly int $officeId,
        public readonly int $clientId,
        public readonly string $environment,
        public readonly string $solutionCode,
        public readonly string $serviceCode,
        public readonly string $operationCode,
        public readonly string $contractorCnpj,
        public readonly string $authorIdentity,
        public readonly string $contributorCnpj,
        public readonly array $payload = [],
        public readonly array $headers = [],
        public readonly ?string $idempotencyKey = null,
        public readonly ?string $correlationId = null,
    ) {}
}

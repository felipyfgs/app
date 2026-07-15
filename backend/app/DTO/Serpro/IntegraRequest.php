<?php

namespace App\DTO\Serpro;

/**
 * Pedido de domínio para Integra Contador.
 * Preferir operation_key; coordenadas oficiais vêm do catálogo (não do frontend).
 *
 * @phpstan-type Payload array<string, mixed>
 */
final class IntegraRequest
{
    /**
     * @param  array<string, mixed>  $businessData  Dados de negócio (protocolo, filtros…) — não credenciais
     * @param  array<string, string>  $headers  Headers extras não secretos (não sobrescreve oficiais)
     * @param  array<string, mixed>  $payload  Legado: envelope parcial; preferir businessData + operation_key
     */
    public function __construct(
        public readonly int $officeId,
        public readonly int $clientId,
        public readonly string $environment,
        public readonly string $contractorCnpj,
        public readonly string $authorIdentity,
        public readonly string $contributorCnpj,
        public readonly ?string $operationKey = null,
        /** @deprecated Preferir operationKey + resolver de catálogo */
        public readonly ?string $solutionCode = null,
        /** @deprecated Preferir operationKey */
        public readonly ?string $serviceCode = null,
        /** @deprecated Preferir operationKey */
        public readonly ?string $operationCode = null,
        public readonly array $businessData = [],
        public readonly array $payload = [],
        public readonly array $headers = [],
        public readonly ?string $idempotencyKey = null,
        public readonly ?string $correlationId = null,
        public readonly ?string $requestTag = null,
    ) {}

    /**
     * Tag de correlação determinística (máx. 32 chars) para X-Request-Tag.
     */
    public function resolvedRequestTag(): string
    {
        if ($this->requestTag !== null && $this->requestTag !== '') {
            return substr($this->requestTag, 0, 32);
        }

        $seed = $this->idempotencyKey
            ?? ($this->correlationId ?? (string) $this->officeId.'-'.$this->clientId.'-'.($this->operationKey ?? 'op'));

        return substr(hash('sha256', $seed), 0, 32);
    }
}

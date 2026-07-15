<?php

namespace App\DTO\Serpro;

/**
 * Resposta de domínio — sem envelope HTTP bruto.
 *
 * @phpstan-type Body array<string, mixed>
 */
final class IntegraResponse
{
    /**
     * @param  array<string, mixed>  $body
     * @param  array<string, string>  $headers
     */
    public function __construct(
        public readonly bool $success,
        public readonly int $httpStatus,
        public readonly array $body,
        public readonly array $headers = [],
        public readonly ?string $errorCode = null,
        public readonly ?string $errorMessage = null,
        public readonly bool $simulated = false,
        public readonly ?int $retryAfterSeconds = null,
        public readonly ?string $correlationId = null,
        public readonly ?int $latencyMs = null,
    ) {}

    /**
     * Resultados SIMULATED não devem virar evidência fiscal produtiva.
     */
    public function isProductiveEvidence(): bool
    {
        return $this->success && ! $this->simulated;
    }

    /**
     * @return array<string, mixed>
     */
    public function toSanitizedArray(): array
    {
        return [
            'success' => $this->success,
            'http_status' => $this->httpStatus,
            'error_code' => $this->errorCode,
            'error_message' => $this->errorMessage,
            'simulated' => $this->simulated,
            'retry_after_seconds' => $this->retryAfterSeconds,
            'correlation_id' => $this->correlationId,
            'latency_ms' => $this->latencyMs,
            'body_keys' => array_keys($this->body),
        ];
    }
}

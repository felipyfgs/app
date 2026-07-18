<?php

namespace App\DTO\Serpro;

/**
 * Resposta normalizada Integra Contador (HTTP + negócio), sem vazar payload fiscal em logs.
 *
 * @phpstan-type Body array<string, mixed>
 */
final class IntegraResponse
{
    /**
     * @param  array<string, mixed>  $body
     * @param  array<string, string>  $headers
     * @param  list<array{codigo?: string, texto?: string}>  $mensagens
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
        public readonly ?string $etag = null,
        public readonly ?string $expiresHeader = null,
        public readonly ?string $businessStatus = null,
        public readonly array $mensagens = [],
        /** @var array<string, mixed>|string|null dados parseados (pedidoDados.dados / resposta) */
        public readonly mixed $dados = null,
        public readonly ?string $operationKey = null,
        public readonly ?string $requestTag = null,
        public readonly ?string $functionalRoute = null,
        public readonly ?string $sourceProvenance = null,
    ) {}

    public function isProductiveEvidence(): bool
    {
        return $this->success
            && ! $this->simulated
            && ! in_array($this->sourceProvenance, ['SIMULATED', 'SERPRO_TRIAL'], true);
    }

    public function isStillProcessing(): bool
    {
        if (in_array($this->httpStatus, [202, 204], true)) {
            return true;
        }
        if ($this->errorCode === 'STILL_PROCESSING') {
            return true;
        }
        $status = strtoupper((string) ($this->businessStatus ?? ''));

        return in_array($status, ['PROCESSING', 'PROCESSANDO', 'PENDENTE', 'EM_PROCESSAMENTO', 'AGUARDANDO'], true);
    }

    public function waitSeconds(): ?int
    {
        if ($this->retryAfterSeconds !== null && $this->retryAfterSeconds > 0) {
            return $this->retryAfterSeconds;
        }

        foreach ([
            'TempoEsperaMedioEmMs',
            'tempoEsperaMedioEmMs',
            'tempoEspera',
            'tempo_espera',
            'waitSeconds',
        ] as $key) {
            if (isset($this->body[$key]) && is_numeric($this->body[$key])) {
                $raw = (int) $this->body[$key];
                // Campos *EmMs → segundos de espera (ceil)
                if (str_contains($key, 'Ms') || str_contains($key, 'ms')) {
                    return max(1, (int) ceil($raw / 1000));
                }

                return max(1, $raw);
            }
            if (is_array($this->dados) && isset($this->dados[$key]) && is_numeric($this->dados[$key])) {
                $raw = (int) $this->dados[$key];
                if (str_contains($key, 'Ms') || str_contains($key, 'ms')) {
                    return max(1, (int) ceil($raw / 1000));
                }

                return max(1, $raw);
            }
        }

        // ETag pode carregar tempoEspera em alguns fluxos SITFIS
        if ($this->etag !== null && preg_match('/tempoEspera[=:](\d+)/i', $this->etag, $m)) {
            return max(1, (int) $m[1]);
        }

        return null;
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
            'has_etag' => $this->etag !== null && $this->etag !== '',
            'expires' => $this->expiresHeader,
            'business_status' => $this->businessStatus,
            'mensagens_count' => count($this->mensagens),
            'has_dados' => $this->dados !== null,
            'operation_key' => $this->operationKey,
            'request_tag' => $this->requestTag,
            'functional_route' => $this->functionalRoute,
            'source_provenance' => $this->sourceProvenance,
            'body_keys' => array_keys($this->body),
        ];
    }
}

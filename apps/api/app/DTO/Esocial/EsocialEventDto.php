<?php

namespace App\DTO\Esocial;

use App\Enums\EsocialEventCode;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * DTO estrito de evento eSocial oficial (antes da persistência).
 * payloadBytes são a evidência bruta; nunca inferir guia/pagamento a partir deles.
 */
final readonly class EsocialEventDto
{
    public function __construct(
        public EsocialEventCode $eventCode,
        public string $competencePeriodKey,
        public string $payloadBytes,
        public ?string $eventVersion = null,
        public ?string $receiptNumber = null,
        public ?string $establishmentCnpj = null,
        public ?CarbonImmutable $occurredAt = null,
        public ?CarbonImmutable $observedAt = null,
        /** @var array<string, mixed> Metadados sanitizados (sem PFX/tokens/XML de termo). */
        public array $metadata = [],
    ) {
        if ($this->payloadBytes === '') {
            throw new InvalidArgumentException('Evento eSocial exige payloadBytes não vazio.');
        }
        if (! preg_match('/^\d{4}-\d{2}$/', $this->competencePeriodKey)) {
            throw new InvalidArgumentException(
                "Competência eSocial inválida: {$this->competencePeriodKey} (esperado YYYY-MM)."
            );
        }
    }

    public function contentSha256(): string
    {
        return hash('sha256', $this->payloadBytes);
    }

    /**
     * @return array<string, mixed>
     */
    public function toSanitizedArray(): array
    {
        return [
            'event_code' => $this->eventCode->value,
            'event_version' => $this->eventVersion,
            'competence_period_key' => $this->competencePeriodKey,
            'receipt_number' => $this->receiptNumber,
            'establishment_cnpj' => $this->establishmentCnpj,
            'content_sha256' => $this->contentSha256(),
            'byte_size' => strlen($this->payloadBytes),
            'occurred_at' => $this->occurredAt?->toIso8601String(),
            'observed_at' => ($this->observedAt ?? CarbonImmutable::now())->toIso8601String(),
            'metadata' => $this->metadata,
        ];
    }
}

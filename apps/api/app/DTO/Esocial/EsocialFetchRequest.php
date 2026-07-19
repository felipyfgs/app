<?php

namespace App\DTO\Esocial;

use App\Enums\EsocialEventCode;
use App\Models\Client;
use App\Models\Establishment;
use App\Models\Office;

/**
 * Pedido de consulta M2M a eventos eSocial admitidos.
 *
 * @phpstan-type SupportedCodes list<EsocialEventCode>
 */
final readonly class EsocialFetchRequest
{
    /**
     * @param  list<EsocialEventCode>  $eventCodes
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public Office $office,
        public Client $client,
        public string $competencePeriodKey,
        public ?Establishment $establishment = null,
        public array $eventCodes = [],
        public ?string $correlationId = null,
        public array $context = [],
    ) {}

    /**
     * @return list<EsocialEventCode>
     */
    public function resolvedEventCodes(): array
    {
        if ($this->eventCodes !== []) {
            return $this->eventCodes;
        }

        return EsocialEventCode::supported();
    }
}

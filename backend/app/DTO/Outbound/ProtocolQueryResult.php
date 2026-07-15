<?php

namespace App\DTO\Outbound;

/**
 * Resultado tipado da consulta de protocolo — sem envelope SOAP.
 */
final readonly class ProtocolQueryResult
{
    public function __construct(
        public string $cStat,
        public string $xMotivo,
        public ?string $consultedAccessKey = null,
        public ?string $returnedAccessKey = null,
        public ?string $protocol = null,
        public ?string $tpAmb = null,
        public bool $ambiguousTimeout = false,
        /** @var array<string, scalar|null> */
        public array $sanitized = [],
    ) {}

    public function isUnauthorizedConsumption(): bool
    {
        return $this->cStat === '656';
    }

    public function isNotFound(): bool
    {
        return $this->cStat === '217';
    }

    public function is562WithKey(): bool
    {
        return $this->cStat === '562' && $this->returnedAccessKey !== null && strlen($this->returnedAccessKey) >= 44;
    }

    public function is562WithoutKey(): bool
    {
        return $this->cStat === '562' && ($this->returnedAccessKey === null || strlen($this->returnedAccessKey) < 44);
    }

    public function isLimitedWithoutKey(): bool
    {
        return in_array($this->cStat, ['561', '613', '526'], true)
            || $this->is562WithoutKey();
    }

    public function isAuthorizedOnCandidate(): bool
    {
        return in_array($this->cStat, ['100', '101', '110', '150'], true);
    }
}

<?php

namespace App\DTO\Fiscal\Module;

/** Contadores de KPI no mesmo escopo filtrado da carteira (não só da página). */
final readonly class ModuleCountersDto
{
    public function __construct(
        public int $upToDate = 0,
        public int $processing = 0,
        public int $pending = 0,
        public int $attention = 0,
        public int $error = 0,
    ) {}

    /**
     * @return array{
     *     up_to_date: int,
     *     processing: int,
     *     pending: int,
     *     attention: int,
     *     error: int
     * }
     */
    public function toArray(): array
    {
        return [
            'up_to_date' => $this->upToDate,
            'processing' => $this->processing,
            'pending' => $this->pending,
            'attention' => $this->attention,
            'error' => $this->error,
        ];
    }
}

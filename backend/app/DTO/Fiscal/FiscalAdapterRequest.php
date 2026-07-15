<?php

namespace App\DTO\Fiscal;

use App\Enums\FiscalTrigger;
use App\Models\Client;
use App\Models\FiscalCompetence;
use App\Models\FiscalMonitoringRun;
use App\Models\Office;

final readonly class FiscalAdapterRequest
{
    /**
     * @param  array<string, mixed>  $progress  cursor/progresso da run
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public Office $office,
        public Client $client,
        public FiscalMonitoringRun $run,
        public string $systemCode,
        public string $serviceCode,
        public string $operationCode,
        public FiscalTrigger $trigger,
        public ?FiscalCompetence $competence = null,
        public ?string $progressCursor = null,
        public array $progress = [],
        public array $context = [],
    ) {}
}

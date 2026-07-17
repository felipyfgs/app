<?php

namespace App\Services\FiscalMonitoring\Surfaces;

use App\Enums\MonitoringChannel;
use App\Enums\MonitoringDocumentPolicy;
use App\Enums\MonitoringOfficialStateSummary;
use App\Enums\MonitoringResultKind;

/**
 * Contrato backend de uma superfície da page-payload-matrix.
 * operation_keys permanecem internos; toPublicArray() não as expõe.
 */
final readonly class MonitoringSurfaceContract
{
    /**
     * @param  list<string>  $operationKeys
     */
    public function __construct(
        public string $surfaceKey,
        public string $routePattern,
        public string $responsibility,
        public MonitoringChannel $channel,
        public array $operationKeys,
        public MonitoringOfficialStateSummary $officialState,
        public MonitoringResultKind $resultKind,
        public bool $allowsDocument,
        public MonitoringDocumentPolicy $documentPolicy,
        public string $sourceLabel,
    ) {}

    /**
     * Resumo público para UI — sem idSistema/idServico/operation_key.
     *
     * @return array{
     *   surface_key: string,
     *   route: string,
     *   responsibility: string,
     *   result_kind: string,
     *   allows_document: bool,
     *   official_state_label: string,
     *   channel_label: string
     * }
     */
    public function toPublicArray(): array
    {
        return [
            'surface_key' => $this->surfaceKey,
            'route' => $this->routePattern,
            'responsibility' => $this->responsibility,
            'result_kind' => $this->resultKind->value,
            'allows_document' => $this->allowsDocument,
            'official_state_label' => $this->officialState->label(),
            'channel_label' => $this->channel->label(),
        ];
    }
}

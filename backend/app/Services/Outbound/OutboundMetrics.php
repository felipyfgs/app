<?php

namespace App\Services\Outbound;

use App\Enums\OutboundCaptureRunStatus;
use App\Enums\OutboundNumberStatus;
use App\Enums\OutboundSeriesStatus;
use App\Models\OutboundCaptureRun;
use App\Models\OutboundNumberState;
use App\Models\OutboundSeriesCursor;
use Illuminate\Support\Facades\Log;

/**
 * Métricas de baixa cardinalidade — sem chave completa, CSC, PFX ou XML.
 */
final class OutboundMetrics
{
    /**
     * @return array{
     *   queue: string,
     *   series_idle: int,
     *   series_blocked: int,
     *   series_incident: int,
     *   gaps_exhausted: int,
     *   xml_pending: int,
     *   runs_last_24h: int,
     *   runs_failed_24h: int,
     *   labels: array<string, string>
     * }
     */
    public function snapshot(?int $officeId = null): array
    {
        $seriesQ = OutboundSeriesCursor::query();
        $numQ = OutboundNumberState::query();
        $runQ = OutboundCaptureRun::query()->where('created_at', '>=', now()->subDay());

        if ($officeId !== null) {
            $seriesQ->where('office_id', $officeId);
            $numQ->where('office_id', $officeId);
            $runQ->where('office_id', $officeId);
        }

        $data = [
            'queue' => (string) config('sefaz.ma_outbound.queue', 'capture-outbound-ma'),
            'series_idle' => (clone $seriesQ)->where('status', OutboundSeriesStatus::Idle)->count(),
            'series_blocked' => (clone $seriesQ)->where('status', OutboundSeriesStatus::Blocked)->count(),
            'series_incident' => (clone $seriesQ)->where('status', OutboundSeriesStatus::FiscalIncident)->count(),
            'gaps_exhausted' => (clone $numQ)->where('status', OutboundNumberStatus::ExhaustedVisible)->count(),
            'xml_pending' => (clone $numQ)->where('status', OutboundNumberStatus::XmlPending)->count(),
            'runs_last_24h' => (clone $runQ)->count(),
            'runs_failed_24h' => (clone $runQ)->where('status', OutboundCaptureRunStatus::Failed)->count(),
            'labels' => [
                'channel' => 'MA_OUTBOUND',
                'position_kind' => 'nNF',
                'uf' => 'MA',
            ],
        ];

        Log::info('metrics.outbound_ma', $data);

        return $data;
    }
}

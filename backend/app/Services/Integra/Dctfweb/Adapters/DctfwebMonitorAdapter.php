<?php

namespace App\Services\Integra\Dctfweb\Adapters;

use App\DTO\Fiscal\FiscalAdapterRequest;
use App\DTO\Fiscal\FiscalAdapterResult;
use App\Enums\DctfwebTransmissionStatus;
use App\Enums\FiscalCoverage;
use App\Enums\FiscalSituation;
use App\Services\Integra\Dctfweb\DctfwebCodes;
use App\Services\Integra\Dctfweb\DctfwebCompetenceResolver;
use App\Services\Integra\Dctfweb\DctfwebDeclarationService;
use App\Services\Integra\Dctfweb\DctfwebIntegraCaller;

/**
 * Reconciliação dirigida pós-evento: consulta recibo/declaração da competência afetada.
 */
final class DctfwebMonitorAdapter extends AbstractDctfwebAdapter
{
    public function __construct(
        DctfwebIntegraCaller $caller,
        DctfwebCompetenceResolver $competences,
        private readonly DctfwebDeclarationService $declarations,
    ) {
        parent::__construct($caller, $competences);
    }

    public function systemCode(): string
    {
        return DctfwebCodes::SYSTEM_DCTFWEB;
    }

    public function serviceCode(): string
    {
        return DctfwebCodes::SERVICE_DCTFWEB;
    }

    public function operationCode(): string
    {
        return DctfwebCodes::OP_MONITOR;
    }

    public function execute(FiscalAdapterRequest $request): FiscalAdapterResult
    {
        $periodKey = $this->resolvePeriodKey($request);

        // Preferência: CONSULTAR_RECIBO; fallback genérico de declaração
        $response = $this->caller->call(
            request: $request,
            solutionCode: DctfwebCodes::SYSTEM_DCTFWEB,
            serviceCode: DctfwebCodes::SERVICE_DCTFWEB,
            operationCode: DctfwebCodes::OP_CONSULTAR_RECIBO,
            payload: [
                'competencia' => $periodKey,
                'periodo' => $periodKey,
            ],
        );

        if (! $response->success) {
            // Fallback declaração
            $response = $this->caller->call(
                request: $request,
                solutionCode: DctfwebCodes::SYSTEM_DCTFWEB,
                serviceCode: DctfwebCodes::SERVICE_DCTFWEB,
                operationCode: DctfwebCodes::OP_CONSULTAR_DECLARACAO,
                payload: [
                    'competencia' => $periodKey,
                    'periodo' => $periodKey,
                ],
            );
        }

        if (! $response->success) {
            return $this->failedFromResponse($response);
        }

        if ($response->simulated && ! $response->isProductiveEvidence()) {
            // Trial/simulado: projeta a partir do body mas sem UP_TO_DATE se vazio
            $body = $response->body;
        } else {
            $body = $response->body;
        }

        $bytes = DctfwebIntegraCaller::evidenceBytes($body);
        $projected = $this->declarations->projectFromRecibo(
            run: $request->run,
            office: $request->office,
            client: $request->client,
            periodKey: $periodKey,
            evidenceBytes: $bytes,
            body: $body,
            sourceVersion: isset($body['versao']) ? (string) $body['versao'] : null,
        );

        $decl = $projected['declaration'];
        $situation = $decl->situation ?? FiscalSituation::Unknown;

        // Evidência simulada não promove regularidade plena
        if ($response->simulated && $situation === FiscalSituation::UpToDate) {
            $situation = FiscalSituation::Unknown;
        }

        $normalized = [
            'period_key' => $periodKey,
            'transmission_status' => $decl->transmission_status?->value
                ?? DctfwebTransmissionStatus::Unknown->value,
            'receipt_number' => $decl->receipt_number,
            'declaration_type' => $decl->declaration_type,
            'evidence_version' => $decl->evidence_version,
            'retification' => $projected['retification'],
            'simulated' => $response->simulated,
            'directed_event' => true,
        ];

        return $this->successResult(
            situation: $situation,
            evidenceBytes: $bytes,
            normalized: $normalized,
            findings: $this->retificationFinding($projected['retification']),
            coverage: FiscalCoverage::Full,
        );
    }
}

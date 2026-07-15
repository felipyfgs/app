<?php

namespace App\Services\Integra\Dctfweb\Adapters;

use App\DTO\Fiscal\FiscalAdapterRequest;
use App\DTO\Fiscal\FiscalAdapterResult;
use App\Enums\DctfwebArtifactKind;
use App\Enums\FiscalCoverage;
use App\Enums\FiscalSituation;
use App\Services\Integra\Dctfweb\DctfwebCodes;
use App\Services\Integra\Dctfweb\DctfwebCompetenceResolver;
use App\Services\Integra\Dctfweb\DctfwebDeclarationService;
use App\Services\Integra\Dctfweb\DctfwebIntegraCaller;

final class DctfwebRelatorioAdapter extends AbstractDctfwebAdapter
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
        return DctfwebCodes::OP_CONSULTAR_RELATORIO;
    }

    public function execute(FiscalAdapterRequest $request): FiscalAdapterResult
    {
        $periodKey = $this->resolvePeriodKey($request);
        $response = $this->callUpstream($request, [
            'competencia' => $periodKey,
            'periodo' => $periodKey,
        ]);

        if (! $response->success) {
            return $this->failedFromResponse($response);
        }

        $bytes = DctfwebIntegraCaller::evidenceBytes($response->body);
        $projected = $this->declarations->projectArtifact(
            run: $request->run,
            office: $request->office,
            client: $request->client,
            periodKey: $periodKey,
            kind: DctfwebArtifactKind::Relatorio,
            evidenceBytes: $bytes,
            body: $response->body,
            contentType: 'application/json',
            sourceVersion: isset($response->body['versao']) ? (string) $response->body['versao'] : null,
        );

        $situation = $response->simulated
            ? FiscalSituation::Unknown
            : FiscalSituation::UpToDate;

        return $this->successResult(
            situation: $situation,
            evidenceBytes: $bytes,
            normalized: [
                'period_key' => $periodKey,
                'artifact_kind' => 'RELATORIO',
                'retification' => $projected['retification'],
                'evidence_version' => $projected['version']->version,
                'simulated' => $response->simulated,
            ],
            findings: $this->retificationFinding($projected['retification']),
            coverage: FiscalCoverage::Full,
        );
    }
}

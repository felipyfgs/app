<?php

namespace App\Services\Fiscal\SimplesMei;

use App\DTO\Fiscal\FiscalAdapterResult;
use App\DTO\Fiscal\SimplesMei\CcmeiDto;
use App\DTO\Fiscal\SimplesMei\DasGuideDto;
use App\DTO\Fiscal\SimplesMei\DasnSimeiDto;
use App\DTO\Fiscal\SimplesMei\DefisDto;
use App\DTO\Fiscal\SimplesMei\PgdasdDeclarationDto;
use App\DTO\Fiscal\SimplesMei\PgmeiDto;
use App\DTO\Fiscal\SimplesMei\RegimeApuracaoDto;
use App\DTO\Fiscal\SimplesMei\SimplesMeiOperationDef;
use App\DTO\Serpro\IntegraResponse;
use App\Enums\FiscalCoverage;
use App\Enums\FiscalFindingSeverity;
use App\Enums\FiscalRunResult;
use App\Enums\FiscalSituation;
use InvalidArgumentException;

/**
 * Converte resposta Integra em FiscalAdapterResult + DTO normalizado.
 */
final class SimplesMeiResponseMapper
{
    public function map(
        SimplesMeiOperationDef $def,
        IntegraResponse $response,
        string $periodKey = '',
    ): FiscalAdapterResult {
        if (! $response->success) {
            return FiscalAdapterResult::failed(
                $response->errorMessage ?? 'Falha na chamada Integra Contador.',
                $response->errorCode ?? 'INTEGRA_FAILED',
                $def->coverage,
            );
        }

        $body = $response->body;
        $evidence = json_encode($body, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

        try {
            [$situation, $normalized, $findings] = $this->parse($def, $body, $periodKey);
        } catch (InvalidArgumentException $e) {
            return FiscalAdapterResult::failed($e->getMessage(), 'DTO_VERSION_UNSUPPORTED', $def->coverage);
        }

        if ($response->simulated) {
            $normalized['simulated'] = true;
            $normalized['evidence_productive'] = false;
        } else {
            $normalized['simulated'] = false;
            $normalized['evidence_productive'] = true;
        }

        // UP_TO_DATE exige evidência (bytes) — sempre presente aqui se success
        if ($situation === FiscalSituation::UpToDate && $evidence === '') {
            $situation = FiscalSituation::Unknown;
        }

        return new FiscalAdapterResult(
            result: FiscalRunResult::Success,
            situation: $situation,
            coverage: $def->coverage,
            evidenceBytes: $evidence,
            evidenceContentType: 'application/json',
            sourceVersion: $def->dtoVersion,
            normalized: $normalized,
            findings: $findings,
            itemsProcessed: 1,
            pagesProcessed: 1,
        );
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array{0: FiscalSituation, 1: array<string, mixed>, 2: list<array<string, mixed>>}
     */
    private function parse(SimplesMeiOperationDef $def, array $body, string $periodKey): array
    {
        $service = strtoupper($def->serviceCode);
        $op = strtoupper($def->operationCode);

        if ($service === 'PGDASD' && in_array($op, ['MONITOR', 'CONSULTAR_DECLARACAO', 'CONSULTAR_RECIBO', 'CONSULTAR_EXTRATO'], true)) {
            $dto = PgdasdDeclarationDto::fromIntegraBody($body, $periodKey);

            return [$dto->situation, $dto->toNormalized(), $this->findingsFromSituation($dto->situation, 'PGDASD', $dto->status)];
        }

        if ($service === 'PGDASD' && $op === 'GERAR_DAS') {
            $dto = DasGuideDto::fromIntegraBody($body, $periodKey, 'SIMPLES_NACIONAL');

            return [FiscalSituation::Attention, $dto->toNormalized(), [[
                'code' => 'DAS_EMITTED_PAYMENT_UNKNOWN',
                'severity' => FiscalFindingSeverity::Info->value,
                'title' => 'DAS emitido (pagamento não confirmado)',
                'detail' => 'Emissão assistida não implica quitação.',
                'situation' => FiscalSituation::Attention->value,
                'creates_pending' => false,
            ]]];
        }

        if ($service === 'PGDASD' && $op === 'TRANSMITIR') {
            return $this->mutatingDeclarationResult($body, 'PGDASD');
        }

        if ($service === 'DEFIS' && in_array($op, ['MONITOR', 'CONSULTAR'], true)) {
            $year = strlen($periodKey) >= 4 ? substr($periodKey, 0, 4) : $periodKey;
            $dto = DefisDto::fromIntegraBody($body, $year);

            return [$dto->situation, $dto->toNormalized(), $this->findingsFromSituation($dto->situation, 'DEFIS', $dto->status)];
        }

        if ($service === 'DEFIS' && $op === 'TRANSMITIR') {
            return $this->mutatingDeclarationResult($body, 'DEFIS');
        }

        if ($service === 'REGIME_APURACAO' && in_array($op, ['MONITOR', 'CONSULTAR'], true)) {
            $dto = RegimeApuracaoDto::fromIntegraBody($body);
            $situation = $dto->currentRegime->value === 'UNKNOWN'
                ? FiscalSituation::Unknown
                : FiscalSituation::UpToDate;

            return [$situation, $dto->toNormalized(), []];
        }

        if ($service === 'PGMEI' && in_array($op, ['MONITOR', 'CONSULTAR', 'CONSULTAR_DAS'], true)) {
            $dto = PgmeiDto::fromIntegraBody($body, $periodKey);

            return [$dto->situation, $dto->toNormalized(), $this->findingsFromSituation($dto->situation, 'PGMEI', $dto->status)];
        }

        if ($service === 'PGMEI' && $op === 'GERAR_DAS') {
            $dto = DasGuideDto::fromIntegraBody($body, $periodKey, 'MEI');

            return [FiscalSituation::Attention, $dto->toNormalized(), [[
                'code' => 'DAS_MEI_EMITTED_PAYMENT_UNKNOWN',
                'severity' => FiscalFindingSeverity::Info->value,
                'title' => 'DAS MEI emitido (pagamento não confirmado)',
                'detail' => 'Emissão assistida não implica quitação.',
                'situation' => FiscalSituation::Attention->value,
                'creates_pending' => false,
            ]]];
        }

        if ($service === 'CCMEI' && in_array($op, ['MONITOR', 'CONSULTAR'], true)) {
            $dto = CcmeiDto::fromIntegraBody($body);

            return [$dto->situation, $dto->toNormalized(), $this->findingsFromSituation($dto->situation, 'CCMEI', $dto->status)];
        }

        if ($service === 'DASN_SIMEI' && in_array($op, ['MONITOR', 'CONSULTAR'], true)) {
            $year = strlen($periodKey) >= 4 ? substr($periodKey, 0, 4) : $periodKey;
            $dto = DasnSimeiDto::fromIntegraBody($body, $year);

            return [$dto->situation, $dto->toNormalized(), $this->findingsFromSituation($dto->situation, 'DASN_SIMEI', $dto->status)];
        }

        if ($service === 'DASN_SIMEI' && $op === 'TRANSMITIR') {
            return $this->mutatingDeclarationResult($body, 'DASN_SIMEI');
        }

        throw new InvalidArgumentException(
            "Operação Simples/MEI sem mapper: {$def->systemCode}/{$def->serviceCode}/{$def->operationCode}"
        );
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array{0: FiscalSituation, 1: array<string, mixed>, 2: list<array<string, mixed>>}
     */
    private function mutatingDeclarationResult(array $body, string $service): array
    {
        return [
            FiscalSituation::Attention,
            [
                'dto' => 'declaration_transmit',
                'service' => $service,
                'status' => $body['status'] ?? 'UNKNOWN',
                'mutability' => 'MUTATING',
            ],
            [[
                'code' => 'MUTATING_TRANSMIT',
                'severity' => FiscalFindingSeverity::High->value,
                'title' => 'Transmissão de declaração',
                'detail' => 'Operação mutante — exige flags e aprovação.',
                'situation' => FiscalSituation::Attention->value,
                'creates_pending' => false,
            ]],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function findingsFromSituation(FiscalSituation $situation, string $service, string $status): array
    {
        if ($situation === FiscalSituation::Pending) {
            return [[
                'code' => "{$service}_PENDING",
                'severity' => FiscalFindingSeverity::High->value,
                'title' => "Pendência {$service}",
                'detail' => "Status oficial: {$status}",
                'situation' => FiscalSituation::Pending->value,
                'creates_pending' => true,
            ]];
        }

        if ($situation === FiscalSituation::Unknown) {
            return [[
                'code' => "{$service}_INCONCLUSIVE",
                'severity' => FiscalFindingSeverity::Info->value,
                'title' => "Competência inconclusiva ({$service})",
                'detail' => 'Fonte não confirmou entrega nem pendência — situação UNKNOWN.',
                'situation' => FiscalSituation::Unknown->value,
                'creates_pending' => false,
            ]];
        }

        return [];
    }
}

<?php

namespace Tests\Support;

use App\Contracts\FiscalSourceAdapter;
use App\DTO\Fiscal\FiscalAdapterRequest;
use App\DTO\Fiscal\FiscalAdapterResult;
use App\Enums\FiscalCoverage;
use App\Enums\FiscalFindingSeverity;
use App\Enums\FiscalMutability;
use App\Enums\FiscalRunResult;
use App\Enums\FiscalSituation;

/**
 * Adapter controlável para testes do núcleo fiscal.
 */
final class FakeFiscalSourceAdapter implements FiscalSourceAdapter
{
    public int $calls = 0;

    public function __construct(
        private FiscalAdapterResult $result,
        private string $system = 'INTEGRA_TEST',
        private string $service = 'TEST_SVC',
        private string $operation = 'MONITOR',
        private FiscalCoverage $coverage = FiscalCoverage::Full,
        private FiscalMutability $mutability = FiscalMutability::ReadOnly,
        private ?string $module = null,
    ) {}

    public static function upToDateWithEvidence(): self
    {
        $evidence = json_encode(['status' => 'OK', 'regular' => true], JSON_THROW_ON_ERROR);

        return new self(new FiscalAdapterResult(
            result: FiscalRunResult::Success,
            situation: FiscalSituation::UpToDate,
            coverage: FiscalCoverage::Full,
            evidenceBytes: $evidence,
            normalized: ['regular' => true],
        ));
    }

    public static function claimUpToDateWithoutEvidence(): self
    {
        return new self(new FiscalAdapterResult(
            result: FiscalRunResult::Success,
            situation: FiscalSituation::UpToDate,
            coverage: FiscalCoverage::Full,
            evidenceBytes: null,
        ));
    }

    public static function requeueAfterPages(): self
    {
        $evidence = json_encode(['page' => 1, 'has_more' => true], JSON_THROW_ON_ERROR);

        return new self(new FiscalAdapterResult(
            result: FiscalRunResult::Partial,
            situation: FiscalSituation::Processing,
            coverage: FiscalCoverage::Full,
            evidenceBytes: $evidence,
            normalized: ['page' => 1],
            shouldRequeue: true,
            progressCursor: 'page:2',
            progress: ['page' => 1],
            pagesProcessed: 20,
            itemsProcessed: 50,
        ));
    }

    public static function withPendingFinding(): self
    {
        $evidence = json_encode(['debt' => 100], JSON_THROW_ON_ERROR);

        return new self(new FiscalAdapterResult(
            result: FiscalRunResult::Success,
            situation: FiscalSituation::Pending,
            coverage: FiscalCoverage::Full,
            evidenceBytes: $evidence,
            normalized: ['debt' => 100],
            findings: [[
                'code' => 'DEBT_OPEN',
                'severity' => FiscalFindingSeverity::High->value,
                'title' => 'Débito em aberto',
                'detail' => 'Valor 100',
                'situation' => FiscalSituation::Pending->value,
                'creates_pending' => true,
            ]],
        ));
    }

    public static function failAfterEvidenceHook(): self
    {
        // Usado com spy que falha na projeção — adapter normal com finding.
        return self::withPendingFinding();
    }

    public function systemCode(): string
    {
        return $this->system;
    }

    public function serviceCode(): string
    {
        return $this->service;
    }

    public function operationCode(): string
    {
        return $this->operation;
    }

    public function mutability(): FiscalMutability
    {
        return $this->mutability;
    }

    public function coverage(): FiscalCoverage
    {
        return $this->coverage;
    }

    public function moduleKey(): ?string
    {
        return $this->module;
    }

    public function supports(FiscalAdapterRequest $request): bool
    {
        return strcasecmp($request->systemCode, $this->system) === 0
            && strcasecmp($request->serviceCode, $this->service) === 0;
    }

    public function execute(FiscalAdapterRequest $request): FiscalAdapterResult
    {
        $this->calls++;

        return $this->result;
    }

    public function setResult(FiscalAdapterResult $result): void
    {
        $this->result = $result;
    }
}

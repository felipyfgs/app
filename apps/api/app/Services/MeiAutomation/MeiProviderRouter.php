<?php

namespace App\Services\MeiAutomation;

use App\Contracts\FiscalSourceAdapter;
use App\DTO\Fiscal\FiscalAdapterRequest;
use App\DTO\Fiscal\FiscalAdapterResult;
use App\DTO\Fiscal\SimplesMei\SimplesMeiOperationDef;
use App\Enums\FiscalCoverage;
use App\Enums\FiscalMutability;
use App\Enums\MeiProvider;
use App\Services\Fiscal\SimplesMei\SimplesMeiCatalog;
use App\Services\MeiAutomation\Providers\MeiOperationProvider;
use App\Services\MeiAutomation\Providers\ReceitaPortalProvider;
use App\Services\MeiAutomation\Providers\SerproMeiProvider;
use App\Services\Serpro\Catalog\OperationKeyMap;

final readonly class MeiProviderRouter implements FiscalSourceAdapter
{
    public function __construct(
        private SimplesMeiOperationDef $definition,
        private SerproMeiProvider $serpro,
        private ReceitaPortalProvider $portal,
        private MeiProviderPolicy $policy,
        private MeiAutomationAttemptRepository $attempts,
    ) {}

    public function systemCode(): string
    {
        return $this->definition->systemCode;
    }

    public function serviceCode(): string
    {
        return $this->definition->serviceCode;
    }

    public function operationCode(): string
    {
        return $this->definition->operationCode;
    }

    public function mutability(): FiscalMutability
    {
        return $this->definition->mutability;
    }

    public function coverage(): FiscalCoverage
    {
        return $this->definition->coverage;
    }

    public function moduleKey(): ?string
    {
        return SimplesMeiCatalog::MODULE;
    }

    public function supports(FiscalAdapterRequest $request): bool
    {
        return strcasecmp($request->systemCode, $this->definition->systemCode) === 0
            && strcasecmp($request->serviceCode, $this->definition->serviceCode) === 0
            && strcasecmp($request->operationCode, $this->definition->operationCode) === 0;
    }

    public function execute(FiscalAdapterRequest $request): FiscalAdapterResult
    {
        $operationKey = OperationKeyMap::require(
            null,
            $this->definition->systemCode,
            $this->definition->serviceCode,
            $this->definition->operationCode,
        );
        $providers = $this->policy->providers($request->office, $operationKey);

        foreach ($providers as $index => $providerName) {
            $outcome = $this->provider($providerName)->execute($request, $operationKey);
            $hasNext = array_key_exists($index + 1, $providers);
            if (! $hasNext || ! $outcome->fallbackEligible || $outcome->submitted) {
                return $outcome->result;
            }
            if ($outcome->attempt !== null && $outcome->fallbackReason !== null) {
                $this->attempts->markFallback($outcome->attempt, $outcome->fallbackReason);
            }
        }

        return FiscalAdapterResult::failed('Nenhum provider MEI disponível.', 'MEI_PROVIDER_UNAVAILABLE');
    }

    private function provider(MeiProvider $provider): MeiOperationProvider
    {
        return match ($provider) {
            MeiProvider::ReceitaPortal, MeiProvider::Fixture => $this->portal,
            MeiProvider::Serpro => $this->serpro,
        };
    }
}

<?php

namespace App\Services\Integra;

use App\DTO\Serpro\EligibilityResult;
use App\Enums\OfficeRole;
use App\Enums\SerproAuthorizationStatus;
use App\Enums\SerproConsumptionClass;
use App\Enums\SerproEligibilityCode;
use App\Enums\SerproEnvironment;
use App\Models\Client;
use App\Models\Office;
use App\Models\SerproServiceCatalogEntry;
use App\Models\User;
use App\Services\Platform\OfficeSubscriptionGate;
use App\Services\Serpro\SerproCircuitBreaker;
use App\Services\Serpro\SerproContractService;
use App\Services\Serpro\SerproKillSwitchService;
use App\Services\Serpro\Usage\UsageBudgetGate;
use App\Support\FeatureFlags;
use Illuminate\Support\Facades\Cache;

/**
 * Matriz de elegibilidade pré-chamada Integra Contador.
 */
final class IntegraEligibilityService
{
    public function __construct(
        private readonly OfficeSubscriptionGate $subscriptionGate,
        private readonly SerproContractService $contracts,
        private readonly SerproKillSwitchService $killSwitch,
        private readonly SerproCircuitBreaker $breaker,
        private readonly TaxProxyPowerService $proxyPowers,
        private readonly OfficeSerproAuthorizationService $authorizations,
        private readonly UsageBudgetGate $budget,
    ) {}

    public function evaluate(
        Office $office,
        Client $client,
        string $solutionCode,
        string $serviceCode,
        string $operationCode,
        SerproEnvironment $environment,
        ?User $user = null,
        ?string $module = null,
    ): EligibilityResult {
        $codes = [];
        $context = [
            'office_id' => $office->id,
            'client_id' => $client->id,
            'solution' => $solutionCode,
            'service' => $serviceCode,
            'operation' => $operationCode,
            'environment' => $environment->value,
        ];

        // 0. Feature flags
        if ($module !== null && ! FeatureFlags::isModuleEnabled($module, $office->id)) {
            $codes[] = SerproEligibilityCode::FeatureDisabled;
        }
        if (FeatureFlags::isKillSwitchActive()) {
            $codes[] = SerproEligibilityCode::KillSwitch;
        }

        // 1. Kill switch SERPRO / solução
        if ($this->killSwitch->isSolutionBlocked($solutionCode)) {
            $codes[] = SerproEligibilityCode::KillSwitch;
        }

        // 2. Circuit breaker
        if (! $this->breaker->isCallAllowed($solutionCode)) {
            $codes[] = SerproEligibilityCode::CircuitOpen;
        }

        // 3. Assinatura do tenant
        if (! $this->subscriptionGate->allowsExternalCalls($office)) {
            $codes[] = SerproEligibilityCode::SubscriptionBlocked;
        }

        // 4. Contrato global
        $contract = $this->contracts->activeFor($environment);
        if ($contract === null || ! $contract->isUsable()) {
            $codes[] = SerproEligibilityCode::ContractUnavailable;
        } elseif ($contract->health_status === 'BLOCKED') {
            $codes[] = SerproEligibilityCode::ContractUnhealthy;
        }

        // 5. Autorização do escritório / Termo / token
        $auth = $this->authorizations->getOrCreate($office, $environment);
        if (in_array($auth->status, [
            SerproAuthorizationStatus::Draft,
            SerproAuthorizationStatus::PendingTerm,
            SerproAuthorizationStatus::Revoked,
        ], true)) {
            $codes[] = SerproEligibilityCode::AuthorizationMissing;
        }
        if ($auth->status === SerproAuthorizationStatus::ActionRequired) {
            $codes[] = SerproEligibilityCode::AuthorizationActionRequired;
        }
        if ($auth->status === SerproAuthorizationStatus::Expired
            || ($auth->termo_valid_to !== null && $auth->termo_valid_to->isPast())) {
            $codes[] = SerproEligibilityCode::AuthorizationExpired;
        }
        if (
            $auth->procurador_token_expires_at === null
            || $auth->procurador_token_expires_at->isPast()
        ) {
            if ($auth->status !== SerproAuthorizationStatus::TokenActive) {
                $codes[] = SerproEligibilityCode::TokenMissing;
            } else {
                $codes[] = SerproEligibilityCode::TokenExpired;
            }
        }

        // 6. Contribuinte mesmo tenant
        if ($client->office_id !== $office->id) {
            $codes[] = SerproEligibilityCode::ContributorCrossTenant;
        }

        // 7. Catálogo / cobertura / mutabilidade
        $catalog = SerproServiceCatalogEntry::query()
            ->where('environment', $environment->value)
            ->where('solution_code', $solutionCode)
            ->where('service_code', $serviceCode)
            ->where('operation_code', $operationCode)
            ->where('is_enabled', true)
            ->orderByDesc('catalog_version')
            ->first();

        if ($catalog === null) {
            $codes[] = SerproEligibilityCode::ServiceNotCataloged;
        } else {
            if ($catalog->coverage === 'UNSUPPORTED') {
                $codes[] = SerproEligibilityCode::CoverageUnsupported;
            }
            if ($catalog->is_mutating) {
                $mod = $module ?? 'mutacoes';
                if (! FeatureFlags::isMutatingEnabled($mod, $office->id)) {
                    $codes[] = SerproEligibilityCode::MutatingDisabled;
                }
            }

            // 8. Procuração / poder
            $requiredPower = $catalog->required_proxy_power;
            if ($requiredPower !== null && $requiredPower !== '') {
                $power = $this->proxyPowers->findUsablePower(
                    $office->id,
                    $client->id,
                    $requiredPower,
                    $auth->author_identity,
                );
                if ($power === null) {
                    $codes[] = SerproEligibilityCode::ProxyPowerMissing;
                }
            }
        }

        // 9. Papel do usuário (se presente)
        if ($user !== null) {
            $membership = $user->memberships()
                ->where('office_id', $office->id)
                ->where('is_active', true)
                ->first();
            if ($membership === null) {
                $codes[] = SerproEligibilityCode::RoleForbidden;
            } elseif ($membership->role === OfficeRole::Viewer) {
                // VIEWER não dispara chamadas externas
                $codes[] = SerproEligibilityCode::RoleForbidden;
            }
        }

        // 10. Orçamento — mesma fonte de verdade do ledger ({@see UsageBudgetGate})
        $consumptionClass = $this->resolveConsumptionClass($catalog);
        $budgetEval = $this->budget->evaluate(
            officeId: (int) $office->id,
            class: $consumptionClass,
            quantity: 1,
            isEssential: false,
        );
        if (! (bool) $budgetEval['allowed']) {
            $codes[] = SerproEligibilityCode::BudgetExceeded;
        }
        // Snapshot tenant-safe (sem global_used/global_budget)
        $context['budget_used'] = $budgetEval['used_quantity'];
        $context['budget_reserved_open'] = $budgetEval['reserved_open_quantity'];
        $context['budget_quota'] = $budgetEval['franchise_quota'];
        $context['budget_would_block'] = $budgetEval['would_block'];
        $context['budget_block_reason'] = $budgetEval['block_reason'];

        // 11. Rate limit simples
        $perOffice = (int) config('serpro.rate_limit.per_office_per_minute', 30);
        $rateKey = 'serpro.rate.office.'.$office->id.'.'.now()->format('YmdHi');
        $hits = (int) Cache::get($rateKey, 0);
        if ($hits >= $perOffice) {
            $codes[] = SerproEligibilityCode::RateLimited;
        }

        $blocking = array_values(array_filter(
            $codes,
            fn (SerproEligibilityCode $c) => $c->isBlocking(),
        ));

        if ($blocking !== []) {
            return EligibilityResult::blockedMany($blocking, $context);
        }

        return EligibilityResult::ok($context);
    }

    /**
     * Incrementa contador de rate limit após elegibilidade OK (pré-chamada).
     */
    public function touchRateLimit(int $officeId): void
    {
        $rateKey = 'serpro.rate.office.'.$officeId.'.'.now()->format('YmdHi');
        if (! Cache::has($rateKey)) {
            Cache::put($rateKey, 1, 120);
        } else {
            Cache::increment($rateKey);
        }
    }

    private function resolveConsumptionClass(?SerproServiceCatalogEntry $catalog): SerproConsumptionClass
    {
        if ($catalog === null) {
            return SerproConsumptionClass::Consulta;
        }

        $raw = $catalog->billable_class;
        $value = $raw instanceof \BackedEnum ? $raw->value : (string) ($raw ?? 'CONSULTA');

        return SerproConsumptionClass::tryFrom(strtoupper($value))
            ?? SerproConsumptionClass::Consulta;
    }
}

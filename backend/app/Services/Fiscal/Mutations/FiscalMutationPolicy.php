<?php

namespace App\Services\Fiscal\Mutations;

use App\Enums\FiscalMutationDenialCode;
use App\Enums\FiscalMutationStatus;
use App\Enums\OfficeRole;
use App\Enums\SerproEnvironment;
use App\Models\Client;
use App\Models\FiscalMutationOperation;
use App\Models\Office;
use App\Models\SerproServiceCatalogEntry;
use App\Models\User;
use App\Services\Fiscal\Demo\FiscalDataOriginResolver;
use App\Services\Integra\IntegraEligibilityService;
use App\Services\Integra\TaxProxyPowerService;
use App\Services\Platform\OfficeSubscriptionGate;
use App\Services\Serpro\SerproKillSwitchService;
use App\Services\Serpro\Usage\OperationCatalog;
use App\Services\Serpro\Usage\PriceCalculator;
use App\Services\Serpro\Usage\UsageBudgetGate;
use App\Support\FeatureFlags;

/**
 * Policy comum de operação mutante (13.1):
 * papel, 2FA recente, procuração, plano, feature flag, custo e kill switch.
 */
final class FiscalMutationPolicy
{
    public function __construct(
        private readonly RecentTwoFactorGate $totp,
        private readonly OfficeSubscriptionGate $subscriptionGate,
        private readonly IntegraEligibilityService $eligibility,
        private readonly TaxProxyPowerService $proxyPowers,
        private readonly SerproKillSwitchService $serproKillSwitch,
        private readonly OperationCatalog $operationCatalog,
        private readonly PriceCalculator $prices,
        private readonly UsageBudgetGate $budget,
        private readonly FiscalDataOriginResolver $dataOrigin,
    ) {}

    /**
     * @param  array{
     *     require_totp?: bool,
     *     require_confirmation?: bool,
     *     confirmed?: bool,
     *     skip_anti_repeat?: bool,
     *     skip_uncertain_check?: bool,
     *     exclude_operation_id?: int|null,
     * }  $options
     */
    public function evaluate(
        Office $office,
        Client $client,
        User $user,
        string $solutionCode,
        string $serviceCode,
        string $operationCode,
        SerproEnvironment $environment,
        ?string $competencePeriodKey = null,
        ?string $module = null,
        array $options = [],
    ): MutationPolicyResult {
        $codes = [];
        $module = $module ?? FiscalMutationCohort::moduleForSolution($solutionCode);
        $excludeOperationId = isset($options['exclude_operation_id'])
            ? (int) $options['exclude_operation_id']
            : null;
        $context = [
            'office_id' => $office->id,
            'client_id' => $client->id,
            'solution' => strtoupper($solutionCode),
            'service' => strtoupper($serviceCode),
            'operation' => strtoupper($operationCode),
            'module' => $module,
            'environment' => $environment->value,
            'competence' => $competencePeriodKey,
            'exclude_operation_id' => $excludeOperationId,
        ];

        // 0. Modo demonstração / office demo — bloqueio explícito sem sucesso fiscal fictício
        if ($this->dataOrigin->isDemoOfficeContext($office)) {
            $codes[] = FiscalMutationDenialCode::DemoMode;
            $context['demo_mode'] = true;
            $context['read_only'] = true;
        }

        // 1. Kill switches
        if (
            FeatureFlags::isKillSwitchActive()
            || (bool) config('features.mutating.kill_switch', false)
            || (bool) config('fiscal_mutations.kill_switch', false)
            || $this->serproKillSwitch->isSolutionBlocked($solutionCode)
        ) {
            $codes[] = FiscalMutationDenialCode::KillSwitch;
        }

        // 2. Feature flags (global + mutações + módulo)
        if (! FeatureFlags::isGloballyEnabled() && ! (bool) config('fiscal_mutations.enabled', false)) {
            $codes[] = FiscalMutationDenialCode::FeatureDisabled;
        }

        if (! FeatureFlags::isMutatingEnabled('mutacoes', $office->id)
            && ! FeatureFlags::isMutatingEnabled($module, $office->id)
        ) {
            $codes[] = FiscalMutationDenialCode::MutatingDisabled;
        }

        if (! FiscalMutationCohort::isOperationEnabled($solutionCode, $serviceCode, $operationCode, $office->id)) {
            $codes[] = FiscalMutationDenialCode::OperationCohortDisabled;
        }

        // 3. Papel — mutações fiscais: somente ADMIN
        $role = $user->roleIn($office);
        if ($role !== OfficeRole::Admin) {
            $codes[] = FiscalMutationDenialCode::RoleForbidden;
        }

        // 4. 2FA recente
        $requireTotp = $options['require_totp'] ?? true;
        if ($requireTotp) {
            if (! $user->hasConfirmedTwoFactor() && config('fortify.two_factor_required', true)) {
                $codes[] = FiscalMutationDenialCode::TotpRequired;
            } elseif (! $this->totp->isRecentlyConfirmed($user)) {
                $codes[] = FiscalMutationDenialCode::TotpExpired;
            }
            $context['totp_seconds_remaining'] = $this->totp->secondsRemaining();
        }

        // 5. Plano / assinatura
        if (! $this->subscriptionGate->allowsMutations($office)
            || ! $this->subscriptionGate->allowsExternalCalls($office)
        ) {
            $codes[] = FiscalMutationDenialCode::SubscriptionBlocked;
        }

        // 6. Tenant isolation
        if ((int) $client->office_id !== (int) $office->id) {
            $codes[] = FiscalMutationDenialCode::ContributorCrossTenant;
        }

        // 7. Catálogo
        $catalog = SerproServiceCatalogEntry::query()
            ->where('environment', $environment->value)
            ->where('solution_code', strtoupper($solutionCode))
            ->where('service_code', strtoupper($serviceCode))
            ->where('operation_code', strtoupper($operationCode))
            ->orderByDesc('catalog_version')
            ->first();

        if ($catalog === null) {
            $codes[] = FiscalMutationDenialCode::ServiceNotCataloged;
        } else {
            $context['catalog_id'] = $catalog->id;
            $context['catalog_label'] = $catalog->label;
            $context['is_mutating'] = $catalog->is_mutating;
            $context['required_proxy_power'] = $catalog->required_proxy_power;

            if (! $catalog->is_mutating) {
                $codes[] = FiscalMutationDenialCode::CatalogNotMutating;
            }
            if (! $catalog->is_enabled) {
                $codes[] = FiscalMutationDenialCode::CatalogDisabled;
            }

            // 8. Procuração / poder (revalidação pontual)
            $requiredPower = $catalog->required_proxy_power;
            if ($requiredPower !== null && $requiredPower !== '') {
                $auth = \App\Models\OfficeSerproAuthorization::query()
                    ->where('office_id', $office->id)
                    ->where('environment', $environment->value)
                    ->first();
                $authorIdentity = $auth?->author_identity ?? '';
                $power = $authorIdentity !== ''
                    ? $this->proxyPowers->findUsablePower(
                        $office->id,
                        $client->id,
                        $requiredPower,
                        $authorIdentity,
                    )
                    : null;

                if ($power === null) {
                    // Distinguir revogado vs ausente se existir registro
                    $any = \App\Models\TaxProxyPower::query()
                        ->where('office_id', $office->id)
                        ->where('client_id', $client->id)
                        ->where('power_code', strtoupper($requiredPower))
                        ->orderByDesc('id')
                        ->first();

                    if ($any !== null && ! $any->isCurrentlyValid()) {
                        $codes[] = FiscalMutationDenialCode::ProxyPowerRevoked;
                    } else {
                        $codes[] = FiscalMutationDenialCode::ProxyPowerMissing;
                    }
                } else {
                    $context['proxy_power_id'] = $power->id;
                }
            }
        }

        // 9. Elegibilidade Integra (contrato, termo, token, breaker…)
        $elig = $this->eligibility->evaluate(
            office: $office,
            client: $client,
            solutionCode: strtoupper($solutionCode),
            serviceCode: strtoupper($serviceCode),
            operationCode: strtoupper($operationCode),
            environment: $environment,
            user: $user,
            module: $module,
        );
        $context['eligibility'] = $elig->toArray();
        if (! $elig->eligible) {
            // Já cobertos acima em parte; agrega como EligibilityBlocked se ainda elegível parcialmente
            $already = array_map(fn (FiscalMutationDenialCode $c) => $c->value, $codes);
            $mapped = false;
            foreach ($elig->codes as $ec) {
                $map = match ($ec->value) {
                    'KILL_SWITCH' => FiscalMutationDenialCode::KillSwitch,
                    'FEATURE_DISABLED' => FiscalMutationDenialCode::FeatureDisabled,
                    'MUTATING_DISABLED' => FiscalMutationDenialCode::MutatingDisabled,
                    'SUBSCRIPTION_BLOCKED' => FiscalMutationDenialCode::SubscriptionBlocked,
                    'PROXY_POWER_MISSING', 'PROXY_POWER_INSUFFICIENT', 'PROXY_POWER_EXPIRED' => FiscalMutationDenialCode::ProxyPowerMissing,
                    'ROLE_FORBIDDEN' => FiscalMutationDenialCode::RoleForbidden,
                    'BUDGET_EXCEEDED' => FiscalMutationDenialCode::BudgetExceeded,
                    'SERVICE_NOT_CATALOGED' => FiscalMutationDenialCode::ServiceNotCataloged,
                    'CONTRIBUTOR_CROSS_TENANT' => FiscalMutationDenialCode::ContributorCrossTenant,
                    default => FiscalMutationDenialCode::EligibilityBlocked,
                };
                if (! in_array($map->value, $already, true)) {
                    $codes[] = $map;
                    $already[] = $map->value;
                    $mapped = true;
                }
            }
            if (! $mapped && $codes === []) {
                $codes[] = FiscalMutationDenialCode::EligibilityBlocked;
            }
        }

        // 10. Custo / franquia
        $classified = $this->operationCatalog->classify(
            strtoupper($solutionCode),
            strtoupper($serviceCode),
            strtoupper($operationCode),
        );
        $class = $classified['class'];
        $estimate = $this->prices->estimate(
            class: $class,
            quantity: 1,
            systemCode: strtoupper($solutionCode),
            serviceCode: strtoupper($serviceCode),
            operationCode: strtoupper($operationCode),
        );
        $budgetEval = $this->budget->evaluate(
            officeId: $office->id,
            class: $class,
            quantity: 1,
            isEssential: (bool) $classified['is_essential'],
        );
        $context['cost_estimate'] = $estimate;
        $context['budget'] = [
            'allowed' => $budgetEval['allowed'],
            'would_block' => $budgetEval['would_block'],
            'block_reason' => $budgetEval['block_reason'],
            'remaining' => $budgetEval['remaining'],
        ];

        if (! $budgetEval['allowed']) {
            $codes[] = FiscalMutationDenialCode::BudgetExceeded;
        }

        // 11. Resultado incerto aberto / anti-repetição
        // exclude_operation_id: ignora self-match (execute da própria PENDING); ainda bloqueia outras.
        if (! ($options['skip_uncertain_check'] ?? false)) {
            $openUncertainQ = FiscalMutationOperation::query()
                ->withoutGlobalScopes()
                ->where('office_id', $office->id)
                ->where('client_id', $client->id)
                ->where('solution_code', strtoupper($solutionCode))
                ->where('service_code', strtoupper($serviceCode))
                ->where('operation_code', strtoupper($operationCode))
                ->where(function ($q) use ($competencePeriodKey): void {
                    if ($competencePeriodKey === null) {
                        $q->whereNull('competence_period_key');
                    } else {
                        $q->where('competence_period_key', $competencePeriodKey);
                    }
                })
                ->whereIn('status', [
                    FiscalMutationStatus::Sent->value,
                    FiscalMutationStatus::UnknownResult->value,
                    FiscalMutationStatus::Reconciling->value,
                ]);

            if ($excludeOperationId !== null && $excludeOperationId > 0) {
                $openUncertainQ->where('id', '!=', $excludeOperationId);
            }

            if ($openUncertainQ->exists()) {
                $codes[] = FiscalMutationDenialCode::UncertainResultOpen;
            }
        }

        if (! ($options['skip_anti_repeat'] ?? false)) {
            $window = max(1, (int) config('fiscal_mutations.anti_repeat_window_seconds', 300));
            $recentQ = FiscalMutationOperation::query()
                ->withoutGlobalScopes()
                ->where('office_id', $office->id)
                ->where('client_id', $client->id)
                ->where('solution_code', strtoupper($solutionCode))
                ->where('service_code', strtoupper($serviceCode))
                ->where('operation_code', strtoupper($operationCode))
                ->where(function ($q) use ($competencePeriodKey): void {
                    if ($competencePeriodKey === null) {
                        $q->whereNull('competence_period_key');
                    } else {
                        $q->where('competence_period_key', $competencePeriodKey);
                    }
                })
                ->where('created_at', '>=', now()->subSeconds($window))
                ->where(function ($q): void {
                    // PENDING só conta se preflight foi elegível (sem denial)
                    $q->where(function ($q2): void {
                        $q2->where('status', FiscalMutationStatus::Pending->value)
                            ->whereNull('denial_code');
                    })->orWhereIn('status', [
                        FiscalMutationStatus::Sent->value,
                        FiscalMutationStatus::Confirmed->value,
                        FiscalMutationStatus::UnknownResult->value,
                        FiscalMutationStatus::Reconciling->value,
                    ]);
                });

            if ($excludeOperationId !== null && $excludeOperationId > 0) {
                $recentQ->where('id', '!=', $excludeOperationId);
            }

            if ($recentQ->exists()) {
                $codes[] = FiscalMutationDenialCode::AntiRepeatWindow;
            }
        }

        // 12. Confirmação explícita (só na execução)
        if (($options['require_confirmation'] ?? false) && ! ($options['confirmed'] ?? false)) {
            $codes[] = FiscalMutationDenialCode::ConfirmationRequired;
        }

        $codes = array_values(array_unique($codes, SORT_REGULAR));

        if ($codes !== []) {
            return MutationPolicyResult::deny($codes, $context);
        }

        return MutationPolicyResult::allow($context, confirmationRequired: true);
    }
}

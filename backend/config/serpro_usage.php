<?php

/**
 * Ledger de consumo SERPRO / Integra Contador.
 *
 * Defaults seguros: shadow mode ON e bloqueio comercial OFF até conciliação
 * e evidência formal de precificação.
 *
 * @see openspec/changes/build-complete-fiscal-monitoring-hub/specs/serpro-api-usage-ledger
 * @see App\Services\Serpro\Usage\UsageLedgerService
 */

return [
    /**
     * Shadow mode: ledger registra reservas/entradas, mas NÃO bloqueia
     * operações por franquia/orçamento (trial e piloto).
     */
    'shadow_mode' => filter_var(env('SERPRO_USAGE_SHADOW_MODE', true), FILTER_VALIDATE_BOOL),

    /**
     * Bloqueio comercial de operações não essenciais quando franquia/limite estoura.
     * Só deve ser true após conciliação e política de plano aprovadas.
     * Shadow mode vence: se shadow=true, blocking fica efetivamente off.
     */
    'commercial_blocking_enabled' => filter_var(
        env('SERPRO_USAGE_COMMERCIAL_BLOCKING', false),
        FILTER_VALIDATE_BOOL
    ),

    /**
     * Limiar de alerta de franquia do tenant (0–1). Ex.: 0.8 = 80% da franquia.
     */
    'franchise_alert_threshold' => (float) env('SERPRO_USAGE_FRANCHISE_ALERT_THRESHOLD', 0.8),

    /**
     * Orçamento global mensal da plataforma (unidades faturáveis).
     * null = sem teto global (apenas franquia por tenant).
     */
    'global_monthly_budget' => ($v = env('SERPRO_USAGE_GLOBAL_MONTHLY_BUDGET')) !== null && $v !== ''
        ? (int) $v
        : null,

    /**
     * Fração máxima do orçamento global que um único tenant pode consumir (0–1).
     * Proteção contra tenant ruidoso. null = sem proteção de share.
     */
    'max_tenant_share_of_global' => ($v = env('SERPRO_USAGE_MAX_TENANT_SHARE')) !== null && $v !== ''
        ? (float) $v
        : 0.40,

    /**
     * Moeda de estimativa (apenas metadado; valores em micros).
     */
    'currency' => env('SERPRO_USAGE_CURRENCY', 'BRL'),

    /**
     * Alertar (audit + log) quando classe DESCONHECIDA for classificada.
     */
    'alert_unknown_class' => filter_var(env('SERPRO_USAGE_ALERT_UNKNOWN', true), FILTER_VALIDATE_BOOL),

    /**
     * Fail-open vs fail-closed quando catálogo/preço indisponível em modo não-shadow.
     * Default true (fail-open com DESCONHECIDA) para não parar trial.
     */
    'fail_open_on_unknown' => filter_var(env('SERPRO_USAGE_FAIL_OPEN_UNKNOWN', true), FILTER_VALIDATE_BOOL),
];

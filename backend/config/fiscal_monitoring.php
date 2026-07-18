<?php

/**
 * Núcleo de monitoramento fiscal (fiscal-monitoring-core).
 * Defaults seguros: mutações off; limites modestos; retenção configurável.
 */
return [
    /**
     * Master switch do núcleo (além de features.global_enabled).
     * Módulos filhos (simples_mei, sitfis, …) usam FeatureFlags por módulo.
     */
    'enabled' => filter_var(env('FISCAL_MONITORING_ENABLED', false), FILTER_VALIDATE_BOOL),

    /** Kill switch exclusivo do núcleo (não substitui FEATURES_KILL_SWITCH). */
    'kill_switch' => filter_var(env('FISCAL_MONITORING_KILL_SWITCH', false), FILTER_VALIDATE_BOOL),

    /** Mutações fiscais desabilitadas por padrão no núcleo. */
    'mutating_enabled' => filter_var(env('FISCAL_MONITORING_MUTATING_ENABLED', false), FILTER_VALIDATE_BOOL),

    /**
     * Lease (minutos) para attempt DCTFWeb/MIT em SENT durante call upstream.
     * Após o lease, SENT deixa de bloquear nova tentativa (crash recovery).
     * UNCERTAIN continua com blocked_retry_until.
     */
    'mutation_inflight_lease_minutes' => (int) env('FISCAL_MONITORING_MUTATION_INFLIGHT_LEASE_MINUTES', 30),

    'scheduler' => [
        'enabled' => filter_var(env('FISCAL_MONITORING_SCHEDULER_ENABLED', false), FILTER_VALIDATE_BOOL),
        /** Máximo de jobs enfileirados por tick do scheduler. */
        'max_dispatch_per_tick' => (int) env('FISCAL_MONITORING_MAX_DISPATCH_PER_TICK', 40),
        /** Concorrência global (locks) no contrato. */
        'global_concurrent_limit' => (int) env('FISCAL_MONITORING_GLOBAL_CONCURRENT', 8),
        /** Concorrência por tenant. */
        'tenant_concurrent_limit' => (int) env('FISCAL_MONITORING_TENANT_CONCURRENT', 2),
        /** Espalhamento em minutos na hora (0–59). */
        'spread_minutes' => 60,
        /** Intervalo padrão entre execuções agendadas (minutos). */
        'default_interval_minutes' => (int) env('FISCAL_MONITORING_DEFAULT_INTERVAL_MINUTES', 60),
        /**
         * Política mensal office+monitor (dia 1–28) — default OFF até rollout.
         * Quando true, cria itens comerciais scheduled e despacha spillover via Horizon.
         */
        'commercial_monthly_enabled' => filter_var(
            env('FISCAL_MONITORING_COMMERCIAL_MONTHLY_ENABLED', false),
            FILTER_VALIDATE_BOOL
        ),
    ],

    /**
     * Franquia comercial de monitores SERPRO (ledger separado do UsageBudgetGate).
     * Default OFF: run service não debita; testes e rollout habilitam explicitamente.
     */
    'commercial' => [
        'enabled' => filter_var(env('FISCAL_MONITORING_COMMERCIAL_ENABLED', false), FILTER_VALIDATE_BOOL),
        'min_interval_seconds' => (int) env('FISCAL_MONITORING_COMMERCIAL_MIN_INTERVAL', 86_400),
        /** Enforce intervalo mínimo oficial mesmo após confirmação manual. */
        'enforce_min_interval' => filter_var(
            env('FISCAL_MONITORING_COMMERCIAL_ENFORCE_MIN_INTERVAL', true),
            FILTER_VALIDATE_BOOL
        ),
    ],

    'job' => [
        'timeout_seconds' => (int) env('FISCAL_MONITORING_JOB_TIMEOUT', 300),
        'lock_ttl_seconds' => (int) env('FISCAL_MONITORING_LOCK_TTL', 360),
        'max_items_per_run' => (int) env('FISCAL_MONITORING_MAX_ITEMS_PER_RUN', 50),
        'max_pages_per_run' => (int) env('FISCAL_MONITORING_MAX_PAGES_PER_RUN', 20),
        'queue' => env('FISCAL_MONITORING_QUEUE', 'default'),
    ],

    'evidence' => [
        /** Dias de retenção padrão no cofre (metadado; purge opcional). */
        'retention_days' => (int) env('FISCAL_EVIDENCE_RETENTION_DAYS', 2555), // ~7 anos
        'max_bytes' => (int) env('FISCAL_EVIDENCE_MAX_BYTES', 10_485_760), // 10 MiB (PDFs PGDAS-D 14–16)
    ],

    'cache' => [
        /** Prefixo obrigatório — sempre incluir office_id nas chaves reais. */
        'key_prefix' => 'fiscal',
        'ttl_seconds' => (int) env('FISCAL_MONITORING_CACHE_TTL', 300),
    ],

    'rate_limit' => [
        'global_rps' => (float) env('FISCAL_MONITORING_GLOBAL_RPS', 4.0),
        'tenant_rps' => (float) env('FISCAL_MONITORING_TENANT_RPS', 1.0),
    ],

    /**
     * Integra-SITFIS — fluxo assíncrono solicitação/protocolo/espera/emissão.
     * Polling respeitoso: nunca mais agressivo que poll_interval; espera min_wait antes da 1ª emissão.
     */
    'sitfis' => [
        /** Código de domínio do adapter/run (não confundir com idSistema oficial SERPRO). */
        'system_code' => 'INTEGRA_SITFIS',
        'id_sistema' => 'SITFIS',
        'service_code' => 'SITFIS',
        'operation_code' => 'MONITOR',
        'solicit_operation_key' => 'sitfis.solicitar_protocolo',
        'emit_operation_key' => 'sitfis.emitir_relatorio',
        'solicit_operation' => 'SOLICITARPROTOCOLO91',
        'emit_operation' => 'RELATORIOSITFIS92',
        'required_proxy_power' => '00002',
        'versao_sistema' => '2.0',
        /** Espera mínima oficial (s) entre solicitação e primeira tentativa de emissão. */
        'min_wait_seconds' => (int) env('SITFIS_MIN_WAIT_SECONDS', 30),
        /** Intervalo mínimo entre polls de emissão (s). */
        'poll_interval_seconds' => (int) env('SITFIS_POLL_INTERVAL_SECONDS', 60),
        /** Máximo de tentativas de emissão após o min_wait. */
        'max_polls' => (int) env('SITFIS_MAX_POLLS', 20),
        /** TTL do snapshot (s) — 24h; refresh manual reutiliza dentro do TTL. */
        'snapshot_ttl_seconds' => (int) env('SITFIS_SNAPSHOT_TTL_SECONDS', 86400),
        /** Intervalo do schedule diário SITFIS (minutos). */
        'interval_minutes' => (int) env('SITFIS_INTERVAL_MINUTES', 1440),
        'parser_version' => '2.0',
    ],

    /**
     * Caixa Postal / DTE — conteúdo fiscal restrito; triagem interna ≠ leitura oficial.
     */
    'mailbox' => [
        /** Até 50 mensagens por página no contrato SERPRO; limita custo e duração da run. */
        'max_pages_per_sync' => (int) env('MAILBOX_MAX_PAGES_PER_SYNC', 20),
        'retention_days' => (int) env('MAILBOX_RETENTION_DAYS', 2555), // ~7 anos
        'max_body_bytes' => (int) env('MAILBOX_MAX_BODY_BYTES', 2_097_152), // 2 MiB
        'max_attachment_bytes' => (int) env('MAILBOX_MAX_ATTACHMENT_BYTES', 10_485_760), // 10 MiB
        'due_soon_days' => (int) env('MAILBOX_DUE_SOON_DAYS', 7),
        'sensitivity_class' => 'FISCAL_RESTRICTED',
        /** Categorias oficiais tratadas como críticas para alerta. */
        'critical_categories' => ['INTIMACAO', 'NOTIFICACAO', 'COBRANCA', 'URGENTE'],
    ],

    /**
     * Perfil demonstrativo (somente local/testing).
     * Seeder pleno: config/fiscal_demo.php + FiscalMonitoringDemoSeeder.
     * Nunca habilita origem DEMO em production — guard no DataOriginResolver.
     */
    'demo' => [
        'office_slug' => env('FISCAL_DEMO_OFFICE_SLUG', 'demo'),
        'force_simulated' => filter_var(env('FISCAL_DEMO_FORCE_SIMULATED', false), FILTER_VALIDATE_BOOL),
    ],
];

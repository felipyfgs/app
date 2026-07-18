<?php

$configuredEnvironment = strtoupper((string) env('SERPRO_DEFAULT_ENVIRONMENT', 'TRIAL'));
if (! in_array($configuredEnvironment, ['TRIAL', 'PRODUCTION'], true)) {
    throw new InvalidArgumentException(
        'SERPRO_DEFAULT_ENVIRONMENT deve ser TRIAL ou PRODUCTION; HOMOLOGATION não é um ambiente SERPRO suportado.',
    );
}

$defaultEnvironment = $configuredEnvironment;

/**
 * Integra Contador / SERPRO — plano de controle global + transporte.
 * Segredos NUNCA ficam aqui: só no SecureObjectStore + VAULT_MASTER_KEY.
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Ambiente padrão do contrato
    |--------------------------------------------------------------------------
    */
    'default_environment' => $defaultEnvironment,

    /*
    |--------------------------------------------------------------------------
    | Endpoints OAuth / API (sem credenciais)
    |--------------------------------------------------------------------------
    */
    'oauth' => [
        /** Endpoint oficial de autenticação (mTLS + client_credentials + role-type TERCEIROS). */
        'token_url' => env(
            'SERPRO_OAUTH_TOKEN_URL',
            'https://autenticacao.sapi.serpro.gov.br/authenticate',
        ),
        'role_type' => env('SERPRO_OAUTH_ROLE_TYPE', 'TERCEIROS'),
        'timeout_seconds' => (int) env('SERPRO_OAUTH_TIMEOUT_SECONDS', 30),
        'connect_timeout_seconds' => (int) env('SERPRO_OAUTH_CONNECT_TIMEOUT_SECONDS', 10),
        /** Margem (segundos) antes da expiração para renovar o par access_token+jwt_token. */
        'expiry_skew_seconds' => (int) env('SERPRO_OAUTH_EXPIRY_SKEW_SECONDS', 120),
        'lock_seconds' => (int) env('SERPRO_OAUTH_LOCK_SECONDS', 30),
        'lock_wait_seconds' => (int) env('SERPRO_OAUTH_LOCK_WAIT_SECONDS', 20),
        /** Exige jwt_token na resposta OAuth (protocolo oficial). */
        'require_jwt_token' => filter_var(env('SERPRO_OAUTH_REQUIRE_JWT_TOKEN', true), FILTER_VALIDATE_BOOL),
    ],

    'api' => [
        'base_url' => env(
            'SERPRO_INTEGRA_BASE_URL',
            'https://gateway.apiserpro.serpro.gov.br/integra-contador/v1',
        ),
        'timeout_seconds' => (int) env('SERPRO_API_TIMEOUT_SECONDS', 60),
        'connect_timeout_seconds' => (int) env('SERPRO_API_CONNECT_TIMEOUT_SECONDS', 10),
        'verify_tls' => true,
        'min_tls' => '1.2',
    ],

    /*
    |--------------------------------------------------------------------------
    | Endpoints oficiais por ambiente
    |--------------------------------------------------------------------------
    | Trial é o gateway de demonstração publicado no Swagger da SERPRO. O
    | bearer/jwt nunca são embutidos no código: a operação só pode usá-los
    | quando forem fornecidos no ambiente. Qualquer ambiente diferente de
    | Trial e Produção é recusado por não ter endpoint público confirmado.
    */
    'environments' => [
        'TRIAL' => [
            'base_url' => env(
                'SERPRO_TRIAL_INTEGRA_BASE_URL',
                'https://gateway.apiserpro.serpro.gov.br/integra-contador-trial/v1',
            ),
            'bearer_token' => env('SERPRO_TRIAL_BEARER_TOKEN', ''),
            'jwt_token' => env('SERPRO_TRIAL_JWT_TOKEN', ''),
        ],
        'PRODUCTION' => [
            'base_url' => env(
                'SERPRO_PRODUCTION_INTEGRA_BASE_URL',
                env('SERPRO_INTEGRA_BASE_URL', 'https://gateway.apiserpro.serpro.gov.br/integra-contador/v1'),
            ),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Drivers por capacidade — default universal fail-closed
    |--------------------------------------------------------------------------
    | Apenas disabled e real são valores operacionais. Configuração legada
    | simulated é rejeitada pelo resolver, sem fallback local.
    */
    'capabilities' => [
        'sitfis' => env('SERPRO_CAPABILITY_SITFIS', 'disabled'),
        'autentica_procurador' => env('SERPRO_CAPABILITY_AUTENTICA_PROCURADOR', 'disabled'),
        'authorization' => env('SERPRO_CAPABILITY_AUTHORIZATION', 'disabled'),
        'mailbox' => env('SERPRO_CAPABILITY_MAILBOX', 'disabled'),
        'dctfweb' => env('SERPRO_CAPABILITY_DCTFWEB', 'disabled'),
        'simples_mei' => env('SERPRO_CAPABILITY_SIMPLES_MEI', 'disabled'),
        'installments' => env('SERPRO_CAPABILITY_INSTALLMENTS', 'disabled'),
        'guides' => env('SERPRO_CAPABILITY_GUIDES', 'disabled'),
        'registrations' => env('SERPRO_CAPABILITY_REGISTRATIONS', 'disabled'),
        'tax_processes' => env('SERPRO_CAPABILITY_TAX_PROCESSES', 'disabled'),
        'default' => env('SERPRO_CAPABILITY_DEFAULT', 'disabled'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Circuit breaker
    |--------------------------------------------------------------------------
    */
    'circuit_breaker' => [
        'failure_threshold' => (int) env('SERPRO_BREAKER_FAILURE_THRESHOLD', 5),
        'open_seconds' => (int) env('SERPRO_BREAKER_OPEN_SECONDS', 120),
        'half_open_max_probes' => (int) env('SERPRO_BREAKER_HALF_OPEN_PROBES', 1),
    ],

    /*
    |--------------------------------------------------------------------------
    | Kill switch (config + runtime cache)
    |--------------------------------------------------------------------------
    */
    'kill_switch' => filter_var(env('SERPRO_KILL_SWITCH', false), FILTER_VALIDATE_BOOL),

    /** TTL (minutos) da evidência de test-connection OAuth para cutover. */
    'credential_connection_test_ttl_minutes' => (int) env('SERPRO_CREDENTIAL_CONNECTION_TEST_TTL_MINUTES', 15),
    'solution_kill_switches' => [
        // 'INTEGRA_SN' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Retenção / offboarding (dias) — ledger e auditoria preservados
    |--------------------------------------------------------------------------
    */
    'retention' => [
        'pfx_days' => (int) env('SERPRO_RETENTION_PFX_DAYS', 2555),
        'token_days' => (int) env('SERPRO_RETENTION_TOKEN_DAYS', 0),
        'termo_days' => (int) env('SERPRO_RETENTION_TERMO_DAYS', 2555),
        'power_days' => (int) env('SERPRO_RETENTION_POWER_DAYS', 2555),
        'evidence_days' => (int) env('SERPRO_RETENTION_EVIDENCE_DAYS', 2555),
        'ledger_days' => (int) env('SERPRO_RETENTION_LEDGER_DAYS', 2555),
    ],

    /*
    |--------------------------------------------------------------------------
    | Reapresentação do Termo após expirar token do procurador
    |--------------------------------------------------------------------------
    | PENDING_VALIDATION | REUSE_STORED_TERM | REQUIRE_NEW_SIGNATURE
    */
    'term_representation' => [
        'TRIAL' => env('SERPRO_TERM_REPRESENTATION_TRIAL', 'PENDING_VALIDATION'),
        'PRODUCTION' => env('SERPRO_TERM_REPRESENTATION_PRODUCTION', 'PENDING_VALIDATION'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Destinatário esperado do Termo (CNPJ software house contratante)
    |--------------------------------------------------------------------------
    | Deve coincidir com o CNPJ do contrato ativo no ambiente.
    */
    /*
    |--------------------------------------------------------------------------
    | PFX do contratante (e-CNPJ A1)
    |--------------------------------------------------------------------------
    */
    'contractor_pfx' => [
        /** Dias mínimos de validade residual para promoção/cutover. */
        'min_horizon_days' => (int) env('SERPRO_CONTRACTOR_PFX_MIN_HORIZON_DAYS', 7),
        /** Exigir extracerts no PFX (cadeia). */
        'require_chain' => filter_var(env('SERPRO_CONTRACTOR_PFX_REQUIRE_CHAIN', false), FILTER_VALIDATE_BOOL),
        /**
         * Legado: contagem de olhos para cutover. Cutover usa OWNER_CONFIRMATION
         * vinculada (SerproRolloutApproval); este valor não autoriza cutover sozinho.
         */
        'cutover_approvals_required' => (int) env('SERPRO_CREDENTIAL_CUTOVER_APPROVALS', 1),
    ],

    /*
    |--------------------------------------------------------------------------
    | Confirmação do proprietário único (OWNER_CONFIRMATION)
    |--------------------------------------------------------------------------
    */
    'owner_confirmation' => [
        /** Janela máxima (horas) entre change_window_start e change_window_end. */
        'max_window_hours' => (int) env('SERPRO_OWNER_CONFIRMATION_MAX_WINDOW_HOURS', 48),
        /**
         * Se true, CONTRACT_ACTIVATE exige OWNER também em TRIAL.
         * Default false: somente PRODUCTION (contrato produtivo).
         */
        'require_for_all_environments' => filter_var(
            env('SERPRO_OWNER_CONFIRMATION_ALL_ENVS', false),
            FILTER_VALIDATE_BOOL,
        ),
    ],

    'termo_destination_cnpj' => env('SERPRO_TERMO_DESTINATION_CNPJ', ''),
    'termo_destination_name' => env('SERPRO_TERMO_DESTINATION_NAME', 'CONTRATANTE'),
    'termo_xsd_path' => env(
        'SERPRO_TERMO_XSD_PATH',
        resource_path('serpro/xsd/termo-autorizacao.v1.xsd'),
    ),
    'termo_schema_meta_path' => env(
        'SERPRO_TERMO_SCHEMA_META_PATH',
        resource_path('serpro/xsd/termo-autorizacao.v1.meta.json'),
    ),

    /*
    |--------------------------------------------------------------------------
    | Rate limits contratuais (opt-in)
    |
    | Não há números oficiais universais no catálogo. Zero desabilita o
    | limitador local até que o contrato/operação forneça um limite validado.
    |--------------------------------------------------------------------------
    */
    'rate_limit' => [
        'version' => (string) env('SERPRO_RATE_LIMIT_VERSION', 'v1'),
        'global_per_minute' => (int) env('SERPRO_RATE_LIMIT_GLOBAL_PER_MINUTE', 0),
        'per_office_per_minute' => (int) env('SERPRO_RATE_LIMIT_OFFICE_PER_MINUTE', 0),
        'default_operation_per_minute' => (int) env('SERPRO_RATE_LIMIT_OPERATION_PER_MINUTE', 0),
        /** @var array<string, array{per_minute?: int}> */
        'operations' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Smoke mTLS real (fora de CI)
    |--------------------------------------------------------------------------
    */
    'smoke' => [
        /** Opt-in operacional. Default OFF. NUNCA habilitar em CI. */
        'enabled' => filter_var(env('SERPRO_SMOKE_ENABLED', false), FILTER_VALIDATE_BOOL),
        'status' => env('SERPRO_SMOKE_STATUS', 'PENDING_OPS'),
        /**
         * Frase exigida em --confirm para tls/oauth live.
         * Ver SerproSmokeService::CONFIRM_PHRASE (I_UNDERSTAND_LIVE_SERPRO).
         */
        'confirm_phrase' => env('SERPRO_SMOKE_CONFIRM_PHRASE', 'I_UNDERSTAND_LIVE_SERPRO'),
        /** Live smoke é proibido quando CI=true / GITHUB_ACTIONS (hard block no serviço). */
        'allow_in_ci' => false,
        /**
         * Host padrão do handshake TLS (default = host de serpro.oauth.token_url).
         * Apenas HTTPS; nunca path /Consultar|/Emitir|/Declarar.
         */
        'tls_url' => env('SERPRO_SMOKE_TLS_URL'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Go-live / contenção produtiva
    |--------------------------------------------------------------------------
    | prod_check_strict: em true, serpro:prod-check falha em qualquer issue.
    | allow_real_drivers_in_prod_check: somente para ensaios controlados.
    | official_sources_manifest: registro versionado de fontes oficiais.
    */
    'prod_check_strict' => filter_var(env('SERPRO_PROD_CHECK_STRICT', false), FILTER_VALIDATE_BOOL),
    'allow_real_drivers_in_prod_check' => filter_var(
        env('SERPRO_ALLOW_REAL_DRIVERS_IN_PROD_CHECK', false),
        FILTER_VALIDATE_BOOL
    ),
    'official_sources_manifest' => env(
        'SERPRO_OFFICIAL_SOURCES_MANIFEST',
        resource_path('serpro/official-sources.v2026-07-18.json')
    ),
    'official_source_verification' => [
        /** Único host documental autorizado; não inclui gateway nem autenticação. */
        'allowed_hosts' => ['apicenter.estaleiro.serpro.gov.br'],
        'allowed_path_prefix' => '/documentacao/api-integra-contador/',
        'expected_source_count' => 8,
        'timeout_seconds' => 20,
        'connect_timeout_seconds' => 5,
        'max_response_bytes' => 5 * 1024 * 1024,
    ],

    /*
    | Endpoint OAuth alternativo (Área do Cliente) — BLOQUEADO até gate externo.
    | Nunca usar como token_url de produção.
    */
    'oauth_alternate_blocked' => true,
    'oauth_canonical_host' => 'autenticacao.sapi.serpro.gov.br',
    'oauth_canonical_path' => '/authenticate',

    /*
    |--------------------------------------------------------------------------
    | Matriz de poderes e procurações
    |--------------------------------------------------------------------------
    */
    'power_matrix_manifest' => env(
        'SERPRO_POWER_MATRIX_MANIFEST',
        resource_path('serpro/power-matrix.v2026-07-18.json')
    ),
    'proxy_powers' => [
        /** Idade máxima da evidência de procuração (horas) para elegibilidade. */
        'freshness_max_age_hours' => (int) env('SERPRO_PROXY_FRESHNESS_HOURS', 168),
        /** Free smoke NÃO pode chamar OBTERPROCURACAO41 faturável. */
        'allow_billable_lookup_in_free_smoke' => filter_var(
            env('SERPRO_PROXY_ALLOW_BILLABLE_IN_FREE_SMOKE', false),
            FILTER_VALIDATE_BOOL
        ),
        /**
         * Hash observado da página oficial (se capturado em runtime/ops).
         * Divergência da matriz aprovada ⇒ REVIEW_REQUIRED.
         */
        'observed_source_sha256' => env('SERPRO_PROXY_MATRIX_OBSERVED_SHA256'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Lifecycle / alertas de expiração (somente verificação — sem assinar/mutar)
    |--------------------------------------------------------------------------
    | Janelas em dias antes do vencimento: 90, 60, 30, 15, 7, 1.
    */
    'lifecycle' => [
        'alert_days' => [90, 60, 30, 15, 7, 1],
        'token_renewal_skew_seconds' => (int) env('SERPRO_TOKEN_RENEWAL_SKEW_SECONDS', 300),
        'lock_seconds' => (int) env('SERPRO_LIFECYCLE_LOCK_SECONDS', 120),
        'queue' => env('SERPRO_LIFECYCLE_QUEUE', 'default'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Filas Horizon (devem constar em config/horizon.php supervisors)
    |--------------------------------------------------------------------------
    */
    'queues' => [
        'fiscal' => env('SERPRO_QUEUE_FISCAL', 'fiscal'),
        'default' => env('SERPRO_QUEUE_DEFAULT', 'default'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Jobs assíncronos SERPRO/fiscal (retry, backoff, flags)
    |--------------------------------------------------------------------------
    */
    'jobs' => [
        'tries' => (int) env('SERPRO_JOB_TRIES', 3),
        'timeout_seconds' => (int) env('SERPRO_JOB_TIMEOUT_SECONDS', 300),
        'backoff' => [30, 120, 300],
        /** Capabilities cujo driver precisa estar ≠ disabled no dispatch e no handle. */
        'flag_capabilities' => [
            'RefreshRegistrationLinksJob' => 'registrations',
            'RefreshTaxProcessesJob' => 'tax_processes',
            'SignTermoWithManagedA1Job' => 'autentica_procurador',
            'PollEventosAtualizacaoJob' => 'authorization',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Eventos de Atualização (/Monitorar) — limites versionados oficiais
    |--------------------------------------------------------------------------
    | Fonte: eventos_limites no manifesto official-sources.
    | 429 remoto: não retry até a janela diária (America/Sao_Paulo) reabrir.
    */
    'eventos' => [
        'limits_version' => (string) env('SERPRO_EVENTOS_LIMITS_VERSION', 'v2026-07-16'),
        'pf_per_day' => (int) env('SERPRO_EVENTOS_PF_PER_DAY', 1000),
        'pj_per_day' => (int) env('SERPRO_EVENTOS_PJ_PER_DAY', 1000),
        'contributors_per_batch' => (int) env('SERPRO_EVENTOS_CONTRIBUTORS_PER_BATCH', 1000),
        'timezone' => env('SERPRO_EVENTOS_TZ', 'America/Sao_Paulo'),
        /**
         * Fallback defensivo APENAS quando a resposta de solicitação omite
         * TempoEsperaMedioEmMs. O fluxo NUNCA usa isso no lugar de TempoLimiteEmMin
         * recebido (one-shot / TTL do protocolo).
         */
        'fallback_wait_ms_if_omitted' => (int) env('SERPRO_EVENTOS_FALLBACK_WAIT_MS', 5000),
        'queue' => env('SERPRO_EVENTOS_QUEUE', 'fiscal'),
        'solicit_pf_operation_key' => 'eventosatualizacao.soliceventospf',
        'solicit_pj_operation_key' => 'eventosatualizacao.soliceventospj',
        'obter_pf_operation_key' => 'eventosatualizacao.obtereventospf',
        'obter_pj_operation_key' => 'eventosatualizacao.obtereventospj',
    ],

    /*
    |--------------------------------------------------------------------------
    | Observabilidade / alertas / runbooks (sem PII nos labels)
    |--------------------------------------------------------------------------
    */
    'observability' => [
        'stuck_queue_seconds' => (int) env('SERPRO_STUCK_QUEUE_SECONDS', 900),
        'horizon_snapshot_enabled' => filter_var(
            env('SERPRO_HORIZON_SNAPSHOT_ENABLED', true),
            FILTER_VALIDATE_BOOL
        ),
        'ops_scan_enabled' => filter_var(env('SERPRO_OPS_SCAN_ENABLED', true), FILTER_VALIDATE_BOOL),
        'runbooks' => [
            'credential_compromised' => 'docs/ops/runbooks/serpro-credential-rotation.md',
            'credential_rotation' => 'docs/ops/runbooks/serpro-credential-rotation.md',
            'clean_prod_deploy' => 'docs/ops/runbooks/serpro-clean-prod-deploy.md',
            'smoke' => 'docs/ops/runbooks/serpro-smoke.md',
            'free_smoke_ladder' => 'docs/ops/runbooks/serpro-free-smoke-ladder.md',
            'go_live_rollout' => 'docs/ops/runbooks/serpro-go-live-rollout.md',
            'cert_expiry' => 'docs/ops/runbooks/serpro-incidents.md',
            'termo_rejected' => 'docs/ops/runbooks/serpro-incidents.md',
            'http_401' => 'docs/ops/runbooks/serpro-incidents.md',
            'http_403_billable' => 'docs/ops/runbooks/serpro-incidents.md',
            'http_429' => 'docs/ops/runbooks/serpro-incidents.md',
            'http_5xx' => 'docs/ops/runbooks/serpro-incidents.md',
            'breaker_open' => 'docs/ops/runbooks/serpro-incidents.md',
            'budget_exceeded' => 'docs/ops/runbooks/serpro-incidents.md',
            'queue_stuck' => 'docs/ops/runbooks/serpro-incidents.md',
            'document_drift' => 'docs/ops/runbooks/serpro-incidents.md',
            'backup_restore' => 'docs/ops/runbooks/serpro-incidents.md',
            'catalog_price' => 'docs/ops/runbooks/serpro-incidents.md',
            'kill_switch' => 'docs/ops/runbooks/serpro-kill-switch.md',
            'audit_integrity' => 'docs/ops/runbooks/serpro-audit-integrity.md',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Readiness (offline por default — sem token/fiscal implícito)
    |--------------------------------------------------------------------------
    */
    'readiness' => [
        'default_ttl_hours' => (int) env('SERPRO_READINESS_TTL_HOURS', 24),
        'allow_live' => filter_var(env('SERPRO_READINESS_ALLOW_LIVE', false), FILTER_VALIDATE_BOOL),
    ],
];

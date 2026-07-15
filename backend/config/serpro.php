<?php

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
    'default_environment' => env('SERPRO_DEFAULT_ENVIRONMENT', 'TRIAL'),

    /*
    |--------------------------------------------------------------------------
    | Endpoints OAuth / API (sem credenciais)
    |--------------------------------------------------------------------------
    */
    'oauth' => [
        'token_url' => env(
            'SERPRO_OAUTH_TOKEN_URL',
            'https://gateway.apiserpro.serpro.gov.br/token',
        ),
        'role_type' => env('SERPRO_OAUTH_ROLE_TYPE', 'TERCEIROS'),
        'timeout_seconds' => (int) env('SERPRO_OAUTH_TIMEOUT_SECONDS', 30),
        'connect_timeout_seconds' => (int) env('SERPRO_OAUTH_CONNECT_TIMEOUT_SECONDS', 10),
        /** Margem (segundos) antes da expiração para renovar o token. */
        'expiry_skew_seconds' => (int) env('SERPRO_OAUTH_EXPIRY_SKEW_SECONDS', 120),
        'lock_seconds' => (int) env('SERPRO_OAUTH_LOCK_SECONDS', 30),
        'lock_wait_seconds' => (int) env('SERPRO_OAUTH_LOCK_WAIT_SECONDS', 20),
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
    | Trial / simulação
    |--------------------------------------------------------------------------
    */
    'trial' => [
        'use_fake_clients' => filter_var(env('SERPRO_USE_FAKE_CLIENTS', true), FILTER_VALIDATE_BOOL),
        'mark_simulated' => true,
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
    'solution_kill_switches' => [
        // 'INTEGRA_SN' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Reapresentação do Termo após expirar token do procurador
    |--------------------------------------------------------------------------
    | PENDING_VALIDATION | REUSE_STORED_TERM | REQUIRE_NEW_SIGNATURE
    */
    'term_representation' => [
        'TRIAL' => env('SERPRO_TERM_REPRESENTATION_TRIAL', 'PENDING_VALIDATION'),
        'HOMOLOGATION' => env('SERPRO_TERM_REPRESENTATION_HOMOLOGATION', 'PENDING_VALIDATION'),
        'PRODUCTION' => env('SERPRO_TERM_REPRESENTATION_PRODUCTION', 'PENDING_VALIDATION'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Destinatário esperado do Termo (CNPJ software house contratante)
    |--------------------------------------------------------------------------
    | Deve coincidir com o CNPJ do contrato ativo no ambiente.
    */
    'termo_destination_cnpj' => env('SERPRO_TERMO_DESTINATION_CNPJ', ''),

    /*
    |--------------------------------------------------------------------------
    | Rate limit global (aprox.)
    |--------------------------------------------------------------------------
    */
    'rate_limit' => [
        'global_per_minute' => (int) env('SERPRO_RATE_LIMIT_GLOBAL_PER_MINUTE', 120),
        'per_office_per_minute' => (int) env('SERPRO_RATE_LIMIT_OFFICE_PER_MINUTE', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | Smoke mTLS real (fora de CI)
    |--------------------------------------------------------------------------
    */
    'smoke' => [
        'enabled' => filter_var(env('SERPRO_SMOKE_ENABLED', false), FILTER_VALIDATE_BOOL),
        'status' => env('SERPRO_SMOKE_STATUS', 'PENDING_OPS'),
    ],
];

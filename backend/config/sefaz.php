<?php

/**
 * Captura SEFAZ DistDFe / CT-e — flags default off até smoke.
 *
 * @see openspec/changes/capture-multi-dfe-sefaz
 */
return [
    // Feature flags (default off)
    'distdfe_enabled' => filter_var(env('SEFAZ_DISTDFE_ENABLED', false), FILTER_VALIDATE_BOOL),
    // MD-e manual/conclusiva (UI). Ciência automática usa auto_ciencia_enabled.
    'manifest_enabled' => filter_var(env('SEFAZ_MANIFEST_ENABLED', false), FILTER_VALIDATE_BOOL),
    /**
     * Ciência técnica automática (210210) ao capturar resNFe sem procNFe.
     * Objetivo: desbloquear XML completo na reconsulta DistDFe — NÃO confirma a operação.
     * Default on quando DistDFe está no ar; desligue com SEFAZ_AUTO_CIENCIA_ENABLED=false.
     */
    'auto_ciencia_enabled' => filter_var(
        env('SEFAZ_AUTO_CIENCIA_ENABLED', env('SEFAZ_DISTDFE_ENABLED', false)),
        FILTER_VALIDATE_BOOL
    ),
    /** Espaçamento entre jobs de ciência (rate limit RecepcaoEvento). */
    'auto_ciencia_delay_seconds' => (int) env('SEFAZ_AUTO_CIENCIA_DELAY_SECONDS', 3),
    /** Teto de ciências enfileiradas por página DistDFe processada. */
    'auto_ciencia_max_per_page' => (int) env('SEFAZ_AUTO_CIENCIA_MAX_PER_PAGE', 30),
    'cte_enabled' => filter_var(env('SEFAZ_CTE_ENABLED', false), FILTER_VALIDATE_BOOL),
    // Compatibilidade legada: MDF-e está fora do escopo escritural e não pode ser reativado por env.
    'mdfe_enabled' => false,
    // NFC-e: gap documentado — só habilitar se canal real existir
    'nfce_enabled' => filter_var(env('SEFAZ_NFCE_ENABLED', false), FILTER_VALIDATE_BOOL),

    'environment' => env('SEFAZ_ENVIRONMENT', 'production'), // production | homologation
    // cUFAutor padrão quando o cadastro do estabelecimento não tem UF
    'default_cuf_autor' => env('SEFAZ_DEFAULT_CUF_AUTOR', '35'),

    'timeout_seconds' => (int) env('SEFAZ_TIMEOUT_SECONDS', 60),
    'connect_timeout_seconds' => (int) env('SEFAZ_CONNECT_TIMEOUT_SECONDS', 15),
    'verify_tls' => filter_var(env('SEFAZ_VERIFY_TLS', true), FILTER_VALIDATE_BOOL),

    // Rate limit operacional (NT 2014.002 / práticas de mercado)
    'page_sleep_seconds' => (float) env('SEFAZ_PAGE_SLEEP_SECONDS', 2),
    'quiet_hours_after_empty' => (float) env('SEFAZ_QUIET_HOURS_AFTER_EMPTY', 1),
    'max_pages_per_job' => (int) env('SEFAZ_MAX_PAGES_PER_JOB', 12),
    'decode_failure_threshold' => (int) env('SEFAZ_DECODE_FAILURE_THRESHOLD', 5),
    'job_timeout_seconds' => (int) env('SEFAZ_JOB_TIMEOUT_SECONDS', 900),
    'lock_ttl_seconds' => (int) env('SEFAZ_LOCK_TTL_SECONDS', 960),

    'queues' => [
        'nfe' => env('SEFAZ_QUEUE_NFE', 'sync-sefaz-nfe'),
        'manifest' => env('SEFAZ_QUEUE_MANIFEST', 'manifest-nfe'),
        'cte' => env('SEFAZ_QUEUE_CTE', 'sync-sefaz-cte'),
    ],

    // URLs Ambiente Nacional (confirmar na relação oficial de WS)
    'nfe' => [
        'production' => env(
            'SEFAZ_NFE_DISTDFE_URL',
            'https://www1.nfe.fazenda.gov.br/NFeDistribuicaoDFe/NFeDistribuicaoDFe.asmx'
        ),
        'homologation' => env(
            'SEFAZ_NFE_DISTDFE_URL_HOM',
            'https://hom.nfe.fazenda.gov.br/NFeDistribuicaoDFe/NFeDistribuicaoDFe.asmx'
        ),
        'soap_action' => 'http://www.portalfiscal.inf.br/nfe/wsdl/NFeDistribuicaoDFe/nfeDistDFeInteresse',
        'namespace' => 'http://www.portalfiscal.inf.br/nfe/wsdl/NFeDistribuicaoDFe',
        'layout_version' => '1.01',
    ],

    'cte' => [
        'production' => env(
            'SEFAZ_CTE_DISTDFE_URL',
            'https://www1.cte.fazenda.gov.br/CTeDistribuicaoDFe/CTeDistribuicaoDFe.asmx'
        ),
        'homologation' => env(
            'SEFAZ_CTE_DISTDFE_URL_HOM',
            'https://hom1.cte.fazenda.gov.br/CTeDistribuicaoDFe/CTeDistribuicaoDFe.asmx'
        ),
        'soap_action' => env(
            'SEFAZ_CTE_SOAP_ACTION',
            'http://www.portalfiscal.inf.br/cte/wsdl/CTeDistribuicaoDFe/cteDistDFeInteresse'
        ),
        'namespace' => env(
            'SEFAZ_CTE_NAMESPACE',
            'http://www.portalfiscal.inf.br/cte/wsdl/CTeDistribuicaoDFe'
        ),
        'layout_version' => env('SEFAZ_CTE_LAYOUT_VERSION', '1.00'),
    ],

    'mdfe' => [
        'production' => env(
            'SEFAZ_MDFE_DISTDFE_URL',
            'https://mdfe.svrs.rs.gov.br/ws/MDFeDistribuicaoDFe/MDFeDistribuicaoDFe.asmx'
        ),
        'homologation' => env(
            'SEFAZ_MDFE_DISTDFE_URL_HOM',
            'https://mdfe-homologacao.svrs.rs.gov.br/ws/MDFeDistribuicaoDFe/MDFeDistribuicaoDFe.asmx'
        ),
        'soap_action' => env(
            'SEFAZ_MDFE_SOAP_ACTION',
            'http://www.portalfiscal.inf.br/mdfe/wsdl/MDFeDistribuicaoDFe/mdfeDistDFeInteresse'
        ),
        'namespace' => env(
            'SEFAZ_MDFE_NAMESPACE',
            'http://www.portalfiscal.inf.br/mdfe/wsdl/MDFeDistribuicaoDFe'
        ),
        'layout_version' => env('SEFAZ_MDFE_LAYOUT_VERSION', '1.00'),
    ],

    'manifest' => [
        'production' => env(
            'SEFAZ_NFE_EVENTO_URL',
            'https://www.nfe.fazenda.gov.br/NFeRecepcaoEvento4/NFeRecepcaoEvento4.asmx'
        ),
        'homologation' => env(
            'SEFAZ_NFE_EVENTO_URL_HOM',
            'https://hom.nfe.fazenda.gov.br/NFeRecepcaoEvento4/NFeRecepcaoEvento4.asmx'
        ),
        'soap_action' => env(
            'SEFAZ_NFE_EVENTO_SOAP_ACTION',
            'http://www.portalfiscal.inf.br/nfe/wsdl/NFeRecepcaoEvento4/nfeRecepcaoEvento'
        ),
        'namespace' => env(
            'SEFAZ_NFE_EVENTO_NS',
            'http://www.portalfiscal.inf.br/nfe/wsdl/NFeRecepcaoEvento4'
        ),
    ],

    /**
     * Captura de saídas MA (NF-e 55 / NFC-e 65) — flags default off até gates G1–G5.
     *
     * @see openspec/changes/build-ma-outbound-nfe-nfce-capture
     * @see docs/ops/ma-outbound-g0-decision.md
     */
    'ma_outbound' => [
        // Feature flags (todas false por padrão — G0)
        'enabled' => filter_var(env('SEFAZ_MA_OUTBOUND_ENABLED', false), FILTER_VALIDATE_BOOL),
        'protocol_query_enabled' => filter_var(env('SEFAZ_MA_PROTOCOL_QUERY_ENABLED', false), FILTER_VALIDATE_BOOL),
        'm2m_retrieval_enabled' => filter_var(env('SEFAZ_MA_M2M_RETRIEVAL_ENABLED', false), FILTER_VALIDATE_BOOL),
        'mutating_probe_enabled' => filter_var(env('SEFAZ_MA_MUTATING_PROBE_ENABLED', false), FILTER_VALIDATE_BOOL),

        // Kill switch operacional (não apaga estado/XML)
        'kill_switch' => filter_var(env('SEFAZ_MA_OUTBOUND_KILL_SWITCH', false), FILTER_VALIDATE_BOOL),

        // UF e cUF do piloto
        'uf' => 'MA',
        'cuf' => '21',

        // Limites conservadores (não ampliar sem nova validação operacional)
        'global_rps' => (float) env('SEFAZ_MA_OUTBOUND_RPS', 1),
        'max_numbers_per_run' => (int) env('SEFAZ_MA_OUTBOUND_MAX_NUMBERS_PER_RUN', 10),
        'retry_interval_hours' => (int) env('SEFAZ_MA_OUTBOUND_RETRY_HOURS', 12),
        'max_attempts_per_number' => (int) env('SEFAZ_MA_OUTBOUND_MAX_ATTEMPTS', 10),
        'seed_max_age_days' => (int) env('SEFAZ_MA_OUTBOUND_SEED_MAX_AGE_DAYS', 60),
        'lock_ttl_seconds' => (int) env('SEFAZ_MA_OUTBOUND_LOCK_TTL', 960),
        'job_timeout_seconds' => (int) env('SEFAZ_MA_OUTBOUND_JOB_TIMEOUT', 900),

        'queue' => env('SEFAZ_MA_OUTBOUND_QUEUE', 'capture-outbound-ma'),

        // Schemas / NT de referência (versionados — não inventar leiaute)
        'schemas' => [
            'consulta_protocolo' => 'NFeConsultaProtocolo4',
            'moc_cstat_562' => '562',
            'moc_cstat_539' => '539',
            'nfe_layout' => '4.00',
            'alphanumeric_key_nt' => 'NT2025.001', // CNPJ/chave alfanuméricos — leiaute vigente
        ],

        /**
         * Endpoints oficiais de consulta de protocolo por modelo e ambiente.
         * Modelo 55/MA → SVAN; modelo 65/MA → SVRS.
         * URLs padrão alinhadas à relação de Web Services do portal NF-e / SVRS.
         */
        'consulta_protocolo' => [
            '55' => [
                'authorizer' => 'SVAN',
                'production' => env(
                    'SEFAZ_MA_NFE55_CONSULTA_URL',
                    'https://www.sefazvirtual.fazenda.gov.br/NFeConsultaProtocolo4/NFeConsultaProtocolo4.asmx'
                ),
                'homologation' => env(
                    'SEFAZ_MA_NFE55_CONSULTA_URL_HOM',
                    'https://hom.sefazvirtual.fazenda.gov.br/NFeConsultaProtocolo4/NFeConsultaProtocolo4.asmx'
                ),
                'soap_action' => env(
                    'SEFAZ_MA_NFE55_CONSULTA_SOAP_ACTION',
                    'http://www.portalfiscal.inf.br/nfe/wsdl/NFeConsultaProtocolo4/nfeConsultaNF'
                ),
                'namespace' => env(
                    'SEFAZ_MA_NFE55_CONSULTA_NS',
                    'http://www.portalfiscal.inf.br/nfe/wsdl/NFeConsultaProtocolo4'
                ),
            ],
            '65' => [
                'authorizer' => 'SVRS',
                'production' => env(
                    'SEFAZ_MA_NFCE65_CONSULTA_URL',
                    'https://nfce.svrs.rs.gov.br/ws/NfeConsulta/NfeConsulta4.asmx'
                ),
                'homologation' => env(
                    'SEFAZ_MA_NFCE65_CONSULTA_URL_HOM',
                    'https://nfce-homologacao.svrs.rs.gov.br/ws/NfeConsulta/NfeConsulta4.asmx'
                ),
                'soap_action' => env(
                    'SEFAZ_MA_NFCE65_CONSULTA_SOAP_ACTION',
                    'http://www.portalfiscal.inf.br/nfe/wsdl/NFeConsultaProtocolo4/nfeConsultaNF'
                ),
                'namespace' => env(
                    'SEFAZ_MA_NFCE65_CONSULTA_NS',
                    'http://www.portalfiscal.inf.br/nfe/wsdl/NFeConsultaProtocolo4'
                ),
            ],
        ],

        // Status M2M: NO_GO até contrato formal da SEFAZ-MA
        'm2m_status' => env('SEFAZ_MA_M2M_STATUS', 'NO_GO_M2M'),
    ],
];

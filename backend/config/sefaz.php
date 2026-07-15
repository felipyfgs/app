<?php

/**
 * Captura SEFAZ DistDFe / CT-e / MDF-e — flags default off até smoke.
 *
 * @see openspec/changes/capture-multi-dfe-sefaz
 */
return [
    // Feature flags (default off)
    'distdfe_enabled' => filter_var(env('SEFAZ_DISTDFE_ENABLED', false), FILTER_VALIDATE_BOOL),
    'manifest_enabled' => filter_var(env('SEFAZ_MANIFEST_ENABLED', false), FILTER_VALIDATE_BOOL),
    'cte_enabled' => filter_var(env('SEFAZ_CTE_ENABLED', false), FILTER_VALIDATE_BOOL),
    'mdfe_enabled' => filter_var(env('SEFAZ_MDFE_ENABLED', false), FILTER_VALIDATE_BOOL),
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
        'mdfe' => env('SEFAZ_QUEUE_MDFE', 'sync-sefaz-mdfe'),
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
];

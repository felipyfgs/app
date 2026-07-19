<?php

/**
 * Monitoramento parcial FGTS via eSocial (tasks 12.x).
 *
 * Cobertura: eventos S-5003, S-5013 e fechamento S-1299 quando disponíveis.
 * Guia, pagamento e pendências do portal FGTS Digital = UNSUPPORTED (sem API pública).
 * Scraping / Gov.br / CAPTCHA / cookie NÃO são fallback.
 */
return [
    /**
     * Não há provider M2M oficial habilitado. Este valor não é configurável por
     * ambiente: qualquer integração futura precisa de change, contrato e bind próprios.
     */
    'runtime_client' => 'disabled',

    /** Kill switch exclusivo do módulo (além de features.modules.fgts). */
    'kill_switch' => filter_var(env('FGTS_ESOCIAL_KILL_SWITCH', false), FILTER_VALIDATE_BOOL),

    /**
     * Janela (horas) após fechamento S-1299 em que a ausência de totalizador
     * ainda é tratada como UNKNOWN (aguardando). Após a janela → ABSENT + finding ATTENTION.
     */
    'totalizer_absence_window_hours' => (int) env('FGTS_ESOCIAL_TOTALIZER_ABSENCE_WINDOW_HOURS', 72),

    'evidence' => [
        'content_type' => 'application/json',
        'source' => 'esocial',
        'source_version' => env('FGTS_ESOCIAL_SOURCE_VERSION', 'unverified-1'),
    ],

    /**
     * Limitações explícitas (texto de produto — API e UI).
     * Nunca omitir: o módulo é parcial por desenho.
     *
     * @var list<string>
     */
    'limitations' => [
        'Cobertura parcial baseada apenas em eventos oficiais do eSocial (S-5003, S-5013, S-1299).',
        'Não há integração com o portal FGTS Digital: guias, pagamentos e pendências do portal não são consultados.',
        'Fechamento eSocial (S-1299) não equivale a recolhimento de FGTS.',
        'Totalizadores indicam base conhecida; não inferem emissão de guia nem quitação.',
        'Ausência de API M2M oficial mantém estados UNKNOWN ou UNSUPPORTED — sem scraping, Gov.br, CAPTCHA ou cookie.',
        'Divergências alertam apenas inconsistências entre eventos eSocial conhecidos, sem declarar débito do portal.',
    ],

    'coverage_label' => 'FGTS (parcial eSocial)',
    'system_code' => 'ESOCIAL',
    'service_code' => 'FGTS',
    'operation_code' => 'MONITOR',
    'module_key' => 'fgts',
];

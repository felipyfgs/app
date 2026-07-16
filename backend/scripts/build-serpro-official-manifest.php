<?php

declare(strict_types=1);

/**
 * Normaliza as evidências capturadas das páginas oficiais do Integra Contador.
 *
 * Uso:
 * php scripts/build-serpro-official-manifest.php \
 *   --rows=/tmp/serpro-official-rows.json \
 *   --docs=/tmp/serpro-doc-metadata.json \
 *   --powers=/tmp/serpro-proxy-powers.json \
 *   --catalog-sha256=<sha> --powers-sha256=<sha> \
 *   --output=resources/serpro/official-service-catalog.v2026-07-16.json
 */
$options = getopt('', [
    'rows:', 'docs:', 'powers:', 'catalog-sha256:', 'powers-sha256:', 'output:',
]);

foreach (['rows', 'docs', 'powers', 'catalog-sha256', 'powers-sha256', 'output'] as $required) {
    if (! isset($options[$required]) || trim((string) $options[$required]) === '') {
        fwrite(STDERR, "Parâmetro obrigatório ausente: --{$required}\n");
        exit(2);
    }
}

/** @return array<mixed> */
$readJson = static function (string $path): array {
    $raw = file_get_contents($path);
    if ($raw === false) {
        throw new RuntimeException("Falha ao ler {$path}");
    }

    $decoded = json_decode($raw, true, flags: JSON_THROW_ON_ERROR);
    if (! is_array($decoded)) {
        throw new RuntimeException("JSON inválido em {$path}");
    }

    return $decoded;
};

$rows = $readJson((string) $options['rows']);
$docs = $readJson((string) $options['docs']);
$powers = $readJson((string) $options['powers']);

$catalogUrl = 'https://apicenter.estaleiro.serpro.gov.br/documentacao/api-integra-contador/pt/catalogo_de_servicos/';
$powersUrl = 'https://apicenter.estaleiro.serpro.gov.br/documentacao/api-integra-contador/pt/servicos_vs_procuracoes/';

$stateMap = [
    'Em produção' => 'PRODUCTION',
    'Em prospecção' => 'PROSPECTION',
    'Em construção' => 'UNDER_CONSTRUCTION',
    'Cancelado' => 'CANCELED',
];

$specialKeys = [
    'ENVIOXMLASSINADO81' => 'autentica_procurador.envio_xml_assinado',
    'SOLICITARPROTOCOLO91' => 'sitfis.solicitar_protocolo',
    'RELATORIOSITFIS92' => 'sitfis.emitir_relatorio',
    'OBTERPROCURACAO41' => 'procuracoes.obter',
    'MSGCONTRIBUINTE61' => 'caixa_postal.lista',
    'MSGDETALHAMENTO62' => 'caixa_postal.detalhe',
    'INNOVAMSG63' => 'caixa_postal.indicador',
    'CONSULTASITUACAODTE111' => 'dte.consultar',
    'CONSVINCULOS261' => 'pnr_contador.consultar_vinculos',
    'SOLICRENUNCIA262' => 'pnr_contador.solicitar_renuncia',
    'CONSRENUNCIA263' => 'pnr_contador.consultar_renuncias',
    'COMPRENUNCIA264' => 'pnr_contador.emitir_comprovante',
    'SITSOLICRENUNCIA265' => 'pnr_contador.situacao_renuncia',
    'CONSPROCPORINTER271' => 'eprocesso.consultar_por_interessado',
];

$moduleBySystem = [
    'AUTENTICAPROCURADOR' => 'authorization',
    'PROCURACOES' => 'authorization',
    'EVENTOSATUALIZACAO' => 'authorization',
    'SITFIS' => 'sitfis',
    'CAIXAPOSTAL' => 'mailbox',
    'DTE' => 'mailbox',
    'DCTFWEB' => 'dctfweb',
    'MIT' => 'dctfweb',
    'PGDASD' => 'simples_mei',
    'REGIMEAPURACAO' => 'simples_mei',
    'DEFIS' => 'simples_mei',
    'PGMEI' => 'simples_mei',
    'CCMEI' => 'simples_mei',
    'DASNSIMEI' => 'simples_mei',
    'SICALC' => 'guides',
    'PAGTOWEB' => 'guides',
    'PNRCONTADOR' => 'registrations',
    'EPROCESSO' => 'tax_processes',
];

$powerFallbackBySystem = [
    'PGDASD' => ['00146'],
    'DEFIS' => ['00146'],
    'DCTFWEB' => ['00103'],
    'MIT' => ['00103'],
    'REGIMEAPURACAO' => ['00060'],
    'CAIXAPOSTAL' => ['00006'],
    'DTE' => ['00050'],
    'PAGTOWEB' => ['00004'],
    'SITFIS' => ['00002'],
    'PARCSN' => ['00076', '00188'],
    'PARCSN-ESP' => ['00125'],
    'PERTSN' => ['00149', '10011'],
    'RELPSN' => ['00210', '10036'],
    'PARCMEI' => ['00134'],
    'PARCMEI-ESP' => ['00133'],
    'PERTMEI' => ['00152', '10012'],
    'RELPMEI' => ['00209', '10035'],
    'EPROCESSO' => ['00051'],
];

$entries = [];
foreach ($rows as $row) {
    if (! is_array($row)) {
        throw new RuntimeException('Linha oficial inválida.');
    }

    $system = (string) $row['id_sistema'];
    $service = (string) $row['id_servico'];
    $state = $stateMap[(string) $row['status']] ?? throw new RuntimeException("Estado desconhecido em {$service}");
    $route = (string) $row['route'];
    $doc = is_array($docs[$service] ?? null) ? $docs[$service] : [];
    $power = is_array($powers[$service] ?? null) ? $powers[$service] : [];
    $requiredPowers = array_values(array_unique(array_map(
        'strval',
        $power['required_proxy_powers'] ?? ($powerFallbackBySystem[$system] ?? []),
    )));

    $systemSlug = strtolower(str_replace('-', '_', $system));
    $serviceSlug = strtolower((string) preg_replace('/\d+$/', '', $service));
    $operationKey = $specialKeys[$service] ?? "{$systemSlug}.{$serviceSlug}";

    $versions = array_values(array_filter(array_map('strval', $doc['versions'] ?? [])));
    $version = $versions[0] ?? match ($system) {
        'SICALC' => '2.9',
        'SITFIS', 'EPROCESSO' => '2.0',
        default => $state === 'PRODUCTION' ? '1.0' : 'UNPUBLISHED',
    };

    $isMutating = $route === 'Declarar'
        || $service === 'ATUBENEFICIO23'
        || ($route === 'Emitir' && ! in_array($service, [
            'RELATORIOSITFIS92', 'EMITIRCCMEI121', 'COMPARRECADACAO72', 'COMPRENUNCIA264',
        ], true));

    $billableClass = match ($route) {
        'Apoiar', 'Monitorar' => 'NAO_FATURAVEL',
        'Consultar' => 'CONSULTA',
        'Declarar' => 'DECLARACAO',
        'Emitir' => 'EMISSAO',
        default => 'DESCONHECIDA',
    };

    $proxyRule = (string) ($power['proxy_rule'] ?? ($requiredPowers === []
        ? 'NOT_APPLICABLE'
        : 'REQUIRED_WHEN_REPRESENTING'));
    if ($system === 'EVENTOSATUALIZACAO') {
        $proxyRule = 'EVENT_DEPENDENT';
    }

    $sources = [[
        'url' => $catalogUrl,
        'sha256' => (string) $options['catalog-sha256'],
        'kind' => 'CATALOG',
    ]];
    foreach (($doc['sources'] ?? []) as $source) {
        if (! is_array($source) || empty($source['url']) || empty($source['sha256'])) {
            continue;
        }
        $sources[] = [
            'url' => (string) $source['url'],
            'sha256' => (string) $source['sha256'],
            'kind' => 'OPERATION',
        ];
    }
    $sources[] = [
        'url' => $powersUrl,
        'sha256' => (string) $options['powers-sha256'],
        'kind' => 'PROXY_MATRIX',
    ];

    // Toda operação produtiva é executável pelo gateway genérico tipado por
    // operation_key. O driver da família e os gates de mutação permanecem
    // independentes e default OFF; isso não equivale a smoke produtivo.
    $platformSupport = $state === 'PRODUCTION' ? 'IMPLEMENTED' : 'INVENTORIED';

    $entries[] = [
        'sequence' => (int) $row['seq'],
        'catalog_code' => (string) $row['code'],
        'operation_key' => $operationKey,
        'id_sistema' => $system,
        'id_servico' => $service,
        'versao_sistema' => $version,
        'route' => $route,
        'auth_mode' => $system === 'AUTENTICAPROCURADOR' ? 'CONTRACT_ONLY' : 'PROCURATOR_WHEN_REPRESENTING',
        'proxy_rule' => $proxyRule,
        'required_proxy_power' => $requiredPowers === [] ? null : implode(' ', $requiredPowers),
        'required_proxy_powers' => $requiredPowers,
        'official_state' => $state,
        'platform_support' => $state === 'PRODUCTION' ? $platformSupport : 'INVENTORIED',
        'monitoring_module' => $moduleBySystem[$system] ?? (str_starts_with($system, 'PARC') || str_starts_with($system, 'PERT') || str_starts_with($system, 'RELP') ? 'installments' : 'inventory'),
        'label' => (string) $row['label'],
        'is_mutating' => $isMutating,
        'billable_class' => $billableClass,
        'dados_mode' => $service === 'SOLICITARPROTOCOLO91' ? 'EMPTY' : 'JSON_STRING',
        'async_policy' => match (true) {
            $system === 'SITFIS' => 'PROTOCOL_POLLING',
            $system === 'EVENTOSATUALIZACAO' => 'BATCH_POLLING',
            $service === 'SITSOLICRENUNCIA265', $service === 'SITUACAOENC315' => 'STATUS_POLLING',
            default => 'HTTP_STATUS',
        },
        'request_schema' => [
            'type' => 'object',
            'fields' => array_values($doc['request_fields'] ?? []),
            'documented' => ! empty($doc['sources']),
        ],
        'response_schema' => [
            'type' => 'object',
            'fields' => array_values($doc['response_fields'] ?? []),
            'documented' => ! empty($doc['sources']),
        ],
        'sources' => $sources,
    ];
}

$manifest = [
    'manifest_version' => '2026.07.16.1',
    'source' => 'SERPRO Integra Contador — catálogo e documentação técnica oficial',
    'verified_at' => '2026-07-16',
    'source_snapshots' => [
        ['url' => $catalogUrl, 'sha256' => (string) $options['catalog-sha256']],
        ['url' => $powersUrl, 'sha256' => (string) $options['powers-sha256']],
    ],
    'expected_counts' => [
        'total' => 119,
        'PRODUCTION' => 98,
        'PROSPECTION' => 19,
        'UNDER_CONSTRUCTION' => 1,
        'CANCELED' => 1,
    ],
    'expected_route_counts' => [
        'Apoiar' => 5,
        'Consultar' => 72,
        'Declarar' => 7,
        'Emitir' => 30,
        'Monitorar' => 5,
    ],
    'notes' => 'Snapshot normalizado a partir das páginas oficiais. Operações não produtivas permanecem INVENTORIED e não executáveis.',
    'entries' => $entries,
];

$output = (string) $options['output'];
$directory = dirname($output);
if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
    throw new RuntimeException("Falha ao criar {$directory}");
}

$json = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n";
if (file_put_contents($output, $json) === false) {
    throw new RuntimeException("Falha ao gravar {$output}");
}

fwrite(STDOUT, sprintf("Manifesto gerado: %s (%d operações)\n", $output, count($entries)));

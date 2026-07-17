<?php

declare(strict_types=1);

/**
 * Gera fixtures sintéticas de contrato para todas as operações produtivas.
 * Elas validam codecs/schemas sem alegar que o conteúdo veio de resposta real.
 *
 * Uso:
 * php scripts/build-serpro-contract-fixtures.php \
 *   --manifest=resources/serpro/official-service-catalog.v2026-07-16.json \
 *   --output=resources/serpro/contract-fixtures.v2026-07-16.json
 */
$options = getopt('', ['manifest:', 'output:']);
foreach (['manifest', 'output'] as $required) {
    if (! isset($options[$required]) || trim((string) $options[$required]) === '') {
        fwrite(STDERR, "Parâmetro obrigatório ausente: --{$required}\n");
        exit(2);
    }
}

$manifestRaw = file_get_contents((string) $options['manifest']);
if ($manifestRaw === false) {
    throw new RuntimeException('Manifesto não pôde ser lido.');
}
$manifest = json_decode($manifestRaw, true, flags: JSON_THROW_ON_ERROR);
if (! is_array($manifest) || ! is_array($manifest['entries'] ?? null)) {
    throw new RuntimeException('Manifesto inválido.');
}

$valueFor = static function (string $type, string $field): mixed {
    $normalized = mb_strtolower($type);
    if (str_contains($normalized, 'boolean')) {
        return false;
    }
    if (str_contains($normalized, 'array') || str_contains($normalized, 'lista')) {
        return [];
    }
    if (str_contains($normalized, 'number') || str_contains($normalized, 'numér') || str_contains($normalized, 'integer')) {
        return 1;
    }
    if (str_contains(mb_strtolower($field), 'data')) {
        return '2026-01-01';
    }

    return 'EXEMPLO_SINTETICO';
};

$buildFields = static function (array $schema) use ($valueFor): array {
    $payload = [];
    foreach (($schema['fields'] ?? []) as $field) {
        if (! is_array($field)) {
            continue;
        }
        $name = trim((string) ($field['field'] ?? ''));
        if ($name === '' || in_array($name, ['status', 'mensagens', 'dados'], true)) {
            continue;
        }
        $payload[$name] = $valueFor((string) ($field['type'] ?? 'string'), $name);
    }

    return $payload;
};

$pgdasdOfficialShape = static function (string $operationKey): ?array {
    $pdf = base64_encode("%PDF-1.4\n%%EOF");

    return match ($operationKey) {
        'pgdasd.consdeclaracao' => [
            'request' => ['anoCalendario' => '2026'],
            'response' => [
                'anoCalendario' => 2026,
                'periodos' => [[
                    'periodoApuracao' => 202606,
                    'operacoes' => [],
                ]],
            ],
        ],
        'pgdasd.consultimadecrec' => [
            'request' => ['periodoApuracao' => '202606'],
            'response' => [
                'numeroDeclaracao' => '20260600000000001',
                'recibo' => ['nomeArquivo' => 'recibo.pdf', 'pdf' => $pdf],
                'declaracao' => ['nomeArquivo' => 'declaracao.pdf', 'pdf' => $pdf],
                'maed' => [
                    'nomeArquivoNotificacao' => 'notificacao-maed.pdf',
                    'pdfNotificacao' => $pdf,
                    'nomeArquivoDarf' => 'darf-maed.pdf',
                    'pdfDarf' => $pdf,
                ],
            ],
        ],
        'pgdasd.consdecrec' => [
            'request' => ['numeroDeclaracao' => '20260600000000001'],
            'response' => [
                'numeroDeclaracao' => '20260600000000001',
                'recibo' => ['nomeArquivo' => 'recibo.pdf', 'pdf' => $pdf],
                'declaracao' => ['nomeArquivo' => 'declaracao.pdf', 'pdf' => $pdf],
                'maed' => [
                    'nomeArquivoNotificacao' => 'notificacao-maed.pdf',
                    'pdfNotificacao' => $pdf,
                    'nomeArquivoDarf' => 'darf-maed.pdf',
                    'pdfDarf' => $pdf,
                ],
            ],
        ],
        'pgdasd.consextrato' => [
            'request' => ['numeroDas' => '20260600000000002'],
            'response' => [
                'numeroDas' => '20260600000000002',
                'extrato' => ['nomeArquivo' => 'extrato-das.pdf', 'pdf' => $pdf],
            ],
        ],
        default => null,
    };
};

$fixtures = [];
foreach ($manifest['entries'] as $entry) {
    if (! is_array($entry) || ($entry['official_state'] ?? null) !== 'PRODUCTION') {
        continue;
    }
    $requestSchema = is_array($entry['request_schema'] ?? null) ? $entry['request_schema'] : [];
    $responseSchema = is_array($entry['response_schema'] ?? null) ? $entry['response_schema'] : [];
    $schemaDigest = hash('sha256', json_encode([
        'request' => $requestSchema,
        'response' => $responseSchema,
    ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

    $pgdasdShape = $pgdasdOfficialShape((string) $entry['operation_key']);
    $fixtures[] = [
        'operation_key' => (string) $entry['operation_key'],
        'route' => (string) $entry['route'],
        'synthetic' => true,
        'schema_sha256' => $schemaDigest,
        'request' => [
            'business_data' => $pgdasdShape['request'] ?? $buildFields($requestSchema),
        ],
        'response' => [
            'status' => 200,
            'mensagens' => [],
            'dados' => $pgdasdShape['response'] ?? $buildFields($responseSchema),
        ],
        'sources' => $entry['sources'],
    ];
}

if (count($fixtures) !== 98) {
    throw new RuntimeException('Esperadas 98 fixtures produtivas; obtidas '.count($fixtures).'.');
}

$output = [
    'fixture_version' => '2026.07.16.1',
    'manifest_sha256' => hash('sha256', $manifestRaw),
    'synthetic' => true,
    'notes' => 'Fixtures sintéticas derivadas dos schemas oficiais; não constituem evidência de resposta produtiva.',
    'fixtures' => $fixtures,
];
$json = json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n";
if (file_put_contents((string) $options['output'], $json) === false) {
    throw new RuntimeException('Falha ao gravar fixtures.');
}

fwrite(STDOUT, sprintf("Fixtures geradas: %s (%d operações)\n", $options['output'], count($fixtures)));

<?php

namespace App\Services\Integra\Sitfis;

use App\Enums\FiscalFindingSeverity;
use App\Enums\FiscalSituation;

/**
 * Parser versionado do relatório oficial SITFIS.
 *
 * - Layout conhecido: normaliza pendências reconhecidas.
 * - Layout/seção desconhecida: artefato permanece (quem chama guarda bytes);
 *   análise ATTENTION e finding de contrato alterado — nunca omite como "regular".
 * - Ausência de item reconhecido NÃO é certidão negativa (claimsNegativeCertificate=false).
 */
final class SitfisReportParser
{
    public const VERSION = '1.0';

    /** Chaves de seções conhecidas no layout v1. */
    private const KNOWN_SECTION_KEYS = [
        'pendencias',
        'pendenciasFiscais',
        'itens',
        'items',
        'debitos',
        'obrigacoes',
        'situacao',
        'status',
        'cabecalho',
        'header',
        'contribuinte',
        'protocolo',
        'dataConsulta',
        'data_consulta',
        'emissao',
        'emitidoEm',
        'observacoes',
        'mensagem',
        'message',
    ];

    /**
     * @param  array<string, mixed>|string  $report  JSON decodificado ou texto/base64 decodificado
     */
    public function parse(array|string $report, ?string $parserVersion = null): SitfisParseResult
    {
        $version = $parserVersion ?? self::VERSION;
        try {
            if ($parserVersion === null && function_exists('config')) {
                $configured = config('fiscal_monitoring.sitfis.parser_version');
                if (is_string($configured) && $configured !== '') {
                    $version = $configured;
                }
            }
        } catch (\Throwable) {
            $version = self::VERSION;
        }

        if (is_string($report)) {
            $trimmed = trim($report);
            if ($trimmed === '') {
                return $this->unknownLayout($version, ['empty_body'], [
                    'parser_note' => 'Corpo do relatório vazio.',
                ]);
            }
            $decoded = json_decode($trimmed, true);
            if (is_array($decoded)) {
                $report = $decoded;
            } else {
                // Texto livre desconhecido — preserva como layout novo
                return $this->unknownLayout($version, ['free_text_body'], [
                    'parser_note' => 'Relatório em formato textual não reconhecido.',
                    'body_preview_len' => strlen($trimmed),
                ]);
            }
        }

        if ($report === []) {
            return $this->unknownLayout($version, ['empty_object'], [
                'parser_note' => 'Relatório JSON vazio.',
            ]);
        }

        $unknown = $this->detectUnknownSections($report);
        $items = $this->extractPendingItems($report);
        $layoutKnown = $this->looksLikeKnownLayout($report, $items !== []);

        if (! $layoutKnown || $unknown !== []) {
            $findings = [[
                'code' => 'SITFIS_LAYOUT_UNKNOWN',
                'severity' => FiscalFindingSeverity::High->value,
                'title' => 'Layout/contrato do relatório SITFIS alterado ou desconhecido',
                'detail' => $unknown !== []
                    ? 'Seções não reconhecidas: '.implode(', ', $unknown)
                    : 'Estrutura do relatório não corresponde ao parser '.$version,
                'situation' => FiscalSituation::Attention->value,
                'creates_pending' => false,
            ]];

            // Itens reconhecidos ainda são projetados (não silenciar)
            foreach ($items as $item) {
                $findings[] = $item;
            }

            return new SitfisParseResult(
                parserVersion: $version,
                layoutRecognized: false,
                contractChanged: true,
                situation: FiscalSituation::Attention,
                findings: $findings,
                unknownSections: $unknown,
                normalized: [
                    'parser_version' => $version,
                    'layout_recognized' => false,
                    'contract_changed' => true,
                    'unknown_sections' => $unknown,
                    'recognized_items_count' => count($items),
                    'is_negative_certificate' => false,
                    'disclaimer' => 'Relatório preservado; análise incompleta por layout desconhecido. Não conclui regularidade.',
                ],
                claimsNegativeCertificate: false,
            );
        }

        if ($items !== []) {
            return new SitfisParseResult(
                parserVersion: $version,
                layoutRecognized: true,
                contractChanged: false,
                situation: FiscalSituation::Pending,
                findings: $items,
                unknownSections: [],
                normalized: [
                    'parser_version' => $version,
                    'layout_recognized' => true,
                    'contract_changed' => false,
                    'recognized_items_count' => count($items),
                    'is_negative_certificate' => false,
                    'disclaimer' => 'Pendências normalizadas a partir do relatório oficial. Rastreabilidade via evidência.',
                    'source_date' => $this->extractSourceDate($report),
                ],
                claimsNegativeCertificate: false,
            );
        }

        // Layout conhecido sem itens: NÃO afirmar certidão negativa / UP_TO_DATE.
        return new SitfisParseResult(
            parserVersion: $version,
            layoutRecognized: true,
            contractChanged: false,
            situation: FiscalSituation::Unknown,
            findings: [[
                'code' => 'SITFIS_NO_RECOGNIZED_ITEMS',
                'severity' => FiscalFindingSeverity::Info->value,
                'title' => 'Nenhuma pendência reconhecida no relatório',
                'detail' => 'Ausência de item reconhecido não equivale a certidão negativa nem garantia absoluta de regularidade.',
                'situation' => FiscalSituation::Unknown->value,
                'creates_pending' => false,
            ]],
            unknownSections: [],
            normalized: [
                'parser_version' => $version,
                'layout_recognized' => true,
                'contract_changed' => false,
                'recognized_items_count' => 0,
                'is_negative_certificate' => false,
                'disclaimer' => 'Resultado do parser sem pendências reconhecidas. Não é certidão negativa. Consulte a data da fonte oficial.',
                'source_date' => $this->extractSourceDate($report),
            ],
            claimsNegativeCertificate: false,
        );
    }

    /**
     * @param  array<string, mixed>  $report
     * @return list<array{code:string,severity?:string,title:string,detail?:string|null,situation?:string,creates_pending?:bool,due_at?:string|null}>
     */
    private function extractPendingItems(array $report): array
    {
        $rawLists = [];
        foreach (['pendencias', 'pendenciasFiscais', 'itens', 'items', 'debitos', 'obrigacoes'] as $key) {
            if (isset($report[$key]) && is_array($report[$key])) {
                $rawLists[] = $report[$key];
            }
        }

        // Envelope comum: dados.pendencias / relatorio.itens
        foreach (['dados', 'relatorio', 'report', 'conteudo', 'resultado'] as $wrap) {
            if (! isset($report[$wrap]) || ! is_array($report[$wrap])) {
                continue;
            }
            foreach (['pendencias', 'pendenciasFiscais', 'itens', 'items', 'debitos'] as $key) {
                if (isset($report[$wrap][$key]) && is_array($report[$wrap][$key])) {
                    $rawLists[] = $report[$wrap][$key];
                }
            }
        }

        $findings = [];
        $seen = [];
        foreach ($rawLists as $list) {
            // Lista associativa única?
            if ($this->isAssoc($list) && (isset($list['codigo']) || isset($list['code']) || isset($list['descricao']))) {
                $list = [$list];
            }
            foreach ($list as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $code = (string) ($row['codigo'] ?? $row['code'] ?? $row['id'] ?? '');
                $title = (string) ($row['descricao'] ?? $row['titulo'] ?? $row['title'] ?? $row['nome'] ?? '');
                if ($code === '' && $title === '') {
                    continue;
                }
                if ($code === '') {
                    $code = 'SITFIS_ITEM_'.substr(hash('sha256', $title), 0, 12);
                }
                $code = strtoupper(preg_replace('/[^A-Za-z0-9_\-.]/', '_', $code) ?? $code);
                if (isset($seen[$code])) {
                    continue;
                }
                $seen[$code] = true;

                $detail = isset($row['detalhe']) || isset($row['detail']) || isset($row['observacao'])
                    ? (string) ($row['detalhe'] ?? $row['detail'] ?? $row['observacao'])
                    : null;
                $due = $row['vencimento'] ?? $row['due_at'] ?? $row['dataVencimento'] ?? null;

                $findings[] = [
                    'code' => $code,
                    'severity' => FiscalFindingSeverity::High->value,
                    'title' => $title !== '' ? $title : $code,
                    'detail' => $detail,
                    'situation' => FiscalSituation::Pending->value,
                    'creates_pending' => true,
                    'due_at' => $due !== null ? (string) $due : null,
                ];
            }
        }

        return $findings;
    }

    /**
     * @param  array<string, mixed>  $report
     * @return list<string>
     */
    private function detectUnknownSections(array $report): array
    {
        $unknown = [];
        foreach (array_keys($report) as $key) {
            $k = (string) $key;
            if ($k === '' || str_starts_with($k, '_')) {
                continue;
            }
            // wrappers conhecidos não contam como desconhecidos
            if (in_array($k, ['dados', 'relatorio', 'report', 'conteudo', 'resultado', 'meta', 'metadata'], true)) {
                continue;
            }
            if (! in_array($k, self::KNOWN_SECTION_KEYS, true)
                && ! in_array(lcfirst($k), self::KNOWN_SECTION_KEYS, true)) {
                // Heurística: chave camel/snake de seções oficiais desconhecidas
                if (preg_match('/^(secao|section|bloco|anexo|nova)/i', $k) === 1) {
                    $unknown[] = $k;
                }
            }
        }

        // Marcador explícito de layout novo (fixture de testes / contrato alterado)
        if (isset($report['layoutVersion']) && (string) $report['layoutVersion'] !== '1'
            && (string) $report['layoutVersion'] !== '1.0') {
            $unknown[] = 'layoutVersion:'.(string) $report['layoutVersion'];
        }
        if (isset($report['layout_version']) && (string) $report['layout_version'] !== '1'
            && (string) $report['layout_version'] !== '1.0') {
            $unknown[] = 'layout_version:'.(string) $report['layout_version'];
        }
        if (! empty($report['__unknown_layout']) || ! empty($report['contratoAlterado'])) {
            $unknown[] = 'explicit_unknown_layout';
        }

        return array_values(array_unique($unknown));
    }

    /**
     * @param  array<string, mixed>  $report
     */
    private function looksLikeKnownLayout(array $report, bool $hasItems): bool
    {
        if ($hasItems) {
            return true;
        }

        foreach (['pendencias', 'pendenciasFiscais', 'itens', 'items', 'debitos', 'situacao', 'status'] as $key) {
            if (array_key_exists($key, $report)) {
                return true;
            }
        }

        foreach (['dados', 'relatorio', 'report', 'conteudo', 'resultado'] as $wrap) {
            if (! isset($report[$wrap]) || ! is_array($report[$wrap])) {
                continue;
            }
            foreach (['pendencias', 'itens', 'items', 'situacao', 'status'] as $key) {
                if (array_key_exists($key, $report[$wrap])) {
                    return true;
                }
            }
        }

        // Flag explícita de layout v1
        if (isset($report['layoutVersion']) && in_array((string) $report['layoutVersion'], ['1', '1.0'], true)) {
            return true;
        }
        if (isset($report['layout_version']) && in_array((string) $report['layout_version'], ['1', '1.0'], true)) {
            return true;
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $report
     */
    private function extractSourceDate(array $report): ?string
    {
        foreach (['dataConsulta', 'data_consulta', 'emitidoEm', 'emissao', 'observed_at', 'data'] as $key) {
            if (! empty($report[$key]) && is_scalar($report[$key])) {
                return (string) $report[$key];
            }
        }
        foreach (['cabecalho', 'header', 'dados', 'relatorio'] as $wrap) {
            if (! isset($report[$wrap]) || ! is_array($report[$wrap])) {
                continue;
            }
            foreach (['dataConsulta', 'data_consulta', 'emitidoEm', 'data'] as $key) {
                if (! empty($report[$wrap][$key]) && is_scalar($report[$wrap][$key])) {
                    return (string) $report[$wrap][$key];
                }
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $unknown
     * @param  array<string, mixed>  $extra
     */
    private function unknownLayout(string $version, array $unknown, array $extra): SitfisParseResult
    {
        return new SitfisParseResult(
            parserVersion: $version,
            layoutRecognized: false,
            contractChanged: true,
            situation: FiscalSituation::Attention,
            findings: [[
                'code' => 'SITFIS_LAYOUT_UNKNOWN',
                'severity' => FiscalFindingSeverity::High->value,
                'title' => 'Layout/contrato do relatório SITFIS alterado ou desconhecido',
                'detail' => (string) ($extra['parser_note'] ?? 'Parser não reconheceu o relatório.'),
                'situation' => FiscalSituation::Attention->value,
                'creates_pending' => false,
            ]],
            unknownSections: $unknown,
            normalized: array_merge([
                'parser_version' => $version,
                'layout_recognized' => false,
                'contract_changed' => true,
                'unknown_sections' => $unknown,
                'is_negative_certificate' => false,
                'disclaimer' => 'Relatório preservado; análise incompleta. Não conclui regularidade.',
            ], $extra),
            claimsNegativeCertificate: false,
        );
    }

    /**
     * @param  array<mixed>  $arr
     */
    private function isAssoc(array $arr): bool
    {
        if ($arr === []) {
            return false;
        }

        return array_keys($arr) !== range(0, count($arr) - 1);
    }
}

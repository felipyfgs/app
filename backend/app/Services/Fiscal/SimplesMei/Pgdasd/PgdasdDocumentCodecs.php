<?php

namespace App\Services\Fiscal\SimplesMei\Pgdasd;

use App\Enums\PgdasdDocumentKind;
use InvalidArgumentException;
use RuntimeException;

/**
 * Codecs de payload/resposta para serviços documentais 14–16.
 */
final class PgdasdDocumentCodecs
{
    /**
     * @return array{periodoApuracao: string}
     */
    public function buildPayload14(string $periodoApuracao): array
    {
        $pa = trim($periodoApuracao);
        if (preg_match('/^\d{6}$/', $pa) !== 1) {
            throw new InvalidArgumentException('CONSULTIMADECREC14 exige periodoApuracao AAAAMM.');
        }

        return ['periodoApuracao' => $pa];
    }

    /**
     * @return array{numeroDeclaracao: string}
     */
    public function buildPayload15(string $numeroDeclaracao): array
    {
        $n = trim($numeroDeclaracao);
        if ($n === '' || strlen($n) > 17) {
            throw new InvalidArgumentException('CONSDECREC15 exige numeroDeclaracao (até 17 chars).');
        }

        return ['numeroDeclaracao' => $n];
    }

    /**
     * @return array{numeroDas: string}
     */
    public function buildPayload16(string $numeroDas): array
    {
        $n = trim($numeroDas);
        if ($n === '' || strlen($n) > 17) {
            throw new InvalidArgumentException('CONSEXTRATO16 exige numeroDas (até 17 chars).');
        }

        return ['numeroDas' => $n];
    }

    /**
     * Extrai descritores de documentos (ainda com Base64 bruto) a partir de dados.
     *
     * @return list<array{
     *   kind: PgdasdDocumentKind,
     *   base64: string,
     *   filename_hint: ?string,
     *   numero_declaracao: ?string,
     *   field: string
     * }>
     */
    public function extractDocumentFields(mixed $dados, string $operationKey): array
    {
        $root = $this->coerceArray($dados);
        $docs = [];

        $map = match ($operationKey) {
            'pgdasd.consultimadecrec', 'pgdasd.consdecrec' => [
                ['pdf', PgdasdDocumentKind::Declaracao, 'nomeArquivo'],
                ['recibo', PgdasdDocumentKind::Recibo, 'nomeArquivo'],
                ['pdfNotificacao', PgdasdDocumentKind::NotificacaoMaed, 'nomeArquivoNotificacao'],
                ['pdfDarf', PgdasdDocumentKind::DarfMaed, 'nomeArquivoDarf'],
            ],
            'pgdasd.consextrato' => [
                ['pdf', PgdasdDocumentKind::Extrato, 'nomeArquivo'],
                ['extrato', PgdasdDocumentKind::Extrato, 'nomeArquivo'],
            ],
            default => [],
        };

        $numeroDec = isset($root['numeroDeclaracao']) ? trim((string) $root['numeroDeclaracao']) : null;
        if ($numeroDec === '') {
            $numeroDec = null;
        }

        // maed aninhado
        $nodes = [$root];
        if (is_array($root['maed'] ?? null)) {
            $nodes[] = $root['maed'];
        }
        if (is_array($root['recibo'] ?? null) && ! is_string($root['recibo'])) {
            $nodes[] = $root['recibo'];
        }

        foreach ($nodes as $node) {
            foreach ($map as [$field, $kind, $nameField]) {
                if (! isset($node[$field]) || ! is_string($node[$field]) || trim($node[$field]) === '') {
                    continue;
                }
                $b64 = trim($node[$field]);
                // placeholders sintéticos não são PDF
                if ($b64 === 'EXEMPLO_SINTETICO') {
                    continue;
                }
                $hint = isset($node[$nameField]) && is_string($node[$nameField])
                    ? $node[$nameField]
                    : null;
                $docs[] = [
                    'kind' => $kind,
                    'base64' => $b64,
                    'filename_hint' => $hint,
                    'numero_declaracao' => $numeroDec,
                    'field' => $field,
                ];
            }
        }

        return $docs;
    }

    /**
     * Remove campos de PDF Base64 de dados, substituindo por descritores sanitizados.
     *
     * @param  array<string, mixed>  $descriptors  field => public descriptor
     * @return array<string, mixed>
     */
    public function sanitizeDados(mixed $dados, array $descriptors): array
    {
        $root = $this->coerceArray($dados);
        $pdfFields = [
            'pdf', 'recibo', 'pdfNotificacao', 'pdfDarf', 'extrato', 'declaracao',
        ];

        $walk = function (array &$node) use (&$walk, $pdfFields, $descriptors): void {
            foreach ($pdfFields as $field) {
                if (array_key_exists($field, $node) && is_string($node[$field])) {
                    $node[$field] = $descriptors[$field] ?? [
                        'sanitized' => true,
                        'content_type' => 'application/pdf',
                    ];
                }
            }
            if (isset($node['maed']) && is_array($node['maed'])) {
                $walk($node['maed']);
            }
            if (isset($node['recibo']) && is_array($node['recibo'])) {
                $walk($node['recibo']);
            }
        };

        $walk($root);

        return $root;
    }

    /**
     * @return array<string, mixed>
     */
    private function coerceArray(mixed $dados): array
    {
        if ($dados === null || $dados === '') {
            return [];
        }
        if (is_string($dados)) {
            $decoded = json_decode($dados, true);
            if (! is_array($decoded)) {
                throw new RuntimeException('Resposta documental: dados não é JSON de objeto.');
            }

            return $decoded;
        }
        if (! is_array($dados)) {
            throw new RuntimeException('Resposta documental: dados em formato inválido.');
        }

        return $dados;
    }
}

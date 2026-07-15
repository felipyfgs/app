<?php

namespace App\Services\Outbound;

use App\Contracts\SvrsNfceDownloadResponseParser as SvrsNfceDownloadResponseParserContract;
use App\DTO\Outbound\SvrsNfceParseResult;
use App\Enums\SvrsNfceTransportOutcome;

/**
 * Parser estrito do wrapper HTML/JS do DownloadXMLDFe.
 * Gramática mínima de escapes JS; sem eval/engine/stripcslashes.
 */
final class SvrsNfceDownloadResponseParser implements SvrsNfceDownloadResponseParserContract
{
    public function __construct(
        private readonly SvrsNfceConfig $config,
    ) {}

    public function parserVersion(): string
    {
        return $this->config->parserVersion();
    }

    public function parseFormPage(string $html): SvrsNfceParseResult
    {
        if (strlen($html) > $this->config->maxHtmlBytes()) {
            return $this->fail(SvrsNfceTransportOutcome::PayloadTooLarge, 'HTML GET excede limite.');
        }

        $markers = [
            'Download do XML do NFCe',
            'DownloadXMLDFe',
            'ChaveAcessoDfe',
            'sistema',
            'Ambiente',
        ];

        $missing = [];
        foreach ($markers as $marker) {
            if (! str_contains($html, $marker)) {
                // Aceitar variação de case em "NFCe" / "NFC-e" no título
                if ($marker === 'Download do XML do NFCe'
                    && (str_contains($html, 'Download do XML do NFC-e')
                        || str_contains($html, 'Download XML'))) {
                    continue;
                }
                $missing[] = $marker;
            }
        }

        // Formulário com action do download
        $hasForm = (bool) preg_match(
            '/<form\b[^>]*(action=["\'][^"\']*DownloadXmlDfe[^"\']*["\']|id=["\'][^"\']*["\'])[^>]*>/i',
            $html
        ) || str_contains($html, 'DownloadXmlDfe') || str_contains($html, 'downloadXml');

        if ($missing !== [] && ! $hasForm) {
            return $this->fail(
                SvrsNfceTransportOutcome::ResponseContractChanged,
                'Formulário GET sem marcadores esperados.',
            );
        }

        if (count($missing) > 2 && ! $hasForm) {
            return $this->fail(
                SvrsNfceTransportOutcome::ResponseContractChanged,
                'Marcadores do formulário GET divergentes.',
            );
        }

        // Página de manutenção/erro genérico sem formulário
        if (! $hasForm && (
            str_contains($html, 'manuten')
            || str_contains($html, 'indispon')
            || str_contains(strtolower($html), 'service unavailable')
        )) {
            return $this->fail(SvrsNfceTransportOutcome::HttpTransient, 'Portal em manutenção ou indisponível.');
        }

        return new SvrsNfceParseResult(
            outcome: SvrsNfceTransportOutcome::FormOk,
            parserVersion: $this->parserVersion(),
        );
    }

    public function parseDownloadPage(string $html): SvrsNfceParseResult
    {
        if (strlen($html) > $this->config->maxHtmlBytes()) {
            return $this->fail(SvrsNfceTransportOutcome::PayloadTooLarge, 'HTML POST excede limite.');
        }

        // Templates reconhecidos de "não disponível"
        if ($this->looksLikeNotAvailable($html)) {
            return new SvrsNfceParseResult(
                outcome: SvrsNfceTransportOutcome::RemoteNotFound,
                parserVersion: $this->parserVersion(),
                sanitizedDetail: 'Documento não disponível no portal.',
            );
        }

        if ($this->looksLikeAuthDenied($html)) {
            return $this->fail(SvrsNfceTransportOutcome::AuthForbidden, 'Autenticação negada pelo portal.');
        }

        // Localizar função/download oficial e literal associado ao Blob
        $literal = $this->extractOfficialBlobLiteral($html);
        if ($literal === null) {
            return $this->fail(
                SvrsNfceTransportOutcome::ResponseContractChanged,
                'Literal do Blob não encontrado ou ambíguo.',
            );
        }

        if (strlen($literal) > $this->config->maxLiteralBytes()) {
            return $this->fail(SvrsNfceTransportOutcome::PayloadTooLarge, 'Literal excede limite.');
        }

        $decoded = $this->decodeJsStringLiteral($literal);
        if ($decoded === null) {
            return $this->fail(
                SvrsNfceTransportOutcome::ResponseContractChanged,
                'Escape JavaScript inválido ou expressão rejeitada.',
            );
        }

        if (strlen($decoded) > $this->config->maxXmlBytes()) {
            return $this->fail(SvrsNfceTransportOutcome::PayloadTooLarge, 'XML decodificado excede limite.');
        }

        if (! str_contains($decoded, 'nfeProc') && ! str_contains($decoded, 'NFe')) {
            return $this->fail(
                SvrsNfceTransportOutcome::ResponseContractChanged,
                'Conteúdo decodificado não parece nfeProc.',
            );
        }

        // Bytes exatos do Blob — sem normalização
        return new SvrsNfceParseResult(
            outcome: SvrsNfceTransportOutcome::Captured,
            xmlBytes: $decoded,
            parserVersion: $this->parserVersion(),
        );
    }

    private function looksLikeNotAvailable(string $html): bool
    {
        $needles = [
            'não foi possível localizar',
            'nao foi possivel localizar',
            'documento não encontrado',
            'documento nao encontrado',
            'chave de acesso inválida',
            'chave de acesso invalida',
            'Nenhum documento encontrado',
            'XML não disponível',
            'XML nao disponivel',
        ];
        $lower = mb_strtolower($html);
        foreach ($needles as $n) {
            if (str_contains($lower, mb_strtolower($n))) {
                return true;
            }
        }

        return false;
    }

    private function looksLikeAuthDenied(string $html): bool
    {
        $needles = [
            'acesso negado',
            'não autorizado',
            'nao autorizado',
            'certificado inválido',
            'certificado invalido',
            'sem permissão',
            'sem permissao',
            '403 Forbidden',
        ];
        foreach ($needles as $n) {
            if (stripos($html, $n) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Extrai o único literal de string associado ao download oficial (Blob/downloadXml).
     */
    private function extractOfficialBlobLiteral(string $html): ?string
    {
        // Padrões observados: downloadXml('...'), new Blob([ '...xml...' ]), var xml = "..."
        $patterns = [
            // downloadXml("...") ou downloadXml('...')
            '/\bdownloadXml\s*\(\s*(["\'])(.*?)\1\s*\)/is',
            // new Blob([ "..." ]) / new Blob(['...'])
            '/\bnew\s+Blob\s*\(\s*\[\s*(["\'])(.*?)\1\s*\]/is',
            // variável tipicamente materializada antes do Blob
            '/\b(?:var|let|const)\s+(?:xml|xmlDfe|conteudoXml|xmlContent)\s*=\s*(["\'])(.*?)\1\s*;/is',
        ];

        $candidates = [];
        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $html, $matches, PREG_SET_ORDER) === false) {
                continue;
            }
            foreach ($matches as $m) {
                $body = $m[2] ?? '';
                // Rejeitar se o "literal" contém concatenação óbvia não capturada
                if ($this->looksLikeExpression($body) || $this->looksLikeTemplateOrConcatOutside($m[0] ?? '')) {
                    return null;
                }
                if ($body !== '' && (str_contains($body, 'nfeProc') || str_contains($body, 'NFe') || str_contains($body, '<'))) {
                    $candidates[] = $body;
                }
            }
        }

        // Fallback: único literal longo contendo <nfeProc
        if ($candidates === []) {
            if (preg_match_all('/(["\'])((?:\\\\.|(?!\1).)*?<nfeProc(?:\\\\.|(?!\1).)*?<\/nfeProc(?:\\\\.|(?!\1).)*)\1/is', $html, $m2, PREG_SET_ORDER)) {
                foreach ($m2 as $row) {
                    $candidates[] = $row[2];
                }
            }
        }

        if ($candidates === []) {
            return null;
        }

        // Múltiplos candidatos distintos → ambiguidade
        $unique = array_values(array_unique($candidates));
        if (count($unique) > 1) {
            return null;
        }

        // Rejeitar se o match original sugere concatenação/template no contexto
        if (preg_match('/downloadXml\s*\(\s*[^\'"]|new\s+Blob\s*\(\s*\[\s*[^\'"]|`[^`]*\$\{/', $html)
            && ! preg_match('/downloadXml\s*\(\s*[\'"]|new\s+Blob\s*\(\s*\[\s*[\'"]/', $html)) {
            return null;
        }

        // Template strings
        if (preg_match('/downloadXml\s*\(\s*`|new\s+Blob\s*\(\s*\[\s*`/', $html)) {
            return null;
        }

        // Concatenação: downloadXml(a + b) ou "..." + "..."
        if (preg_match('/downloadXml\s*\([^)]*\+|new\s+Blob\s*\(\s*\[[^\]]*\+/', $html)) {
            return null;
        }

        return $unique[0];
    }

    private function looksLikeExpression(string $body): bool
    {
        // Dentro do literal capturado não deve haver ${} de template residual mal parseado
        if (str_contains($body, '${')) {
            return true;
        }

        return false;
    }

    private function looksLikeTemplateOrConcatOutside(string $match): bool
    {
        return str_contains($match, '`') || str_contains($match, '${') || preg_match('/["\']\s*\+\s*["\']/', $match) === 1;
    }

    /**
     * Decoder mínimo de escapes JavaScript de string entre aspas.
     * Aceita: \\ \" \' \n \r \t \b \f \v \0 \xHH \uXXXX
     * Rejeita: escapes inválidos, truncamento, \u{...} estendido.
     */
    public function decodeJsStringLiteral(string $literal): ?string
    {
        $out = '';
        $len = strlen($literal);
        $i = 0;

        while ($i < $len) {
            $ch = $literal[$i];
            if ($ch !== '\\') {
                $out .= $ch;
                $i++;
                continue;
            }

            $i++;
            if ($i >= $len) {
                return null; // truncado
            }

            $esc = $literal[$i];
            $i++;

            switch ($esc) {
                case '\\':
                case '"':
                case "'":
                case '/':
                    $out .= $esc;
                    break;
                case 'n':
                    $out .= "\n";
                    break;
                case 'r':
                    $out .= "\r";
                    break;
                case 't':
                    $out .= "\t";
                    break;
                case 'b':
                    $out .= "\x08";
                    break;
                case 'f':
                    $out .= "\x0C";
                    break;
                case 'v':
                    $out .= "\x0B";
                    break;
                case '0':
                    // Somente \0 puro (não octal estendido)
                    if ($i < $len && ctype_digit($literal[$i])) {
                        return null;
                    }
                    $out .= "\0";
                    break;
                case 'x':
                    if ($i + 2 > $len) {
                        return null;
                    }
                    $hex = substr($literal, $i, 2);
                    if (! ctype_xdigit($hex)) {
                        return null;
                    }
                    $out .= chr(hexdec($hex));
                    $i += 2;
                    break;
                case 'u':
                    if ($i < $len && $literal[$i] === '{') {
                        return null; // \u{...} não suportado
                    }
                    if ($i + 4 > $len) {
                        return null;
                    }
                    $hex = substr($literal, $i, 4);
                    if (! ctype_xdigit($hex)) {
                        return null;
                    }
                    $code = hexdec($hex);
                    if ($code <= 0x7F) {
                        $out .= chr($code);
                    } else {
                        $converted = mb_convert_encoding(pack('n', $code), 'UTF-8', 'UTF-16BE');
                        if ($converted === false) {
                            return null;
                        }
                        $out .= $converted;
                    }
                    $i += 4;
                    break;
                default:
                    return null; // escape inválido / expressão
            }
        }

        return $out;
    }

    private function fail(SvrsNfceTransportOutcome $outcome, string $detail): SvrsNfceParseResult
    {
        return new SvrsNfceParseResult(
            outcome: $outcome,
            parserVersion: $this->parserVersion(),
            sanitizedDetail: $detail,
        );
    }
}

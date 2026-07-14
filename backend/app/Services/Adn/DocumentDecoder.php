<?php

namespace App\Services\Adn;

use App\Exceptions\Adn\DocumentDecodeException;

final class DocumentDecoder
{
    /**
     * @return array{bytes: string, sha256: string}
     */
    public function decodeBase64Gzip(string $contentBase64): array
    {
        $binary = base64_decode($contentBase64, true);
        if ($binary === false) {
            throw new DocumentDecodeException('Base64 inválido.');
        }

        $decoded = @gzdecode($binary);
        if ($decoded === false || $decoded === '') {
            // gzdecode pode emitir warning; normalizamos para exceção de domínio
            throw new DocumentDecodeException('GZip inválido.');
        }

        return [
            'bytes' => $decoded,
            'sha256' => hash('sha256', $decoded),
        ];
    }
}

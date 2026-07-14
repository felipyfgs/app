<?php

namespace App\Services\Adn;

use App\Exceptions\Adn\AdnPermanentException;
use App\Exceptions\Adn\AdnRetryableException;
use RuntimeException;

/**
 * Transporte cURL mTLS: PFX apenas em memória (BLOB), TLS ≥ 1.2, hostname verificado.
 */
class CurlMtlsTransport
{
    public function __construct(
        private readonly int $timeoutSeconds = 30,
        private readonly int $connectTimeoutSeconds = 10,
        private readonly bool $verifyTls = true,
    ) {
        if (! $this->verifyTls) {
            throw new RuntimeException('Verificação TLS não pode ser desativada.');
        }
    }

    /**
     * @param  array{pfx: string, password: string}  $certificate
     * @return array{status: int, body: string, headers: array<string, string>}
     */
    public function get(string $url, array $certificate): array
    {
        if (! extension_loaded('curl')) {
            throw new AdnPermanentException('Cliente ADN indisponível por falha de configuração.');
        }

        if (! defined('CURLOPT_SSLCERT_BLOB')) {
            throw new AdnPermanentException('Cliente ADN sem suporte a PFX em memória.');
        }

        if (($certificate['pfx'] ?? '') === '' || ! is_string($certificate['pfx'])) {
            throw new AdnPermanentException('Credencial mTLS inválida.');
        }

        $ch = curl_init($url);
        if ($ch === false) {
            throw new AdnPermanentException('Cliente ADN indisponível por falha de configuração.');
        }

        $headers = [];
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT => $this->timeoutSeconds,
            CURLOPT_CONNECTTIMEOUT => $this->connectTimeoutSeconds,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_2,
            CURLOPT_SSLCERTTYPE => 'P12',
            CURLOPT_SSLCERT_BLOB => $certificate['pfx'],
            CURLOPT_KEYPASSWD => (string) ($certificate['password'] ?? ''),
            CURLOPT_HTTPHEADER => [
                'Accept: application/json, application/xml, text/xml, */*',
            ],
            CURLOPT_HEADERFUNCTION => function ($curl, string $line) use (&$headers): int {
                $len = strlen($line);
                $parts = explode(':', $line, 2);
                if (count($parts) === 2) {
                    $headers[strtolower(trim($parts[0]))] = trim($parts[1]);
                }

                return $len;
            },
        ]);

        $body = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errno !== 0) {
            unset($error);

            if ($this->isRetryableCurlError($errno)) {
                throw new AdnRetryableException('Falha temporária na comunicação com o ADN.');
            }

            throw new AdnPermanentException('Falha permanente na comunicação mTLS com o ADN.');
        }

        if ($body === false) {
            throw new AdnRetryableException('O ADN não retornou uma resposta utilizável.');
        }

        return [
            'status' => $status,
            'body' => $body,
            'headers' => $headers,
        ];
    }

    private function isRetryableCurlError(int $errno): bool
    {
        return in_array($errno, [
            CURLE_COULDNT_RESOLVE_HOST,
            CURLE_COULDNT_CONNECT,
            CURLE_OPERATION_TIMEDOUT,
            CURLE_PARTIAL_FILE,
            CURLE_GOT_NOTHING,
            CURLE_SEND_ERROR,
            CURLE_RECV_ERROR,
        ], true);
    }
}

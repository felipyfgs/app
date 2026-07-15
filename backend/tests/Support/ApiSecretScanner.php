<?php

namespace Tests\Support;

use PHPUnit\Framework\AssertionFailedError;

/**
 * Varredura automática de marcadores de segredo em payloads de API.
 * Usar em testes Feature de Operations e superfícies tenant.
 */
final class ApiSecretScanner
{
    /** @var list<string> */
    public const FORBIDDEN_MARKERS = [
        'BEGIN PRIVATE KEY',
        'BEGIN RSA PRIVATE KEY',
        'BEGIN CERTIFICATE',
        '-----BEGIN',
        'VAULT_MASTER_KEY',
        'consumer_secret',
        'Consumer Secret',
        'password=',
        'CURLOPT_SSLCERT_BLOB',
        'Bearer ey',
    ];

    /**
     * Chaves JSON proibidas (valor recuperável). Metadados públicos como
     * has_procurador_token / procurador_token_expires_at / termo_sha256 são permitidos.
     *
     * @var list<string>
     */
    public const FORBIDDEN_JSON_KEYS = [
        '"password"',
        '"pfx"',
        '"private_key"',
        '"consumer_secret"',
        '"access_token"',
        '"vault_object_id"',
        '"termo_xml"',
        '"procurador_token"',
        '"raw_xml"',
        '"certificate_pem"',
        '"oauth_vault_object_id"',
        '"pfx_vault_object_id"',
        '"termo_vault_object_id"',
        '"procurador_token_vault_object_id"',
        '"author_pfx_vault_object_id"',
        '"token_vault_object_id"',
    ];

    /**
     * @return list<string> marcadores encontrados
     */
    public static function findLeaks(string $payload): array
    {
        $hits = [];
        $lower = strtolower($payload);

        foreach (self::FORBIDDEN_MARKERS as $marker) {
            if (str_contains($lower, strtolower($marker))) {
                $hits[] = $marker;
            }
        }

        foreach (self::FORBIDDEN_JSON_KEYS as $key) {
            // Match chave JSON exata (evita falso positivo em has_procurador_token).
            if (preg_match('/'.preg_quote(strtolower($key), '/').'\s*:/', $lower) === 1
                || str_contains($lower, strtolower($key))) {
                // Permitir apenas se for substring de metadado público allowlisted.
                if (self::isAllowedPublicKeyContext($lower, strtolower($key))) {
                    continue;
                }
                $hits[] = $key;
            }
        }

        // vault_object_id como substring de *_vault_object_id no payload sanitizado
        // só falha se aparecer como chave recuperável (já coberto) ou valor ULID solto com vault
        if (preg_match('/"vault_object_id"\s*:/', $lower) === 1) {
            $hits[] = 'vault_object_id';
        }

        // Chave de acesso NF-e 44 dígitos
        if (preg_match('/\b\d{44}\b/', $payload) === 1) {
            $hits[] = 'access_key_44_digits';
        }

        return array_values(array_unique($hits));
    }

    private static function isAllowedPublicKeyContext(string $lowerPayload, string $forbiddenKey): bool
    {
        // "procurador_token" como substring de has_procurador_token / procurador_token_expires_at
        if ($forbiddenKey === '"procurador_token"') {
            // Se a chave exata "procurador_token": existe, não permitir.
            if (preg_match('/"procurador_token"\s*:/', $lowerPayload) === 1) {
                return false;
            }

            return true;
        }

        return false;
    }

    public static function assertClean(string $payload, string $context = 'response'): void
    {
        $hits = self::findLeaks($payload);
        if ($hits !== []) {
            throw new AssertionFailedError(
                "Marcadores sensíveis em {$context}: ".implode(', ', $hits)
            );
        }
    }
}

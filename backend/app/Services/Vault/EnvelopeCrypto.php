<?php

namespace App\Services\Vault;

use RuntimeException;

/**
 * Envelope: DEK aleatória + XChaCha20-Poly1305; DEK embrulhada pela master key versionada.
 */
final class EnvelopeCrypto
{
    public function __construct(
        private readonly string $masterKeyBinary,
        private readonly int $keyVersion = 1,
    ) {
        if (strlen($this->masterKeyBinary) !== SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES) {
            throw new RuntimeException('VAULT_MASTER_KEY deve decodificar para 32 bytes.');
        }
    }

    public static function fromConfig(): self
    {
        $encoded = (string) config('vault.master_key', '');
        $version = (int) config('vault.master_key_version', 1);

        if ($encoded === '') {
            throw new RuntimeException('VAULT_MASTER_KEY não configurada.');
        }

        $binary = base64_decode($encoded, true);
        if ($binary === false) {
            throw new RuntimeException('VAULT_MASTER_KEY inválida (base64).');
        }

        return new self($binary, $version);
    }

    /**
     * @param  array<string, scalar|null>  $metadata
     * @return array{ciphertext: string, wrapped_dek: string, nonce: string, wrap_nonce: string, key_version: int}
     */
    public function seal(string $plaintext, array $metadata = []): array
    {
        $dek = random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES);
        $nonce = random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES);
        $aad = $this->aad($metadata);

        $ciphertext = sodium_crypto_aead_xchacha20poly1305_ietf_encrypt(
            $plaintext,
            $aad,
            $nonce,
            $dek
        );

        $wrapNonce = random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES);
        $wrappedDek = sodium_crypto_aead_xchacha20poly1305_ietf_encrypt(
            $dek,
            $this->wrapAad(),
            $wrapNonce,
            $this->masterKeyBinary
        );

        sodium_memzero($dek);

        return [
            'ciphertext' => $ciphertext,
            'wrapped_dek' => $wrappedDek,
            'nonce' => $nonce,
            'wrap_nonce' => $wrapNonce,
            'key_version' => $this->keyVersion,
        ];
    }

    /**
     * @param  array{ciphertext: string, wrapped_dek: string, nonce: string, wrap_nonce: string, key_version: int}  $envelope
     * @param  array<string, scalar|null>  $metadata
     */
    public function open(array $envelope, array $metadata = []): string
    {
        $dek = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt(
            $envelope['wrapped_dek'],
            $this->wrapAad((int) $envelope['key_version']),
            $envelope['wrap_nonce'],
            $this->masterKeyBinary
        );

        if ($dek === false) {
            throw new RuntimeException('Falha ao desembrulhar DEK (chave mestra ou adulteração).');
        }

        $plaintext = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt(
            $envelope['ciphertext'],
            $this->aad($metadata),
            $envelope['nonce'],
            $dek
        );

        sodium_memzero($dek);

        if ($plaintext === false) {
            throw new RuntimeException('Falha ao descriptografar objeto (AAD/metadados ou adulteração).');
        }

        return $plaintext;
    }

    /**
     * @param  array<string, scalar|null>  $metadata
     */
    private function aad(array $metadata): string
    {
        ksort($metadata);

        return json_encode($metadata, JSON_THROW_ON_ERROR);
    }

    private function wrapAad(?int $version = null): string
    {
        return 'vault-dek-v'.($version ?? $this->keyVersion);
    }
}

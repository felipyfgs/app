<?php

namespace App\Services\Vault;

use App\Contracts\SecureObjectStore;
use Illuminate\Support\Str;
use RuntimeException;

final class FilesystemSecureObjectStore implements SecureObjectStore
{
    public function __construct(
        private readonly EnvelopeCrypto $crypto,
        private readonly string $root,
    ) {
        if (! is_dir($this->root) && ! mkdir($this->root, 0700, true) && ! is_dir($this->root)) {
            throw new RuntimeException('Não foi possível criar o diretório do cofre.');
        }
    }

    public function put(string $plaintext, array $metadata = []): string
    {
        $id = (string) Str::ulid();
        $envelope = $this->crypto->seal($plaintext, $metadata);

        $payload = [
            'v' => 1,
            'key_version' => $envelope['key_version'],
            'nonce' => base64_encode($envelope['nonce']),
            'wrap_nonce' => base64_encode($envelope['wrap_nonce']),
            'wrapped_dek' => base64_encode($envelope['wrapped_dek']),
            'ciphertext' => base64_encode($envelope['ciphertext']),
        ];

        $path = $this->path($id);
        $dir = dirname($path);
        if (! is_dir($dir) && ! mkdir($dir, 0700, true) && ! is_dir($dir)) {
            throw new RuntimeException('Não foi possível criar o diretório do objeto.');
        }

        if (file_put_contents($path, json_encode($payload, JSON_THROW_ON_ERROR), LOCK_EX) === false) {
            throw new RuntimeException('Falha ao gravar objeto no cofre.');
        }

        chmod($path, 0600);

        return $id;
    }

    public function get(string $objectId, array $metadata = []): string
    {
        $path = $this->path($objectId);
        if (! is_file($path)) {
            throw new RuntimeException('Objeto não encontrado no cofre.');
        }

        $raw = file_get_contents($path);
        if ($raw === false) {
            throw new RuntimeException('Falha ao ler objeto do cofre.');
        }

        /** @var array{v:int,key_version:int,nonce:string,wrap_nonce:string,wrapped_dek:string,ciphertext:string} $payload */
        $payload = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);

        $ciphertext = base64_decode((string) ($payload['ciphertext'] ?? ''), true);
        $wrappedDek = base64_decode((string) ($payload['wrapped_dek'] ?? ''), true);
        $nonce = base64_decode((string) ($payload['nonce'] ?? ''), true);
        $wrapNonce = base64_decode((string) ($payload['wrap_nonce'] ?? ''), true);

        if ($ciphertext === false || $wrappedDek === false || $nonce === false || $wrapNonce === false) {
            throw new RuntimeException('Envelope do cofre corrompido (base64).');
        }

        return $this->crypto->open([
            'ciphertext' => $ciphertext,
            'wrapped_dek' => $wrappedDek,
            'nonce' => $nonce,
            'wrap_nonce' => $wrapNonce,
            'key_version' => (int) $payload['key_version'],
        ], $metadata);
    }

    public function delete(string $objectId): void
    {
        $path = $this->path($objectId);
        if (is_file($path)) {
            $size = filesize($path) ?: 0;
            if ($size > 0) {
                file_put_contents($path, random_bytes(min($size, 4096)));
            }
            unlink($path);
        }
    }

    public function exists(string $objectId): bool
    {
        return is_file($this->path($objectId));
    }

    private function path(string $objectId): string
    {
        if (! preg_match('/^[0-9A-HJKMNP-TV-Z]{26}$/i', $objectId)) {
            throw new RuntimeException('Identificador de objeto inválido.');
        }

        $prefix = strtolower(substr($objectId, 0, 2));

        return rtrim($this->root, '/').'/'.$prefix.'/'.$objectId.'.json';
    }
}

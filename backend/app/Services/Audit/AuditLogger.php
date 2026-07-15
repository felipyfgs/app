<?php

namespace App\Services\Audit;

use App\Models\AuditLog;
use App\Support\CurrentOffice;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Trilha de auditoria + log estruturado sem segredos.
 */
final class AuditLogger
{
    /** @var list<string> */
    private const REDACT_KEYS = [
        'password', 'pfx', 'private_key', 'privateKey', 'pem', 'certificate',
        'token', 'secret', 'authorization', 'cookie', 'vault_object_id',
        'master_key', 'VAULT_MASTER_KEY',
        'csc', 'csc_token', 'cscToken', 'xml', 'raw_xml', 'soap',
    ];

    public function __construct(
        private readonly CurrentOffice $currentOffice,
    ) {}

    /**
     * @param  array<string, mixed>  $context
     */
    public function record(
        string $action,
        string $result = 'SUCCESS',
        ?Model $subject = null,
        array $context = [],
        ?int $userId = null,
        ?int $officeId = null,
    ): void {
        $safe = $this->redact($context);
        $correlationId = $this->correlationId();

        try {
            AuditLog::query()->create([
                'office_id' => $officeId ?? $this->currentOffice->id(),
                'user_id' => $userId ?? auth()->id(),
                'action' => $action,
                'subject_type' => $subject ? $subject::class : null,
                'subject_id' => $subject?->getKey(),
                'result' => $result,
                'context' => $safe,
                'ip_address' => request()?->ip(),
                'user_agent' => request()?->userAgent(),
                'correlation_id' => $correlationId,
                'created_at' => now(),
            ]);
        } catch (Throwable $e) {
            // Auditoria não deve derrubar a operação principal.
            report($e);
        }

        Log::info('audit.'.$action, [
            'result' => $result,
            'subject_type' => $subject ? class_basename($subject) : null,
            'subject_id' => $subject?->getKey(),
            'office_id' => $officeId ?? $this->currentOffice->id(),
            'user_id' => $userId ?? auth()->id(),
            'correlation_id' => $correlationId,
            'context' => $safe,
        ]);
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function redact(array $context): array
    {
        $out = [];
        foreach ($context as $key => $value) {
            $lower = strtolower((string) $key);
            if ($this->isSensitiveKey($lower)) {
                $out[$key] = '[redacted]';

                continue;
            }
            if (is_array($value)) {
                $out[$key] = $this->redact($value);

                continue;
            }
            if (is_string($value) && $this->looksLikeSecret($value)) {
                $out[$key] = '[redacted]';

                continue;
            }
            $out[$key] = $value;
        }

        return $out;
    }

    public function correlationId(): string
    {
        $existing = request()?->attributes->get('correlation_id');
        if (is_string($existing) && $existing !== '') {
            return $existing;
        }

        $id = (string) Str::uuid();
        request()?->attributes->set('correlation_id', $id);

        return $id;
    }

    private function isSensitiveKey(string $lower): bool
    {
        foreach (self::REDACT_KEYS as $key) {
            $needle = strtolower($key);
            // Chaves curtas (ex.: csc) só por igualdade — evita redigir csc_id (metadado público).
            if (strlen($needle) <= 3) {
                if ($lower === $needle || str_starts_with($lower, $needle.'_') || str_ends_with($lower, '_'.$needle)) {
                    // csc_id e csc_configured são metadados não secretos
                    if (in_array($lower, ['csc_id', 'csc_configured', 'csc_configured_at'], true)) {
                        return false;
                    }

                    return true;
                }

                continue;
            }
            if ($lower === $needle || str_contains($lower, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function looksLikeSecret(string $value): bool
    {
        if (str_contains($value, 'BEGIN ') && str_contains($value, 'PRIVATE KEY')) {
            return true;
        }
        if (str_contains($value, 'BEGIN CERTIFICATE')) {
            return true;
        }

        return false;
    }
}

<?php

namespace App\Services\Audit;

use App\Models\AuditLog;
use App\Support\CurrentOffice;
use App\Support\LogSanitizer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Trilha de auditoria + log estruturado sem segredos.
 * Sanitização central em {@see LogSanitizer} (reutilizável por adapters/jobs).
 */
final class AuditLogger
{
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
        return LogSanitizer::redact($context);
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
}

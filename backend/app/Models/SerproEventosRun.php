<?php

namespace App\Models;

use App\Enums\SerproEnvironment;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'office_id',
    'client_id',
    'environment',
    'person_type',
    'phase',
    'protocol',
    'tempo_espera_medio_ms',
    'tempo_limite_em_min',
    'not_before_at',
    'expires_at',
    'result_consumed',
    'one_shot_complete',
    'status',
    'correlation_id',
    'operation_key_solicit',
    'operation_key_obter',
    'evento',
    'contributors_in_batch',
    'result_fingerprint',
    'error_code',
    'error_message',
    'simulated',
    'progress',
    'result_summary',
    'solicited_at',
    'obtained_at',
])]
class SerproEventosRun extends Model
{
    public const PHASE_IDLE = 'IDLE';

    public const PHASE_SOLICITED = 'SOLICITED';

    public const PHASE_WAITING = 'WAITING';

    public const PHASE_OBTAINING = 'OBTAINING';

    public const PHASE_CONSUMED = 'CONSUMED';

    public const PHASE_EXPIRED = 'EXPIRED';

    public const PHASE_FAILED = 'FAILED';

    public const PHASE_RATE_LIMITED = 'RATE_LIMITED';

    public const STATUS_PENDING = 'PENDING';

    public const STATUS_RUNNING = 'RUNNING';

    public const STATUS_SUCCEEDED = 'SUCCEEDED';

    public const STATUS_FAILED = 'FAILED';

    public const STATUS_BLOCKED = 'BLOCKED';

    public const STATUS_RATE_LIMITED = 'RATE_LIMITED';

    protected $table = 'serpro_eventos_runs';

    protected function casts(): array
    {
        return [
            'environment' => SerproEnvironment::class,
            'result_consumed' => 'boolean',
            'one_shot_complete' => 'boolean',
            'simulated' => 'boolean',
            'progress' => 'array',
            'result_summary' => 'array',
            'not_before_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
            'solicited_at' => 'immutable_datetime',
            'obtained_at' => 'immutable_datetime',
        ];
    }

    public function office(): BelongsTo
    {
        return $this->belongsTo(Office::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function isOneShotConsumed(): bool
    {
        return $this->one_shot_complete || $this->result_consumed || $this->phase === self::PHASE_CONSUMED;
    }

    /**
     * @return array<string, mixed>
     */
    public function toSanitizedArray(): array
    {
        return [
            'id' => $this->id,
            'office_id' => $this->office_id,
            'client_id' => $this->client_id,
            'environment' => $this->environment instanceof SerproEnvironment
                ? $this->environment->value
                : (string) $this->environment,
            'person_type' => $this->person_type,
            'phase' => $this->phase,
            'protocol' => $this->protocol,
            'tempo_espera_medio_ms' => $this->tempo_espera_medio_ms,
            'tempo_limite_em_min' => $this->tempo_limite_em_min,
            'not_before_at' => $this->not_before_at?->toIso8601String(),
            'expires_at' => $this->expires_at?->toIso8601String(),
            'result_consumed' => $this->result_consumed,
            'one_shot_complete' => $this->one_shot_complete,
            'status' => $this->status,
            'correlation_id' => $this->correlation_id,
            'evento' => $this->evento,
            'contributors_in_batch' => $this->contributors_in_batch,
            'result_fingerprint' => $this->result_fingerprint,
            'error_code' => $this->error_code,
            'error_message' => $this->error_message,
            'simulated' => $this->simulated,
            'solicited_at' => $this->solicited_at?->toIso8601String(),
            'obtained_at' => $this->obtained_at?->toIso8601String(),
        ];
    }
}

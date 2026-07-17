<?php

namespace App\Models;

use App\Enums\CommunicationChannel;
use App\Enums\CommunicationDispatchStatus;
use App\Enums\CommunicationExecutionMode;
use App\Models\Concerns\BelongsToOffice;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Estrutura futura de rastreio. Esta change não cria dispatches em leitura/prévia.
 * Destinatário: mascarado + hash, nunca valor em claro.
 */
#[Fillable([
    'office_id',
    'client_id',
    'preference_id',
    'projection_id',
    'pgdasd_artifact_id',
    'module_key',
    'submodule_key',
    'period_key',
    'channel',
    'status',
    'recipient_masked',
    'recipient_hash',
    'idempotency_key',
    'template_key',
    'template_version',
    'provider',
    'provider_external_id',
    'queued_at',
    'sent_at',
    'delivered_at',
    'read_at',
    'failed_at',
    'canceled_at',
    'error_code',
    'error_message',
    'metadata',
])]
class ClientCommunicationDispatch extends Model
{
    use BelongsToOffice;

    protected function casts(): array
    {
        return [
            'channel' => CommunicationChannel::class,
            'status' => CommunicationDispatchStatus::class,
            'queued_at' => 'immutable_datetime',
            'sent_at' => 'immutable_datetime',
            'delivered_at' => 'immutable_datetime',
            'read_at' => 'immutable_datetime',
            'failed_at' => 'immutable_datetime',
            'canceled_at' => 'immutable_datetime',
            'metadata' => 'array',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function preference(): BelongsTo
    {
        return $this->belongsTo(ClientCommunicationPreference::class, 'preference_id');
    }

    public function projection(): BelongsTo
    {
        return $this->belongsTo(TaxObligationProjection::class, 'projection_id');
    }

    public function pgdasdArtifact(): BelongsTo
    {
        return $this->belongsTo(PgdasdArtifact::class, 'pgdasd_artifact_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(ClientCommunicationEvent::class, 'dispatch_id')
            ->orderByDesc('occurred_at')
            ->orderByDesc('id');
    }

    /**
     * @return array<string, mixed>
     */
    public function toPublicArray(): array
    {
        return [
            'id' => $this->id,
            'client_id' => $this->client_id,
            'channel' => $this->channel?->value ?? $this->getRawOriginal('channel'),
            'status' => $this->status?->value ?? $this->getRawOriginal('status'),
            'period_key' => $this->period_key,
            'execution_mode' => CommunicationExecutionMode::TemplateOnly->value,
            'recipient_masked' => $this->recipient_masked,
            'queued_at' => $this->queued_at?->toIso8601String(),
            'sent_at' => $this->sent_at?->toIso8601String(),
            'delivered_at' => $this->delivered_at?->toIso8601String(),
            'read_at' => $this->read_at?->toIso8601String(),
            'failed_at' => $this->failed_at?->toIso8601String(),
            'canceled_at' => $this->canceled_at?->toIso8601String(),
            'events' => $this->relationLoaded('events')
                ? $this->events->map(static function (ClientCommunicationEvent $e): array {
                    return [
                        'id' => $e->id,
                        'status' => $e->status,
                        'occurred_at' => $e->occurred_at?->toIso8601String(),
                    ];
                })->values()->all()
                : [],
        ];
    }
}

<?php

namespace App\Services\Communication\Events;

use App\Contracts\CommunicationTransport;
use App\DTO\Communication\CommunicationPayloadDigest;
use App\DTO\Communication\GatewayEventData;
use App\Enums\Communication\ConversationStatus;
use App\Enums\Communication\GatewayEventType;
use App\Enums\Communication\InboxStatus;
use App\Enums\Communication\MessageDirection;
use App\Enums\Communication\MessageKind;
use App\Enums\Communication\MessageSource;
use App\Enums\Communication\MessageStatus;
use App\Enums\CommunicationChannel;
use App\Exceptions\GatewayEventConflictException;
use App\Models\CommunicationAttachment;
use App\Models\CommunicationContact;
use App\Models\CommunicationConversation;
use App\Models\CommunicationEvent;
use App\Models\CommunicationIdentity;
use App\Models\CommunicationInbox;
use App\Models\CommunicationMessage;
use App\Services\Communication\Automation\FiscalDispatchStatusProjector;
use App\Services\Communication\Media\CommunicationMediaStore;
use App\Services\Communication\Pairing\CommunicationPairingStateStore;
use App\Services\Communication\WhatsappAddressNormalizer;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

final readonly class GatewayEventIngestor
{
    public function __construct(
        private WhatsappAddressNormalizer $normalizer,
        private CommunicationTransport $transport,
        private CommunicationMediaStore $media,
        private CommunicationPairingStateStore $pairing,
        private CommunicationEventRecorder $events,
        private FiscalDispatchStatusProjector $fiscalStatuses,
    ) {}

    /** @return 'processed'|'duplicate' */
    public function ingest(GatewayEventData $incoming): string
    {
        $digest = CommunicationPayloadDigest::make($incoming->toArray());
        $existing = CommunicationEvent::query()->withoutGlobalScopes()
            ->where('gateway_event_id', $incoming->gatewayEventId)
            ->first();
        if ($existing !== null) {
            if (! hash_equals((string) $existing->payload_digest, $digest)) {
                throw new GatewayEventConflictException('Gateway event ID reutilizado com conteúdo diferente.');
            }

            return 'duplicate';
        }

        $inbox = CommunicationInbox::query()->withoutGlobalScopes()
            ->where('session_id', $incoming->sessionId)
            ->firstOrFail();

        $storedMedia = null;
        if ($incoming->type === GatewayEventType::MessageReceived && is_string($incoming->payload['spool_id'] ?? null)) {
            $expectedSha = strtolower((string) ($incoming->payload['media_sha256'] ?? ''));
            $expectedSize = (int) ($incoming->payload['media_size_bytes'] ?? -1);
            if (! preg_match('/^[a-f0-9]{64}$/', $expectedSha) || $expectedSize < 0) {
                throw new RuntimeException('Descriptor de mídia do gateway inválido.');
            }
            $storedMedia = $this->media->putStream(
                $this->transport->downloadMedia((string) $incoming->payload['spool_id']),
                [
                    'office_id' => (int) $inbox->office_id,
                    'inbox_id' => (int) $inbox->id,
                    'gateway_event_id' => $incoming->gatewayEventId,
                    'sha256' => $expectedSha,
                ],
            );
            if ($storedMedia['size_bytes'] !== $expectedSize || ! hash_equals($expectedSha, $storedMedia['sha256'])) {
                $this->media->delete($storedMedia['object_id']);
                throw new RuntimeException('Mídia recebida não corresponde ao descriptor do gateway.');
            }
        }

        try {
            $result = DB::transaction(function () use ($incoming, $digest, $inbox, $storedMedia): string {
                $duplicate = CommunicationEvent::query()->withoutGlobalScopes()
                    ->where('gateway_event_id', $incoming->gatewayEventId)
                    ->lockForUpdate()
                    ->first();
                if ($duplicate !== null) {
                    if (! hash_equals((string) $duplicate->payload_digest, $digest)) {
                        throw new GatewayEventConflictException('Gateway event ID reutilizado com conteúdo diferente.');
                    }

                    return 'duplicate';
                }

                [$conversationId, $messageId, $safePayload] = match ($incoming->type) {
                    GatewayEventType::MessageReceived => $this->ingestInbound($incoming, $inbox, $storedMedia),
                    GatewayEventType::MessageStatusChanged => $this->ingestReceipt($incoming, $inbox),
                    GatewayEventType::MessageActionReceived => $this->ingestMessageAction($incoming, $inbox),
                    GatewayEventType::SessionStatusChanged => $this->ingestSessionStatus($incoming, $inbox),
                    GatewayEventType::PairingUpdated => $this->ingestPairing($incoming, $inbox),
                    GatewayEventType::MediaReady => [null, null, ['media_ready' => true]],
                    GatewayEventType::HistorySynced => $this->ingestHistory($incoming, $inbox),
                    GatewayEventType::ChatPresenceChanged,
                    GatewayEventType::ContactPresenceChanged => $this->ingestPresenceSignal($incoming, $inbox),
                    GatewayEventType::ContactProfileChanged => $this->ingestContactProfile($incoming, $inbox),
                    GatewayEventType::ContactIdentityChanged => $this->ingestIdentityChange($incoming, $inbox),
                    GatewayEventType::PrivacySettingsChanged,
                    GatewayEventType::BlocklistChanged,
                    GatewayEventType::ChatStateChanged,
                    GatewayEventType::SyncStatusChanged,
                    GatewayEventType::MediaRetryUpdated,
                    GatewayEventType::GatewayAlert => [null, null, $this->allowlistedStatePayload($incoming)],
                };

                $this->events->record(
                    officeId: (int) $inbox->office_id,
                    type: $incoming->type->value,
                    payload: $safePayload,
                    inboxId: (int) $inbox->id,
                    conversationId: $conversationId,
                    messageId: $messageId,
                    gatewayEventId: $incoming->gatewayEventId,
                    payloadDigest: $digest,
                    occurredAt: $incoming->occurredAt,
                );

                return 'processed';
            });
            if ($result === 'duplicate' && $storedMedia !== null) {
                $this->media->delete($storedMedia['object_id']);
            }

            return $result;
        } catch (Throwable $error) {
            if ($storedMedia !== null) {
                $this->media->delete($storedMedia['object_id']);
            }
            throw $error;
        }
    }

    /**
     * @param  array{object_id:string,size_bytes:int,sha256:string}|null  $storedMedia
     * @return array{0:int,1:int,2:array<string,mixed>}
     */
    private function ingestInbound(GatewayEventData $incoming, CommunicationInbox $inbox, ?array $storedMedia): array
    {
        $history = (bool) ($incoming->payload['history'] ?? false);
        $providerId = (string) ($incoming->payload['provider_message_id'] ?? '');
        if (! preg_match('/^[A-Za-z0-9][A-Za-z0-9._:-]{2,127}$/', $providerId)) {
            throw new RuntimeException('provider_message_id inválido.');
        }
        $existing = CommunicationMessage::query()->withoutGlobalScopes()
            ->where('inbox_id', $inbox->id)
            ->where('provider_message_id', $providerId)
            ->first();
        if ($existing !== null) {
            return [(int) $existing->conversation_id, (int) $existing->id, ['provider_message_id' => $providerId]];
        }

        $address = $this->normalizer->normalize((string) ($incoming->payload['from'] ?? ''));
        $addressHash = hash('sha256', $address);
        $identity = CommunicationIdentity::query()->withoutGlobalScopes()
            ->where('office_id', $inbox->office_id)
            ->where('channel', CommunicationChannel::Whatsapp->value)
            ->where('address_hash', $addressHash)
            ->lockForUpdate()
            ->first();
        if ($identity === null) {
            $contact = CommunicationContact::query()->withoutGlobalScopes()->create([
                'office_id' => $inbox->office_id,
                'is_provisional' => true,
                'is_active' => true,
            ]);
            $identity = CommunicationIdentity::query()->withoutGlobalScopes()->create([
                'office_id' => $inbox->office_id,
                'contact_id' => $contact->id,
                'channel' => CommunicationChannel::Whatsapp,
                'address_encrypted' => $address,
                'address_hash' => $addressHash,
                'address_masked' => $this->maskAddress($address),
                'is_active' => true,
                'last_seen_at' => $incoming->occurredAt,
            ]);
        } else {
            $identity->forceFill(['last_seen_at' => $incoming->occurredAt])->save();
        }

        CommunicationIdentity::query()->withoutGlobalScopes()->whereKey($identity->id)->lockForUpdate()->first();
        $conversationQuery = CommunicationConversation::query()->withoutGlobalScopes()
            ->where('inbox_id', $inbox->id)
            ->where('identity_id', $identity->id)
            ->lockForUpdate();
        if (! $history) {
            $conversationQuery->where('status', '<>', ConversationStatus::Resolved->value);
        }
        $conversation = $conversationQuery->orderByDesc('last_message_at')->orderByDesc('id')->first();
        if ($conversation === null) {
            $conversation = CommunicationConversation::query()->withoutGlobalScopes()->create([
                'office_id' => $inbox->office_id,
                'inbox_id' => $inbox->id,
                'identity_id' => $identity->id,
                'status' => $history ? ConversationStatus::Resolved : ConversationStatus::Open,
                'work_department_id' => $inbox->work_department_id,
                'last_message_at' => $incoming->occurredAt,
                'resolved_at' => $history ? $incoming->occurredAt : null,
            ]);
            $clientIds = $identity->clientLinks()->withoutGlobalScopes()->pluck('client_id');
            foreach ($clientIds as $clientId) {
                DB::table('communication_conversation_clients')->insertOrIgnore([
                    'office_id' => $inbox->office_id,
                    'conversation_id' => $conversation->id,
                    'client_id' => $clientId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        $kind = MessageKind::tryFrom(strtoupper((string) ($incoming->payload['kind'] ?? 'TEXT')))
            ?? MessageKind::Text;
        $body = trim((string) ($incoming->payload['text'] ?? ''));
        $occurredAt = isset($incoming->payload['occurred_at'])
            ? Carbon::parse((string) $incoming->payload['occurred_at'])->toImmutable()
            : Carbon::instance($incoming->occurredAt)->toImmutable();
        $direction = match (strtoupper((string) ($incoming->payload['direction'] ?? 'INBOUND'))) {
            'OUTBOUND' => MessageDirection::Outbound,
            'INTERNAL' => MessageDirection::Internal,
            default => MessageDirection::Inbound,
        };
        $replyTo = null;
        $replyProviderId = (string) ($incoming->payload['reply_to_provider_message_id']
            ?? data_get($incoming->payload, 'reply_to.provider_message_id')
            ?? '');
        if ($replyProviderId !== '') {
            $replyTo = CommunicationMessage::query()->withoutGlobalScopes()
                ->where('inbox_id', $inbox->id)
                ->where('conversation_id', $conversation->id)
                ->where('provider_message_id', $replyProviderId)
                ->value('id');
        }
        $metadata = array_filter([
            'history' => $history ?: null,
            'ephemeral' => ($incoming->payload['ephemeral'] ?? false) ?: null,
            'view_once' => ($incoming->payload['view_once'] ?? false) ?: null,
            'media_state' => is_string($incoming->payload['media_state'] ?? null)
                ? $incoming->payload['media_state']
                : null,
            'media_error_code' => is_string($incoming->payload['media_error_code'] ?? null)
                ? $incoming->payload['media_error_code']
                : null,
            'location' => is_array($incoming->payload['location'] ?? null) ? $incoming->payload['location'] : null,
            'contact' => is_array($incoming->payload['contact'] ?? null) ? $incoming->payload['contact'] : null,
            'poll' => is_array($incoming->payload['poll'] ?? null) ? $incoming->payload['poll'] : null,
            'interactive' => is_array($incoming->payload['interactive'] ?? null) ? $incoming->payload['interactive'] : null,
        ], static fn (mixed $value): bool => $value !== null);
        $message = CommunicationMessage::query()->withoutGlobalScopes()->create([
            'office_id' => $inbox->office_id,
            'inbox_id' => $inbox->id,
            'conversation_id' => $conversation->id,
            'identity_id' => $identity->id,
            'reply_to_message_id' => $replyTo,
            'direction' => $direction,
            'kind' => $kind,
            'source' => MessageSource::Gateway,
            'status' => $direction === MessageDirection::Inbound ? MessageStatus::Delivered : MessageStatus::Sent,
            'body_encrypted' => $body !== '' ? $body : null,
            'provider_message_id' => $providerId,
            'gateway_event_id' => $incoming->gatewayEventId,
            'content_digest' => hash('sha256', implode('|', [$kind->value, $body, $storedMedia['sha256'] ?? ''])),
            'metadata' => $metadata,
            'occurred_at' => $occurredAt,
            'sent_at' => $direction === MessageDirection::Inbound ? null : $occurredAt,
            'delivered_at' => $direction === MessageDirection::Inbound ? $occurredAt : null,
        ]);

        if ($storedMedia !== null) {
            CommunicationAttachment::query()->withoutGlobalScopes()->create([
                'office_id' => $inbox->office_id,
                'message_id' => $message->id,
                'object_id' => $storedMedia['object_id'],
                'original_name_encrypted' => $this->safeFilename(
                    (string) ($incoming->payload['filename'] ?? ''),
                    $kind,
                ),
                'mime_type' => $this->safeMime((string) ($incoming->payload['mime_type'] ?? 'application/octet-stream')),
                'size_bytes' => $storedMedia['size_bytes'],
                'sha256' => $storedMedia['sha256'],
                'storage_context' => [
                    'office_id' => (int) $inbox->office_id,
                    'inbox_id' => (int) $inbox->id,
                    'gateway_event_id' => $incoming->gatewayEventId,
                    'sha256' => $storedMedia['sha256'],
                ],
            ]);
        }
        if (! $history) {
            $conversation->forceFill([
                'status' => ConversationStatus::Open,
                'snoozed_until' => null,
                'last_message_at' => $occurredAt,
                'lock_version' => (int) $conversation->lock_version + 1,
            ])->save();
        } elseif ($conversation->last_message_at === null || $conversation->last_message_at->isBefore($occurredAt)) {
            $conversation->forceFill(['last_message_at' => $occurredAt])->save();
        }

        return [(int) $conversation->id, (int) $message->id, [
            'provider_message_id' => $providerId,
            'kind' => $kind->value,
            'has_media' => $storedMedia !== null,
            'history' => $history,
        ]];
    }

    /** @return array{0:?int,1:?int,2:array<string,mixed>} */
    private function ingestMessageAction(GatewayEventData $incoming, CommunicationInbox $inbox): array
    {
        $targetProviderId = (string) ($incoming->payload['target_message_id']
            ?? $incoming->payload['target_provider_message_id']
            ?? '');
        $action = strtoupper((string) ($incoming->payload['action'] ?? ''));
        if ($targetProviderId === '' || ! in_array($action, ['EDIT', 'REVOKE', 'REACTION', 'POLL_VOTE', 'INTERACTIVE_RESPONSE'], true)) {
            throw new RuntimeException('Ação de mensagem do gateway inválida.');
        }

        $message = CommunicationMessage::query()->withoutGlobalScopes()
            ->where('inbox_id', $inbox->id)
            ->where('provider_message_id', $targetProviderId)
            ->lockForUpdate()
            ->first();
        if ($message === null) {
            return [null, null, ['action' => $action, 'target_message_id' => $targetProviderId, 'orphan' => true]];
        }

        $sender = $this->normalizer->normalize((string) ($incoming->payload['from'] ?? ''));
        $identity = CommunicationIdentity::query()->withoutGlobalScopes()->find($message->identity_id);
        if ($identity === null || ! hash_equals((string) $identity->address_hash, hash('sha256', $sender))) {
            throw new RuntimeException('Ação não pertence à identidade da mensagem alvo.');
        }

        $metadata = is_array($message->metadata) ? $message->metadata : [];
        $actorKey = hash('sha256', $sender);
        switch ($action) {
            case 'EDIT':
                $text = trim((string) ($incoming->payload['text'] ?? ''));
                if ($text === '') {
                    throw new RuntimeException('Edição recebida sem conteúdo.');
                }
                $message->body_encrypted = $text;
                $metadata['edited_at'] = $incoming->occurredAt->format(DATE_ATOM);
                break;
            case 'REVOKE':
                $message->body_encrypted = null;
                $metadata['revoked'] = true;
                $metadata['revoked_at'] = $incoming->occurredAt->format(DATE_ATOM);
                break;
            case 'REACTION':
                $reactions = is_array($metadata['reactions'] ?? null) ? $metadata['reactions'] : [];
                $emoji = (string) ($incoming->payload['emoji'] ?? '');
                if (($incoming->payload['removed'] ?? false) || $emoji === '') {
                    unset($reactions[$actorKey]);
                } else {
                    $reactions[$actorKey] = mb_substr($emoji, 0, 32);
                }
                $metadata['reactions'] = $reactions;
                break;
            case 'POLL_VOTE':
                $votes = is_array($metadata['poll_votes'] ?? null) ? $metadata['poll_votes'] : [];
                $votes[$actorKey] = [
                    'option_names' => array_values(array_filter(
                        is_array($incoming->payload['option_names'] ?? null) ? $incoming->payload['option_names'] : [],
                        'is_string',
                    )),
                    'option_hashes' => array_values(array_filter(
                        is_array($incoming->payload['option_hashes'] ?? null) ? $incoming->payload['option_hashes'] : [],
                        static fn (mixed $value): bool => is_string($value) && preg_match('/^[a-f0-9]{64}$/', $value) === 1,
                    )),
                ];
                $metadata['poll_votes'] = $votes;
                break;
            case 'INTERACTIVE_RESPONSE':
                $metadata['interactive_response'] = array_filter([
                    'text' => is_string($incoming->payload['text'] ?? null) ? $incoming->payload['text'] : null,
                    'selected_id' => is_string($incoming->payload['selected_id'] ?? null) ? $incoming->payload['selected_id'] : null,
                ], static fn (mixed $value): bool => $value !== null);
                break;
        }
        $metadata['last_action_event_id'] = $incoming->gatewayEventId;
        $message->metadata = $metadata;
        $message->save();

        return [(int) $message->conversation_id, (int) $message->id, array_filter([
            'action' => $action,
            'target_message_id' => $targetProviderId,
            'provider_message_id' => $incoming->payload['provider_message_id'] ?? null,
            'emoji' => $action === 'REACTION' ? (string) ($incoming->payload['emoji'] ?? '') : null,
        ], static fn (mixed $value): bool => $value !== null)];
    }

    /** @return array{0:null,1:null,2:array<string,mixed>} */
    private function ingestHistory(GatewayEventData $incoming, CommunicationInbox $inbox): array
    {
        $messages = $incoming->payload['messages'] ?? null;
        if (! is_array($messages) || count($messages) > 100) {
            throw new RuntimeException('Batch de histórico do gateway inválido.');
        }
        $imported = 0;
        $duplicates = 0;
        foreach ($messages as $index => $payload) {
            if (! is_array($payload)) {
                throw new RuntimeException('Mensagem de histórico inválida.');
            }
            $payload['history'] = true;
            $synthetic = new GatewayEventData(
                gatewayEventId: 'history-'.substr(hash('sha256', $incoming->gatewayEventId.'|'.$index.'|'.($payload['provider_message_id'] ?? '')), 0, 48),
                sessionId: $incoming->sessionId,
                type: GatewayEventType::MessageReceived,
                occurredAt: $incoming->occurredAt,
                payload: $payload,
            );
            $before = CommunicationMessage::query()->withoutGlobalScopes()
                ->where('inbox_id', $inbox->id)
                ->where('provider_message_id', (string) ($payload['provider_message_id'] ?? ''))
                ->exists();
            $this->ingestInbound($synthetic, $inbox, null);
            if ($before) {
                $duplicates++;
            } else {
                $imported++;
            }
        }

        return [null, null, array_filter([
            'batch_id' => $incoming->payload['batch_id'] ?? null,
            'complete' => (bool) ($incoming->payload['complete'] ?? false),
            'imported_count' => $imported,
            'duplicate_count' => $duplicates,
            'rejected_count' => (int) ($incoming->payload['rejected_count'] ?? 0),
        ], static fn (mixed $value): bool => $value !== null)];
    }

    /** @return array{0:?int,1:null,2:array<string,mixed>} */
    private function ingestPresenceSignal(GatewayEventData $incoming, CommunicationInbox $inbox): array
    {
        [$identity, $conversation] = $this->knownContext($inbox, (string) ($incoming->payload['from'] ?? ''));
        $safe = array_filter([
            'from' => $incoming->payload['from'] ?? null,
            'presence' => $incoming->payload['presence'] ?? null,
            'available' => isset($incoming->payload['available']) ? (bool) $incoming->payload['available'] : null,
            'media' => $incoming->payload['media'] ?? null,
            'last_seen' => $incoming->payload['last_seen'] ?? null,
            'ttl_seconds' => isset($incoming->payload['ttl_seconds']) ? (int) $incoming->payload['ttl_seconds'] : null,
        ], static fn (mixed $value): bool => $value !== null);
        if ($identity !== null && isset($safe['last_seen'])) {
            $identity->forceFill(['last_seen_at' => Carbon::parse((string) $safe['last_seen'])])->save();
        }

        return [$conversation?->id, null, $safe];
    }

    /** @return array{0:?int,1:null,2:array<string,mixed>} */
    private function ingestContactProfile(GatewayEventData $incoming, CommunicationInbox $inbox): array
    {
        $user = (string) ($incoming->payload['user'] ?? '');
        [$identity, $conversation] = $this->knownContext($inbox, $user);
        $safe = array_filter([
            'user' => $user,
            'display_name' => $incoming->payload['display_name'] ?? null,
            'business_name' => $incoming->payload['business_name'] ?? null,
            'picture_id' => $incoming->payload['picture_id'] ?? null,
            'about' => $incoming->payload['about'] ?? null,
        ], static fn (mixed $value): bool => is_bool($value) || (is_scalar($value) && (string) $value !== ''));
        if ($identity !== null) {
            $contact = $identity->contact()->withoutGlobalScopes()->first();
            if ($contact !== null) {
                $metadata = is_array($contact->metadata) ? $contact->metadata : [];
                $metadata['whatsapp_profile'] = $safe;
                $attributes = ['metadata' => $metadata];
                $displayName = trim((string) ($safe['display_name'] ?? $safe['business_name'] ?? ''));
                if ($contact->is_provisional && $displayName !== '') {
                    $attributes['name'] = $displayName;
                }
                $contact->forceFill($attributes)->save();
            }
        }

        return [$conversation?->id, null, $safe];
    }

    /** @return array{0:?int,1:null,2:array<string,mixed>} */
    private function ingestIdentityChange(GatewayEventData $incoming, CommunicationInbox $inbox): array
    {
        $user = (string) ($incoming->payload['user'] ?? '');
        [, $conversation] = $this->knownContext($inbox, $user);

        return [$conversation?->id, null, [
            'user' => $user,
            'change' => (string) ($incoming->payload['change'] ?? 'IDENTITY_CHANGED'),
        ]];
    }

    /** @return array{0:?CommunicationIdentity,1:?CommunicationConversation} */
    private function knownContext(CommunicationInbox $inbox, string $address): array
    {
        $normalized = $this->normalizer->normalize($address);
        $identity = CommunicationIdentity::query()->withoutGlobalScopes()
            ->where('office_id', $inbox->office_id)
            ->where('channel', CommunicationChannel::Whatsapp->value)
            ->where('address_hash', hash('sha256', $normalized))
            ->first();
        $conversation = $identity === null ? null : CommunicationConversation::query()->withoutGlobalScopes()
            ->where('inbox_id', $inbox->id)
            ->where('identity_id', $identity->id)
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->first();

        return [$identity, $conversation];
    }

    /** @return array<string,mixed> */
    private function allowlistedStatePayload(GatewayEventData $incoming): array
    {
        $allowed = match ($incoming->type) {
            GatewayEventType::PrivacySettingsChanged => ['settings'],
            GatewayEventType::BlocklistChanged => ['action', 'users'],
            GatewayEventType::ChatStateChanged => ['to', 'action', 'value', 'target_message_id', 'delete_media', 'duration_seconds'],
            GatewayEventType::SyncStatusChanged => ['component', 'status', 'error_code'],
            GatewayEventType::MediaRetryUpdated => ['provider_message_id', 'status', 'error_code'],
            GatewayEventType::GatewayAlert => ['code', 'severity', 'retryable', 'retry_after_seconds'],
            default => [],
        };

        return array_intersect_key($incoming->payload, array_flip($allowed));
    }

    /** @return array{0:?int,1:?int,2:array<string,mixed>} */
    private function ingestReceipt(GatewayEventData $incoming, CommunicationInbox $inbox): array
    {
        $providerId = (string) ($incoming->payload['provider_message_id'] ?? '');
        $status = MessageStatus::tryFrom(strtoupper((string) ($incoming->payload['status'] ?? '')));
        if ($providerId === '' || $status === null) {
            throw new RuntimeException('Receipt do gateway inválido.');
        }
        $message = CommunicationMessage::query()->withoutGlobalScopes()
            ->where('inbox_id', $inbox->id)
            ->where('provider_message_id', $providerId)
            ->lockForUpdate()
            ->first();
        if ($message === null) {
            return [null, null, ['provider_message_id' => $providerId, 'status' => $status->value, 'orphan' => true]];
        }
        $current = $message->status instanceof MessageStatus
            ? $message->status
            : MessageStatus::from((string) $message->status);
        $merged = $current->merge($status);
        if ($merged !== $current) {
            $timestampField = match ($merged) {
                MessageStatus::Accepted => 'accepted_at',
                MessageStatus::Sent => 'sent_at',
                MessageStatus::Delivered => 'delivered_at',
                MessageStatus::Read => 'read_at',
                MessageStatus::Failed => 'failed_at',
                default => null,
            };
            $attributes = ['status' => $merged];
            if ($timestampField !== null) {
                $attributes[$timestampField] = $incoming->occurredAt;
            }
            $message->forceFill($attributes)->save();
        }
        $this->fiscalStatuses->project(
            $message,
            $merged,
            $incoming->occurredAt,
            'WHATSAPP_GATEWAY',
            $incoming->gatewayEventId,
            CommunicationPayloadDigest::make($incoming->payload),
        );

        return [(int) $message->conversation_id, (int) $message->id, [
            'provider_message_id' => $providerId,
            'status' => $merged->value,
        ]];
    }

    /** @return array{0:null,1:null,2:array<string,mixed>} */
    private function ingestSessionStatus(GatewayEventData $incoming, CommunicationInbox $inbox): array
    {
        $status = InboxStatus::tryFrom(strtoupper((string) ($incoming->payload['status'] ?? '')));
        if ($status === null) {
            throw new RuntimeException('Status de sessão inválido.');
        }
        $inbox->forceFill([
            'status' => $status,
            'connected_at' => $status === InboxStatus::Connected ? $incoming->occurredAt : $inbox->connected_at,
            'last_seen_at' => $incoming->occurredAt,
            'revoked_at' => $status === InboxStatus::Revoked ? $incoming->occurredAt : null,
            'lock_version' => (int) $inbox->lock_version + 1,
        ])->save();
        if ($status === InboxStatus::Connected || $status === InboxStatus::Revoked) {
            $this->pairing->forget((int) $inbox->id);
        }

        return [null, null, ['status' => $status->value]];
    }

    /** @return array{0:null,1:null,2:array<string,mixed>} */
    private function ingestPairing(GatewayEventData $incoming, CommunicationInbox $inbox): array
    {
        $event = strtoupper((string) ($incoming->payload['event'] ?? ''));
        if ($event === '') {
            throw new RuntimeException('Evento de pairing inválido.');
        }
        if (in_array($event, ['CODE', 'QR', 'QR_AVAILABLE', 'PASSKEY_REQUIRED', 'PASSKEY_CONFIRMATION_REQUIRED'], true)) {
            $this->pairing->put((int) $inbox->id, $incoming->payload);
            $inbox->forceFill(['status' => InboxStatus::Pairing, 'lock_version' => (int) $inbox->lock_version + 1])->save();
        } elseif (in_array($event, ['SUCCESS', 'PAIRED'], true)) {
            $this->pairing->forget((int) $inbox->id);
            $inbox->forceFill([
                'status' => InboxStatus::Connected,
                'connected_at' => $incoming->occurredAt,
                'last_seen_at' => $incoming->occurredAt,
                'lock_version' => (int) $inbox->lock_version + 1,
            ])->save();
        } else {
            $inbox->forceFill(['status' => InboxStatus::Degraded, 'lock_version' => (int) $inbox->lock_version + 1])->save();
        }

        return [null, null, [
            'event' => $event,
            'expires_at' => $incoming->payload['expires_at'] ?? null,
            'error_code' => $incoming->payload['error_code'] ?? null,
        ]];
    }

    private function maskAddress(string $address): string
    {
        return substr($address, 0, min(3, strlen($address))).'•••••'.substr($address, -4);
    }

    private function safeMime(string $mime): string
    {
        $mime = strtolower(trim(explode(';', $mime, 2)[0]));

        return preg_match('#^[a-z0-9.+-]+/[a-z0-9.+-]+$#', $mime) ? $mime : 'application/octet-stream';
    }

    private function safeFilename(string $filename, MessageKind $kind): string
    {
        $filename = basename(str_replace('\\', '/', trim($filename)));
        $filename = preg_replace('/[\x00-\x1F\x7F]/u', '', $filename) ?? '';
        if ($filename === '') {
            $filename = match ($kind) {
                MessageKind::Image => 'imagem',
                MessageKind::Audio => 'audio',
                MessageKind::Video => 'video',
                MessageKind::Sticker => 'sticker.webp',
                default => 'anexo',
            };
        }

        return mb_substr($filename, 0, 255);
    }
}

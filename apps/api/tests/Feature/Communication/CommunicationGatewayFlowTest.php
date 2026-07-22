<?php

namespace Tests\Feature\Communication;

use App\Contracts\CommunicationTransport;
use App\DTO\Communication\GatewayCommandData;
use App\DTO\Communication\GatewayCommandReceipt;
use App\DTO\Communication\GatewayQueryData;
use App\Enums\Communication\ConversationStatus;
use App\Enums\Communication\GatewayCommandType;
use App\Enums\Communication\GatewayEventType;
use App\Enums\Communication\InboxStatus;
use App\Enums\Communication\MessageDirection;
use App\Enums\Communication\MessageKind;
use App\Enums\Communication\MessageSource;
use App\Enums\Communication\MessageStatus;
use App\Enums\Communication\OutboxStatus;
use App\Enums\CommunicationChannel;
use App\Enums\CommunicationDispatchStatus;
use App\Enums\CommunicationExecutionMode;
use App\Events\CommunicationEventCommitted;
use App\Exceptions\CommunicationTransportException;
use App\Models\Client;
use App\Models\ClientCommunicationDispatch;
use App\Models\CommunicationAttachment;
use App\Models\CommunicationContact;
use App\Models\CommunicationConversation;
use App\Models\CommunicationEvent;
use App\Models\CommunicationIdentity;
use App\Models\CommunicationInbox;
use App\Models\CommunicationMessage;
use App\Models\CommunicationOutboxEntry;
use App\Models\Office;
use App\Services\Communication\Outbox\CommunicationOutboxDispatcher;
use App\Services\Communication\Security\CommunicationHmacSigner;
use GuzzleHttp\Psr7\Utils;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Psr\Http\Message\StreamInterface;
use Tests\TestCase;

final class CommunicationGatewayFlowTest extends TestCase
{
    use RefreshDatabase;

    private FakeCommunicationTransport $transport;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'communication.enabled' => true,
            'communication.gateway.enabled' => true,
            'communication.hmac.current_key_id' => 'test-key',
            'communication.hmac.current_secret' => str_repeat('h', 32),
            'communication.media.disk_root' => sys_get_temp_dir().'/communication-gateway-tests-'.Str::ulid(),
        ]);
        Event::fake([CommunicationEventCommitted::class]);
        $this->transport = new FakeCommunicationTransport;
        $this->app->instance(CommunicationTransport::class, $this->transport);
    }

    public function test_signed_inbound_is_idempotent_creates_provisional_timeline_and_rejects_conflict(): void
    {
        [, $inbox] = $this->context();
        $bytes = '%PDF-inbound-private';
        $event = $this->event($inbox, GatewayEventType::MessageReceived, 'gateway-inbound-0001', [
            'provider_message_id' => 'provider-inbound-0001',
            'from' => '+5511999990001',
            'kind' => 'DOCUMENT',
            'text' => 'Documento enviado',
            'spool_id' => 'spool-inbound-0001',
            'media_sha256' => hash('sha256', $bytes),
            'media_size_bytes' => strlen($bytes),
            'mime_type' => 'application/pdf',
            'filename' => '../comprovante.pdf',
        ]);
        $this->transport->media['spool-inbound-0001'] = $bytes;

        $this->postSignedEvent($event)->assertNoContent()->assertHeader('X-Communication-Result', 'processed');
        $this->postSignedEvent($event)->assertNoContent()->assertHeader('X-Communication-Result', 'duplicate');

        $conflicting = $event;
        $conflicting['payload']['text'] = 'Conteúdo conflitante';
        $this->postSignedEvent($conflicting)->assertStatus(409)->assertJson(['error' => 'EVENT_DIGEST_CONFLICT']);

        $this->assertDatabaseCount('communication_contacts', 1);
        $this->assertDatabaseHas('communication_contacts', ['is_provisional' => true]);
        $this->assertDatabaseCount('communication_identities', 1);
        $this->assertDatabaseCount('communication_conversations', 1);
        $this->assertDatabaseHas('communication_conversations', ['status' => ConversationStatus::Open->value]);
        $this->assertDatabaseCount('communication_messages', 1);
        $this->assertDatabaseCount('communication_attachments', 1);
        $this->assertDatabaseCount('communication_events', 1);
        $attachment = CommunicationAttachment::query()->withoutGlobalScopes()->firstOrFail();
        $this->assertSame(hash('sha256', $bytes), $attachment->sha256);
        $this->assertSame('comprovante.pdf', $attachment->original_name_encrypted);
        $this->assertSame(1, $this->transport->downloadCalls);
    }

    public function test_live_outbound_from_device_creates_message_reopens_pending_and_deduplicates_provider_id(): void
    {
        [$office, $inbox] = $this->context();
        [, $conversation] = $this->identityAndConversation($office, $inbox, ConversationStatus::Pending);

        $event = $this->event($inbox, GatewayEventType::MessageReceived, 'gateway-device-outbound-0001', [
            'provider_message_id' => 'provider-device-outbound-0001',
            'from' => '+5511999990002',
            'direction' => 'OUTBOUND',
            'kind' => 'TEXT',
            'text' => 'Enviado no celular',
        ]);

        $this->postSignedEvent($event)->assertNoContent()->assertHeader('X-Communication-Result', 'processed');
        $this->postSignedEvent($event)->assertNoContent()->assertHeader('X-Communication-Result', 'duplicate');

        $message = CommunicationMessage::query()->withoutGlobalScopes()
            ->where('provider_message_id', 'provider-device-outbound-0001')
            ->firstOrFail();
        $this->assertSame(MessageDirection::Outbound, $message->direction);
        $this->assertSame(MessageStatus::Sent, $message->status);
        $this->assertSame(MessageSource::Gateway, $message->source);
        $this->assertSame($conversation->id, $message->conversation_id);
        $this->assertSame(ConversationStatus::Open, $conversation->refresh()->status);
        $this->assertSame(1, CommunicationMessage::query()->withoutGlobalScopes()->count());
    }

    public function test_inbound_reopens_pending_conversation_and_receipts_never_regress_message_or_dispatch(): void
    {
        [$office, $inbox] = $this->context();
        [$identity, $conversation] = $this->identityAndConversation($office, $inbox, ConversationStatus::Pending);

        $this->postSignedEvent($this->event($inbox, GatewayEventType::MessageReceived, 'gateway-reply-0001', [
            'provider_message_id' => 'provider-reply-0001',
            'from' => '+5511999990002',
            'kind' => 'TEXT',
            'text' => 'Recebi, obrigado.',
        ]))->assertNoContent();

        $this->assertSame($conversation->id, CommunicationMessage::query()->withoutGlobalScopes()->firstOrFail()->conversation_id);
        $this->assertSame(ConversationStatus::Open, $conversation->refresh()->status);
        $this->assertNull($conversation->assignee_membership_id);

        $client = Client::factory()->create(['office_id' => $office->id]);
        $outbound = CommunicationMessage::query()->withoutGlobalScopes()->create([
            'office_id' => $office->id,
            'inbox_id' => $inbox->id,
            'conversation_id' => $conversation->id,
            'identity_id' => $identity->id,
            'direction' => MessageDirection::Outbound,
            'kind' => MessageKind::Text,
            'source' => MessageSource::FiscalAutomation,
            'status' => MessageStatus::Accepted,
            'body_encrypted' => 'Mensagem fiscal',
            'provider_message_id' => 'provider-outbound-0001',
            'content_digest' => hash('sha256', 'Mensagem fiscal'),
            'occurred_at' => now(),
        ]);
        $dispatch = ClientCommunicationDispatch::query()->withoutGlobalScopes()->create([
            'office_id' => $office->id,
            'client_id' => $client->id,
            'inbox_id' => $inbox->id,
            'identity_id' => $identity->id,
            'conversation_id' => $conversation->id,
            'message_id' => $outbound->id,
            'module_key' => 'simples_mei',
            'submodule_key' => 'pgdasd',
            'period_key' => '2026-06',
            'channel' => CommunicationChannel::Whatsapp,
            'execution_mode' => CommunicationExecutionMode::WhatsappNative,
            'status' => CommunicationDispatchStatus::Accepted,
            'recipient_masked' => '***0002',
            'recipient_hash' => hash('sha256', '+5511999990002'),
            'idempotency_key' => hash('sha256', 'receipt-test'),
        ]);
        $outbound->forceFill(['client_communication_dispatch_id' => $dispatch->id])->save();

        $this->postSignedEvent($this->event($inbox, GatewayEventType::MessageStatusChanged, 'gateway-receipt-read-0001', [
            'provider_message_id' => 'provider-outbound-0001',
            'status' => 'READ',
        ]))->assertNoContent();
        $this->postSignedEvent($this->event($inbox, GatewayEventType::MessageStatusChanged, 'gateway-receipt-sent-0001', [
            'provider_message_id' => 'provider-outbound-0001',
            'status' => 'SENT',
        ]))->assertNoContent();

        $this->assertSame(MessageStatus::Read, $outbound->refresh()->status);
        $this->assertNotNull($outbound->read_at);
        $this->assertSame(CommunicationDispatchStatus::Read, $dispatch->refresh()->status);
        $this->assertNotNull($dispatch->read_at);
        $this->assertDatabaseCount('client_communication_events', 1);
        $this->assertSame(3, CommunicationEvent::query()->withoutGlobalScopes()->count());
    }

    public function test_outbox_accepts_retries_and_terminally_classifies_failures(): void
    {
        [$office, $inbox] = $this->context();
        [$identity, $conversation] = $this->identityAndConversation($office, $inbox);
        $acceptedMessage = $this->outboundMessage($office, $inbox, $identity, $conversation, 'accepted');
        $accepted = $this->outbox($office, $inbox, $acceptedMessage, 'command-accepted-0001');
        $dispatcher = app(CommunicationOutboxDispatcher::class);

        $dispatcher->dispatch((int) $accepted->id);
        $this->assertSame(OutboxStatus::Accepted, $accepted->refresh()->status);
        $this->assertSame(MessageStatus::Accepted, $acceptedMessage->refresh()->status);

        $retryMessage = $this->outboundMessage($office, $inbox, $identity, $conversation, 'retry');
        $retry = $this->outbox($office, $inbox, $retryMessage, 'command-retry-0001');
        $this->transport->failures['command-retry-0001'] = new CommunicationTransportException('GATEWAY_TEMPORARY', true);
        $dispatcher->dispatch((int) $retry->id);
        $this->assertSame(OutboxStatus::Retry, $retry->refresh()->status);
        $this->assertSame(1, $retry->attempt_count);
        $this->assertSame(MessageStatus::Queued, $retryMessage->refresh()->status);

        $retry->forceFill(['attempt_count' => 9, 'available_at' => now()->subSecond()])->save();
        $dispatcher->dispatch((int) $retry->id);
        $this->assertSame(OutboxStatus::Dead, $retry->refresh()->status);
        $this->assertSame(MessageStatus::Unknown, $retryMessage->refresh()->status);

        $failedMessage = $this->outboundMessage($office, $inbox, $identity, $conversation, 'failed');
        $failed = $this->outbox($office, $inbox, $failedMessage, 'command-failed-0001');
        $this->transport->failures['command-failed-0001'] = new CommunicationTransportException('INVALID_DESTINATION', false);
        $dispatcher->dispatch((int) $failed->id);
        $this->assertSame(OutboxStatus::Dead, $failed->refresh()->status);
        $this->assertSame(MessageStatus::Failed, $failedMessage->refresh()->status);
    }

    /** @return array{Office,CommunicationInbox} */
    private function context(): array
    {
        $office = Office::factory()->create(['communication_enabled' => true]);
        $inbox = CommunicationInbox::query()->withoutGlobalScopes()->create([
            'office_id' => $office->id,
            'name' => 'Atendimento',
            'session_id' => 'session-'.Str::ulid(),
            'status' => InboxStatus::Connected,
            'is_enabled' => true,
            'is_default' => true,
        ]);

        return [$office, $inbox];
    }

    /** @return array{CommunicationIdentity,CommunicationConversation} */
    private function identityAndConversation(
        Office $office,
        CommunicationInbox $inbox,
        ConversationStatus $status = ConversationStatus::Open,
    ): array {
        $contact = CommunicationContact::query()->withoutGlobalScopes()->create([
            'office_id' => $office->id,
            'name' => 'Cliente',
            'is_active' => true,
        ]);
        $address = '+5511999990002';
        $identity = CommunicationIdentity::query()->withoutGlobalScopes()->create([
            'office_id' => $office->id,
            'contact_id' => $contact->id,
            'channel' => CommunicationChannel::Whatsapp,
            'address_encrypted' => $address,
            'address_hash' => hash('sha256', $address),
            'address_masked' => '***0002',
            'is_active' => true,
        ]);
        $conversation = CommunicationConversation::query()->withoutGlobalScopes()->create([
            'office_id' => $office->id,
            'inbox_id' => $inbox->id,
            'identity_id' => $identity->id,
            'status' => $status,
            'last_message_at' => now(),
        ]);

        return [$identity, $conversation];
    }

    private function outboundMessage(
        Office $office,
        CommunicationInbox $inbox,
        CommunicationIdentity $identity,
        CommunicationConversation $conversation,
        string $suffix,
    ): CommunicationMessage {
        return CommunicationMessage::query()->withoutGlobalScopes()->create([
            'office_id' => $office->id,
            'inbox_id' => $inbox->id,
            'conversation_id' => $conversation->id,
            'identity_id' => $identity->id,
            'direction' => MessageDirection::Outbound,
            'kind' => MessageKind::Text,
            'source' => MessageSource::Human,
            'status' => MessageStatus::Queued,
            'body_encrypted' => 'Mensagem '.$suffix,
            'provider_message_id' => 'provider-'.$suffix.'-0001',
            'content_digest' => hash('sha256', $suffix),
            'occurred_at' => now(),
        ]);
    }

    private function outbox(
        Office $office,
        CommunicationInbox $inbox,
        CommunicationMessage $message,
        string $commandId,
    ): CommunicationOutboxEntry {
        $identity = CommunicationIdentity::query()->withoutGlobalScopes()->findOrFail($message->identity_id);
        $payload = [
            'to' => (string) $identity->address_encrypted,
            'kind' => 'TEXT',
            'text' => $message->body_encrypted,
        ];

        return CommunicationOutboxEntry::query()->withoutGlobalScopes()->create([
            'office_id' => $office->id,
            'inbox_id' => $inbox->id,
            'message_id' => $message->id,
            'command_id' => $commandId,
            'session_id' => $inbox->session_id,
            'type' => GatewayCommandType::SendMessage,
            'payload_encrypted' => $payload,
            'payload_digest' => hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR)),
            'status' => OutboxStatus::Pending,
            'available_at' => now()->subSecond(),
        ]);
    }

    /** @param array<string,mixed> $payload */
    private function event(
        CommunicationInbox $inbox,
        GatewayEventType $type,
        string $gatewayEventId,
        array $payload,
    ): array {
        return [
            'contract_version' => 'v1',
            'gateway_event_id' => $gatewayEventId,
            'session_id' => $inbox->session_id,
            'type' => $type->value,
            'occurred_at' => now()->toIso8601String(),
            'payload' => $payload,
        ];
    }

    /** @param array<string,mixed> $event */
    private function postSignedEvent(array $event)
    {
        $path = '/api/internal/v1/communication/gateway/events';
        $body = json_encode($event, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        $headers = app(CommunicationHmacSigner::class)->headers('POST', $path, $body);

        return $this->json('POST', $path, $event, $headers, JSON_UNESCAPED_SLASHES);
    }
}

final class FakeCommunicationTransport implements CommunicationTransport
{
    /** @var array<string,string> */
    public array $media = [];

    /** @var array<string,CommunicationTransportException> */
    public array $failures = [];

    public int $downloadCalls = 0;

    public function dispatch(GatewayCommandData $command): GatewayCommandReceipt
    {
        if (isset($this->failures[$command->commandId])) {
            throw $this->failures[$command->commandId];
        }

        return new GatewayCommandReceipt($command->commandId, false);
    }

    public function query(GatewayQueryData $query): array
    {
        return [
            'query_id' => $query->queryId,
            'type' => $query->type->value,
            'result' => [],
        ];
    }

    public function sessionStatus(string $sessionId): array
    {
        return [
            'session_id' => $sessionId,
            'status' => 'CONNECTED',
            'desired_connected' => true,
            'reconnect_count' => 0,
        ];
    }

    public function downloadMedia(string $spoolId): StreamInterface
    {
        $this->downloadCalls++;

        return Utils::streamFor($this->media[$spoolId] ?? '');
    }
}

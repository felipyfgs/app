<?php

namespace App\DTO\Communication;

use App\Enums\Communication\GatewayCommandType;
use App\Enums\Communication\GatewayQueryType;
use BackedEnum;
use InvalidArgumentException;

final class GatewayContractPayload
{
    /** @var list<string> */
    private const FORBIDDEN_KEYS = [
        'access_token',
        'credentials',
        'device_jid',
        'direct_path',
        'media_key',
        'node',
        'protobuf',
        'qr',
        'qr_code',
        'raw',
        'raw_event',
        'raw_node',
        'raw_proto',
        'refresh_token',
        'thumbnail_base64',
        'token',
    ];

    /** @var array<string, array{allowed: list<string>, required: list<string>}> */
    private const COMMAND_SHAPES = [
        'SESSION_PROVISION' => ['allowed' => ['desired_connected'], 'required' => []],
        'SESSION_PAIR' => ['allowed' => [], 'required' => []],
        'SESSION_PAIR_PHONE' => ['allowed' => ['phone', 'show_push_notification'], 'required' => ['phone']],
        'SESSION_PASSKEY_RESPOND' => ['allowed' => ['id', 'client_data_json', 'authenticator_data', 'signature'], 'required' => ['id', 'client_data_json', 'authenticator_data', 'signature']],
        'SESSION_PASSKEY_CONFIRM' => ['allowed' => ['id', 'confirm'], 'required' => ['id', 'confirm']],
        'SESSION_CONNECT' => ['allowed' => [], 'required' => []],
        'SESSION_DISCONNECT' => ['allowed' => [], 'required' => []],
        'SESSION_RESET' => ['allowed' => [], 'required' => []],
        'SESSION_SET_PASSIVE' => ['allowed' => ['passive'], 'required' => ['passive']],
        'SESSION_LOGOUT' => ['allowed' => [], 'required' => []],
        'MESSAGE_SEND' => ['allowed' => ['to', 'kind', 'text', 'caption', 'reply_to', 'link_preview', 'media', 'location', 'contact', 'poll', 'interactive'], 'required' => ['to']],
        'MESSAGE_EDIT' => ['allowed' => ['to', 'target_message_id', 'sender', 'text'], 'required' => ['to', 'target_message_id', 'text']],
        'MESSAGE_REVOKE' => ['allowed' => ['to', 'target_message_id', 'sender'], 'required' => ['to', 'target_message_id']],
        'MESSAGE_REACT' => ['allowed' => ['to', 'target_message_id', 'sender', 'emoji'], 'required' => ['to', 'target_message_id', 'emoji']],
        'POLL_VOTE' => ['allowed' => ['to', 'target_message_id', 'sender', 'option_names'], 'required' => ['to', 'target_message_id', 'option_names']],
        'MESSAGE_MARK' => ['allowed' => ['to', 'message_ids', 'receipt', 'sender', 'timestamp', 'protocol'], 'required' => ['to', 'message_ids', 'receipt']],
        'MESSAGE_REQUEST_UNAVAILABLE' => ['allowed' => ['to', 'target_message_id', 'sender'], 'required' => ['to', 'target_message_id']],
        'MEDIA_RETRY_REQUEST' => ['allowed' => ['to', 'target_message_id', 'sender', 'from_me'], 'required' => ['to', 'target_message_id', 'sender', 'from_me']],
        'PRESENCE_SET' => ['allowed' => ['presence', 'force_active_delivery_receipts'], 'required' => ['presence']],
        'PRESENCE_SUBSCRIBE' => ['allowed' => ['to'], 'required' => ['to']],
        'CHAT_PRESENCE_SET' => ['allowed' => ['to', 'presence', 'media'], 'required' => ['to', 'presence']],
        'CHAT_DISAPPEARING_SET' => ['allowed' => ['to', 'timer_seconds'], 'required' => ['to', 'timer_seconds']],
        'CHAT_STATE_UPDATE' => ['allowed' => ['to', 'action', 'value', 'target_message_id', 'sender', 'timestamp', 'duration_seconds', 'delete_media', 'from_me'], 'required' => ['action']],
        'BLOCKLIST_UPDATE' => ['allowed' => ['to', 'action'], 'required' => ['to', 'action']],
        'PRIVACY_UPDATE' => ['allowed' => ['name', 'value'], 'required' => ['name', 'value']],
        'DEFAULT_DISAPPEARING_SET' => ['allowed' => ['timer_seconds'], 'required' => ['timer_seconds']],
        'HISTORY_SYNC_REQUEST' => ['allowed' => ['to', 'last_message_id', 'last_message_from', 'last_message_timestamp', 'last_message_from_me', 'count'], 'required' => ['to', 'last_message_id', 'last_message_from', 'last_message_timestamp', 'last_message_from_me', 'count']],
    ];

    /** @var array<string, array{allowed: list<string>, required: list<string>}> */
    private const QUERY_SHAPES = [
        'USER_CHECK' => ['allowed' => ['users'], 'required' => ['users']],
        'USER_INFO' => ['allowed' => ['users'], 'required' => ['users']],
        'BUSINESS_PROFILE' => ['allowed' => ['users'], 'required' => ['users']],
        'PROFILE_PICTURE' => ['allowed' => ['user', 'preview'], 'required' => ['user']],
        'CONTACT_QR_LINK' => ['allowed' => ['revoke'], 'required' => []],
        'CONTACT_QR_RESOLVE' => ['allowed' => ['link'], 'required' => ['link']],
        'BUSINESS_LINK_RESOLVE' => ['allowed' => ['link'], 'required' => ['link']],
        'BLOCKLIST' => ['allowed' => [], 'required' => []],
        'PRIVACY_SETTINGS' => ['allowed' => [], 'required' => []],
    ];

    /** @param array<string, mixed> $payload */
    public static function assertCommand(GatewayCommandType $type, array $payload): void
    {
        self::assertShape($payload, self::COMMAND_SHAPES[$type->value], 'comando '.$type->value);
        if ($type === GatewayCommandType::UpdateChatState) {
            $action = strtoupper(trim((string) ($payload['action'] ?? '')));
            if ($action === 'MARK_CLEAN' && (int) ($payload['timestamp'] ?? 0) <= 0) {
                throw new InvalidArgumentException('timestamp é obrigatório para MARK_CLEAN.');
            }
            if (! in_array($action, ['SYNC', 'MARK_CLEAN'], true)
                && trim((string) ($payload['to'] ?? '')) === '') {
                throw new InvalidArgumentException('to é obrigatório para ação de chat 1:1.');
            }
        }
    }

    /** @param array<string, mixed> $payload */
    public static function assertQuery(GatewayQueryType $type, array $payload): void
    {
        self::assertShape($payload, self::QUERY_SHAPES[$type->value], 'query '.$type->value);
    }

    /** @param array<string, mixed> $payload */
    public static function assertSafeEvent(array $payload): void
    {
        self::assertObject($payload, 'evento');
        self::assertSafeValue($payload, 'payload', 0);
    }

    /** @return list<string> */
    public static function commandTypeValues(): array
    {
        return array_keys(self::COMMAND_SHAPES);
    }

    /** @return list<string> */
    public static function queryTypeValues(): array
    {
        return array_keys(self::QUERY_SHAPES);
    }

    public static function requiresProviderMessageId(GatewayCommandType $type): bool
    {
        return in_array($type, [
            GatewayCommandType::SendMessage,
            GatewayCommandType::EditMessage,
            GatewayCommandType::RevokeMessage,
            GatewayCommandType::ReactMessage,
            GatewayCommandType::VotePoll,
        ], true);
    }

    /** @return object|array<string, mixed> */
    public static function jsonObject(array $payload): object|array
    {
        return $payload === [] ? (object) [] : $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array{allowed: list<string>, required: list<string>}  $shape
     */
    private static function assertShape(array $payload, array $shape, string $context): void
    {
        self::assertObject($payload, $context);

        $unknown = array_values(array_diff(array_keys($payload), $shape['allowed']));
        if ($unknown !== []) {
            throw new InvalidArgumentException(sprintf(
                'Campo desconhecido em %s: %s.',
                $context,
                implode(', ', $unknown),
            ));
        }

        $missing = array_values(array_diff($shape['required'], array_keys($payload)));
        if ($missing !== []) {
            throw new InvalidArgumentException(sprintf(
                'Campo obrigatório ausente em %s: %s.',
                $context,
                implode(', ', $missing),
            ));
        }

        self::assertSafeValue($payload, 'payload', 0);
    }

    /** @param array<mixed> $payload */
    private static function assertObject(array $payload, string $context): void
    {
        if ($payload !== [] && array_is_list($payload)) {
            throw new InvalidArgumentException("Payload de {$context} deve ser objeto JSON.");
        }
    }

    private static function assertSafeValue(mixed $value, string $path, int $depth): void
    {
        if ($depth > 8) {
            throw new InvalidArgumentException("Payload excede profundidade máxima em {$path}.");
        }

        if ($value instanceof BackedEnum) {
            return;
        }

        if (is_null($value) || is_scalar($value)) {
            return;
        }

        if (! is_array($value)) {
            throw new InvalidArgumentException("Valor não serializável em {$path}.");
        }

        foreach ($value as $key => $child) {
            if (! is_int($key)) {
                if (! preg_match('/^[a-z][a-z0-9_]{0,63}$/', $key)) {
                    throw new InvalidArgumentException("Chave inválida em {$path}.");
                }
                if (in_array($key, self::FORBIDDEN_KEYS, true)) {
                    throw new InvalidArgumentException("Campo sensível não permitido em {$path}.{$key}.");
                }
            }
            self::assertSafeValue($child, $path.'.'.$key, $depth + 1);
        }
    }
}

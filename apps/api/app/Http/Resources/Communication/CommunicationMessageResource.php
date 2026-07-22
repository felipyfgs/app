<?php

namespace App\Http\Resources\Communication;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class CommunicationMessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'conversation_id' => $this->conversation_id,
            'direction' => $this->direction?->value ?? $this->direction,
            'kind' => $this->kind?->value ?? $this->kind,
            'source' => $this->source?->value ?? $this->source,
            'status' => $this->status?->value ?? $this->status,
            'body' => $this->purged_at === null ? $this->body_encrypted : null,
            'reply_to_message_id' => $this->reply_to_message_id,
            'author_membership_id' => $this->author_membership_id,
            'occurred_at' => $this->occurred_at?->toIso8601String(),
            'sent_at' => $this->sent_at?->toIso8601String(),
            'delivered_at' => $this->delivered_at?->toIso8601String(),
            'read_at' => $this->read_at?->toIso8601String(),
            'metadata' => $this->safeMetadata(),
            'attachments' => $this->whenLoaded('attachments', fn () => $this->attachments->map(fn ($attachment) => [
                'id' => $attachment->id,
                'filename' => $attachment->original_name_encrypted ?: 'anexo-'.$attachment->id,
                'mime_type' => $attachment->mime_type,
                'size_bytes' => (int) $attachment->size_bytes,
                'sha256' => $attachment->sha256,
                'download_url' => '/api/v1/communication/attachments/'.$attachment->id.'/download',
                'preview_url' => $this->supportsInlinePreview((string) $attachment->mime_type)
                    ? '/api/v1/communication/attachments/'.$attachment->id.'/preview'
                    : null,
                'purged_at' => $attachment->purged_at?->toIso8601String(),
            ])->values()),
        ];
    }

    /** @return array<string,mixed> */
    private function safeMetadata(): array
    {
        $metadata = is_array($this->metadata) ? $this->metadata : [];
        $allowed = array_intersect_key($metadata, array_flip([
            'edited_at',
            'revoked',
            'revoked_at',
            'poll',
            'poll_votes',
            'location',
            'contact',
            'interactive',
            'interactive_response',
            'history',
            'ephemeral',
            'view_once',
            'media_state',
            'media_error_code',
        ]));
        $allowed['reactions'] = array_values(array_filter(
            is_array($metadata['reactions'] ?? null) ? $metadata['reactions'] : [],
            'is_string',
        ));

        return array_filter($allowed, static fn (mixed $value): bool => $value !== null && $value !== []);
    }

    private function supportsInlinePreview(string $mime): bool
    {
        return str_starts_with($mime, 'image/')
            || str_starts_with($mime, 'audio/')
            || str_starts_with($mime, 'video/');
    }
}

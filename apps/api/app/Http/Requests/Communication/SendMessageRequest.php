<?php

namespace App\Http\Requests\Communication;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class SendMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'body' => ['nullable', 'string', 'max:4096', 'required_without:file'],
            'file' => [
                'nullable',
                'file',
                'max:'.max(1, (int) ceil(((int) config('communication.media.max_bytes', 20_971_520)) / 1024)),
                'mimetypes:image/jpeg,image/png,image/webp,audio/ogg,audio/mpeg,audio/mp4,audio/webm,video/mp4,video/webm,application/pdf,text/plain,application/zip',
            ],
            'kind' => ['nullable', Rule::in(['TEXT', 'IMAGE', 'AUDIO', 'VIDEO', 'DOCUMENT', 'STICKER'])],
            'ptt' => ['sometimes', 'boolean'],
            'reply_to_message_id' => ['nullable', 'integer', 'min:1'],
            'internal_note' => ['sometimes', 'boolean'],
            'idempotency_key' => ['nullable', 'string', 'regex:/^[A-Za-z0-9][A-Za-z0-9._:-]{7,127}$/'],
        ];
    }
}

<?php

namespace App\Http\Requests\Communication;

use Illuminate\Foundation\Http\FormRequest;

final class StoreCannedResponseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:120'],
            'shortcut' => ['required', 'string', 'max:80', 'regex:/^[a-z0-9._-]+$/'],
            'body' => ['required', 'string', 'max:4096'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}

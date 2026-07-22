<?php

namespace App\Http\Requests\Communication;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateInboxRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:120'],
            'is_enabled' => ['sometimes', 'boolean'],
            'is_default' => ['sometimes', 'boolean'],
            'work_department_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'lock_version' => ['required', 'integer', 'min:1'],
        ];
    }
}

<?php

namespace App\Http\Requests\Communication;

use Illuminate\Foundation\Http\FormRequest;

final class StoreInboxRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'is_enabled' => ['sometimes', 'boolean'],
            'is_default' => ['sometimes', 'boolean'],
            'work_department_id' => ['nullable', 'integer', 'min:1'],
        ];
    }
}

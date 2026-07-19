<?php

namespace App\Http\Requests\Clients;

use Illuminate\Foundation\Http\FormRequest;

class UpdateClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'legal_name' => ['sometimes', 'string', 'max:255'],
            'display_name' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
            'inactive_reason' => ['nullable', 'string', 'max:1000'],
            'legal_nature_code' => ['nullable', 'string', 'max:16'],
            'legal_nature_name' => ['nullable', 'string', 'max:255'],
            'company_size_code' => ['nullable', 'string', 'max:16'],
            'company_size_name' => ['nullable', 'string', 'max:255'],
            'tax_regime' => ['nullable', 'string', 'max:64'],
            // imutáveis / proibidos
            'root_cnpj' => ['prohibited'],
            'cnpj' => ['prohibited'],
            'office_id' => ['prohibited'],
            'matrix_client_id' => ['prohibited'],
            'registration_source' => ['prohibited'],
            'registration_refreshed_at' => ['prohibited'],
        ];
    }
}

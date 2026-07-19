<?php

namespace App\Http\Requests\Clients;

use App\Enums\OfficeRole;
use App\Enums\RegistrationStatus;
use App\Rules\ValidCnpj;
use App\Support\CurrentOffice;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // policy no controller
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'legal_name' => ['required', 'string', 'max:255'],
            'display_name' => ['nullable', 'string', 'max:255'],
            'cnpj' => ['required', 'string', new ValidCnpj],
            'notes' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
            'inactive_reason' => ['nullable', 'string', 'max:1000'],
            'legal_nature_code' => ['nullable', 'string', 'max:16'],
            'legal_nature_name' => ['nullable', 'string', 'max:255'],
            'company_size_code' => ['nullable', 'string', 'max:16'],
            'company_size_name' => ['nullable', 'string', 'max:255'],
            'tax_regime' => ['nullable', 'string', 'max:64'],
            // vínculo matriz → filial (cadastro próprio; só o link)
            'matrix_client_id' => ['nullable', 'integer', 'min:1'],
            // primeiro estabelecimento (1:1 com o cliente)
            'trade_name' => ['nullable', 'string', 'max:255'],
            'is_matrix' => ['sometimes', 'boolean'],
            'establishment_is_active' => ['sometimes', 'boolean'],
            'registration_status' => ['nullable', 'string', Rule::enum(RegistrationStatus::class)],
            'registration_status_at' => ['nullable', 'date'],
            'registration_status_reason' => ['nullable', 'string', 'max:255'],
            'activity_started_at' => ['nullable', 'date'],
            'main_cnae_code' => ['nullable', 'string', 'max:16'],
            'main_cnae_name' => ['nullable', 'string', 'max:255'],
            'public_email' => ['nullable', 'email', 'max:255'],
            'public_phone' => ['nullable', 'string', 'max:32'],
            'capture_enabled' => ['sometimes', 'boolean'],
            'address' => ['nullable', 'array'],
            'address.postal_code' => ['nullable', 'string', 'max:16'],
            'address.street_type' => ['nullable', 'string', 'max:32'],
            'address.street' => ['nullable', 'string', 'max:255'],
            'address.number' => ['nullable', 'string', 'max:32'],
            'address.complement' => ['nullable', 'string', 'max:255'],
            'address.district' => ['nullable', 'string', 'max:255'],
            'address.city' => ['nullable', 'string', 'max:255'],
            'address.city_ibge_code' => ['nullable', 'string', 'max:16'],
            'address.state' => ['nullable', 'string', 'size:2'],
            'address.country' => ['nullable', 'string', 'max:64'],
            // contato interno responsável opcional (distinto do contato público do CNPJ)
            'initial_contact' => ['nullable', 'array'],
            'initial_contact.name' => ['required_with:initial_contact', 'string', 'max:255'],
            'initial_contact.role' => ['nullable', 'string', 'max:255'],
            'initial_contact.email' => ['nullable', 'email', 'max:255'],
            'initial_contact.phone' => ['nullable', 'string', 'max:32'],
            'initial_contact.is_whatsapp' => ['sometimes', 'boolean'],
            'initial_contact.is_primary' => ['sometimes', 'boolean'],
            'initial_contact.receives_alerts' => ['sometimes', 'boolean'],
            'initial_contact.notes' => ['nullable', 'string'],
            'initial_contact.office_id' => ['prohibited'],
            'initial_contact.client_id' => ['prohibited'],
            'custom_fields' => ['nullable', 'array', 'max:20'],
            'custom_fields.*.label' => ['required', 'string', 'max:100'],
            'custom_fields.*.type' => ['required', 'string', Rule::in(['TEXT', 'SECRET'])],
            'custom_fields.*.value' => ['nullable', 'string', 'max:10000'],
            'custom_fields.*.office_id' => ['prohibited'],
            'custom_fields.*.client_id' => ['prohibited'],
            // office_id / proveniência do cliente nunca são autoridade do navegador
            // (campos ausentes em validated() → ignorados; proibidos se enviados como autoridade)
            'office_id' => ['prohibited'],
            'root_cnpj' => ['prohibited'],
            'registration_source' => ['prohibited'],
            'source' => ['prohibited'],
            'source_updated_at' => ['prohibited'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $contact = $this->input('initial_contact');

            if (is_array($contact)
                && $contact !== []
                && blank($contact['email'] ?? null)
                && blank($contact['phone'] ?? null)) {
                $validator->errors()->add(
                    'initial_contact.email',
                    'Informe ao menos um canal do contato responsável: e-mail ou telefone.'
                );
            }

            $hasSecret = collect($this->input('custom_fields', []))
                ->contains(fn ($field): bool => is_array($field) && ($field['type'] ?? null) === 'SECRET');

            if ($hasSecret && app(CurrentOffice::class)->role() !== OfficeRole::Admin) {
                $validator->errors()->add(
                    'custom_fields',
                    'Somente administradores podem cadastrar campos secretos.'
                );
            }
        });
    }
}

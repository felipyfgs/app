<?php

namespace App\DTO\Cnpj;

use App\Enums\RegistrationStatus;

final class EstablishmentRegistrationData
{
    public function __construct(
        public readonly string $cnpj,
        public readonly ?string $tradeName,
        public readonly bool $isMatrix,
        public readonly RegistrationStatus $registrationStatus,
        public readonly ?string $registrationStatusAt,
        public readonly ?string $registrationStatusReason,
        public readonly ?string $activityStartedAt,
        public readonly ?string $mainCnaeCode,
        public readonly ?string $mainCnaeName,
        public readonly AddressData $address,
        public readonly ?string $publicEmail,
        public readonly ?string $publicPhone,
        public readonly ?string $sourceUpdatedAt,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'cnpj' => $this->cnpj,
            'trade_name' => $this->tradeName,
            'is_matrix' => $this->isMatrix,
            'registration_status' => $this->registrationStatus->value,
            'registration_status_at' => $this->registrationStatusAt,
            'registration_status_reason' => $this->registrationStatusReason,
            'activity_started_at' => $this->activityStartedAt,
            'main_cnae_code' => $this->mainCnaeCode,
            'main_cnae_name' => $this->mainCnaeName,
            'address' => $this->address->toArray(),
            'public_email' => $this->publicEmail,
            'public_phone' => $this->publicPhone,
            'source_updated_at' => $this->sourceUpdatedAt,
        ];
    }
}

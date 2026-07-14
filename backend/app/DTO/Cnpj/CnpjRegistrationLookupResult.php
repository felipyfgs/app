<?php

namespace App\DTO\Cnpj;

use App\Enums\RegistrationStatus;
use JsonSerializable;

/**
 * DTO sanitizado da consulta cadastral — único formato permitido em cache, API e logs.
 */
final class CnpjRegistrationLookupResult implements JsonSerializable
{
    public function __construct(
        public readonly string $source,
        public readonly ?string $sourceUpdatedAt,
        public readonly ClientRegistrationData $client,
        public readonly EstablishmentRegistrationData $establishment,
    ) {}

    /**
     * @return array{
     *   source: string,
     *   source_updated_at: ?string,
     *   client: array<string, mixed>,
     *   establishment: array<string, mixed>
     * }
     */
    public function toArray(): array
    {
        return [
            'source' => $this->source,
            'source_updated_at' => $this->sourceUpdatedAt,
            'client' => $this->client->toArray(),
            'establishment' => $this->establishment->toArray(),
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $client = is_array($data['client'] ?? null) ? $data['client'] : [];
        $establishment = is_array($data['establishment'] ?? null) ? $data['establishment'] : [];
        $address = is_array($establishment['address'] ?? null) ? $establishment['address'] : [];

        $statusRaw = $establishment['registration_status'] ?? null;
        $status = $statusRaw instanceof RegistrationStatus
            ? $statusRaw
            : RegistrationStatus::fromExternal(is_string($statusRaw) ? $statusRaw : null);

        return new self(
            source: (string) ($data['source'] ?? 'CNPJ_WS'),
            sourceUpdatedAt: self::nullableString($data['source_updated_at'] ?? null),
            client: new ClientRegistrationData(
                rootCnpj: (string) ($client['root_cnpj'] ?? ''),
                legalName: (string) ($client['legal_name'] ?? ''),
                legalNatureCode: self::nullableString($client['legal_nature_code'] ?? null),
                legalNatureName: self::nullableString($client['legal_nature_name'] ?? null),
                companySizeCode: self::nullableString($client['company_size_code'] ?? null),
                companySizeName: self::nullableString($client['company_size_name'] ?? null),
            ),
            establishment: new EstablishmentRegistrationData(
                cnpj: (string) ($establishment['cnpj'] ?? ''),
                tradeName: self::nullableString($establishment['trade_name'] ?? null),
                isMatrix: (bool) ($establishment['is_matrix'] ?? false),
                registrationStatus: $status,
                registrationStatusAt: self::nullableString($establishment['registration_status_at'] ?? null),
                registrationStatusReason: self::nullableString($establishment['registration_status_reason'] ?? null),
                activityStartedAt: self::nullableString($establishment['activity_started_at'] ?? null),
                mainCnaeCode: self::nullableString($establishment['main_cnae_code'] ?? null),
                mainCnaeName: self::nullableString($establishment['main_cnae_name'] ?? null),
                address: AddressData::fromArray($address),
                publicEmail: self::nullableString($establishment['public_email'] ?? null),
                publicPhone: self::nullableString($establishment['public_phone'] ?? null),
                sourceUpdatedAt: self::nullableString($establishment['source_updated_at'] ?? null),
            ),
        );
    }

    private static function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }
        $normalized = trim($value);

        return $normalized !== '' ? $normalized : null;
    }
}

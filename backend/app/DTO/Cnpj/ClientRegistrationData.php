<?php

namespace App\DTO\Cnpj;

final class ClientRegistrationData
{
    public function __construct(
        public readonly string $rootCnpj,
        public readonly string $legalName,
        public readonly ?string $legalNatureCode = null,
        public readonly ?string $legalNatureName = null,
        public readonly ?string $companySizeCode = null,
        public readonly ?string $companySizeName = null,
    ) {}

    /**
     * @return array{
     *   root_cnpj: string,
     *   legal_name: string,
     *   legal_nature_code: ?string,
     *   legal_nature_name: ?string,
     *   company_size_code: ?string,
     *   company_size_name: ?string
     * }
     */
    public function toArray(): array
    {
        return [
            'root_cnpj' => $this->rootCnpj,
            'legal_name' => $this->legalName,
            'legal_nature_code' => $this->legalNatureCode,
            'legal_nature_name' => $this->legalNatureName,
            'company_size_code' => $this->companySizeCode,
            'company_size_name' => $this->companySizeName,
        ];
    }
}

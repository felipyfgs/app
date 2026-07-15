<?php

namespace App\DTO\Fiscal\SimplesMei;

use App\Enums\FiscalSituation;
use InvalidArgumentException;

final readonly class CcmeiDto
{
    public const VERSION = '1';

    public function __construct(
        public string $version,
        public string $status,
        public ?string $certificateNumber,
        public ?string $issuedAt,
        public FiscalSituation $situation,
        public array $raw,
    ) {}

    /**
     * @param  array<string, mixed>  $body
     */
    public static function fromIntegraBody(array $body): self
    {
        $version = (string) ($body['dto_version'] ?? $body['version'] ?? self::VERSION);
        if ($version !== self::VERSION) {
            throw new InvalidArgumentException("CCMEI DTO versão não suportada: {$version}");
        }

        $data = is_array($body['data'] ?? null) ? $body['data'] : $body;
        $status = strtoupper((string) ($data['status'] ?? $data['situacao'] ?? 'UNKNOWN'));
        $cert = isset($data['certificate_number'])
            ? (string) $data['certificate_number']
            : (isset($data['numero_certificado']) ? (string) $data['numero_certificado'] : null);
        $issued = isset($data['issued_at'])
            ? (string) $data['issued_at']
            : (isset($data['data_emissao']) ? (string) $data['data_emissao'] : null);

        $situation = match ($status) {
            'ATIVO', 'VALIDO', 'OK', 'EMITIDO' => FiscalSituation::UpToDate,
            'INATIVO', 'CANCELADO', 'SUSPENSO' => FiscalSituation::Attention,
            'INCONCLUSIVO', 'UNKNOWN', '' => FiscalSituation::Unknown,
            default => FiscalSituation::Unknown,
        };

        return new self(
            version: self::VERSION,
            status: $status,
            certificateNumber: $cert !== '' ? $cert : null,
            issuedAt: $issued !== '' ? $issued : null,
            situation: $situation,
            raw: $data,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toNormalized(): array
    {
        return [
            'dto' => 'ccmei',
            'dto_version' => $this->version,
            'status' => $this->status,
            'certificate_number' => $this->certificateNumber,
            'issued_at' => $this->issuedAt,
            'situation' => $this->situation->value,
            'regime_family' => 'MEI',
        ];
    }
}

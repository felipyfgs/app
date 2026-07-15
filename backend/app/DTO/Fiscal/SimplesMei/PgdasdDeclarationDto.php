<?php

namespace App\DTO\Fiscal\SimplesMei;

use App\Enums\FiscalSituation;
use InvalidArgumentException;

/**
 * DTO versionado de declaração PGDAS-D (contrato de adapter).
 *
 * @phpstan-type Raw array<string, mixed>
 */
final readonly class PgdasdDeclarationDto
{
    public const VERSION = '1';

    public function __construct(
        public string $version,
        public string $competence,
        public string $status,
        public ?string $receiptNumber,
        public ?string $declarationId,
        public ?string $transmittedAt,
        public FiscalSituation $situation,
        public array $raw,
    ) {}

    /**
     * @param  array<string, mixed>  $body
     */
    public static function fromIntegraBody(array $body, string $fallbackCompetence = ''): self
    {
        $version = (string) ($body['dto_version'] ?? $body['version'] ?? self::VERSION);
        if ($version !== self::VERSION) {
            throw new InvalidArgumentException("PGDAS-D DTO versão não suportada: {$version}");
        }

        $data = is_array($body['data'] ?? null) ? $body['data'] : $body;
        $status = strtoupper((string) ($data['status'] ?? $data['situacao'] ?? 'UNKNOWN'));
        $competence = (string) ($data['competence'] ?? $data['competencia'] ?? $fallbackCompetence);
        $receipt = isset($data['receipt_number'])
            ? (string) $data['receipt_number']
            : (isset($data['numero_recibo']) ? (string) $data['numero_recibo'] : null);
        $declId = isset($data['declaration_id'])
            ? (string) $data['declaration_id']
            : (isset($data['id_declaracao']) ? (string) $data['id_declaracao'] : null);
        $transmittedAt = isset($data['transmitted_at'])
            ? (string) $data['transmitted_at']
            : (isset($data['data_transmissao']) ? (string) $data['data_transmissao'] : null);

        return new self(
            version: self::VERSION,
            competence: $competence,
            status: $status,
            receiptNumber: $receipt !== '' ? $receipt : null,
            declarationId: $declId !== '' ? $declId : null,
            transmittedAt: $transmittedAt !== '' ? $transmittedAt : null,
            situation: self::mapSituation($status, $receipt),
            raw: $data,
        );
    }

    private static function mapSituation(string $status, ?string $receipt): FiscalSituation
    {
        return match ($status) {
            'ENTREGUE', 'TRANSMITIDA', 'DELIVERED', 'OK', 'REGULAR' => $receipt !== null
                ? FiscalSituation::UpToDate
                : FiscalSituation::Unknown,
            'PENDENTE', 'PENDING', 'OMISSA', 'OMITIDA' => FiscalSituation::Pending,
            'PROCESSANDO', 'PROCESSING' => FiscalSituation::Processing,
            'ATENCAO', 'ATTENTION', 'DIVERGENTE' => FiscalSituation::Attention,
            'NAO_APLICAVEL', 'NOT_APPLICABLE' => FiscalSituation::NotApplicable,
            'ERRO', 'ERROR' => FiscalSituation::Error,
            'INCONCLUSIVO', 'UNKNOWN', '' => FiscalSituation::Unknown,
            default => FiscalSituation::Unknown,
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function toNormalized(): array
    {
        return [
            'dto' => 'pgdasd_declaration',
            'dto_version' => $this->version,
            'competence' => $this->competence,
            'status' => $this->status,
            'receipt_number' => $this->receiptNumber,
            'declaration_id' => $this->declarationId,
            'transmitted_at' => $this->transmittedAt,
            'situation' => $this->situation->value,
            'regime_family' => 'SIMPLES_NACIONAL',
        ];
    }
}

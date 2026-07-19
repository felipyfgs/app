<?php

namespace App\DTO\Fiscal\SimplesMei;

use App\Enums\FiscalGuideEmissionStatus;
use App\Enums\FiscalGuidePaymentStatus;
use InvalidArgumentException;

/**
 * DAS gerado de forma assistida — emissão ≠ pagamento.
 */
final readonly class DasGuideDto
{
    public const VERSION = '1';

    public function __construct(
        public string $version,
        public string $competence,
        public string $regimeFamily,
        public ?string $documentNumber,
        public ?string $dueDate,
        public ?float $amount,
        public FiscalGuideEmissionStatus $emissionStatus,
        public FiscalGuidePaymentStatus $paymentStatus,
        public array $raw,
    ) {}

    /**
     * @param  array<string, mixed>  $body
     */
    public static function fromIntegraBody(array $body, string $fallbackCompetence = '', string $regimeFamily = 'SIMPLES_NACIONAL'): self
    {
        $version = (string) ($body['dto_version'] ?? $body['version'] ?? self::VERSION);
        if ($version !== self::VERSION) {
            throw new InvalidArgumentException("DAS guide DTO versão não suportada: {$version}");
        }

        $data = is_array($body['data'] ?? null) ? $body['data'] : $body;
        $competence = (string) ($data['competence'] ?? $data['competencia'] ?? $fallbackCompetence);
        $doc = isset($data['document_number'])
            ? (string) $data['document_number']
            : (isset($data['numero_documento']) ? (string) $data['numero_documento'] : null);
        $due = isset($data['due_date'])
            ? (string) $data['due_date']
            : (isset($data['vencimento']) ? (string) $data['vencimento'] : null);
        $amount = isset($data['amount'])
            ? (float) $data['amount']
            : (isset($data['valor']) ? (float) $data['valor'] : null);

        $emissionRaw = strtoupper((string) ($data['emission_status'] ?? 'ISSUED'));
        $emission = FiscalGuideEmissionStatus::tryFrom($emissionRaw) ?? FiscalGuideEmissionStatus::Issued;

        // Nunca inferir pagamento a partir da emissão
        $payment = FiscalGuidePaymentStatus::Unknown;

        return new self(
            version: self::VERSION,
            competence: $competence,
            regimeFamily: $regimeFamily,
            documentNumber: $doc !== '' ? $doc : null,
            dueDate: $due !== '' ? $due : null,
            amount: $amount,
            emissionStatus: $emission,
            paymentStatus: $payment,
            raw: $data,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toNormalized(): array
    {
        return [
            'dto' => 'das_guide',
            'dto_version' => $this->version,
            'competence' => $this->competence,
            'regime_family' => $this->regimeFamily,
            'document_number' => $this->documentNumber,
            'due_date' => $this->dueDate,
            'amount' => $this->amount,
            'emission_status' => $this->emissionStatus->value,
            'payment_status' => $this->paymentStatus->value,
            'payment_inferred' => false,
        ];
    }
}

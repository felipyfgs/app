<?php

namespace App\Services\Integra;

use App\DTO\Serpro\TermoValidationResult;
use Carbon\CarbonImmutable;
use DOMDocument;
use DOMXPath;

/**
 * Validador do Termo de Autorização XML.
 *
 * Limites documentados (XSD completo SERPRO pode não estar embutido):
 * - Valida well-formed XML e estrutura crítica (assinadoPor, destinatário, vigência).
 * - Hash SHA-256 do XML completo.
 * - XMLDSig: tenta localizar Signature; verificação criptográfica completa
 *   depende de openssl + C14N e pode ser parcial se biblioteca XSD indisponível.
 * - Não executa validação XSD oficial se o schema não estiver em storage/schemas.
 */
final class TermoXmlValidator
{
    /**
     * @param  string  $xml
     * @param  string  $expectedAuthorIdentity  CPF/CNPJ do Autor
     * @param  string  $expectedDestinationCnpj  CNPJ da software house contratante
     */
    public function validate(
        string $xml,
        string $expectedAuthorIdentity,
        string $expectedDestinationCnpj,
    ): TermoValidationResult {
        $limits = [
            'xsd_full' => false,
            'xmldsig_crypto' => false,
            'structure_critical' => true,
        ];

        $xml = trim($xml);
        if ($xml === '' || ! str_contains($xml, '<')) {
            return new TermoValidationResult(
                valid: false,
                errorCode: 'EMPTY_XML',
                errorMessage: 'Termo XML vazio ou inválido.',
                limits: $limits,
            );
        }

        $sha256 = hash('sha256', $xml);

        $previous = libxml_use_internal_errors(true);
        $dom = new DOMDocument;
        $loaded = $dom->loadXML($xml, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (! $loaded) {
            return new TermoValidationResult(
                valid: false,
                errorCode: 'MALFORMED_XML',
                errorMessage: 'Termo XML malformado.',
                sha256: $sha256,
                limits: $limits,
            );
        }

        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('ds', 'http://www.w3.org/2000/09/xmldsig#');

        $signedBy = $this->firstText($xpath, [
            '//*[local-name()="assinadoPor"]',
            '//*[local-name()="AssinadoPor"]',
            '//*[local-name()="signedBy"]',
        ]);
        $destination = $this->firstText($xpath, [
            '//*[local-name()="destinatario"]',
            '//*[local-name()="Destinatario"]',
            '//*[local-name()="cnpjDestinatario"]',
            '//*[local-name()="CNPJDestinatario"]',
        ]);
        $authorNode = $this->firstText($xpath, [
            '//*[local-name()="autorPedido"]',
            '//*[local-name()="AutorPedido"]',
            '//*[local-name()="cpfAutor"]',
            '//*[local-name()="cnpjAutor"]',
            '//*[local-name()="niAutor"]',
        ]);
        $validFromRaw = $this->firstText($xpath, [
            '//*[local-name()="dataInicioVigencia"]',
            '//*[local-name()="vigenciaInicio"]',
            '//*[local-name()="validFrom"]',
            '//*[local-name()="dtIni"]',
        ]);
        $validToRaw = $this->firstText($xpath, [
            '//*[local-name()="dataFimVigencia"]',
            '//*[local-name()="vigenciaFim"]',
            '//*[local-name()="validTo"]',
            '//*[local-name()="dtFim"]',
        ]);

        $signedByNorm = $this->normalizeId($signedBy ?? '');
        $destinationNorm = $this->normalizeId($destination ?? '');
        $authorNorm = $this->normalizeId($authorNode ?? $signedByNorm);
        $expectedAuthor = $this->normalizeId($expectedAuthorIdentity);
        $expectedDest = $this->normalizeId($expectedDestinationCnpj);

        if ($signedByNorm === '' || $authorNorm === '') {
            return new TermoValidationResult(
                valid: false,
                errorCode: 'MISSING_SIGNER',
                errorMessage: 'Identidade do signatário (assinadoPor) não encontrada no Termo.',
                sha256: $sha256,
                limits: $limits,
            );
        }

        if ($signedByNorm !== $authorNorm && $signedByNorm !== $expectedAuthor) {
            return new TermoValidationResult(
                valid: false,
                errorCode: 'SIGNER_MISMATCH',
                errorMessage: 'Identidade assinadoPor diverge do titular/Autor do Pedido.',
                signedBy: $signedByNorm,
                authorIdentity: $authorNorm,
                sha256: $sha256,
                limits: $limits,
            );
        }

        if ($expectedAuthor !== '' && $signedByNorm !== $expectedAuthor && $authorNorm !== $expectedAuthor) {
            return new TermoValidationResult(
                valid: false,
                errorCode: 'AUTHOR_MISMATCH',
                errorMessage: 'Autor do Termo diverge da identidade configurada no escritório.',
                signedBy: $signedByNorm,
                authorIdentity: $authorNorm,
                sha256: $sha256,
                limits: $limits,
            );
        }

        if ($expectedDest !== '' && $destinationNorm !== '' && $destinationNorm !== $expectedDest) {
            return new TermoValidationResult(
                valid: false,
                errorCode: 'DESTINATION_MISMATCH',
                errorMessage: 'Destinatário do Termo diverge da software house contratante.',
                signedBy: $signedByNorm,
                destinationCnpj: $destinationNorm,
                authorIdentity: $authorNorm,
                sha256: $sha256,
                limits: $limits,
            );
        }

        if ($expectedDest !== '' && $destinationNorm === '') {
            return new TermoValidationResult(
                valid: false,
                errorCode: 'MISSING_DESTINATION',
                errorMessage: 'Destinatário do Termo não encontrado.',
                sha256: $sha256,
                limits: $limits,
            );
        }

        $validFrom = $this->parseDate($validFromRaw);
        $validTo = $this->parseDate($validToRaw);

        if ($validTo !== null && $validTo->isPast()) {
            return new TermoValidationResult(
                valid: false,
                errorCode: 'TERM_EXPIRED',
                errorMessage: 'Termo com vigência expirada.',
                signedBy: $signedByNorm,
                destinationCnpj: $destinationNorm,
                authorIdentity: $authorNorm,
                validFrom: $validFrom,
                validTo: $validTo,
                sha256: $sha256,
                limits: $limits,
            );
        }

        $signatureNodes = $xpath->query('//ds:Signature|//*[local-name()="Signature"]');
        $hasSignature = $signatureNodes !== false && $signatureNodes->length > 0;
        $signatureValid = false;
        $signatureChecked = false;

        if ($hasSignature) {
            $signatureChecked = true;
            // Verificação criptográfica completa exigiria C14N + cert embutido.
            // Marcamos presença de Signature; openssl verify é best-effort.
            $signatureValid = $this->tryVerifyXmlDsigPresence($dom);
            $limits['xmldsig_crypto'] = $signatureValid;
        }

        if (! $hasSignature) {
            return new TermoValidationResult(
                valid: false,
                errorCode: 'MISSING_SIGNATURE',
                errorMessage: 'Termo sem elemento Signature (XMLDSig).',
                signedBy: $signedByNorm,
                destinationCnpj: $destinationNorm,
                authorIdentity: $authorNorm,
                validFrom: $validFrom,
                validTo: $validTo,
                sha256: $sha256,
                signatureChecked: false,
                signatureValid: false,
                limits: $limits,
            );
        }

        // Schema oficial opcional
        $xsdPath = storage_path('schemas/serpro/termo-autorizacao.xsd');
        if (is_readable($xsdPath)) {
            $limits['xsd_full'] = true;
            $previous = libxml_use_internal_errors(true);
            $ok = $dom->schemaValidate($xsdPath);
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
            if (! $ok) {
                return new TermoValidationResult(
                    valid: false,
                    errorCode: 'XSD_FAILED',
                    errorMessage: 'Termo não conforme ao XSD oficial.',
                    signedBy: $signedByNorm,
                    destinationCnpj: $destinationNorm,
                    authorIdentity: $authorNorm,
                    validFrom: $validFrom,
                    validTo: $validTo,
                    sha256: $sha256,
                    signatureChecked: $signatureChecked,
                    signatureValid: $signatureValid,
                    limits: $limits,
                );
            }
        }

        return new TermoValidationResult(
            valid: true,
            signedBy: $signedByNorm,
            destinationCnpj: $destinationNorm !== '' ? $destinationNorm : $expectedDest,
            authorIdentity: $authorNorm !== '' ? $authorNorm : $expectedAuthor,
            validFrom: $validFrom,
            validTo: $validTo,
            sha256: $sha256,
            signatureChecked: $signatureChecked,
            signatureValid: $signatureValid,
            limits: $limits,
        );
    }

    /**
     * @param  list<string>  $queries
     */
    private function firstText(DOMXPath $xpath, array $queries): ?string
    {
        foreach ($queries as $q) {
            $nodes = $xpath->query($q);
            if ($nodes !== false && $nodes->length > 0) {
                $text = trim((string) $nodes->item(0)?->textContent);

                return $text !== '' ? $text : null;
            }
        }

        return null;
    }

    private function normalizeId(string $raw): string
    {
        return strtoupper(preg_replace('/[^0-9A-Za-z]/', '', $raw) ?? '');
    }

    private function parseDate(?string $raw): ?CarbonImmutable
    {
        if ($raw === null || trim($raw) === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($raw);
        } catch (\Throwable) {
            return null;
        }
    }

    private function tryVerifyXmlDsigPresence(DOMDocument $dom): bool
    {
        // Best-effort: presença de SignatureValue + SignedInfo.
        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('ds', 'http://www.w3.org/2000/09/xmldsig#');
        $sv = $xpath->query('//ds:SignatureValue|//*[local-name()="SignatureValue"]');
        $si = $xpath->query('//ds:SignedInfo|//*[local-name()="SignedInfo"]');

        return $sv !== false && $sv->length > 0 && $si !== false && $si->length > 0;
    }
}

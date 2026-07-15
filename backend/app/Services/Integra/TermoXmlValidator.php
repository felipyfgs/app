<?php

namespace App\Services\Integra;

use App\DTO\Serpro\TermoValidationResult;
use App\Enums\TermoAuthorizationState;
use Carbon\CarbonImmutable;
use DOMDocument;
use DOMXPath;
use RobRichards\XMLSecLibs\XMLSecurityDSig;
use RobRichards\XMLSecLibs\XMLSecurityKey;

/**
 * Validador do Termo de Autorização XML (layout oficial + XMLDSig + cert).
 *
 * Estados: LOCAL_VALIDATED (só local) ≠ SERPRO_ACCEPTED (retorno real Autentica Procurador).
 * Cadeia ICP-Brasil completa/revogação permanecem sujeitas ao SERPRO.
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
            'certificate_checks' => false,
        ];

        $xml = trim($xml);
        if ($xml === '' || ! str_contains($xml, '<')) {
            return $this->reject('EMPTY_XML', 'Termo XML vazio ou inválido.', $limits);
        }

        $sha256 = hash('sha256', $xml);

        $previous = libxml_use_internal_errors(true);
        $dom = new DOMDocument;
        $loaded = $dom->loadXML($xml, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (! $loaded) {
            return $this->reject('MALFORMED_XML', 'Termo XML malformado.', $limits, $sha256);
        }

        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('ds', 'http://www.w3.org/2000/09/xmldsig#');

        // Atributos/elementos oficiais (case-insensitive local-name)
        $signedBy = $this->firstText($xpath, [
            '//*[local-name()="assinadoPor"]',
            '//@assinadoPor',
            '//*[local-name()="AssinadoPor"]',
        ]);
        $destination = $this->firstText($xpath, [
            '//*[local-name()="destinatario"]',
            '//@destinatario',
            '//*[local-name()="cnpjDestinatario"]',
            '//@cnpjDestinatario',
        ]);
        $sistema = $this->firstText($xpath, [
            '//*[local-name()="sistema"]',
            '//@sistema',
        ]);
        $dataAssinaturaRaw = $this->firstText($xpath, [
            '//*[local-name()="dataAssinatura"]',
            '//@dataAssinatura',
        ]);
        $validFromRaw = $this->firstText($xpath, [
            '//*[local-name()="dataInicioVigencia"]',
            '//*[local-name()="vigenciaInicio"]',
            '//@dataInicioVigencia',
            '//@vigenciaInicio',
            '//*[local-name()="vigencia"]/*[local-name()="inicio"]',
        ]);
        $validToRaw = $this->firstText($xpath, [
            '//*[local-name()="dataFimVigencia"]',
            '//*[local-name()="vigenciaFim"]',
            '//@dataFimVigencia',
            '//@vigenciaFim',
            '//*[local-name()="vigencia"]/*[local-name()="fim"]',
        ]);
        $authorNode = $this->firstText($xpath, [
            '//*[local-name()="autorPedido"]',
            '//*[local-name()="niAutor"]',
            '//@niAutor',
            '//*[local-name()="cpfAutor"]',
            '//*[local-name()="cnpjAutor"]',
        ]);

        $signedByNorm = $this->normalizeId($signedBy ?? '');
        $destinationNorm = $this->normalizeId($destination ?? '');
        $authorNorm = $this->normalizeId($authorNode ?? $signedByNorm);
        $expectedAuthor = $this->normalizeId($expectedAuthorIdentity);
        $expectedDest = $this->normalizeId($expectedDestinationCnpj);

        if ($signedByNorm === '' || $authorNorm === '') {
            return $this->reject('MISSING_SIGNER', 'Identidade do signatário (assinadoPor) não encontrada no Termo.', $limits, $sha256);
        }

        if ($signedByNorm !== $authorNorm && $signedByNorm !== $expectedAuthor) {
            return $this->reject(
                'SIGNER_MISMATCH',
                'Identidade assinadoPor diverge do titular/Autor do Pedido.',
                $limits,
                $sha256,
                $signedByNorm,
                $authorNorm,
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
                authorizationState: TermoAuthorizationState::Rejected->value,
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
                authorizationState: TermoAuthorizationState::Rejected->value,
            );
        }

        if ($expectedDest !== '' && $destinationNorm === '') {
            return $this->reject('MISSING_DESTINATION', 'Destinatário do Termo não encontrado.', $limits, $sha256, $signedByNorm, $authorNorm);
        }

        $validFrom = $this->parseOfficialDate($validFromRaw) ?? $this->parseOfficialDate($dataAssinaturaRaw);
        $validTo = $this->parseOfficialDate($validToRaw);

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
                authorizationState: TermoAuthorizationState::Rejected->value,
            );
        }

        $signatureNodes = $xpath->query('//ds:Signature|//*[local-name()="Signature"]');
        $hasSignature = $signatureNodes !== false && $signatureNodes->length > 0;
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
                authorizationState: TermoAuthorizationState::Rejected->value,
            );
        }

        $crypto = $this->verifyXmlDsig($dom);
        $limits['xmldsig_crypto'] = $crypto['ok'];
        $limits['certificate_checks'] = $crypto['cert_ok'];

        if (! $crypto['ok']) {
            return new TermoValidationResult(
                valid: false,
                errorCode: $crypto['error'] ?? 'SIGNATURE_INVALID',
                errorMessage: $crypto['message'] ?? 'Assinatura XMLDSig inválida.',
                signedBy: $signedByNorm,
                destinationCnpj: $destinationNorm,
                authorIdentity: $authorNorm,
                validFrom: $validFrom,
                validTo: $validTo,
                sha256: $sha256,
                signatureChecked: true,
                signatureValid: false,
                limits: $limits,
                authorizationState: TermoAuthorizationState::Rejected->value,
            );
        }

        // Identidade do certificado vs assinadoPor (quando disponível)
        if ($crypto['cert_identity'] !== null && $crypto['cert_identity'] !== '') {
            $certId = $this->normalizeId($crypto['cert_identity']);
            if ($certId !== $signedByNorm && $certId !== $authorNorm && $certId !== $expectedAuthor) {
                return new TermoValidationResult(
                    valid: false,
                    errorCode: 'CERT_IDENTITY_MISMATCH',
                    errorMessage: 'Titular do certificado diverge do assinadoPor/Autor.',
                    signedBy: $signedByNorm,
                    destinationCnpj: $destinationNorm,
                    authorIdentity: $authorNorm,
                    validFrom: $validFrom,
                    validTo: $validTo,
                    sha256: $sha256,
                    signatureChecked: true,
                    signatureValid: true,
                    limits: $limits,
                    authorizationState: TermoAuthorizationState::Rejected->value,
                );
            }
        }

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
                    signatureChecked: true,
                    signatureValid: true,
                    limits: $limits,
                    authorizationState: TermoAuthorizationState::Rejected->value,
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
            signatureChecked: true,
            signatureValid: true,
            limits: array_merge($limits, ['sistema' => $sistema]),
            authorizationState: TermoAuthorizationState::LocalValidated->value,
        );
    }

    /**
     * @return array{ok: bool, cert_ok: bool, cert_identity: ?string, error: ?string, message: ?string}
     */
    private function verifyXmlDsig(DOMDocument $dom): array
    {
        try {
            $objDSig = new XMLSecurityDSig;
            $objDSig->idKeys = ['Id', 'ID', 'id'];

            $signature = $objDSig->locateSignature($dom);
            if ($signature === null) {
                return [
                    'ok' => false,
                    'cert_ok' => false,
                    'cert_identity' => null,
                    'error' => 'MISSING_SIGNATURE',
                    'message' => 'Signature XMLDSig não localizada.',
                ];
            }

            $xpath = new DOMXPath($dom);
            $xpath->registerNamespace('ds', 'http://www.w3.org/2000/09/xmldsig#');
            $signatureAlgorithm = (string) $xpath->evaluate('string(//ds:SignatureMethod/@Algorithm)');
            $digestAlgorithm = (string) $xpath->evaluate('string(//ds:DigestMethod/@Algorithm)');
            $canonicalAlgorithm = (string) $xpath->evaluate('string(//ds:CanonicalizationMethod/@Algorithm)');
            if ($signatureAlgorithm !== XMLSecurityKey::RSA_SHA256
                || $digestAlgorithm !== XMLSecurityDSig::SHA256
                || ! in_array($canonicalAlgorithm, [XMLSecurityDSig::C14N, XMLSecurityDSig::EXC_C14N], true)) {
                return [
                    'ok' => false,
                    'cert_ok' => false,
                    'cert_identity' => null,
                    'error' => 'XMLDSIG_ALGORITHM_UNSUPPORTED',
                    'message' => 'Termo deve usar RSA-SHA256, digest SHA-256 e C14N.',
                ];
            }

            $objDSig->canonicalizeSignedInfo();
            if (! $objDSig->validateReference()) {
                return [
                    'ok' => false,
                    'cert_ok' => false,
                    'cert_identity' => null,
                    'error' => 'DIGEST_MISMATCH',
                    'message' => 'Digest da referência XMLDSig não confere.',
                ];
            }

            $objKey = $objDSig->locateKey();
            if ($objKey === null) {
                return [
                    'ok' => false,
                    'cert_ok' => false,
                    'cert_identity' => null,
                    'error' => 'KEY_MISSING',
                    'message' => 'Chave pública da assinatura não localizada.',
                ];
            }

            // Carrega X509 embutido (KeyInfo)
            $xpath = new DOMXPath($dom);
            $xpath->registerNamespace('ds', 'http://www.w3.org/2000/09/xmldsig#');
            $x509 = $xpath->query('//ds:X509Certificate|//*[local-name()="X509Certificate"]');
            if ($x509 === false || $x509->length === 0) {
                return [
                    'ok' => false,
                    'cert_ok' => false,
                    'cert_identity' => null,
                    'error' => 'X509_MISSING',
                    'message' => 'Certificado X.509 embutido ausente.',
                ];
            }
            $pem = $this->certPemFromBase64(trim((string) $x509->item(0)?->textContent));
            $objKey->loadKey($pem, false, true);

            if (! $objDSig->verify($objKey)) {
                return [
                    'ok' => false,
                    'cert_ok' => false,
                    'cert_identity' => null,
                    'error' => 'SIGNATURE_INVALID',
                    'message' => 'Assinatura RSA-SHA256 não confere.',
                ];
            }

            $certIdentity = null;
            $certOk = false;
            $xpath = new DOMXPath($dom);
            $xpath->registerNamespace('ds', 'http://www.w3.org/2000/09/xmldsig#');
            $x509 = $xpath->query('//ds:X509Certificate|//*[local-name()="X509Certificate"]');
            if ($x509 !== false && $x509->length > 0) {
                $pem = $this->certPemFromBase64(trim((string) $x509->item(0)?->textContent));
                $parsed = openssl_x509_parse($pem);
                if (is_array($parsed)) {
                    $certOk = true;
                    $now = time();
                    if (isset($parsed['validTo_time_t']) && $parsed['validTo_time_t'] < $now) {
                        return [
                            'ok' => false,
                            'cert_ok' => false,
                            'cert_identity' => null,
                            'error' => 'CERT_EXPIRED',
                            'message' => 'Certificado do Termo expirado.',
                        ];
                    }
                    if (isset($parsed['validFrom_time_t']) && $parsed['validFrom_time_t'] > $now) {
                        return [
                            'ok' => false,
                            'cert_ok' => false,
                            'cert_identity' => null,
                            'error' => 'CERT_NOT_YET_VALID',
                            'message' => 'Certificado do Termo ainda não válido.',
                        ];
                    }
                    // Uso de chave: digitalSignature / nonRepudiation
                    $ku = $parsed['extensions']['keyUsage'] ?? '';
                    if (is_string($ku) && $ku !== ''
                        && ! str_contains(strtolower($ku), 'digital signature')
                        && ! str_contains(strtolower($ku), 'non repudiation')
                        && ! str_contains(strtolower($ku), 'nonrepudiation')) {
                        return [
                            'ok' => false,
                            'cert_ok' => false,
                            'cert_identity' => null,
                            'error' => 'CERT_KEY_USAGE_INVALID',
                            'message' => 'Certificado não permite assinatura digital.',
                        ];
                    }
                    $cn = (string) ($parsed['subject']['CN'] ?? '');
                    if (preg_match('/(\d{11}|\d{14})/', $cn, $m)) {
                        $certIdentity = $m[1];
                    }
                    $serial = (string) ($parsed['subject']['serialNumber'] ?? '');
                    if ($certIdentity === null && preg_match('/(\d{11}|\d{14})/', $serial, $m2)) {
                        $certIdentity = $m2[1];
                    }
                }
            }

            if (! $certOk) {
                return [
                    'ok' => false,
                    'cert_ok' => false,
                    'cert_identity' => null,
                    'error' => 'CERT_INVALID',
                    'message' => 'Certificado X.509 embutido inválido.',
                ];
            }

            return [
                'ok' => true,
                'cert_ok' => $certOk,
                'cert_identity' => $certIdentity,
                'error' => null,
                'message' => null,
            ];
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'cert_ok' => false,
                'cert_identity' => null,
                'error' => 'XMLDSIG_ERROR',
                'message' => 'Falha na verificação XMLDSig: '.$e->getMessage(),
            ];
        }
    }

    private function certPemFromBase64(string $b64): string
    {
        $b64 = preg_replace('/\s+/', '', $b64) ?? '';

        return "-----BEGIN CERTIFICATE-----\n".chunk_split($b64, 64, "\n")."-----END CERTIFICATE-----\n";
    }

    /**
     * Datas oficiais AAAAMMDD ou ISO.
     */
    private function parseOfficialDate(?string $raw): ?CarbonImmutable
    {
        if ($raw === null || trim($raw) === '') {
            return null;
        }
        $raw = trim($raw);
        if (preg_match('/^\d{8}$/', $raw)) {
            try {
                return CarbonImmutable::createFromFormat('Ymd', $raw)?->startOfDay();
            } catch (\Throwable) {
                return null;
            }
        }
        try {
            return CarbonImmutable::parse($raw);
        } catch (\Throwable) {
            return null;
        }
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
                if ($text === '' && $nodes->item(0)?->nodeType === XML_ATTRIBUTE_NODE) {
                    $text = trim((string) $nodes->item(0)->nodeValue);
                }

                return $text !== '' ? $text : null;
            }
        }

        return null;
    }

    private function normalizeId(string $raw): string
    {
        return strtoupper(preg_replace('/[^0-9A-Za-z]/', '', $raw) ?? '');
    }

    /**
     * @param  array<string, mixed>  $limits
     */
    private function reject(
        string $code,
        string $message,
        array $limits,
        ?string $sha256 = null,
        ?string $signedBy = null,
        ?string $author = null,
    ): TermoValidationResult {
        return new TermoValidationResult(
            valid: false,
            errorCode: $code,
            errorMessage: $message,
            signedBy: $signedBy,
            authorIdentity: $author,
            sha256: $sha256,
            limits: $limits,
            authorizationState: TermoAuthorizationState::Rejected->value,
        );
    }
}

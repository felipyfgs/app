<?php

namespace Tests\Support;

use App\Services\Integra\TermoAutorizacaoGenerator;
use App\Services\Integra\TermoXmlSigner;
use Carbon\CarbonImmutable;
use DOMDocument;

/**
 * Fixtures sintéticas do Termo (sem PFX/identidades reais de clientes).
 */
final class TermoFixtureFactory
{
    public static function defaultAuthorCpf(): string
    {
        // CPF sintético com DV válido (sem identidade real de cliente).
        return '52998224725';
    }

    public static function defaultDestinationCnpj(): string
    {
        return '11222333000181';
    }

    /**
     * @return array{xml: string, private_pem: string, cert_pem: string}
     */
    public static function signedTermo(
        string $authorIdentity = '52998224725',
        string $destinationCnpj = '11222333000181',
        string $authorName = 'Autor Teste Sintetico',
        string $destinationName = 'CONTRATANTE TESTE LTDA',
        string $validTo = '20271231',
        ?string $dataAssinatura = null,
    ): array {
        $tipo = strlen($authorIdentity) === 11 ? 'PF' : 'PJ';
        $generator = new TermoAutorizacaoGenerator;
        $unsigned = $generator->generateUnsigned(
            destinationCnpj: $destinationCnpj,
            destinationName: $destinationName,
            authorIdentity: $authorIdentity,
            authorName: $authorName,
            authorTipo: $tipo,
            dataAssinatura: $dataAssinatura ?? CarbonImmutable::now('America/Sao_Paulo')->format('Ymd'),
            vigencia: $validTo,
        );

        [$privatePem, $certPem] = self::syntheticKeyPair($authorIdentity);
        $signer = new TermoXmlSigner;
        $signed = $signer->sign($unsigned, $privatePem, $certPem);

        return ['xml' => $signed, 'private_pem' => $privatePem, 'cert_pem' => $certPem];
    }

    public static function unsignedDraft(
        string $authorIdentity = '52998224725',
        string $destinationCnpj = '11222333000181',
    ): string {
        $tipo = strlen($authorIdentity) === 11 ? 'PF' : 'PJ';
        $generator = new TermoAutorizacaoGenerator;

        return $generator->generateUnsigned(
            destinationCnpj: $destinationCnpj,
            destinationName: 'CONTRATANTE TESTE LTDA',
            authorIdentity: $authorIdentity,
            authorName: 'Autor Teste Sintetico',
            authorTipo: $tipo,
            dataAssinatura: CarbonImmutable::now('America/Sao_Paulo')->format('Ymd'),
            vigencia: '20271231',
        );
    }

    /**
     * XML com wrapping: identidade não assinada fora do nó dados (pós-assinatura).
     */
    public static function wrappingAttackXml(): string
    {
        $fixture = self::signedTermo();
        $dom = new DOMDocument;
        $dom->loadXML($fixture['xml'], LIBXML_NONET);
        $root = $dom->documentElement;
        $evil = $dom->createElement('assinadoPor');
        $evil->setAttribute('numero', '99999999999');
        $evil->setAttribute('nome', 'Attacker');
        $evil->setAttribute('tipo', 'PF');
        $evil->setAttribute('papel', 'autor pedido de dados');
        // Inserir antes de Signature (fora de dados).
        $root?->insertBefore($evil, $root->lastChild);

        return $dom->saveXML() ?: $fixture['xml'];
    }

    /**
     * Layout legado TermoAutorizacao (deve ser rejeitado).
     */
    public static function legacyRootXml(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<TermoAutorizacao Id="termo-1">
  <assinadoPor>52998224725</assinadoPor>
  <autorPedido>52998224725</autorPedido>
  <destinatario>11222333000181</destinatario>
  <dataInicioVigencia>2026-01-01</dataInicioVigencia>
  <dataFimVigencia>2027-12-31</dataFimVigencia>
</TermoAutorizacao>
XML;
    }

    /**
     * @return array{0: string, 1: string} privatePem, certPem
     */
    public static function syntheticKeyPair(string $identity): array
    {
        $privateKey = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
            'digest_alg' => 'sha256',
        ]);
        if ($privateKey === false) {
            throw new \RuntimeException('openssl_pkey_new failed');
        }
        $csr = openssl_csr_new([
            'commonName' => 'Autor Sintetico:'.$identity,
            'serialNumber' => $identity,
            'countryName' => 'BR',
        ], $privateKey, ['digest_alg' => 'sha256']);
        if ($csr === false) {
            throw new \RuntimeException('openssl_csr_new failed');
        }
        $certificate = openssl_csr_sign($csr, null, $privateKey, 365, ['digest_alg' => 'sha256']);
        if ($certificate === false) {
            throw new \RuntimeException('openssl_csr_sign failed');
        }
        openssl_pkey_export($privateKey, $privatePem);
        openssl_x509_export($certificate, $certificatePem);

        return [$privatePem, $certificatePem];
    }
}

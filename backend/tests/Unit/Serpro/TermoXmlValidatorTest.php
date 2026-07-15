<?php

namespace Tests\Unit\Serpro;

use App\Services\Integra\TermoXmlValidator;
use PHPUnit\Framework\TestCase;

class TermoXmlValidatorTest extends TestCase
{
    private TermoXmlValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new TermoXmlValidator;
    }

    public function test_termo_valido_estrutura_critica(): void
    {
        $xml = $this->sampleTermo(
            signedBy: '12345678901',
            destinario: '11222333000181',
            author: '12345678901',
        );

        $result = $this->validator->validate($xml, '12345678901', '11222333000181');

        $this->assertTrue($result->valid);
        $this->assertNotNull($result->sha256);
        $this->assertTrue($result->signatureChecked);
    }

    public function test_signatario_divergente_rejeita(): void
    {
        $xml = $this->sampleTermo(
            signedBy: '99999999999',
            destinario: '11222333000181',
            author: '12345678901',
        );

        $result = $this->validator->validate($xml, '12345678901', '11222333000181');

        $this->assertFalse($result->valid);
        $this->assertContains($result->errorCode, ['SIGNER_MISMATCH', 'AUTHOR_MISMATCH']);
    }

    public function test_destinatario_divergente_rejeita(): void
    {
        $xml = $this->sampleTermo(
            signedBy: '12345678901',
            destinario: '99888777000166',
            author: '12345678901',
        );

        $result = $this->validator->validate($xml, '12345678901', '11222333000181');

        $this->assertFalse($result->valid);
        $this->assertSame('DESTINATION_MISMATCH', $result->errorCode);
    }

    public function test_sem_signature_rejeita(): void
    {
        $xml = <<<'XML'
<?xml version="1.0"?>
<TermoAutorizacao>
  <assinadoPor>12345678901</assinadoPor>
  <autorPedido>12345678901</autorPedido>
  <destinatario>11222333000181</destinatario>
  <dataInicioVigencia>2026-01-01</dataInicioVigencia>
  <dataFimVigencia>2027-12-31</dataFimVigencia>
</TermoAutorizacao>
XML;

        $result = $this->validator->validate($xml, '12345678901', '11222333000181');
        $this->assertFalse($result->valid);
        $this->assertSame('MISSING_SIGNATURE', $result->errorCode);
    }

    public function test_termo_expirado_rejeita(): void
    {
        $xml = $this->sampleTermo(
            signedBy: '12345678901',
            destinario: '11222333000181',
            author: '12345678901',
            validTo: '2020-01-01',
        );

        $result = $this->validator->validate($xml, '12345678901', '11222333000181');
        $this->assertFalse($result->valid);
        $this->assertSame('TERM_EXPIRED', $result->errorCode);
    }

    private function sampleTermo(
        string $signedBy,
        string $destinario,
        string $author,
        string $validTo = '2027-12-31',
    ): string {
        return <<<XML
<?xml version="1.0"?>
<TermoAutorizacao>
  <assinadoPor>{$signedBy}</assinadoPor>
  <autorPedido>{$author}</autorPedido>
  <destinatario>{$destinario}</destinatario>
  <dataInicioVigencia>2026-01-01</dataInicioVigencia>
  <dataFimVigencia>{$validTo}</dataFimVigencia>
  <Signature xmlns="http://www.w3.org/2000/09/xmldsig#">
    <SignedInfo><CanonicalizationMethod/><SignatureMethod/><Reference><DigestMethod/><DigestValue>abc</DigestValue></Reference></SignedInfo>
    <SignatureValue>ZmFrZS1zaWduYXR1cmU=</SignatureValue>
  </Signature>
</TermoAutorizacao>
XML;
    }
}

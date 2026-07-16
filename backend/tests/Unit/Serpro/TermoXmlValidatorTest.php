<?php

namespace Tests\Unit\Serpro;

use App\Enums\TermoAuthorizationState;
use App\Services\Integra\TermoAutorizacaoGenerator;
use App\Services\Integra\TermoXmlValidator;
use PHPUnit\Framework\TestCase;
use Tests\Support\TermoFixtureFactory;

class TermoXmlValidatorTest extends TestCase
{
    private TermoXmlValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new TermoXmlValidator;
    }

    public function test_termo_valido_estrutura_critica_e_local_validated(): void
    {
        $author = TermoFixtureFactory::defaultAuthorCpf();
        $dest = TermoFixtureFactory::defaultDestinationCnpj();
        $fixture = TermoFixtureFactory::signedTermo($author, $dest);

        $result = $this->validator->validate($fixture['xml'], $author, $dest);

        $this->assertTrue($result->valid, ($result->errorCode ?? '').': '.($result->errorMessage ?? ''));
        $this->assertNotNull($result->sha256);
        $this->assertTrue($result->signatureChecked);
        $this->assertSame(TermoAuthorizationState::LocalValidated->value, $result->authorizationState);
        $this->assertNotSame(TermoAuthorizationState::SerproAccepted->value, $result->authorizationState);
    }

    public function test_raiz_legada_termo_autorizacao_rejeita(): void
    {
        $xml = TermoFixtureFactory::legacyRootXml();
        $result = $this->validator->validate(
            $xml,
            TermoFixtureFactory::defaultAuthorCpf(),
            TermoFixtureFactory::defaultDestinationCnpj(),
        );

        $this->assertFalse($result->valid);
        $this->assertSame('LEGACY_OR_INVALID_ROOT', $result->errorCode);
    }

    public function test_signatario_divergente_rejeita(): void
    {
        $fixture = TermoFixtureFactory::signedTermo('11144477735', TermoFixtureFactory::defaultDestinationCnpj());

        $result = $this->validator->validate(
            $fixture['xml'],
            TermoFixtureFactory::defaultAuthorCpf(),
            TermoFixtureFactory::defaultDestinationCnpj(),
        );

        $this->assertFalse($result->valid);
        $this->assertContains($result->errorCode, ['SIGNER_MISMATCH', 'AUTHOR_MISMATCH', 'CERT_IDENTITY_MISMATCH']);
    }

    public function test_destinatario_divergente_rejeita(): void
    {
        $author = TermoFixtureFactory::defaultAuthorCpf();
        $fixture = TermoFixtureFactory::signedTermo($author, '99888777000100');

        $result = $this->validator->validate(
            $fixture['xml'],
            $author,
            TermoFixtureFactory::defaultDestinationCnpj(),
        );

        $this->assertFalse($result->valid);
        $this->assertSame('DESTINATION_MISMATCH', $result->errorCode);
    }

    public function test_sem_signature_rejeita(): void
    {
        $xml = TermoFixtureFactory::unsignedDraft();
        $result = $this->validator->validate(
            $xml,
            TermoFixtureFactory::defaultAuthorCpf(),
            TermoFixtureFactory::defaultDestinationCnpj(),
        );

        $this->assertFalse($result->valid);
        $this->assertSame('MISSING_SIGNATURE', $result->errorCode);
    }

    public function test_termo_expirado_rejeita(): void
    {
        $author = TermoFixtureFactory::defaultAuthorCpf();
        $dest = TermoFixtureFactory::defaultDestinationCnpj();
        $fixture = TermoFixtureFactory::signedTermo(
            $author,
            $dest,
            validTo: '20200101',
        );

        $result = $this->validator->validate($fixture['xml'], $author, $dest);

        $this->assertFalse($result->valid);
        $this->assertSame('TERM_EXPIRED', $result->errorCode);
    }

    public function test_signature_wrapping_rejeita(): void
    {
        $xml = TermoFixtureFactory::wrappingAttackXml();
        $result = $this->validator->validate(
            $xml,
            TermoFixtureFactory::defaultAuthorCpf(),
            TermoFixtureFactory::defaultDestinationCnpj(),
        );

        $this->assertFalse($result->valid);
        $this->assertContains($result->errorCode, [
            'UNEXPECTED_ROOT_CHILD',
            'SIGNATURE_WRAPPING',
            'REFERENCE_URI',
            'SIGNATURE_COUNT',
        ]);
    }

    public function test_doctype_entity_rejeita(): void
    {
        $xml = <<<'XML'
<?xml version="1.0"?>
<!DOCTYPE foo [<!ENTITY xxe SYSTEM "file:///etc/passwd">]>
<termoDeAutorizacao>
  <dados>&xxe;</dados>
</termoDeAutorizacao>
XML;

        $result = $this->validator->validate(
            $xml,
            TermoFixtureFactory::defaultAuthorCpf(),
            TermoFixtureFactory::defaultDestinationCnpj(),
        );

        $this->assertFalse($result->valid);
        $this->assertSame('UNSAFE_XML', $result->errorCode);
    }

    public function test_textos_legais_canonicos_no_gerador(): void
    {
        $gen = new TermoAutorizacaoGenerator;
        $xml = $gen->generateUnsigned(
            TermoFixtureFactory::defaultDestinationCnpj(),
            'CONTRATANTE',
            TermoFixtureFactory::defaultAuthorCpf(),
            'Autor',
            'PF',
            '20260716',
            '20271231',
        );

        $this->assertStringContainsString('termoDeAutorizacao', $xml);
        $this->assertStringContainsString('API Integra Contador', $xml);
        $this->assertStringContainsString('papel="contratante"', $xml);
        $this->assertStringContainsString('papel="autor pedido de dados"', $xml);
    }
}

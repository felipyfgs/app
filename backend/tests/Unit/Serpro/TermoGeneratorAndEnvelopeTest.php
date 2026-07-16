<?php

namespace Tests\Unit\Serpro;

use App\Enums\TermoAuthorizationState;
use App\Services\Integra\HttpAutenticarProcuradorClient;
use App\Services\Integra\TermoAutorizacaoGenerator;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;
use Tests\Support\TermoFixtureFactory;

class TermoGeneratorAndEnvelopeTest extends TestCase
{
    public function test_gerador_deterministico_para_mesmas_entradas(): void
    {
        $gen = new TermoAutorizacaoGenerator;
        $a = $gen->generateUnsigned(
            '11222333000181',
            'EMPRESA X',
            TermoFixtureFactory::defaultAuthorCpf(),
            'Autor',
            'PF',
            '20260716',
            '20271231',
        );
        $b = $gen->generateUnsigned(
            '11222333000181',
            'EMPRESA X',
            TermoFixtureFactory::defaultAuthorCpf(),
            'Autor',
            'PF',
            '20260716',
            '20271231',
        );

        $this->assertSame($a, $b);
        $this->assertStringStartsWith('<?xml version="1.0" encoding="UTF-8"?>', $a);
        $this->assertStringContainsString('<termoDeAutorizacao>', $a);
        $this->assertStringContainsString('<dados>', $a);
        $this->assertStringContainsString('id="API Integra Contador"', $a);
        $this->assertStringContainsString('papel="contratante"', $a);
        $this->assertStringContainsString('papel="autor pedido de dados"', $a);
        $this->assertStringNotContainsString('<Signature', $a);
    }

    public function test_envelope_base64_round_trip_e_bloqueia_xml_assinado(): void
    {
        $fixture = TermoFixtureFactory::signedTermo();
        $envelope = HttpAutenticarProcuradorClient::buildPedidoDadosEnvelope($fixture['xml']);

        $this->assertArrayHasKey('dados', $envelope);
        $decoded = json_decode($envelope['dados'], true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('xml', $decoded);
        $this->assertArrayNotHasKey('xmlAssinado', $decoded);
        $this->assertSame($fixture['xml'], base64_decode((string) $decoded['xml'], true));

        // dados é JSON string (não objeto no envelope final do pedido — string escapável)
        $this->assertIsString($envelope['dados']);
        $this->assertStringStartsWith('{', $envelope['dados']);
    }

    public function test_local_validated_nunca_e_serpro_accepted(): void
    {
        $this->assertNotSame(
            TermoAuthorizationState::LocalValidated->value,
            TermoAuthorizationState::SerproAccepted->value,
        );
        $this->assertFalse(TermoAuthorizationState::LocalValidated->isRemoteAccepted());
        $this->assertFalse(TermoAuthorizationState::Simulated->isRemoteAccepted());
        $this->assertTrue(TermoAuthorizationState::SerproAccepted->isRemoteAccepted());
    }

    public function test_schema_meta_declara_derived_not_official(): void
    {
        $metaPath = dirname(__DIR__, 3).'/resources/serpro/xsd/termo-autorizacao.v1.meta.json';
        $this->assertFileExists($metaPath);
        $meta = json_decode((string) file_get_contents($metaPath), true, 512, JSON_THROW_ON_ERROR);
        $this->assertFalse($meta['official_xsd']);
        $this->assertStringContainsString('DERIVED', $meta['label']);
        $this->assertNotEmpty($meta['content_sha256']);
        $this->assertSame('termoDeAutorizacao', $meta['root_element']);

        $xsd = (string) file_get_contents(dirname($metaPath).'/termo-autorizacao.v1.xsd');
        $this->assertSame($meta['content_sha256'], hash('sha256', $xsd));
        $this->assertStringContainsString('NOT an official SERPRO XSD', $xsd);
    }

    public function test_datas_aceita_carbon(): void
    {
        $gen = new TermoAutorizacaoGenerator;
        $xml = $gen->generateUnsigned(
            '11222333000181',
            'EMPRESA',
            TermoFixtureFactory::defaultAuthorCpf(),
            'Autor',
            'PF',
            CarbonImmutable::create(2026, 7, 16),
            CarbonImmutable::create(2027, 12, 31),
        );
        $this->assertStringContainsString('data="20260716"', $xml);
        $this->assertStringContainsString('data="20271231"', $xml);
    }
}

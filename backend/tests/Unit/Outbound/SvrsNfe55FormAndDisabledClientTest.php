<?php

namespace Tests\Unit\Outbound;

use App\DTO\Outbound\SvrsNfceRetrievalRequest;
use App\Enums\SvrsNfceTransportOutcome;
use App\Services\Outbound\DisabledSvrsNfe55OutboundXmlRetrievalClient;
use App\Services\Outbound\HttpSvrsNfe55OutboundXmlRetrievalClient;
use App\Services\Outbound\SvrsNfceConfig;
use App\Services\Outbound\SvrsNfceDownloadResponseParser;
use App\Services\Outbound\SvrsNfe55Config;
use App\Services\Outbound\SvrsNfe55KillSwitchService;
use ReflectionClass;
use Tests\TestCase;

class SvrsNfe55FormAndDisabledClientTest extends TestCase
{
    public function test_form_nfe55_fixture_ok(): void
    {
        $html = file_get_contents(base_path('tests/fixtures/svrs-nfe55/get_form_ok.html'));
        $parser = new SvrsNfceDownloadResponseParser(new SvrsNfceConfig);
        $result = $parser->parseFormPage((string) $html);
        $this->assertSame(SvrsNfceTransportOutcome::FormOk, $result->outcome);
    }

    public function test_post_success_fixture_captura_literal(): void
    {
        $html = file_get_contents(base_path('tests/fixtures/svrs-nfe55/post_success.html'));
        $parser = new SvrsNfceDownloadResponseParser(new SvrsNfceConfig);
        $result = $parser->parseDownloadPage((string) $html);
        $this->assertSame(SvrsNfceTransportOutcome::Captured, $result->outcome);
        $this->assertNotNull($result->xmlBytes);
        $this->assertStringContainsString('nfeProc', (string) $result->xmlBytes);
    }

    public function test_parser_compartilhado_aceita_contrato_blob_por_propriedade(): void
    {
        $html = file_get_contents(base_path('tests/fixtures/svrs-nfce/post_success_object_property.html'));
        $parser = new SvrsNfceDownloadResponseParser(new SvrsNfceConfig);
        $result = $parser->parseDownloadPage((string) $html);

        $this->assertSame(SvrsNfceTransportOutcome::Captured, $result->outcome);
        $this->assertNotNull($result->xmlBytes);
        $this->assertStringStartsWith('<nfeProc', (string) $result->xmlBytes);
    }

    public function test_disabled_client_nfe55(): void
    {
        $client = new DisabledSvrsNfe55OutboundXmlRetrievalClient;
        $this->assertFalse($client->isAvailable());
        $r = $client->retrieve(new SvrsNfceRetrievalRequest(
            accessKey: '21260700000000000000550010000000011000000010',
            environment: 'production',
            correlationId: 'c',
            officeId: 1,
            profileId: 1,
            clientId: 1,
            establishmentId: 1,
        ), ['pfx' => 'x', 'password' => 'y']);
        $this->assertSame(SvrsNfceTransportOutcome::ChannelDisabled, $r->outcome);
    }

    public function test_cliente_nfe55_nao_propaga_segredos_em_resultado_ou_excecao_sanitizada(): void
    {
        config(['sefaz.svrs_nfe55_xml.retrieval_enabled' => true]);
        $fullKey = '21260712345678000190550010000000011000000010';
        $password = 'senha-ultrassecreta-test-only';
        $pfxMarker = 'PFX-BYTES-NAO-PODEM-VAZAR';
        $client = new HttpSvrsNfe55OutboundXmlRetrievalClient(
            new SvrsNfe55Config,
            app(SvrsNfceDownloadResponseParser::class),
            app(SvrsNfe55KillSwitchService::class),
        );

        // PFX vazio encerra antes da rede, exercitando o retorno de erro público.
        $result = $client->retrieve(new SvrsNfceRetrievalRequest(
            accessKey: $fullKey,
            environment: 'production',
            correlationId: 'anti-secret-nfe55',
            officeId: 1,
            profileId: 1,
            clientId: 1,
            establishmentId: 1,
        ), ['pfx' => '', 'password' => $password, 'marker' => $pfxMarker]);

        $encoded = json_encode($result, JSON_THROW_ON_ERROR);
        $this->assertSame(SvrsNfceTransportOutcome::AuthForbidden, $result->outcome);
        $this->assertStringNotContainsString($fullKey, $encoded);
        $this->assertStringNotContainsString($password, $encoded);
        $this->assertStringNotContainsString($pfxMarker, $encoded);
        $this->assertStringNotContainsString('<nfeProc', $encoded);

        $source = file_get_contents(
            (new ReflectionClass(HttpSvrsNfe55OutboundXmlRetrievalClient::class))->getFileName()
        );
        $this->assertNotFalse($source);
        $this->assertStringNotContainsString('Log::', (string) $source);
        $this->assertStringNotContainsString('file_put_contents($pfx', (string) $source);
        $this->assertStringNotContainsString('detail: $e->getMessage()', (string) $source);
    }

    public function test_nfe55_paths_allowlisted(): void
    {
        $cfg = new SvrsNfe55Config;
        $this->assertStringContainsString('/NFESSL/', $cfg->getPath());
        $this->assertStringContainsString('NfeSSL', $cfg->postPath());
        $this->assertSame('Nfe', $cfg->postStaticFields()['sistema']);
        $this->assertSame('2', $cfg->parserVersion());
        $this->assertFalse($cfg->retrievalEnabled());
    }
}

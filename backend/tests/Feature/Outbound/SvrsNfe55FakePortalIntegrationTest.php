<?php

namespace Tests\Feature\Outbound;

use App\DTO\Outbound\SvrsNfceRetrievalRequest;
use App\Enums\SvrsNfceTransportOutcome;
use App\Services\Outbound\HttpSvrsNfe55OutboundXmlRetrievalClient;
use App\Services\Outbound\SvrsNfceConfig;
use App\Services\Outbound\SvrsNfceDownloadResponseParser;
use App\Services\Outbound\SvrsNfe55Config;
use App\Services\Outbound\SvrsNfe55KillSwitchService;
use Illuminate\Support\Facades\Cache;
use Tests\Support\FakeSvrsMtlsPortal;
use Tests\TestCase;

class SvrsNfe55FakePortalIntegrationTest extends TestCase
{
    public function test_cliente_executa_get_post_mtls_e_captura_nfe55_no_fake_portal(): void
    {
        if (! function_exists('pcntl_fork') || ! defined('CURLOPT_SSLCERT_BLOB')) {
            $this->markTestSkipped('pcntl ou CURLOPT_SSLCERT_BLOB indisponível.');
        }

        $dir = sys_get_temp_dir().'/svrs-nfe55-fake-'.uniqid();
        mkdir($dir, 0700);
        $portal = null;

        try {
            $certificates = $this->generateCertificates($dir);
            $key = '21260712345678000190550010000000011000000010';
            $port = FakeSvrsMtlsPortal::freePort();
            $getBody = (string) file_get_contents(base_path('tests/fixtures/svrs-nfe55/get_form_ok.html'));
            $postBody = (string) file_get_contents(base_path('tests/fixtures/svrs-nfe55/post_success.html'));

            $portal = FakeSvrsMtlsPortal::start(
                port: $port,
                serverCertificate: $certificates['server_crt'],
                serverKey: $certificates['server_key'],
                caCertificate: $certificates['ca_crt'],
                getBody: $getBody,
                postBody: $postBody,
                expectedAccessKey: $key,
            );

            config([
                'sefaz.ca_bundle' => $certificates['ca_crt'],
                'sefaz.svrs_nfe55_xml.retrieval_enabled' => true,
                'sefaz.svrs_nfe55_xml.kill_switch' => false,
                'sefaz.svrs_nfe55_xml.host' => 'localhost',
                'sefaz.svrs_nfe55_xml.port' => $port,
                'sefaz.svrs_nfe55_xml.allowed_hosts' => ['localhost'],
                'sefaz.svrs_nfe55_xml.get_path' => '/NFESSL/DownloadXMLDFe',
                'sefaz.svrs_nfe55_xml.post_path' => '/NfeSSL/DownloadXmlDfe',
                'sefaz.svrs_nfe55_xml.post_fields' => ['sistema' => 'Nfe', 'OrigemSite' => '0'],
            ]);
            Cache::forget('sefaz.svrs_nfe55_xml.kill_switch.runtime');

            $client = new HttpSvrsNfe55OutboundXmlRetrievalClient(
                new SvrsNfe55Config,
                new SvrsNfceDownloadResponseParser(new SvrsNfceConfig),
                app(SvrsNfe55KillSwitchService::class),
            );
            $pfx = file_get_contents($certificates['client_pfx']);
            $this->assertNotFalse($pfx);

            $result = $client->retrieve(new SvrsNfceRetrievalRequest(
                accessKey: $key,
                environment: 'production',
                correlationId: 'fake-svrs-nfe55',
                officeId: 1,
                profileId: 1,
                clientId: 1,
                establishmentId: 1,
            ), ['pfx' => $pfx, 'password' => 'test']);

            $this->assertSame(SvrsNfceTransportOutcome::Captured, $result->outcome, $result->sanitizedDetail ?? '');
            $this->assertSame(200, $result->httpStatus);
            $this->assertNotNull($result->xmlBytes);
            $this->assertStringContainsString('<nfeProc', (string) $result->xmlBytes);
            $this->assertSame(hash('sha256', (string) $result->xmlBytes), $result->sha256);
            $this->assertSame(0, $portal->wait(), 'fake portal deve validar GET, cookie e POST allowlisted');
            $portal = null;
        } finally {
            if ($portal !== null) {
                $portal->wait();
            }
            Cache::forget('sefaz.svrs_nfe55_xml.kill_switch.runtime');
            foreach (glob($dir.'/*') ?: [] as $file) {
                @unlink($file);
            }
            @rmdir($dir);
        }
    }

    /** @return array{ca_crt: string, server_key: string, server_crt: string, client_pfx: string} */
    private function generateCertificates(string $dir): array
    {
        $caKey = $dir.'/ca.key';
        $caCrt = $dir.'/ca.crt';
        $serverKey = $dir.'/server.key';
        $serverCrt = $dir.'/server.crt';
        $clientKey = $dir.'/client.key';
        $clientCrt = $dir.'/client.crt';
        $clientPfx = $dir.'/client.pfx';
        $serverConfig = $dir.'/server.cnf';
        file_put_contents($serverConfig, "[req]\ndistinguished_name=dn\n[dn]\n[v3]\nsubjectAltName=DNS:localhost,IP:127.0.0.1\n");

        $commands = [
            "openssl req -x509 -newkey rsa:2048 -keyout {$caKey} -out {$caCrt} -days 1 -nodes -subj '/CN=SVRS-Fake-CA' 2>/dev/null",
            "openssl req -newkey rsa:2048 -keyout {$serverKey} -out {$dir}/server.csr -nodes -subj '/CN=localhost' 2>/dev/null",
            "openssl x509 -req -in {$dir}/server.csr -CA {$caCrt} -CAkey {$caKey} -CAcreateserial -out {$serverCrt} -days 1 -extfile {$serverConfig} -extensions v3 2>/dev/null",
            "openssl req -newkey rsa:2048 -keyout {$clientKey} -out {$dir}/client.csr -nodes -subj '/CN=SVRS-Fake-Client' 2>/dev/null",
            "openssl x509 -req -in {$dir}/client.csr -CA {$caCrt} -CAkey {$caKey} -CAcreateserial -out {$clientCrt} -days 1 2>/dev/null",
            "openssl pkcs12 -export -out {$clientPfx} -inkey {$clientKey} -in {$clientCrt} -passout pass:test 2>/dev/null",
        ];
        foreach ($commands as $command) {
            exec($command, $output, $code);
            if ($code !== 0) {
                $this->fail('openssl falhou ao preparar o fake portal mTLS.');
            }
        }

        return [
            'ca_crt' => $caCrt,
            'server_key' => $serverKey,
            'server_crt' => $serverCrt,
            'client_pfx' => $clientPfx,
        ];
    }
}

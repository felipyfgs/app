<?php

namespace Tests\Feature\Outbound;

use App\Contracts\SvrsNfceOutboundXmlRetrievalClient;
use App\DTO\Outbound\SvrsNfceRetrievalRequest;
use App\Enums\SvrsNfceTransportOutcome;
use App\Services\Outbound\HttpSvrsNfceOutboundXmlRetrievalClient;
use App\Services\Outbound\SvrsNfceConfig;
use App\Services\Outbound\SvrsNfceDownloadResponseParser;
use App\Services\Outbound\SvrsNfceKillSwitchService;
use ReflectionClass;
use Tests\TestCase;

/**
 * 6.8 — Segurança mTLS do cliente SVRS + servidor local quando openssl s_server disponível.
 * CI sem rede fiscal; certificado de teste exclusivo do repositório.
 */
class SvrsNfceMtlsLocalTest extends TestCase
{
    public function test_cliente_forca_tls12_blob_e_rejeita_redirect_externo(): void
    {
        $source = file_get_contents(
            (new ReflectionClass(HttpSvrsNfceOutboundXmlRetrievalClient::class))->getFileName()
        );
        $this->assertNotFalse($source);
        $this->assertStringContainsString('CURLOPT_SSLCERT_BLOB', $source);
        $this->assertStringContainsString('CURLOPT_SSL_VERIFYPEER', $source);
        $this->assertStringContainsString('CURLOPT_SSL_VERIFYHOST', $source);
        $this->assertStringContainsString('CURL_SSLVERSION_TLSv1_2', $source);
        $this->assertStringContainsString('CURLOPT_FOLLOWLOCATION => false', $source);
        $this->assertStringContainsString('CURLPROTO_HTTPS', $source);
        $this->assertStringContainsString('redirect rejected', $source);
        // Cookie jar efêmero + limpeza
        $this->assertStringContainsString('COOKIEJAR', $source);
        $this->assertStringContainsString('@unlink($cookieFile)', $source);
        // PFX não em PEM em disco permanente
        $this->assertStringNotContainsString('file_put_contents($pfx', $source);
    }

    public function test_host_e_url_nao_allowlisted_rejeitados(): void
    {
        $cfg = new SvrsNfceConfig;
        $this->expectException(\InvalidArgumentException::class);
        $cfg->assertUrlAllowed('https://evil.example/NfceSSL/DownloadXmlDfe');
    }

    public function test_http_plain_rejeitado(): void
    {
        $cfg = new SvrsNfceConfig;
        $this->expectException(\InvalidArgumentException::class);
        $cfg->assertUrlAllowed('http://dfe-portal.svrs.rs.gov.br/NFCESSL/DownloadXMLDFe');
    }

    public function test_credencial_vazia_nao_dispara_rede(): void
    {
        config(['sefaz.svrs_nfce_xml.retrieval_enabled' => true]);
        $client = app(HttpSvrsNfceOutboundXmlRetrievalClient::class);
        $req = new SvrsNfceRetrievalRequest(
            accessKey: '21260712345678000190650010000000011234567892',
            environment: 'homologation',
            correlationId: 'test-corr',
            officeId: 1,
            profileId: 1,
            clientId: 1,
            establishmentId: 1,
        );
        $result = $client->retrieve($req, ['pfx' => '', 'password' => '']);
        $this->assertSame(SvrsNfceTransportOutcome::AuthForbidden, $result->outcome);
        $this->assertNull($result->xmlBytes);
    }

    public function test_flag_off_e_kill_switch_antes_de_materializar_certificado(): void
    {
        config(['sefaz.svrs_nfce_xml.retrieval_enabled' => false]);
        $client = app(HttpSvrsNfceOutboundXmlRetrievalClient::class);
        $req = new SvrsNfceRetrievalRequest(
            accessKey: '21260712345678000190650010000000011234567892',
            environment: 'homologation',
            correlationId: 'test-corr',
            officeId: 1,
            profileId: 1,
            clientId: 1,
            establishmentId: 1,
        );
        $r = $client->retrieve($req, ['pfx' => 'dummy', 'password' => 'x']);
        $this->assertSame(SvrsNfceTransportOutcome::ChannelDisabled, $r->outcome);

        config(['sefaz.svrs_nfce_xml.retrieval_enabled' => true]);
        \Illuminate\Support\Facades\Cache::forever('sefaz.svrs_nfce_xml.kill_switch.runtime', true);
        $client2 = new HttpSvrsNfceOutboundXmlRetrievalClient(
            new SvrsNfceConfig,
            app(SvrsNfceDownloadResponseParser::class),
            app(SvrsNfceKillSwitchService::class),
        );
        $r2 = $client2->retrieve($req, ['pfx' => 'dummy', 'password' => 'x']);
        $this->assertSame(SvrsNfceTransportOutcome::KillSwitch, $r2->outcome);
        \Illuminate\Support\Facades\Cache::forget('sefaz.svrs_nfce_xml.kill_switch.runtime');
    }

    public function test_servidor_mtls_local_openssl_quando_disponivel(): void
    {
        $dir = sys_get_temp_dir().'/svrs-mtls-'.uniqid();
        mkdir($dir, 0700);
        $caKey = $dir.'/ca.key';
        $caCrt = $dir.'/ca.crt';
        $srvKey = $dir.'/server.key';
        $srvCrt = $dir.'/server.crt';
        $cliKey = $dir.'/client.key';
        $cliCrt = $dir.'/client.crt';
        $cliPfx = $dir.'/client.pfx';
        $srvConf = $dir.'/server.cnf';

        try {
            file_put_contents($srvConf, "[req]\ndistinguished_name=dn\n[dn]\n[v3]\nsubjectAltName=DNS:localhost,IP:127.0.0.1\n");

            $cmds = [
                "openssl req -x509 -newkey rsa:2048 -keyout {$caKey} -out {$caCrt} -days 1 -nodes -subj '/CN=SVRS-Test-CA' 2>/dev/null",
                "openssl req -newkey rsa:2048 -keyout {$srvKey} -out {$dir}/server.csr -nodes -subj '/CN=localhost' 2>/dev/null",
                "openssl x509 -req -in {$dir}/server.csr -CA {$caCrt} -CAkey {$caKey} -CAcreateserial -out {$srvCrt} -days 1 -extfile {$srvConf} -extensions v3 2>/dev/null",
                "openssl req -newkey rsa:2048 -keyout {$cliKey} -out {$dir}/client.csr -nodes -subj '/CN=SVRS-Test-Client' 2>/dev/null",
                "openssl x509 -req -in {$dir}/client.csr -CA {$caCrt} -CAkey {$caKey} -CAcreateserial -out {$cliCrt} -days 1 2>/dev/null",
                "openssl pkcs12 -export -out {$cliPfx} -inkey {$cliKey} -in {$cliCrt} -passout pass:test 2>/dev/null",
            ];
            foreach ($cmds as $cmd) {
                exec($cmd, $out, $code);
                if ($code !== 0) {
                    $this->markTestSkipped('openssl falhou ao gerar cadeia de teste.');
                }
            }

            if (! is_file($cliPfx) || ! is_file($srvCrt)) {
                $this->markTestSkipped('artefatos openssl ausentes.');
            }

            $port = $this->freePort();
            $log = $dir.'/s_server.log';
            // -www responde HTTP simples; -Verify 1 exige client cert
            $cmd = sprintf(
                'openssl s_server -accept %d -cert %s -key %s -CAfile %s -Verify 1 -www -naccept 2 >%s 2>&1 & echo $!',
                $port,
                escapeshellarg($srvCrt),
                escapeshellarg($srvKey),
                escapeshellarg($caCrt),
                escapeshellarg($log)
            );
            $pid = (int) trim((string) shell_exec($cmd));
            if ($pid < 1) {
                $this->markTestSkipped('não foi possível iniciar openssl s_server.');
            }

            try {
                usleep(300_000);
                $pfx = file_get_contents($cliPfx);
                $this->assertNotFalse($pfx);

                // Certificado aceito (mTLS OK)
                $ok = $this->curlMtls("https://127.0.0.1:{$port}/", $pfx, 'test', $caCrt);
                $this->assertSame(200, $ok['status'], 'cliente válido deve ser aceito: '.$ok['error']);

                // Certificado rejeitado (sem client cert)
                $noClient = $this->curlMtls("https://127.0.0.1:{$port}/", null, null, $caCrt);
                $this->assertTrue(
                    $noClient['status'] === 0 || $noClient['status'] >= 400,
                    'sem client cert deve falhar'
                );

                // TLS inválido (sem verificar CA / host errado) — forçamos falha de peer
                $badCa = $this->curlMtls("https://127.0.0.1:{$port}/", $pfx, 'test', null, verifyPeer: true);
                // Sem CA bundle: peer verify falha
                $this->assertSame(0, $badCa['status']);
                $this->assertNotSame('', $badCa['error']);

                // Redirect externo seria bloqueado pelo cliente (assert no source + config)
                config([
                    'sefaz.svrs_nfce_xml.allowed_hosts' => ['dfe-portal.svrs.rs.gov.br'],
                    'sefaz.svrs_nfce_xml.host' => 'dfe-portal.svrs.rs.gov.br',
                ]);
                $cfg = new SvrsNfceConfig;
                try {
                    $cfg->assertUrlAllowed('https://127.0.0.1/evil');
                    $this->fail('host local não allowlisted deveria falhar');
                } catch (\InvalidArgumentException) {
                    $this->assertTrue(true);
                }
            } finally {
                if ($pid > 0) {
                    if (function_exists('posix_kill')) {
                        @posix_kill($pid, 15);
                    } else {
                        @exec('kill '.$pid.' 2>/dev/null');
                    }
                }
            }
        } finally {
            // limpar artefatos de teste (não deixar PFX em /tmp)
            foreach (glob($dir.'/*') ?: [] as $f) {
                @unlink($f);
            }
            @rmdir($dir);
        }
    }

    /**
     * @return array{status: int, body: string, error: string}
     */
    private function curlMtls(
        string $url,
        ?string $pfx,
        ?string $password,
        ?string $caFile,
        bool $verifyPeer = true,
    ): array {
        $ch = curl_init($url);
        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => $verifyPeer,
            CURLOPT_SSL_VERIFYHOST => $verifyPeer ? 0 : 0, // IP literal
            CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_2,
            CURLOPT_FOLLOWLOCATION => false,
        ];
        if ($caFile !== null) {
            $opts[CURLOPT_CAINFO] = $caFile;
        }
        if ($pfx !== null && defined('CURLOPT_SSLCERT_BLOB')) {
            $opts[CURLOPT_SSLCERTTYPE] = 'P12';
            $opts[CURLOPT_SSLCERT_BLOB] = $pfx;
            $opts[CURLOPT_KEYPASSWD] = (string) $password;
        }
        curl_setopt_array($ch, $opts);
        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        return [
            'status' => $body === false ? 0 : $status,
            'body' => is_string($body) ? $body : '',
            'error' => $error,
        ];
    }

    private function freePort(): int
    {
        $sock = @stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
        if ($sock === false) {
            return 18443;
        }
        $name = stream_socket_get_name($sock, false);
        fclose($sock);
        if (is_string($name) && str_contains($name, ':')) {
            return (int) substr($name, strrpos($name, ':') + 1);
        }

        return 18443;
    }
}

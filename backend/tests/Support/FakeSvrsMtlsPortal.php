<?php

namespace Tests\Support;

use RuntimeException;

/**
 * Portal SVRS local, descartável e restrito aos dois exchanges esperados.
 * Exige certificado cliente, valida cookie/sessão e o contrato do POST.
 */
final class FakeSvrsMtlsPortal
{
    private function __construct(
        private readonly int $pid,
    ) {}

    public static function start(
        int $port,
        string $serverCertificate,
        string $serverKey,
        string $caCertificate,
        string $getBody,
        string $postBody,
        string $expectedAccessKey,
    ): self {
        if (! function_exists('pcntl_fork') || ! function_exists('stream_socket_pair')) {
            throw new RuntimeException('pcntl/stream_socket_pair indisponível.');
        }

        $signals = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
        if ($signals === false) {
            throw new RuntimeException('Não foi possível criar canal de prontidão do fake SVRS.');
        }

        $pid = pcntl_fork();
        if ($pid === -1) {
            throw new RuntimeException('Não foi possível iniciar fake SVRS.');
        }

        if ($pid === 0) {
            fclose($signals[0]);
            $exitCode = self::serve(
                $signals[1],
                $port,
                $serverCertificate,
                $serverKey,
                $caCertificate,
                $getBody,
                $postBody,
                $expectedAccessKey,
            );
            fclose($signals[1]);
            exit($exitCode);
        }

        fclose($signals[1]);
        stream_set_timeout($signals[0], 5);
        $ready = trim((string) fgets($signals[0]));
        fclose($signals[0]);
        if ($ready !== 'READY') {
            @posix_kill($pid, SIGTERM);
            pcntl_waitpid($pid, $status);
            throw new RuntimeException($ready !== '' ? $ready : 'Fake SVRS não iniciou.');
        }

        return new self($pid);
    }

    public function wait(): int
    {
        pcntl_waitpid($this->pid, $status);
        if (pcntl_wifexited($status)) {
            return pcntl_wexitstatus($status);
        }

        return 255;
    }

    public static function freePort(): int
    {
        $socket = @stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
        if ($socket === false) {
            throw new RuntimeException('Não foi possível reservar porta local: '.$error);
        }
        $name = stream_socket_get_name($socket, false);
        fclose($socket);
        if (! is_string($name) || ! str_contains($name, ':')) {
            throw new RuntimeException('Porta local inválida.');
        }

        return (int) substr($name, strrpos($name, ':') + 1);
    }

    /** @param resource $signal */
    private static function serve(
        $signal,
        int $port,
        string $serverCertificate,
        string $serverKey,
        string $caCertificate,
        string $getBody,
        string $postBody,
        string $expectedAccessKey,
    ): int {
        $context = stream_context_create([
            'ssl' => [
                'local_cert' => $serverCertificate,
                'local_pk' => $serverKey,
                'cafile' => $caCertificate,
                'verify_peer' => true,
                'verify_peer_name' => false,
                'allow_self_signed' => false,
                'capture_peer_cert' => true,
                'disable_compression' => true,
            ],
        ]);

        $server = @stream_socket_server(
            'tls://127.0.0.1:'.$port,
            $errno,
            $error,
            STREAM_SERVER_BIND | STREAM_SERVER_LISTEN,
            $context,
        );
        if ($server === false) {
            fwrite($signal, 'ERROR '.$errno.' '.$error."\n");

            return 10;
        }

        fwrite($signal, "READY\n");
        $ok = true;
        for ($exchange = 0; $exchange < 2; $exchange++) {
            $connection = @stream_socket_accept($server, 8);
            if ($connection === false) {
                $ok = false;
                break;
            }
            stream_set_timeout($connection, 5);
            $request = self::readRequest($connection);
            [$status, $headers, $response] = self::route(
                $request,
                $getBody,
                $postBody,
                $expectedAccessKey,
            );
            if ($status !== 200) {
                $ok = false;
            }
            self::writeResponse($connection, $status, $headers, $response);
            fclose($connection);
        }
        fclose($server);

        return $ok ? 0 : 20;
    }

    /** @param resource $connection */
    private static function readRequest($connection): array
    {
        $raw = '';
        while (! str_contains($raw, "\r\n\r\n") && ! feof($connection)) {
            $chunk = fread($connection, 8192);
            if ($chunk === false || $chunk === '') {
                break;
            }
            $raw .= $chunk;
            if (strlen($raw) > 65536) {
                break;
            }
        }

        [$head, $body] = array_pad(explode("\r\n\r\n", $raw, 2), 2, '');
        $lines = explode("\r\n", $head);
        $requestLine = array_shift($lines) ?? '';
        preg_match('#^(GET|POST)\s+([^\s]+)\s+HTTP/#', $requestLine, $matches);
        $headers = [];
        foreach ($lines as $line) {
            $parts = explode(':', $line, 2);
            if (count($parts) === 2) {
                $headers[strtolower(trim($parts[0]))] = trim($parts[1]);
            }
        }

        $length = (int) ($headers['content-length'] ?? 0);
        while (strlen($body) < $length && ! feof($connection)) {
            $chunk = fread($connection, $length - strlen($body));
            if ($chunk === false || $chunk === '') {
                break;
            }
            $body .= $chunk;
        }

        return [
            'method' => $matches[1] ?? '',
            'path' => $matches[2] ?? '',
            'headers' => $headers,
            'body' => $body,
        ];
    }

    /** @return array{int, array<string, string>, string} */
    private static function route(
        array $request,
        string $getBody,
        string $postBody,
        string $expectedAccessKey,
    ): array {
        if ($request['method'] === 'GET' && $request['path'] === '/NFESSL/DownloadXMLDFe') {
            return [200, ['Set-Cookie' => 'svrs_session=test-only; Path=/; Secure; HttpOnly'], $getBody];
        }

        if ($request['method'] === 'POST' && $request['path'] === '/NfeSSL/DownloadXmlDfe') {
            parse_str((string) $request['body'], $fields);
            $cookie = (string) ($request['headers']['cookie'] ?? '');
            $valid = str_contains($cookie, 'svrs_session=test-only')
                && ($fields['sistema'] ?? null) === 'Nfe'
                && ($fields['OrigemSite'] ?? null) === '0'
                && ($fields['Ambiente'] ?? null) === '1'
                && ($fields['ChaveAcessoDfe'] ?? null) === $expectedAccessKey;

            return $valid
                ? [200, [], $postBody]
                : [422, [], '<html><body>Contrato POST inválido</body></html>'];
        }

        return [404, [], '<html><body>Não encontrado</body></html>'];
    }

    /** @param resource $connection @param array<string, string> $headers */
    private static function writeResponse($connection, int $status, array $headers, string $body): void
    {
        $reasons = [200 => 'OK', 404 => 'Not Found', 422 => 'Unprocessable Entity'];
        $response = 'HTTP/1.1 '.$status.' '.($reasons[$status] ?? 'Error')."\r\n";
        $headers += [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Content-Length' => (string) strlen($body),
            'Connection' => 'close',
        ];
        foreach ($headers as $name => $value) {
            $response .= $name.': '.$value."\r\n";
        }
        fwrite($connection, $response."\r\n".$body);
    }
}

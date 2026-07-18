<?php

namespace Tests\Feature\Serpro;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class SerproOfficialSourcesVerifyCommandTest extends TestCase
{
    /** @var list<string> */
    private array $temporaryManifests = [];

    protected function tearDown(): void
    {
        foreach ($this->temporaryManifests as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }

        parent::tearDown();
    }

    public function test_command_requests_exactly_the_eight_allowlisted_documents_and_sanitizes_output(): void
    {
        [$manifest, $bodies] = $this->useFakeManifest();
        $this->fakeDocuments($manifest, $bodies);

        [$exitCode, $output] = $this->runCommand();

        $this->assertSame(0, $exitCode);
        $this->assertSame('PASS', $output['status']);
        $this->assertCount(8, $output['results']);
        Http::assertSentCount(8);

        $expectedUrls = collect($manifest['sources'])
            ->where('verification_kind', 'HTTP_CONTENT')
            ->where('canonical', true)
            ->pluck('url')
            ->sort()
            ->values()
            ->all();
        $requestedUrls = Http::recorded()
            ->map(fn (array $record): string => $record[0]->url())
            ->sort()
            ->values()
            ->all();

        $this->assertSame($expectedUrls, $requestedUrls);
        Http::assertSent(fn (Request $request): bool => $request->method() === 'GET'
            && parse_url($request->url(), PHP_URL_HOST) === 'apicenter.estaleiro.serpro.gov.br'
            && str_starts_with(
                (string) parse_url($request->url(), PHP_URL_PATH),
                '/documentacao/api-integra-contador/',
            ));

        foreach ($output['results'] as $result) {
            $this->assertSame([
                'source_key',
                'result',
                'http_status',
                'hash_result',
                'expected_sha256',
                'observed_sha256',
            ], array_keys($result));
            $this->assertSame('PASS', $result['result']);
        }

        $json = json_encode($output, JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('https://', $json);
        $this->assertStringNotContainsString('documento-fake:', $json);
        $this->assertStringNotContainsString('Authorization', $json);

        foreach ($manifest['sources'] as $source) {
            if (($source['verification_kind'] ?? null) !== 'HTTP_CONTENT') {
                $this->assertNotContains($source['url'] ?? null, $requestedUrls);
            }
        }
    }

    public function test_disallowed_host_blocks_all_network_before_request(): void
    {
        foreach ([
            'https://www.gov.br/documentacao',
            'https://gateway.apiserpro.serpro.gov.br/documentacao',
            'https://autenticacao.sapi.serpro.gov.br/authenticate',
            'https://127.0.0.1/documentacao/api-integra-contador/',
            'https://apicenter.estaleiro.serpro.gov.br.example.test/documentacao/api-integra-contador/',
        ] as $disallowedUrl) {
            [$manifest, $bodies] = $this->useFakeManifest(function (array &$manifest) use ($disallowedUrl): void {
                $manifest['sources'][0]['url'] = $disallowedUrl;
            });
            $this->fakeDocuments($manifest, $bodies);

            [$exitCode, $output] = $this->runCommand();

            $this->assertSame(1, $exitCode, $disallowedUrl);
            $this->assertSame('REVIEW_REQUIRED', $output['status'], $disallowedUrl);
            $this->assertSame('MANIFEST_INVALID', $output['results'][0]['result'], $disallowedUrl);
            Http::assertSentCount(0);
        }
    }

    public function test_redirect_is_rejected_without_following_location(): void
    {
        [$manifest, $bodies] = $this->useFakeManifest();
        $redirectedUrl = $this->canonicalSources($manifest)[0]['url'];
        $this->fakeDocuments($manifest, $bodies, [
            $redirectedUrl => Http::response('', 302, ['Location' => 'https://www.gov.br/redirect']),
        ]);

        [$exitCode, $output] = $this->runCommand();

        $this->assertSame(1, $exitCode);
        $this->assertSame('REVIEW_REQUIRED', $output['status']);
        $this->assertContains('REDIRECT_REJECTED', array_column($output['results'], 'result'));
        Http::assertSentCount(8);
        Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), 'www.gov.br'));
    }

    public function test_hash_mismatch_requires_review_without_updating_expected_hash(): void
    {
        [$manifest, $bodies] = $this->useFakeManifest();
        $source = $this->canonicalSources($manifest)[0];
        $bodies[$source['source_key']] = 'conteudo-divergente';
        $this->fakeDocuments($manifest, $bodies);

        [$exitCode, $output] = $this->runCommand();
        $failure = collect($output['results'])->firstWhere('source_key', $source['source_key']);

        $this->assertSame(1, $exitCode);
        $this->assertSame('HASH_MISMATCH', $failure['result']);
        $this->assertSame('MISMATCH', $failure['hash_result']);
        $this->assertSame($source['content_sha256'], $failure['expected_sha256']);
        $this->assertNotSame($failure['expected_sha256'], $failure['observed_sha256']);
    }

    public function test_timeout_or_connection_failure_requires_review_without_leaking_exception(): void
    {
        [$manifest, $bodies] = $this->useFakeManifest();
        $failedUrl = $this->canonicalSources($manifest)[0]['url'];
        $this->fakeDocuments($manifest, $bodies, [
            $failedUrl => Http::failedConnection('detalhe-interno-timeout'),
        ]);

        [$exitCode, $output, $rawOutput] = $this->runCommand();

        $this->assertSame(1, $exitCode);
        $this->assertContains('TIMEOUT_OR_CONNECTION_ERROR', array_column($output['results'], 'result'));
        $this->assertStringNotContainsString('detalhe-interno-timeout', $rawOutput);
    }

    public function test_oversize_body_requires_review(): void
    {
        config(['serpro.official_source_verification.max_response_bytes' => 16]);
        [$manifest, $bodies] = $this->useFakeManifest();
        $this->fakeDocuments($manifest, $bodies);

        [$exitCode, $output] = $this->runCommand();

        $this->assertSame(1, $exitCode);
        $this->assertContains('OVERSIZE', array_column($output['results'], 'result'));
        Http::assertSentCount(8);
    }

    public function test_unexpected_http_status_requires_review(): void
    {
        [$manifest, $bodies] = $this->useFakeManifest();
        $failedUrl = $this->canonicalSources($manifest)[0]['url'];
        $this->fakeDocuments($manifest, $bodies, [
            $failedUrl => Http::response('erro-interno', 503),
        ]);

        [$exitCode, $output, $rawOutput] = $this->runCommand();

        $this->assertSame(1, $exitCode);
        $this->assertContains('HTTP_STATUS_UNEXPECTED', array_column($output['results'], 'result'));
        $this->assertStringNotContainsString('erro-interno', $rawOutput);
    }

    /**
     * @param  (callable(array<string, mixed>&): void)|null  $mutate
     * @return array{array<string, mixed>, array<string, string>}
     */
    private function useFakeManifest(?callable $mutate = null): array
    {
        /** @var array<string, mixed> $manifest */
        $manifest = json_decode(
            (string) file_get_contents(resource_path('serpro/official-sources.v2026-07-18.json')),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
        $bodies = [];

        foreach ($manifest['sources'] as &$source) {
            if (($source['verification_kind'] ?? null) !== 'HTTP_CONTENT'
                || ($source['canonical'] ?? null) !== true
            ) {
                continue;
            }

            $body = 'documento-fake:'.$source['source_key'];
            $bodies[$source['source_key']] = $body;
            $source['content_sha256'] = hash('sha256', $body);
        }
        unset($source);

        if ($mutate !== null) {
            $mutate($manifest);
        }

        $manifestPath = $this->writeTemporaryJson('serpro-official-sources-', $manifest);

        /** @var array<string, mixed> $catalog */
        $catalog = json_decode(
            (string) file_get_contents(resource_path('serpro/official-service-catalog.v2026-07-16.json')),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
        $sourcesByUrl = collect($this->canonicalSources($manifest))->keyBy('url');
        foreach ($catalog['source_snapshots'] as &$snapshot) {
            $snapshot['sha256'] = $sourcesByUrl[$snapshot['url']]['content_sha256'];
        }
        unset($snapshot);
        $catalogPath = $this->writeTemporaryJson('serpro-official-catalog-', $catalog);

        /** @var array<string, mixed> $matrix */
        $matrix = json_decode(
            (string) file_get_contents(resource_path('serpro/power-matrix.v2026-07-18.json')),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
        $proxySource = collect($this->canonicalSources($manifest))->firstWhere(
            'source_key',
            'services_vs_proxies',
        );
        $matrix['source_content_sha256'] = $proxySource['content_sha256'];
        $matrix['derived_from_catalog'] = basename($catalogPath);
        $matrixPath = $this->writeTemporaryJson('serpro-power-matrix-', $matrix);

        config([
            'serpro.official_sources_manifest' => $manifestPath,
            'serpro.official_service_catalog_manifest' => $catalogPath,
            'serpro.power_matrix_manifest' => $matrixPath,
        ]);

        return [$manifest, $bodies];
    }

    /**
     * @param  array<string, mixed>  $manifest
     * @param  array<string, string>  $bodies
     * @param  array<string, mixed>  $overrides
     */
    private function fakeDocuments(array $manifest, array $bodies, array $overrides = []): void
    {
        $responses = [];
        foreach ($this->canonicalSources($manifest) as $source) {
            $responses[$source['url']] = Http::response($bodies[$source['source_key']] ?? '', 200);
        }

        Http::fake(array_replace($responses, $overrides));
        Http::preventStrayRequests();
    }

    /**
     * @param  array<string, mixed>  $manifest
     * @return list<array<string, mixed>>
     */
    private function canonicalSources(array $manifest): array
    {
        return array_values(array_filter(
            $manifest['sources'],
            static fn (array $source): bool => ($source['verification_kind'] ?? null) === 'HTTP_CONTENT'
                && ($source['canonical'] ?? null) === true,
        ));
    }

    /**
     * @param  array<string, mixed>  $contents
     */
    private function writeTemporaryJson(string $prefix, array $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), $prefix);
        $this->assertNotFalse($path);
        file_put_contents($path, json_encode($contents, JSON_THROW_ON_ERROR));
        $this->temporaryManifests[] = $path;

        return $path;
    }

    /**
     * @return array{int, array<string, mixed>, string}
     */
    private function runCommand(): array
    {
        $exitCode = Artisan::call('serpro:official-sources-verify');
        $rawOutput = Artisan::output();

        /** @var array<string, mixed> $output */
        $output = json_decode($rawOutput, true, 512, JSON_THROW_ON_ERROR);

        return [$exitCode, $output, $rawOutput];
    }
}

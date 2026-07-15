<?php

namespace App\Services\Outbound;

use App\Contracts\SefazOutboundProtocolQueryClient;
use App\DTO\Outbound\ProtocolQueryResult;

/**
 * Cliente fake para CI — mapeia chaves de teste a fixtures sem rede.
 */
final class FakeSefazOutboundProtocolQueryClient implements SefazOutboundProtocolQueryClient
{
    /** @var array<string, string> access_key => fixture basename */
    private array $map = [];

    public function __construct(
        private readonly ProtocolQueryResponseParser $parser,
    ) {}

    public function mapKeyToFixture(string $accessKey, string $fixtureBasename): void
    {
        $this->map[strtoupper($accessKey)] = $fixtureBasename;
    }

    public function consult(
        string $accessKey,
        string $model,
        string $environment,
        array $certificate,
    ): ProtocolQueryResult {
        $key = strtoupper($accessKey);
        $base = $this->map[$key] ?? 'consulta_217.xml';
        $path = base_path('tests/fixtures/ma-outbound/'.$base);
        if (! is_file($path)) {
            return new ProtocolQueryResult(
                cStat: '217',
                xMotivo: 'Fixture ausente no fake client.',
                consultedAccessKey: $key,
            );
        }

        return $this->parser->parse((string) file_get_contents($path), $key);
    }
}

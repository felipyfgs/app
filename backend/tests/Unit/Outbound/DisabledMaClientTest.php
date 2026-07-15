<?php

namespace Tests\Unit\Outbound;

use App\Services\Outbound\DisabledMaOutboundXmlRetrievalClient;
use App\Services\Outbound\DisabledSefazOutboundMutatingProbeClient;
use PHPUnit\Framework\TestCase;

class DisabledMaClientTest extends TestCase
{
    public function test_m2m_disabled_por_default(): void
    {
        $client = new DisabledMaOutboundXmlRetrievalClient;
        $this->assertFalse($client->isAvailable());
        $result = $client->requestExport('2026-07', '55', 'homologation', ['pfx' => 'x', 'password' => 'y']);
        $this->assertFalse($result->accepted);
        $this->assertSame('NO_GO_M2M', $result->status);
    }

    public function test_probe_disabled(): void
    {
        $client = new DisabledSefazOutboundMutatingProbeClient;
        $this->assertFalse($client->isActive());
    }
}

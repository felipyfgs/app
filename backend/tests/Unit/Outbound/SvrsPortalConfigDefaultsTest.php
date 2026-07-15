<?php

namespace Tests\Unit\Outbound;

use App\Services\Outbound\SvrsNfceConfig;
use App\Services\Outbound\SvrsNfe55Config;
use App\Services\Outbound\SvrsPortalEgressConfig;
use Tests\TestCase;

class SvrsPortalConfigDefaultsTest extends TestCase
{
    public function test_defaults_defensivos_nao_sao_5s_30s_20(): void
    {
        // Limpa overrides de env do phpunit se houver
        $egress = new SvrsPortalEgressConfig;
        $this->assertGreaterThanOrEqual(120, $egress->minIntervalGlobalSeconds());
        $this->assertGreaterThanOrEqual(900, $egress->minIntervalRootSeconds());
        $this->assertSame(1, $egress->maxKeysPerJob());
        $this->assertSame(10, $egress->maxExchangesPerHour());
        $this->assertSame(50, $egress->maxExchangesPerDay());
        $this->assertSame(6, $egress->maxKeysPerRootPerDay());

        $nfce = new SvrsNfceConfig;
        $this->assertGreaterThanOrEqual(120, $nfce->minIntervalGlobalSeconds());
        $this->assertGreaterThanOrEqual(900, $nfce->minIntervalRootSeconds());
        $this->assertSame(1, $nfce->maxKeysPerRun());

        $this->assertFalse($nfce->retrievalEnabled());
        $this->assertFalse($nfce->autoQueueEnabled());

        $nfe55 = new SvrsNfe55Config;
        $this->assertFalse($nfe55->retrievalEnabled());
        $this->assertFalse($nfe55->autoQueueEnabled());
        $this->assertStringContainsString('NFESSL', $nfe55->getPath());
        $this->assertSame('Nfe', $nfe55->postStaticFields()['sistema']);
    }

    public function test_cohort_id_obrigatorio_quando_canal_ligado(): void
    {
        config([
            'sefaz.svrs_nfce_xml.retrieval_enabled' => true,
            'sefaz.svrs_portal_egress.cohort_id' => 'pilot-a',
        ]);
        $cfg = new SvrsPortalEgressConfig;
        $this->assertTrue($cfg->anyPortalChannelEnabled());
        $this->assertSame('pilot-a', $cfg->cohortId());
    }
}

<?php

namespace Tests\Unit\Enums;

use App\Enums\FiscalCoverage;
use App\Enums\FiscalDataOrigin;
use App\Enums\FiscalModuleKey;
use App\Enums\FiscalSituation;
use PHPUnit\Framework\TestCase;

/**
 * Task 1.6 — identificadores tipados de módulo, situação, cobertura e origem.
 */
class FiscalModuleAndOriginEnumsTest extends TestCase
{
    public function test_module_keys_batem_com_contrato_da_change(): void
    {
        $expected = [
            'dashboard',
            'simples_mei',
            'dctfweb',
            'installments',
            'sitfis',
            'mailbox',
            'declarations',
            'guides',
            'fgts',
        ];

        $this->assertSame($expected, FiscalModuleKey::values());
        $this->assertSame('/monitoring/dctfweb', FiscalModuleKey::Dctfweb->monitoringPath());
        $this->assertSame('dctfweb_mit', FiscalModuleKey::Dctfweb->featureFlagKey());
        $this->assertNull(FiscalModuleKey::Dashboard->featureFlagKey());
        $this->assertSame(FiscalModuleKey::Mailbox, FiscalModuleKey::tryFromPath('/monitoring/mailbox'));
    }

    public function test_data_origin_values_and_synthetic(): void
    {
        $this->assertSame(['DEMO', 'SIMULATED', 'LIVE'], FiscalDataOrigin::values());
        $this->assertTrue(FiscalDataOrigin::Demo->isSynthetic());
        $this->assertTrue(FiscalDataOrigin::Simulated->isSynthetic());
        $this->assertFalse(FiscalDataOrigin::Live->isSynthetic());
    }

    public function test_situation_and_coverage_vocabulary_preservado(): void
    {
        $situations = array_map(static fn (FiscalSituation $s) => $s->value, FiscalSituation::cases());
        $this->assertContains('UP_TO_DATE', $situations);
        $this->assertContains('UNSUPPORTED', $situations);
        $this->assertContains('BLOCKED', $situations);
        $this->assertCount(9, $situations);

        $coverage = array_map(static fn (FiscalCoverage $c) => $c->value, FiscalCoverage::cases());
        $this->assertContains('FULL', $coverage);
        $this->assertContains('PARTIAL', $coverage);
        $this->assertContains('UNSUPPORTED', $coverage);
    }
}

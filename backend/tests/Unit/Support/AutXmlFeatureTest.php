<?php

namespace Tests\Unit\Support;

use App\Support\AutXmlFeature;
use Tests\TestCase;

class AutXmlFeatureTest extends TestCase
{
    public function test_disabled_by_default(): void
    {
        config([
            'sefaz.autxml.enabled' => false,
            'sefaz.autxml.kill_switch' => false,
            'sefaz.autxml.office_allowlist' => [],
            'sefaz.autxml.allow_all_offices' => false,
        ]);

        $this->assertFalse(AutXmlFeature::isGloballyEnabled());
        $this->assertFalse(AutXmlFeature::isOfficeAllowed(42));
    }

    public function test_kill_switch_overrides_enabled(): void
    {
        config([
            'sefaz.autxml.enabled' => true,
            'sefaz.autxml.kill_switch' => true,
            'sefaz.autxml.office_allowlist' => [1],
            'sefaz.autxml.allow_all_offices' => true,
        ]);

        $this->assertFalse(AutXmlFeature::isGloballyEnabled());
        $this->assertFalse(AutXmlFeature::isOfficeAllowed(1));
    }

    public function test_allowlist_filtra_office(): void
    {
        config([
            'sefaz.autxml.enabled' => true,
            'sefaz.autxml.kill_switch' => false,
            'sefaz.autxml.office_allowlist' => [10, 20],
            'sefaz.autxml.allow_all_offices' => false,
        ]);

        $this->assertTrue(AutXmlFeature::isGloballyEnabled());
        $this->assertTrue(AutXmlFeature::isOfficeAllowed(10));
        $this->assertFalse(AutXmlFeature::isOfficeAllowed(99));
    }

    public function test_allow_all_somente_com_allowlist_vazia(): void
    {
        config([
            'sefaz.autxml.enabled' => true,
            'sefaz.autxml.kill_switch' => false,
            'sefaz.autxml.office_allowlist' => [],
            'sefaz.autxml.allow_all_offices' => true,
        ]);

        $this->assertTrue(AutXmlFeature::isOfficeAllowed(7));
    }
}

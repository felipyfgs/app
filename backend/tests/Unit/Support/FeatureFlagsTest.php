<?php

namespace Tests\Unit\Support;

use App\Support\FeatureFlags;
use InvalidArgumentException;
use Tests\TestCase;

class FeatureFlagsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'features.kill_switch' => false,
            'features.global_enabled' => false,
            'features.mutating.enabled' => false,
            'features.mutating.kill_switch' => false,
            'features.modules' => $this->allModulesOff(),
        ]);
    }

    public function test_tudo_desabilitado_por_padrao(): void
    {
        foreach (FeatureFlags::knownModules() as $module) {
            $this->assertFalse(FeatureFlags::isModuleEnabled($module));
            $this->assertFalse(FeatureFlags::isModuleEnabled($module, 1));
            $this->assertFalse(FeatureFlags::isMutatingEnabled($module));
            $this->assertFalse(FeatureFlags::isMutatingEnabled($module, 1));
        }

        $this->assertFalse(FeatureFlags::isGloballyEnabled());
        $this->assertFalse(FeatureFlags::isKillSwitchActive());
    }

    public function test_kill_switch_global_vence_enable(): void
    {
        config([
            'features.kill_switch' => true,
            'features.global_enabled' => true,
            'features.modules.simples_mei.enabled' => true,
            'features.modules.simples_mei.allow_all_offices' => true,
            'features.mutating.enabled' => true,
            'features.modules.simples_mei.mutating_enabled' => true,
        ]);

        $this->assertFalse(FeatureFlags::isGloballyEnabled());
        $this->assertFalse(FeatureFlags::isModuleEnabled('simples_mei', 1));
        $this->assertFalse(FeatureFlags::isMutatingEnabled('simples_mei', 1));
    }

    public function test_modulo_requer_global_e_allowlist(): void
    {
        config([
            'features.global_enabled' => true,
            'features.modules.sitfis.enabled' => true,
            'features.modules.sitfis.office_allowlist' => [10],
            'features.modules.sitfis.allow_all_offices' => false,
        ]);

        $this->assertTrue(FeatureFlags::isModuleEnabled('sitfis'));
        $this->assertTrue(FeatureFlags::isModuleEnabled('sitfis', 10));
        $this->assertFalse(FeatureFlags::isModuleEnabled('sitfis', 99));
    }

    public function test_allow_all_somente_com_allowlist_vazia(): void
    {
        config([
            'features.global_enabled' => true,
            'features.modules.mailbox.enabled' => true,
            'features.modules.mailbox.office_allowlist' => [],
            'features.modules.mailbox.allow_all_offices' => true,
        ]);

        $this->assertTrue(FeatureFlags::isModuleEnabled('mailbox', 7));
    }

    public function test_mutante_requer_gates_transversais_e_do_modulo(): void
    {
        config([
            'features.global_enabled' => true,
            'features.mutating.enabled' => true,
            'features.modules.mutacoes.enabled' => true,
            'features.modules.mutacoes.mutating_enabled' => true,
            'features.modules.mutacoes.allow_all_offices' => true,
        ]);

        $this->assertTrue(FeatureFlags::isMutatingEnabled('mutacoes', 1));

        config(['features.mutating.enabled' => false]);
        $this->assertFalse(FeatureFlags::isMutatingEnabled('mutacoes', 1));

        config([
            'features.mutating.enabled' => true,
            'features.modules.mutacoes.mutating_enabled' => false,
        ]);
        $this->assertFalse(FeatureFlags::isMutatingEnabled('mutacoes', 1));
    }

    public function test_mutating_kill_switch_vence(): void
    {
        config([
            'features.global_enabled' => true,
            'features.mutating.enabled' => true,
            'features.mutating.kill_switch' => true,
            'features.modules.guias.enabled' => true,
            'features.modules.guias.mutating_enabled' => true,
            'features.modules.guias.allow_all_offices' => true,
        ]);

        $this->assertTrue(FeatureFlags::isModuleEnabled('guias', 1));
        $this->assertFalse(FeatureFlags::isMutatingEnabled('guias', 1));
    }

    public function test_modulo_desconhecido_lanca(): void
    {
        $this->expectException(InvalidArgumentException::class);
        FeatureFlags::isModuleEnabled('nao_existe');
    }

    public function test_snapshot_sanitizado(): void
    {
        $snap = FeatureFlags::snapshot();
        $this->assertArrayHasKey('kill_switch', $snap);
        $this->assertArrayHasKey('modules', $snap);
        $this->assertCount(count(FeatureFlags::MODULES), $snap['modules']);
        $this->assertArrayNotHasKey('secrets', $snap);
    }

    /**
     * @return array<string, array{enabled: bool, mutating_enabled: bool, office_allowlist: list<int>, allow_all_offices: bool}>
     */
    private function allModulesOff(): array
    {
        $out = [];
        foreach (FeatureFlags::MODULES as $module) {
            $out[$module] = [
                'enabled' => false,
                'mutating_enabled' => false,
                'office_allowlist' => [],
                'allow_all_offices' => false,
            ];
        }

        return $out;
    }
}

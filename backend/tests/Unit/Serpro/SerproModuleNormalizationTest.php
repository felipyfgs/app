<?php

namespace Tests\Unit\Serpro;

use App\Services\Serpro\SerproOperationService;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Garante que aliases do catálogo (dctfweb, installments…) mapeiam para FeatureFlags.
 */
class SerproModuleNormalizationTest extends TestCase
{
    public function test_aliases_do_catalogo_normalizam_para_modulos_conhecidos(): void
    {
        $svc = $this->app->make(SerproOperationService::class);
        $m = new ReflectionMethod(SerproOperationService::class, 'normalizeFeatureModule');
        $m->setAccessible(true);

        $this->assertSame('dctfweb_mit', $m->invoke($svc, 'dctfweb'));
        $this->assertSame('dctfweb_mit', $m->invoke($svc, 'dctfweb_mit'));
        $this->assertSame('parcelamentos', $m->invoke($svc, 'installments'));
        $this->assertSame('guias', $m->invoke($svc, 'guides'));
        $this->assertSame('sitfis', $m->invoke($svc, 'sitfis'));
        $this->assertSame('mailbox', $m->invoke($svc, 'mailbox'));
        $this->assertNull($m->invoke($svc, 'tax_processes'));
        $this->assertNull($m->invoke($svc, 'registrations'));
        $this->assertNull($m->invoke($svc, 'authorization'));
        $this->assertNull($m->invoke($svc, 'inventory'));
    }

    public function test_module_for_operation_usa_meta_normalizada(): void
    {
        $svc = $this->app->make(SerproOperationService::class);
        $m = new ReflectionMethod(SerproOperationService::class, 'moduleForOperation');
        $m->setAccessible(true);

        $this->assertSame(
            'dctfweb_mit',
            $m->invoke($svc, 'dctfweb.consrecibo', ['monitoring_module' => 'dctfweb']),
        );
        $this->assertSame(
            'parcelamentos',
            $m->invoke($svc, 'parcsn.pedidosparc', ['monitoring_module' => 'installments']),
        );
        $this->assertNull(
            $m->invoke($svc, 'eprocesso.consultar_por_interessado', ['monitoring_module' => 'tax_processes']),
        );
    }
}

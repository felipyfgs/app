<?php

namespace Tests\Unit\Integra;

use App\Services\Integra\SerproTechnicalParameterGuard;
use RuntimeException;
use Tests\TestCase;

class SerproTechnicalParameterGuardTest extends TestCase
{
    public function test_rejects_tenant_technical_params(): void
    {
        $guard = new SerproTechnicalParameterGuard;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('jwt_token');

        $guard->assertClean([
            'periodo' => '2026-01',
            'jwt_token' => 'should-not-pass',
        ]);
    }

    public function test_allows_business_data(): void
    {
        $guard = new SerproTechnicalParameterGuard;
        $guard->assertClean([
            'periodo' => '2026-01',
            'protocolo' => 'ABC123',
            'filtros' => ['tipo' => 'DAS'],
        ]);

        $this->assertTrue(true);
    }

    public function test_strip_removes_forbidden_nested(): void
    {
        $guard = new SerproTechnicalParameterGuard;
        $clean = $guard->strip([
            'ok' => 1,
            'oauth' => ['access_token' => 'x'],
            'nested' => ['termo_xml' => '<x/>', 'keep' => true],
        ]);

        $this->assertSame(['ok' => 1, 'nested' => ['keep' => true]], $clean);
    }
}

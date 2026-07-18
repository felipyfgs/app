<?php

namespace Tests\Unit\Serpro;

use App\DTO\Serpro\ProcuracaoLookupRequest;
use App\Services\Integra\HttpIntegraProcuracoesClient;
use ReflectionMethod;
use Tests\TestCase;

class HttpIntegraProcuracoesClientTest extends TestCase
{
    public function test_monta_payload_oficial_com_outorgante_e_outorgado_tipados(): void
    {
        $request = new ProcuracaoLookupRequest(
            officeId: 10,
            clientId: 20,
            environment: 'TRIAL',
            authorIdentity: '48123272000105',
            contributorCnpj: '30288513000100',
            powerCode: '00146',
        );

        $data = $this->invokePrivate('buildBusinessData', $request);

        $this->assertSame([
            'outorgante' => '30288513000100',
            'tipoOutorgante' => '2',
            'outorgado' => '48123272000105',
            'tipoOutorgado' => '2',
        ], $data);
        $this->assertArrayNotHasKey('codigoPoder', $data);
    }

    public function test_mapeia_somente_nome_oficial_pgdas_e_ignora_sistema_desconhecido(): void
    {
        $powers = $this->invokePrivate('mapPowers', [[
            'dtexpiracao' => '20991231',
            'nrsistemas' => 2,
            'sistemas' => [
                '  PGDAS-D   - a partir de 01/2018 ',
                'Sistema ainda não mapeado',
            ],
        ]]);

        $this->assertCount(1, $powers);
        $this->assertSame('00146', $powers[0]['power_code']);
        $this->assertSame('PGDASD', $powers[0]['system_code']);
        $this->assertSame('ACTIVE', $powers[0]['status']);
    }

    public function test_mapeia_marcador_todos_para_poderes_das_familias_do_hub(): void
    {
        $powers = $this->invokePrivate('mapPowers', [[
            'dtexpiracao' => '20991231',
            'nrsistemas' => 1,
            'sistemas' => ['TODOS'],
        ]]);

        $codes = array_column($powers, 'power_code');
        // Outorga "TODOS" expande via power-matrix PRODUCTION (não colapsa em 00146).
        $this->assertContains('00146', $codes);
        $this->assertContains('00002', $codes); // SITFIS
        $this->assertContains('00103', $codes); // DCTFWEB/MIT
        $this->assertContains('00006', $codes); // Caixa Postal
        $this->assertContains('00125', $codes); // PARCSN-ESP (matriz completa)
        $this->assertTrue(count($codes) > 5);
        foreach ($powers as $power) {
            $this->assertSame('ACTIVE', $power['status']);
            $this->assertSame('TODOS', $power['raw']['system'] ?? null);
        }
    }

    public function test_resposta_ambigua_nao_libera_poder(): void
    {
        $unknown = $this->invokePrivate('mapPowers', [[
            'dtexpiracao' => '20991231',
            'nrsistemas' => 1,
            'sistemas' => ['Sistema não aprovado'],
        ]]);
        $countMismatch = $this->invokePrivate('mapPowers', [[
            'dtexpiracao' => '20991231',
            'nrsistemas' => 2,
            'sistemas' => ['PGDAS-D - a partir de 01/2018'],
        ]]);
        $invalidDate = $this->invokePrivate('mapPowers', [[
            'dtexpiracao' => '20991340',
            'nrsistemas' => 1,
            'sistemas' => ['PGDAS-D - a partir de 01/2018'],
        ]]);

        $this->assertSame([], $unknown);
        $this->assertSame([], $countMismatch);
        $this->assertSame([], $invalidDate);
    }

    private function invokePrivate(string $method, mixed ...$arguments): mixed
    {
        $reflection = new ReflectionMethod(HttpIntegraProcuracoesClient::class, $method);

        return $reflection->invoke(new HttpIntegraProcuracoesClient, ...$arguments);
    }
}

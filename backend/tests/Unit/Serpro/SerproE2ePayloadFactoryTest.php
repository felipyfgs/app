<?php

namespace Tests\Unit\Serpro;

use App\Models\Client;
use App\Models\Establishment;
use App\Models\Office;
use App\Services\Serpro\E2e\SerproE2ePayloadFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SerproE2ePayloadFactoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_sitfis_solicit_usa_dados_empty(): void
    {
        $office = Office::factory()->create();
        $client = Client::factory()->forOffice($office)->create();
        Establishment::factory()->create([
            'office_id' => $office->id,
            'client_id' => $client->id,
            'cnpj' => '30288513000100',
            'is_matrix' => true,
            'is_active' => true,
        ]);

        $built = app(SerproE2ePayloadFactory::class)->forOperation(
            'sitfis.solicitar_protocolo',
            $client,
        );

        $this->assertSame([], $built['business_data']);
        $this->assertSame('', $built['payload']['dados'] ?? null);
    }

    public function test_emit_relatorio_exige_protocolo_relatorio(): void
    {
        $office = Office::factory()->create();
        $client = Client::factory()->forOffice($office)->create();
        Establishment::factory()->create([
            'office_id' => $office->id,
            'client_id' => $client->id,
            'cnpj' => '30288513000100',
            'is_matrix' => true,
            'is_active' => true,
        ]);

        $built = app(SerproE2ePayloadFactory::class)->forOperation(
            'sitfis.emitir_relatorio',
            $client,
            ['protocol' => 'PROT-ABC'],
        );

        $this->assertSame('PROT-ABC', $built['business_data']['protocoloRelatorio'] ?? null);
        $decoded = json_decode((string) $built['payload']['dados'], true);
        $this->assertIsArray($decoded);
        $this->assertSame('PROT-ABC', $decoded['protocoloRelatorio'] ?? null);
    }

    public function test_pgdasd_consdeclaracao_preenche_ano(): void
    {
        $office = Office::factory()->create();
        $client = Client::factory()->forOffice($office)->create();
        Establishment::factory()->create([
            'office_id' => $office->id,
            'client_id' => $client->id,
            'cnpj' => '30288513000100',
            'is_matrix' => true,
            'is_active' => true,
        ]);

        $built = app(SerproE2ePayloadFactory::class)->forOperation(
            'pgdasd.consdeclaracao',
            $client,
            ['period' => '2026-05'],
        );

        $this->assertSame('2026', $built['business_data']['anoCalendario'] ?? null);
    }

    public function test_pnr_consultar_renuncias_usa_periodo_e_paginacao_documentados(): void
    {
        $office = Office::factory()->create();
        $client = Client::factory()->forOffice($office)->create();
        Establishment::factory()->create([
            'office_id' => $office->id,
            'client_id' => $client->id,
            'cnpj' => '30288513000100',
            'is_matrix' => true,
            'is_active' => true,
        ]);

        $built = app(SerproE2ePayloadFactory::class)->forOperation(
            'pnr_contador.consultar_renuncias',
            $client,
            ['context' => [
                'dtInicio' => '2026-01-01',
                'dtFim' => '2026-01-31',
                'page' => 0,
                'pageSize' => 25,
            ]],
        );

        $this->assertSame([
            'dtInicio' => '2026-01-01',
            'dtFim' => '2026-01-31',
            'page' => 0,
            'pageSize' => 25,
        ], $built['business_data']);
    }
}

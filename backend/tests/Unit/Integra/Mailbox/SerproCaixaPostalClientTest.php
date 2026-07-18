<?php

namespace Tests\Unit\Integra\Mailbox;

use App\Contracts\SerproOperationExecutor;
use App\DTO\Serpro\IntegraResponse;
use App\Enums\FiscalSourceProvenance;
use App\Models\Client;
use App\Models\Establishment;
use App\Models\Office;
use App\Services\Integra\ContributorCnpjResolver;
use App\Services\Integra\Mailbox\SerproCaixaPostalClient;
use App\Services\Serpro\CapabilityDriverResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

final class SerproCaixaPostalClientTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['serpro.capabilities.mailbox' => 'real']);
    }

    public function test_normaliza_lista_oficial_e_envia_campos_obrigatorios(): void
    {
        [$office, $client] = $this->tenant();
        $executor = Mockery::mock(SerproOperationExecutor::class);
        $executor->shouldReceive('execute')->once()->withArgs(function (
            Office $receivedOffice,
            Client $receivedClient,
            string $operationKey,
            array $businessData,
        ) use ($office, $client): bool {
            $this->assertSame($office->id, $receivedOffice->id);
            $this->assertSame($client->id, $receivedClient->id);
            $this->assertSame('caixa_postal.lista', $operationKey);
            $this->assertSame('0', $businessData['statusLeitura']);
            $this->assertSame('0', $businessData['indicadorPagina']);
            $this->assertArrayNotHasKey('cnpjReferencia', $businessData);

            return true;
        })->andReturn(new IntegraResponse(
            success: true,
            httpStatus: 200,
            body: [],
            dados: [
                'codigo' => 'Sucesso-CAIXAPOSTAL-00',
                'conteudo' => [[
                    'indicadorUltimaPagina' => 'S',
                    'quantidadeMensagens' => 1,
                    'listaMensagens' => [[
                        'isn' => '0000082838',
                        'codigoSistemaRemetente' => '00001',
                        'codigoModelo' => '00123',
                        'dataEnvio' => '20260718',
                        'horaEnvio' => '143025',
                        'indicadorLeitura' => '0',
                        'assuntoModelo' => 'Declaração ++VARIAVEL++ processada',
                        'valorParametroAssunto' => '2026',
                        'dataValidade' => '20260818',
                        'relevancia' => '2',
                        'descricaoOrigem' => 'Receita Federal',
                    ]],
                ]],
            ],
            operationKey: 'caixa_postal.lista',
            sourceProvenance: FiscalSourceProvenance::SerproTrial->value,
        ));

        $result = $this->client($executor)->listMessages([
            'office_id' => $office->id,
            'client_id' => $client->id,
        ]);

        $this->assertTrue($result->success);
        $this->assertSame(1, $result->officialUnreadCount);
        $this->assertSame('caixa-postal-serpro-trial-v1', $result->sourceVersion);
        $this->assertSame('S', $result->rawMeta['indicador_ultima_pagina']);
        $this->assertSame(1, $result->rawMeta['quantidade_mensagens']);
        $this->assertSame('0000082838', $result->items[0]['external_id']);
        $this->assertSame('Declaração 2026 processada', $result->items[0]['subject']);
        $this->assertSame('2026-07-18T14:30:25+00:00', $result->items[0]['received_at']);
        $this->assertSame('2026-08-18T00:00:00+00:00', $result->items[0]['due_at']);
        $this->assertFalse($result->items[0]['official_read']);
        $this->assertSame('high', $result->items[0]['severity_hint']);
    }

    public function test_envia_isn_e_renderiza_detalhe_oficial(): void
    {
        [$office, $client] = $this->tenant();
        $executor = Mockery::mock(SerproOperationExecutor::class);
        $executor->shouldReceive('execute')->once()->withArgs(function (
            Office $receivedOffice,
            Client $receivedClient,
            string $operationKey,
            array $businessData,
        ): bool {
            $this->assertSame('caixa_postal.detalhe', $operationKey);
            $this->assertSame(['isn' => '0000082838'], $businessData);

            return true;
        })->andReturn(new IntegraResponse(
            success: true,
            httpStatus: 200,
            body: [],
            dados: [
                'conteudo' => [[
                    'isn' => '0000082838',
                    'codigoSistemaRemetente' => '00001',
                    'codigoModelo' => '00123',
                    'dataEnvio' => '20260718',
                    'horaEnvio' => '143025',
                    'dataLeitura' => '20260718',
                    'assuntoModelo' => 'Aviso ++VARIAVEL++',
                    'valorParametroAssunto' => 'fiscal',
                    'corpoModelo' => 'Período ++1++ disponível em ++2++',
                    'variaveis' => ['2026', 'https://receita.example'],
                    'descricaoOrigem' => 'Receita Federal',
                ]],
            ],
            operationKey: 'caixa_postal.detalhe',
            sourceProvenance: FiscalSourceProvenance::SerproReal->value,
        ));

        $result = $this->client($executor)->getMessageDetail('0000082838', [
            'office_id' => $office->id,
            'client_id' => $client->id,
        ]);

        $this->assertTrue($result->success);
        $this->assertSame('Aviso fiscal', $result->subject);
        $this->assertSame('Período 2026 disponível em https://receita.example', $result->bodyBytes);
        $this->assertTrue($result->officialRead);
        $this->assertSame('caixa-postal-serpro-v1', $result->sourceVersion);
    }

    /** @return array{Office, Client} */
    private function tenant(): array
    {
        $office = Office::factory()->create();
        $client = Client::factory()->create([
            'office_id' => $office->id,
            'root_cnpj' => '11222333',
        ]);
        Establishment::factory()->forClient($client, '11222333000181')->create();

        return [$office, $client];
    }

    private function client(SerproOperationExecutor $executor): SerproCaixaPostalClient
    {
        return new SerproCaixaPostalClient(
            operations: $executor,
            drivers: app(CapabilityDriverResolver::class),
            contributors: app(ContributorCnpjResolver::class),
        );
    }
}

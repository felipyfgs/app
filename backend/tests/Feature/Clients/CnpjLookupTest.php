<?php

namespace Tests\Feature\Clients;

use App\Enums\OfficeRole;
use App\Enums\RegistrationStatus;
use App\Models\Office;
use App\Models\User;
use App\Services\Clients\CnpjWsRegistrationLookup;
use App\Support\CurrentOffice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class CnpjLookupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::clear();
        RateLimiter::clear('cnpj-ws-public-api');
    }

    public function test_operador_consulta_cnpj_e_recebe_dto_aninhado_sanitizado(): void
    {
        $this->authenticate(OfficeRole::Operator);
        Http::fake([
            'https://publica.cnpj.ws/cnpj/27865757000102' => Http::response([
                'razao_social' => 'GLOBO COMUNICACAO E PARTICIPACOES S/A',
                'atualizado_em' => '2024-01-15T10:00:00Z',
                'natureza_juridica' => ['id' => '2054', 'descricao' => 'Sociedade Anônima Fechada'],
                'porte' => ['id' => '05', 'descricao' => 'Demais'],
                'capital_social' => '1000000.00',
                'socios' => [['cpf_cnpj_socio' => '12345678901', 'nome' => 'Nao deve sair']],
                'estabelecimento' => [
                    'nome_fantasia' => 'GLOBOPLAY',
                    'tipo' => 'Matriz',
                    'situacao_cadastral' => 'Ativa',
                    'data_situacao_cadastral' => '2005-01-01',
                    'data_inicio_atividade' => '1986-01-26',
                    'email' => 'contato@example.com',
                    'ddd1' => '21',
                    'telefone1' => '12345678',
                    'cep' => '20040020',
                    'tipo_logradouro' => 'RUA',
                    'logradouro' => 'LOPES QUINTAS',
                    'numero' => '303',
                    'bairro' => 'JARDIM BOTANICO',
                    'cidade' => ['nome' => 'RIO DE JANEIRO', 'ibge_id' => '3304557'],
                    'estado' => ['sigla' => 'RJ'],
                    'atividade_principal' => ['id' => '6021700', 'descricao' => 'Atividades de televisão aberta'],
                    'inscricoes_estaduais' => [['inscricao_estadual' => 'secreto']],
                    'simples' => ['simples' => 'Não'],
                ],
            ]),
        ]);

        $response = $this->getJson('/api/v1/cnpj/27865757000102/lookup')
            ->assertOk()
            ->assertJsonPath('data.source', 'CNPJ_WS')
            ->assertJsonPath('data.client.legal_name', 'GLOBO COMUNICACAO E PARTICIPACOES S/A')
            ->assertJsonPath('data.client.root_cnpj', '27865757')
            ->assertJsonPath('data.establishment.trade_name', 'GLOBOPLAY')
            ->assertJsonPath('data.establishment.registration_status', RegistrationStatus::Active->value)
            ->assertJsonPath('data.establishment.address.city', 'RIO DE JANEIRO')
            ->assertJsonMissingPath('data.socios')
            ->assertJsonMissingPath('data.capital_social')
            ->assertJsonMissingPath('data.estabelecimento');

        $json = $response->json('data');
        $this->assertArrayNotHasKey('socios', $json);
        $this->assertArrayNotHasKey('capital_social', $json);
        $this->assertStringNotContainsString('12345678901', json_encode($json));
    }

    public function test_viewer_nao_pode_consultar_e_cnpj_alfanumerico_preserva_fallback_manual(): void
    {
        $this->authenticate(OfficeRole::Viewer);
        $this->getJson('/api/v1/cnpj/27865757000102/lookup')->assertForbidden();

        $this->authenticate(OfficeRole::Operator);
        $this->getJson('/api/v1/cnpj/12ABC34501DE35/lookup')
            ->assertUnprocessable()
            ->assertJsonPath('message', 'A consulta pública ainda aceita somente CNPJ numérico. Preencha o cadastro manualmente.');
    }

    public function test_falha_externa_retorna_mensagem_sanitizada(): void
    {
        $this->authenticate(OfficeRole::Operator);
        Http::fake(['*' => Http::response(['detalhe_interno' => 'segredo'], 500)]);

        $this->getJson('/api/v1/cnpj/27865757000102/lookup')
            ->assertStatus(503)
            ->assertJsonMissingPath('detalhe_interno')
            ->assertJsonMissingPath('data');
    }

    public function test_cache_reutiliza_dto_sanitizado_sem_segunda_chamada(): void
    {
        $this->authenticate(OfficeRole::Operator);
        Http::fake([
            'https://publica.cnpj.ws/cnpj/27865757000102' => Http::response([
                'razao_social' => 'EMPRESA CACHE SA',
                'estabelecimento' => [
                    'nome_fantasia' => 'Cache',
                    'situacao_cadastral' => 'Ativa',
                    'tipo' => 'Matriz',
                ],
            ]),
        ]);

        $this->getJson('/api/v1/cnpj/27865757000102/lookup')->assertOk();
        $this->getJson('/api/v1/cnpj/27865757000102/lookup')
            ->assertOk()
            ->assertJsonPath('data.client.legal_name', 'EMPRESA CACHE SA');

        Http::assertSentCount(1);

        $cached = app(CnpjWsRegistrationLookup::class)->getCached('27865757000102');
        $this->assertNotNull($cached);
        $this->assertSame('EMPRESA CACHE SA', $cached->client->legalName);
    }

    public function test_404_retorna_mensagem_sanitizada(): void
    {
        $this->authenticate(OfficeRole::Operator);
        Http::fake([
            'https://publica.cnpj.ws/cnpj/27865757000102' => Http::response([], 404),
        ]);
        $this->getJson('/api/v1/cnpj/27865757000102/lookup')
            ->assertStatus(503)
            ->assertJsonPath('message', 'CNPJ não localizado na consulta pública.');
    }

    public function test_opcionais_ausentes_aceitam_situacao_unknown(): void
    {
        $this->authenticate(OfficeRole::Operator);
        Http::fake([
            'https://publica.cnpj.ws/cnpj/27865757000102' => Http::response([
                'razao_social' => 'SOMENTE RAZAO',
                'estabelecimento' => [],
            ]),
        ]);

        $this->getJson('/api/v1/cnpj/27865757000102/lookup')
            ->assertOk()
            ->assertJsonPath('data.client.legal_name', 'SOMENTE RAZAO')
            ->assertJsonPath('data.establishment.registration_status', RegistrationStatus::Unknown->value)
            ->assertJsonPath('data.establishment.trade_name', null);
    }

    private function authenticate(OfficeRole $role): void
    {
        $office = Office::factory()->create();
        $user = User::factory()->forOffice($office, $role)->create();
        $this->actingAs($user);
        app(CurrentOffice::class)->resolve($user);
    }
}

<?php

namespace Tests\Unit\Serpro;

use App\Contracts\SecureObjectStore;
use App\Contracts\SerproOperationExecutor;
use App\DTO\Serpro\IntegraRequest;
use App\DTO\Serpro\IntegraResponse;
use App\DTO\Serpro\ProcuradorAuthRequest;
use App\Enums\SecureObjectPurpose;
use App\Enums\SerproAuthorizationStatus;
use App\Enums\SerproContractStatus;
use App\Enums\SerproEnvironment;
use App\Enums\TermoAuthorizationState;
use App\Models\Office;
use App\Models\OfficeSerproAuthorization;
use App\Models\SerproContract;
use App\Services\Integra\HttpAutenticarProcuradorClient;
use App\Services\Serpro\SerproContractService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Tests\Support\TermoFixtureFactory;
use Tests\TestCase;

class HttpAutenticarProcuradorClientTest extends TestCase
{
    use RefreshDatabase;

    public function test_envia_dados_como_json_string_com_xml_base64_e_nao_xml_assinado(): void
    {
        $this->seedContract();
        $fixture = TermoFixtureFactory::signedTermo();
        $captured = null;

        $operations = Mockery::mock(SerproOperationExecutor::class);
        $operations->shouldReceive('executeRequest')
            ->once()
            ->with(Mockery::on(function (IntegraRequest $req) use (&$captured, $fixture) {
                $captured = $req;
                $this->assertSame(['xml'], array_keys($req->businessData));
                $this->assertArrayNotHasKey('dados', $req->businessData);
                $this->assertArrayNotHasKey('xmlAssinado', $req->businessData);
                $this->assertSame(
                    $fixture['xml'],
                    base64_decode((string) $req->businessData['xml'], true),
                );
                $this->assertSame('autentica_procurador.envio_xml_assinado', $req->operationKey);
                $this->assertStringStartsWith(
                    'procurador-auth-v3:'.hash('sha256', $fixture['xml']).':',
                    (string) $req->idempotencyKey,
                );

                return true;
            }))
            ->andReturn(new IntegraResponse(
                success: true,
                httpStatus: 200,
                body: [
                    'autenticar_procurador_token' => 'tok-real-xyz',
                    'data_hora_expiracao' => '2026-07-17T00:00:00',
                ],
                simulated: false,
                etag: 'etag-1',
                dados: ['autenticar_procurador_token' => 'tok-real-xyz'],
            ));

        $client = new HttpAutenticarProcuradorClient(
            $operations,
            app(SerproContractService::class),
            app(SecureObjectStore::class),
        );

        $result = $client->authenticate(new ProcuradorAuthRequest(
            officeId: 1,
            environment: SerproEnvironment::Trial->value,
            authorIdentity: TermoFixtureFactory::defaultAuthorCpf(),
            termoXml: $fixture['xml'],
            contractorBearerToken: 'bearer',
        ));

        $this->assertTrue($result->success);
        $this->assertFalse($result->simulated);
        $this->assertSame(TermoAuthorizationState::SerproAccepted->value, $result->authorizationState);
        $this->assertSame('tok-real-xyz', $result->token);
        $this->assertNotNull($captured);
    }

    public function test_resposta_simulada_nunca_vira_serpro_accepted(): void
    {
        $this->seedContract();
        $fixture = TermoFixtureFactory::signedTermo();

        $operations = Mockery::mock(SerproOperationExecutor::class);
        $operations->shouldReceive('executeRequest')->once()->andReturn(new IntegraResponse(
            success: true,
            httpStatus: 200,
            body: ['autenticar_procurador_token' => 'tok-sim'],
            simulated: true,
        ));

        $client = new HttpAutenticarProcuradorClient(
            $operations,
            app(SerproContractService::class),
            app(SecureObjectStore::class),
        );

        $result = $client->authenticate(new ProcuradorAuthRequest(
            officeId: 2,
            environment: SerproEnvironment::Trial->value,
            authorIdentity: TermoFixtureFactory::defaultAuthorCpf(),
            termoXml: $fixture['xml'],
            contractorBearerToken: 'bearer',
        ));

        $this->assertTrue($result->success);
        $this->assertTrue($result->simulated);
        $this->assertSame(TermoAuthorizationState::Simulated->value, $result->authorizationState);
        $this->assertNotSame(TermoAuthorizationState::SerproAccepted->value, $result->authorizationState);
    }

    public function test_expiracao_sem_offset_e_interpretada_em_horario_de_brasilia(): void
    {
        $this->seedContract();
        $fixture = TermoFixtureFactory::signedTermo();
        Cache::flush();
        $expiry = CarbonImmutable::now('America/Sao_Paulo')->addMinutes(5)->format('Y-m-d\\TH:i:s');

        $operations = Mockery::mock(SerproOperationExecutor::class);
        $operations->shouldReceive('executeRequest')->once()->andReturn(new IntegraResponse(
            success: true,
            httpStatus: 200,
            body: [
                'autenticar_procurador_token' => 'token-de-teste',
                'data_hora_expiracao' => $expiry,
            ],
            simulated: false,
        ));

        $result = (new HttpAutenticarProcuradorClient(
            $operations,
            app(SerproContractService::class),
            app(SecureObjectStore::class),
        ))->authenticate(new ProcuradorAuthRequest(
            officeId: 5,
            environment: SerproEnvironment::Trial->value,
            authorIdentity: TermoFixtureFactory::defaultAuthorCpf(),
            termoXml: $fixture['xml'],
            contractorBearerToken: 'bearer',
        ));

        $this->assertTrue($result->success);
        $this->assertNotNull($result->expiresAt);
        $this->assertTrue($result->expiresAt->isFuture());
        $this->assertSame('UTC', $result->expiresAt->getTimezone()->getName());
    }

    public function test_304_sem_cache_integro_falha_fechado(): void
    {
        $this->seedContract();
        $fixture = TermoFixtureFactory::signedTermo();
        Cache::flush();

        $operations = Mockery::mock(SerproOperationExecutor::class);
        $operations->shouldReceive('executeRequest')->once()->andReturn(new IntegraResponse(
            success: true,
            httpStatus: 304,
            body: [],
            simulated: false,
        ));

        $client = new HttpAutenticarProcuradorClient(
            $operations,
            app(SerproContractService::class),
            app(SecureObjectStore::class),
        );

        $result = $client->authenticate(new ProcuradorAuthRequest(
            officeId: 3,
            environment: SerproEnvironment::Trial->value,
            authorIdentity: TermoFixtureFactory::defaultAuthorCpf(),
            termoXml: $fixture['xml'],
            contractorBearerToken: 'bearer',
        ));

        $this->assertFalse($result->success);
        $this->assertSame('CACHE_INCONSISTENT', $result->errorCode);
    }

    public function test_304_recupera_token_selado_do_mesmo_termo_ate_o_proximo_corte_diario(): void
    {
        $this->seedContract();
        $fixture = TermoFixtureFactory::signedTermo();
        $office = Office::factory()->create();
        $author = TermoFixtureFactory::defaultAuthorCpf();
        $termoHash = hash('sha256', $fixture['xml']);
        $store = app(SecureObjectStore::class);
        $termoVaultId = $store->put($fixture['xml'], SecureObjectPurpose::SerproTermoXml->aadBase([
            'office_id' => $office->id,
            'environment' => SerproEnvironment::Trial->value,
            'kind' => 'signed',
            'sha256' => $termoHash,
            'author_identity' => $author,
        ]));
        $vaultId = $store->put(json_encode([
            'token' => 'token-selado-de-teste',
            'expires_at' => now()->subMinute()->toIso8601String(),
        ], JSON_THROW_ON_ERROR), SecureObjectPurpose::SerproProcuradorToken->aadBase([
            'office_id' => $office->id,
            'environment' => SerproEnvironment::Trial->value,
            'author_identity' => $author,
        ]));
        OfficeSerproAuthorization::query()->create([
            'office_id' => $office->id,
            'environment' => SerproEnvironment::Trial,
            'status' => SerproAuthorizationStatus::TermValid,
            'author_identity_type' => 'CPF',
            'author_identity' => $author,
            'termo_vault_object_id' => $termoVaultId,
            'termo_sha256' => $termoHash,
            'termo_valid_to' => now()->addYear(),
            'procurador_token_vault_object_id' => $vaultId,
            'procurador_token_expires_at' => now()->subMinute(),
        ]);
        Cache::flush();

        $operations = Mockery::mock(SerproOperationExecutor::class);
        $operations->shouldReceive('executeRequest')->once()->andReturn(new IntegraResponse(
            success: true,
            httpStatus: 304,
            body: [],
            simulated: false,
        ));

        $result = (new HttpAutenticarProcuradorClient(
            $operations,
            app(SerproContractService::class),
            $store,
        ))->authenticate(new ProcuradorAuthRequest(
            officeId: $office->id,
            environment: SerproEnvironment::Trial->value,
            authorIdentity: $author,
            termoXml: $fixture['xml'],
            contractorBearerToken: 'bearer',
        ));

        $this->assertTrue($result->success);
        $this->assertSame('token-selado-de-teste', $result->token);
        $this->assertNotNull($result->expiresAt);
        $this->assertTrue($result->expiresAt->isFuture());
        $this->assertSame('00:00:00', $result->expiresAt->setTimezone('America/Sao_Paulo')->format('H:i:s'));
    }

    public function test_token_nao_fica_em_redis_em_claro(): void
    {
        $this->seedContract();
        $fixture = TermoFixtureFactory::signedTermo();
        Cache::flush();

        $operations = Mockery::mock(SerproOperationExecutor::class);
        $operations->shouldReceive('executeRequest')->once()->andReturn(new IntegraResponse(
            success: true,
            httpStatus: 200,
            body: ['autenticar_procurador_token' => 'super-secret-token-abc'],
            simulated: false,
            etag: 'W/"etag-secret"',
            expiresHeader: CarbonImmutable::now()->addHours(6)->toRfc7231String(),
        ));

        $client = new HttpAutenticarProcuradorClient(
            $operations,
            app(SerproContractService::class),
            app(SecureObjectStore::class),
        );

        $author = TermoFixtureFactory::defaultAuthorCpf();
        $result = $client->authenticate(new ProcuradorAuthRequest(
            officeId: 4,
            environment: SerproEnvironment::Trial->value,
            authorIdentity: $author,
            termoXml: $fixture['xml'],
            contractorBearerToken: 'bearer',
        ));

        $this->assertTrue($result->success);
        $this->assertNotNull($result->token);

        $termoHash = hash('sha256', $fixture['xml']);
        $contract = app(SerproContractService::class)->activeFor(SerproEnvironment::Trial);
        $contractKey = (string) ($contract?->id ?? 'x');
        $key = sprintf(
            'serpro:procurador:meta:%d:%s:%s:%s:%s',
            4,
            'TRIAL',
            substr(hash('sha256', $contractKey), 0, 16),
            substr(hash('sha256', $author), 0, 16),
            substr($termoHash, 0, 16),
        );
        $meta = Cache::get($key);
        $this->assertIsArray($meta);
        $this->assertArrayNotHasKey('token', $meta);
        $serialized = json_encode($meta);
        $this->assertStringNotContainsString('super-secret-token-abc', (string) $serialized);
        $this->assertNotEmpty($meta['vault_object_id']);
    }

    private function seedContract(): void
    {
        SerproContract::query()->create([
            'environment' => SerproEnvironment::Trial,
            'status' => SerproContractStatus::Active,
            'contractor_cnpj' => '11222333000181',
            'contractor_name' => 'CONTRATANTE TESTE',
            'health_status' => 'OK',
            'activated_at' => now(),
        ]);
    }
}

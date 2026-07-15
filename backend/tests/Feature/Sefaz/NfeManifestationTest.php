<?php

namespace Tests\Feature\Sefaz;

use App\Contracts\SefazNfeManifestationClient;
use App\Domain\Sefaz\ManifestationResultDto;
use App\Enums\AdnDocumentType;
use App\Enums\CaptureChannel;
use App\Enums\CredentialStatus;
use App\Enums\DocumentDirection;
use App\Enums\FiscalRole;
use App\Enums\NfeManifestationType;
use App\Enums\OfficeRole;
use App\Jobs\ReconsultNfeAfterManifestationJob;
use App\Models\Client;
use App\Models\ClientCredential;
use App\Models\DfeDocument;
use App\Models\DocumentInterest;
use App\Models\Establishment;
use App\Models\NfeDocument;
use App\Models\Office;
use App\Models\User;
use App\Support\CurrentOffice;
use Database\Factories\EstablishmentFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class NfeManifestationTest extends TestCase
{
    use RefreshDatabase;

    public function test_viewer_403_manifestations(): void
    {
        $office = Office::factory()->create();
        $viewer = User::factory()->forOffice($office, OfficeRole::Viewer)->withTwoFactorConfirmed()->create();
        $this->actingAs($viewer);
        app(CurrentOffice::class)->resolve($viewer);

        $key = '35260711222333000181550010000000016666666666';
        $this->seedSummary($office, $key);

        $this->postJson('/api/v1/documents/'.$key.'/manifestations', [
            'type' => 'CIENCIA',
        ])->assertForbidden();
    }

    public function test_viewer_403_unlock(): void
    {
        $office = Office::factory()->create();
        $viewer = User::factory()->forOffice($office, OfficeRole::Viewer)->withTwoFactorConfirmed()->create();
        $this->actingAs($viewer);
        app(CurrentOffice::class)->resolve($viewer);

        $key = '35260711222333000181550010000000015555555555';
        $this->seedSummary($office, $key);

        $this->postJson('/api/v1/documents/'.$key.'/unlock-xml')->assertForbidden();
    }

    public function test_flag_off_retorna_422(): void
    {
        config(['sefaz.manifest_enabled' => false]);
        [$office, $user] = $this->seedOperator();
        $this->actingAs($user);
        app(CurrentOffice::class)->resolve($user);

        $key = '35260711222333000181550010000000014444444444';
        $this->seedSummary($office, $key);

        $this->postJson('/api/v1/documents/'.$key.'/manifestations', ['type' => 'CIENCIA'])
            ->assertStatus(422)
            ->assertJsonPath('data.status', 'flag_off');
    }

    public function test_happy_path_ciencia_enfileira_reconsulta(): void
    {
        config(['sefaz.manifest_enabled' => true]);
        Queue::fake();

        [$office, $user, $establishment] = $this->seedOperatorWithCredential();
        $this->actingAs($user);
        app(CurrentOffice::class)->resolve($user);

        $key = '35260711222333000181550010000000013333333333';
        $this->seedSummary($office, $key, $establishment);

        $mock = Mockery::mock(SefazNfeManifestationClient::class);
        $mock->shouldReceive('register')
            ->once()
            ->withArgs(function (array $cert, string $cnpj, string $accessKey, NfeManifestationType $type) use ($key, $establishment) {
                $this->assertArrayHasKey('pfx', $cert);
                $this->assertArrayNotHasKey('private_key', $cert);
                $this->assertSame($establishment->cnpj, $cnpj);
                $this->assertSame($key, $accessKey);
                $this->assertSame(NfeManifestationType::Ciencia, $type);

                return true;
            })
            ->andReturn(new ManifestationResultDto(
                cStat: '128',
                xMotivo: 'Lote processado',
                protocol: '191111111111111',
                tpEvento: '210210',
                eventCStat: '135',
                eventXMotivo: 'Evento registrado',
            ));
        $this->app->instance(SefazNfeManifestationClient::class, $mock);

        $this->postJson('/api/v1/documents/'.$key.'/manifestations', [
            'type' => 'CIENCIA',
            'purpose' => 'UNLOCK_XML',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'accepted')
            ->assertJsonPath('data.manifestation_status', 'CIENCIA_REGISTRADA')
            ->assertJsonMissingPath('data.pfx')
            ->assertJsonMissingPath('data.password')
            ->assertJsonMissingPath('data.private_key');

        $this->assertDatabaseHas('nfe_documents', [
            'access_key' => $key,
            'manifestation_status' => 'CIENCIA_REGISTRADA',
        ]);

        Queue::assertPushed(ReconsultNfeAfterManifestationJob::class, function (ReconsultNfeAfterManifestationJob $job) use ($key, $office) {
            return $job->accessKey === $key && $job->officeId === $office->id;
        });
    }

    public function test_nao_realizada_sem_justificativa_422(): void
    {
        config(['sefaz.manifest_enabled' => true]);
        [$office, $user, $establishment] = $this->seedOperatorWithCredential();
        $this->actingAs($user);
        app(CurrentOffice::class)->resolve($user);

        $key = '35260711222333000181550010000000012222222222';
        $this->seedSummary($office, $key, $establishment);

        $this->postJson('/api/v1/documents/'.$key.'/manifestations', [
            'type' => 'NAO_REALIZADA',
        ])
            ->assertStatus(422)
            ->assertJsonPath('data.status', 'validation_error');
    }

    public function test_unlock_xml_delega_ciencia(): void
    {
        config(['sefaz.manifest_enabled' => true]);
        Queue::fake();

        [$office, $user, $establishment] = $this->seedOperatorWithCredential();
        $this->actingAs($user);
        app(CurrentOffice::class)->resolve($user);

        $key = '35260711222333000181550010000000011111111111';
        $this->seedSummary($office, $key, $establishment);

        $mock = Mockery::mock(SefazNfeManifestationClient::class);
        $mock->shouldReceive('register')
            ->once()
            ->andReturn(new ManifestationResultDto(
                cStat: '128',
                xMotivo: 'ok',
                protocol: '192222222222222',
                tpEvento: '210210',
                eventCStat: '135',
                eventXMotivo: 'ok',
            ));
        $this->app->instance(SefazNfeManifestationClient::class, $mock);

        $this->postJson('/api/v1/documents/'.$key.'/unlock-xml')
            ->assertOk()
            ->assertJsonPath('data.status', 'accepted');
    }

    /**
     * @return array{0: Office, 1: User}
     */
    private function seedOperator(): array
    {
        $office = Office::factory()->create();
        $user = User::factory()->forOffice($office, OfficeRole::Operator)->withTwoFactorConfirmed()->create();

        return [$office, $user];
    }

    /**
     * @return array{0: Office, 1: User, 2: Establishment}
     */
    private function seedOperatorWithCredential(): array
    {
        [$office, $user] = $this->seedOperator();
        $client = Client::factory()->forOffice($office)->create(['root_cnpj' => '99888777']);
        $est = Establishment::factory()
            ->forClient($client, EstablishmentFactory::cnpjWithRoot('99888777'))
            ->create();

        $store = app(\App\Contracts\SecureObjectStore::class);
        $payload = json_encode([
            'pfx' => base64_encode('fake-pfx-binary-not-used'),
            'password' => 'secret',
        ], JSON_THROW_ON_ERROR);
        $fp = strtoupper(hash('sha256', 'fake-pfx'));
        $objectId = $store->put($payload, [
            'office_id' => $office->id,
            'client_id' => $client->id,
            'fingerprint' => $fp,
        ]);

        ClientCredential::query()->create([
            'office_id' => $office->id,
            'client_id' => $client->id,
            'status' => CredentialStatus::Active,
            'subject_name' => 'Teste',
            'holder_cnpj' => $est->cnpj,
            'fingerprint_sha256' => $fp,
            'valid_from' => now()->subYear(),
            'valid_to' => now()->addYear(),
            'vault_object_id' => $objectId,
            'activated_at' => now(),
        ]);

        return [$office, $user, $est];
    }

    private function seedSummary(Office $office, string $accessKey, ?Establishment $establishment = null): NfeDocument
    {
        $xml = '<resNFe><chNFe>'.$accessKey.'</chNFe></resNFe>';
        $sha = hash('sha256', $xml.$accessKey);
        $store = app(\App\Contracts\SecureObjectStore::class);
        $objectId = $store->put($xml, ['office_id' => $office->id, 'sha256' => $sha]);

        $doc = DfeDocument::query()->create([
            'office_id' => $office->id,
            'sha256' => $sha,
            'document_type' => AdnDocumentType::Nfe,
            'schema_version' => 'resNFe_v1.01.xsd',
            'access_key' => $accessKey,
            'vault_object_id' => $objectId,
            'byte_size' => strlen($xml),
            'parse_status' => 'OK',
        ]);

        if ($establishment !== null) {
            DocumentInterest::query()->create([
                'office_id' => $office->id,
                'dfe_document_id' => $doc->id,
                'establishment_id' => $establishment->id,
                'nsu' => 1,
                'environment' => 'production',
                'channel' => CaptureChannel::NfeDistDfe->value,
                'fiscal_role' => FiscalRole::Taker,
            ]);
        }

        return NfeDocument::query()->create([
            'office_id' => $office->id,
            'dfe_document_id' => $doc->id,
            'access_key' => $accessKey,
            'model' => '55',
            'issuer_cnpj' => '11222333000181',
            'recipient_cnpj' => $establishment?->cnpj ?? '99888777000166',
            'fiscal_role' => FiscalRole::Taker,
            'direction' => DocumentDirection::In,
            'status' => 'SUMMARY',
            'is_summary' => true,
            'manifestation_status' => 'PENDING_MANIFESTATION',
        ]);
    }
}

<?php

namespace Tests\Feature\Sefaz;

use App\Contracts\SecureObjectStore;
use App\Contracts\SefazNfeManifestationClient;
use App\Domain\Sefaz\ManifestationResultDto;
use App\Enums\AdnDocumentType;
use App\Enums\CaptureChannel;
use App\Enums\CredentialStatus;
use App\Enums\DocumentDirection;
use App\Enums\FiscalRole;
use App\Enums\NfeManifestationType;
use App\Jobs\AutoCienciaNfeJob;
use App\Jobs\ReconsultNfeAfterManifestationJob;
use App\Models\Client;
use App\Models\ClientCredential;
use App\Models\DfeDocument;
use App\Models\DocumentInterest;
use App\Models\Establishment;
use App\Models\NfeDocument;
use App\Models\Office;
use App\Services\Sefaz\AutoCienciaScheduler;
use App\Services\Sefaz\NfeManifestationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class AutoCienciaTest extends TestCase
{
    use RefreshDatabase;

    public function test_scheduler_enfileira_apenas_resumo_sem_full(): void
    {
        config([
            'sefaz.auto_ciencia_enabled' => true,
            'sefaz.manifest_enabled' => false,
            'sefaz.auto_ciencia_delay_seconds' => 0,
        ]);
        Queue::fake();

        $office = Office::factory()->create();
        $pendingKey = '35260711222333000181550010000000010000000001';
        $fullKey = '35260711222333000181550010000000010000000002';

        $this->seedSummary($office->id, $pendingKey);
        $this->seedSummary($office->id, $fullKey);
        $this->seedFull($office->id, $fullKey);

        $n = app(AutoCienciaScheduler::class)->enqueueForKeys($office->id, [$pendingKey, $fullKey]);

        $this->assertSame(1, $n);
        Queue::assertPushed(AutoCienciaNfeJob::class, 1);
        Queue::assertPushed(AutoCienciaNfeJob::class, fn (AutoCienciaNfeJob $job) => $job->accessKey === $pendingKey);
    }

    public function test_job_envia_ciencia_e_enfileira_reconsulta(): void
    {
        config([
            'sefaz.auto_ciencia_enabled' => true,
            'sefaz.manifest_enabled' => false,
        ]);
        Queue::fake();

        $office = Office::factory()->create();
        $client = Client::factory()->forOffice($office)->create(['is_active' => true]);
        $est = Establishment::factory()->forClient($client)->create([
            'is_active' => true,
            'capture_enabled' => true,
            'cnpj' => '99888777000166',
        ]);
        $store = app(SecureObjectStore::class);
        $fp = strtoupper(hash('sha256', 'auto-ciencia-pfx-'.$client->id));
        $objectId = $store->put(json_encode([
            'pfx' => base64_encode('fake-pfx-binary-not-used'),
            'password' => 'secret',
        ], JSON_THROW_ON_ERROR), [
            'office_id' => $office->id,
            'client_id' => $client->id,
            'fingerprint' => $fp,
        ]);

        ClientCredential::query()->create([
            'office_id' => $office->id,
            'client_id' => $client->id,
            'status' => CredentialStatus::Active,
            'subject_name' => $client->legal_name,
            'holder_cnpj' => $est->cnpj,
            'fingerprint_sha256' => $fp,
            'valid_from' => now()->subYear(),
            'valid_to' => now()->addYear(),
            'vault_object_id' => $objectId,
            'activated_at' => now(),
        ]);

        $key = '35260711222333000181550010000000010000000003';
        $this->seedSummary($office->id, $key, $est);

        $mock = Mockery::mock(SefazNfeManifestationClient::class);
        $mock->shouldReceive('register')
            ->once()
            ->withArgs(function ($cert, $cnpj, $accessKey, NfeManifestationType $type) use ($key, $est) {
                return $accessKey === $key
                    && $type === NfeManifestationType::Ciencia
                    && $cnpj === $est->cnpj;
            })
            ->andReturn(new ManifestationResultDto(
                cStat: '128',
                xMotivo: 'Lote processado',
                protocol: '191234567890123',
                tpEvento: '210210',
                eventCStat: '135',
                eventXMotivo: 'Evento registrado',
            ));
        $this->app->instance(SefazNfeManifestationClient::class, $mock);

        (new AutoCienciaNfeJob($office->id, $key))->handle(app(NfeManifestationService::class));

        $this->assertSame(
            'CIENCIA_REGISTRADA',
            NfeDocument::query()->where('access_key', $key)->value('manifestation_status')
        );
        Queue::assertPushed(ReconsultNfeAfterManifestationJob::class);
    }

    public function test_job_noop_quando_flag_off(): void
    {
        config([
            'sefaz.auto_ciencia_enabled' => false,
            'sefaz.manifest_enabled' => false,
        ]);

        $office = Office::factory()->create();
        $key = '35260711222333000181550010000000010000000004';
        $this->seedSummary($office->id, $key);

        $mock = Mockery::mock(SefazNfeManifestationClient::class);
        $mock->shouldNotReceive('register');
        $this->app->instance(SefazNfeManifestationClient::class, $mock);

        (new AutoCienciaNfeJob($office->id, $key))->handle(app(NfeManifestationService::class));

        $this->assertSame(
            'PENDING_MANIFESTATION',
            NfeDocument::query()->where('access_key', $key)->value('manifestation_status')
        );
    }

    private function seedSummary(int $officeId, string $accessKey, ?Establishment $est = null): void
    {
        $xml = '<resNFe><chNFe>'.$accessKey.'</chNFe></resNFe>';
        $doc = DfeDocument::query()->create([
            'office_id' => $officeId,
            'sha256' => hash('sha256', $xml.$accessKey),
            'document_type' => AdnDocumentType::Nfe,
            'schema_version' => 'resNFe_v1.01.xsd',
            'access_key' => $accessKey,
            'vault_object_id' => '01TESTSUM'.substr(hash('sha256', $accessKey), 0, 16),
            'byte_size' => strlen($xml),
            'parse_status' => 'OK',
        ]);

        NfeDocument::query()->create([
            'office_id' => $officeId,
            'dfe_document_id' => $doc->id,
            'access_key' => $accessKey,
            'number' => '1',
            'issuer_cnpj' => '11222333000181',
            'recipient_cnpj' => $est?->cnpj ?? '99888777000166',
            'fiscal_role' => FiscalRole::Taker,
            'direction' => DocumentDirection::In,
            'status' => 'ACTIVE',
            'is_summary' => true,
            'manifestation_status' => 'PENDING_MANIFESTATION',
        ]);

        if ($est !== null) {
            DocumentInterest::query()->create([
                'office_id' => $officeId,
                'dfe_document_id' => $doc->id,
                'establishment_id' => $est->id,
                'nsu' => random_int(1, 99999),
                'environment' => 'production',
                'channel' => CaptureChannel::NfeDistDfe->value,
                'fiscal_role' => FiscalRole::Taker->value,
            ]);
        }
    }

    private function seedFull(int $officeId, string $accessKey): void
    {
        $xml = '<nfeProc><chNFe>'.$accessKey.'</chNFe></nfeProc>';
        $doc = DfeDocument::query()->create([
            'office_id' => $officeId,
            'sha256' => hash('sha256', $xml.$accessKey.'full'),
            'document_type' => AdnDocumentType::Nfe,
            'schema_version' => 'procNFe_v4.00.xsd',
            'access_key' => $accessKey,
            'vault_object_id' => '01TESTFULL'.substr(hash('sha256', $accessKey), 0, 15),
            'byte_size' => strlen($xml),
            'parse_status' => 'OK',
        ]);

        NfeDocument::query()->create([
            'office_id' => $officeId,
            'dfe_document_id' => $doc->id,
            'access_key' => $accessKey,
            'number' => '1',
            'issuer_cnpj' => '11222333000181',
            'recipient_cnpj' => '99888777000166',
            'fiscal_role' => FiscalRole::Taker,
            'direction' => DocumentDirection::In,
            'status' => 'ACTIVE',
            'is_summary' => false,
            'manifestation_status' => null,
        ]);
    }
}

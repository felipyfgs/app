<?php

namespace Tests\Feature\Fiscal\SimplesMei;

use App\Contracts\SecureObjectStore;
use App\Enums\FiscalSourceProvenance;
use App\Models\CcmeiIssuedCertificate;
use App\Models\Client;
use App\Models\Establishment;
use App\Models\Office;
use App\Services\Fiscal\SimplesMei\CcmeiCertificateIssuanceProjector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use RuntimeException;
use Tests\TestCase;

final class CcmeiCertificateIssuanceProjectorTest extends TestCase
{
    use RefreshDatabase;

    private Office $office;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->office = Office::factory()->create();
        $this->client = Client::factory()->forOffice($this->office)->create(['root_cnpj' => '11222333']);
        Establishment::factory()->forClient($this->client, '11222333000181')->create();
    }

    public function test_stores_valid_trial_document_once_and_hides_vault_references(): void
    {
        $bytes = '%PDF-1.7 certificate';
        $sha256 = hash('sha256', $bytes);
        $vault = Mockery::mock(SecureObjectStore::class);
        $vault->shouldReceive('put')->once()
            ->with($bytes, CcmeiCertificateIssuanceProjector::certificateAad($this->office->id, $this->client->id, $sha256))
            ->andReturn('01JCCMEICERTIFICATE000001');
        $this->app->instance(SecureObjectStore::class, $vault);

        $service = app(CcmeiCertificateIssuanceProjector::class);
        $dados = ['cnpj' => '11222333000181', 'pdf' => base64_encode($bytes)];
        $first = $service->project($this->office, $this->client, FiscalSourceProvenance::SerproTrial->value, $dados);
        $second = $service->project($this->office, $this->client, FiscalSourceProvenance::SerproTrial->value, $dados);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, CcmeiIssuedCertificate::query()->withoutGlobalScopes()->count());
        $public = $second->toPublicArray();
        $this->assertSame('application/pdf', $public['mime_type']);
        $this->assertArrayNotHasKey('certificate_vault_object_id', $public);
        $this->assertArrayNotHasKey('certificate_sha256', $public);
        $this->assertArrayNotHasKey('contributor_cnpj', $public);
    }

    public function test_rejects_non_official_source_or_cross_tenant_before_writing(): void
    {
        $vault = Mockery::mock(SecureObjectStore::class);
        $vault->shouldNotReceive('put');
        $this->app->instance(SecureObjectStore::class, $vault);
        $service = app(CcmeiCertificateIssuanceProjector::class);
        $dados = ['cnpj' => '11222333000181', 'pdf' => base64_encode('%PDF-1.4')];

        $otherOffice = Office::factory()->create();
        foreach ([['SIMULATED', $this->client], [FiscalSourceProvenance::SerproTrial->value, Client::factory()->forOffice($otherOffice)->create()]] as [$source, $client]) {
            try {
                $service->project($this->office, $client, $source, $dados);
                $this->fail('A projeção deveria ser rejeitada.');
            } catch (RuntimeException) {
                $this->addToAssertionCount(1);
            }
        }
        $this->assertSame(0, CcmeiIssuedCertificate::query()->withoutGlobalScopes()->count());
    }
}

<?php

namespace Tests\Feature\Fiscal\Guides;

use App\Contracts\SecureObjectStore;
use App\Enums\FiscalSourceProvenance;
use App\Enums\OfficeRole;
use App\Models\Client;
use App\Models\FiscalMonitoringRun;
use App\Models\Office;
use App\Models\PagtowebArrecadacaoReceipt;
use App\Models\User;
use App\Services\Fiscal\Guides\PagtowebArrecadacaoReceiptProjector;
use App\Support\CurrentOffice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

final class PagtowebArrecadacaoReceiptApiTest extends TestCase
{
    use RefreshDatabase;

    private Office $office;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->office = Office::factory()->create();
        $this->client = Client::factory()->forOffice($this->office)->create();
        $user = User::factory()->forOffice($this->office, OfficeRole::Admin)->withTwoFactorConfirmed()->create();
        $this->actingAs($user);
        app(CurrentOffice::class)->clear();
        app(CurrentOffice::class)->resolve($user);
    }

    public function test_history_is_local_and_rejects_client_office_id(): void
    {
        Queue::fake();
        $receipt = $this->receipt($this->client);
        $base = "/api/v1/fiscal/guides/receipts/clients/{$this->client->id}";

        $this->getJson("{$base}/history")
            ->assertOk()
            ->assertJsonPath('data.provenance.serpro_called', false)
            ->assertJsonPath('data.items.0.id', $receipt->id)
            ->assertJsonMissingPath('data.items.0.receipt_vault_object_id')
            ->assertJsonMissingPath('data.items.0.receipt_sha256');
        $this->getJson("{$base}/history?office_id={$this->office->id}")
            ->assertStatus(422)
            ->assertJsonPath('code', 'CLIENT_OFFICE_ID_REJECTED');
        Queue::assertNothingPushed();
    }

    public function test_download_is_tenant_scoped_and_private(): void
    {
        $receipt = $this->receipt($this->client);
        $vault = Mockery::mock(SecureObjectStore::class);
        $vault->shouldReceive('get')->once()->andReturn('%PDF-1.4 receipt');
        $this->app->instance(SecureObjectStore::class, $vault);
        $this->app->forgetInstance(PagtowebArrecadacaoReceiptProjector::class);

        $response = $this->get("/api/v1/fiscal/guides/receipts/clients/{$this->client->id}/{$receipt->id}/download");
        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringContainsString('private', (string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $response->assertHeader('X-Content-Type-Options', 'nosniff');

        $other = Client::factory()->forOffice($this->office)->create();
        $this->getJson("/api/v1/fiscal/guides/receipts/clients/{$other->id}/{$receipt->id}/download")
            ->assertNotFound();

        $foreignOffice = Office::factory()->create();
        $foreignClient = Client::factory()->forOffice($foreignOffice)->create();
        $foreignReceipt = $this->receipt($foreignClient);
        $this->getJson("/api/v1/fiscal/guides/receipts/clients/{$foreignClient->id}/{$foreignReceipt->id}/download")
            ->assertNotFound();
    }

    public function test_confirmed_request_never_serializes_document_number_in_public_run_or_database(): void
    {
        Queue::fake();
        config(['fiscal_monitoring.enabled' => true, 'serpro.capabilities.guides' => 'disabled']);
        $number = '12345678901234567';

        $response = $this->postJson("/api/v1/fiscal/guides/receipts/clients/{$this->client->id}/request", [
            'confirmed' => true,
            'numeroDocumento' => $number,
        ])->assertCreated();

        $response->assertJsonMissingPath('data.office_id')
            ->assertJsonMissingPath('data.client_id')
            ->assertJsonMissingPath('data.idempotency_key')
            ->assertJsonMissingPath('data.correlation_id');
        $serialized = json_encode(FiscalMonitoringRun::query()->firstOrFail()->getAttributes(), JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString($number, $serialized);
        $this->assertStringNotContainsString($number, (string) $response->getContent());
        Queue::assertNothingPushed();
    }

    public function test_viewer_cannot_request_a_billable_receipt(): void
    {
        $viewer = User::factory()->forOffice($this->office, OfficeRole::Viewer)->withTwoFactorConfirmed()->create();
        $this->actingAs($viewer);
        app(CurrentOffice::class)->clear();
        app(CurrentOffice::class)->resolve($viewer);

        $this->postJson("/api/v1/fiscal/guides/receipts/clients/{$this->client->id}/request", [
            'confirmed' => true,
            'numeroDocumento' => '12345678901234567',
        ])->assertForbidden();
    }

    private function receipt(Client $client): PagtowebArrecadacaoReceipt
    {
        return PagtowebArrecadacaoReceipt::query()->create([
            'office_id' => $client->office_id,
            'client_id' => $client->id,
            'receipt_vault_object_id' => '01JPAGTOWEBAPI0000000001',
            'receipt_sha256' => str_repeat('a', 64),
            'receipt_mime_type' => 'application/pdf',
            'receipt_byte_size' => 18,
            'source_provenance' => FiscalSourceProvenance::SerproTrial,
            'observed_at' => now(),
        ]);
    }
}

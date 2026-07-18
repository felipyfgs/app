<?php

namespace Tests\Feature\Fiscal\Guides;

use App\Contracts\SecureObjectStore;
use App\Enums\OfficeRole;
use App\Models\Client;
use App\Models\Office;
use App\Models\PagtowebArrecadacaoReceipt;
use App\Models\User;
use App\Services\Fiscal\Guides\PagtowebArrecadacaoReceiptProjector;
use App\Support\CurrentOffice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class PagtowebArrecadacaoReceiptControllerTest extends TestCase
{
    use RefreshDatabase;

    private Office $office;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'features.global_enabled' => true,
            'features.kill_switch' => false,
            'features.modules.guias.enabled' => true,
            'features.modules.guias.allow_all_offices' => true,
            'fiscal_monitoring.enabled' => true,
        ]);
        $this->office = Office::factory()->create();
        $this->client = Client::factory()->forOffice($this->office)->create();
        $user = User::factory()->forOffice($this->office, OfficeRole::Admin)->withTwoFactorConfirmed()->create();
        $this->actingAs($user);
        app(CurrentOffice::class)->clear();
        app(CurrentOffice::class)->resolve($user);
    }

    #[Test]
    public function it_returns_only_local_sanitized_receipt_history(): void
    {
        $receipt = $this->receipt($this->office, $this->client);
        $foreignOffice = Office::factory()->create();
        $foreignClient = Client::factory()->forOffice($foreignOffice)->create();
        $this->receipt($foreignOffice, $foreignClient);

        $this->getJson($this->base().'/history')
            ->assertOk()
            ->assertJsonPath('data.client_id', $this->client->id)
            ->assertJsonPath('data.items.0.id', $receipt->id)
            ->assertJsonPath('data.items.0.mime_type', 'application/pdf')
            ->assertJsonMissingPath('data.items.0.receipt_vault_object_id')
            ->assertJsonMissingPath('data.items.0.receipt_sha256')
            ->assertJsonMissingPath('data.items.0.numeroDocumento');
    }

    #[Test]
    public function it_rejects_client_supplied_office_id_and_does_not_enumerate_foreign_clients(): void
    {
        $foreign = Client::factory()->forOffice(Office::factory()->create())->create();

        $this->postJson($this->base().'/request', [
            'confirmed' => true,
            'numeroDocumento' => '12345678901234567',
            'context' => ['office_id' => $this->office->id],
        ])->assertUnprocessable()->assertJsonPath('code', 'CLIENT_OFFICE_ID_REJECTED');
        $this->getJson("/api/v1/fiscal/guides/receipts/clients/{$foreign->id}/history")
            ->assertNotFound()->assertJsonPath('code', 'RESOURCE_NOT_FOUND');
    }

    #[Test]
    public function it_downloads_only_the_current_office_client_receipt_with_private_headers(): void
    {
        $receipt = $this->receipt($this->office, $this->client);
        $vault = Mockery::mock(SecureObjectStore::class);
        $vault->shouldReceive('get')->once()->with(
            $receipt->receipt_vault_object_id,
            PagtowebArrecadacaoReceiptProjector::receiptAad($this->office->id, $this->client->id, $receipt->receipt_sha256),
        )->andReturn('%PDF-1.4');
        $this->app->instance(SecureObjectStore::class, $vault);

        $this->get($this->base()."/{$receipt->id}/download")
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    #[Test]
    public function viewer_can_read_but_cannot_request_and_foreign_receipt_is_not_exposed(): void
    {
        $viewer = User::factory()->forOffice($this->office, OfficeRole::Viewer)->withTwoFactorConfirmed()->create();
        $foreignOffice = Office::factory()->create();
        $foreignClient = Client::factory()->forOffice($foreignOffice)->create();
        $foreignReceipt = $this->receipt($foreignOffice, $foreignClient);
        $this->actingAs($viewer);
        app(CurrentOffice::class)->clear();
        app(CurrentOffice::class)->resolve($viewer);

        $this->getJson($this->base().'/history')->assertOk();
        $this->postJson($this->base().'/request', ['confirmed' => true, 'numeroDocumento' => '12345678901234567'])->assertForbidden();
        $this->get("{$this->base()}/{$foreignReceipt->id}/download")->assertNotFound();
    }

    private function base(): string
    {
        return "/api/v1/fiscal/guides/receipts/clients/{$this->client->id}";
    }

    private function receipt(Office $office, Client $client): PagtowebArrecadacaoReceipt
    {
        return PagtowebArrecadacaoReceipt::query()->create([
            'office_id' => $office->id,
            'client_id' => $client->id,
            'receipt_vault_object_id' => '01JPAGTOWEBRECEIPT'.str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT),
            'receipt_sha256' => hash('sha256', uniqid('receipt-', true)),
            'receipt_mime_type' => 'application/pdf',
            'receipt_byte_size' => 8,
            'source_provenance' => 'SERPRO_TRIAL',
            'observed_at' => now(),
        ]);
    }
}

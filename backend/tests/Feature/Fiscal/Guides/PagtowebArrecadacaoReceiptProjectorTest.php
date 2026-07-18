<?php

namespace Tests\Feature\Fiscal\Guides;

use App\Contracts\SecureObjectStore;
use App\Enums\FiscalSourceProvenance;
use App\Models\Client;
use App\Models\Office;
use App\Models\PagtowebArrecadacaoReceipt;
use App\Services\Fiscal\Guides\PagtowebArrecadacaoReceiptProjector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use RuntimeException;
use Tests\TestCase;

final class PagtowebArrecadacaoReceiptProjectorTest extends TestCase
{
    use RefreshDatabase;

    public function test_stores_official_receipt_once_and_hides_vault_references(): void
    {
        $office = Office::factory()->create();
        $client = Client::factory()->forOffice($office)->create();
        $bytes = "%PDF-1.4\nrecibo";
        $sha256 = hash('sha256', $bytes);
        $vault = Mockery::mock(SecureObjectStore::class);
        $vault->shouldReceive('put')->once()
            ->with($bytes, PagtowebArrecadacaoReceiptProjector::receiptAad($office->id, $client->id, $sha256))
            ->andReturn('01JPAGTOWEBRECEIPT000001');
        $this->app->instance(SecureObjectStore::class, $vault);

        $projector = app(PagtowebArrecadacaoReceiptProjector::class);
        $first = $projector->project($office, $client, FiscalSourceProvenance::SerproTrial->value, base64_encode($bytes));
        $second = $projector->project($office, $client, FiscalSourceProvenance::SerproTrial->value, base64_encode($bytes));

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, PagtowebArrecadacaoReceipt::query()->withoutGlobalScopes()->count());
        $this->assertSame('application/pdf', $second->toPublicArray()['mime_type']);
        $this->assertArrayNotHasKey('receipt_vault_object_id', $second->toPublicArray());
        $this->assertArrayNotHasKey('receipt_sha256', $second->toPublicArray());
    }

    public function test_rejects_unverified_source_and_cross_tenant_before_vault_write(): void
    {
        $office = Office::factory()->create();
        $client = Client::factory()->forOffice($office)->create();
        $otherOffice = Office::factory()->create();
        $otherClient = Client::factory()->forOffice($otherOffice)->create();
        $vault = Mockery::mock(SecureObjectStore::class);
        $vault->shouldNotReceive('put');
        $this->app->instance(SecureObjectStore::class, $vault);
        $projector = app(PagtowebArrecadacaoReceiptProjector::class);

        foreach ([['SIMULATED', $client], [FiscalSourceProvenance::SerproReal->value, $otherClient]] as [$source, $candidate]) {
            try {
                $projector->project($office, $candidate, $source, base64_encode('%PDF-1.4'));
                $this->fail('A projeção deveria ser rejeitada.');
            } catch (RuntimeException) {
                $this->addToAssertionCount(1);
            }
        }
        $this->assertSame(0, PagtowebArrecadacaoReceipt::query()->withoutGlobalScopes()->count());
    }
}

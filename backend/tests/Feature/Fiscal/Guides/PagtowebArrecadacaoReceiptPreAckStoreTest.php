<?php

namespace Tests\Feature\Fiscal\Guides;

use App\Contracts\SecureObjectStore;
use App\DTO\Serpro\IntegraResponse;
use App\Enums\FiscalSourceProvenance;
use App\Models\Client;
use App\Models\FiscalMonitoringRun;
use App\Models\Office;
use App\Models\PagtowebArrecadacaoReceipt;
use App\Services\Fiscal\Guides\PagtowebArrecadacaoReceiptPreAckStore;
use App\Services\Fiscal\Guides\PagtowebArrecadacaoReceiptQueryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use RuntimeException;
use Tests\TestCase;

final class PagtowebArrecadacaoReceiptPreAckStoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_captures_pdf_before_ack_and_returns_only_descriptor(): void
    {
        $office = Office::factory()->create();
        $client = Client::factory()->forOffice($office)->create();
        $run = FiscalMonitoringRun::query()->create([
            'office_id' => $office->id,
            'client_id' => $client->id,
            'system_code' => 'PAGTOWEB',
            'service_code' => 'COMPARRECADACAO72',
            'operation_code' => 'EMITIR_COMPROVANTE_ARRECADACAO',
            'operation_key' => 'pagtoweb.comparrecadacao',
            'source_provenance' => FiscalSourceProvenance::SerproReal->value,
            'trigger' => 'MANUAL',
            'idempotency_key' => 'pagtoweb-pre-ack-'.$client->id,
            'status' => 'QUEUED',
            'situation' => 'UNKNOWN',
            'coverage' => 'FULL',
            'mutability' => 'READ_ONLY',
        ]);
        $bytes = '%PDF-1.4 comprovante';
        $vault = Mockery::mock(SecureObjectStore::class);
        $vault->shouldReceive('put')->once()->andReturn('01JPAGTOWEBPREACK0000001');
        $this->app->instance(SecureObjectStore::class, $vault);

        $captured = app(PagtowebArrecadacaoReceiptPreAckStore::class)->capture(
            'pagtoweb.comparrecadacao',
            'fiscal-run:'.$run->id,
            new IntegraResponse(
                success: true,
                httpStatus: 200,
                body: ['dados' => base64_encode($bytes)],
                dados: base64_encode($bytes),
                sourceProvenance: FiscalSourceProvenance::SerproReal->value,
            ),
            $office->id,
            $client->id,
        );

        $serialized = json_encode(['body' => $captured->body, 'dados' => $captured->dados], JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('JVBER', $serialized);
        $this->assertSame(1, PagtowebArrecadacaoReceipt::query()->withoutGlobalScopes()->count());
        $this->assertSame(true, $captured->dados['available']);
        $this->assertArrayHasKey('receipt_id', $captured->dados);
    }

    public function test_invalid_ephemeral_number_does_not_create_a_run(): void
    {
        $office = Office::factory()->create();
        $client = Client::factory()->forOffice($office)->create();

        $this->expectException(\InvalidArgumentException::class);
        app(PagtowebArrecadacaoReceiptQueryService::class)->request($office, $client, '', null);
    }

    public function test_official_trial_receipt_is_projected_but_keeps_trial_provenance(): void
    {
        $office = Office::factory()->create();
        $client = Client::factory()->forOffice($office)->create();
        $run = FiscalMonitoringRun::query()->create([
            'office_id' => $office->id,
            'client_id' => $client->id,
            'system_code' => 'PAGTOWEB',
            'service_code' => 'COMPARRECADACAO72',
            'operation_code' => 'EMITIR_COMPROVANTE_ARRECADACAO',
            'operation_key' => 'pagtoweb.comparrecadacao',
            'source_provenance' => FiscalSourceProvenance::SerproTrial->value,
            'trigger' => 'MANUAL',
            'idempotency_key' => 'pagtoweb-trial-pre-ack-'.$client->id,
            'status' => 'QUEUED',
            'situation' => 'UNKNOWN',
            'coverage' => 'FULL',
            'mutability' => 'READ_ONLY',
        ]);
        $vault = Mockery::mock(SecureObjectStore::class);
        $vault->shouldReceive('put')->once()->andReturn('01JPAGTOWEBTRIAL00000001');
        $this->app->instance(SecureObjectStore::class, $vault);

        app(PagtowebArrecadacaoReceiptPreAckStore::class)->capture(
            'pagtoweb.comparrecadacao',
            'fiscal-run:'.$run->id,
            new IntegraResponse(
                success: true,
                httpStatus: 200,
                body: ['dados' => base64_encode('%PDF-1.4 trial')],
                dados: base64_encode('%PDF-1.4 trial'),
                sourceProvenance: FiscalSourceProvenance::SerproTrial->value,
            ),
            $office->id,
            $client->id,
        );

        $this->assertDatabaseHas('pagtoweb_arrecadacao_receipts', [
            'office_id' => $office->id,
            'client_id' => $client->id,
            'source_provenance' => FiscalSourceProvenance::SerproTrial->value,
        ]);
    }

    public function test_capture_failure_leaves_no_partial_receipt_for_later_ack(): void
    {
        $office = Office::factory()->create();
        $client = Client::factory()->forOffice($office)->create();
        $run = FiscalMonitoringRun::query()->create([
            'office_id' => $office->id,
            'client_id' => $client->id,
            'system_code' => 'PAGTOWEB',
            'service_code' => 'COMPARRECADACAO72',
            'operation_code' => 'EMITIR_COMPROVANTE_ARRECADACAO',
            'operation_key' => 'pagtoweb.comparrecadacao',
            'source_provenance' => FiscalSourceProvenance::SerproReal->value,
            'trigger' => 'MANUAL',
            'idempotency_key' => 'pagtoweb-pre-ack-failure-'.$client->id,
            'status' => 'QUEUED',
            'situation' => 'UNKNOWN',
            'coverage' => 'FULL',
            'mutability' => 'READ_ONLY',
        ]);
        $vault = Mockery::mock(SecureObjectStore::class);
        $vault->shouldReceive('put')->once()->andThrow(new RuntimeException('cofre indisponível'));
        $this->app->instance(SecureObjectStore::class, $vault);

        $this->expectException(RuntimeException::class);
        try {
            app(PagtowebArrecadacaoReceiptPreAckStore::class)->capture(
                'pagtoweb.comparrecadacao',
                'fiscal-run:'.$run->id,
                new IntegraResponse(
                    success: true,
                    httpStatus: 200,
                    body: ['dados' => base64_encode('%PDF-1.4 falha')],
                    dados: base64_encode('%PDF-1.4 falha'),
                    sourceProvenance: FiscalSourceProvenance::SerproReal->value,
                ),
                $office->id,
                $client->id,
            );
        } finally {
            $this->assertDatabaseMissing('pagtoweb_arrecadacao_receipts', ['office_id' => $office->id, 'client_id' => $client->id]);
        }
    }
}

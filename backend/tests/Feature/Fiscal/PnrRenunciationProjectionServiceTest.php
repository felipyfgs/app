<?php

namespace Tests\Feature\Fiscal;

use App\Contracts\SecureObjectStore;
use App\Enums\FiscalSourceProvenance;
use App\Models\Client;
use App\Models\Establishment;
use App\Models\FiscalPnrRenunciation;
use App\Models\Office;
use App\Services\Integra\Registrations\PnrRenunciationProjectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use RuntimeException;
use Tests\TestCase;

final class PnrRenunciationProjectionServiceTest extends TestCase
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

    public function test_projeta_historico_de_modo_idempotente_e_sanitizado(): void
    {
        $service = app(PnrRenunciationProjectionService::class);
        $dados = [
            'content' => [['id' => 42, 'cnpjRenunciada' => '11222333000181', 'dataRenuncia' => 1_700_000_000_000]],
            'number' => 0,
            'last' => true,
            'totalElements' => 1,
        ];

        $first = $service->projectHistory($this->office, $this->client, FiscalSourceProvenance::SerproTrial->value, $dados);
        $second = $service->projectHistory($this->office, $this->client, FiscalSourceProvenance::SerproTrial->value, $dados);

        $this->assertCount(1, $first);
        $this->assertCount(1, $second);
        $this->assertSame(1, FiscalPnrRenunciation::query()->withoutGlobalScopes()->count());
        $projection = FiscalPnrRenunciation::query()->withoutGlobalScopes()->sole();
        $this->assertSame(42, $projection->renunciation_id);
        $this->assertSame(FiscalSourceProvenance::SerproTrial, $projection->source_provenance);
        $this->assertSame(['history_page' => 0, 'history_total' => 1, 'history_last_page' => true], $projection->summary_sanitized);
        $this->assertSame('11222333000181', $projection->contributor_cnpj);
    }

    public function test_rejeita_cliente_de_outro_escritorio_sem_persistir(): void
    {
        $otherOffice = Office::factory()->create();
        $otherClient = Client::factory()->forOffice($otherOffice)->create();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cliente não pertence ao escritório ativo.');
        try {
            app(PnrRenunciationProjectionService::class)->projectHistory(
                $this->office,
                $otherClient,
                FiscalSourceProvenance::SerproTrial->value,
                ['content' => [], 'number' => 0, 'last' => true, 'totalElements' => 0],
            );
        } finally {
            $this->assertSame(0, FiscalPnrRenunciation::query()->withoutGlobalScopes()->count());
        }
    }

    public function test_rejeita_proveniencia_simulada_antes_de_persistir(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Fonte PNR não verificável');
        try {
            app(PnrRenunciationProjectionService::class)->projectHistory(
                $this->office,
                $this->client,
                'SIMULATED',
                ['content' => [], 'number' => 0, 'last' => true, 'totalElements' => 0],
            );
        } finally {
            $this->assertSame(0, FiscalPnrRenunciation::query()->withoutGlobalScopes()->count());
        }
    }

    public function test_rejeita_renuncia_de_cnpj_diferente_sem_persistir(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('CNPJ retornado pela renúncia não pertence');
        try {
            app(PnrRenunciationProjectionService::class)->projectHistory(
                $this->office,
                $this->client,
                FiscalSourceProvenance::SerproTrial->value,
                [
                    'content' => [['id' => 42, 'cnpjRenunciada' => '04252011000110', 'dataRenuncia' => 1_700_000_000_000]],
                    'number' => 0,
                    'last' => true,
                    'totalElements' => 1,
                ],
            );
        } finally {
            $this->assertSame(0, FiscalPnrRenunciation::query()->withoutGlobalScopes()->count());
        }
    }

    public function test_projeta_situacao_sem_persistir_mensagem_remota(): void
    {
        $projection = app(PnrRenunciationProjectionService::class)->projectStatus(
            $this->office,
            $this->client,
            FiscalSourceProvenance::SerproReal->value,
            [
                'resultado' => true,
                'mensagemRetorno' => 'Mensagem remota que não deve virar resumo.',
                'renuncia' => ['id' => 42, 'cnpjRenunciada' => '11222333000181', 'dataRenuncia' => 1_700_000_000_000],
            ],
        );

        $this->assertNotNull($projection);
        $this->assertSame(['status_approved' => true, 'status_has_renunciation' => true], $projection->summary_sanitized);
        $this->assertSame(FiscalSourceProvenance::SerproReal, $projection->source_provenance);
        $this->assertStringNotContainsString('Mensagem remota', json_encode($projection->toPublicArray(), JSON_THROW_ON_ERROR));
    }

    public function test_guarda_comprovante_no_cofre_uma_unica_vez_e_omite_referencias_publicas(): void
    {
        $vault = Mockery::mock(SecureObjectStore::class);
        $bytes = "%PDF-1.7\n";
        $sha256 = hash('sha256', $bytes);
        $vault->shouldReceive('put')
            ->once()
            ->with($bytes, PnrRenunciationProjectionService::receiptAad($this->office->id, $this->client->id, 42, $sha256))
            ->andReturn('01JPNRRENUNCIATIONRECEIPT1');
        $this->app->instance(SecureObjectStore::class, $vault);
        $service = app(PnrRenunciationProjectionService::class);

        $service->projectHistory($this->office, $this->client, FiscalSourceProvenance::SerproTrial->value, [
            'content' => [['id' => 42, 'cnpjRenunciada' => '11222333000181', 'dataRenuncia' => 1_700_000_000_000]],
            'number' => 0,
            'last' => true,
            'totalElements' => 1,
        ]);

        $first = $service->projectReceipt($this->office, $this->client, 42, FiscalSourceProvenance::SerproTrial->value, base64_encode($bytes));
        $second = $service->projectReceipt($this->office, $this->client, 42, FiscalSourceProvenance::SerproTrial->value, base64_encode($bytes));

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, FiscalPnrRenunciation::query()->withoutGlobalScopes()->count());
        $public = $second->fresh()->toPublicArray();
        $this->assertSame(['mime_type' => 'application/pdf', 'byte_size' => strlen($bytes), 'observed_at' => $second->receipt_observed_at?->toIso8601String()], $public['receipt']);
        $this->assertArrayNotHasKey('receipt_vault_object_id', $public);
        $this->assertArrayNotHasKey('receipt_sha256', $public);
    }

    public function test_rejeita_comprovante_acima_do_limite_antes_do_cofre(): void
    {
        config(['fiscal_monitoring.evidence.max_bytes' => 8]);
        $vault = Mockery::mock(SecureObjectStore::class);
        $vault->shouldNotReceive('put');
        $this->app->instance(SecureObjectStore::class, $vault);
        $service = app(PnrRenunciationProjectionService::class);
        $service->projectHistory($this->office, $this->client, FiscalSourceProvenance::SerproTrial->value, [
            'content' => [['id' => 42, 'cnpjRenunciada' => '11222333000181', 'dataRenuncia' => 1_700_000_000_000]],
            'number' => 0,
            'last' => true,
            'totalElements' => 1,
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Comprovante PNR excede limite de 8 bytes.');
        $service->projectReceipt(
            $this->office,
            $this->client,
            42,
            FiscalSourceProvenance::SerproTrial->value,
            base64_encode('%PDF-1.7\n'),
        );
    }
}

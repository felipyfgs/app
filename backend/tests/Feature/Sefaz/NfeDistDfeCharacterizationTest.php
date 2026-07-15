<?php

namespace Tests\Feature\Sefaz;

use App\Contracts\SefazDistDfeClient;
use App\Domain\Sefaz\DistDfeDocumentDto;
use App\Domain\Sefaz\DistDfePageDto;
use App\Enums\CaptureChannel;
use App\Enums\DocumentDirection;
use App\Enums\FiscalRole;
use App\Enums\OfficeRole;
use App\Enums\SyncCursorStatus;
use App\Exceptions\Adn\DocumentDecodeException;
use App\Jobs\SyncSefazDistDfeJob;
use App\Models\ChannelSyncCursor;
use App\Models\Client;
use App\Models\Establishment;
use App\Models\NfeDocument;
use App\Models\Office;
use App\Models\User;
use App\Services\Certificates\CredentialService;
use App\Services\Sefaz\DistDfePageProcessor;
use App\Support\AutXmlFeature;
use App\Support\CurrentOffice;
use Database\Factories\EstablishmentFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Caracterização do NFE_DISTDFE atual antes da refatoração autXML.
 * Garante que o canal do cliente (TAKER/IN) e a flag do escritório
 * permanecem isolados.
 */
class NfeDistDfeCharacterizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_feature_flag_distdfe_default_de_config_e_false(): void
    {
        // Ambiente local pode ligar SEFAZ_DISTDFE_ENABLED; o default do arquivo de config é false.
        $default = filter_var(env('SEFAZ_DISTDFE_ENABLED', false), FILTER_VALIDATE_BOOL);
        config(['sefaz.distdfe_enabled' => false]);
        $this->assertFalse(CaptureChannel::NfeDistDfe->isEnabled());
        // Caracteriza o contrato do job com flag off (independente do .env do host).
        $this->assertFalse((bool) config('sefaz.distdfe_enabled'));
        unset($default);
    }

    public function test_autxml_desligado_por_padrao_e_allowlist_vazia_bloqueia(): void
    {
        $this->assertFalse((bool) config('sefaz.autxml.enabled'));
        $this->assertFalse((bool) config('sefaz.autxml.kill_switch'));
        $this->assertSame([], config('sefaz.autxml.office_allowlist'));
        $this->assertFalse(CaptureChannel::NfeAutXmlDistDfe->isEnabled());
        $this->assertFalse(AutXmlFeature::isGloballyEnabled());
        $this->assertFalse(AutXmlFeature::isOfficeAllowed(1));
    }

    public function test_job_distdfe_no_op_com_flag_desligada(): void
    {
        config(['sefaz.distdfe_enabled' => false]);

        $cursor = $this->seedCursor();
        (new SyncSefazDistDfeJob($cursor->id, 'TEST'))->handle(
            app(SefazDistDfeClient::class),
            app(DistDfePageProcessor::class),
            app(CredentialService::class),
        );

        $this->assertSame(0, $cursor->fresh()->last_nsu);
        $this->assertSame(SyncCursorStatus::Idle, $cursor->fresh()->status);
    }

    public function test_pagina_138_persiste_procnfe_como_taker_in_e_avanca_nsu(): void
    {
        config(['sefaz.auto_ciencia_enabled' => false]);

        $cursor = $this->seedCursor();
        $establishment = $cursor->establishment;
        $chave = '35260711222333000181550010000000011234567901';
        $xml = $this->sampleProcNfe('99888777000166', '11222333000181', $chave);
        $b64 = base64_encode(gzencode($xml));

        $page = new DistDfePageDto(
            cStat: '138',
            xMotivo: 'Documento(s) localizado(s)',
            ultNsu: 10,
            maxNsu: 100,
            documents: [
                new DistDfeDocumentDto(
                    nsu: 10,
                    schema: 'procNFe_v4.00.xsd',
                    contentBase64: $b64,
                    schemaFamily: 'procNFe',
                ),
            ],
        );

        $result = app(DistDfePageProcessor::class)->process($cursor, $establishment, $page);

        $this->assertSame(1, $result['documents']);
        $this->assertSame(10, $result['advanced_to']);
        $this->assertSame(10, $cursor->fresh()->last_nsu);
        $this->assertDatabaseHas('document_interests', [
            'establishment_id' => $establishment->id,
            'nsu' => 10,
            'channel' => CaptureChannel::NfeDistDfe->value,
            'fiscal_role' => FiscalRole::Taker->value,
        ]);
        $this->assertDatabaseHas('nfe_documents', [
            'office_id' => $cursor->office_id,
            'access_key' => $chave,
            'direction' => DocumentDirection::In->value,
        ]);
    }

    public function test_pagina_137_nao_avanca_alem_do_ultnsu_e_agenda_quiet(): void
    {
        $cursor = $this->seedCursor(lastNsu: 5);
        $page = new DistDfePageDto(
            cStat: '137',
            xMotivo: 'Nenhum documento localizado',
            ultNsu: 5,
            maxNsu: 5,
            documents: [],
        );

        $result = app(DistDfePageProcessor::class)->process($cursor, $cursor->establishment, $page);

        $this->assertSame(0, $result['documents']);
        $this->assertSame(5, $result['advanced_to']);
        $this->assertSame(SyncCursorStatus::Idle, $cursor->fresh()->status);
        $this->assertNotNull($cursor->fresh()->next_sync_at);
    }

    public function test_cstat_656_bloqueia_sem_avancar_nsu(): void
    {
        $cursor = $this->seedCursor(lastNsu: 7);
        $page = new DistDfePageDto(
            cStat: '656',
            xMotivo: 'Consumo Indevido',
            ultNsu: 7,
            maxNsu: 100,
            documents: [],
        );

        $result = app(DistDfePageProcessor::class)->process($cursor, $cursor->establishment, $page);

        $this->assertSame(0, $result['documents']);
        $this->assertSame(7, $result['advanced_to']);
        $this->assertSame(SyncCursorStatus::Blocked, $cursor->fresh()->status);
        $this->assertSame('656', $cursor->fresh()->last_cstat);
    }

    public function test_falha_de_decode_nao_avanca_nsu(): void
    {
        $cursor = $this->seedCursor();
        $page = new DistDfePageDto(
            cStat: '138',
            xMotivo: 'Documento(s) localizado(s)',
            ultNsu: 1,
            maxNsu: 1,
            documents: [
                new DistDfeDocumentDto(
                    nsu: 1,
                    schema: 'procNFe_v4.00.xsd',
                    contentBase64: '@@@not-valid-base64@@@',
                    schemaFamily: 'procNFe',
                ),
            ],
        );

        try {
            app(DistDfePageProcessor::class)->process($cursor, $cursor->establishment, $page);
            $this->fail('esperava DocumentDecodeException');
        } catch (DocumentDecodeException) {
            // expected
        }

        $this->assertSame(0, $cursor->fresh()->last_nsu);
        $this->assertGreaterThanOrEqual(1, $cursor->fresh()->consecutive_decode_failures);
        $this->assertSame(0, NfeDocument::query()->where('office_id', $cursor->office_id)->count());
    }

    public function test_canal_cliente_nao_usa_cursor_de_escritorio(): void
    {
        $this->assertFalse(CaptureChannel::NfeDistDfe->usesOfficeCursor());
        $this->assertTrue(CaptureChannel::NfeAutXmlDistDfe->usesOfficeCursor());
        $this->assertTrue(CaptureChannel::NfeDistDfe->usesNsuCursor());
        $this->assertTrue(CaptureChannel::NfeAutXmlDistDfe->usesNsuCursor());
    }

    private function seedCursor(int $lastNsu = 0): ChannelSyncCursor
    {
        $office = Office::factory()->create();
        $user = User::factory()->forOffice($office, OfficeRole::Operator)->withTwoFactorConfirmed()->create();
        app(CurrentOffice::class)->resolve($user);

        $client = Client::factory()->forOffice($office)->create(['root_cnpj' => '11222333']);
        $cnpj = EstablishmentFactory::cnpjWithRoot('11222333');
        $establishment = Establishment::factory()->forClient($client, $cnpj)->create();

        return ChannelSyncCursor::query()->create([
            'office_id' => $office->id,
            'establishment_id' => $establishment->id,
            'environment' => 'production',
            'source' => 'SEFAZ',
            'channel' => CaptureChannel::NfeDistDfe,
            'last_nsu' => $lastNsu,
            'status' => SyncCursorStatus::Idle,
        ]);
    }

    private function sampleProcNfe(string $emitCnpj, string $destCnpj, string $chave): string
    {
        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<nfeProc xmlns="http://www.portalfiscal.inf.br/nfe">
  <NFe>
    <infNFe Id="NFe{$chave}">
      <ide>
        <mod>55</mod>
        <serie>1</serie>
        <nNF>1</nNF>
        <dhEmi>2026-07-01T10:00:00-03:00</dhEmi>
      </ide>
      <emit>
        <CNPJ>{$emitCnpj}</CNPJ>
        <xNome>Emitente</xNome>
      </emit>
      <dest>
        <CNPJ>{$destCnpj}</CNPJ>
        <xNome>Destinatario</xNome>
      </dest>
      <total><ICMSTot><vNF>100.00</vNF></ICMSTot></total>
    </infNFe>
  </NFe>
  <protNFe><infProt><chNFe>{$chave}</chNFe><cStat>100</cStat></infProt></protNFe>
</nfeProc>
XML;
    }
}

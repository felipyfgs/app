<?php

namespace Tests\Unit\Integra\Sitfis;

use App\Enums\FiscalSituation;
use App\Services\Integra\Sitfis\SitfisReportParser;
use Tests\TestCase;

class SitfisReportParserTest extends TestCase
{
    private SitfisReportParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new SitfisReportParser;
    }

    public function test_pendencias_reconhecidas_viram_findings_com_rastreabilidade(): void
    {
        $result = $this->parser->parse([
            'layoutVersion' => '1.0',
            'dataConsulta' => '2026-07-15',
            'pendencias' => [
                [
                    'codigo' => 'PGDAS_ATRASO',
                    'descricao' => 'PGDAS-D em atraso',
                    'detalhe' => 'Competência 2026-05',
                    'vencimento' => '2026-06-20',
                ],
            ],
        ]);

        $this->assertTrue($result->layoutRecognized);
        $this->assertFalse($result->contractChanged);
        $this->assertSame(FiscalSituation::Pending, $result->situation);
        $this->assertFalse($result->claimsNegativeCertificate);
        $this->assertCount(1, $result->findings);
        $this->assertSame('PGDAS_ATRASO', $result->findings[0]['code']);
        $this->assertTrue($result->findings[0]['creates_pending']);
        $this->assertFalse($result->normalized['is_negative_certificate']);
    }

    public function test_ausencia_de_item_nao_e_certidao_negativa(): void
    {
        $result = $this->parser->parse([
            'layoutVersion' => '1.0',
            'dataConsulta' => '2026-07-15',
            'pendencias' => [],
        ]);

        $this->assertTrue($result->layoutRecognized);
        $this->assertSame(FiscalSituation::Unknown, $result->situation);
        $this->assertNotSame(FiscalSituation::UpToDate, $result->situation);
        $this->assertFalse($result->claimsNegativeCertificate);
        $this->assertFalse($result->normalized['is_negative_certificate']);
        $this->assertSame('SITFIS_NO_RECOGNIZED_ITEMS', $result->findings[0]['code']);
        $this->assertStringContainsString('certidão', mb_strtolower($result->findings[0]['detail'] ?? ''));
    }

    public function test_layout_novo_mantem_attention_e_nao_omite_como_regular(): void
    {
        $result = $this->parser->parse([
            'layoutVersion' => '9.9',
            '__unknown_layout' => true,
            'secaoNovaOficial' => ['foo' => 'bar'],
            'pendencias' => [
                ['codigo' => 'X1', 'descricao' => 'Item ainda legível'],
            ],
        ]);

        $this->assertFalse($result->layoutRecognized);
        $this->assertTrue($result->contractChanged);
        $this->assertSame(FiscalSituation::Attention, $result->situation);
        $this->assertFalse($result->claimsNegativeCertificate);

        $codes = array_column($result->findings, 'code');
        $this->assertContains('SITFIS_LAYOUT_UNKNOWN', $codes);
        $this->assertContains('X1', $codes);
    }
}

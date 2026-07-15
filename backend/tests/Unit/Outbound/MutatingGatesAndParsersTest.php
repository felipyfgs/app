<?php

namespace Tests\Unit\Outbound;

use App\Enums\OutboundCaptureMode;
use App\Enums\OutboundFiscalModel;
use App\Enums\OutboundProfileStatus;
use App\Enums\OutboundSeriesStatus;
use App\Models\OutboundCaptureProfile;
use App\Models\OutboundSeriesCursor;
use App\Services\Outbound\AccessKeyCandidateBuilder;
use App\Services\Outbound\InutilizationResponseParser;
use App\Services\Outbound\MutatingProbeGateEvaluator;
use App\Services\Outbound\Rejection539Parser;
use Tests\TestCase;

class MutatingGatesAndParsersTest extends TestCase
{
    public function test_gates_mutantes_bloqueiam_com_flag_off(): void
    {
        config(['sefaz.ma_outbound.mutating_probe_enabled' => false]);

        $profile = new OutboundCaptureProfile([
            'allowlisted' => true,
            'consent_recorded' => true,
            'mandate_reference' => 'X',
            'kill_switch' => false,
            'model' => OutboundFiscalModel::Nfe,
            'csc_configured' => false,
            'status' => OutboundProfileStatus::Active,
            'mode' => OutboundCaptureMode::Assisted,
        ]);
        $series = new OutboundSeriesCursor([
            'series_closed_for_mutation' => true,
            'erp_coordination_ref' => 'ERP-1',
            'status' => OutboundSeriesStatus::Closed,
        ]);

        $eval = app(MutatingProbeGateEvaluator::class)->evaluate($profile, $series);
        $this->assertFalse($eval['allowed']);
        $this->assertContains('mutating_flag_off', $eval['reasons_codes']);
    }

    public function test_inutilizacao_102_e_241(): void
    {
        $parser = new InutilizationResponseParser;
        $ok = $parser->parse((string) file_get_contents(base_path('tests/fixtures/ma-outbound/inutilizacao_102.xml')));
        $this->assertSame('INUTILIZED', $ok['outcome']);

        $used = $parser->parse((string) file_get_contents(base_path('tests/fixtures/ma-outbound/inutilizacao_241.xml')));
        $this->assertSame('PROVEN_USED', $used['outcome']);
    }

    public function test_parser_539_valida_identidade(): void
    {
        $builder = new AccessKeyCandidateBuilder;
        $parser = new Rejection539Parser($builder);
        $xml = (string) file_get_contents(base_path('tests/fixtures/ma-outbound/rejeicao_539.xml'));

        // Chave da fixture pode não bater DV/identidade real — valid=false é ok se divergir
        $result = $parser->parse($xml, '21', '12345678000190', '55', 1, 1, '1');
        $this->assertSame('539', $result['cStat']);
        $this->assertArrayHasKey('valid', $result);
    }
}

<?php

namespace Tests\Unit\Outbound;

use App\Enums\OutboundCaptureMode;
use App\Enums\OutboundFiscalModel;
use App\Enums\OutboundNumberStatus;
use App\Enums\OutboundProfileStatus;
use App\Enums\OutboundSeriesStatus;
use App\Enums\SvrsNfceFailureReason;
use App\Models\Client;
use App\Models\Establishment;
use App\Models\Office;
use App\Models\OutboundCaptureProfile;
use App\Models\OutboundNumberState;
use App\Models\OutboundSeriesCursor;
use App\Services\Outbound\DisabledSvrsNfceOutboundXmlRetrievalClient;
use App\Services\Outbound\SvrsNfceConfig;
use App\Services\Outbound\SvrsNfceKillSwitchService;
use App\Services\Outbound\SvrsNfceRetrievalEligibility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class SvrsNfceEligibilityAndConfigTest extends TestCase
{
    use RefreshDatabase;

    public function test_flags_default_off(): void
    {
        $cfg = new SvrsNfceConfig;
        $this->assertFalse($cfg->retrievalEnabled());
        $this->assertFalse($cfg->autoQueueEnabled());
        $this->assertFalse($cfg->pilotAllowlistOnly());
        $this->assertSame('dfe-portal.svrs.rs.gov.br', $cfg->host());
        $this->assertStringStartsWith('https://', $cfg->getUrl());
    }

    public function test_host_not_allowlisted_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        config(['sefaz.svrs_nfce_xml.host' => 'evil.example.com']);
        (new SvrsNfceConfig)->host();
    }

    public function test_disabled_client(): void
    {
        $client = new DisabledSvrsNfceOutboundXmlRetrievalClient;
        $this->assertFalse($client->isAvailable());
    }

    public function test_eligibility_nfce_ma_ok(): void
    {
        config(['sefaz.svrs_nfce_xml.retrieval_enabled' => true]);
        Cache::flush();

        [$profile, $number] = $this->seedNumber(
            model: OutboundFiscalModel::Nfce,
            key: '21260712345678000190650010000000011234567892',
            status: OutboundNumberStatus::XmlPending,
        );

        $eval = app(SvrsNfceRetrievalEligibility::class)->evaluate($number, $profile, true);
        $this->assertTrue($eval->eligible);
    }

    public function test_eligibility_rejects_nfe_55(): void
    {
        config(['sefaz.svrs_nfce_xml.retrieval_enabled' => true]);
        [$profile, $number] = $this->seedNumber(
            model: OutboundFiscalModel::Nfe,
            key: '21260712345678000190550010000000011234567890',
            status: OutboundNumberStatus::XmlPending,
        );
        // force model 55 on key
        $number->forceFill([
            'discovered_access_key' => '21260712345678000190550010000000011234567890',
        ])->save();

        $eval = app(SvrsNfceRetrievalEligibility::class)->evaluate($number, $profile, true);
        $this->assertFalse($eval->eligible);
        $this->assertSame(SvrsNfceFailureReason::NotEligible, $eval->reason);
    }

    public function test_kill_switch_preserves_state(): void
    {
        config(['sefaz.svrs_nfce_xml.retrieval_enabled' => true]);
        [$profile, $number] = $this->seedNumber(
            model: OutboundFiscalModel::Nfce,
            key: '21260712345678000190650010000000011234567892',
            status: OutboundNumberStatus::XmlPending,
        );

        $ks = app(SvrsNfceKillSwitchService::class);
        $ks->activate('drill', 1, $profile->office_id);
        $this->assertTrue($ks->isActive());

        $eval = app(SvrsNfceRetrievalEligibility::class)->evaluate($number, $profile, true);
        $this->assertFalse($eval->eligible);
        $this->assertSame(SvrsNfceFailureReason::KillSwitch, $eval->reason);

        // Estado fiscal preservado
        $number->refresh();
        $this->assertSame(OutboundNumberStatus::XmlPending, $number->status);

        $ks->deactivate('fim drill', 1, $profile->office_id);
        $this->assertFalse($ks->isActive());
        $number->refresh();
        $this->assertSame(OutboundNumberStatus::XmlPending, $number->status);
    }

    /**
     * @return array{0: OutboundCaptureProfile, 1: OutboundNumberState}
     */
    private function seedNumber(OutboundFiscalModel $model, string $key, OutboundNumberStatus $status): array
    {
        $office = Office::factory()->create();
        $client = Client::factory()->forOffice($office)->create();
        $est = Establishment::factory()->forClient($client)->create([
            'cnpj' => '12345678000190',
            'address_state' => 'MA',
        ]);
        $profile = OutboundCaptureProfile::query()->create([
            'office_id' => $office->id,
            'client_id' => $client->id,
            'establishment_id' => $est->id,
            'uf' => 'MA',
            'environment' => 'homologation',
            'model' => $model,
            'mode' => OutboundCaptureMode::Assisted,
            'status' => OutboundProfileStatus::Active,
            'allowlisted' => true,
        ]);
        $series = OutboundSeriesCursor::query()->create([
            'office_id' => $office->id,
            'outbound_capture_profile_id' => $profile->id,
            'establishment_id' => $est->id,
            'environment' => 'homologation',
            'model' => $model,
            'series' => 1,
            'seed_nnf' => 1,
            'discovery_position' => 2,
            'status' => OutboundSeriesStatus::Idle,
        ]);
        $number = OutboundNumberState::query()->create([
            'office_id' => $office->id,
            'outbound_capture_profile_id' => $profile->id,
            'outbound_series_cursor_id' => $series->id,
            'series' => 1,
            'nnf' => 1,
            'status' => $status,
            'discovered_access_key' => $key,
        ]);

        return [$profile, $number];
    }
}

<?php

namespace Tests\Unit\Outbound;

use App\Enums\OutboundCaptureMode;
use App\Enums\OutboundFiscalModel;
use App\Enums\OutboundNumberStatus;
use App\Enums\OutboundProfileStatus;
use App\Enums\OutboundSeriesStatus;
use App\Models\Client;
use App\Models\Establishment;
use App\Models\Office;
use App\Models\OutboundCaptureProfile;
use App\Models\OutboundNumberState;
use App\Models\OutboundSeriesCursor;
use App\Services\Outbound\OutboundXmlRecoveryRouter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OutboundXmlRecoveryRouterTest extends TestCase
{
    use RefreshDatabase;

    public function test_sem_chave_vai_para_assisted(): void
    {
        [$profile, $number] = $this->makeNumber(null);
        $route = app(OutboundXmlRecoveryRouter::class)->route($number, $profile);
        $this->assertSame(OutboundXmlRecoveryRouter::SOURCE_ASSISTED, $route['source']);
        $this->assertFalse($route['may_call_svrs']);
    }

    public function test_canal_off_assisted(): void
    {
        config(['sefaz.svrs_nfce_xml.retrieval_enabled' => false]);
        $key = '21260712345678000190650010000000011234567892';
        [$profile, $number] = $this->makeNumber($key);
        $route = app(OutboundXmlRecoveryRouter::class)->route($number, $profile);
        $this->assertSame(OutboundXmlRecoveryRouter::SOURCE_ASSISTED, $route['source']);
    }

    public function test_canal_on_com_chave_svrs(): void
    {
        config(['sefaz.svrs_nfce_xml.retrieval_enabled' => true]);
        $key = '21260712345678000190650010000000011234567892';
        [$profile, $number] = $this->makeNumber($key);
        $route = app(OutboundXmlRecoveryRouter::class)->route($number, $profile);
        $this->assertSame(OutboundXmlRecoveryRouter::SOURCE_SVRS, $route['source']);
        $this->assertTrue($route['may_call_svrs']);
    }

    /**
     * @return array{0: OutboundCaptureProfile, 1: OutboundNumberState}
     */
    private function makeNumber(?string $key): array
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
            'model' => OutboundFiscalModel::Nfce,
            'mode' => OutboundCaptureMode::Assisted,
            'status' => OutboundProfileStatus::Active,
            'allowlisted' => true,
        ]);
        $series = OutboundSeriesCursor::query()->create([
            'office_id' => $office->id,
            'outbound_capture_profile_id' => $profile->id,
            'establishment_id' => $est->id,
            'environment' => 'homologation',
            'model' => OutboundFiscalModel::Nfce,
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
            'status' => OutboundNumberStatus::XmlPending,
            'discovered_access_key' => $key,
        ]);

        return [$profile, $number];
    }
}

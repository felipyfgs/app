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
use App\Services\Outbound\SvrsNfe55RetrievalEligibility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class SvrsNfe55EligibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_nfe55_ma_ok(): void
    {
        config(['sefaz.svrs_nfe55_xml.retrieval_enabled' => true]);
        Cache::flush();

        // Chave 55 com DV válido (calculado)
        $key = $this->buildKey55('12345678000190', 1, 1);
        [$profile, $number] = $this->makeNumber(OutboundFiscalModel::Nfe, $key);

        $eval = app(SvrsNfe55RetrievalEligibility::class)->evaluate($number, $profile, true);
        $this->assertTrue($eval->eligible, $eval->sanitizedDetail ?? '');
    }

    public function test_rejects_model_65(): void
    {
        config(['sefaz.svrs_nfe55_xml.retrieval_enabled' => true]);
        $key = '21260712345678000190650010000000011234567892';
        [$profile, $number] = $this->makeNumber(OutboundFiscalModel::Nfce, $key);

        $eval = app(SvrsNfe55RetrievalEligibility::class)->evaluate($number, $profile, true);
        $this->assertFalse($eval->eligible);
        $this->assertSame(SvrsNfceFailureReason::NotEligible, $eval->reason);
    }

    public function test_channel_disabled(): void
    {
        config(['sefaz.svrs_nfe55_xml.retrieval_enabled' => false]);
        $key = $this->buildKey55('12345678000190', 1, 1);
        [$profile, $number] = $this->makeNumber(OutboundFiscalModel::Nfe, $key);

        $eval = app(SvrsNfe55RetrievalEligibility::class)->evaluate($number, $profile, true);
        $this->assertFalse($eval->eligible);
        $this->assertSame(SvrsNfceFailureReason::ChannelDisabled, $eval->reason);
    }

    /**
     * @return array{0: OutboundCaptureProfile, 1: OutboundNumberState}
     */
    private function makeNumber(OutboundFiscalModel $model, string $key): array
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
            'status' => OutboundNumberStatus::XmlPending,
            'discovered_access_key' => $key,
        ]);

        return [$profile, $number];
    }

    private function buildKey55(string $cnpj, int $series, int $nnf): string
    {
        $cnpj = str_pad(preg_replace('/\D/', '', $cnpj) ?? '', 14, '0', STR_PAD_LEFT);
        $body = '21'.'2607'.$cnpj.'55'
            .str_pad((string) $series, 3, '0', STR_PAD_LEFT)
            .str_pad((string) $nnf, 9, '0', STR_PAD_LEFT)
            .'1'
            .'12345678';
        $weights = [2, 3, 4, 5, 6, 7, 8, 9];
        $sum = 0;
        $w = 0;
        for ($i = 42; $i >= 0; $i--) {
            $sum += (int) $body[$i] * $weights[$w % 8];
            $w++;
        }
        $mod = $sum % 11;
        $dv = $mod === 0 || $mod === 1 ? 0 : 11 - $mod;

        return $body.(string) $dv;
    }
}

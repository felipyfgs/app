<?php

namespace Tests\Feature\Outbound;

use App\Enums\OfficeRole;
use App\Enums\OutboundCaptureMode;
use App\Enums\OutboundFiscalModel;
use App\Enums\OutboundProfileStatus;
use App\Enums\OutboundRetrievalOrigin;
use App\Enums\OutboundUrgencyBand;
use App\Enums\SvrsNfceRecoveryStatus;
use App\Jobs\PlanOutboundDeadlineScheduleJob;
use App\Models\Client;
use App\Models\Establishment;
use App\Models\MaOutboundRetrievalRequest;
use App\Models\Office;
use App\Models\OutboundCaptureProfile;
use App\Models\User;
use App\Services\Outbound\OutboundDeadlinePlannerService;
use App\Support\CurrentOffice;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * 13.11 — planner/API/UI payloads nunca expõem PFX, PEM, senha ou chave completa sem máscara.
 */
class OutboundDeadlineSecretsAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_planner_logs_e_api_sem_segredos(): void
    {
        Log::spy();
        config([
            'outbound_deadline.enabled' => true,
            'outbound_deadline.planner_enabled' => true,
        ]);

        $office = Office::factory()->create();
        $client = Client::factory()->forOffice($office)->create();
        $est = Establishment::factory()->forClient($client)->create([
            'office_id' => $office->id,
            'address_state' => 'MA',
        ]);
        $profile = OutboundCaptureProfile::query()->create([
            'office_id' => $office->id,
            'client_id' => $client->id,
            'establishment_id' => $est->id,
            'uf' => 'MA',
            'environment' => 'homologation',
            'model' => OutboundFiscalModel::Nfe,
            'mode' => OutboundCaptureMode::Assisted,
            'status' => OutboundProfileStatus::SeedReady,
        ]);

        $fullKey = '35260799888777000166550010000000011234567999';
        MaOutboundRetrievalRequest::query()->create([
            'office_id' => $office->id,
            'outbound_capture_profile_id' => $profile->id,
            'establishment_id' => $est->id,
            'environment' => 'homologation',
            'model' => OutboundFiscalModel::Nfe,
            'direction' => 'OUT',
            'competence' => '2026-07',
            'status' => 'PENDING',
            'mode' => OutboundCaptureMode::Automatic,
            'origin' => OutboundRetrievalOrigin::SvrsPortalByKey,
            'access_key' => $fullKey,
            'recovery_status' => SvrsNfceRecoveryStatus::Eligible,
            'urgency_band' => OutboundUrgencyBand::Planned,
            'created_at' => now()->subDays(2),
        ]);

        PlanOutboundDeadlineScheduleJob::dispatchSync($office->id);
        app(OutboundDeadlinePlannerService::class)->plan($office->id, CarbonImmutable::now('UTC'));

        $admin = User::factory()->forOffice($office, OfficeRole::Admin)->withTwoFactorConfirmed()->create();
        $this->actingAs($admin);
        app(CurrentOffice::class)->resolve($admin);

        $endpoints = [
            '/api/v1/outbound/deadline/competence?competence=2026-07',
            '/api/v1/outbound/deadline/capacity?competence=2026-07',
            '/api/v1/outbound/deadline/pending?competence=2026-07',
            '/api/v1/outbound/deadline/metrics?competence=2026-07',
        ];

        foreach ($endpoints as $url) {
            $res = $this->getJson($url)->assertOk();
            $body = $res->getContent();
            $this->assertStringNotContainsString('BEGIN CERTIFICATE', $body);
            $this->assertStringNotContainsString('PRIVATE KEY', $body);
            $this->assertStringNotContainsString('pfx', strtolower($body));
            $this->assertStringNotContainsString('password', strtolower($body));
            $this->assertStringNotContainsString($fullKey, $body);
            $this->assertStringNotContainsString('vault_object_id', $body);
        }
    }
}

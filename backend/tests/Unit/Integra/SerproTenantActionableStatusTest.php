<?php

namespace Tests\Unit\Integra;

use App\Enums\OfficeSerproOnboardingStatus;
use App\Enums\SerproEnvironment;
use App\Models\Office;
use App\Models\OfficeSerproOnboardingState;
use App\Services\Integra\SerproTenantActionableStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SerproTenantActionableStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_technical_error_is_sanitized_for_tenant(): void
    {
        $office = Office::factory()->create(['name' => 'Office']);
        OfficeSerproOnboardingState::query()->create([
            'office_id' => $office->id,
            'environment' => SerproEnvironment::Trial,
            'status' => OfficeSerproOnboardingStatus::TechnicalError,
            'technical_code' => 'OAUTH_MTLS_FAILED',
            'technical_message' => 'consumer_secret invalid + Bearer abc.def.ghi',
            'actionable_code' => 'PLATFORM_UNAVAILABLE',
            'actionable_message' => 'Integração SERPRO temporariamente indisponível. Tente novamente mais tarde.',
            'correlation_id' => 'corr-123',
        ]);

        $status = app(SerproTenantActionableStatusService::class)->forOffice($office, SerproEnvironment::Trial);

        $this->assertSame('PLATFORM_UNAVAILABLE', $status['actionable'][0]['code'] ?? null);
        $this->assertStringNotContainsString('Bearer', $status['actionable'][0]['message'] ?? '');
        $this->assertStringNotContainsString('consumer_secret', $status['actionable'][0]['message'] ?? '');
        $this->assertSame('corr-123', $status['correlation_id']);
        // Tenant onboarding projection sem technical_message
        $this->assertArrayNotHasKey('technical', $status['onboarding']);
    }

    public function test_platform_diagnosis_separate_from_tenant(): void
    {
        $platform = app(SerproTenantActionableStatusService::class)->platformDiagnosis(SerproEnvironment::Trial);

        $this->assertArrayHasKey('environment', $platform);
        $this->assertArrayHasKey('kill_switch', $platform);
        $this->assertArrayNotHasKey('consumer_secret', $platform);
    }
}

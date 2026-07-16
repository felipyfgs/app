<?php

namespace Tests\Unit\Serpro;

use App\Enums\SerproEnvironment;
use App\Models\Office;
use App\Models\SerproReadinessRun;
use App\Services\Serpro\SerproReadinessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class SerproReadinessServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_global_readiness_offline_no_live_and_persists(): void
    {
        $this->assertTrue(Schema::hasTable('serpro_readiness_runs'));

        $svc = app(SerproReadinessService::class);
        $run = $svc->evaluateGlobal(SerproEnvironment::Trial, persist: true, trigger: 'TEST');

        $this->assertInstanceOf(SerproReadinessRun::class, $run);
        $this->assertFalse($run->live_evidence);
        $this->assertNotNull($run->expires_at);
        $this->assertNotEmpty($run->evidences);
        $payload = $run->toSanitizedArray();
        $this->assertArrayHasKey('result', $payload);
        $this->assertSame('OFFLINE', $payload['summary']['evidence_mode'] ?? null);
        $this->assertStringContainsString('não emite token', (string) ($payload['summary']['note'] ?? ''));

        // Sem segredos
        $json = json_encode($payload);
        $this->assertStringNotContainsString('BEGIN CERTIFICATE', (string) $json);
        $this->assertStringNotContainsString('consumer_secret', (string) $json);
    }

    public function test_office_readiness_without_persist_is_array_and_sanitized(): void
    {
        $office = Office::factory()->create(['slug' => 'real-office-'.uniqid()]);
        $svc = app(SerproReadinessService::class);
        $payload = $svc->evaluateOffice($office, SerproEnvironment::Trial, persist: false);

        $this->assertIsArray($payload);
        $this->assertSame('OFFICE', $payload['scope']);
        $this->assertArrayHasKey('global', $payload);
        $this->assertArrayNotHasKey('issues', $payload['global']);
    }

    public function test_demo_office_fails_real_gate(): void
    {
        $office = Office::factory()->create(['slug' => 'demo-escritorio']);
        $svc = app(SerproReadinessService::class);
        $payload = $svc->evaluateOffice($office, SerproEnvironment::Trial, persist: false);

        $this->assertSame('FAIL', $payload['result']);
    }
}

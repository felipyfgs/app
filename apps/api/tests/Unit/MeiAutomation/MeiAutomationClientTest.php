<?php

namespace Tests\Unit\MeiAutomation;

use App\DTO\MeiAutomation\MeiAutomationJobRequest;
use App\Enums\MeiAutomationStatus;
use App\Services\MeiAutomation\MeiAutomationClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MeiAutomationClientTest extends TestCase
{
    public function test_creates_signed_job_without_leaking_contract_fields(): void
    {
        config()->set('mei_automation.base_url', 'http://mei.test');
        config()->set('mei_automation.hmac.key_id', 'laravel');
        config()->set('mei_automation.hmac.secret', 'shared-test-secret');
        Http::fake([
            'http://mei.test/v1/jobs' => Http::response([
                'id' => '0f82d5ec-d69f-4b2b-a2d6-b2c52e0e1b92',
                'operation_key' => 'fixture.health',
                'status' => 'QUEUED',
                'result' => null,
                'error' => null,
                'artifacts' => [],
                'action_type' => null,
            ], 202),
        ]);

        $result = app(MeiAutomationClient::class)->create(new MeiAutomationJobRequest(
            operationKey: 'fixture.health',
            idempotencyKey: 'fixture:12345678',
            requestFingerprint: str_repeat('a', 64),
            clientRef: 'opaque-client-fixture',
        ));

        self::assertSame(MeiAutomationStatus::Queued, $result->status);
        Http::assertSent(function (Request $request): bool {
            return $request->hasHeader('X-MEI-Signature')
                && $request->hasHeader('X-MEI-Nonce')
                && $request->url() === 'http://mei.test/v1/jobs';
        });
    }
}

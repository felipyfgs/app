<?php

namespace Tests\Feature\Activation;

use App\Enums\ActivationMethod;
use App\Enums\SubscriptionPlan;
use App\Models\Office;
use App\Models\User;
use App\Services\Activation\CreatePendingOfficeService;
use App\Services\Auth\RecentPasswordConfirmationGate;
use App\Support\LogSanitizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class ActivationSecurityTest extends TestCase
{
    use RefreshDatabase;

    private const VALID_CNPJ = '11222333000181';

    public function test_log_sanitizer_redige_campos_de_ativacao(): void
    {
        $redacted = LogSanitizer::redact([
            'temporary_password' => 'Abcd-Efgh-Ijkl-Mnop',
            'activation_url' => '/activate#token=deadbeef',
            'activation_token' => 'deadbeef',
            'password' => 'secret',
            'reconfirm_password' => 'secret',
            'token' => 'abc',
            'safe' => 'ok',
        ]);

        $this->assertSame('[redacted]', $redacted['temporary_password']);
        $this->assertSame('[redacted]', $redacted['activation_url']);
        $this->assertSame('[redacted]', $redacted['activation_token']);
        $this->assertSame('[redacted]', $redacted['password']);
        $this->assertSame('[redacted]', $redacted['reconfirm_password']);
        $this->assertSame('[redacted]', $redacted['token']);
        $this->assertSame('ok', $redacted['safe']);
    }

    public function test_resposta_create_tem_no_store(): void
    {
        Office::factory()->create();
        $admin = User::factory()->asPlatformAdmin()->create([
            'password' => bcrypt('admin-secret-12'),
        ]);
        $this->actingAs($admin);
        app(RecentPasswordConfirmationGate::class)->markConfirmed($admin);

        $this->actingAs($admin)
            ->postJson('/api/v1/platform/offices', [
                'name' => 'Sec Office',
                'profile' => [
                    'cnpj' => self::VALID_CNPJ,
                    'legal_name' => 'Sec LTDA',
                    'institutional_email' => 'c@sec.example',
                    'institutional_phone' => '11911112222',
                ],
                'plan' => SubscriptionPlan::Starter->value,
                'admin_name' => 'Sec Admin',
                'admin_email' => 'sec.admin@example',
                'method' => ActivationMethod::ManualLink->value,
                'idempotency_key' => 'sec-key-001',
            ])
            ->assertCreated()
            ->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_audit_context_nao_inclui_segredo_em_claro(): void
    {
        Office::factory()->create();
        $admin = User::factory()->asPlatformAdmin()->create();
        $this->actingAs($admin);

        Log::spy();

        $result = app(CreatePendingOfficeService::class)->create([
            'name' => 'Audit Office',
            'profile' => [
                'cnpj' => self::VALID_CNPJ,
                'legal_name' => 'Audit LTDA',
                'institutional_email' => 'c@audit.example',
                'institutional_phone' => '11933334444',
            ],
            'plan' => SubscriptionPlan::Starter,
            'admin_name' => 'Audit Admin',
            'admin_email' => 'audit.admin@example',
            'method' => ActivationMethod::ManualLink,
            'idempotency_key' => 'audit-key-001',
        ], $admin);

        $tokenPart = substr((string) $result['activation_url'], strlen('/activate#token='));

        Log::shouldHaveReceived('info')->withArgs(function (string $message, array $context) use ($tokenPart) {
            $encoded = json_encode($context) ?: '';

            return ! str_contains($encoded, $tokenPart);
        })->atLeast()->once();
    }
}

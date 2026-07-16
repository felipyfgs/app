<?php

namespace Tests\Unit\Operations;

use App\Enums\OfficeRole;
use App\Services\Clients\CaptureEligibilityService;
use App\Services\Operations\Inbox\InboxItemFactory;
use Tests\TestCase;

class InboxItemFactoryTest extends TestCase
{
    private function factory(): InboxItemFactory
    {
        // CaptureEligibilityService é final — usa instância real (sem mock de métodos).
        return new InboxItemFactory(app(CaptureEligibilityService::class));
    }

    public function test_type_severity_mapa_criticos_conhecidos(): void
    {
        $this->assertSame('critical', InboxItemFactory::TYPE_SEVERITY['cursor_blocked']);
        $this->assertSame('critical', InboxItemFactory::TYPE_SEVERITY['credential_expired']);
        $this->assertSame('critical', InboxItemFactory::TYPE_SEVERITY['svrs_nfce_breaker']);
        $this->assertSame('high', InboxItemFactory::TYPE_SEVERITY['cursor_error']);
        $this->assertSame('medium', InboxItemFactory::TYPE_SEVERITY['fiscal_pending']);
    }

    public function test_item_id_estavel_e_severity_por_tipo(): void
    {
        $factory = $this->factory();

        $a = $factory->item(
            'cursor_blocked',
            'Cursor bloqueado',
            'Corpo',
            ['blocked'],
            10,
            20,
            '2026-07-14T12:00:00+00:00',
            OfficeRole::Operator,
            null,
            null,
        );
        $b = $factory->item(
            'cursor_blocked',
            'Outro título',
            'Outro corpo',
            ['x'],
            10,
            20,
            '2026-07-15T12:00:00+00:00',
            OfficeRole::Admin,
            null,
            null,
        );

        $this->assertSame($a['id'], $b['id']);
        $this->assertSame(32, strlen((string) $a['id']));
        $this->assertSame('critical', $a['severity']);
        $this->assertSame('/clients/10', $a['links']['client']);
        $this->assertStringNotContainsString('BEGIN ', (string) $a['body']);
    }

    public function test_encode_decode_cursor_roundtrip(): void
    {
        $factory = $this->factory();

        $token = $factory->encodeCursor(42);
        $this->assertSame(42, $factory->decodeCursor($token));
        $this->assertSame(0, $factory->decodeCursor('!!!invalid!!!'));
        // encode de negativo não é dígito puro no decode → 0
        $this->assertSame(0, $factory->decodeCursor($factory->encodeCursor(-5)));
    }

    public function test_sanitize_text_trunca_e_aceita_null(): void
    {
        $factory = $this->factory();

        $this->assertNull($factory->sanitizeText(null));
        $this->assertNull($factory->sanitizeText(''));
        // Texto longo com espaços (evita regex de base64 em scrubString).
        $long = trim(str_repeat('abcde ', 80));
        $sanitized = (string) $factory->sanitizeText($long);
        $this->assertLessThanOrEqual(280, mb_strlen($sanitized));
        $this->assertGreaterThan(100, mb_strlen($sanitized));
        $this->assertStringNotContainsString('BEGIN ', $sanitized);
    }
}

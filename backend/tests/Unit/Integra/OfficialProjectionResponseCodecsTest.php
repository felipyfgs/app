<?php

namespace Tests\Unit\Integra;

use App\Services\Integra\Registrations\RegistrationLinksResponseCodec;
use App\Services\Integra\TaxProcesses\TaxProcessesResponseCodec;
use RuntimeException;
use Tests\TestCase;

final class OfficialProjectionResponseCodecsTest extends TestCase
{
    public function test_registration_codec_decodes_multiple_cnpjs_and_cursor(): void
    {
        $page = app(RegistrationLinksResponseCodec::class)->decode([
            'codigo' => '200',
            'texto' => 'OK',
            'cnpjs' => [
                ['cnpj' => '11222333000181', 'situacaoCadastral' => 'ATIVA'],
                ['cnpj' => '55666777000009', 'situacaoCadastral' => 'BAIXADA'],
            ],
            'totalInThePage' => 2,
            'totalInTheDatabase' => 3,
            'lastCnpj' => '55666777000009',
        ]);

        $this->assertCount(2, $page['rows']);
        $this->assertSame('11222333000181', $page['rows'][0]['cnpj']);
        $this->assertSame('55666777000009', $page['last_cnpj']);
        $this->assertSame(3, $page['total_in_database']);
    }

    public function test_registration_codec_rejects_legacy_or_invalid_layout(): void
    {
        $this->expectException(RuntimeException::class);
        app(RegistrationLinksResponseCodec::class)->decode([
            'vinculos' => [['cnpj' => '11222333000181']],
        ]);
    }

    public function test_tax_process_codec_requires_official_number_key(): void
    {
        $rows = app(TaxProcessesResponseCodec::class)->decode([
            [
                'numeroDoProcesso' => '10200.000001/2026-10',
                'situacao' => 'ATIVO',
            ],
            [
                'numeroDoProcesso' => '10200.000002/2026-64',
                'situacao' => 'ARQUIVADO',
            ],
        ]);

        $this->assertSame('10200.000001/2026-10', $rows[0]['numeroDoProcesso']);
        $this->assertCount(2, $rows);
    }

    public function test_tax_process_codec_fails_closed_without_numero_do_processo(): void
    {
        $this->expectException(RuntimeException::class);
        app(TaxProcessesResponseCodec::class)->decode([
            'numero' => 'legacy-key',
            'situacao' => 'ATIVO',
        ]);
    }
}

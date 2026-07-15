<?php

namespace Tests\Unit\Support;

use App\Enums\DocumentKind;
use App\Support\DocumentCatalogCursor;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class DocumentCatalogCursorTest extends TestCase
{
    public function test_mantem_posicoes_independentes_por_fonte(): void
    {
        $advanced = (new DocumentCatalogCursor)->advance([
            ['id' => 12, 'kind' => 'NFSE'],
            ['id' => 8, 'kind' => 'NFE'],
            ['id' => 11, 'kind' => 'NFSE'],
            ['id' => 6, 'kind' => 'CTE'],
            ['id' => 4, 'kind' => 'MDFE'],
        ]);

        $decoded = DocumentCatalogCursor::fromToken($advanced->toToken());

        $this->assertSame(11, $decoded->beforeId(DocumentKind::Nfse));
        $this->assertSame(8, $decoded->beforeId(DocumentKind::Nfe));
        $this->assertSame(8, $decoded->beforeId(DocumentKind::Nfce));
        $this->assertSame(6, $decoded->beforeId(DocumentKind::Cte));
        $this->assertSame(4, $decoded->beforeId(DocumentKind::Mdfe));
    }

    public function test_aceita_cursor_escalar_legado_para_as_duas_fontes(): void
    {
        $cursor = DocumentCatalogCursor::fromToken('42');

        $this->assertSame(42, $cursor->beforeId(DocumentKind::Nfse));
        $this->assertSame(42, $cursor->beforeId(DocumentKind::Nfe));
        $this->assertSame(42, $cursor->beforeId(DocumentKind::Cte));
        $this->assertSame(42, $cursor->beforeId(DocumentKind::Mdfe));
    }

    public function test_rejeita_token_invalido(): void
    {
        $this->expectException(InvalidArgumentException::class);

        DocumentCatalogCursor::fromToken('token-invalido');
    }
}

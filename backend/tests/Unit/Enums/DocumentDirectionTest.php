<?php

namespace Tests\Unit\Enums;

use App\Enums\DocumentDirection;
use App\Enums\FiscalRole;
use PHPUnit\Framework\TestCase;

class DocumentDirectionTest extends TestCase
{
    public function test_from_fiscal_role(): void
    {
        $this->assertSame(DocumentDirection::Out, DocumentDirection::fromFiscalRole(FiscalRole::Issuer));
        $this->assertSame(DocumentDirection::In, DocumentDirection::fromFiscalRole(FiscalRole::Taker));
        $this->assertSame(DocumentDirection::In, DocumentDirection::fromFiscalRole(FiscalRole::Intermediary));
        $this->assertSame(DocumentDirection::In, DocumentDirection::fromFiscalRole(FiscalRole::Sender));
        $this->assertSame(DocumentDirection::In, DocumentDirection::fromFiscalRole(FiscalRole::Recipient));
        $this->assertSame(DocumentDirection::In, DocumentDirection::fromFiscalRole(FiscalRole::Expeditor));
        $this->assertSame(DocumentDirection::In, DocumentDirection::fromFiscalRole(FiscalRole::Receiver));
        $this->assertSame(DocumentDirection::Unknown, DocumentDirection::fromFiscalRole(FiscalRole::AutXml));
        $this->assertSame(DocumentDirection::Unknown, DocumentDirection::fromFiscalRole(null));
    }

    public function test_try_from_request(): void
    {
        $this->assertSame(DocumentDirection::In, DocumentDirection::tryFromRequest('in'));
        $this->assertSame(DocumentDirection::Out, DocumentDirection::tryFromRequest('OUT'));
        $this->assertNull(DocumentDirection::tryFromRequest(''));
        $this->assertNull(DocumentDirection::tryFromRequest('INVALID'));
    }

    public function test_labels(): void
    {
        $this->assertSame('Entrada', DocumentDirection::In->label());
        $this->assertSame('Saída', DocumentDirection::Out->label());
        $this->assertSame('Indefinida', DocumentDirection::Unknown->label());
    }
}

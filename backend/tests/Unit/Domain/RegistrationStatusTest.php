<?php

namespace Tests\Unit\Domain;

use App\Enums\RegistrationStatus;
use PHPUnit\Framework\TestCase;

class RegistrationStatusTest extends TestCase
{
    public function test_normaliza_rotulos_conhecidos(): void
    {
        $this->assertSame(RegistrationStatus::Active, RegistrationStatus::fromExternal('Ativa'));
        $this->assertSame(RegistrationStatus::Suspended, RegistrationStatus::fromExternal('SUSPENSA'));
        $this->assertSame(RegistrationStatus::Unfit, RegistrationStatus::fromExternal('Inapta'));
        $this->assertSame(RegistrationStatus::Closed, RegistrationStatus::fromExternal('Baixada'));
        $this->assertSame(RegistrationStatus::Void, RegistrationStatus::fromExternal('Nula'));
        $this->assertSame(RegistrationStatus::Unknown, RegistrationStatus::fromExternal('Qualquer coisa nova'));
        $this->assertSame(RegistrationStatus::Unknown, RegistrationStatus::fromExternal(null));
    }
}

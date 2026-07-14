<?php

namespace Tests\Unit\Domain;

use App\Domain\Cnpj;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class CnpjTest extends TestCase
{
    public function test_normaliza_mascara_e_minusculas(): void
    {
        $cnpj = Cnpj::parse('11.222.333/0001-81');

        $this->assertSame('11222333000181', $cnpj->value());
        $this->assertSame('11222333', $cnpj->root());
    }

    public function test_rejeita_digitos_invalidos(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Cnpj::parse('11222333000182');
    }

    public function test_alfanumerico_valido(): void
    {
        // Monta CNPJ alfanumérico com DV correto
        $base = '12ABC34501DE';
        $d1 = self::digit($base, [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]);
        $d2 = self::digit($base.$d1, [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]);
        $full = $base.$d1.$d2;

        $cnpj = Cnpj::parse(strtolower($full));
        $this->assertSame(strtoupper($full), $cnpj->value());
        $this->assertSame(14, strlen($cnpj->value()));
    }

    public function test_comparacao_de_raiz(): void
    {
        $a = Cnpj::parse('11222333000181');
        $base = '112223330002';
        $d1 = self::digit($base, [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]);
        $d2 = self::digit($base.$d1, [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]);
        $b = Cnpj::parse($base.$d1.$d2);

        $this->assertTrue($a->sameRootAs($b));
    }

    /**
     * @param  list<int>  $weights
     */
    private static function digit(string $base, array $weights): string
    {
        $sum = 0;
        for ($i = 0, $len = strlen($base); $i < $len; $i++) {
            $sum += (ord($base[$i]) - 48) * $weights[$i];
        }
        $mod = $sum % 11;

        return (string) ($mod < 2 ? 0 : 11 - $mod);
    }
}

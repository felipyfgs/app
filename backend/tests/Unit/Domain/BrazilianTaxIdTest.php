<?php

namespace Tests\Unit\Domain;

use App\Domain\BrazilianTaxId;
use App\Domain\Cpf;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class BrazilianTaxIdTest extends TestCase
{
    public function test_normaliza_e_detecta_cpf_cnpj(): void
    {
        $cnpj = BrazilianTaxId::parse('11.222.333/0001-81');
        $this->assertTrue($cnpj->isCnpj());
        $this->assertSame('11222333000181', $cnpj->value());

        $cpfValue = $this->validCpf();
        $cpf = BrazilianTaxId::parseCpf($cpfValue);
        $this->assertTrue($cpf->isCpf());
        $this->assertSame($cpfValue, $cpf->value());
    }

    public function test_round_trip_array_json(): void
    {
        $id = BrazilianTaxId::parse('11222333000181');
        $payload = $id->toArray();
        $json = json_encode($payload, JSON_THROW_ON_ERROR);
        $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        $again = BrazilianTaxId::fromArrayOrString($decoded);
        $this->assertTrue($id->equals($again));
    }

    public function test_rejeita_coercao_invalida(): void
    {
        $this->expectException(InvalidArgumentException::class);
        BrazilianTaxId::parse('123');
    }

    public function test_cpf_dv(): void
    {
        $this->assertNotNull(Cpf::tryParse($this->validCpf()));
        $this->assertNull(Cpf::tryParse('11111111111'));
    }

    private function validCpf(): string
    {
        $base = '529982247';
        for ($t = 9; $t < 11; $t++) {
            $sum = 0;
            for ($i = 0; $i < $t; $i++) {
                $sum += (int) $base[$i] * (($t + 1) - $i);
            }
            $base .= (string) (((10 * $sum) % 11) % 10);
        }

        return $base;
    }
}

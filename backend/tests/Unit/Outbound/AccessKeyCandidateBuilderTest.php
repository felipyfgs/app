<?php

namespace Tests\Unit\Outbound;

use App\Enums\OutboundFiscalModel;
use App\Services\Outbound\AccessKeyCandidateBuilder;
use PHPUnit\Framework\TestCase;

class AccessKeyCandidateBuilderTest extends TestCase
{
    public function test_constroi_chave_44_e_dv_valido(): void
    {
        $builder = new AccessKeyCandidateBuilder;
        $result = $builder->build([
            'cuf' => '21',
            'aamm' => '2607',
            'cnpj' => '12345678000190',
            'model' => OutboundFiscalModel::Nfe,
            'series' => 1,
            'nnf' => 1,
            'tp_emis' => '1',
            'cnf' => '12345678',
        ]);

        $this->assertSame(44, strlen($result['access_key']));
        $this->assertTrue($builder->validateDv($result['access_key']));
        $this->assertSame('12345678', $result['cnf']);
        $this->assertSame('21', substr($result['access_key'], 0, 2));
        $this->assertSame('55', substr($result['access_key'], 20, 2));
    }

    public function test_cnf_deterministico_estavel(): void
    {
        $builder = new AccessKeyCandidateBuilder;
        $a = $builder->deterministicCnf('12345678000190', '55', '001', '000000001', '2607');
        $b = $builder->deterministicCnf('12345678000190', '55', '001', '000000001', '2607');
        $this->assertSame($a, $b);
        $this->assertSame(8, strlen($a));
    }

    public function test_matches_identity(): void
    {
        $builder = new AccessKeyCandidateBuilder;
        $result = $builder->build([
            'cuf' => '21',
            'aamm' => '2607',
            'cnpj' => '12345678000190',
            'model' => '65',
            'series' => 1,
            'nnf' => 42,
            'tp_emis' => '1',
        ]);

        $this->assertTrue($builder->matchesIdentity(
            $result['access_key'],
            '21',
            '12345678000190',
            '65',
            1,
            42,
            '1',
        ));
        $this->assertFalse($builder->matchesIdentity(
            $result['access_key'],
            '21',
            '12345678000190',
            '65',
            1,
            99,
            '1',
        ));
    }
}

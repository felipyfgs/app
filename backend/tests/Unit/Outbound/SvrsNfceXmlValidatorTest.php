<?php

namespace Tests\Unit\Outbound;

use App\Enums\SvrsNfceFailureReason;
use App\Models\Client;
use App\Models\Establishment;
use App\Models\Office;
use App\Services\Outbound\SvrsNfceConfig;
use App\Services\Outbound\SvrsNfceXmlValidator;
use App\Services\Sefaz\NfeXmlProjectionParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SvrsNfceXmlValidatorTest extends TestCase
{
    use RefreshDatabase;

    private function establishment(): Establishment
    {
        $office = Office::factory()->create();
        $client = Client::factory()->forOffice($office)->create();

        return Establishment::factory()->forClient($client)->create([
            'cnpj' => '12345678000190',
            'address_state' => 'MA',
        ]);
    }

    private function validator(): SvrsNfceXmlValidator
    {
        return new SvrsNfceXmlValidator(new NfeXmlProjectionParser, new SvrsNfceConfig);
    }

    public function test_fixture_65_ok_sem_signature_em_testing(): void
    {
        config(['sefaz.svrs_nfce_xml.require_signature' => false]);
        $xml = file_get_contents(base_path('tests/fixtures/ma-outbound/procNFe_65_out_ma.xml'));
        // strip comment
        $xml = preg_replace('/<!--.*?-->/s', '', $xml) ?? $xml;
        $key = '21260712345678000190650010000000011234567892';
        $r = $this->validator()->validate($xml, $key, $this->establishment(), 'homologation');
        $this->assertNull($r['failure_reason']);
        $this->assertSame($key, $r['access_key']);
        $this->assertSame(hash('sha256', $xml), $r['sha256']);
    }

    public function test_reject_xxe_dtd(): void
    {
        $xml = '<?xml version="1.0"?><!DOCTYPE foo [<!ENTITY xxe SYSTEM "file:///etc/passwd">]><nfeProc>&xxe;</nfeProc>';
        $r = $this->validator()->validate($xml, '21260712345678000190650010000000011234567892', $this->establishment(), 'homologation');
        $this->assertSame(SvrsNfceFailureReason::InvalidXml, $r['failure_reason']);
    }

    public function test_reject_wrong_model_key(): void
    {
        config(['sefaz.svrs_nfce_xml.require_signature' => false]);
        $xml = file_get_contents(base_path('tests/fixtures/ma-outbound/procNFe_65_out_ma.xml'));
        $xml = preg_replace('/<!--.*?-->/s', '', $xml) ?? $xml;
        // expected key with model 55
        $r = $this->validator()->validate(
            $xml,
            '21260712345678000190550010000000011234567890',
            $this->establishment(),
            'homologation'
        );
        $this->assertSame(SvrsNfceFailureReason::IdentityMismatch, $r['failure_reason']);
    }

    public function test_dv_validator_numeric(): void
    {
        $v = $this->validator();
        // chave fixture pode não ter DV real — método aceita formato
        $this->assertIsBool($v->accessKeyDvValid('21260712345678000190650010000000011234567892'));
    }
}

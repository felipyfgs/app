<?php

namespace Tests\Unit\Outbound;

use App\Enums\SvrsNfceTransportOutcome;
use App\Services\Outbound\SvrsNfceConfig;
use App\Services\Outbound\SvrsNfceDownloadResponseParser;
use Tests\TestCase;

class SvrsBlockMultipleQueriesParserTest extends TestCase
{
    public function test_bloqueio_http_200_prevalece_sobre_xml(): void
    {
        $html = file_get_contents(base_path('tests/fixtures/svrs-portal/post_blocked_multiple_queries.html'));
        $parser = new SvrsNfceDownloadResponseParser(new SvrsNfceConfig);
        $result = $parser->parseDownloadPage((string) $html);
        $this->assertSame(SvrsNfceTransportOutcome::EgressBlockedMultipleQueries, $result->outcome);
        $this->assertNull($result->xmlBytes);
    }

    public function test_form_get_tambem_detecta_bloqueio(): void
    {
        $html = file_get_contents(base_path('tests/fixtures/svrs-portal/post_blocked_multiple_queries.html'));
        $parser = new SvrsNfceDownloadResponseParser(new SvrsNfceConfig);
        $result = $parser->parseFormPage((string) $html);
        $this->assertSame(SvrsNfceTransportOutcome::EgressBlockedMultipleQueries, $result->outcome);
    }

    public function test_fingerprint_estavel(): void
    {
        $html = file_get_contents(base_path('tests/fixtures/svrs-portal/post_blocked_multiple_queries.html'));
        $parser = new SvrsNfceDownloadResponseParser(new SvrsNfceConfig);
        $fp1 = $parser->blockTemplateFingerprint((string) $html);
        $fp2 = $parser->blockTemplateFingerprint((string) $html);
        $this->assertSame($fp1, $fp2);
        $this->assertSame(64, strlen($fp1));
    }
}

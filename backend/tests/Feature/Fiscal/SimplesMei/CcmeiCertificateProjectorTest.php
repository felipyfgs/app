<?php

namespace Tests\Feature\Fiscal\SimplesMei;

use App\Models\CcmeiCertificateProjection;
use App\Models\Client;
use App\Models\Office;
use App\Services\Fiscal\SimplesMei\CcmeiCertificateProjector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class CcmeiCertificateProjectorTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function persists_only_the_sanitized_summary_and_is_idempotent(): void
    {
        $office = Office::factory()->create();
        $client = Client::factory()->forOffice($office)->create();
        $projector = app(CcmeiCertificateProjector::class);

        $first = $projector->project(
            $office,
            $client,
            ['status' => 'ATIVA', 'situation' => 'UP_TO_DATE'],
            null,
            'SIMULATED',
        );
        $second = $projector->project(
            $office,
            $client,
            ['status' => 'ATIVA', 'situation' => 'UP_TO_DATE'],
            null,
            'SIMULATED',
        );

        $this->assertTrue($first['created']);
        $this->assertFalse($second['created']);
        $this->assertSame($first['observation']->id, $second['observation']->id);
        $this->assertSame([
            'id',
            'status',
            'situation',
            'observed_at',
            'source_provenance',
        ], array_keys($first['observation']->toPublicArray()));
        $this->assertDatabaseCount('ccmei_certificate_projections', 1);
        $this->assertSame('ATIVA', CcmeiCertificateProjection::query()->firstOrFail()->status);
    }

    #[Test]
    public function rejects_client_outside_the_office_before_persisting(): void
    {
        $office = Office::factory()->create();
        $otherOffice = Office::factory()->create();
        $foreignClient = Client::factory()->forOffice($otherOffice)->create();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cliente não pertence ao escritório da projeção CCMEI.');

        app(CcmeiCertificateProjector::class)->project(
            $office,
            $foreignClient,
            ['status' => 'ATIVA', 'situation' => 'UP_TO_DATE'],
            null,
            'SIMULATED',
        );
    }
}

<?php

namespace Tests\Feature\Fiscal\SimplesMei;

use App\Models\CcmeiRegistrationStatusProjection;
use App\Models\Client;
use App\Models\Office;
use App\Services\Fiscal\SimplesMei\CcmeiRegistrationStatusProjector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CcmeiRegistrationStatusProjectorTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function persists_only_sanitized_summary_and_deduplicates_observations(): void
    {
        $office = Office::factory()->create();
        $client = Client::factory()->forOffice($office)->create();
        $summary = ['status' => 'ATIVA', 'enquadrado_mei' => true, 'situation' => 'UP_TO_DATE', 'count' => 1];
        $projector = app(CcmeiRegistrationStatusProjector::class);

        $first = $projector->project($office, $client, $summary, null, 'SIMULATED');
        $second = $projector->project($office, $client, $summary, null, 'SIMULATED');

        $this->assertTrue($first['created']);
        $this->assertFalse($second['created']);
        $this->assertDatabaseCount('ccmei_registration_status_observations', 1);
        $this->assertDatabaseCount('ccmei_registration_status_projections', 1);
        $this->assertSame('ATIVA', CcmeiRegistrationStatusProjection::query()->firstOrFail()->status);
        $this->assertArrayNotHasKey('cnpj', $first['projection']->toPublicArray());
    }
}

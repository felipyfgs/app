<?php

namespace Tests\Feature\Fiscal\SimplesMei;

use App\Models\Client;
use App\Models\DefisDeclarationObservation;
use App\Models\DefisDeclarationProjection;
use App\Models\Office;
use App\Services\Fiscal\SimplesMei\DefisDeclarationProjector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class DefisDeclarationProjectorTest extends TestCase
{
    use RefreshDatabase;

    public function test_persists_sanitized_rows_idempotently(): void
    {
        $office = Office::factory()->create();
        $client = Client::factory()->forOffice($office)->create();
        $items = [['calendar_year' => 2025, 'type' => '1', 'transmitted_at' => null]];

        app(DefisDeclarationProjector::class)->project($office, $client, $items, null, 'SIMULATED');
        app(DefisDeclarationProjector::class)->project($office, $client, $items, null, 'SIMULATED');

        $this->assertDatabaseCount('defis_declaration_observations', 1);
        $this->assertDatabaseCount('defis_declaration_projections', 1);
        $this->assertSame([
            'calendar_year' => 2025,
            'declaration_type' => '1',
            'observed_at' => DefisDeclarationProjection::query()->firstOrFail()->last_observed_at?->toIso8601String(),
            'source_provenance' => 'SIMULATED',
        ], DefisDeclarationProjection::query()->firstOrFail()->toPublicArray());
        $this->assertSame(2025, DefisDeclarationObservation::query()->firstOrFail()->calendar_year);
    }

    public function test_rejects_foreign_client_before_persisting(): void
    {
        $office = Office::factory()->create();
        $foreign = Client::factory()->forOffice(Office::factory()->create())->create();

        $this->expectException(RuntimeException::class);
        app(DefisDeclarationProjector::class)->project(
            $office,
            $foreign,
            [['calendar_year' => 2025, 'type' => '1', 'transmitted_at' => null]],
            null,
            'SIMULATED',
        );
    }
}

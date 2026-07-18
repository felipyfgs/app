<?php

namespace Tests\Unit\Fiscal\Dctfweb;

use App\DTO\Fiscal\FiscalAdapterRequest;
use App\DTO\Fiscal\FiscalAdapterResult;
use App\DTO\Serpro\IntegraResponse;
use App\Enums\DctfwebCategory;
use App\Enums\DctfwebDeclarationState;
use App\Enums\FiscalCoverage;
use App\Enums\FiscalRunResult;
use App\Enums\FiscalSituation;
use App\Enums\FiscalSourceProvenance;
use App\Enums\FiscalTrigger;
use App\Models\Client;
use App\Models\DctfwebConsultObservation;
use App\Models\DctfwebDeclaration;
use App\Models\FiscalMonitoringRun;
use App\Models\Office;
use App\Models\TaxObligationDefinition;
use App\Models\TaxObligationProjection;
use App\Services\Fiscal\Dctfweb\DctfwebPostConsultService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DctfwebPostConsultServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function non_serpro_real_response_is_not_productive_and_records_unverified_provenance(): void
    {
        [$request, $result] = $this->context();
        $service = app(DctfwebPostConsultService::class);

        foreach ([
            new IntegraResponse(true, 200, [], simulated: true, dados: [], sourceProvenance: 'SIMULATED'),
            new IntegraResponse(true, 200, [], dados: [], sourceProvenance: null),
            new IntegraResponse(true, 200, [], dados: [], sourceProvenance: 'UNVERIFIED'),
        ] as $response) {
            $handled = $service->handle($request, $response, $result);
            $this->assertSame(FiscalSituation::Unknown, $handled['result']->situation);
            $this->assertFalse((bool) ($handled['result']->normalized['dctfweb']['productive'] ?? true));
        }

        $observations = DctfwebConsultObservation::query()->orderBy('id')->get();
        $this->assertCount(3, $observations);
        $this->assertSame(FiscalSourceProvenance::Unverified->value, $observations[0]->provenance);
        $this->assertSame(FiscalSourceProvenance::Unverified->value, $observations[1]->provenance);
        $this->assertSame(FiscalSourceProvenance::Unverified->value, $observations[2]->provenance);
        $this->assertTrue($observations->every(fn ($o) => $o->productive === false));
    }

    #[Test]
    public function non_productive_consult_demotes_existing_current_state(): void
    {
        [$request, $result] = $this->context();
        $periodKey = '2026-06';

        $definition = TaxObligationDefinition::query()->firstOrCreate(
            ['code' => 'DCTFWEB'],
            [
                'name' => 'DCTFWeb',
                'module_key' => 'dctfweb_mit',
                'is_active' => true,
            ],
        );

        $declaration = DctfwebDeclaration::query()->create([
            'office_id' => $request->office->id,
            'client_id' => $request->client->id,
            'period_key' => $periodKey,
            'category' => DctfwebCategory::GeralMensal,
            'declaration_type' => 'ORIGINAL',
            'transmission_status' => 'TRANSMITTED',
            'situation' => FiscalSituation::UpToDate,
            'declaration_state' => DctfwebDeclarationState::Current,
            'coverage' => FiscalCoverage::Full,
            'payment_status' => 'UNKNOWN',
            'evidence_version' => 1,
            'calendar_verified' => true,
        ]);

        TaxObligationProjection::query()->create([
            'office_id' => $request->office->id,
            'client_id' => $request->client->id,
            'obligation_definition_id' => $definition->id,
            'period_key' => $periodKey,
            'period_year' => 2026,
            'period_month' => 6,
            'situation' => FiscalSituation::UpToDate,
            'dctfweb_declaration_state' => DctfwebDeclarationState::Current,
            'dctfweb_last_declaration_id' => $declaration->id,
            'dctfweb_calendar_verified' => true,
            'dctfweb_category' => DctfwebCategory::GeralMensal->value,
        ]);

        $service = app(DctfwebPostConsultService::class);
        $service->handle(
            $request,
            new IntegraResponse(true, 200, [], simulated: true, dados: [], sourceProvenance: 'SIMULATED'),
            $result,
        );

        $declaration->refresh();
        $projection = TaxObligationProjection::query()->firstOrFail();

        $this->assertSame(DctfwebDeclarationState::Unverified, $declaration->declaration_state);
        $this->assertSame(FiscalSituation::Unknown, $declaration->situation);
        $this->assertFalse((bool) $declaration->calendar_verified);
        $this->assertSame(DctfwebDeclarationState::Unverified, $projection->dctfweb_declaration_state);
        $this->assertSame(FiscalSituation::Unknown, $projection->situation);
        $this->assertFalse((bool) $projection->dctfweb_calendar_verified);
    }

    /** @return array{FiscalAdapterRequest,FiscalAdapterResult} */
    private function context(): array
    {
        $office = Office::factory()->create(['timezone' => 'America/Sao_Paulo']);
        $client = Client::factory()->forOffice($office)->create();
        $run = FiscalMonitoringRun::query()->create([
            'office_id' => $office->id,
            'client_id' => $client->id,
            'system_code' => 'INTEGRA_DCTFWEB',
            'service_code' => 'DCTFWEB',
            'operation_code' => 'CONSULTAR_RECIBO',
            'source_provenance' => FiscalSourceProvenance::Unverified,
            'trigger' => FiscalTrigger::Manual,
            'idempotency_key' => 'dctfweb-post-consult-test-'.uniqid(),
            'status' => 'QUEUED',
            'situation' => 'UNKNOWN',
            'coverage' => 'FULL',
            'mutability' => 'READ_ONLY',
            'progress' => [
                'expected_period_key' => '2026-06',
                'period_key' => '2026-06',
            ],
        ]);
        $request = new FiscalAdapterRequest(
            office: $office,
            client: $client,
            run: $run,
            systemCode: 'INTEGRA_DCTFWEB',
            serviceCode: 'DCTFWEB',
            operationCode: 'CONSULTAR_RECIBO',
            trigger: FiscalTrigger::Manual,
            progress: $run->progress,
        );
        $result = new FiscalAdapterResult(
            result: FiscalRunResult::Success,
            situation: FiscalSituation::Unknown,
            coverage: FiscalCoverage::Full,
            evidenceBytes: '{}',
            normalized: [],
        );

        return [$request, $result];
    }
}

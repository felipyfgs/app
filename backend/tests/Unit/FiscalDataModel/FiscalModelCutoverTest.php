<?php

namespace Tests\Unit\FiscalDataModel;

use App\Support\FiscalDataModel\FiscalModelAggregates;
use App\Support\FiscalDataModel\FiscalModelCutover;
use InvalidArgumentException;
use Tests\TestCase;

class FiscalModelCutoverTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'fiscal_data_model.kill_switch' => false,
            'fiscal_data_model.job_payload_version' => 1,
            'fiscal_data_model.aggregates.'.FiscalModelAggregates::TENANCY_CADASTRO => [
                'write_canonical' => true,
                'read_canonical' => false,
                'shadow_verify' => false,
                'office_allowlist' => [],
                'allow_all_offices' => false,
            ],
        ]);
    }

    public function test_default_reads_legacy_and_writes_canonical(): void
    {
        $agg = FiscalModelAggregates::TENANCY_CADASTRO;
        $this->assertTrue(FiscalModelCutover::writesCanonical($agg, 1));
        $this->assertFalse(FiscalModelCutover::readsCanonical($agg, 1));
        $this->assertSame('legacy', FiscalModelCutover::readAuthority($agg, 1));
    }

    public function test_kill_switch_forces_legacy_read_without_blocking_write(): void
    {
        $agg = FiscalModelAggregates::TENANCY_CADASTRO;
        config([
            'fiscal_data_model.kill_switch' => true,
            'fiscal_data_model.aggregates.'.$agg.'.read_canonical' => true,
            'fiscal_data_model.aggregates.'.$agg.'.allow_all_offices' => true,
        ]);

        $this->assertFalse(FiscalModelCutover::readsCanonical($agg, 1));
        $this->assertTrue(FiscalModelCutover::writesCanonical($agg, 1));
        $this->assertSame('legacy', FiscalModelCutover::readAuthority($agg, 1));
    }

    public function test_read_canonical_requires_allowlist_or_allow_all(): void
    {
        $agg = FiscalModelAggregates::TENANCY_CADASTRO;
        config([
            'fiscal_data_model.aggregates.'.$agg.'.read_canonical' => true,
            'fiscal_data_model.aggregates.'.$agg.'.allow_all_offices' => false,
            'fiscal_data_model.aggregates.'.$agg.'.office_allowlist' => [],
        ]);
        $this->assertFalse(FiscalModelCutover::readsCanonical($agg, 9));

        config(['fiscal_data_model.aggregates.'.$agg.'.office_allowlist' => [9]]);
        $this->assertTrue(FiscalModelCutover::readsCanonical($agg, 9));
        $this->assertFalse(FiscalModelCutover::readsCanonical($agg, 8));
    }

    public function test_job_payload_versioning(): void
    {
        $payload = FiscalModelCutover::versionJobPayload(['channel_sync_cursor_id' => 10]);
        $this->assertSame(1, $payload['fiscal_model_payload_version']);
        $this->assertTrue(FiscalModelCutover::jobPayloadIsCurrent(1));
        $this->assertFalse(FiscalModelCutover::jobPayloadIsCurrent(null));
        $this->assertFalse(FiscalModelCutover::jobPayloadIsCurrent(0));
    }

    public function test_unknown_aggregate_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);
        FiscalModelCutover::readsCanonical('nope');
    }
}

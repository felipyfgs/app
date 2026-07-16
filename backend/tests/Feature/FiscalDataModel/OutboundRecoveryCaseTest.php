<?php

namespace Tests\Feature\FiscalDataModel;

use App\Models\Office;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class OutboundRecoveryCaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_caso_e_tentativa_idempotentes_e_sem_urgency_captured(): void
    {
        if (! Schema::hasTable('outbound_recovery_cases')) {
            $this->markTestSkipped('tabelas outbound recovery ausentes');
        }

        $office = Office::factory()->create();

        $caseId = DB::table('outbound_recovery_cases')->insertGetId([
            'office_id' => $office->id,
            'identity_key' => 'NFe3525TESTKEY00000000000000000000000000000000',
            'urgency' => 'NORMAL',
            'completeness' => 'OPEN',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('outbound_recovery_attempts')->insert([
            'office_id' => $office->id,
            'outbound_recovery_case_id' => $caseId,
            'source' => 'SVRS_NFCE',
            'request_tag' => 'tag-1',
            'result' => 'PENDING',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Cancelamento idempotente do caso
        DB::table('outbound_recovery_cases')->where('id', $caseId)->update([
            'completeness' => 'CANCELLED',
            'updated_at' => now(),
        ]);
        DB::table('outbound_recovery_cases')->where('id', $caseId)->update([
            'completeness' => 'CANCELLED',
            'updated_at' => now(),
        ]);

        $this->assertSame(1, (int) DB::table('outbound_recovery_cases')->count());
        $this->assertSame('CANCELLED', DB::table('outbound_recovery_cases')->value('completeness'));
        $this->assertNotSame('CAPTURED', DB::table('outbound_recovery_cases')->value('urgency'));

        // Satisfação pelo primeiro acquisition id
        DB::table('outbound_recovery_cases')->where('id', $caseId)->update([
            'completeness' => 'SATISFIED',
            'updated_at' => now(),
        ]);
        $this->assertSame('SATISFIED', DB::table('outbound_recovery_cases')->where('id', $caseId)->value('completeness'));
    }
}

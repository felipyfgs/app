<?php

namespace Tests\Feature\Operations;

use App\Enums\CredentialStatus;
use App\Enums\OfficeRole;
use App\Enums\SyncCursorStatus;
use App\Models\Client;
use App\Models\ClientCredential;
use App\Models\Establishment;
use App\Models\InstanceBackupRun;
use App\Models\Office;
use App\Models\SyncCursor;
use App\Models\SyncRun;
use App\Models\User;
use App\Support\CurrentOffice;
use Database\Factories\EstablishmentFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OperationsInboxTest extends TestCase
{
    use RefreshDatabase;

    private function actingMember(Office $office, OfficeRole $role = OfficeRole::Admin): User
    {
        $user = User::factory()->forOffice($office, $role)->withTwoFactorConfirmed()->create();
        $this->actingAs($user);
        app(CurrentOffice::class)->clear();
        app(CurrentOffice::class)->resolve($user);

        return $user;
    }

    public function test_isolamento_entre_escritorios(): void
    {
        $officeA = Office::factory()->create();
        $officeB = Office::factory()->create();

        $clientA = Client::factory()->forOffice($officeA)->create(['legal_name' => 'Cliente A']);
        $estA = Establishment::factory()->forClient($clientA)->create();
        SyncCursor::query()->create([
            'office_id' => $officeA->id,
            'establishment_id' => $estA->id,
            'environment' => 'restricted_production',
            'last_nsu' => 0,
            'status' => SyncCursorStatus::Blocked,
            'last_error' => 'decode failures',
        ]);

        $clientB = Client::factory()->forOffice($officeB)->create(['legal_name' => 'Cliente B']);
        $estB = Establishment::factory()->forClient($clientB)->create();
        SyncCursor::query()->create([
            'office_id' => $officeB->id,
            'establishment_id' => $estB->id,
            'environment' => 'restricted_production',
            'last_nsu' => 0,
            'status' => SyncCursorStatus::Blocked,
            'last_error' => 'other office',
        ]);

        $this->actingMember($officeA);

        $response = $this->getJson('/api/v1/operations/inbox?office_id='.$officeB->id);
        $response->assertOk();

        $types = collect($response->json('data'))->pluck('type');
        $this->assertTrue($types->contains('cursor_blocked'));
        $bodies = collect($response->json('data'))->pluck('body')->implode(' ');
        $this->assertStringNotContainsString('other office', $bodies);
        $clientIds = collect($response->json('data'))->pluck('client_id')->filter()->unique()->values();
        $this->assertSame([$clientA->id], $clientIds->all());
    }

    public function test_cursor_blocked_e_credential_expiring(): void
    {
        $office = Office::factory()->create();
        $client = Client::factory()->forOffice($office)->create(['legal_name' => 'Acme']);
        $est = Establishment::factory()->forClient($client, EstablishmentFactory::cnpjWithRoot($client->root_cnpj))->create([
            'capture_enabled' => true,
            'is_active' => true,
        ]);
        SyncCursor::query()->create([
            'office_id' => $office->id,
            'establishment_id' => $est->id,
            'environment' => 'restricted_production',
            'last_nsu' => 10,
            'status' => SyncCursorStatus::Blocked,
            'last_error' => 'limite de decode',
        ]);

        ClientCredential::query()->create([
            'office_id' => $office->id,
            'client_id' => $client->id,
            'status' => CredentialStatus::Active,
            'subject_name' => 'CN=Acme',
            'holder_cnpj' => $est->cnpj,
            'fingerprint_sha256' => str_repeat('a', 64),
            'valid_from' => now()->subYear(),
            'valid_to' => now()->addDays(5),
            'vault_object_id' => 'vaultobj01abcdefghijklmn',
            'activated_at' => now()->subMonth(),
            'expires_alert_30' => true,
            'expires_alert_7' => true,
            'expires_alert_1' => false,
        ]);

        $this->actingMember($office);

        $response = $this->getJson('/api/v1/operations/inbox');
        $response->assertOk();
        $types = collect($response->json('data'))->pluck('type');
        $this->assertTrue($types->contains('cursor_blocked'));
        $this->assertTrue($types->contains('credential_expiring_7d'));
        $this->assertTrue($types->contains('backup_never'));

        $blocked = collect($response->json('data'))->firstWhere('type', 'cursor_blocked');
        $this->assertSame('critical', $blocked['severity']);
        $this->assertSame('/clients/'.$client->id.'/sincronizacao', $blocked['links']['sync']);
        $this->assertStringNotContainsString('vault_object_id', json_encode($response->json()));
    }

    public function test_backup_never_e_stale_no_summary(): void
    {
        $office = Office::factory()->create();
        $this->actingMember($office);

        $summary = $this->getJson('/api/v1/operations/summary')->assertOk()->json('data');
        $this->assertTrue($summary['backup']['never']);
        $this->assertTrue($summary['backup']['stale']);
        $this->assertArrayHasKey('inbox_critical', $summary);
        $this->assertArrayHasKey('inbox_high', $summary);
        $this->assertArrayHasKey('inbox_total', $summary);
        $this->assertGreaterThanOrEqual(1, $summary['inbox_critical']);

        InstanceBackupRun::factory()->create([
            'kind' => InstanceBackupRun::KIND_FULL,
            'status' => InstanceBackupRun::STATUS_SUCCESS,
            'finished_at' => now()->subHours(30),
        ]);

        $stale = $this->getJson('/api/v1/operations/summary')->assertOk()->json('data');
        $this->assertFalse($stale['backup']['never']);
        $this->assertTrue($stale['backup']['stale']);

        $inbox = $this->getJson('/api/v1/operations/inbox?type=backup_stale')->assertOk();
        $this->assertNotEmpty($inbox->json('data'));
    }

    public function test_viewer_sem_trigger_sync(): void
    {
        $office = Office::factory()->create();
        $client = Client::factory()->forOffice($office)->create();
        $est = Establishment::factory()->forClient($client)->create([
            'capture_enabled' => true,
            'is_active' => true,
        ]);
        $cursor = SyncCursor::query()->create([
            'office_id' => $office->id,
            'establishment_id' => $est->id,
            'environment' => 'restricted_production',
            'last_nsu' => 0,
            'status' => SyncCursorStatus::Error,
            'last_error' => 'timeout',
        ]);
        ClientCredential::query()->create([
            'office_id' => $office->id,
            'client_id' => $client->id,
            'status' => CredentialStatus::Active,
            'subject_name' => 'CN=X',
            'holder_cnpj' => $est->cnpj,
            'fingerprint_sha256' => str_repeat('b', 64),
            'valid_from' => now()->subYear(),
            'valid_to' => now()->addYear(),
            'vault_object_id' => 'vaultobj02abcdefghijklmn',
            'activated_at' => now()->subMonth(),
        ]);

        SyncRun::query()->create([
            'office_id' => $office->id,
            'sync_cursor_id' => $cursor->id,
            'status' => 'FAILED',
            'trigger' => 'SCHEDULED',
            'error_message' => 'falha recente',
            'started_at' => now()->subHour(),
            'finished_at' => now()->subMinutes(50),
        ]);

        $this->actingMember($office, OfficeRole::Viewer);

        $response = $this->getJson('/api/v1/operations/inbox')->assertOk();
        $actions = collect($response->json('data'))->flatMap(fn ($item) => $item['actions'] ?? []);
        $this->assertFalse($actions->contains(fn ($a) => ($a['type'] ?? null) === 'trigger_sync'));
    }

    public function test_operador_elegivel_recebe_trigger_sync_e_blocked_nao(): void
    {
        $office = Office::factory()->create();
        // Modelo 1 cliente = 1 CNPJ/estabelecimento: dois clientes distintos.
        $clientOk = Client::factory()->forOffice($office)->create(['is_active' => true]);
        $clientBlocked = Client::factory()->forOffice($office)->create(['is_active' => true]);
        $estOk = Establishment::factory()->forClient($clientOk)->create([
            'capture_enabled' => true,
            'is_active' => true,
        ]);
        $estBlocked = Establishment::factory()->forClient($clientBlocked)->create([
            'capture_enabled' => true,
            'is_active' => true,
        ]);

        foreach ([[$clientOk, $estOk, 'vaultobj03abcdefghijklmn'], [$clientBlocked, $estBlocked, 'vaultobj04abcdefghijklmn']] as [$client, $est, $vaultId]) {
            ClientCredential::query()->create([
                'office_id' => $office->id,
                'client_id' => $client->id,
                'status' => CredentialStatus::Active,
                'subject_name' => 'CN=X',
                'holder_cnpj' => $est->cnpj,
                'fingerprint_sha256' => hash('sha256', (string) $vaultId),
                'valid_from' => now()->subYear(),
                'valid_to' => now()->addYear(),
                'vault_object_id' => $vaultId,
                'activated_at' => now()->subMonth(),
            ]);
        }

        SyncCursor::query()->create([
            'office_id' => $office->id,
            'establishment_id' => $estOk->id,
            'environment' => 'restricted_production',
            'last_nsu' => 1,
            'status' => SyncCursorStatus::Error,
            'last_error' => 'timeout',
        ]);
        SyncCursor::query()->create([
            'office_id' => $office->id,
            'establishment_id' => $estBlocked->id,
            'environment' => 'restricted_production',
            'last_nsu' => 2,
            'status' => SyncCursorStatus::Blocked,
            'last_error' => 'decode',
        ]);

        $this->actingMember($office, OfficeRole::Operator);

        $response = $this->getJson('/api/v1/operations/inbox')->assertOk();
        $byEst = collect($response->json('data'))->keyBy('establishment_id');

        $errorItem = $byEst->get($estOk->id);
        $this->assertNotNull($errorItem);
        $this->assertTrue(collect($errorItem['actions'])->contains(fn ($a) => ($a['type'] ?? null) === 'trigger_sync'));

        $blockedItem = $byEst->get($estBlocked->id);
        $this->assertNotNull($blockedItem);
        $this->assertFalse(collect($blockedItem['actions'])->contains(fn ($a) => ($a['type'] ?? null) === 'trigger_sync'));
    }

    public function test_dois_ambientes_mesmo_estabelecimento_ids_distintos(): void
    {
        $office = Office::factory()->create();
        $client = Client::factory()->forOffice($office)->create();
        $est = Establishment::factory()->forClient($client)->create();

        SyncCursor::query()->create([
            'office_id' => $office->id,
            'establishment_id' => $est->id,
            'environment' => 'restricted_production',
            'last_nsu' => 1,
            'status' => SyncCursorStatus::Blocked,
            'last_error' => 'prod',
        ]);
        SyncCursor::query()->create([
            'office_id' => $office->id,
            'establishment_id' => $est->id,
            'environment' => 'homologation',
            'last_nsu' => 2,
            'status' => SyncCursorStatus::Blocked,
            'last_error' => 'hom',
        ]);

        $this->actingMember($office);

        $items = collect($this->getJson('/api/v1/operations/inbox?type=cursor_blocked')->assertOk()->json('data'))
            ->where('establishment_id', $est->id)
            ->values();

        $this->assertCount(2, $items);
        $this->assertNotSame($items[0]['id'], $items[1]['id']);
    }

    public function test_payload_sem_marcadores_sensiveis(): void
    {
        $office = Office::factory()->create();
        $client = Client::factory()->forOffice($office)->create();
        $est = Establishment::factory()->forClient($client)->create();
        SyncCursor::query()->create([
            'office_id' => $office->id,
            'establishment_id' => $est->id,
            'environment' => 'restricted_production',
            'last_nsu' => 0,
            'status' => SyncCursorStatus::Error,
            'last_error' => 'erro remoto sem segredo',
        ]);

        $this->actingMember($office);
        $json = $this->getJson('/api/v1/operations/inbox')->assertOk()->getContent();

        foreach ([
            'VAULT_MASTER_KEY',
            'vault_object_id',
            'BEGIN PRIVATE',
            'BEGIN RSA',
            '-----BEGIN',
            'password=',
            '.pfx',
        ] as $needle) {
            $this->assertStringNotContainsStringIgnoringCase($needle, $json);
        }
    }

    public function test_filtro_severity_e_paginacao(): void
    {
        $office = Office::factory()->create();
        $this->actingMember($office);

        $critical = $this->getJson('/api/v1/operations/inbox?severity=critical&limit=1')->assertOk();
        $this->assertLessThanOrEqual(1, count($critical->json('data')));
        foreach ($critical->json('data') as $item) {
            $this->assertSame('critical', $item['severity']);
        }
    }
}

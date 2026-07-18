<?php

namespace Tests\Feature\Clients;

use App\Enums\ClientProcuracaoSyncStatus;
use App\Enums\OfficeRole;
use App\Models\Client;
use App\Models\ClientProcuracaoSync;
use App\Models\Office;
use App\Models\User;
use App\Support\CurrentOffice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ClientProcuracaoValidityProjectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_list_projects_authorized_expiring_expired_missing_and_unverified_without_sensitive_data(): void
    {
        $office = Office::factory()->create();
        $user = User::factory()->forOffice($office, OfficeRole::Operator)->withTwoFactorConfirmed()->create();
        $authorized = Client::factory()->forOffice($office)->create(['legal_name' => 'Autorizada']);
        $expiring = Client::factory()->forOffice($office)->create(['legal_name' => 'A vencer']);
        $expired = Client::factory()->forOffice($office)->create(['legal_name' => 'Vencida']);
        $missing = Client::factory()->forOffice($office)->create(['legal_name' => 'Ausente']);
        $unverified = Client::factory()->forOffice($office)->create(['legal_name' => 'Não verificada']);

        $this->sync($authorized, ClientProcuracaoSyncStatus::Authorized, now()->addDays(31));
        $this->sync($expiring, ClientProcuracaoSyncStatus::Authorized, now()->addDays(30));
        // Estado persistido ainda ativo: GET calcula vencimento sem fazer write.
        $this->sync($expired, ClientProcuracaoSyncStatus::Authorized, now()->subSecond());
        $this->sync($missing, ClientProcuracaoSyncStatus::Missing, null);

        $this->actingAs($user);
        app(CurrentOffice::class)->resolve($user);

        $response = $this->getJson('/api/v1/clients?per_page=50')->assertOk();
        $items = collect($response->json('data'))->keyBy('legal_name');

        $this->assertSame('authorized', $items['Autorizada']['procuracao_status']);
        $this->assertSame('expiring', $items['A vencer']['procuracao_status']);
        $this->assertSame('expired', $items['Vencida']['procuracao_status']);
        $this->assertSame('missing', $items['Ausente']['procuracao_status']);
        $this->assertSame('unverified', $items['Não verificada']['procuracao_status']);

        $body = (string) $response->getContent();
        $this->assertStringNotContainsString('evidence_ref', $body);
        $this->assertStringNotContainsString('powers_summary', $body);
        $this->assertStringNotContainsString('author_identity', $body);
        $this->assertSame(ClientProcuracaoSyncStatus::Authorized, $expired->fresh()->procuracaoSync?->status);
    }

    public function test_detail_and_list_never_project_other_office_procuracao(): void
    {
        $officeA = Office::factory()->create();
        $officeB = Office::factory()->create();
        $userA = User::factory()->forOffice($officeA, OfficeRole::Operator)->withTwoFactorConfirmed()->create();
        $clientA = Client::factory()->forOffice($officeA)->create();
        $clientB = Client::factory()->forOffice($officeB)->create();
        $this->sync($clientA, ClientProcuracaoSyncStatus::Authorized, now()->addYear());
        $this->sync($clientB, ClientProcuracaoSyncStatus::Expired, now()->subDay());

        $this->actingAs($userA);
        app(CurrentOffice::class)->resolve($userA);

        $this->getJson('/api/v1/clients')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $clientA->id)
            ->assertJsonPath('data.0.procuracao_status', 'authorized');
        $this->getJson("/api/v1/clients/{$clientB->id}")->assertNotFound();
    }

    private function sync(Client $client, ClientProcuracaoSyncStatus $status, mixed $validTo): void
    {
        ClientProcuracaoSync::query()->create([
            'office_id' => $client->office_id,
            'client_id' => $client->id,
            'status' => $status,
            'valid_from' => now()->subMonth(),
            'valid_to' => $validTo,
            'last_verified_at' => now()->subMinute(),
            'evidence_ref' => 'vault:never-return-this',
            'powers_summary' => ['power_codes' => ['00146']],
            'last_check_result' => 'AUTHORIZED',
            'source' => 'official_sync',
        ]);
    }
}

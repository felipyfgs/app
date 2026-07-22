<?php

namespace Tests\Feature\Communication;

use App\Enums\Communication\InboxStatus;
use App\Enums\Communication\RecipientMode;
use App\Enums\OfficeRole;
use App\Events\CommunicationEventCommitted;
use App\Models\Client;
use App\Models\ClientCommunicationPreference;
use App\Models\CommunicationContact;
use App\Models\CommunicationIdentity;
use App\Models\CommunicationIdentityLink;
use App\Models\CommunicationInbox;
use App\Models\Office;
use App\Models\User;
use App\Support\CurrentOffice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class CommunicationAdministrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Event::fake([CommunicationEventCommitted::class]);
        config([
            'communication.enabled' => true,
            'communication.gateway.enabled' => true,
        ]);
    }

    public function test_contacts_support_multiple_client_links_but_remain_isolated_by_office(): void
    {
        $office = Office::factory()->create(['communication_enabled' => true]);
        $foreignOffice = Office::factory()->create(['communication_enabled' => true]);
        $admin = User::factory()->forOffice($office, OfficeRole::Admin)->create();
        $operator = User::factory()->forOffice($office, OfficeRole::Operator)->create();
        $foreignAdmin = User::factory()->forOffice($foreignOffice, OfficeRole::Admin)->create();
        $clientA = Client::factory()->create(['office_id' => $office->id]);
        $clientB = Client::factory()->create(['office_id' => $office->id]);
        $foreignClient = Client::factory()->create(['office_id' => $foreignOffice->id]);

        $this->authenticate($admin);
        $created = $this->postJson('/api/v1/communication/contacts', [
            'name' => 'Contador responsável',
            'phone' => '(11) 99999-5555',
            'client_id' => $clientA->id,
            'is_primary' => true,
        ])->assertCreated()
            ->assertJsonPath('data.identities.0.links.0.client_id', $clientA->id);
        $contactId = (int) $created->json('data.id');
        $identityId = (int) $created->json('data.identities.0.id');

        $this->postJson('/api/v1/communication/identities/'.$identityId.'/links', [
            'client_id' => $clientB->id,
            'receives_automatic' => true,
        ])->assertCreated();
        $this->assertDatabaseCount('communication_identity_links', 2);
        $this->getJson('/api/v1/communication/contacts/'.$contactId)
            ->assertOk()
            ->assertJsonCount(2, 'data.identities.0.links');
        $this->postJson('/api/v1/communication/contacts', [
            'name' => 'Duplicado',
            'phone' => '+55 11 99999-5555',
        ])->assertStatus(409)->assertJsonPath('code', 'identity_conflict');

        $this->authenticate($operator);
        $this->postJson('/api/v1/communication/contacts', [
            'name' => 'Sem gerência',
            'phone' => '+5511999996666',
        ])->assertForbidden();

        $this->authenticate($foreignAdmin);
        $this->postJson('/api/v1/communication/contacts', [
            'name' => 'Mesmo número, outro escritório',
            'phone' => '+5511999995555',
            'client_id' => $foreignClient->id,
        ])->assertCreated();
        $this->assertSame(2, CommunicationIdentity::query()->withoutGlobalScopes()
            ->where('address_hash', hash('sha256', '+5511999995555'))->count());
        $this->getJson('/api/v1/communication/contacts/'.$contactId)->assertNotFound();
    }

    public function test_policy_and_selected_recipients_are_explicit_versioned_and_fail_closed(): void
    {
        $office = Office::factory()->create(['communication_enabled' => true]);
        $admin = User::factory()->forOffice($office, OfficeRole::Admin)->create();
        $client = Client::factory()->create(['office_id' => $office->id]);
        $inbox = $this->inbox($office);
        $first = $this->identity($office, $client, '+5511999997001', true);
        $second = $this->identity($office, $client, '+5511999997002', false);
        $preference = ClientCommunicationPreference::query()->withoutGlobalScopes()->create([
            'office_id' => $office->id,
            'client_id' => $client->id,
            'module_key' => 'simples_mei',
            'submodule_key' => 'pgdasd',
            'automatic_requested' => true,
            'whatsapp_enabled' => true,
            'recipient_mode' => RecipientMode::Primary,
            'lock_version' => 1,
        ]);
        $this->authenticate($admin);

        $base = [
            'module_key' => 'simples_mei',
            'submodule_key' => 'pgdasd',
            'inbox_id' => null,
            'is_enabled' => true,
            'send_day' => 20,
            'send_time' => '10:30',
            'timezone' => 'America/Sao_Paulo',
            'recipient_mode' => RecipientMode::Selected->value,
            'template_key' => 'pgdasd.document',
            'template_version' => '2',
            'lock_version' => 0,
        ];
        $this->putJson('/api/v1/communication/automation-policies', $base)->assertStatus(422);
        $created = $this->putJson('/api/v1/communication/automation-policies', [
            ...$base,
            'inbox_id' => $inbox->id,
        ])->assertOk()
            ->assertJsonPath('data.lock_version', 1)
            ->assertJsonPath('data.recipient_mode', RecipientMode::Selected->value);
        $this->putJson('/api/v1/communication/automation-policies', [
            ...$base,
            'inbox_id' => $inbox->id,
            'send_day' => 21,
            'lock_version' => 0,
        ])->assertStatus(409)->assertJsonPath('code', 'version_conflict');
        $this->putJson('/api/v1/communication/automation-policies', [
            ...$base,
            'module_key' => 'defis',
            'inbox_id' => $inbox->id,
        ])->assertStatus(422);
        $this->getJson('/api/v1/communication/automation-policies')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $created->json('data.id'));

        $query = '?module_key=simples_mei&submodule_key=pgdasd';
        $this->getJson('/api/v1/communication/clients/'.$client->id.'/automation-recipients'.$query)
            ->assertOk()
            ->assertJsonCount(2, 'data.identities')
            ->assertJsonPath('data.identities.0.id', $first->id);
        $this->putJson('/api/v1/communication/clients/'.$client->id.'/automation-recipients'.$query, [
            'recipient_mode' => RecipientMode::Selected->value,
            'identity_ids' => [],
            'lock_version' => $preference->lock_version,
        ])->assertStatus(422);
        $this->putJson('/api/v1/communication/clients/'.$client->id.'/automation-recipients'.$query, [
            'recipient_mode' => RecipientMode::Selected->value,
            'identity_ids' => [$second->id],
            'lock_version' => $preference->lock_version,
        ])->assertOk()
            ->assertJsonPath('data.recipient_mode', RecipientMode::Selected->value)
            ->assertJsonPath('data.selected_identity_ids.0', $second->id);
        $this->putJson('/api/v1/communication/clients/'.$client->id.'/automation-recipients'.$query, [
            'recipient_mode' => RecipientMode::AllEligible->value,
            'identity_ids' => [$first->id, $second->id],
            'lock_version' => $preference->lock_version,
        ])->assertStatus(409)->assertJsonPath('code', 'version_conflict');
    }

    public function test_pairing_is_durable_and_switches_refuse_commands_when_disabled(): void
    {
        $office = Office::factory()->create(['communication_enabled' => true]);
        $admin = User::factory()->forOffice($office, OfficeRole::Admin)->create();
        $inbox = $this->inbox($office);
        $this->authenticate($admin);

        $this->postJson('/api/v1/communication/inboxes/'.$inbox->id.'/pairing')
            ->assertStatus(202)
            ->assertJsonCount(2, 'data.commands');
        $this->assertDatabaseCount('communication_outbox_entries', 2);
        $this->assertSame(InboxStatus::Provisioned, $inbox->refresh()->status);

        config(['communication.enabled' => false]);
        $this->postJson('/api/v1/communication/inboxes/'.$inbox->id.'/pairing')
            ->assertStatus(503)
            ->assertJsonPath('code', 'COMMUNICATION_DISABLED');
        $this->assertDatabaseCount('communication_outbox_entries', 2);
    }

    private function authenticate(User $user): void
    {
        Sanctum::actingAs($user);
        app(CurrentOffice::class)->clear();
    }

    private function inbox(Office $office): CommunicationInbox
    {
        return CommunicationInbox::query()->withoutGlobalScopes()->create([
            'office_id' => $office->id,
            'name' => 'WhatsApp geral',
            'session_id' => 'session-'.Str::ulid(),
            'status' => InboxStatus::Connected,
            'is_enabled' => true,
            'is_default' => true,
        ]);
    }

    private function identity(Office $office, Client $client, string $address, bool $primary): CommunicationIdentity
    {
        $contact = CommunicationContact::query()->withoutGlobalScopes()->create([
            'office_id' => $office->id,
            'name' => 'Contato '.substr($address, -4),
            'is_active' => true,
        ]);
        $identity = CommunicationIdentity::query()->withoutGlobalScopes()->create([
            'office_id' => $office->id,
            'contact_id' => $contact->id,
            'channel' => 'WHATSAPP',
            'address_encrypted' => $address,
            'address_hash' => hash('sha256', $address),
            'address_masked' => '***'.substr($address, -4),
            'is_active' => true,
        ]);
        CommunicationIdentityLink::query()->withoutGlobalScopes()->create([
            'office_id' => $office->id,
            'identity_id' => $identity->id,
            'client_id' => $client->id,
            'is_primary' => $primary,
            'receives_automatic' => true,
        ]);

        return $identity;
    }
}

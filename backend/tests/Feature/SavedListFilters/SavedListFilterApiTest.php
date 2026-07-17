<?php

namespace Tests\Feature\SavedListFilters;

use App\Enums\OfficeRole;
use App\Models\Office;
use App\Models\SavedListFilter;
use App\Models\User;
use App\Support\CurrentOffice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Persistência e API de filtros salvos (personal | office) — isolamento e ownership.
 *
 * @see openspec/changes/filtros-salvos-monitoring
 */
class SavedListFilterApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_cria_preset_pessoal(): void
    {
        [$office, $user] = $this->seedMember(OfficeRole::Viewer);
        $this->actingAsOffice($user);

        // office_id no body é stripado pelo middleware — preset fica no CurrentOffice
        $ok = $this->postJson('/api/v1/list-filters', [
            'surface' => 'monitoring.installments',
            'name' => 'Meus atrasados',
            'visibility' => 'personal',
            'schema_version' => 1,
            'payload' => [
                'schema_version' => 1,
                'q' => 'acme',
                'filters' => [
                    ['key' => 'situation', 'operator' => 'eq', 'value' => 'BLOCKED'],
                ],
            ],
            'office_id' => 99999,
        ])->assertCreated()
            ->assertJsonPath('data.surface', 'monitoring.installments')
            ->assertJsonPath('data.name', 'Meus atrasados')
            ->assertJsonPath('data.visibility', 'personal')
            ->assertJsonPath('data.user_id', $user->id)
            ->assertJsonPath('data.payload.q', 'acme');

        $this->assertArrayNotHasKey('office_id', $ok->json('data'));

        $row = SavedListFilter::query()->first();
        $this->assertNotNull($row);
        $this->assertSame($office->id, $row->office_id);
        $this->assertNotSame(99999, $row->office_id);
        $this->assertSame($user->id, $row->user_id);
        $this->assertSame('personal', $row->visibility);
    }

    public function test_viewer_nao_publica_visibility_office(): void
    {
        [, $viewer] = $this->seedMember(OfficeRole::Viewer);
        $this->actingAsOffice($viewer);

        $this->postJson('/api/v1/list-filters', [
            'surface' => 'monitoring.sitfis',
            'name' => 'Equipe SITFIS',
            'visibility' => 'office',
            'payload' => ['q' => ''],
        ])->assertForbidden();
    }

    public function test_operator_compartilha_com_office(): void
    {
        [$office, $operator] = $this->seedMember(OfficeRole::Operator);
        $viewer = User::factory()->forOffice($office, OfficeRole::Viewer)->withTwoFactorConfirmed()->create();
        $this->actingAsOffice($operator);

        $created = $this->postJson('/api/v1/list-filters', [
            'surface' => 'monitoring.sitfis',
            'name' => 'Equipe SITFIS',
            'visibility' => 'office',
            'payload' => [
                'filters' => [
                    ['key' => 'situation', 'operator' => 'eq', 'value' => 'PENDING'],
                ],
            ],
        ])->assertCreated()
            ->assertJsonPath('data.visibility', 'office');

        $id = $created->json('data.id');

        $this->actingAsOffice($viewer);
        $list = $this->getJson('/api/v1/list-filters?surface=monitoring.sitfis')
            ->assertOk()
            ->json('data');

        $ids = collect($list)->pluck('id')->all();
        $this->assertContains($id, $ids);
    }

    public function test_listagem_mistura_personal_do_user_e_office(): void
    {
        [$office, $author] = $this->seedMember(OfficeRole::Operator);
        $other = User::factory()->forOffice($office, OfficeRole::Operator)->withTwoFactorConfirmed()->create();

        $this->actingAsOffice($author);
        $mine = $this->postJson('/api/v1/list-filters', [
            'surface' => 'clients.index',
            'name' => 'Meus ativos',
            'visibility' => 'personal',
            'payload' => ['q' => 'a'],
        ])->assertCreated()->json('data.id');

        $shared = $this->postJson('/api/v1/list-filters', [
            'surface' => 'clients.index',
            'name' => 'Equipe ativos',
            'visibility' => 'office',
            'payload' => ['q' => 'b'],
        ])->assertCreated()->json('data.id');

        // Preset pessoal de outro usuário — não deve aparecer para o author
        $this->actingAsOffice($other);
        $otherPersonal = $this->postJson('/api/v1/list-filters', [
            'surface' => 'clients.index',
            'name' => 'Segredo do outro',
            'visibility' => 'personal',
            'payload' => ['q' => 'c'],
        ])->assertCreated()->json('data.id');

        $this->actingAsOffice($author);
        $ids = collect($this->getJson('/api/v1/list-filters?surface=clients.index')
            ->assertOk()
            ->json('data'))->pluck('id')->all();

        $this->assertContains($mine, $ids);
        $this->assertContains($shared, $ids);
        $this->assertNotContains($otherPersonal, $ids);
    }

    public function test_filtro_surface_na_listagem(): void
    {
        [, $user] = $this->seedMember(OfficeRole::Operator);
        $this->actingAsOffice($user);

        $this->postJson('/api/v1/list-filters', [
            'surface' => 'monitoring.sitfis',
            'name' => 'A',
            'visibility' => 'personal',
            'payload' => [],
        ])->assertCreated();

        $this->postJson('/api/v1/list-filters', [
            'surface' => 'docs.catalog',
            'name' => 'B',
            'visibility' => 'personal',
            'payload' => [],
        ])->assertCreated();

        $this->getJson('/api/v1/list-filters')->assertStatus(422);

        $list = $this->getJson('/api/v1/list-filters?surface=monitoring.sitfis')
            ->assertOk()
            ->json('data');

        $this->assertCount(1, $list);
        $this->assertSame('A', $list[0]['name']);
    }

    public function test_isolamento_entre_dois_offices(): void
    {
        [$officeA, $userA] = $this->seedMember(OfficeRole::Admin);
        [$officeB, $userB] = $this->seedMember(OfficeRole::Admin);

        $this->actingAsOffice($userA);
        $idA = $this->postJson('/api/v1/list-filters', [
            'surface' => 'monitoring.sitfis',
            'name' => 'Preset A',
            'visibility' => 'office',
            'payload' => ['q' => 'tenant-a'],
        ])->assertCreated()->json('data.id');

        $this->actingAsOffice($userB);
        $listB = $this->getJson('/api/v1/list-filters?surface=monitoring.sitfis')
            ->assertOk()
            ->json('data');
        $this->assertSame([], $listB);

        // Não resolve modelo de outro office (global scope + 404)
        $this->deleteJson('/api/v1/list-filters/'.$idA)->assertNotFound();

        $this->actingAsOffice($userA);
        $this->getJson('/api/v1/list-filters?surface=monitoring.sitfis')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $idA);

        $this->assertSame($officeA->id, SavedListFilter::query()->find($idA)?->office_id);
        $this->assertNotSame($officeB->id, SavedListFilter::query()->find($idA)?->office_id);
    }

    public function test_autor_exclui_preset_pessoal(): void
    {
        [, $user] = $this->seedMember(OfficeRole::Viewer);
        $this->actingAsOffice($user);

        $id = $this->postJson('/api/v1/list-filters', [
            'surface' => 'work.queue',
            'name' => 'Minha fila',
            'visibility' => 'personal',
            'payload' => [],
        ])->assertCreated()->json('data.id');

        $this->deleteJson('/api/v1/list-filters/'.$id)->assertNoContent();

        $this->getJson('/api/v1/list-filters?surface=work.queue')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_admin_exclui_preset_office_de_terceiros(): void
    {
        [$office, $operator] = $this->seedMember(OfficeRole::Operator);
        $admin = User::factory()->forOffice($office, OfficeRole::Admin)->withTwoFactorConfirmed()->create();

        $this->actingAsOffice($operator);
        $id = $this->postJson('/api/v1/list-filters', [
            'surface' => 'monitoring.fgts',
            'name' => 'Shared FGTS',
            'visibility' => 'office',
            'payload' => [],
        ])->assertCreated()->json('data.id');

        // Outro operador não exclui
        $otherOp = User::factory()->forOffice($office, OfficeRole::Operator)->withTwoFactorConfirmed()->create();
        $this->actingAsOffice($otherOp);
        $this->deleteJson('/api/v1/list-filters/'.$id)->assertForbidden();

        $this->actingAsOffice($admin);
        $this->deleteJson('/api/v1/list-filters/'.$id)->assertNoContent();
    }

    public function test_autor_atualiza_e_viewer_nao_eleva_para_office(): void
    {
        [$office, $viewer] = $this->seedMember(OfficeRole::Viewer);
        $this->actingAsOffice($viewer);

        $id = $this->postJson('/api/v1/list-filters', [
            'surface' => 'monitoring.sitfis',
            'name' => 'Rascunho',
            'visibility' => 'personal',
            'payload' => ['q' => 'old'],
        ])->assertCreated()->json('data.id');

        $this->patchJson('/api/v1/list-filters/'.$id, [
            'name' => 'Rascunho v2',
            'payload' => ['q' => 'new'],
        ])->assertOk()
            ->assertJsonPath('data.name', 'Rascunho v2')
            ->assertJsonPath('data.payload.q', 'new');

        $this->patchJson('/api/v1/list-filters/'.$id, [
            'visibility' => 'office',
        ])->assertForbidden();

        unset($office);
    }

    /**
     * @return array{0: Office, 1: User}
     */
    private function seedMember(OfficeRole $role): array
    {
        $office = Office::factory()->create();
        $user = User::factory()->forOffice($office, $role)->withTwoFactorConfirmed()->create();

        return [$office, $user];
    }

    private function actingAsOffice(User $user): void
    {
        $this->actingAs($user);
        app(CurrentOffice::class)->clear();
        app(CurrentOffice::class)->resolve($user);
    }
}

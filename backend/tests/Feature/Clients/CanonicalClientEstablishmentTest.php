<?php

namespace Tests\Feature\Clients;

use App\Domain\Cnpj;
use App\Enums\OfficeRole;
use App\Models\Client;
use App\Models\Establishment;
use App\Models\Office;
use App\Models\User;
use App\Support\CurrentOffice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Agregado canônico: um Cliente por raiz, N estabelecimentos.
 */
class CanonicalClientEstablishmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_segundo_cnpj_mesma_raiz_anexa_estabelecimento_ao_mesmo_cliente(): void
    {
        [$office, $user] = $this->officeUser(OfficeRole::Operator);
        $this->actingAs($user);
        app(CurrentOffice::class)->resolve($user);

        $matrixCnpj = '11222333000181';
        $this->assertTrue(Cnpj::hasValidCheckDigits($matrixCnpj));

        $first = $this->postJson('/api/v1/clients', [
            'legal_name' => 'Grupo Alpha LTDA',
            'cnpj' => $matrixCnpj,
            'is_matrix' => true,
        ])->assertCreated();

        $clientId = $first->json('data.client.id');
        $this->assertSame('11222333', $first->json('data.client.root_cnpj'));

        // CNPJ filial mesma raiz (dígitos válidos gerados)
        $branch = $this->validCnpjWithRoot('11222333', '0002');
        $second = $this->postJson('/api/v1/clients', [
            'legal_name' => 'Grupo Alpha Filial',
            'cnpj' => $branch,
            'is_matrix' => false,
        ])->assertCreated();

        $this->assertSame($clientId, $second->json('data.client.id'));
        $this->assertSame(1, Client::query()->count());
        $this->assertSame(2, Establishment::query()->where('client_id', $clientId)->count());
        $this->assertFalse((bool) $second->json('data.establishment.is_matrix'));
    }

    public function test_raiz_coerente_entre_estabelecimento_e_cliente(): void
    {
        $office = Office::factory()->create();
        $client = Client::factory()->forOffice($office)->create(['root_cnpj' => '11222333']);
        $est = Establishment::factory()->forClient($client, '11222333000181')->create();

        $this->assertSame($client->root_cnpj, substr($est->cnpj, 0, 8));
        $this->assertSame($client->office_id, $est->office_id);
    }

    /**
     * @return array{0: Office, 1: User}
     */
    private function officeUser(OfficeRole $role): array
    {
        $office = Office::factory()->create();
        $user = User::factory()->forOffice($office, $role)->withTwoFactorConfirmed()->create();

        return [$office, $user];
    }

    private function validCnpjWithRoot(string $root8, string $order4): string
    {
        $base = strtoupper($root8.$order4);
        $this->assertSame(12, strlen($base));
        $d1 = $this->checkDigit($base, [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]);
        $d2 = $this->checkDigit($base.$d1, [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]);

        return $base.$d1.$d2;
    }

    /**
     * @param  list<int>  $weights
     */
    private function checkDigit(string $base, array $weights): string
    {
        $sum = 0;
        for ($i = 0, $len = strlen($base); $i < $len; $i++) {
            $sum += (ord($base[$i]) - 48) * $weights[$i];
        }
        $mod = $sum % 11;
        $digit = $mod < 2 ? 0 : 11 - $mod;

        return (string) $digit;
    }
}

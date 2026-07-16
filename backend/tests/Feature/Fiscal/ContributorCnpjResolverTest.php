<?php

namespace Tests\Feature\Fiscal;

use App\Models\Client;
use App\Models\Establishment;
use App\Models\Office;
use App\Services\Integra\ContributorCnpjResolver;
use Database\Factories\EstablishmentFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

final class ContributorCnpjResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_same_root_in_different_offices_never_mixes_contributor(): void
    {
        $officeA = Office::factory()->create();
        $officeB = Office::factory()->create();
        $clientA = Client::factory()->forOffice($officeA)->create(['root_cnpj' => '99888777']);
        $clientB = Client::factory()->forOffice($officeB)->create(['root_cnpj' => '99888777']);

        $cnpjA = EstablishmentFactory::cnpjWithRoot('99888777', '0001');
        $cnpjB = EstablishmentFactory::cnpjWithRoot('99888777', '0002');
        Establishment::factory()->forClient($clientA, $cnpjA)->create();
        Establishment::factory()->forClient($clientB, $cnpjB)->create();

        $resolver = app(ContributorCnpjResolver::class);
        $this->assertSame($cnpjA, $resolver->resolve($clientA));
        $this->assertSame($cnpjB, $resolver->resolve($clientB));
    }

    public function test_root_without_full_establishment_fails_closed(): void
    {
        $office = Office::factory()->create();
        $client = Client::factory()->forOffice($office)->create(['root_cnpj' => '99888777']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('CNPJ completo do contribuinte não encontrado.');
        app(ContributorCnpjResolver::class)->resolve($client);
    }
}

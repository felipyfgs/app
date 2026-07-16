<?php

namespace Tests\Feature\Serpro;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class SerproContractCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_uses_a_non_conflicting_serpro_environment_option(): void
    {
        $this->artisan('serpro:contract', [
            'action' => 'list',
            '--serpro-env' => 'PRODUCTION',
        ])
            ->expectsOutput('Nenhum contrato para PRODUCTION')
            ->assertSuccessful();
    }
}

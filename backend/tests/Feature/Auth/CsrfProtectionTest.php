<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;
use Tests\TestCase;

class CsrfProtectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_csrf_e_stateful_api_configurados(): void
    {
        $this->assertTrue(class_exists(ValidateCsrfToken::class));
        $this->assertTrue(class_exists(EnsureFrontendRequestsAreStateful::class));

        // statefulApi() registra o middleware Sanctum no grupo api
        $api = app('router')->getMiddlewareGroups()['api'] ?? [];
        $serialized = json_encode($api);
        $this->assertTrue(
            str_contains((string) $serialized, 'EnsureFrontendRequestsAreStateful')
            || str_contains((string) $serialized, 'stateful')
            || collect($api)->contains(fn ($m) => is_string($m) && str_contains($m, 'Sanctum'))
        );

        $domains = config('sanctum.stateful');
        $this->assertNotEmpty($domains);
        $this->assertTrue(
            collect($domains)->contains(fn ($d) => str_contains((string) $d, 'localhost'))
        );
    }
}

<?php

namespace Tests\Concerns;

trait InteractsWithSpaAuth
{
    protected function asSpa(): static
    {
        return $this->withHeaders([
            'Origin' => 'http://localhost',
            'Referer' => 'http://localhost/',
            'Accept' => 'application/json',
        ]);
    }
}

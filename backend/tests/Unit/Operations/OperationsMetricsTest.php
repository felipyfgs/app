<?php

namespace Tests\Unit\Operations;

use App\Services\Operations\OperationsMetrics;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class OperationsMetricsTest extends TestCase
{
    public function test_increment_descarta_labels_de_alta_cardinalidade(): void
    {
        Log::spy();

        $metrics = new OperationsMetrics;
        $metrics->increment('ops.test', 1, [
            'channel' => 'serpro_http',
            'result' => 'OK',
            'cnpj' => '11222333000181',
            'access_key' => str_repeat('1', 44),
        ]);

        Log::shouldHaveReceived('info')->withArgs(function ($msg, $ctx = null) {
            if ($msg !== 'metrics.counter') {
                return true;
            }
            $labels = is_array($ctx) ? ($ctx['labels'] ?? []) : [];

            return ($labels['channel'] ?? null) === 'serpro_http'
                && ($labels['result'] ?? null) === 'OK'
                && ! array_key_exists('cnpj', $labels)
                && ! array_key_exists('access_key', $labels);
        })->atLeast()->once();
    }

    public function test_observe_latency_emite_histogram(): void
    {
        Log::spy();

        $metrics = new OperationsMetrics;
        $metrics->observeLatency('ops.latency', 250, [
            'channel' => 'fiscal_monitoring',
            'result' => 'SUCCESS',
        ]);

        Log::shouldHaveReceived('info')->withArgs(function ($msg, $ctx = null) {
            return $msg === 'metrics.histogram'
                && is_array($ctx)
                && ($ctx['name'] ?? null) === 'ops.latency'
                && ($ctx['value_ms'] ?? null) === 250;
        })->atLeast()->once();
    }
}

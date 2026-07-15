<?php

namespace App\Jobs\Fiscal;

use App\Services\FiscalMonitoring\FiscalMonitoringRunService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Executa run de monitoramento com revalidação pré-chamada no service.
 * Identidade lógica está na run (idempotency_key); requeue cria continuação.
 */
class ExecuteFiscalMonitoringRunJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout;

    public function __construct(
        public int $fiscalMonitoringRunId,
    ) {
        $this->timeout = max(60, (int) config('fiscal_monitoring.job.timeout_seconds', 300));
        $this->onQueue((string) config('fiscal_monitoring.job.queue', 'default'));
    }

    public function handle(FiscalMonitoringRunService $runs): void
    {
        try {
            $runs->execute($this->fiscalMonitoringRunId);
        } catch (Throwable $e) {
            Log::warning('fiscal_monitoring.run_job_failed', [
                'run_id' => $this->fiscalMonitoringRunId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [15, 60, 120];
    }
}

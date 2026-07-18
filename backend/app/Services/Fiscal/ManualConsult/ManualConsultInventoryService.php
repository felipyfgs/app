<?php

namespace App\Services\Fiscal\ManualConsult;

use App\Enums\ManualConsultEligibility;
use App\Models\Client;
use App\Models\FiscalMonitoringRun;
use App\Models\Office;

/**
 * Inventário tenant-scoped de consultas manuais + elegibilidade sanitizada.
 * GET nunca dispara SERPRO.
 */
final class ManualConsultInventoryService
{
    public function __construct(
        private readonly ManualConsultActionCatalog $catalog,
        private readonly ManualConsultEligibilityGate $eligibility,
    ) {}

    /**
     * @return array{
     *   actions: list<array<string, mixed>>,
     *   meta: array{total: int, ready: int, client_id: int|null, serpro_called: false}
     * }
     */
    public function inventory(
        Office $office,
        ?Client $client = null,
        ?string $surfaceKey = null,
        ?string $moduleKey = null,
    ): array {
        $environment = $this->eligibility->environment();
        $auth = $this->eligibility->authorizationFor($office, $environment);
        $hasToken = $this->eligibility->hasUsableToken($auth);

        $actions = [];
        $ready = 0;

        foreach ($this->catalog->all() as $def) {
            if ($surfaceKey !== null && $surfaceKey !== '' && $def->surfaceKey !== $surfaceKey) {
                continue;
            }
            if ($moduleKey !== null && $moduleKey !== '' && $def->moduleKey !== $moduleKey) {
                continue;
            }

            $status = $this->eligibility->evaluateWithContext(
                $office,
                $def,
                $hasToken,
                $client,
                $auth,
                $environment,
            );
            if ($status === ManualConsultEligibility::Ready) {
                $ready++;
            }

            $actions[] = $this->toPublicAction($def, $status, $office, $client);
        }

        return [
            'actions' => $actions,
            'meta' => [
                'total' => count($actions),
                'ready' => $ready,
                'client_id' => $client?->id,
                'serpro_called' => false,
            ],
        ];
    }

    public function evaluateFor(
        Office $office,
        ManualConsultActionDefinition $def,
        ?Client $client = null,
    ): ManualConsultEligibility {
        return $this->eligibility->evaluate($office, $def, $client);
    }

    /**
     * @return array<string, mixed>
     */
    private function toPublicAction(
        ManualConsultActionDefinition $def,
        ManualConsultEligibility $eligibility,
        Office $office,
        ?Client $client,
    ): array {
        return [
            'action_id' => $def->actionId,
            'label' => $def->label,
            'surface_key' => $def->surfaceKey,
            'module_key' => $def->moduleKey,
            'module_route' => $def->moduleRoute,
            'eligibility' => $eligibility->value,
            'eligibility_label' => $eligibility->label(),
            'executable' => $eligibility->isExecutable(),
            'async' => $def->async,
            'params_schema' => $def->paramsSchema,
            'last_result_summary' => $client !== null
                ? $this->lastResultSummary($office, $client, $def)
                : null,
            'operation_hint' => $def->operationKey,
        ];
    }

    /**
     * @return array{status: string|null, observed_at: string|null, run_id: int|null}|null
     */
    private function lastResultSummary(
        Office $office,
        Client $client,
        ManualConsultActionDefinition $def,
    ): ?array {
        $codes = $def->runCodes;
        if ($codes === null) {
            return null;
        }

        $q = FiscalMonitoringRun::query()
            ->withoutGlobalScopes()
            ->where('office_id', $office->id)
            ->where('client_id', $client->id)
            ->where('system_code', $codes['system'])
            ->where('service_code', $codes['service']);
        if (($codes['operation'] ?? '') !== '') {
            $q->where('operation_code', $codes['operation']);
        }
        $run = $q->orderByDesc('id')->first();

        if ($run === null) {
            return null;
        }

        return [
            'status' => $run->status?->value ?? (string) $run->status,
            'observed_at' => $run->finished_at?->toIso8601String()
                ?? $run->updated_at?->toIso8601String(),
            'run_id' => $run->id,
        ];
    }
}

<?php

namespace App\Services\Serpro;

use App\Enums\SerproCapabilityDriver;
use App\Enums\SerproEnvironment;
use App\Enums\SerproFunctionalRoute;
use App\Models\Client;
use App\Models\FiscalMonitoringRun;
use App\Models\Office;
use App\Models\OfficeSerproAuthorization;
use App\Services\FiscalMonitoring\FiscalMonitoringRunService;
use App\Support\FeatureFlags;
use RuntimeException;
use Throwable;

final class SerproInitialMailboxSyncDispatcher
{
    public function __construct(
        private readonly CapabilityDriverResolver $drivers,
        private readonly SerproProductionEgressGate $egressGate,
        private readonly FiscalMonitoringRunService $runs,
    ) {}

    /**
     * @return array{
     *   state: 'queued'|'action_required',
     *   code: string|null,
     *   message: string|null,
     *   run: FiscalMonitoringRun|null
     * }
     */
    public function dispatchIfAllowed(
        Office $office,
        OfficeSerproAuthorization $authorization,
        string $idempotencyKey,
        ?int $actorUserId,
        ?string $correlationId,
    ): array {
        if (! FeatureFlags::isGloballyEnabled() && ! (bool) config('fiscal_monitoring.enabled', false)) {
            return $this->blocked('FEATURE_DISABLED', 'Monitoramento fiscal está desabilitado.');
        }

        if (! FeatureFlags::isModuleEnabled('mailbox', (int) $office->id)
            && ! (bool) config('features.modules.mailbox.enabled', false)) {
            return $this->blocked('MAILBOX_FEATURE_DISABLED', 'Módulo Caixa Postal está desabilitado para este tenant.');
        }

        try {
            $driver = $this->drivers->forCapability('mailbox');
        } catch (Throwable $e) {
            return $this->blocked('MAILBOX_CAPABILITY_BLOCKED', $this->sanitize($e->getMessage()));
        }

        if ($driver !== SerproCapabilityDriver::Real) {
            return $this->blocked('MAILBOX_CAPABILITY_BLOCKED', 'Capability Caixa Postal não está em driver real.');
        }

        $egress = $this->egressGate->evaluateBillableEgress(
            route: SerproFunctionalRoute::Consultar,
            office: $office,
            environment: SerproEnvironment::Production,
        );
        if (! $egress['allowed']) {
            return $this->blocked(
                $egress['code'] ?? 'EGRESS_BLOCKED',
                $egress['message'] ?? 'Gate operacional bloqueou a sincronização inicial.',
            );
        }

        $required = $authorization->computeActionsRequired();
        if ($required !== []) {
            $first = $required[0];

            return $this->blocked(
                (string) ($first['code'] ?? 'AUTHORITY_REQUIRED'),
                (string) ($first['message'] ?? 'Autoridade oficial pendente para consultar Caixa Postal.'),
            );
        }

        if (! $authorization->status->allowsExternalCalls()) {
            return $this->blocked('AUTHORITY_REQUIRED', 'Termo/procurador/poder oficial ainda não permite consulta.');
        }

        $client = Client::query()
            ->withoutGlobalScopes()
            ->where('office_id', $office->id)
            ->where('is_active', true)
            ->orderBy('id')
            ->first();

        if ($client === null) {
            return $this->blocked('CLIENT_REQUIRED', 'Nenhum cliente ativo disponível para a primeira consulta da Caixa Postal.');
        }

        try {
            $run = $this->runs->enqueueManual(
                office: $office,
                client: $client,
                systemCode: 'INTEGRA_CAIXAPOSTAL',
                serviceCode: 'CAIXA_POSTAL',
                operationCode: 'LISTAR',
                actorId: $actorUserId,
                correlationId: 'serpro-prod-onboarding-'.$idempotencyKey,
                dispatch: true,
            );

            return [
                'state' => 'queued',
                'code' => null,
                'message' => null,
                'run' => $run,
            ];
        } catch (RuntimeException $e) {
            return $this->blocked('MAILBOX_DISPATCH_FAILED', $this->sanitize($e->getMessage()));
        }
    }

    /**
     * @return array{state: 'action_required', code: string, message: string, run: null}
     */
    private function blocked(string $code, string $message): array
    {
        return [
            'state' => 'action_required',
            'code' => strtoupper($code),
            'message' => mb_substr($this->sanitize($message), 0, 500),
            'run' => null,
        ];
    }

    private function sanitize(string $message): string
    {
        $message = preg_replace('/Bearer\s+\S+/i', 'Bearer [redacted]', $message) ?? $message;
        $message = preg_replace('/\b[A-Za-z0-9+\/]{40,}={0,2}\b/', '[redacted]', $message) ?? $message;
        $message = preg_replace('/(secret|password|senha|pfx|jwt|xml)[^,;. ]*/i', '$1=[redacted]', $message) ?? $message;

        return $message;
    }
}

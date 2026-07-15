<?php

namespace App\Services\Integra\Sitfis;

use App\Models\Client;
use App\Models\FiscalMonitoringRun;
use App\Models\FiscalSnapshot;
use App\Models\Office;
use App\Services\FiscalMonitoring\FiscalIdempotency;
use App\Services\FiscalMonitoring\FiscalMonitoringRunService;
use App\Support\FeatureFlags;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * TTL/cache SITFIS: devolve snapshot existente com idade; só enfileira nova chamada se expirado/force.
 */
final class SitfisSnapshotService
{
    public function __construct(
        private readonly FiscalMonitoringRunService $runs,
    ) {}

    /**
     * Snapshot atual + metadados de idade/validade (sem nova chamada).
     *
     * @return array{
     *     snapshot: ?FiscalSnapshot,
     *     age_seconds: ?int,
     *     observed_at: ?string,
     *     expires_at: ?string,
     *     ttl_seconds: int,
     *     is_within_ttl: bool,
     *     is_negative_certificate: bool,
     *     disclaimer: ?string,
     *     active_run: ?array<string, mixed>
     * }
     */
    public function current(Office $office, Client $client): array
    {
        $this->assertTenant($office, $client);
        $ttl = $this->ttlSeconds();
        $system = $this->systemCode();
        $service = $this->serviceCode();

        $snapshot = FiscalSnapshot::query()
            ->withoutGlobalScopes()
            ->where('office_id', $office->id)
            ->where('client_id', $client->id)
            ->where('system_code', $system)
            ->where('service_code', $service)
            ->where('is_current', true)
            ->whereNotNull('evidence_artifact_id')
            ->orderByDesc('id')
            ->first();

        $now = CarbonImmutable::now();
        $age = null;
        $expiresAt = null;
        $within = false;
        $disclaimer = null;
        $isNeg = false;

        if ($snapshot !== null && $snapshot->observed_at !== null) {
            $age = (int) max(0, $snapshot->observed_at->diffInSeconds($now));
            $expiresAt = $snapshot->observed_at->addSeconds($ttl);
            $within = $age < $ttl;
            $normalized = $snapshot->normalized ?? [];
            $disclaimer = isset($normalized['disclaimer']) ? (string) $normalized['disclaimer'] : null;
            $isNeg = (bool) ($normalized['is_negative_certificate'] ?? false);
        }

        $activeRun = FiscalMonitoringRun::query()
            ->withoutGlobalScopes()
            ->where('office_id', $office->id)
            ->where('client_id', $client->id)
            ->where('system_code', $system)
            ->where('service_code', $service)
            ->whereIn('status', ['QUEUED', 'RUNNING', 'REQUEUED'])
            ->orderByDesc('id')
            ->first();

        // REQUEUED é terminal no parent; continuação fica QUEUED — também buscar PROCESSING situation
        if ($activeRun === null) {
            $activeRun = FiscalMonitoringRun::query()
                ->withoutGlobalScopes()
                ->where('office_id', $office->id)
                ->where('client_id', $client->id)
                ->where('system_code', $system)
                ->where('service_code', $service)
                ->where('situation', 'PROCESSING')
                ->whereNull('finished_at')
                ->orderByDesc('id')
                ->first();
        }

        return [
            'snapshot' => $snapshot,
            'age_seconds' => $age,
            'observed_at' => $snapshot?->observed_at?->toIso8601String(),
            'expires_at' => $expiresAt?->toIso8601String(),
            'ttl_seconds' => $ttl,
            'is_within_ttl' => $within,
            'is_negative_certificate' => $isNeg,
            'disclaimer' => $disclaimer,
            'active_run' => $activeRun?->toPublicArray(),
        ];
    }

    /**
     * Enfileira monitoramento se snapshot expirado, ausente ou force=true.
     * Abertura de tela (force=false + dentro do TTL) NÃO cria chamada.
     *
     * @return array{run: ?FiscalMonitoringRun, reused_snapshot: bool, enqueued: bool, reason: string, view: array<string, mixed>}
     */
    public function refresh(
        Office $office,
        Client $client,
        bool $force = false,
        ?int $actorId = null,
        bool $dispatch = true,
    ): array {
        $this->assertTenant($office, $client);

        if (! FeatureFlags::isModuleEnabled('sitfis', $office->id)
            && ! (bool) config('fiscal_monitoring.enabled', false)
            && ! FeatureFlags::isGloballyEnabled()) {
            throw new RuntimeException('Módulo SITFIS desabilitado.');
        }

        $view = $this->current($office, $client);

        if (! $force && $view['is_within_ttl'] && $view['snapshot'] !== null) {
            return [
                'run' => null,
                'reused_snapshot' => true,
                'enqueued' => false,
                'reason' => 'WITHIN_TTL',
                'view' => $this->publicView($view),
            ];
        }

        // Já há execução em andamento — não duplica
        if ($view['active_run'] !== null) {
            $status = $view['active_run']['status'] ?? null;
            if (in_array($status, ['QUEUED', 'RUNNING'], true)) {
                return [
                    'run' => null,
                    'reused_snapshot' => $view['snapshot'] !== null,
                    'enqueued' => false,
                    'reason' => 'ALREADY_RUNNING',
                    'view' => $this->publicView($view),
                ];
            }
        }

        $correlation = (string) Str::uuid();
        $run = $this->runs->enqueueManual(
            office: $office,
            client: $client,
            systemCode: $this->systemCode(),
            serviceCode: $this->serviceCode(),
            operationCode: $this->operationCode(),
            actorId: $actorId,
            correlationId: $correlation,
            dispatch: $dispatch,
        );

        $view = $this->current($office, $client);

        return [
            'run' => $run,
            'reused_snapshot' => false,
            'enqueued' => true,
            'reason' => $force ? 'FORCE' : 'TTL_EXPIRED_OR_MISSING',
            'view' => $this->publicView($view),
        ];
    }

    /**
     * @param  array<string, mixed>  $view
     * @return array<string, mixed>
     */
    public function publicView(array $view): array
    {
        /** @var ?FiscalSnapshot $snapshot */
        $snapshot = $view['snapshot'];

        return [
            'snapshot' => $snapshot?->toPublicArray(),
            'age_seconds' => $view['age_seconds'],
            'observed_at' => $view['observed_at'],
            'expires_at' => $view['expires_at'],
            'ttl_seconds' => $view['ttl_seconds'],
            'is_within_ttl' => $view['is_within_ttl'],
            'is_negative_certificate' => false, // hard rule: API nunca afirma certidão
            'disclaimer' => $view['disclaimer'] ?? 'Ausência de pendência reconhecida não equivale a certidão negativa.',
            'active_run' => $view['active_run'],
            'cache_key_hint' => FiscalIdempotency::cacheKey(
                (int) ($snapshot?->office_id ?? 0),
                'sitfis',
                'snap',
                (string) ($snapshot?->client_id ?? 0),
            ),
        ];
    }

    private function assertTenant(Office $office, Client $client): void
    {
        if ((int) $client->office_id !== (int) $office->id) {
            throw new RuntimeException('Cliente não pertence ao escritório ativo.');
        }
    }

    private function ttlSeconds(): int
    {
        return max(60, (int) config('fiscal_monitoring.sitfis.snapshot_ttl_seconds', 86400));
    }

    private function systemCode(): string
    {
        return (string) config('fiscal_monitoring.sitfis.system_code', 'INTEGRA_SITFIS');
    }

    private function serviceCode(): string
    {
        return (string) config('fiscal_monitoring.sitfis.service_code', 'SITFIS');
    }

    private function operationCode(): string
    {
        return (string) config('fiscal_monitoring.sitfis.operation_code', 'MONITOR');
    }
}

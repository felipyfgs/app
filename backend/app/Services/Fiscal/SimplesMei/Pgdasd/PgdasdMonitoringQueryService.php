<?php

namespace App\Services\Fiscal\SimplesMei\Pgdasd;

use App\Enums\FiscalRunStatus;
use App\Enums\PgdasdDeclarationState;
use App\Enums\PgdasdOperationKind;
use App\Jobs\Fiscal\ExecuteFiscalMonitoringRunJob;
use App\Models\Client;
use App\Models\FiscalMonitoringRun;
use App\Models\Office;
use App\Models\PgdasdArtifact;
use App\Models\PgdasdOperation;
use App\Models\PgdasdRbt12Projection;
use App\Models\TaxObligationDefinition;
use App\Models\TaxObligationProjection;
use App\Services\FiscalMonitoring\FiscalMonitoringRunService;
use RuntimeException;

/**
 * Consultas tenant-scoped da carteira/histórico PGDAS-D (somente leitura local).
 */
final class PgdasdMonitoringQueryService
{
    public function __construct(
        private readonly FiscalMonitoringRunService $runs,
        private readonly PgdasdOperationProjector $projector,
        private readonly PgdasdCommunicationService $communication,
    ) {}

    /**
     * Detalhe de carteira por cliente (para ModulePortfolio detail).
     *
     * @param  list<int>  $clientIds
     * @return array<int, array<string, mixed>>
     */
    public function portfolioDetails(Office $office, array $clientIds): array
    {
        if ($clientIds === []) {
            return [];
        }

        $tz = is_string($office->timezone) && $office->timezone !== ''
            ? $office->timezone
            : 'America/Sao_Paulo';
        $expectedPa = PgdasdPeriod::toPeriodoApuracao(PgdasdPeriod::expectedPa(null, $tz));
        $periodKey = PgdasdPeriod::periodKeyFromPeriodoApuracao($expectedPa);

        $definition = TaxObligationDefinition::query()->where('code', 'PGDAS_D')->first();
        $projections = TaxObligationProjection::query()
            ->withoutGlobalScopes()
            ->where('office_id', $office->id)
            ->whereIn('client_id', $clientIds)
            ->where('period_key', $periodKey)
            ->where('obligation_definition_id', $definition?->id ?? 0)
            ->get()
            ->keyBy('client_id');

        $communications = $this->communication->summariesForClients($office, $clientIds);

        $lastOps = PgdasdOperation::query()
            ->withoutGlobalScopes()
            ->where('office_id', $office->id)
            ->whereIn('client_id', $clientIds)
            ->where('kind', 'DECLARATION')
            ->orderByDesc('transmitted_at')
            ->orderByDesc('declaration_number')
            ->get()
            ->groupBy('client_id');

        $rbt12 = PgdasdRbt12Projection::query()
            ->withoutGlobalScopes()
            ->with('projection')
            ->where('office_id', $office->id)
            ->whereIn('client_id', $clientIds)
            ->orderByDesc('id')
            ->get()
            ->groupBy('client_id');

        $map = [];
        foreach ($clientIds as $cid) {
            $proj = $projections->get($cid);
            $clientOps = $lastOps->get($cid, collect());
            $lastDecl = $clientOps->first(
                static fn (PgdasdOperation $op) => $op->period_key === $periodKey
            ) ?? $clientOps->first();
            $displayPeriodKey = $lastDecl?->period_key ?: $periodKey;
            $latestRbt = $rbt12->get($cid, collect())->first(
                static fn (PgdasdRbt12Projection $item): bool => $item->projection?->period_key === $displayPeriodKey
            );

            $state = $proj?->pgdasd_declaration_state?->value
                ?? PgdasdDeclarationState::Unverified->value;
            $lastPublic = $lastDecl?->toPublicArray();
            $rbtPublic = $latestRbt?->toPublicArray();
            $comm = $communications[$cid];

            $map[$cid] = [
                'module_key' => 'simples_mei',
                'submodule' => 'PGDASD',
                'expected_periodo_apuracao' => $expectedPa,
                'expected_period_key' => $periodKey,
                'period_key' => $periodKey,
                'declaration_state' => $state,
                'declaration_state_reason' => $this->stateReason($proj),
                'last_declaration' => $lastPublic,
                'latest_declaration' => $lastPublic === null ? null : [
                    'period_key' => $lastPublic['period_key'] ?? null,
                    'declaration_number' => $lastPublic['numero_declaracao'] ?? null,
                    'number' => $lastPublic['numero_declaracao'] ?? null,
                    'operation_type' => $lastPublic['operation_kind'] ?? null,
                    'transmitted_at' => $lastPublic['transmitted_at'] ?? null,
                ],
                'rbt12' => $rbtPublic,
                'last_productive_consulted_at' => $proj?->last_valid_query_at?->toIso8601String(),
                'last_valid_query_at' => $proj?->last_valid_query_at?->toIso8601String(),
                'calendar_verified' => (bool) ($proj?->pgdasd_calendar_verified ?? false),
                'communication' => $comm,
                'links' => [
                    'history' => "/api/v1/fiscal/simples-mei/pgdasd/clients/{$cid}/history",
                    'preferences' => "/api/v1/fiscal/simples-mei/pgdasd/clients/{$cid}/communication-preference",
                    'preview' => "/api/v1/fiscal/simples-mei/pgdasd/clients/{$cid}/communication-preview",
                    'tracking' => "/api/v1/fiscal/simples-mei/pgdasd/clients/{$cid}/communications",
                ],
            ];
        }

        return $map;
    }

    /**
     * Histórico local — contrato SPA (periods + provenance). Sem SERPRO.
     *
     * @return array<string, mixed>
     */
    public function history(Office $office, Client $client, ?int $year = null): array
    {
        $this->assertClient($office, $client);
        if ($year !== null && ($year < 2000 || $year > 2100)) {
            throw new RuntimeException('Ano do histórico inválido.');
        }
        $definitionId = TaxObligationDefinition::query()->where('code', 'PGDAS_D')->value('id');

        $operations = PgdasdOperation::query()
            ->withoutGlobalScopes()
            ->where('office_id', $office->id)
            ->where('client_id', $client->id)
            ->whereHas('projection', fn ($query) => $query->where('obligation_definition_id', $definitionId ?? 0))
            ->when($year !== null, function ($q) use ($year): void {
                $q->where('period_key', 'like', sprintf('%04d-%%', $year));
            })
            ->orderByDesc('period_key')
            ->orderByDesc('transmitted_at')
            ->orderByDesc('id')
            ->get();

        $artifacts = PgdasdArtifact::query()
            ->withoutGlobalScopes()
            ->with('evidenceArtifact')
            ->where('office_id', $office->id)
            ->where('client_id', $client->id)
            ->whereHas('projection', function ($query) use ($definitionId, $year): void {
                $query->where('obligation_definition_id', $definitionId ?? 0)
                    ->when($year !== null, fn ($inner) => $inner->where('period_key', 'like', sprintf('%04d-%%', $year)));
            })
            ->orderByDesc('observed_at')
            ->get();

        $rbt12 = PgdasdRbt12Projection::query()
            ->withoutGlobalScopes()
            ->where('office_id', $office->id)
            ->where('client_id', $client->id)
            ->whereHas('projection', function ($query) use ($definitionId, $year): void {
                $query->where('obligation_definition_id', $definitionId ?? 0)
                    ->when($year !== null, fn ($inner) => $inner->where('period_key', 'like', sprintf('%04d-%%', $year)));
            })
            ->orderByDesc('id')
            ->get();

        $proj = $this->currentProjection($office, $client);
        $tz = is_string($office->timezone) && $office->timezone !== ''
            ? $office->timezone
            : 'America/Sao_Paulo';
        $expectedPeriodKey = PgdasdPeriod::toPeriodKey(PgdasdPeriod::expectedPa(null, $tz));

        // Agrupa por period_key no formato esperado pelo SPA
        $byPeriod = [];
        foreach ($operations as $op) {
            $pk = (string) ($op->period_key ?: 'unknown');
            if (! isset($byPeriod[$pk])) {
                $byPeriod[$pk] = [
                    'period_key' => $pk,
                    'declaration_state' => null,
                    'last_valid_query_at' => null,
                    'declarations' => [],
                    'das' => [],
                    'documents' => [],
                    'artifacts' => [],
                    'rbt12' => null,
                ];
            }
            if ($op->operationKind() === PgdasdOperationKind::Declaration) {
                $byPeriod[$pk]['declarations'][] = [
                    'period_key' => $op->period_key,
                    'declaration_number' => $op->declaration_number,
                    'number' => $op->declaration_number,
                    'operation_type' => $op->normalized_operation_type ?? $op->raw_operation_type,
                    'transmitted_at' => $op->transmitted_at?->toIso8601String(),
                    'malha' => $op->malha,
                ];
            } elseif ($op->operationKind() === PgdasdOperationKind::Das) {
                $byPeriod[$pk]['das'][] = [
                    'das_number' => $op->das_number,
                    'issued_at' => $op->issued_at?->toIso8601String(),
                    'payment_located' => $op->payment_located,
                    'payment_observation' => $op->payment_located === false
                        ? 'Pagamento não localizado até a consulta.'
                        : ($op->payment_located === true ? 'Pagamento localizado até a consulta.' : null),
                    'payment_observed_at' => $op->payment_observed_at?->toIso8601String(),
                ];
            }
        }

        foreach ($artifacts as $art) {
            $pk = is_array($art->metadata) ? (string) ($art->metadata['period_key'] ?? '') : '';
            if ($pk === '' || ! isset($byPeriod[$pk])) {
                // artefatos sem PA conhecido entram no PA esperado ou bucket vazio
                $pk = $expectedPeriodKey;
                if (! isset($byPeriod[$pk])) {
                    $byPeriod[$pk] = [
                        'period_key' => $pk,
                        'declaration_state' => null,
                        'last_valid_query_at' => null,
                        'declarations' => [],
                        'das' => [],
                        'documents' => [],
                        'artifacts' => [],
                        'rbt12' => null,
                    ];
                }
            }
            $doc = $art->toPublicArray();
            $byPeriod[$pk]['documents'][] = $doc;
            $byPeriod[$pk]['artifacts'][] = $doc;
        }

        foreach ($rbt12 as $r) {
            $pk = is_array($r->metadata) ? (string) ($r->metadata['period_key'] ?? '') : '';
            if ($pk === '') {
                $pk = $expectedPeriodKey;
            }
            if (! isset($byPeriod[$pk])) {
                $byPeriod[$pk] = [
                    'period_key' => $pk,
                    'declaration_state' => null,
                    'last_valid_query_at' => null,
                    'declarations' => [],
                    'das' => [],
                    'documents' => [],
                    'artifacts' => [],
                    'rbt12' => null,
                ];
            }
            if ($byPeriod[$pk]['rbt12'] === null) {
                $byPeriod[$pk]['rbt12'] = $r->toPublicArray();
            }
        }

        // Estado do PA esperado na projeção
        $state = $proj?->pgdasd_declaration_state?->value
            ?? PgdasdDeclarationState::Unverified->value;
        $lastValid = $proj?->last_valid_query_at?->toIso8601String();
        if (isset($byPeriod[$expectedPeriodKey])) {
            $byPeriod[$expectedPeriodKey]['declaration_state'] = $state;
            $byPeriod[$expectedPeriodKey]['declaration_state_reason'] = $this->stateReason($proj);
            $byPeriod[$expectedPeriodKey]['last_valid_query_at'] = $lastValid;
        }

        krsort($byPeriod);
        $periods = array_values($byPeriod);

        return [
            'client' => [
                'id' => $client->id,
                'legal_name' => $client->legal_name,
                'cnpj_masked' => $this->historyCnpjMasked($client),
            ],
            'expected_period_key' => $expectedPeriodKey,
            'declaration_state' => $state,
            'declaration_state_reason' => $this->stateReason($proj),
            'last_valid_query_at' => $lastValid,
            'periods' => $periods,
            'history' => $periods,
            // campos aditivos legados (modal simples)
            'client_id' => $client->id,
            'legal_name' => $client->legal_name,
            'last_productive_consulted_at' => $lastValid,
            'operations' => $operations->map->toPublicArray()->values()->all(),
            'artifacts' => $artifacts->map->toPublicArray()->values()->all(),
            'rbt12_projections' => $rbt12->map->toPublicArray()->values()->all(),
            'provenance' => [
                'source' => 'LOCAL_PROJECTION',
                'serpro_called' => false,
            ],
        ];
    }

    public function findArtifact(Office $office, Client $client, int $artifactId): ?PgdasdArtifact
    {
        return PgdasdArtifact::query()
            ->withoutGlobalScopes()
            ->with('evidenceArtifact')
            ->where('office_id', $office->id)
            ->where('client_id', $client->id)
            ->whereKey($artifactId)
            ->first();
    }

    public function findArtifactForOffice(Office $office, int $artifactId): ?PgdasdArtifact
    {
        return PgdasdArtifact::query()
            ->withoutGlobalScopes()
            ->with('evidenceArtifact')
            ->where('office_id', $office->id)
            ->whereKey($artifactId)
            ->first();
    }

    /**
     * Enfileira coleta documental explícita (14 ou 15) — faturável.
     *
     * @param  array<string, mixed>  $params
     */
    public function enqueueDocumentCollect(
        Office $office,
        Client $client,
        string $operation,
        array $params,
        ?int $actorId,
    ): FiscalMonitoringRun {
        $this->assertClient($office, $client);

        $op = strtoupper($operation);
        $operationCode = match ($op) {
            '14', 'CONSULTIMADECREC', 'CONSULTAR_ULTIMA_DECLARACAO_RECIBO' => 'CONSULTAR_ULTIMA_DECLARACAO_RECIBO',
            '15', 'CONSDECREC', 'CONSULTAR_RECIBO' => 'CONSULTAR_RECIBO',
            default => throw new RuntimeException('Operação documental PGDAS-D inválida.'),
        };

        $periodKey = trim((string) ($params['period_key'] ?? ''));
        try {
            PgdasdPeriod::parse($periodKey);
        } catch (\Throwable) {
            throw new RuntimeException('PA inválido para coleta documental.');
        }
        if ($operationCode === 'CONSULTAR_RECIBO') {
            $declarationNumber = trim((string) ($params['numeroDeclaracao'] ?? ''));
            if ($declarationNumber === '') {
                throw new RuntimeException('Número da declaração é obrigatório para o serviço 15.');
            }

            $observed = PgdasdOperation::query()
                ->withoutGlobalScopes()
                ->where('office_id', $office->id)
                ->where('client_id', $client->id)
                ->where('kind', PgdasdOperationKind::Declaration->value)
                ->where('declaration_number', $declarationNumber)
                ->orderByDesc('transmitted_at')
                ->orderByDesc('id')
                ->first();
            if ($observed === null) {
                throw new RuntimeException(
                    'Declaração não observada em consulta válida do serviço 13 para este cliente.'
                );
            }
            if ((string) $observed->period_key !== $periodKey) {
                throw new RuntimeException('O PA informado não corresponde à declaração observada.');
            }
        }

        $run = $this->runs->enqueueManual(
            office: $office,
            client: $client,
            systemCode: 'INTEGRA_SN',
            serviceCode: 'PGDASD',
            operationCode: $operationCode,
            actorId: $actorId,
            dispatch: false,
        );

        $progress = is_array($run->progress) ? $run->progress : [];
        if (isset($params['periodoApuracao'])) {
            $progress['periodo_apuracao'] = (string) $params['periodoApuracao'];
            try {
                $progress['period_key'] = PgdasdPeriod::periodKeyFromPeriodoApuracao((string) $params['periodoApuracao']);
            } catch (\Throwable) {
                // ignore
            }
        }
        if (isset($params['period_key'])) {
            $progress['period_key'] = (string) $params['period_key'];
        }
        if (isset($params['numeroDeclaracao'])) {
            $progress['numero_declaracao'] = (string) $params['numeroDeclaracao'];
        }
        if (isset($params['numeroDas'])) {
            $progress['numero_das'] = (string) $params['numeroDas'];
        }
        $run->forceFill(['progress' => $progress])->save();
        ExecuteFiscalMonitoringRunJob::dispatch((int) $run->id)
            ->onQueue((string) config('fiscal_monitoring.job.queue', 'default'));

        return $run->fresh() ?? $run;
    }

    public function enqueueAutomaticRbt12Extract(
        PgdasdRbt12Projection $rbt12,
    ): FiscalMonitoringRun {
        $rbt12->loadMissing(['projection', 'client']);
        $projection = $rbt12->projection;
        $client = $rbt12->client;
        $office = Office::query()->find($rbt12->office_id);
        if ($projection === null || $client === null || $office === null
            || (int) $projection->office_id !== (int) $office->id
            || (int) $client->office_id !== (int) $office->id
            || $rbt12->status?->value !== 'PENDING'
            || ! is_string($rbt12->source_das_number)
            || $rbt12->source_das_number === ''
        ) {
            throw new RuntimeException('Reserva RBT12 inválida para consulta do extrato.');
        }

        $originRunId = $rbt12->source_run_id;
        $priorMetadata = is_array($rbt12->metadata) ? $rbt12->metadata : [];
        $priorExtractRunId = isset($priorMetadata['extract_run_id'])
            ? (int) $priorMetadata['extract_run_id']
            : null;

        $correlationId = 'pgdasd-rbt12-'.substr((string) $rbt12->source_reference_key, 0, 50);
        $run = $this->runs->enqueueManual(
            office: $office,
            client: $client,
            systemCode: 'INTEGRA_SN',
            serviceCode: 'PGDASD',
            operationCode: 'CONSULTAR_EXTRATO',
            correlationId: $correlationId,
            dispatch: false,
        );
        $progress = [
            'period_key' => $projection->period_key,
            'periodo_apuracao' => str_replace('-', '', (string) $projection->period_key),
            'numero_das' => $rbt12->source_das_number,
            'rbt12_projection_id' => (int) $rbt12->id,
            'rbt12_source_reference_key' => $rbt12->source_reference_key,
        ];
        $run->forceFill([
            'operation_key' => 'pgdasd.consextrato',
            'progress' => $progress,
        ])->save();

        $metadata = $priorMetadata;
        $metadata['reservation_run_id'] ??= $originRunId;
        $metadata['extract_run_id'] = $run->id;
        $rbt12->forceFill([
            'source_run_id' => $run->id,
            'metadata' => $metadata,
        ])->save();

        // Correlação determinística reutiliza a mesma run: não re-despachar se
        // já estava vinculada e não está FAILED (em voo ou terminal de sucesso).
        $status = $run->status instanceof FiscalRunStatus
            ? $run->status
            : FiscalRunStatus::tryFrom((string) ($run->status ?? ''));
        $alreadyLinked = $priorExtractRunId !== null && $priorExtractRunId === (int) $run->id;

        if ($alreadyLinked && $status !== null && $status !== FiscalRunStatus::Failed) {
            return $run->fresh() ?? $run;
        }

        if ($status !== null && $status !== FiscalRunStatus::Queued) {
            return $run->fresh() ?? $run;
        }

        ExecuteFiscalMonitoringRunJob::dispatch((int) $run->id)
            ->onQueue((string) config('fiscal_monitoring.job.queue', 'default'));

        return $run->fresh() ?? $run;
    }

    private function currentProjection(Office $office, Client $client): ?TaxObligationProjection
    {
        $tz = is_string($office->timezone) && $office->timezone !== ''
            ? $office->timezone
            : 'America/Sao_Paulo';
        $periodKey = PgdasdPeriod::toPeriodKey(PgdasdPeriod::expectedPa(null, $tz));

        $def = TaxObligationDefinition::query()
            ->where('code', 'PGDAS_D')
            ->first();

        $q = TaxObligationProjection::query()
            ->withoutGlobalScopes()
            ->where('office_id', $office->id)
            ->where('client_id', $client->id)
            ->where('period_key', $periodKey);

        $q->where('obligation_definition_id', $def?->id ?? 0);

        return $q->first();
    }

    private function assertClient(Office $office, Client $client): void
    {
        if ((int) $client->office_id !== (int) $office->id) {
            throw new RuntimeException('Cliente não pertence ao escritório ativo.');
        }
    }

    private function stateReason(?TaxObligationProjection $projection): string
    {
        if ($projection === null || $projection->last_valid_query_at === null) {
            return 'NO_VALID_QUERY';
        }
        $metadata = is_array($projection->metadata) ? $projection->metadata : [];
        if (is_string($metadata['pgdasd_declaration_state_reason'] ?? null)) {
            return $metadata['pgdasd_declaration_state_reason'];
        }

        return match ($projection->pgdasd_declaration_state?->value) {
            'CURRENT' => 'EXPECTED_PA_FOUND',
            'DUE_WITHIN_DEADLINE' => 'WITHIN_DEADLINE',
            'OVERDUE_NOT_FOUND' => 'ABSENT_AFTER_VERIFIED_DEADLINE',
            default => 'UNVERIFIED',
        };
    }

    private function maskCnpj(?string $cnpj): string
    {
        $normalized = preg_replace('/[^A-Z0-9]/', '', strtoupper((string) $cnpj)) ?? '';
        if (strlen($normalized) < 8) {
            return '****';
        }

        return substr($normalized, 0, 4)
            .str_repeat('*', max(4, strlen($normalized) - 8))
            .(strlen($normalized) > 8 ? substr($normalized, -4) : '');
    }

    private function historyCnpjMasked(Client $client): string
    {
        $cnpj = $client->establishments()
            ->orderByDesc('is_matrix')
            ->orderBy('id')
            ->value('cnpj');

        return $this->maskCnpj(is_string($cnpj) && $cnpj !== '' ? $cnpj : $client->root_cnpj);
    }
}

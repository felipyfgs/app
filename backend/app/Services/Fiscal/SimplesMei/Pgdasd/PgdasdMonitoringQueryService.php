<?php

namespace App\Services\Fiscal\SimplesMei\Pgdasd;

use App\Enums\PgdasdDeclarationState;
use App\Enums\PgdasdOperationKind;
use App\Models\Client;
use App\Models\ClientCommunicationPreference;
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

        $projections = TaxObligationProjection::query()
            ->withoutGlobalScopes()
            ->where('office_id', $office->id)
            ->whereIn('client_id', $clientIds)
            ->where('period_key', $periodKey)
            ->get()
            ->keyBy('client_id');

        $prefs = ClientCommunicationPreference::query()
            ->withoutGlobalScopes()
            ->where('office_id', $office->id)
            ->whereIn('client_id', $clientIds)
            ->where('module_key', PgdasdCommunicationService::MODULE)
            ->where('submodule_key', PgdasdCommunicationService::SUBMODULE)
            ->get()
            ->keyBy('client_id');

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
            $latestRbt = $rbt12->get($cid, collect())->first();
            $pref = $prefs->get($cid);

            $state = $proj?->pgdasd_declaration_state?->value
                ?? PgdasdDeclarationState::Unverified->value;
            $lastPublic = $lastDecl?->toPublicArray();
            $rbtPublic = $latestRbt?->toPublicArray();
            // lock_version virtual = 1 (default da migration). DB submodule_key canônico: pgdasd
            // (PgdasdCommunicationService::SUBMODULE) — distinto do submodule público PGDASD na carteira.
            $comm = $pref?->toPublicArray() ?? [
                'automatic_requested' => false,
                'automatic_effective' => false,
                'execution_mode' => 'TEMPLATE_ONLY',
                'email_enabled' => false,
                'whatsapp_enabled' => false,
                'lock_version' => 1,
            ];

            $map[$cid] = [
                'module_key' => 'simples_mei',
                'submodule' => 'PGDASD',
                'expected_periodo_apuracao' => $expectedPa,
                'expected_period_key' => $periodKey,
                'period_key' => $periodKey,
                'declaration_state' => $state,
                'last_declaration' => $lastPublic,
                'latest_declaration' => $lastPublic === null ? null : [
                    'period_key' => $lastPublic['period_key'] ?? null,
                    'declaration_number' => $lastPublic['numero_declaracao'] ?? null,
                    'number' => $lastPublic['numero_declaracao'] ?? null,
                    'operation_type' => $lastPublic['operation_kind'] ?? null,
                    'transmitted_at' => $lastPublic['transmitted_at'] ?? null,
                ],
                'rbt12' => $rbtPublic,
                'last_productive_consulted_at' => $proj?->pgdasd_last_productive_consulted_at?->toIso8601String()
                    ?? $proj?->last_valid_query_at?->toIso8601String(),
                'last_valid_query_at' => $proj?->pgdasd_last_productive_consulted_at?->toIso8601String()
                    ?? $proj?->last_valid_query_at?->toIso8601String(),
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

        $operations = PgdasdOperation::query()
            ->withoutGlobalScopes()
            ->where('office_id', $office->id)
            ->where('client_id', $client->id)
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
            ->orderByDesc('observed_at')
            ->get();

        $rbt12 = PgdasdRbt12Projection::query()
            ->withoutGlobalScopes()
            ->where('office_id', $office->id)
            ->where('client_id', $client->id)
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
            $doc = [
                'id' => $art->id,
                'kind' => $art->kind,
                'filename' => $art->filename,
                'content_type' => $art->content_type,
                'observed_at' => $art->observed_at?->toIso8601String(),
                'download_href' => '/api/v1/fiscal/simples-mei/pgdasd/artifacts/'.$art->id.'/download',
            ];
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
            $byPeriod[$pk]['rbt12'] = $r->toPublicArray();
        }

        // Estado do PA esperado na projeção
        $state = $proj?->pgdasd_declaration_state?->value
            ?? PgdasdDeclarationState::Unverified->value;
        $lastValid = $proj?->pgdasd_last_productive_consulted_at?->toIso8601String()
            ?? $proj?->last_valid_query_at?->toIso8601String();
        if (isset($byPeriod[$expectedPeriodKey])) {
            $byPeriod[$expectedPeriodKey]['declaration_state'] = $state;
            $byPeriod[$expectedPeriodKey]['last_valid_query_at'] = $lastValid;
        }

        krsort($byPeriod);
        $periods = array_values($byPeriod);

        return [
            'client' => [
                'id' => $client->id,
                'legal_name' => $client->legal_name,
                'cnpj_masked' => method_exists($client, 'cnpjMasked')
                    ? $client->cnpjMasked()
                    : null,
            ],
            'expected_period_key' => $expectedPeriodKey,
            'declaration_state' => $state,
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
                'source' => 'local_projection',
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
    ): \App\Models\FiscalMonitoringRun {
        $this->assertClient($office, $client);

        $op = strtoupper($operation);
        $operationCode = match ($op) {
            '14', 'CONSULTIMADECREC', 'CONSULTAR_ULTIMA_DECLARACAO_RECIBO' => 'CONSULTAR_ULTIMA_DECLARACAO_RECIBO',
            '15', 'CONSDECREC', 'CONSULTAR_RECIBO' => 'CONSULTAR_RECIBO',
            '16', 'CONSEXTRATO', 'CONSULTAR_EXTRATO' => 'CONSULTAR_EXTRATO',
            default => throw new RuntimeException('Operação documental PGDAS-D inválida.'),
        };

        $run = $this->runs->enqueueManual(
            office: $office,
            client: $client,
            systemCode: 'INTEGRA_SN',
            serviceCode: 'PGDASD',
            operationCode: $operationCode,
            actorId: $actorId,
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

        if ($def !== null) {
            $q->where('obligation_definition_id', $def->id);
        }

        return $q->first();
    }

    private function assertClient(Office $office, Client $client): void
    {
        if ((int) $client->office_id !== (int) $office->id) {
            throw new RuntimeException('Cliente não pertence ao escritório ativo.');
        }
    }
}

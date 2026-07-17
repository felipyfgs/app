<?php

namespace App\Services\Fiscal\SimplesMei\Pgdasd;

use App\DTO\Fiscal\FiscalAdapterRequest;
use App\DTO\Fiscal\FiscalAdapterResult;
use App\DTO\Serpro\IntegraResponse;
use App\Enums\FiscalSituation;
use App\Enums\PgdasdDeclarationState;
use App\Enums\PgdasdRbt12Status;
use App\Models\FiscalEvidenceArtifact;
use App\Models\PgdasdArtifact;
use App\Models\PgdasdRbt12Projection;
use App\Models\TaxObligationDefinition;
use App\Models\TaxObligationProjection;
use App\Services\FiscalMonitoring\FiscalEvidenceStore;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;

/**
 * Orquestra projeção pós-consulta PGDAS-D (13–16): operações, estado, RBT12 e documentos.
 */
final class PgdasdPostConsultService
{
    public function __construct(
        private readonly PgdasdConsDeclaracao13Codec $codec13,
        private readonly PgdasdDocumentCodecs $documentCodecs,
        private readonly PgdasdDocumentSanitizer $sanitizer,
        private readonly PgdasdOperationProjector $projector,
        private readonly PgdasdDeclarationStateResolver $stateResolver,
        private readonly PgdasdRbt12Service $rbt12,
    ) {}

    /**
     * Enriquece o resultado do adapter com projeção e sanitização.
     *
     * @return array{result: FiscalAdapterResult, sanitized_dados: mixed}
     */
    public function handle(
        FiscalAdapterRequest $request,
        IntegraResponse $response,
        FiscalAdapterResult $result,
        string $operationKey,
    ): array {
        if (! $response->success || $result->result->value !== 'SUCCESS') {
            return ['result' => $result, 'sanitized_dados' => $response->dados];
        }

        return match ($operationKey) {
            'pgdasd.consdeclaracao' => $this->handle13($request, $response, $result),
            'pgdasd.consultimadecrec',
            'pgdasd.consdecrec',
            'pgdasd.consextrato' => $this->handleDocumental($request, $response, $result, $operationKey),
            default => ['result' => $result, 'sanitized_dados' => $response->dados],
        };
    }

    /**
     * @return array{result: FiscalAdapterResult, sanitized_dados: mixed}
     */
    private function handle13(
        FiscalAdapterRequest $request,
        IntegraResponse $response,
        FiscalAdapterResult $result,
    ): array {
        $normalized = is_array($result->normalized) ? $result->normalized : [];
        $productive = $response->isProductiveEvidence();
        $simulated = $response->simulated || ! $productive;

        try {
            $decoded = $this->codec13->decodeDados($response->dados ?? $response->body['dados'] ?? null);
        } catch (\Throwable $e) {
            Log::warning('pgdasd.consdeclaracao.decode_failed', [
                'run_id' => $request->run->id,
                'reason' => $e->getMessage(),
            ]);
            $normalized['pgdasd'] = [
                'incomplete' => true,
                'declaration_state' => PgdasdDeclarationState::Unverified->value,
                'error' => 'DECODE_FAILED',
            ];

            return [
                'result' => $this->withNormalized($result, $normalized, FiscalSituation::Unknown),
                'sanitized_dados' => $response->dados,
            ];
        }

        $projection = $this->resolveOrCreateProjection($request);
        if ($projection === null) {
            $normalized['pgdasd'] = [
                'incomplete' => true,
                'declaration_state' => PgdasdDeclarationState::Unverified->value,
                'error' => 'PROJECTION_UNAVAILABLE',
            ];

            return [
                'result' => $this->withNormalized($result, $normalized, FiscalSituation::Unknown),
                'sanitized_dados' => $response->dados,
            ];
        }

        $decodedIncomplete = (bool) ($decoded['incomplete'] ?? true);
        $projected = [
            'upserted' => [],
            'projections' => [],
            'last_declaration' => null,
            'incomplete' => $decodedIncomplete,
        ];

        // Resposta incompleta não pode ser projetada (projector lança RuntimeException).
        if (! $decodedIncomplete) {
            try {
                $projected = $this->projector->projectFromDecoded(
                    $request->run,
                    $request->office,
                    $request->client,
                    $decoded,
                );
                $projected['incomplete'] = false;
            } catch (\Throwable $e) {
                Log::warning('pgdasd.consdeclaracao.project_failed', [
                    'run_id' => $request->run->id,
                    'reason' => $e->getMessage(),
                ]);
                $projected['incomplete'] = true;
            }
        }

        $expectedPa = $this->resolveExpectedPa($request);
        $declForPa = null;
        if ($expectedPa !== null) {
            try {
                $periodKey = PgdasdPeriod::periodKeyFromPeriodoApuracao($expectedPa);
                $declForPa = $this->projector->latestDeclarationForPeriod(
                    (int) $request->office->id,
                    (int) $request->client->id,
                    $periodKey,
                );
            } catch (\Throwable) {
                $declForPa = null;
            }
        }

        $lastProductive = $productive ? CarbonImmutable::now() : null;
        if ($productive) {
            $projection->forceFill([
                'pgdasd_last_productive_consulted_at' => $lastProductive,
                'last_valid_query_at' => $lastProductive,
                'last_valid_run_id' => $request->run->id,
            ])->save();
        }

        $responseIncomplete = $projected['incomplete'] || $decodedIncomplete;
        $statePack = $this->stateResolver->resolve(
            declarationForExpectedPa: $declForPa,
            lastProductiveConsultedAt: $productive
                ? ($projection->pgdasd_last_productive_consulted_at ?? $lastProductive)
                : null,
            projection: $projection,
            responseIncomplete: $responseIncomplete,
            simulated: $simulated,
        );

        $projection->forceFill([
            'pgdasd_declaration_state' => $statePack['state'],
            'pgdasd_last_declaration_operation_id' => $projected['last_declaration']?->id
                ?? $declForPa?->id,
            'pgdasd_calendar_version_code' => $statePack['calendar_version_code'],
            'pgdasd_calendar_verified' => $statePack['calendar_verified'],
        ])->save();

        $rbt12New = [];
        if ($productive && ! $responseIncomplete) {
            $rbt12New = $this->rbt12->reserveFromOperations(
                $request->run,
                $projected['upserted'],
            );
            if ($rbt12New !== []) {
                $latest = $rbt12New[array_key_last($rbt12New)];
                $projection->forceFill([
                    'pgdasd_latest_rbt12_projection_id' => $latest->id,
                ])->save();
            }
        }

        $normalized['pgdasd'] = [
            'incomplete' => $responseIncomplete,
            'declaration_state' => $statePack['state']->value,
            'calendar_verified' => $statePack['calendar_verified'],
            'operations_count' => count($projected['upserted']),
            'last_declaration_id' => $projected['last_declaration']?->id,
            'last_declaration_number' => $projected['last_declaration']?->declaration_number,
            'rbt12_pending_ids' => array_map(static fn ($p) => $p->id, $rbt12New),
            'expected_periodo_apuracao' => $expectedPa,
            'productive' => $productive,
            'periods' => array_map(static function (array $p): array {
                return [
                    'periodo_apuracao' => $p['periodo_apuracao'],
                    'period_key' => $p['period_key'],
                    'operations_count' => count($p['operations']),
                    'incomplete' => $p['incomplete'],
                ];
            }, $decoded['periods'] ?? []),
        ];

        $situation = match ($statePack['state']) {
            PgdasdDeclarationState::Current => FiscalSituation::UpToDate,
            PgdasdDeclarationState::DueWithinDeadline => FiscalSituation::Pending,
            PgdasdDeclarationState::OverdueNotFound => FiscalSituation::Pending,
            PgdasdDeclarationState::Unverified => FiscalSituation::Unknown,
        };

        // Operação incompleta ou simulada não promove verde
        if ($responseIncomplete || $simulated) {
            $situation = FiscalSituation::Unknown;
        }

        // Evidence JSON sem Base64 (serviço 13 não tem PDF)
        $evidence = json_encode($normalized, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

        return [
            'result' => new FiscalAdapterResult(
                result: $result->result,
                situation: $situation,
                coverage: $result->coverage,
                evidenceBytes: $evidence,
                evidenceContentType: 'application/json',
                sourceVersion: $result->sourceVersion,
                normalized: $normalized,
                findings: $result->findings,
                itemsProcessed: $result->itemsProcessed,
                pagesProcessed: $result->pagesProcessed,
            ),
            'sanitized_dados' => $response->dados,
        ];
    }

    /**
     * @return array{result: FiscalAdapterResult, sanitized_dados: mixed}
     */
    private function handleDocumental(
        FiscalAdapterRequest $request,
        IntegraResponse $response,
        FiscalAdapterResult $result,
        string $operationKey,
    ): array {
        $periodKey = $request->competence?->period_key
            ?? (string) ($request->context['period_key'] ?? $request->progress['period_key'] ?? '');
        $periodoApuracao = (string) ($request->context['periodoApuracao']
            ?? $request->progress['periodo_apuracao']
            ?? ($periodKey !== '' ? PgdasdPeriod::periodoApuracaoFromPeriodKey($periodKey) : ''));
        $numeroDas = isset($request->context['numeroDas'])
            ? (string) $request->context['numeroDas']
            : (isset($request->progress['numero_das']) ? (string) $request->progress['numero_das'] : null);

        $projection = $this->resolveOrCreateProjection($request);
        if ($projection === null) {
            return ['result' => $result, 'sanitized_dados' => $response->dados];
        }

        $pack = $this->sanitizer->sanitizeAndStore(
            run: $request->run,
            clientId: (int) $request->client->id,
            dados: $response->dados ?? $response->body['dados'] ?? null,
            operationKey: $operationKey,
            projectionId: (int) $projection->id,
            periodKey: $periodKey !== '' ? $periodKey : null,
            periodoApuracao: $periodoApuracao !== '' ? $periodoApuracao : null,
            numeroDas: $numeroDas,
        );

        $normalized = is_array($result->normalized) ? $result->normalized : [];
        $normalized['pgdasd_documents'] = [
            'artifacts' => array_map(
                static fn ($a) => $a->toPublicArray(),
                $pack['artifacts'],
            ),
            'failures' => $pack['failures'],
        ];

        // Resolve RBT12 se for extrato 16 e houver projeção PENDING
        if ($operationKey === 'pgdasd.consextrato' && $pack['evidence'] !== []) {
            $this->resolvePendingRbt12($request, $numeroDas, $pack);
        }

        $evidenceMeta = json_encode([
            'dto' => 'pgdasd_document',
            'operation_key' => $operationKey,
            'artifacts' => $normalized['pgdasd_documents']['artifacts'],
            'failures' => $pack['failures'],
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

        return [
            'result' => new FiscalAdapterResult(
                result: $result->result,
                situation: $result->situation,
                coverage: $result->coverage,
                evidenceBytes: $evidenceMeta,
                evidenceContentType: 'application/json',
                sourceVersion: $result->sourceVersion,
                normalized: $normalized,
                findings: $result->findings,
                itemsProcessed: $result->itemsProcessed,
                pagesProcessed: $result->pagesProcessed,
            ),
            'sanitized_dados' => $pack['sanitized_dados'],
        ];
    }

    /**
     * @param  array{artifacts: list<PgdasdArtifact>, evidence: list<FiscalEvidenceArtifact>, failures: list<array<string, string>>}  $pack
     */
    private function resolvePendingRbt12(FiscalAdapterRequest $request, ?string $numeroDas, array $pack): void
    {
        if ($numeroDas === null || $numeroDas === '' || $pack['evidence'] === []) {
            return;
        }

        $rbt = PgdasdRbt12Projection::query()
            ->withoutGlobalScopes()
            ->where('office_id', $request->office->id)
            ->where('client_id', $request->client->id)
            ->where('source_das_number', $numeroDas)
            ->where('status', PgdasdRbt12Status::Pending)
            ->orderByDesc('id')
            ->first();

        if ($rbt === null) {
            return;
        }

        $artifactId = $pack['artifacts'][0]->id ?? null;
        if ($artifactId === null) {
            return;
        }

        try {
            $bytes = app(FiscalEvidenceStore::class)
                ->readAuthorized($pack['evidence'][0], (int) $request->office->id);
            $this->rbt12->resolveFromPdfBytes(
                $rbt,
                $bytes,
                artifactId: (int) $artifactId,
                sourceRunId: $request->run->id,
            );
        } catch (\Throwable $e) {
            $this->rbt12->markFailed($rbt, 'READ_OR_PARSE_FAILED', $request->run->id);
            Log::warning('pgdasd.rbt12.resolve_failed', [
                'projection_id' => $rbt->id,
                'reason' => $e->getMessage(),
            ]);
        }
    }

    private function resolveExpectedPa(FiscalAdapterRequest $request): ?string
    {
        $fromProgress = $request->progress['expected_periodo_apuracao']
            ?? $request->context['expected_periodo_apuracao']
            ?? $request->progress['periodo_apuracao']
            ?? $request->context['periodoApuracao']
            ?? null;
        if (is_string($fromProgress) && preg_match('/^\d{6}$/', $fromProgress) === 1) {
            return $fromProgress;
        }

        $periodKey = $request->competence?->period_key
            ?? (string) ($request->progress['period_key'] ?? $request->context['period_key'] ?? '');
        if ($periodKey !== '') {
            try {
                return PgdasdPeriod::periodoApuracaoFromPeriodKey($periodKey);
            } catch (\Throwable) {
                return null;
            }
        }

        $tz = (string) ($request->office->timezone ?? 'America/Sao_Paulo');
        if ($tz === '') {
            $tz = 'America/Sao_Paulo';
        }
        $pa = PgdasdPeriod::expectedPa(null, $tz);

        return PgdasdPeriod::toPeriodoApuracao($pa);
    }

    private function resolveOrCreateProjection(FiscalAdapterRequest $request): ?TaxObligationProjection
    {
        $def = TaxObligationDefinition::query()
            ->where('code', 'PGDAS_D')
            ->orWhere(function ($q): void {
                $q->where('module_key', 'simples_mei')
                    ->where('service_code', 'PGDASD');
            })
            ->first();

        if ($def === null) {
            return null;
        }

        $periodKey = $request->competence?->period_key
            ?? (string) ($request->progress['period_key'] ?? '');
        if ($periodKey === '') {
            $tz = (string) ($request->office->timezone ?? 'America/Sao_Paulo') ?: 'America/Sao_Paulo';
            $pa = PgdasdPeriod::expectedPa(null, $tz);
            $periodKey = PgdasdPeriod::toPeriodKey($pa);
        }

        try {
            $parsed = PgdasdPeriod::parse($periodKey);
        } catch (\Throwable) {
            return null;
        }

        return TaxObligationProjection::query()->withoutGlobalScopes()->firstOrCreate(
            [
                'office_id' => $request->office->id,
                'client_id' => $request->client->id,
                'obligation_definition_id' => $def->id,
                'period_key' => $periodKey,
            ],
            [
                'period_year' => (int) $parsed->format('Y'),
                'period_month' => (int) $parsed->format('m'),
                'competence_id' => $request->competence?->id,
                'applicability' => 'APPLICABLE',
                'situation' => 'UNKNOWN',
                'delivery_status' => 'UNKNOWN',
                'is_open' => true,
            ],
        );
    }

    private function withNormalized(
        FiscalAdapterResult $result,
        array $normalized,
        FiscalSituation $situation,
    ): FiscalAdapterResult {
        return new FiscalAdapterResult(
            result: $result->result,
            situation: $situation,
            coverage: $result->coverage,
            evidenceBytes: $result->evidenceBytes,
            evidenceContentType: $result->evidenceContentType,
            sourceVersion: $result->sourceVersion,
            normalized: $normalized,
            findings: $result->findings,
            itemsProcessed: $result->itemsProcessed,
            pagesProcessed: $result->pagesProcessed,
        );
    }
}

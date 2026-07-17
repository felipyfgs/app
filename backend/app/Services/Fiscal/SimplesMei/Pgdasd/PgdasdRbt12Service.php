<?php

namespace App\Services\Fiscal\SimplesMei\Pgdasd;

use App\Enums\PgdasdOperationKind;
use App\Enums\PgdasdRbt12Status;
use App\Jobs\Fiscal\FetchPgdasdRbt12Job;
use App\Models\FiscalMonitoringRun;
use App\Models\PgdasdOperation;
use App\Models\PgdasdRbt12Projection;
use App\Models\TaxObligationProjection;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/** Reserva e resolve RBT12 uma única vez por referência fiscal. */
final class PgdasdRbt12Service
{
    public function __construct(
        private readonly PgdasdRbt12Parser $parser,
        private readonly PdfTextExtractor $pdfText,
    ) {}

    /**
     * @param  list<PgdasdOperation>  $operations
     * @param  list<TaxObligationProjection>  $periodProjections
     * @return list<PgdasdRbt12Projection>
     */
    public function reserveFromOperations(
        FiscalMonitoringRun $run,
        array $operations,
        array $periodProjections = [],
    ): array {
        $byProjection = collect($operations)->groupBy('projection_id');
        $projectionIds = collect($periodProjections)
            ->map(static fn (TaxObligationProjection $projection): int => (int) $projection->id)
            ->merge($byProjection->keys()->map(static fn ($id): int => (int) $id))
            ->unique()
            ->values();
        $created = [];

        foreach ($projectionIds as $projectionId) {
            $projection = TaxObligationProjection::query()
                ->withoutGlobalScopes()
                ->where('office_id', $run->office_id)
                ->whereKey((int) $projectionId)
                ->first();
            if ($projection === null) {
                continue;
            }

            $latestDeclaration = $this->latestDeclarationForProjection($projection);
            $dasOperations = PgdasdOperation::query()
                ->withoutGlobalScopes()
                ->where('office_id', $run->office_id)
                ->where('client_id', $run->client_id)
                ->where('projection_id', $projection->id)
                ->where('kind', PgdasdOperationKind::Das->value)
                ->orderBy('issued_at')
                ->orderBy('das_number')
                ->get();

            if ($dasOperations->isEmpty()) {
                $reserved = $this->reserveNoDas($run, $projection, $latestDeclaration);
                if ($reserved !== null) {
                    $created[] = $reserved;
                    $this->pointProjectionAtLatestRbt12($projection, $reserved);
                }

                continue;
            }

            foreach ($dasOperations as $das) {
                if (! is_string($das->das_number) || $das->das_number === '') {
                    continue;
                }
                $key = $this->sourceReferenceKey(
                    (int) $run->office_id,
                    (int) $run->client_id,
                    (string) $projection->period_key,
                    $das->das_number,
                    $latestDeclaration?->declaration_number,
                    $latestDeclaration?->transmitted_at?->toIso8601String(),
                );
                $reserved = $this->reserve(
                    run: $run,
                    projection: $projection,
                    sourceKey: $key,
                    status: PgdasdRbt12Status::Pending,
                    dasNumber: $das->das_number,
                    latestDeclaration: $latestDeclaration,
                );
                if ($reserved !== null) {
                    $created[] = $reserved;
                    $this->pointProjectionAtLatestRbt12($projection, $reserved);
                    try {
                        FetchPgdasdRbt12Job::dispatch((int) $reserved->id)
                            ->onQueue((string) config('fiscal_monitoring.job.queue', 'default'));
                    } catch (\Throwable) {
                        $this->markFailed($reserved, 'EXTRACT_JOB_DISPATCH_FAILED', (int) $run->id);
                    }
                }
            }
        }

        return $created;
    }

    public function sourceReferenceKey(
        int $officeId,
        int $clientId,
        string $periodKey,
        string $dasNumber,
        ?string $latestDeclarationNumber,
        ?string $latestTransmission,
    ): string {
        return hash('sha256', implode('|', [
            $officeId,
            $clientId,
            $periodKey,
            $dasNumber,
            $latestDeclarationNumber ?? '',
            $latestTransmission ?? '',
        ]));
    }

    public function resolveFromPdfBytes(
        PgdasdRbt12Projection $projection,
        string $pdfBytes,
        int $artifactId,
        ?int $sourceRunId = null,
    ): PgdasdRbt12Projection {
        $metadata = is_array($projection->metadata) ? $projection->metadata : [];
        $periodKey = $projection->projection?->period_key
            ?? ($metadata['period_key'] ?? null);
        $periodoApuracao = is_string($periodKey) ? str_replace('-', '', $periodKey) : '';

        try {
            $parsed = $this->parser->parse($this->pdfText->extract($pdfBytes), $periodoApuracao);
        } catch (\Throwable) {
            return $this->markFailed($projection, 'PDF_TEXT_EXTRACTION_FAILED', $sourceRunId);
        }

        $projection->forceFill([
            'status' => $parsed['status'],
            'total_cents' => $parsed['total_cents'],
            'internal_market_cents' => $parsed['internal_market_cents'],
            'external_market_cents' => $parsed['external_market_cents'],
            'sanitized_error' => $parsed['reason'],
            'parser_version' => $parsed['parser_version'],
            'source_artifact_id' => $artifactId,
            'source_run_id' => $sourceRunId ?? $projection->source_run_id,
            'extracted_at' => CarbonImmutable::now(),
        ])->save();

        return $projection->refresh();
    }

    public function markAttempted(PgdasdRbt12Projection $projection): bool
    {
        $updated = PgdasdRbt12Projection::query()
            ->withoutGlobalScopes()
            ->whereKey($projection->id)
            ->whereNull('attempted_at')
            ->where('status', PgdasdRbt12Status::Pending->value)
            ->update(['attempted_at' => CarbonImmutable::now(), 'updated_at' => CarbonImmutable::now()]);

        if ($updated === 1) {
            return true;
        }

        // Reentregas de uma reserva ainda PENDING devem poder despachar a mesma
        // run idempotente; attempted_at não representa conclusão nem ACK remoto.
        return PgdasdRbt12Projection::query()
            ->withoutGlobalScopes()
            ->whereKey($projection->id)
            ->where('status', PgdasdRbt12Status::Pending->value)
            ->exists();
    }

    public function markFailed(
        PgdasdRbt12Projection $projection,
        string $reason,
        ?int $sourceRunId = null,
    ): PgdasdRbt12Projection {
        $projection->forceFill([
            'status' => PgdasdRbt12Status::Failed,
            'sanitized_error' => mb_substr($reason, 0, 255),
            'parser_version' => PgdasdRbt12Parser::VERSION,
            'source_run_id' => $sourceRunId ?? $projection->source_run_id,
            'extracted_at' => CarbonImmutable::now(),
        ])->save();

        return $projection->refresh();
    }

    public function reconcileTerminalFailure(FiscalMonitoringRun $run): int
    {
        if (strtoupper((string) $run->operation_code) !== 'CONSULTAR_EXTRATO') {
            return 0;
        }

        return PgdasdRbt12Projection::query()
            ->withoutGlobalScopes()
            ->where('office_id', $run->office_id)
            ->where('client_id', $run->client_id)
            ->where('source_run_id', $run->id)
            ->where('status', PgdasdRbt12Status::Pending->value)
            ->update([
                'status' => PgdasdRbt12Status::Failed->value,
                'sanitized_error' => 'EXTRACT_QUERY_FAILED',
                'parser_version' => PgdasdRbt12Parser::VERSION,
                'extracted_at' => CarbonImmutable::now(),
                'updated_at' => CarbonImmutable::now(),
            ]);
    }

    private function reserveNoDas(
        FiscalMonitoringRun $run,
        TaxObligationProjection $projection,
        ?PgdasdOperation $latestDeclaration,
    ): ?PgdasdRbt12Projection {
        $key = $this->sourceReferenceKey(
            (int) $run->office_id,
            (int) $run->client_id,
            (string) $projection->period_key,
            'NO_DAS',
            $latestDeclaration?->declaration_number,
            $latestDeclaration?->transmitted_at?->toIso8601String(),
        );

        return $this->reserve(
            run: $run,
            projection: $projection,
            sourceKey: $key,
            status: PgdasdRbt12Status::NoDas,
            dasNumber: null,
            latestDeclaration: $latestDeclaration,
        );
    }

    private function reserve(
        FiscalMonitoringRun $run,
        TaxObligationProjection $projection,
        string $sourceKey,
        PgdasdRbt12Status $status,
        ?string $dasNumber,
        ?PgdasdOperation $latestDeclaration,
    ): ?PgdasdRbt12Projection {
        try {
            return DB::transaction(function () use (
                $run,
                $projection,
                $sourceKey,
                $status,
                $dasNumber,
                $latestDeclaration,
            ): ?PgdasdRbt12Projection {
                $existing = PgdasdRbt12Projection::query()
                    ->withoutGlobalScopes()
                    ->where('office_id', $run->office_id)
                    ->where('client_id', $run->client_id)
                    ->where('projection_id', $projection->id)
                    ->where('source_reference_key', $sourceKey)
                    ->lockForUpdate()
                    ->first();
                if ($existing !== null) {
                    return null;
                }

                return PgdasdRbt12Projection::query()->create([
                    'office_id' => $run->office_id,
                    'client_id' => $run->client_id,
                    'projection_id' => $projection->id,
                    'source_reference_key' => $sourceKey,
                    'source_das_number' => $dasNumber,
                    'source_declaration_number' => $latestDeclaration?->declaration_number,
                    'source_transmitted_at' => $latestDeclaration?->transmitted_at,
                    'status' => $status,
                    'source_run_id' => $run->id,
                    'metadata' => ['period_key' => $projection->period_key],
                ]);
            });
        } catch (QueryException $exception) {
            if (str_contains(strtolower($exception->getMessage()), 'unique')) {
                return null;
            }

            throw $exception;
        }
    }

    private function latestDeclarationForProjection(TaxObligationProjection $projection): ?PgdasdOperation
    {
        return PgdasdOperation::query()
            ->withoutGlobalScopes()
            ->where('office_id', $projection->office_id)
            ->where('client_id', $projection->client_id)
            ->where('projection_id', $projection->id)
            ->where('kind', PgdasdOperationKind::Declaration->value)
            ->whereNotNull('transmitted_at')
            ->orderByDesc('transmitted_at')
            ->orderByDesc('declaration_number')
            ->first();
    }

    private function pointProjectionAtLatestRbt12(
        TaxObligationProjection $projection,
        PgdasdRbt12Projection $rbt12,
    ): void {
        $projection->forceFill(['pgdasd_latest_rbt12_projection_id' => $rbt12->id])->save();
    }
}

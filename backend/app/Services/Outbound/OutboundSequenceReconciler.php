<?php

namespace App\Services\Outbound;

use App\Contracts\SefazOutboundProtocolQueryClient;
use App\DTO\Outbound\ProtocolQueryResult;
use App\Enums\OutboundNumberStatus;
use App\Enums\OutboundSeriesStatus;
use App\Models\Establishment;
use App\Models\OutboundNumberState;
use App\Models\OutboundSeriesCursor;
use App\Services\Certificates\CredentialService;
use App\Services\Clients\CaptureEligibilityService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Reconciliação somente leitura por nNF + consulta 562.
 * Persiste candidata/resultado antes de avançar discovery_position.
 */
final class OutboundSequenceReconciler
{
    public function __construct(
        private readonly SefazOutboundProtocolQueryClient $queryClient,
        private readonly AccessKeyCandidateBuilder $keyBuilder,
        private readonly CredentialService $credentials,
        private readonly CaptureEligibilityService $eligibility,
        private readonly OutboundKillSwitchService $killSwitch,
    ) {}

    /**
     * @return array{
     *   consulted: int,
     *   discovered: int,
     *   gaps: int,
     *   blocked: bool,
     *   nnf_start: ?int,
     *   nnf_end: ?int
     * }
     */
    public function reconcileSeries(OutboundSeriesCursor $series, int $maxNumbers = 10): array
    {
        $series->loadMissing(['profile', 'establishment']);
        $profile = $series->profile;
        $establishment = $series->establishment;

        if ($profile === null || $establishment === null) {
            throw new RuntimeException('Série sem perfil/estabelecimento.');
        }

        if ($this->killSwitch->isBlocked($profile)) {
            return ['consulted' => 0, 'discovered' => 0, 'gaps' => 0, 'blocked' => true, 'nnf_start' => null, 'nnf_end' => null];
        }

        $elig = $this->eligibility->evaluateMaOutbound($establishment, $profile);
        if (! $elig['protocol_query']) {
            return ['consulted' => 0, 'discovered' => 0, 'gaps' => 0, 'blocked' => false, 'nnf_start' => null, 'nnf_end' => null];
        }

        $lockKey = sprintf(
            'outbound-ma:%d:%s:%s:%d',
            $establishment->id,
            $series->environment,
            $series->model->value,
            $series->series
        );
        $lock = Cache::lock($lockKey, (int) config('sefaz.ma_outbound.lock_ttl_seconds', 960));
        if (! $lock->get()) {
            return ['consulted' => 0, 'discovered' => 0, 'gaps' => 0, 'blocked' => false, 'nnf_start' => null, 'nnf_end' => null];
        }

        try {
            $series->forceFill([
                'status' => OutboundSeriesStatus::Running,
                'locked_at' => now(),
                'lock_owner' => gethostname().':'.getmypid(),
            ])->save();

            $client = $establishment->relationLoaded('client')
                ? $establishment->client
                : \App\Models\Client::query()->find($establishment->client_id);
            if ($client === null) {
                throw new RuntimeException('Cliente da raiz ausente.');
            }
            $credential = $this->credentials->activeFor($client);
            if ($credential === null) {
                throw new RuntimeException('Credencial A1 da raiz ausente.');
            }
            $material = $this->credentials->loadPfxMaterial($credential);
            if ($material === null) {
                throw new RuntimeException('Não foi possível materializar A1 da raiz.');
            }

            $max = min($maxNumbers, (int) config('sefaz.ma_outbound.max_numbers_per_run', 10));
            $rps = max(0.1, (float) config('sefaz.ma_outbound.global_rps', 1));
            $sleepUs = (int) (1_000_000 / $rps);

            $consulted = $discovered = $gaps = 0;
            $nnfStart = null;
            $nnfEnd = null;
            $position = (int) $series->discovery_position;

            // Também reprocessa lacunas elegíveis
            $pending = OutboundNumberState::query()
                ->where('outbound_series_cursor_id', $series->id)
                ->whereIn('status', [
                    OutboundNumberStatus::GapPending->value,
                    OutboundNumberStatus::RetryScheduled->value,
                    OutboundNumberStatus::ConsultQueued->value,
                ])
                ->where(function ($q): void {
                    $q->whereNull('next_attempt_at')->orWhere('next_attempt_at', '<=', now());
                })
                ->orderBy('nnf')
                ->limit($max)
                ->get();

            $toProcess = [];
            foreach ($pending as $state) {
                $toProcess[] = $state;
            }

            while (count($toProcess) < $max) {
                $nnf = $position;
                $existing = OutboundNumberState::query()
                    ->where('outbound_series_cursor_id', $series->id)
                    ->where('nnf', $nnf)
                    ->first();
                if ($existing === null) {
                    $existing = $this->createQueuedState($series, $nnf, $establishment, $material);
                }
                if ($existing->status->isTerminalSuccess() || $existing->status === OutboundNumberStatus::ExhaustedVisible) {
                    $position++;
                    continue;
                }
                if (! in_array($existing, $toProcess, true)) {
                    $toProcess[] = $existing;
                }
                $position++;
                if (count($toProcess) >= $max) {
                    break;
                }
            }

            foreach ($toProcess as $state) {
                if ($consulted >= $max) {
                    break;
                }
                if ($nnfStart === null) {
                    $nnfStart = $state->nnf;
                }
                $nnfEnd = $state->nnf;

                $result = $this->consultNumber($series, $state, $establishment, $material);
                $consulted++;
                usleep($sleepUs);

                if ($result['discovered']) {
                    $discovered++;
                }
                if ($result['gap']) {
                    $gaps++;
                }
                if ($result['blocked']) {
                    $series->forceFill([
                        'status' => OutboundSeriesStatus::Blocked,
                        'last_error' => $result['block_reason'] ?? 'Série bloqueada',
                        'last_cstat' => $result['cstat'] ?? null,
                    ])->save();

                    return compact('consulted', 'discovered', 'gaps') + [
                        'blocked' => true,
                        'nnf_start' => $nnfStart,
                        'nnf_end' => $nnfEnd,
                    ];
                }
            }

            // Avança posição após persistência dos resultados
            $newPos = max((int) $series->discovery_position, ($nnfEnd ?? $series->discovery_position - 1) + 1);
            $series->forceFill([
                'discovery_position' => $newPos,
                'status' => OutboundSeriesStatus::Idle,
                'last_run_at' => now(),
                'next_run_at' => now()->addHours((int) config('sefaz.ma_outbound.retry_interval_hours', 12)),
                'locked_at' => null,
                'lock_owner' => null,
            ])->save();

            return [
                'consulted' => $consulted,
                'discovered' => $discovered,
                'gaps' => $gaps,
                'blocked' => false,
                'nnf_start' => $nnfStart,
                'nnf_end' => $nnfEnd,
            ];
        } finally {
            if ($series->status === OutboundSeriesStatus::Running) {
                $series->forceFill([
                    'status' => OutboundSeriesStatus::Idle,
                    'locked_at' => null,
                    'lock_owner' => null,
                ])->save();
            }
            $lock->release();
            // limpa material sensível
            if (isset($material)) {
                $material['pfx'] = '';
                $material['password'] = '';
            }
        }
    }

    /**
     * @param  array{pfx: string, password: string}  $material
     * @return array{discovered: bool, gap: bool, blocked: bool, cstat?: string, block_reason?: string}
     */
    private function consultNumber(
        OutboundSeriesCursor $series,
        OutboundNumberState $state,
        Establishment $establishment,
        array $material,
    ): array {
        return DB::transaction(function () use ($series, $state, $establishment, $material) {
            $maxAttempts = (int) config('sefaz.ma_outbound.max_attempts_per_number', 10);
            $retryHours = (int) config('sefaz.ma_outbound.retry_interval_hours', 12);

            // Preserva candidata após timeout ambíguo
            if ($state->candidate_access_key === null) {
                $aamm = $this->plausibleAamm($series);
                $built = $this->keyBuilder->build([
                    'cuf' => '21',
                    'aamm' => $aamm,
                    'cnpj' => $establishment->cnpj,
                    'model' => $series->model,
                    'series' => $series->series,
                    'nnf' => $state->nnf,
                    'tp_emis' => $series->tp_emis,
                    'cnf' => $state->candidate_cnf,
                ]);
                $state->candidate_access_key = $built['access_key'];
                $state->candidate_cnf = $built['cnf'];
                $state->save();
            }

            $result = $this->queryClient->consult(
                $state->candidate_access_key,
                $series->model->value,
                $series->environment,
                $material,
            );

            $state->attempts = (int) $state->attempts + 1;
            $state->last_attempt_at = now();
            $state->last_cstat = $result->cStat;
            $state->last_xmotivo = mb_substr($result->xMotivo, 0, 500);
            $state->sanitized_response = $result->sanitized;
            $state->protocol = $result->protocol;

            if ($result->ambiguousTimeout) {
                $state->status = OutboundNumberStatus::RetryScheduled;
                $state->next_attempt_at = now()->addHours($retryHours);
                $state->save();

                return ['discovered' => false, 'gap' => true, 'blocked' => false, 'cstat' => $result->cStat];
            }

            if ($result->isUnauthorizedConsumption()) {
                $state->status = OutboundNumberStatus::Blocked;
                $state->block_reason = 'cStat 656 — consumo indevido';
                $state->save();
                $this->killSwitch->blockSeries($series, 'cStat 656', $result->cStat);

                return ['discovered' => false, 'gap' => false, 'blocked' => true, 'cstat' => '656', 'block_reason' => '656'];
            }

            if ($result->is562WithKey() || ($result->isAuthorizedOnCandidate() && $result->returnedAccessKey)) {
                $discoveredKey = strtoupper($result->returnedAccessKey ?? $state->candidate_access_key);
                if (! $this->keyBuilder->matchesIdentity(
                    $discoveredKey,
                    '21',
                    $establishment->cnpj,
                    $series->model->value,
                    $series->series,
                    $state->nnf,
                    $series->tp_emis,
                )) {
                    $state->status = OutboundNumberStatus::Blocked;
                    $state->block_reason = 'Chave retornada diverge da identidade esperada';
                    $state->save();
                    $this->killSwitch->blockSeries($series, 'chave divergente', $result->cStat);

                    return ['discovered' => false, 'gap' => false, 'blocked' => true, 'cstat' => $result->cStat, 'block_reason' => 'chave divergente'];
                }

                $state->discovered_access_key = $discoveredKey;
                $state->key_discovered_at = now();
                $state->status = OutboundNumberStatus::XmlPending; // KEY_DISCOVERED → XML_PENDING
                $state->save();

                return ['discovered' => true, 'gap' => false, 'blocked' => false, 'cstat' => $result->cStat];
            }

            if ($result->isLimitedWithoutKey()) {
                $state->status = OutboundNumberStatus::LimitedNoKey;
                $state->block_reason = '562/limitado sem chave — sem força bruta de cNF';
                $state->save();

                return ['discovered' => false, 'gap' => true, 'blocked' => false, 'cstat' => $result->cStat];
            }

            if ($result->isNotFound() || $result->cStat === '217') {
                if ($state->attempts >= $maxAttempts) {
                    $state->status = OutboundNumberStatus::ExhaustedVisible;
                    $state->next_attempt_at = null;
                } else {
                    $state->status = OutboundNumberStatus::RetryScheduled;
                    $state->next_attempt_at = now()->addHours($retryHours);
                }
                $state->save();

                return ['discovered' => false, 'gap' => true, 'blocked' => false, 'cstat' => $result->cStat];
            }

            // Resposta inesperada
            $state->status = OutboundNumberStatus::GapPending;
            $state->next_attempt_at = now()->addHours($retryHours);
            $state->save();

            Log::info('outbound.consult.unexpected', [
                'series_id' => $series->id,
                'nnf' => $state->nnf,
                'cStat' => $result->cStat,
            ]);

            return ['discovered' => false, 'gap' => true, 'blocked' => false, 'cstat' => $result->cStat];
        });
    }

    /**
     * @param  array{pfx: string, password: string}  $material
     */
    private function createQueuedState(
        OutboundSeriesCursor $series,
        int $nnf,
        Establishment $establishment,
        array $material,
    ): OutboundNumberState {
        $aamm = $this->plausibleAamm($series);
        $built = $this->keyBuilder->build([
            'cuf' => '21',
            'aamm' => $aamm,
            'cnpj' => $establishment->cnpj,
            'model' => $series->model,
            'series' => $series->series,
            'nnf' => $nnf,
            'tp_emis' => $series->tp_emis,
        ]);

        return OutboundNumberState::query()->create([
            'office_id' => $series->office_id,
            'outbound_capture_profile_id' => $series->outbound_capture_profile_id,
            'outbound_series_cursor_id' => $series->id,
            'series' => $series->series,
            'nnf' => $nnf,
            'status' => OutboundNumberStatus::ConsultQueued,
            'candidate_access_key' => $built['access_key'],
            'candidate_cnf' => $built['cnf'],
            'attempts' => 0,
        ]);
    }

    private function plausibleAamm(OutboundSeriesCursor $series): string
    {
        if ($series->seed_issued_at !== null) {
            return $series->seed_issued_at->format('ym');
        }

        return CarbonImmutable::now()->format('ym');
    }
}

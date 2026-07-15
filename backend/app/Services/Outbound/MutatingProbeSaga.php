<?php

namespace App\Services\Outbound;

use App\Contracts\SefazOutboundInutilizationClient;
use App\Contracts\SefazOutboundMutatingProbeClient;
use App\Enums\OutboundNumberStatus;
use App\Enums\OutboundSeriesStatus;
use App\Models\OutboundNumberState;
use App\Models\OutboundSeriesCursor;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Saga idempotente: inutilização → sonda 539 → autorização inesperada → cancelamento.
 * Sem retry cego após timeout mutante.
 */
final class MutatingProbeSaga
{
    public function __construct(
        private readonly MutatingProbeGateEvaluator $gates,
        private readonly SefazOutboundInutilizationClient $inutilization,
        private readonly SefazOutboundMutatingProbeClient $probe,
        private readonly InutilizationResponseParser $inutParser,
        private readonly Rejection539Parser $rejection539,
        private readonly OutboundKillSwitchService $killSwitch,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array{pfx: string, password: string}  $certificate
     * @return array{status: string, message: string}
     */
    public function run(
        OutboundSeriesCursor $series,
        OutboundNumberState $number,
        array $certificate,
        int $userId,
    ): array {
        $series->loadMissing('profile', 'establishment');
        $profile = $series->profile;
        if ($profile === null) {
            throw new RuntimeException('Série sem perfil.');
        }

        $gate = $this->gates->evaluate($profile, $series);
        if (! $gate['allowed']) {
            return ['status' => 'blocked', 'message' => implode(' ', $gate['reasons'])];
        }

        // Nunca usar o próximo número de série ativa aberta
        if (! $series->series_closed_for_mutation) {
            return ['status' => 'blocked', 'message' => 'Série não fechada para mutação.'];
        }

        if ($number->nnf >= (int) $series->discovery_position && $series->status !== OutboundSeriesStatus::Closed) {
            // Preferir lacunas históricas
            if ($number->status !== OutboundNumberStatus::ExhaustedVisible
                && $number->status !== OutboundNumberStatus::LimitedNoKey
                && $number->status !== OutboundNumberStatus::GapPending) {
                return ['status' => 'blocked', 'message' => 'Sonda só em lacuna histórica fechada.'];
            }
        }

        return DB::transaction(function () use ($series, $number, $certificate, $userId, $profile) {
            if ($number->status === OutboundNumberStatus::NumberInutilized
                || $number->status === OutboundNumberStatus::Complete
                || $number->status === OutboundNumberStatus::Stopped) {
                return ['status' => 'noop', 'message' => 'Número já encerrado.'];
            }

            if ($number->status !== OutboundNumberStatus::NumberProvenUsed
                && $number->status !== OutboundNumberStatus::ProbeSent
                && $number->status !== OutboundNumberStatus::Rejected539) {
                $number->status = OutboundNumberStatus::InutilizationPending;
                $number->save();

                $year = now()->format('y');
                $raw = $this->inutilization->inutilize(
                    $series->model->value,
                    $series->environment,
                    $series->series,
                    $number->nnf,
                    $number->nnf,
                    $year,
                    (string) $series->establishment?->cnpj,
                    $certificate,
                );

                $parsed = isset($raw['outcome'])
                    ? $raw
                    : $this->inutParser->parse((string) ($raw['raw'] ?? ''));

                if ($parsed['outcome'] === 'INUTILIZED') {
                    $number->forceFill([
                        'status' => OutboundNumberStatus::NumberInutilized,
                        'last_cstat' => $parsed['cStat'],
                        'last_xmotivo' => $parsed['xMotivo'],
                        'protocol' => $parsed['protocol'] ?? null,
                    ])->save();
                    $this->audit->record('outbound.mutation.inutilized', 'SUCCESS', $number, [
                        'nnf' => $number->nnf,
                        'cStat' => $parsed['cStat'],
                    ], $userId, $profile->office_id);

                    return ['status' => 'inutilized', 'message' => 'Número inutilizado; saga encerrada.'];
                }

                if ($parsed['outcome'] === 'PROVEN_USED') {
                    $number->forceFill([
                        'status' => OutboundNumberStatus::NumberProvenUsed,
                        'last_cstat' => $parsed['cStat'],
                        'last_xmotivo' => $parsed['xMotivo'],
                    ])->save();
                } elseif ($parsed['outcome'] === 'AMBIGUOUS') {
                    $number->forceFill([
                        'status' => OutboundNumberStatus::Blocked,
                        'block_reason' => 'Timeout/ambiguidade em inutilização — sem retry cego',
                    ])->save();
                    $this->killSwitch->blockSeries($series, 'inutilização ambígua', $parsed['cStat'] ?? null);

                    return ['status' => 'blocked', 'message' => 'Resultado ambíguo na inutilização.'];
                } else {
                    return ['status' => 'rejected', 'message' => $parsed['xMotivo'] ?? 'Inutilização rejeitada'];
                }
            }

            // Spike 539 somente após NUMBER_PROVEN_USED e gates ainda válidos
            $gate2 = $this->gates->evaluate($profile, $series);
            if (! $gate2['allowed'] || $number->status !== OutboundNumberStatus::NumberProvenUsed) {
                if ($number->status !== OutboundNumberStatus::NumberProvenUsed
                    && $number->status !== OutboundNumberStatus::ProbeSent) {
                    return ['status' => 'stopped', 'message' => 'Gates ou estado impedem sonda 539.'];
                }
            }

            if (! $this->probe->isActive()) {
                return ['status' => 'disabled', 'message' => 'Cliente de sonda inativo (G5).'];
            }

            $number->status = OutboundNumberStatus::ProbeSent;
            $number->save();

            $probeResult = $this->probe->probe(
                $series->model->value,
                $series->environment,
                ['series' => $series->series, 'nnf' => $number->nnf, 'homologation_only' => true],
                $certificate,
            );

            if (! empty($probeResult['authorized'])) {
                $number->forceFill([
                    'status' => OutboundNumberStatus::AuthorizedUnexpected,
                    'last_cstat' => $probeResult['cStat'] ?? '100',
                    'discovered_access_key' => $probeResult['access_key'] ?? null,
                    'key_discovered_at' => now(),
                ])->save();

                $series->forceFill(['status' => OutboundSeriesStatus::FiscalIncident])->save();
                $this->killSwitch->activateProfile($profile, 'Autorização inesperada em sonda 539', $userId);
                $this->killSwitch->activateGlobal('Autorização inesperada em sonda mutante MA', $userId, $profile->office_id);
                $this->audit->record('outbound.mutation.authorized_unexpected', 'FAILURE', $number, [
                    'nnf' => $number->nnf,
                    'cStat' => $probeResult['cStat'] ?? null,
                ], $userId, $profile->office_id);

                return ['status' => 'fiscal_incident', 'message' => 'Autorização inesperada — incidente fiscal.'];
            }

            $parsed539 = $this->rejection539->parse(
                (string) ($probeResult['raw'] ?? ''),
                '21',
                (string) $series->establishment?->cnpj,
                $series->model->value,
                $series->series,
                $number->nnf,
                $series->tp_emis,
            );

            if ($parsed539['valid'] && $parsed539['access_key']) {
                $number->forceFill([
                    'status' => OutboundNumberStatus::Rejected539,
                    'discovered_access_key' => $parsed539['access_key'],
                    'key_discovered_at' => now(),
                    'last_cstat' => '539',
                    'last_xmotivo' => $parsed539['xMotivo'],
                ])->save();
                // Em seguida XML_PENDING via fluxo de recuperação
                $number->forceFill(['status' => OutboundNumberStatus::XmlPending])->save();

                return ['status' => 'key_discovered', 'message' => 'Chave revelada via 539.'];
            }

            $number->forceFill([
                'status' => OutboundNumberStatus::Blocked,
                'block_reason' => 'Sonda sem chave 539 válida',
                'last_cstat' => $probeResult['cStat'] ?? null,
            ])->save();

            return ['status' => 'no_key', 'message' => 'Sonda sem chave utilizável.'];
        });
    }
}

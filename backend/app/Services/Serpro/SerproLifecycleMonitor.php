<?php

namespace App\Services\Serpro;

use App\Enums\SerproAuthorizationStatus;
use App\Models\OfficeSerproAuthorization;
use App\Models\SerproContract;
use App\Models\TaxProxyPower;
use App\Services\Audit\AuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Verifica PFX contratante, A1, Termo, token e procurações.
 * Apenas alertas/marcações — NUNCA assina Termo, renova procuração ou muta fiscal.
 */
final class SerproLifecycleMonitor
{
    public function __construct(
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @return array{
     *   alerts: list<array<string, mixed>>,
     *   scanned: array<string, int>,
     *   lock_acquired: bool
     * }
     */
    public function scan(): array
    {
        $lockSeconds = (int) config('serpro.lifecycle.lock_seconds', 120);
        $lock = Cache::lock('serpro.lifecycle.scan', max(30, $lockSeconds));

        if (! $lock->get()) {
            return [
                'alerts' => [],
                'scanned' => [],
                'lock_acquired' => false,
            ];
        }

        try {
            $alertDays = config('serpro.lifecycle.alert_days', [90, 60, 30, 15, 7, 1]);
            if (! is_array($alertDays)) {
                $alertDays = [90, 60, 30, 15, 7, 1];
            }
            $alertDays = array_values(array_unique(array_map('intval', $alertDays)));
            sort($alertDays); // menor janela aplicável primeiro (1,7,15,30,60,90)

            $alerts = [];
            $now = CarbonImmutable::now();
            $skew = (int) config('serpro.lifecycle.token_renewal_skew_seconds', 300);

            $contracts = 0;
            foreach (SerproContract::query()->orderBy('id')->get() as $contract) {
                $contracts++;
                if ($contract->cert_valid_to !== null) {
                    $alerts = array_merge($alerts, $this->windowAlerts(
                        kind: 'CONTRACTOR_PFX',
                        subjectId: (int) $contract->id,
                        officeId: null,
                        validTo: CarbonImmutable::parse((string) $contract->cert_valid_to),
                        now: $now,
                        alertDays: $alertDays,
                        meta: [
                            'environment' => $contract->environment->value ?? (string) $contract->environment,
                        ],
                    ));
                }
            }

            $auths = 0;
            foreach (OfficeSerproAuthorization::query()->orderBy('id')->get() as $auth) {
                $auths++;
                if ($auth->author_cert_valid_to !== null) {
                    $alerts = array_merge($alerts, $this->windowAlerts(
                        kind: 'AUTHOR_A1',
                        subjectId: (int) $auth->id,
                        officeId: (int) $auth->office_id,
                        validTo: CarbonImmutable::parse((string) $auth->author_cert_valid_to),
                        now: $now,
                        alertDays: $alertDays,
                        meta: ['environment' => $auth->environment->value ?? (string) $auth->environment],
                    ));
                }
                if ($auth->termo_valid_to !== null) {
                    $alerts = array_merge($alerts, $this->windowAlerts(
                        kind: 'TERMO',
                        subjectId: (int) $auth->id,
                        officeId: (int) $auth->office_id,
                        validTo: CarbonImmutable::parse((string) $auth->termo_valid_to),
                        now: $now,
                        alertDays: $alertDays,
                        meta: ['environment' => $auth->environment->value ?? (string) $auth->environment],
                    ));
                }
                if ($auth->procurador_token_expires_at !== null) {
                    $exp = CarbonImmutable::parse((string) $auth->procurador_token_expires_at);
                    $secondsLeft = $now->diffInSeconds($exp, false);
                    if ($secondsLeft <= $skew) {
                        $alerts[] = [
                            'kind' => 'PROCURADOR_TOKEN',
                            'subject_id' => (int) $auth->id,
                            'office_id' => (int) $auth->office_id,
                            'days_left' => 0,
                            'severity' => $secondsLeft <= 0 ? 'EXPIRED' : 'RENEWAL_SKEW',
                            'message' => $secondsLeft <= 0
                                ? 'Token do procurador expirado — renovação manual/consentida necessária.'
                                : 'Token do procurador na margem de renovação (skew) — jobs que ultrapassariam a validade devem bloquear.',
                            'expires_at' => $exp->toIso8601String(),
                        ];
                        // Marca action required se expirado; NÃO renova automaticamente
                        if ($secondsLeft <= 0 && $auth->status === SerproAuthorizationStatus::TokenActive) {
                            $auth->status = SerproAuthorizationStatus::ActionRequired;
                            $auth->action_required_reason = 'Token do procurador expirado; renovação exige ação explícita.';
                            $auth->save();
                        }
                    }
                }
            }

            $powers = 0;
            foreach (TaxProxyPower::query()->whereNull('closed_at')->orderBy('id')->cursor() as $power) {
                $powers++;
                if ($power->valid_to !== null) {
                    $alerts = array_merge($alerts, $this->windowAlerts(
                        kind: 'PROXY_POWER',
                        subjectId: (int) $power->id,
                        officeId: (int) $power->office_id,
                        validTo: CarbonImmutable::parse((string) $power->valid_to),
                        now: $now,
                        alertDays: $alertDays,
                        meta: [
                            'power_code' => $power->power_code,
                            'client_id' => $power->client_id,
                        ],
                    ));
                }
            }

            foreach ($alerts as $alert) {
                Log::info('serpro.lifecycle.alert', [
                    'kind' => $alert['kind'],
                    'subject_id' => $alert['subject_id'],
                    'office_id' => $alert['office_id'],
                    'days_left' => $alert['days_left'],
                    'severity' => $alert['severity'],
                    // sem PII/segredos
                ]);
            }

            if ($alerts !== []) {
                $this->audit->record('serpro.lifecycle.scan', 'SUCCESS', null, [
                    'alerts_count' => count($alerts),
                    'kinds' => array_values(array_unique(array_column($alerts, 'kind'))),
                ], null, null);
            }

            return [
                'alerts' => $alerts,
                'scanned' => [
                    'contracts' => $contracts,
                    'authorizations' => $auths,
                    'proxy_powers' => $powers,
                ],
                'lock_acquired' => true,
            ];
        } finally {
            $lock->release();
        }
    }

    /**
     * @param  list<int>  $alertDays
     * @param  array<string, mixed>  $meta
     * @return list<array<string, mixed>>
     */
    private function windowAlerts(
        string $kind,
        int $subjectId,
        ?int $officeId,
        CarbonImmutable $validTo,
        CarbonImmutable $now,
        array $alertDays,
        array $meta = [],
    ): array {
        $daysLeft = (int) $now->startOfDay()->diffInDays($validTo->startOfDay(), false);

        if ($daysLeft < 0) {
            return [[
                'kind' => $kind,
                'subject_id' => $subjectId,
                'office_id' => $officeId,
                'days_left' => $daysLeft,
                'severity' => 'EXPIRED',
                'message' => "{$kind} expirado.",
                'expires_at' => $validTo->toIso8601String(),
                'meta' => $meta,
            ]];
        }

        // Escolhe a menor janela tal que daysLeft <= window (ex.: 5 dias → D7, não D90).
        $matched = null;
        foreach ($alertDays as $window) {
            if ($daysLeft <= $window) {
                $matched = $window;
                break;
            }
        }

        if ($matched === null) {
            return [];
        }

        return [[
            'kind' => $kind,
            'subject_id' => $subjectId,
            'office_id' => $officeId,
            'days_left' => $daysLeft,
            'severity' => 'D'.$matched,
            'message' => "{$kind} vence em {$daysLeft} dia(s) (janela {$matched}).",
            'expires_at' => $validTo->toIso8601String(),
            'meta' => $meta,
        ]];
    }
}

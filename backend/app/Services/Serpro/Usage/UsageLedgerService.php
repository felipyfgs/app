<?php

namespace App\Services\Serpro\Usage;

use App\DTO\Serpro\IntegraResponse;
use App\Enums\SerproConsumptionClass;
use App\Enums\SerproUsageReservationStatus;
use App\Enums\SerproUsageResult;
use App\Models\Office;
use App\Models\SerproApiUsageEntry;
use App\Models\SerproApiUsageReservation;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use LogicException;

/**
 * Ledger desacoplado: chamado antes/depois de qualquer HTTP SERPRO.
 *
 * Fluxo:
 * 1. reserve() — classifica, precifica, avalia orçamento sob lock, grava reserva idempotente
 * 2. finalize() — após resposta (sucesso ou falha possivelmente faturável) → entrada imutável
 * 3. release() — abortou antes do HTTP (não faturável)
 */
final class UsageLedgerService
{
    public function __construct(
        private readonly OperationCatalog $catalog,
        private readonly PriceCalculator $prices,
        private readonly UsageBudgetGate $budget,
        private readonly UsageShadowPolicy $shadow,
        private readonly AuditLogger $audit,
    ) {}

    public function reserve(UsageReserveRequest $request): UsageReserveOutcome
    {
        // Idempotência: reutiliza execução lógica existente (pré-check sem lock).
        $existing = SerproApiUsageReservation::query()
            ->withoutGlobalScopes()
            ->where('idempotency_key', $request->idempotencyKey)
            ->first();

        if ($existing !== null) {
            $this->assertSameOffice($existing, $request->officeId);

            return new UsageReserveOutcome(
                allowed: $existing->status !== SerproUsageReservationStatus::Blocked,
                reservation: $existing,
                replayed: true,
                budget: $this->budget->tenantSnapshot($request->officeId),
            );
        }

        $classified = $this->catalog->classify(
            $request->systemCode,
            $request->serviceCode,
            $request->operationCode,
        );

        $class = $classified['class'];
        $isEssential = $request->forceEssential ?? $classified['is_essential'];

        $estimate = $this->prices->estimate(
            class: $class,
            quantity: $request->quantity,
            systemCode: $request->systemCode,
            serviceCode: $request->serviceCode,
            operationCode: $request->operationCode,
        );

        $correlationId = $request->correlationId
            ?? $this->audit->correlationId()
            ?: (string) Str::uuid();

        $shadow = $this->shadow->isShadowMode();

        /** @var array{reservation: SerproApiUsageReservation, budget: array<string, mixed>, allowed: bool, replayed: bool} $pack */
        $pack = DB::transaction(function () use (
            $request,
            $class,
            $isEssential,
            $estimate,
            $correlationId,
            $shadow,
        ): array {
            // Double-check sob lock de unique (corrida de retry / cross-tenant).
            $race = SerproApiUsageReservation::query()
                ->withoutGlobalScopes()
                ->where('idempotency_key', $request->idempotencyKey)
                ->lockForUpdate()
                ->first();

            if ($race !== null) {
                $this->assertSameOffice($race, $request->officeId);

                return [
                    'reservation' => $race,
                    'budget' => $this->budget->tenantSnapshot($request->officeId),
                    'allowed' => $race->status !== SerproUsageReservationStatus::Blocked,
                    'replayed' => true,
                ];
            }

            // Serializa avaliação de orçamento por office (evita oversubscription de franquia).
            $this->lockOfficeBudget($request->officeId);

            $budgetEval = $this->budget->evaluate(
                officeId: $request->officeId,
                class: $class,
                quantity: $request->quantity,
                isEssential: $isEssential,
            );

            $allowed = (bool) $budgetEval['allowed'];
            $status = $allowed
                ? SerproUsageReservationStatus::Reserved
                : SerproUsageReservationStatus::Blocked;

            // Simulação nunca reserva orçamento/franquia
            if ($request->isSimulated) {
                $allowed = true;
                $status = SerproUsageReservationStatus::Reserved;
            }

            // Rotas oficiais não faturáveis
            if (in_array($request->functionalRoute, ['Apoiar', 'Monitorar'], true)) {
                $class = SerproConsumptionClass::NaoFaturavel;
            }

            $create = [
                'office_id' => $request->officeId,
                'idempotency_key' => $request->idempotencyKey,
                'client_id' => $request->clientId,
                'contributor_ref' => $request->contributorRef,
                'system_code' => $request->systemCode,
                'service_code' => $request->serviceCode,
                'operation_code' => $request->operationCode,
                'consumption_class' => $class,
                'quantity' => $request->quantity,
                'is_essential' => $isEssential,
                'status' => $status,
                'correlation_id' => $correlationId,
                'price_version_id' => $request->isSimulated ? null : $estimate['price_version_id'],
                'estimated_cost_micros' => $request->isSimulated ? null : $estimate['estimated_cost_micros'],
                'shadow_mode' => $shadow,
                'would_block' => $request->isSimulated ? false : (bool) $budgetEval['would_block'],
                'block_reason' => $allowed ? null : ($budgetEval['block_reason'] ?? null),
                'result' => $allowed ? null : SerproUsageResult::BlockedByBudget,
                'reserved_at' => now(),
                'finalized_at' => $allowed ? null : now(),
            ];
            if (Schema::hasColumn('serpro_api_usage_reservations', 'operation_key')) {
                $create['operation_key'] = $request->operationKey;
                $create['is_simulated'] = $request->isSimulated;
                $create['request_tag'] = $request->requestTag;
                $create['functional_route'] = $request->functionalRoute;
            }

            $reservation = SerproApiUsageReservation::query()->create($create);

            return [
                'reservation' => $reservation,
                'budget' => $budgetEval,
                'allowed' => $allowed,
                'replayed' => false,
            ];
        });

        $reservation = $pack['reservation'];
        $budgetEval = $pack['budget'];
        $allowed = $pack['allowed'];

        if ($reservation->status === SerproUsageReservationStatus::Blocked) {
            $this->audit->record(
                action: 'serpro.usage.blocked',
                result: 'BLOCKED',
                subject: $reservation,
                context: [
                    'office_id' => $request->officeId,
                    'service_code' => $request->serviceCode,
                    'operation_code' => $request->operationCode,
                    'block_reason' => $reservation->block_reason,
                    // sem CNPJ
                ],
                officeId: $request->officeId,
            );
        }

        return new UsageReserveOutcome(
            allowed: $allowed,
            reservation: $reservation,
            replayed: (bool) $pack['replayed'],
            budget: $budgetEval,
        );
    }

    /**
     * Finaliza reserva com resultado da chamada (inclusive falha possivelmente faturável).
     */
    public function finalize(
        SerproApiUsageReservation $reservation,
        SerproUsageResult $result,
        ?int $latencyMs = null,
        ?int $httpStatus = null,
        ?bool $possiblyBillable = null,
    ): SerproApiUsageEntry {
        $reservation = SerproApiUsageReservation::query()
            ->withoutGlobalScopes()
            ->whereKey($reservation->id)
            ->firstOrFail();

        // Já finalizada: reutiliza entrada (idempotente).
        if ($reservation->status === SerproUsageReservationStatus::Finalized) {
            $entry = SerproApiUsageEntry::query()
                ->withoutGlobalScopes()
                ->where('reservation_id', $reservation->id)
                ->first();

            if ($entry !== null) {
                return $entry;
            }
        }

        if ($reservation->status === SerproUsageReservationStatus::Blocked) {
            throw new LogicException('Reserva bloqueada não pode ser finalizada como chamada SERPRO.');
        }

        if ($reservation->status === SerproUsageReservationStatus::Released) {
            throw new LogicException('Reserva liberada não pode ser finalizada.');
        }

        $billable = $possiblyBillable ?? $result->possiblyBillableByDefault();

        // NAO_FATURAVEL nunca vira tentativa faturável.
        if ($reservation->consumption_class->value === 'NAO_FATURAVEL') {
            $billable = false;
        }

        return DB::transaction(function () use (
            $reservation,
            $result,
            $latencyMs,
            $httpStatus,
            $billable,
        ): SerproApiUsageEntry {
            $locked = SerproApiUsageReservation::query()
                ->withoutGlobalScopes()
                ->whereKey($reservation->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status === SerproUsageReservationStatus::Finalized) {
                return SerproApiUsageEntry::query()
                    ->withoutGlobalScopes()
                    ->where('reservation_id', $locked->id)
                    ->firstOrFail();
            }

            if ($locked->status === SerproUsageReservationStatus::Released) {
                throw new LogicException('Reserva liberada não pode ser finalizada.');
            }

            if ($locked->status === SerproUsageReservationStatus::Blocked) {
                throw new LogicException('Reserva bloqueada não pode ser finalizada como chamada SERPRO.');
            }

            $existingEntry = SerproApiUsageEntry::query()
                ->withoutGlobalScopes()
                ->where('idempotency_key', $locked->idempotency_key)
                ->first();

            if ($existingEntry !== null) {
                $locked->status = SerproUsageReservationStatus::Finalized;
                $locked->result = $result;
                $locked->latency_ms = $latencyMs;
                $locked->http_status = $httpStatus;
                $locked->possibly_billable = $billable;
                $locked->finalized_at = now();
                $locked->save();

                return $existingEntry;
            }

            // Custo histórico: preserva estimativa da reserva (não recalcula se preço mudou).
            $entryData = [
                'office_id' => $locked->office_id,
                'reservation_id' => $locked->id,
                'idempotency_key' => $locked->idempotency_key,
                'client_id' => $locked->client_id,
                'contributor_ref' => $locked->contributor_ref,
                'system_code' => $locked->system_code,
                'service_code' => $locked->service_code,
                'operation_code' => $locked->operation_code,
                'consumption_class' => $locked->consumption_class,
                'quantity' => $locked->quantity,
                'result' => $result,
                'correlation_id' => $locked->correlation_id,
                'price_version_id' => $locked->price_version_id,
                'estimated_cost_micros' => $locked->estimated_cost_micros,
                'is_billable_attempt' => $billable,
                'latency_ms' => $latencyMs,
                'http_status' => $httpStatus,
                'shadow_mode' => $locked->shadow_mode,
                'occurred_at' => now(),
                'created_at' => now(),
            ];
            if (Schema::hasColumn('serpro_api_usage_entries', 'operation_key')) {
                $entryData['operation_key'] = $locked->operation_key;
                $entryData['is_simulated'] = (bool) $locked->is_simulated;
                $entryData['request_tag'] = $locked->request_tag;
                $entryData['functional_route'] = $locked->functional_route;
            }
            $entry = SerproApiUsageEntry::query()->create($entryData);

            $locked->status = SerproUsageReservationStatus::Finalized;
            $locked->result = $result;
            $locked->latency_ms = $latencyMs;
            $locked->http_status = $httpStatus;
            $locked->possibly_billable = $billable;
            $locked->finalized_at = now();
            $locked->save();

            return $entry;
        });
    }

    /**
     * Libera reserva quando a chamada HTTP não foi disparada (não faturável).
     * Concorrente com finalize: se já Finalized, no-op seguro (retorna estado atual).
     */
    public function release(SerproApiUsageReservation $reservation): SerproApiUsageReservation
    {
        return DB::transaction(function () use ($reservation): SerproApiUsageReservation {
            $locked = SerproApiUsageReservation::query()
                ->withoutGlobalScopes()
                ->whereKey($reservation->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status->isTerminal()) {
                return $locked;
            }

            $locked->status = SerproUsageReservationStatus::Released;
            $locked->result = SerproUsageResult::Released;
            $locked->possibly_billable = false;
            $locked->finalized_at = now();
            $locked->save();

            return $locked;
        });
    }

    /**
     * Helper: reserva + callback + finalize/release.
     *
     * Se o callback retornar {@see IntegraResponse}, mapeia success/httpStatus → SerproUsageResult.
     * Se retornar array com chave `usage_result` (SerproUsageResult), usa esse valor.
     * Caso contrário, Success se não lançar.
     *
     * @template T
     *
     * @param  callable(): T  $call
     * @return array{outcome: UsageReserveOutcome, result: T|null, entry: SerproApiUsageEntry|null, error: \Throwable|null}
     */
    public function around(UsageReserveRequest $request, callable $call): array
    {
        $outcome = $this->reserve($request);

        if (! $outcome->allowed) {
            return [
                'outcome' => $outcome,
                'result' => null,
                'entry' => null,
                'error' => null,
            ];
        }

        $started = hrtime(true);
        try {
            $result = $call();
            $latency = (int) ((hrtime(true) - $started) / 1_000_000);
            [$usageResult, $httpStatus, $latencyOverride] = $this->resolveUsageResultFromCallback($result);
            if ($latencyOverride !== null) {
                $latency = $latencyOverride;
            }

            $entry = $this->finalize(
                $outcome->reservation,
                $usageResult,
                latencyMs: $latency,
                httpStatus: $httpStatus,
            );

            return [
                'outcome' => $outcome,
                'result' => $result,
                'entry' => $entry,
                'error' => null,
            ];
        } catch (\Throwable $e) {
            $latency = (int) ((hrtime(true) - $started) / 1_000_000);
            $entry = $this->finalize(
                $outcome->reservation,
                SerproUsageResult::TransportError,
                latencyMs: $latency,
                possiblyBillable: true,
            );

            return [
                'outcome' => $outcome,
                'result' => null,
                'entry' => $entry,
                'error' => $e,
            ];
        }
    }

    /**
     * Mapeia resposta Integra → resultado de uso do ledger.
     */
    public function mapIntegraResponse(IntegraResponse $response): SerproUsageResult
    {
        if ($response->success) {
            return SerproUsageResult::Success;
        }

        if ($response->httpStatus === 0 || $response->errorCode === 'TRANSPORT_ERROR') {
            return SerproUsageResult::TransportError;
        }

        if ($response->httpStatus === 408 || $response->httpStatus === 504
            || $response->errorCode === 'TIMEOUT') {
            return SerproUsageResult::Timeout;
        }

        if ($response->httpStatus >= 500) {
            return SerproUsageResult::HttpError;
        }

        if ($response->httpStatus >= 400) {
            return SerproUsageResult::ClientError;
        }

        return SerproUsageResult::Unknown;
    }

    private function assertSameOffice(SerproApiUsageReservation $reservation, int $officeId): void
    {
        if ((int) $reservation->office_id !== $officeId) {
            throw new LogicException(
                'idempotency_key já utilizado por outro office_id (isolamento de tenant).'
            );
        }
    }

    /**
     * Trava por office para serializar avaliação de orçamento dentro da transaction.
     * PostgreSQL: advisory xact lock; demais drivers: lockForUpdate na linha do office.
     */
    private function lockOfficeBudget(int $officeId): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            // Namespace estável (uso SERPRO) + office_id
            DB::select('SELECT pg_advisory_xact_lock(?, ?)', [0x5E12_0001, $officeId]);

            return;
        }

        Office::query()
            ->whereKey($officeId)
            ->lockForUpdate()
            ->first();
    }

    /**
     * @return array{0: SerproUsageResult, 1: int|null, 2: int|null} usage, httpStatus, latencyMs override
     */
    private function resolveUsageResultFromCallback(mixed $result): array
    {
        if ($result instanceof IntegraResponse) {
            return [
                $this->mapIntegraResponse($result),
                $result->httpStatus > 0 ? $result->httpStatus : null,
                $result->latencyMs,
            ];
        }

        if (is_array($result)) {
            $usage = $result['usage_result'] ?? null;
            if ($usage instanceof SerproUsageResult) {
                $http = isset($result['http_status']) ? (int) $result['http_status'] : null;
                $lat = isset($result['latency_ms']) ? (int) $result['latency_ms'] : null;

                return [$usage, $http, $lat];
            }
        }

        return [SerproUsageResult::Success, null, null];
    }
}

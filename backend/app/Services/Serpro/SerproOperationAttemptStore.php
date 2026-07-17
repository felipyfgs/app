<?php

namespace App\Services\Serpro;

use App\DTO\Serpro\IntegraResponse;
use App\Enums\FiscalSourceProvenance;
use App\Enums\SerproAttemptState;
use App\Models\SerproOperationAttempt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Persistência da state machine de tentativas do executor central.
 *
 * Garantia: no máximo um HTTP por chave lógica namespaced; replays finalizados
 * devolvem resultado persistido; concorrentes em dispatched bloqueiam.
 */
final class SerproOperationAttemptStore
{
    /**
     * @return array{
     *   action: 'dispatch'|'replay'|'wait',
     *   attempt: SerproOperationAttempt|null,
     *   response: IntegraResponse|null
     * }
     */
    public function beginOrReplay(
        int $officeId,
        string $environment,
        string $operationKey,
        string $entityKey,
        string $idempotencyKey,
        string $requestTag,
        ?string $correlationId,
        ?int $clientId,
    ): array {
        if (! Schema::hasTable('serpro_operation_attempts')) {
            return [
                'action' => 'dispatch',
                'attempt' => null,
                'response' => null,
            ];
        }

        return DB::transaction(function () use (
            $officeId,
            $environment,
            $operationKey,
            $entityKey,
            $idempotencyKey,
            $requestTag,
            $correlationId,
            $clientId,
        ): array {
            $existing = SerproOperationAttempt::query()
                ->withoutGlobalScopes()
                ->where('idempotency_key', $idempotencyKey)
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                if ((int) $existing->office_id !== $officeId) {
                    return [
                        'action' => 'wait',
                        'attempt' => $existing,
                        'response' => $this->blocked(
                            $operationKey,
                            'IDEMPOTENCY_CROSS_TENANT',
                            'Chave de idempotência pertence a outro escritório.',
                            $correlationId,
                            $requestTag,
                            409,
                        ),
                    ];
                }

                if ($existing->isTerminal()) {
                    return [
                        'action' => 'replay',
                        'attempt' => $existing,
                        'response' => $this->toResponse($existing),
                    ];
                }

                if ($existing->isInFlight()) {
                    return [
                        'action' => 'wait',
                        'attempt' => $existing,
                        'response' => $this->blocked(
                            $operationKey,
                            'ATTEMPT_IN_FLIGHT',
                            'Operação lógica já despachada; aguarde ou reconcilie.',
                            $correlationId,
                            $requestTag,
                            409,
                        ),
                    ];
                }

                // reserved → claim for dispatch
                $existing->forceFill([
                    'attempt_state' => SerproAttemptState::Dispatched,
                    'dispatched_at' => now(),
                    'request_tag' => $existing->request_tag ?: $requestTag,
                    'correlation_id' => $existing->correlation_id ?: $correlationId,
                ])->save();

                return [
                    'action' => 'dispatch',
                    'attempt' => $existing->fresh(),
                    'response' => null,
                ];
            }

            $attempt = SerproOperationAttempt::query()->create([
                'office_id' => $officeId,
                'environment' => $environment,
                'operation_key' => $operationKey,
                'entity_key' => $entityKey,
                'idempotency_key' => $idempotencyKey,
                'request_tag' => $requestTag,
                'correlation_id' => $correlationId,
                'attempt_state' => SerproAttemptState::Dispatched,
                'client_id' => $clientId,
                'reserved_at' => now(),
                'dispatched_at' => now(),
            ]);

            return [
                'action' => 'dispatch',
                'attempt' => $attempt,
                'response' => null,
            ];
        });
    }

    public function markReserved(SerproOperationAttempt $attempt, ?int $reservationId = null): void
    {
        if (! Schema::hasTable('serpro_operation_attempts')) {
            return;
        }

        $attempt->forceFill([
            'attempt_state' => SerproAttemptState::Reserved,
            'reservation_id' => $reservationId ?? $attempt->reservation_id,
            'reserved_at' => $attempt->reserved_at ?? now(),
            'dispatched_at' => null,
        ])->save();
    }

    public function attachReservation(SerproOperationAttempt $attempt, int $reservationId): void
    {
        if (! Schema::hasTable('serpro_operation_attempts')) {
            return;
        }

        $attempt->forceFill(['reservation_id' => $reservationId])->save();
    }

    public function acknowledge(SerproOperationAttempt $attempt, IntegraResponse $response): void
    {
        $this->persistTerminal($attempt, $response, SerproAttemptState::Acknowledged);
    }

    public function markUncertain(SerproOperationAttempt $attempt, IntegraResponse $response): void
    {
        $this->persistTerminal($attempt, $response, SerproAttemptState::Uncertain);
    }

    public function reconcile(SerproOperationAttempt $attempt, IntegraResponse $response): void
    {
        $this->persistTerminal($attempt, $response, SerproAttemptState::Reconciled);
    }

    private function persistTerminal(
        SerproOperationAttempt $attempt,
        IntegraResponse $response,
        SerproAttemptState $state,
    ): void {
        if (! Schema::hasTable('serpro_operation_attempts')) {
            return;
        }

        $now = now();
        $attempt->forceFill([
            'attempt_state' => $state,
            'success' => $response->success,
            'http_status' => $response->httpStatus > 0 ? $response->httpStatus : null,
            'error_code' => $response->errorCode,
            'error_message' => $response->errorMessage !== null
                ? mb_substr($response->errorMessage, 0, 500)
                : null,
            'simulated' => $response->simulated,
            'latency_ms' => $response->latencyMs,
            'source_provenance' => $response->sourceProvenance,
            'business_status' => $response->businessStatus,
            'functional_route' => $response->functionalRoute,
            'mensagens' => $response->mensagens,
            'dados' => $this->sanitizeAttemptDados($attempt->operation_key, $response->dados),
            'body' => $this->sanitizeAttemptBody($attempt->operation_key, $response->body),
            'headers' => $response->headers,
            'request_tag' => $response->requestTag ?? $attempt->request_tag,
            'correlation_id' => $response->correlationId ?? $attempt->correlation_id,
            'acknowledged_at' => in_array($state, [
                SerproAttemptState::Acknowledged,
                SerproAttemptState::Uncertain,
                SerproAttemptState::Reconciled,
            ], true) ? $now : $attempt->acknowledged_at,
            'reconciled_at' => $state === SerproAttemptState::Reconciled ? $now : $attempt->reconciled_at,
        ])->save();
    }

    public function toResponse(SerproOperationAttempt $attempt): IntegraResponse
    {
        return new IntegraResponse(
            success: (bool) $attempt->success,
            httpStatus: (int) ($attempt->http_status ?? 0),
            body: is_array($attempt->body) ? $attempt->body : [],
            headers: is_array($attempt->headers) ? $attempt->headers : [],
            errorCode: $attempt->error_code,
            errorMessage: $attempt->error_message,
            simulated: (bool) $attempt->simulated,
            correlationId: $attempt->correlation_id,
            latencyMs: $attempt->latency_ms,
            businessStatus: $attempt->business_status,
            mensagens: is_array($attempt->mensagens) ? $attempt->mensagens : [],
            dados: $attempt->dados,
            operationKey: $attempt->operation_key,
            requestTag: $attempt->request_tag,
            functionalRoute: $attempt->functional_route,
            sourceProvenance: $attempt->source_provenance
                ?? FiscalSourceProvenance::Unverified->value,
        );
    }

    private function blocked(
        string $operationKey,
        string $code,
        string $message,
        ?string $correlationId,
        ?string $requestTag,
        int $status,
    ): IntegraResponse {
        return new IntegraResponse(
            success: false,
            httpStatus: $status,
            body: [],
            errorCode: $code,
            errorMessage: $message,
            correlationId: $correlationId,
            operationKey: $operationKey,
            requestTag: $requestTag,
            sourceProvenance: FiscalSourceProvenance::Unverified->value,
        );
    }

    /**
     * Remove Base64 de PDFs PGDAS-D 14–16 antes de persistir no attempt store.
     *
     * @return array<string, mixed>|null
     */
    private function sanitizeAttemptDados(?string $operationKey, mixed $dados): ?array
    {
        if (! is_array($dados)) {
            if (is_string($dados) && $dados !== '') {
                $decoded = json_decode($dados, true);
                if (is_array($decoded)) {
                    $dados = $decoded;
                } else {
                    return null;
                }
            } else {
                return null;
            }
        }

        if (! $this->isPgdasdDocumentalKey($operationKey)) {
            return $dados;
        }

        return $this->stripPdfBase64Fields($dados);
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    private function sanitizeAttemptBody(?string $operationKey, array $body): array
    {
        if (! $this->isPgdasdDocumentalKey($operationKey)) {
            return $body;
        }

        if (isset($body['dados'])) {
            if (is_array($body['dados'])) {
                $body['dados'] = $this->stripPdfBase64Fields($body['dados']);
            } elseif (is_string($body['dados'])) {
                $decoded = json_decode($body['dados'], true);
                if (is_array($decoded)) {
                    $body['dados'] = $this->stripPdfBase64Fields($decoded);
                }
            }
        }

        return $this->stripPdfBase64Fields($body);
    }

    private function isPgdasdDocumentalKey(?string $operationKey): bool
    {
        return in_array($operationKey, [
            'pgdasd.consultimadecrec',
            'pgdasd.consdecrec',
            'pgdasd.consextrato',
        ], true);
    }

    /**
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>
     */
    private function stripPdfBase64Fields(array $node): array
    {
        foreach (['pdf', 'recibo', 'pdfNotificacao', 'pdfDarf', 'extrato', 'declaracao'] as $field) {
            if (isset($node[$field]) && is_string($node[$field]) && strlen($node[$field]) > 32) {
                $node[$field] = [
                    'sanitized' => true,
                    'omitted_from_attempt_store' => true,
                    'byte_length_estimate' => (int) floor(strlen($node[$field]) * 0.75),
                ];
            }
        }
        if (isset($node['maed']) && is_array($node['maed'])) {
            $node['maed'] = $this->stripPdfBase64Fields($node['maed']);
        }
        if (isset($node['recibo']) && is_array($node['recibo'])) {
            $node['recibo'] = $this->stripPdfBase64Fields($node['recibo']);
        }

        return $node;
    }
}

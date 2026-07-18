<?php

namespace Tests\Support\Fakes;

use App\Contracts\GuideEmissionClient;
use App\Services\Fiscal\Guides\DTO\GuideEmissionRequest;
use App\Services\Fiscal\Guides\DTO\GuideEmissionResult;
use App\Services\Fiscal\Guides\DTO\GuidePaymentLookupRequest;
use App\Services\Fiscal\Guides\DTO\GuidePaymentLookupResult;
use App\Services\Fiscal\Guides\DTO\GuideReconcileRequest;
use App\Services\Fiscal\Guides\DTO\GuideReconcileResult;
use App\Services\Fiscal\Guides\Exceptions\GuideTransportTimeoutException;
use Carbon\CarbonImmutable;

/**
 * Client de trial/CI — modos controláveis para testes de emissão/timeout/pagamento.
 */
final class FakeGuideEmissionClient implements GuideEmissionClient
{
    /** success | timeout | reject | different_amount */
    public string $emitMode = 'success';

    /** FOUND | NOT_FOUND | STILL_UNKNOWN | REJECTED */
    public string $reconcileMode = 'FOUND';

    /** NOT_FOUND | NOT_PAID | PAID | PARTIAL */
    public string $paymentMode = 'NOT_PAID';

    public int $defaultAmountCents = 150_00;

    public string $defaultIdentifier = 'GUIA-FAKE-001';

    public function emit(GuideEmissionRequest $request): GuideEmissionResult
    {
        if ($this->emitMode === 'timeout') {
            throw new GuideTransportTimeoutException(
                'Timeout simulado após POST de emissão.',
                $request->correlationId,
            );
        }

        if ($this->emitMode === 'reject') {
            throw new \RuntimeException('Rejeição simulada pela fonte oficial.');
        }

        $amount = $request->amountCents ?? $this->defaultAmountCents;
        if ($this->emitMode === 'different_amount') {
            $amount = $amount + 100;
        }

        $due = $request->dueAtIso ?? CarbonImmutable::now()->addDays(7)->toIso8601String();
        $identifier = $this->defaultIdentifier.'-'.substr(hash('sha256', $request->idempotencyKey), 0, 8);

        $pdf = "%PDF-1.4\n% fake-guide\n"
            ."%% identifier={$identifier}\n"
            ."%% amount={$amount}\n"
            ."%% office={$request->officeId}\n"
            ."%% client={$request->clientId}\n"
            ."%%EOF\n";

        return new GuideEmissionResult(
            documentBytes: $pdf,
            contentType: 'application/pdf',
            identifierCode: $identifier,
            amountCents: $amount,
            dueAtIso: $due,
            validUntilIso: CarbonImmutable::now()->addDays(5)->toIso8601String(),
            remoteProtocol: 'FAKE-PROTO-'.substr(hash('sha256', $request->idempotencyKey), 0, 12),
            correlationId: $request->correlationId,
            latencyMs: 5,
            simulated: true,
        );
    }

    public function reconcile(GuideReconcileRequest $request): GuideReconcileResult
    {
        return match ($this->reconcileMode) {
            'NOT_FOUND' => new GuideReconcileResult(outcome: 'NOT_FOUND'),
            'STILL_UNKNOWN' => new GuideReconcileResult(outcome: 'STILL_UNKNOWN'),
            'REJECTED' => new GuideReconcileResult(
                outcome: 'REJECTED',
                errorCode: 'REMOTE_REJECTED',
                errorMessage: 'Emissão rejeitada na reconciliação.',
            ),
            default => new GuideReconcileResult(
                outcome: 'FOUND',
                emission: $this->emit(new GuideEmissionRequest(
                    officeId: $request->officeId,
                    clientId: $request->clientId,
                    systemCode: $request->systemCode,
                    serviceCode: $request->serviceCode,
                    operationCode: $request->operationCode,
                    competencePeriodKey: $request->competencePeriodKey,
                    debitRef: $request->debitRef,
                    amountCents: $this->defaultAmountCents,
                    dueAtIso: CarbonImmutable::now()->addDays(7)->toIso8601String(),
                    idempotencyKey: $request->idempotencyKey,
                    correlationId: $request->correlationId,
                )),
            ),
        };
    }

    public function lookupPayment(GuidePaymentLookupRequest $request): GuidePaymentLookupResult
    {
        return match ($this->paymentMode) {
            'PAID' => new GuidePaymentLookupResult(
                status: 'PAID',
                externalId: 'PAY-'.($request->identifierCode ?? 'X'),
                amountCents: $this->defaultAmountCents,
                paidAtIso: CarbonImmutable::now()->toIso8601String(),
                source: 'INTEGRA_PAGAMENTO',
                evidenceBytes: json_encode(['paid' => true, 'id' => $request->identifierCode], JSON_THROW_ON_ERROR),
                contentType: 'application/json',
                official: true,
            ),
            'PARTIAL' => new GuidePaymentLookupResult(
                status: 'PARTIAL',
                externalId: 'PAY-PARTIAL-'.($request->identifierCode ?? 'X'),
                amountCents: (int) ($this->defaultAmountCents / 2),
                paidAtIso: CarbonImmutable::now()->toIso8601String(),
                source: 'INTEGRA_PAGAMENTO',
                official: true,
            ),
            'NOT_FOUND' => new GuidePaymentLookupResult(status: 'NOT_FOUND', official: true),
            default => new GuidePaymentLookupResult(status: 'NOT_PAID', official: true),
        };
    }
}

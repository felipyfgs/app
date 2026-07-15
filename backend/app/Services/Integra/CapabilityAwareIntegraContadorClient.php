<?php

namespace App\Services\Integra;

use App\Contracts\IntegraContadorClient;
use App\DTO\Serpro\IntegraRequest;
use App\DTO\Serpro\IntegraResponse;
use App\Enums\FiscalSourceProvenance;
use App\Enums\SerproCapabilityDriver;
use App\Services\Serpro\CapabilityDriverResolver;

/** Seleciona o transporte por capacidade, sem fallback entre drivers. */
final class CapabilityAwareIntegraContadorClient implements IntegraContadorClient
{
    public function __construct(
        private readonly CapabilityDriverResolver $drivers,
        private readonly FakeIntegraContadorClient $legacyFake,
        private readonly SimulatedIntegraContadorClient $simulated,
        private readonly HttpIntegraContadorClient $real,
    ) {}

    public function execute(IntegraRequest $request): IntegraResponse
    {
        if ($request->operationKey === null || $request->operationKey === '') {
            return $this->legacyClient()->execute($request);
        }

        return match ($this->drivers->forOperationKey($request->operationKey)) {
            SerproCapabilityDriver::Disabled => new IntegraResponse(
                success: false,
                httpStatus: 503,
                body: [],
                errorCode: 'CAPABILITY_DISABLED',
                errorMessage: 'Capacidade SERPRO desabilitada.',
                correlationId: $request->correlationId,
                operationKey: $request->operationKey,
                requestTag: $request->resolvedRequestTag(),
                sourceProvenance: FiscalSourceProvenance::Unverified->value,
            ),
            SerproCapabilityDriver::Simulated => $this->simulated->execute($request),
            SerproCapabilityDriver::Real => $this->real->execute($request),
        };
    }

    private function legacyClient(): IntegraContadorClient
    {
        $useFake = app()->environment('testing')
            || (bool) config('serpro.trial.use_fake_clients', true);

        return $useFake ? $this->legacyFake : $this->real;
    }
}

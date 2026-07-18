<?php

namespace Tests\Support;

use App\Contracts\SerproOperationExecutor;
use App\DTO\Serpro\IntegraRequest;
use App\DTO\Serpro\IntegraResponse;
use App\DTO\Serpro\MutationAuthorization;
use App\DTO\Serpro\SerproOperationCommand;
use App\Enums\FiscalSourceProvenance;
use App\Models\Client;
use App\Models\Office;
use Tests\Support\Fakes\FakeIntegraContadorClient;

/**
 * Executor estritamente de testes: encaminha cenários determinísticos ao
 * double explícito, sem transportar gates, credenciais ou egress do runtime.
 */
final class FakeSerproOperationExecutor implements SerproOperationExecutor
{
    public function __construct(private readonly FakeIntegraContadorClient $client) {}

    public function run(SerproOperationCommand $command): IntegraResponse
    {
        return $this->respond(new IntegraRequest(
            officeId: (int) $command->office->id,
            clientId: (int) ($command->client?->id ?? 0),
            environment: $command->environment ?? 'TRIAL',
            contractorCnpj: '11222333000181',
            authorIdentity: '52998224725',
            contributorCnpj: '11222333000181',
            operationKey: $command->operationKey,
            businessData: $command->businessData,
            payload: $command->payload,
            headers: $command->headers,
            idempotencyKey: $command->idempotencyKey,
            correlationId: $command->correlationId,
        ));
    }

    public function execute(
        Office $office,
        Client $client,
        string $operationKey,
        array $businessData = [],
        ?string $idempotencyKey = null,
        ?string $correlationId = null,
        ?MutationAuthorization $mutationAuth = null,
        ?string $entityKey = null,
        ?string $module = null,
    ): IntegraResponse {
        return $this->respond(new IntegraRequest(
            officeId: (int) $office->id,
            clientId: (int) $client->id,
            environment: (string) config('serpro.default_environment', 'TRIAL'),
            contractorCnpj: '11222333000181',
            authorIdentity: '52998224725',
            contributorCnpj: '11222333000181',
            operationKey: $operationKey,
            businessData: $businessData,
            idempotencyKey: $idempotencyKey,
            correlationId: $correlationId,
        ));
    }

    public function executeRequest(
        IntegraRequest $request,
        ?MutationAuthorization $mutationAuth = null,
        ?string $module = null,
    ): IntegraResponse {
        return $this->respond($request);
    }

    private function respond(IntegraRequest $request): IntegraResponse
    {
        if ($request->isMutating || in_array($request->operationKey, self::MUTATING_OPERATION_KEYS, true)) {
            return new IntegraResponse(
                success: false,
                httpStatus: 423,
                body: [],
                errorCode: 'MUTATION_DISABLED',
                errorMessage: 'Operação mutante bloqueada no executor de teste.',
                simulated: true,
                correlationId: $request->correlationId,
                operationKey: $request->operationKey,
                requestTag: $request->resolvedRequestTag(),
                sourceProvenance: FiscalSourceProvenance::Simulated->value,
            );
        }

        return $this->client->execute($request);
    }

    /** @var list<string> */
    private const MUTATING_OPERATION_KEYS = [
        'pgdasd.transdeclaracao', 'pgdasd.gerardas', 'pgdasd.gerardascobranca',
        'pgdasd.gerardasprocesso', 'pgdasd.gerardasavulso',
        'regimeapuracao.efetuaropcaoregime', 'defis.transdeclaracao',
        'pgmei.gerardaspdf', 'pgmei.gerardascodbarra', 'pgmei.atubeneficio',
        'dasnsimei.transdeclaracao', 'dasnsimei.gerardasexcesso',
        'dctfweb.gerarguia', 'dctfweb.gerarguiamaed', 'dctfweb.aplvinculacao',
        'dctfweb.transdeclaracao', 'dctfweb.gerarguiacomabatimento',
        'dctfweb.editarvalorsuspenso', 'dctfweb.gerarguiaandamento',
        'mit.encapuracao', 'sicalc.consolidargerardarf', 'sicalc.gerardarfcodbarra',
        'parcsn.gerardas', 'parcsn_esp.gerardas', 'pertsn.gerardas',
        'relpsn.gerardas', 'parcmei.gerardas', 'parcmei_esp.gerardas',
        'pertmei.gerardas', 'relpmei.gerardas',
        'parc_paex.emitirdocarrecadacao', 'parc_sipade.emitirdocarrecadacao',
        'pnr_contador.solicitar_renuncia',
    ];
}

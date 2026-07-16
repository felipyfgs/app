<?php

namespace App\Services\Integra;

use App\Contracts\IntegraProcuracoesClient;
use App\DTO\Serpro\ProcuracaoLookupRequest;
use App\DTO\Serpro\ProcuracaoLookupResult;
use App\DTO\Serpro\SerproOperationCommand;
use App\Models\Office;
use App\Services\Serpro\SerproOperationService;
use Carbon\CarbonImmutable;

/**
 * Lookup de procurações via executor central (operation_key oficial).
 */
final class HttpIntegraProcuracoesClient implements IntegraProcuracoesClient
{
    public const OPERATION_KEY = 'procuracoes.obter';

    public function __construct(
        private readonly SerproOperationService $operations,
    ) {}

    public function lookup(ProcuracaoLookupRequest $request): ProcuracaoLookupResult
    {
        $office = Office::query()->withoutGlobalScopes()->find($request->officeId);
        if ($office === null) {
            return new ProcuracaoLookupResult(
                success: false,
                powers: [],
                simulated: false,
                errorCode: 'OFFICE_NOT_FOUND',
                errorMessage: 'Office não encontrado.',
            );
        }

        $response = $this->operations->run(new SerproOperationCommand(
            office: $office,
            client: null,
            operationKey: self::OPERATION_KEY,
            businessData: array_filter([
                'codigoPoder' => $request->powerCode,
            ], static fn ($v) => $v !== null && $v !== ''),
            correlationId: $request->correlationId,
            environment: $request->environment,
            authorIdentityOverride: $request->authorIdentity,
            contributorIdentityOverride: $request->contributorCnpj,
        ));

        if (! $response->success) {
            return new ProcuracaoLookupResult(
                success: false,
                powers: [],
                simulated: $response->simulated,
                errorCode: $response->errorCode,
                errorMessage: $response->errorMessage,
                evidenceRef: $response->requestTag,
            );
        }

        $powers = $this->mapPowers($response->dados);

        return new ProcuracaoLookupResult(
            success: true,
            powers: $powers,
            simulated: $response->simulated,
            evidenceRef: $response->requestTag ?? ('PROCURACAO-'.$request->officeId),
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function mapPowers(mixed $dados): array
    {
        if (! is_array($dados)) {
            return [];
        }

        $rows = $dados['poderes'] ?? $dados['powers'] ?? $dados;
        if (! is_array($rows)) {
            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $out[] = [
                'power_code' => (string) ($row['codigo'] ?? $row['power_code'] ?? $row['codigoPoder'] ?? ''),
                'system_code' => (string) ($row['sistema'] ?? $row['system_code'] ?? ''),
                'service_code' => (string) ($row['servico'] ?? $row['service_code'] ?? ''),
                'valid_from' => isset($row['inicio']) ? CarbonImmutable::parse((string) $row['inicio']) : null,
                'valid_to' => isset($row['fim']) ? CarbonImmutable::parse((string) $row['fim']) : null,
                'raw' => $row,
            ];
        }

        return $out;
    }
}

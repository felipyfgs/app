<?php

namespace App\Services\Integra\Dctfweb;

use App\Contracts\IntegraContadorClient;
use App\DTO\Fiscal\FiscalAdapterRequest;
use App\DTO\Serpro\IntegraRequest;
use App\DTO\Serpro\IntegraResponse;
use App\Enums\SerproEnvironment;
use App\Enums\SerproUsageResult;
use App\Models\OfficeSerproAuthorization;
use App\Services\Integra\IntegraEligibilityService;
use App\Services\Serpro\SerproContractService;
use App\Services\Serpro\Usage\UsageLedgerService;
use App\Services\Serpro\Usage\UsageReserveRequest;

/**
 * Monta IntegraRequest a partir do contexto da run e identidades persistidas.
 * Nunca aceita CNPJ/autor vindos do frontend como autoridade.
 * Exige autorização real (sem fallback para contratante) + elegibilidade + ledger.
 */
final class DctfwebIntegraCaller
{
    private const PLACEHOLDER_AUTHOR = '00000000000000';

    public function __construct(
        private readonly IntegraContadorClient $client,
        private readonly SerproContractService $contracts,
        private readonly IntegraEligibilityService $eligibility,
        private readonly UsageLedgerService $ledger,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function call(
        FiscalAdapterRequest $request,
        string $solutionCode,
        string $serviceCode,
        string $operationCode,
        array $payload = [],
        ?string $idempotencyKey = null,
    ): IntegraResponse {
        $env = $this->resolveEnvironment();
        $contract = $this->contracts->activeFor($env);
        if ($contract === null || ! $contract->isUsable()) {
            return $this->blocked(
                'CONTRACT_UNAVAILABLE',
                'Contrato SERPRO indisponível.',
                $request->run->correlation_id,
            );
        }

        $contractorCnpj = strtoupper((string) $contract->contractor_cnpj);

        $auth = OfficeSerproAuthorization::query()
            ->where('office_id', $request->office->id)
            ->where('environment', $env->value)
            ->first();

        if ($auth === null) {
            return $this->blocked(
                'AUTHORIZATION_MISSING',
                'Autorização SERPRO do escritório ausente.',
                $request->run->correlation_id,
            );
        }

        $author = strtoupper(trim((string) ($auth->author_identity ?? '')));
        // Nunca fallback para CNPJ do contratante; placeholder de getOrCreate é inválido.
        if ($author === '' || $author === self::PLACEHOLDER_AUTHOR) {
            return $this->blocked(
                'AUTHOR_IDENTITY_MISSING',
                'Autor do Pedido não configurado (identidade real obrigatória).',
                $request->run->correlation_id,
            );
        }

        $elig = $this->eligibility->evaluate(
            $request->office,
            $request->client,
            $solutionCode,
            $serviceCode,
            $operationCode,
            $env,
            null,
            DctfwebCodes::MODULE,
        );

        if (! $elig->eligible) {
            $code = $elig->primaryCode()->value;

            return $this->blocked(
                $code,
                'Elegibilidade Integra negada: '.$code,
                $request->run->correlation_id,
            );
        }

        $this->eligibility->touchRateLimit((int) $request->office->id);

        $contributor = strtoupper(preg_replace('/[^0-9A-Za-z]/', '', (string) $request->client->root_cnpj) ?? '');
        if (strlen($contributor) !== 14) {
            $contributor = strtoupper((string) $request->client->root_cnpj);
        }

        $correlationId = $request->run->correlation_id;
        $idem = $idempotencyKey ?? $request->run->idempotency_key;
        $idem = 'dctf:'.$idem.':'.$operationCode;

        $reserve = $this->ledger->reserve(new UsageReserveRequest(
            officeId: (int) $request->office->id,
            idempotencyKey: $idem,
            systemCode: $solutionCode,
            serviceCode: $serviceCode,
            operationCode: $operationCode,
            quantity: 1,
            clientId: (int) $request->client->id,
            contributorRef: substr(hash('sha256', $contributor), 0, 16),
            correlationId: $correlationId,
        ));

        if (! $reserve->allowed) {
            return $this->blocked(
                'BUDGET_EXCEEDED',
                'Orçamento SERPRO bloqueou a operação.',
                $correlationId,
            );
        }

        $integra = new IntegraRequest(
            officeId: (int) $request->office->id,
            clientId: (int) $request->client->id,
            environment: $env->value,
            solutionCode: $solutionCode,
            serviceCode: $serviceCode,
            operationCode: $operationCode,
            contractorCnpj: $contractorCnpj,
            authorIdentity: $author,
            contributorCnpj: $contributor,
            payload: $payload,
            idempotencyKey: $idem,
            correlationId: $correlationId,
        );

        try {
            $response = $this->client->execute($integra);
        } catch (\Throwable $e) {
            $this->ledger->finalize(
                $reserve->reservation,
                SerproUsageResult::TransportError,
                possiblyBillable: true,
            );

            return new IntegraResponse(
                success: false,
                httpStatus: 0,
                body: [],
                errorCode: 'TRANSPORT_ERROR',
                errorMessage: 'Falha de transporte Integra Contador.',
                correlationId: $correlationId,
            );
        }

        $this->ledger->finalize(
            $reserve->reservation,
            $this->ledger->mapIntegraResponse($response),
            latencyMs: $response->latencyMs,
            httpStatus: $response->httpStatus > 0 ? $response->httpStatus : null,
        );

        return $response;
    }

    public function resolveEnvironment(): SerproEnvironment
    {
        $raw = strtoupper((string) config('serpro.default_environment', 'TRIAL'));

        return SerproEnvironment::tryFrom($raw) ?? SerproEnvironment::Trial;
    }

    /**
     * Serializa body para evidência (bytes estáveis).
     *
     * @param  array<string, mixed>  $body
     */
    public static function evidenceBytes(array $body): string
    {
        return json_encode($body, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }

    private function blocked(string $code, string $message, ?string $correlationId): IntegraResponse
    {
        return new IntegraResponse(
            success: false,
            httpStatus: 422,
            body: [],
            errorCode: $code,
            errorMessage: $message,
            correlationId: $correlationId,
        );
    }
}

<?php

namespace App\Services\Integra;

use App\Contracts\IntegraContadorClient;
use App\DTO\Serpro\IntegraRequest;
use App\DTO\Serpro\IntegraResponse;
use App\Enums\FiscalSourceProvenance;
use App\Services\Serpro\Catalog\OperationCoordinateResolver;
use Illuminate\Support\Str;

/**
 * Simulador determinístico contratualmente equivalente (mesmos DTOs/estados do cliente real).
 * Somente fora de produção; nunca alega origem SERPRO_REAL.
 *
 * Cenários (businessData['__scenario'] ou env SERPRO_SIMULATOR_SCENARIO):
 * success | processing | cache_304 | rate_limit | unavailable | unknown_contract
 */
final class SimulatedIntegraContadorClient implements IntegraContadorClient
{
    public function __construct(
        private readonly OperationCoordinateResolver $coordinates,
    ) {}

    public function execute(IntegraRequest $request): IntegraResponse
    {
        $operationKey = $request->operationKey
            ?? $this->inferOperationKey($request);
        $coords = $operationKey !== null
            ? $this->coordinates->resolve($operationKey)
            : null;

        $scenario = (string) ($request->businessData['__scenario']
            ?? $request->payload['__scenario']
            ?? config('serpro.simulator.scenario', 'success'));

        $tag = $request->resolvedRequestTag();
        $route = $coords['route']->value ?? null;

        return match ($scenario) {
            'processing' => $this->processing($request, $operationKey, $tag, $route),
            'cache_304' => $this->cache304($request, $operationKey, $tag, $route),
            'rate_limit' => $this->rateLimit($request, $operationKey, $tag, $route),
            'unavailable' => $this->unavailable($request, $operationKey, $tag, $route),
            'unknown_contract' => $this->unknownContract($request, $operationKey, $tag, $route),
            default => $this->success($request, $operationKey, $tag, $route, $coords),
        };
    }

    /**
     * @param  array<string, mixed>|null  $coords
     */
    private function success(
        IntegraRequest $request,
        ?string $operationKey,
        string $tag,
        ?string $route,
        ?array $coords,
    ): IntegraResponse {
        $body = $this->successBody($operationKey, $request);

        return new IntegraResponse(
            success: true,
            httpStatus: 200,
            body: $body,
            headers: ['etag' => 'W/"sim-'.substr($tag, 0, 8).'"'],
            simulated: true,
            correlationId: $request->correlationId,
            latencyMs: 5,
            etag: 'W/"sim-'.substr($tag, 0, 8).'"',
            businessStatus: 'OK',
            mensagens: [['codigo' => '0', 'texto' => 'Simulação OK']],
            dados: $body['dados'] ?? $body,
            operationKey: $operationKey,
            requestTag: $tag,
            functionalRoute: $route,
            sourceProvenance: FiscalSourceProvenance::Simulated->value,
        );
    }

    private function processing(
        IntegraRequest $request,
        ?string $operationKey,
        string $tag,
        ?string $route,
    ): IntegraResponse {
        return new IntegraResponse(
            success: false,
            httpStatus: 204,
            body: ['tempoEspera' => 30, 'status' => 'PROCESSANDO'],
            errorCode: 'STILL_PROCESSING',
            errorMessage: 'Relatório ainda em processamento (simulado).',
            simulated: true,
            retryAfterSeconds: 30,
            correlationId: $request->correlationId,
            latencyMs: 3,
            businessStatus: 'PROCESSANDO',
            mensagens: [['codigo' => '202', 'texto' => 'Em processamento']],
            operationKey: $operationKey,
            requestTag: $tag,
            functionalRoute: $route,
            sourceProvenance: FiscalSourceProvenance::Simulated->value,
        );
    }

    private function cache304(
        IntegraRequest $request,
        ?string $operationKey,
        string $tag,
        ?string $route,
    ): IntegraResponse {
        return new IntegraResponse(
            success: true,
            httpStatus: 304,
            body: [],
            headers: ['etag' => 'W/"cached"'],
            simulated: true,
            correlationId: $request->correlationId,
            etag: 'W/"cached"',
            expiresHeader: now()->addHour()->toRfc7231String(),
            businessStatus: 'NOT_MODIFIED',
            operationKey: $operationKey,
            requestTag: $tag,
            functionalRoute: $route,
            sourceProvenance: FiscalSourceProvenance::Simulated->value,
        );
    }

    private function rateLimit(
        IntegraRequest $request,
        ?string $operationKey,
        string $tag,
        ?string $route,
    ): IntegraResponse {
        return new IntegraResponse(
            success: false,
            httpStatus: 429,
            body: [],
            errorCode: 'RATE_LIMITED',
            errorMessage: 'Rate limit simulado.',
            simulated: true,
            retryAfterSeconds: 60,
            correlationId: $request->correlationId,
            operationKey: $operationKey,
            requestTag: $tag,
            functionalRoute: $route,
            sourceProvenance: FiscalSourceProvenance::Simulated->value,
        );
    }

    private function unavailable(
        IntegraRequest $request,
        ?string $operationKey,
        string $tag,
        ?string $route,
    ): IntegraResponse {
        return new IntegraResponse(
            success: false,
            httpStatus: 503,
            body: [],
            errorCode: 'UPSTREAM_ERROR',
            errorMessage: 'Indisponibilidade simulada.',
            simulated: true,
            correlationId: $request->correlationId,
            operationKey: $operationKey,
            requestTag: $tag,
            functionalRoute: $route,
            sourceProvenance: FiscalSourceProvenance::Simulated->value,
        );
    }

    private function unknownContract(
        IntegraRequest $request,
        ?string $operationKey,
        string $tag,
        ?string $route,
    ): IntegraResponse {
        return new IntegraResponse(
            success: false,
            httpStatus: 503,
            body: [],
            errorCode: 'CONTRACT_UNAVAILABLE',
            errorMessage: 'Contrato desconhecido (simulado).',
            simulated: true,
            correlationId: $request->correlationId,
            operationKey: $operationKey,
            requestTag: $tag,
            functionalRoute: $route,
            sourceProvenance: FiscalSourceProvenance::Simulated->value,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function successBody(?string $operationKey, IntegraRequest $request): array
    {
        if ($operationKey === 'sitfis.solicitar_protocolo'
            || ($request->operationCode === 'SOLICITAR_RELATORIO')
            || ($request->operationCode === 'SOLICITARPROTOCOLO91')) {
            $protocol = 'SIM-'.strtoupper(substr(hash('sha256', $request->contributorCnpj.$request->officeId), 0, 16));

            return [
                'status' => 'OK',
                'protocolo' => $protocol,
                'dados' => json_encode(['protocolo' => $protocol], JSON_THROW_ON_ERROR),
                'tempoEspera' => 5,
            ];
        }

        if ($operationKey === 'sitfis.emitir_relatorio'
            || ($request->operationCode === 'EMITIR_RELATORIO')
            || ($request->operationCode === 'RELATORIOSITFIS92')) {
            $report = [
                'layoutVersion' => '2.0',
                'situacao' => 'REGULAR',
                'pendencias' => [],
                'is_negative_certificate' => false,
                'resumo' => 'Situação fiscal regular (simulado).',
                'contribuinte' => $request->contributorCnpj,
            ];

            return [
                'status' => 'OK',
                'dados' => json_encode($report, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                'relatorio' => $report,
            ];
        }

        if ($operationKey === 'autentica_procurador.envio_xml_assinado') {
            return [
                'status' => 'OK',
                'dados' => json_encode([
                    'token' => 'SIM-PROCURADOR-'.Str::lower(Str::random(24)),
                    'expires_in' => 3600,
                ], JSON_THROW_ON_ERROR),
            ];
        }

        return [
            'status' => 'OK',
            'dados' => '{}',
            'simulated' => true,
        ];
    }

    private function inferOperationKey(IntegraRequest $request): ?string
    {
        $op = $request->operationCode;
        if ($op === 'SOLICITARPROTOCOLO91' || $op === 'SOLICITAR_RELATORIO') {
            return 'sitfis.solicitar_protocolo';
        }
        if ($op === 'RELATORIOSITFIS92' || $op === 'EMITIR_RELATORIO') {
            return 'sitfis.emitir_relatorio';
        }
        if ($op === 'ENVIOXMLASSINADO81' || $op === 'AUTENTICAR') {
            return 'autentica_procurador.envio_xml_assinado';
        }

        return $request->operationKey;
    }
}

<?php

namespace App\Services\Integra\Mailbox;

use App\Contracts\CaixaPostalClient;
use App\Contracts\SerproOperationExecutor;
use App\DTO\Mailbox\CaixaPostalDetailResult;
use App\DTO\Mailbox\CaixaPostalListResult;
use App\Enums\SerproCapabilityDriver;
use App\Models\Client;
use App\Models\Office;
use App\Services\Integra\ContributorCnpjResolver;
use App\Services\Serpro\CapabilityDriverResolver;

/**
 * Fonte Caixa Postal via executor central (operation_key tipado).
 * disabled → fail-closed; real → executor central.
 */
final class SerproCaixaPostalClient implements CaixaPostalClient
{
    public function __construct(
        private readonly SerproOperationExecutor $operations,
        private readonly CapabilityDriverResolver $drivers,
        private readonly ContributorCnpjResolver $contributors,
    ) {}

    public function listMessages(array $context = []): CaixaPostalListResult
    {
        $driver = $this->drivers->forCapability('mailbox');
        if ($driver === SerproCapabilityDriver::Disabled) {
            return new CaixaPostalListResult(
                success: false,
                errorCode: 'CAPABILITY_DISABLED',
                errorMessage: 'Caixa Postal desabilitada.',
            );
        }
        $resolved = $this->resolveContext($context);
        if ($resolved instanceof CaixaPostalListResult) {
            return $resolved;
        }
        [$office, $client, $contributor] = $resolved;

        $business = array_filter([
            'cnpjReferencia' => $contributor,
            'statusLeitura' => $context['status_leitura'] ?? null,
        ], static fn ($v) => $v !== null && $v !== '');

        $response = $this->operations->execute(
            office: $office,
            client: $client,
            operationKey: 'caixa_postal.lista',
            businessData: $business,
            correlationId: isset($context['correlation_id']) ? (string) $context['correlation_id'] : null,
            module: 'mailbox',
        );
        if ($response->hasSimulatedSource()) {
            $response = $response->rejectSimulatedSource();
        }

        if (! $response->success) {
            return new CaixaPostalListResult(
                success: false,
                simulated: $response->simulated,
                errorCode: $response->errorCode,
                errorMessage: $response->errorMessage,
            );
        }

        return new CaixaPostalListResult(
            success: true,
            items: $this->mapList($response->dados),
            simulated: $response->simulated,
        );
    }

    public function getMessageDetail(string $externalMessageId, array $context = []): CaixaPostalDetailResult
    {
        $driver = $this->drivers->forCapability('mailbox');
        if ($driver === SerproCapabilityDriver::Disabled) {
            return new CaixaPostalDetailResult(
                success: false,
                externalId: $externalMessageId,
                errorCode: 'CAPABILITY_DISABLED',
                errorMessage: 'Caixa Postal desabilitada.',
            );
        }
        $resolved = $this->resolveContext($context);
        if ($resolved instanceof CaixaPostalListResult) {
            return new CaixaPostalDetailResult(
                success: false,
                externalId: $externalMessageId,
                errorCode: $resolved->errorCode,
                errorMessage: $resolved->errorMessage,
            );
        }
        [$office, $client] = $resolved;

        $response = $this->operations->execute(
            office: $office,
            client: $client,
            operationKey: 'caixa_postal.detalhe',
            businessData: ['idMensagem' => $externalMessageId],
            correlationId: isset($context['correlation_id']) ? (string) $context['correlation_id'] : null,
            module: 'mailbox',
        );
        if ($response->hasSimulatedSource()) {
            $response = $response->rejectSimulatedSource();
        }

        if (! $response->success) {
            return new CaixaPostalDetailResult(
                success: false,
                externalId: $externalMessageId,
                simulated: $response->simulated,
                errorCode: $response->errorCode,
                errorMessage: $response->errorMessage,
            );
        }

        $dados = is_array($response->dados) ? $response->dados : [];

        return new CaixaPostalDetailResult(
            success: true,
            externalId: $externalMessageId,
            bodyBytes: isset($dados['conteudo']) ? (string) $dados['conteudo'] : (isset($dados['body']) ? (string) $dados['body'] : null),
            subject: isset($dados['assunto']) ? (string) $dados['assunto'] : null,
            simulated: $response->simulated,
            meta: $dados,
        );
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array{0: Office, 1: Client, 2: string}|CaixaPostalListResult
     */
    private function resolveContext(array $context): array|CaixaPostalListResult
    {
        $officeId = (int) ($context['office_id'] ?? 0);
        $clientId = (int) ($context['client_id'] ?? 0);
        $office = Office::query()->withoutGlobalScopes()->find($officeId);
        $client = Client::query()->withoutGlobalScopes()
            ->where('office_id', $officeId)
            ->whereKey($clientId)
            ->first();
        if ($office === null || $client === null) {
            return new CaixaPostalListResult(
                success: false,
                errorCode: 'CONTRIBUTOR_IDENTITY_MISSING',
                errorMessage: 'Cliente tenant-scoped não encontrado para Caixa Postal.',
            );
        }
        try {
            $contributor = $this->contributors->resolve($client);
        } catch (\Throwable) {
            return new CaixaPostalListResult(
                success: false,
                errorCode: 'CONTRIBUTOR_IDENTITY_MISSING',
                errorMessage: 'CNPJ completo do contribuinte não encontrado.',
            );
        }

        return [$office, $client, $contributor];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function mapList(mixed $dados): array
    {
        if (! is_array($dados)) {
            return [];
        }
        $rows = $dados['mensagens'] ?? $dados['messages'] ?? $dados;
        if (! is_array($rows)) {
            return [];
        }
        $out = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $out[] = [
                'external_id' => (string) ($row['id'] ?? $row['idMensagem'] ?? $row['external_id'] ?? ''),
                'subject' => (string) ($row['assunto'] ?? $row['subject'] ?? ''),
                'received_at' => $row['dataRecebimento'] ?? $row['received_at'] ?? null,
                'is_read' => (bool) ($row['lida'] ?? $row['is_read'] ?? false),
                'meta' => $row,
            ];
        }

        return $out;
    }
}

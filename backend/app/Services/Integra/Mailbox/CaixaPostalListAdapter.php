<?php

namespace App\Services\Integra\Mailbox;

use App\Contracts\CaixaPostalClient;
use App\Contracts\FiscalSourceAdapter;
use App\DTO\Fiscal\FiscalAdapterRequest;
use App\DTO\Fiscal\FiscalAdapterResult;
use App\Enums\FiscalCoverage;
use App\Enums\FiscalFindingSeverity;
use App\Enums\FiscalMutability;
use App\Enums\FiscalRunResult;
use App\Enums\FiscalSituation;

/**
 * Adapter lista Caixa Postal — system/service/operation distintos do DTE.
 */
final class CaixaPostalListAdapter implements FiscalSourceAdapter
{
    public function __construct(
        private readonly CaixaPostalClient $client,
        private readonly MailboxMessageStore $store,
    ) {}

    public function systemCode(): string
    {
        return 'INTEGRA_CAIXAPOSTAL';
    }

    public function serviceCode(): string
    {
        return 'CAIXA_POSTAL';
    }

    public function operationCode(): string
    {
        return 'LISTAR';
    }

    public function mutability(): FiscalMutability
    {
        return FiscalMutability::ReadOnly;
    }

    public function coverage(): FiscalCoverage
    {
        return FiscalCoverage::Full;
    }

    public function moduleKey(): ?string
    {
        return 'mailbox';
    }

    public function supports(FiscalAdapterRequest $request): bool
    {
        return strcasecmp($request->systemCode, $this->systemCode()) === 0
            && strcasecmp($request->serviceCode, $this->serviceCode()) === 0
            && (
                strcasecmp($request->operationCode, $this->operationCode()) === 0
                || strcasecmp($request->operationCode, 'MONITOR') === 0
            );
    }

    public function execute(FiscalAdapterRequest $request): FiscalAdapterResult
    {
        $list = $this->client->listMessages([
            'office_id' => $request->office->id,
            'client_id' => $request->client->id,
            'cnpj' => $request->client->root_cnpj,
            'correlation_id' => $request->run->correlation_id,
        ]);

        if (! $list->success) {
            $this->store->markListError($request->office, $request->client, $request->run->id);

            return FiscalAdapterResult::failed(
                $list->errorMessage ?? 'Falha ao listar Caixa Postal.',
                $list->errorCode ?? 'MAILBOX_LIST_FAILED',
            );
        }

        $applied = $this->store->applyList(
            $request->office,
            $request->client,
            $list,
            $request->run->id,
        );

        $evidence = json_encode([
            'operation' => 'LISTAR',
            'source' => 'CAIXA_POSTAL',
            'simulated' => $list->simulated,
            'item_count' => count($list->items),
            'created' => $applied['created'],
            'updated' => $applied['updated'],
            'official_unread_count' => $list->officialUnreadCount,
            // sem corpos/subjects
            'external_ids' => array_map(
                fn (array $i) => $i['external_id'] ?? null,
                $list->items
            ),
        ], JSON_THROW_ON_ERROR);

        $findings = [];
        if (($list->officialUnreadCount ?? 0) > 0 || $applied['created'] > 0) {
            $findings[] = [
                'code' => 'MAILBOX_NEW_OR_UNREAD',
                'severity' => FiscalFindingSeverity::Medium->value,
                'title' => 'Mensagens na Caixa Postal',
                'detail' => sprintf(
                    '%d nova(s), unread oficial: %s',
                    $applied['created'],
                    $list->officialUnreadCount === null ? 'n/d' : (string) $list->officialUnreadCount
                ),
                'situation' => FiscalSituation::Attention->value,
                'creates_pending' => $applied['created'] > 0,
            ];
        }

        $situation = ($applied['created'] > 0 || ($list->officialUnreadCount ?? 0) > 0)
            ? FiscalSituation::Attention
            : FiscalSituation::UpToDate;

        return new FiscalAdapterResult(
            result: FiscalRunResult::Success,
            situation: $situation,
            coverage: FiscalCoverage::Full,
            evidenceBytes: $evidence,
            evidenceContentType: 'application/json',
            sourceVersion: $list->sourceVersion,
            normalized: [
                'source' => 'CAIXA_POSTAL',
                'created' => $applied['created'],
                'updated' => $applied['updated'],
                'official_unread_count' => $list->officialUnreadCount,
                'messages_status' => 'CONSULTED',
            ],
            findings: $findings,
            itemsProcessed: count($list->items),
            pagesProcessed: 1,
        );
    }
}

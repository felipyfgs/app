<?php

namespace Tests\Support\Fakes;

use App\Contracts\CaixaPostalClient;
use App\DTO\Mailbox\CaixaPostalDetailResult;
use App\DTO\Mailbox\CaixaPostalListResult;
use Carbon\CarbonImmutable;

/**
 * Client trial/CI — respostas simuladas sem conteúdo fiscal real.
 * Configurável via propriedades públicas para testes.
 */
final class FakeCaixaPostalClient implements CaixaPostalClient
{
    /** @var list<array<string, mixed>> */
    public array $listItems = [];

    public int $listCalls = 0;

    public int $detailCalls = 0;

    /** @var list<CaixaPostalListResult> */
    public array $listResults = [];

    /** @var list<array<string, mixed>> */
    public array $listContexts = [];

    /** @var array<string, CaixaPostalDetailResult> */
    public array $detailsByExternalId = [];

    public function __construct()
    {
        $this->resetDefaults();
    }

    public function resetDefaults(): void
    {
        $now = CarbonImmutable::now();
        $this->listItems = [
            [
                'external_id' => 'msg-fake-001',
                'category_code' => 'INFORMATIVO',
                'category_label' => 'Informativo',
                'sender_code' => 'RFB',
                'sender_label' => 'Receita Federal',
                'subject' => 'Assunto simulado (não copiar em log)',
                'received_at' => $now->subDay()->toIso8601String(),
                'due_at' => $now->addDays(10)->toIso8601String(),
                'severity_hint' => 'MEDIUM',
                'official_read' => false,
                'has_attachment' => true,
            ],
        ];
        $this->detailsByExternalId = [
            'msg-fake-001' => new CaixaPostalDetailResult(
                success: true,
                externalId: 'msg-fake-001',
                bodyBytes: "Corpo simulado da mensagem fiscal.\nNão deve aparecer em inbox/log.",
                bodyContentType: 'text/plain; charset=utf-8',
                attachments: [[
                    'external_id' => 'att-1',
                    'filename' => 'anexo-simulado.pdf',
                    'content_type' => 'application/pdf',
                    'bytes' => '%PDF-1.4 fake attachment body',
                ]],
                categoryCode: 'INFORMATIVO',
                categoryLabel: 'Informativo',
                senderCode: 'RFB',
                senderLabel: 'Receita Federal',
                subject: 'Assunto simulado (não copiar em log)',
                receivedAt: $now->subDay()->toIso8601String(),
                dueAt: $now->addDays(10)->toIso8601String(),
                severityHint: 'MEDIUM',
                officialRead: false,
                simulated: true,
            ),
        ];
        $this->listCalls = 0;
        $this->detailCalls = 0;
        $this->listResults = [];
        $this->listContexts = [];
    }

    public function listMessages(array $context = []): CaixaPostalListResult
    {
        $this->listCalls++;
        $this->listContexts[] = $context;

        if ($this->listResults !== []) {
            return array_shift($this->listResults);
        }

        return new CaixaPostalListResult(
            success: true,
            items: $this->listItems,
            officialUnreadCount: count(array_filter(
                $this->listItems,
                fn (array $i) => ($i['official_read'] ?? false) === false
            )),
            simulated: true,
            rawMeta: ['item_count' => count($this->listItems)],
        );
    }

    public function getMessageDetail(string $externalMessageId, array $context = []): CaixaPostalDetailResult
    {
        $this->detailCalls++;

        if (isset($this->detailsByExternalId[$externalMessageId])) {
            return $this->detailsByExternalId[$externalMessageId];
        }

        return new CaixaPostalDetailResult(
            success: false,
            externalId: $externalMessageId,
            simulated: true,
            errorCode: 'NOT_FOUND',
            errorMessage: 'Mensagem simulada não encontrada.',
        );
    }
}

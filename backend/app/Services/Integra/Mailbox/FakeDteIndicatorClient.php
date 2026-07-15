<?php

namespace App\Services\Integra\Mailbox;

use App\Contracts\DteIndicatorClient;
use App\DTO\Mailbox\DteIndicatorResult;
use App\Enums\MailboxDteStatus;

/**
 * Client trial/CI do indicador DTE (proveniência separada).
 */
final class FakeDteIndicatorClient implements DteIndicatorClient
{
    public int $calls = 0;

    public MailboxDteStatus $status = MailboxDteStatus::Active;

    public bool $success = true;

    public function getIndicator(array $context = []): DteIndicatorResult
    {
        $this->calls++;

        if (! $this->success) {
            return new DteIndicatorResult(
                success: false,
                status: MailboxDteStatus::Error,
                simulated: true,
                errorCode: 'DTE_FAKE_ERROR',
                errorMessage: 'Falha simulada no indicador DTE.',
            );
        }

        return new DteIndicatorResult(
            success: true,
            status: $this->status,
            simulated: true,
            meta: ['simulated' => true],
        );
    }
}

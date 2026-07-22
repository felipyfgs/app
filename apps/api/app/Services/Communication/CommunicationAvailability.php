<?php

namespace App\Services\Communication;

use App\Enums\Communication\InboxStatus;
use App\Models\CommunicationInbox;
use DomainException;

final class CommunicationAvailability
{
    public function assertEnabled(CommunicationInbox $inbox, bool $requiresConnected = false): void
    {
        if (! config('communication.enabled') || ! config('communication.gateway.enabled')) {
            throw new DomainException('COMMUNICATION_DISABLED');
        }

        $inbox->loadMissing('office');
        if (! $inbox->office?->communication_enabled) {
            throw new DomainException('OFFICE_COMMUNICATION_DISABLED');
        }
        if (! $inbox->is_enabled || $inbox->revoked_at !== null) {
            throw new DomainException('INBOX_COMMUNICATION_DISABLED');
        }
        if ($requiresConnected && $inbox->status !== InboxStatus::Connected) {
            throw new DomainException('INBOX_NOT_CONNECTED');
        }
    }
}

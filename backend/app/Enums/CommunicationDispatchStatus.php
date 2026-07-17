<?php

namespace App\Enums;

enum CommunicationDispatchStatus: string
{
    case NotConfigured = 'NOT_CONFIGURED';
    case NoHistory = 'NO_HISTORY';
    case Queued = 'QUEUED';
    case Sent = 'SENT';
    case Delivered = 'DELIVERED';
    case Read = 'READ';
    case Partial = 'PARTIAL';
    case Failed = 'FAILED';
    case Canceled = 'CANCELED';
}

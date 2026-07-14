<?php

namespace App\Enums;

enum OfficeRole: string
{
    case Admin = 'ADMIN';
    case Operator = 'OPERATOR';
    case Viewer = 'VIEWER';

    public function isAdmin(): bool
    {
        return $this === self::Admin;
    }

    public function canManageClients(): bool
    {
        return $this === self::Admin || $this === self::Operator;
    }

    public function canManageCredentials(): bool
    {
        return $this === self::Admin;
    }

    public function canTriggerSync(): bool
    {
        return $this === self::Admin || $this === self::Operator;
    }

    public function canExport(): bool
    {
        return $this === self::Admin || $this === self::Operator;
    }
}

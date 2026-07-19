<?php

namespace App\Enums;

/**
 * State machine do onboarding de autorização SERPRO do escritório.
 * Derivado de perfil + consentimento + A1 canônico (jobs em waves posteriores).
 */
enum OfficeSerproOnboardingStatus: string
{
    case Incomplete = 'incomplete';
    case Ready = 'ready';
    case Provisioning = 'provisioning';
    case Authorized = 'authorized';
    case ActionRequired = 'action_required';
    case TechnicalError = 'technical_error';
    case Revoked = 'revoked';

    public function isTerminal(): bool
    {
        return $this === self::Revoked;
    }

    public function allowsExternalCalls(): bool
    {
        return $this === self::Authorized;
    }
}

<?php

namespace App\Policies;

use App\Enums\OfficeRole;
use App\Models\OutboundCaptureProfile;
use App\Models\User;
use App\Support\CurrentOffice;

class OutboundCaptureProfilePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->role() !== null;
    }

    public function view(User $user, OutboundCaptureProfile $profile): bool
    {
        return $this->sameOffice($user, $profile->office_id);
    }

    public function create(User $user): bool
    {
        return in_array($this->role(), [OfficeRole::Admin, OfficeRole::Operator], true);
    }

    public function update(User $user, OutboundCaptureProfile $profile): bool
    {
        return $this->sameOffice($user, $profile->office_id)
            && in_array($this->role(), [OfficeRole::Admin, OfficeRole::Operator], true);
    }

    /** Ativação, allowlist, mandato, kill switch, CSC — ADMIN. */
    public function administer(User $user, OutboundCaptureProfile $profile): bool
    {
        return $this->sameOffice($user, $profile->office_id)
            && $this->role() === OfficeRole::Admin;
    }

    public function triggerReadOnly(User $user, OutboundCaptureProfile $profile): bool
    {
        return $this->sameOffice($user, $profile->office_id)
            && in_array($this->role(), [OfficeRole::Admin, OfficeRole::Operator], true);
    }

    public function uploadPackage(User $user, OutboundCaptureProfile $profile): bool
    {
        return $this->triggerReadOnly($user, $profile);
    }

    private function role(): ?OfficeRole
    {
        return app(CurrentOffice::class)->role();
    }

    private function sameOffice(User $user, int $officeId): bool
    {
        return app(CurrentOffice::class)->resolve($user)?->id === $officeId;
    }
}

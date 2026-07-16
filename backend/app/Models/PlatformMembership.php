<?php

namespace App\Models;

use App\Enums\PlatformRole;
use Database\Factories\PlatformMembershipFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Associação global usuário ↔ papel da plataforma.
 * SEM office_id de membership — escopo global estrutural.
 * default_office_id: Office padrão de contexto (não cria OfficeMembership).
 */
#[Fillable(['user_id', 'role', 'is_active', 'default_office_id'])]
class PlatformMembership extends Model
{
    /** @use HasFactory<PlatformMembershipFactory> */
    use HasFactory;

    protected $table = 'platform_memberships';

    protected function casts(): array
    {
        return [
            'role' => PlatformRole::class,
            'is_active' => 'boolean',
            'default_office_id' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function defaultOffice(): BelongsTo
    {
        return $this->belongsTo(Office::class, 'default_office_id');
    }

    public function isPlatformAdmin(): bool
    {
        return $this->is_active && $this->role === PlatformRole::PlatformAdmin;
    }
}

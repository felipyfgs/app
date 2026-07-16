<?php

namespace App\Models;

use App\Enums\CredentialStatus;
use App\Enums\RegistrationSource;
use App\Models\Concerns\BelongsToOffice;
use Database\Factories\ClientFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

#[Fillable([
    'office_id',
    'legal_name',
    'display_name',
    'root_cnpj',
    'matrix_client_id',
    'legal_nature_code',
    'legal_nature_name',
    'company_size_code',
    'company_size_name',
    'tax_regime',
    'notes',
    'is_active',
    'inactive_reason',
    'registration_source',
    'registration_refreshed_at',
])]
class Client extends Model
{
    /** @use HasFactory<ClientFactory> */
    use BelongsToOffice, HasFactory, SoftDeletes;

    protected static function booted(): void
    {
        // Evidência fiscal/financeira: exclusão física bloqueada (retenção explícita).
        static::forceDeleting(function (Client $client): void {
            // Sem depender do global scope da request (fail-closed / sem CurrentOffice).
            $hasCursors = Establishment::query()
                ->withoutGlobalScopes()
                ->where('client_id', $client->id)
                ->where('office_id', $client->office_id)
                ->whereHas('syncCursors')
                ->exists();

            $hasEvidence = $hasCursors
                || DB::table('dfe_documents')
                    ->where('office_id', $client->office_id)
                    ->whereExists(function ($q) use ($client): void {
                        $q->selectRaw('1')
                            ->from('document_interests as di')
                            ->join('establishments as e', 'e.id', '=', 'di.establishment_id')
                            ->whereColumn('di.dfe_document_id', 'dfe_documents.id')
                            ->where('e.client_id', $client->id)
                            ->where('e.office_id', $client->office_id);
                    })
                    ->exists()
                || DB::table('fiscal_monitoring_runs')
                    ->where('client_id', $client->id)
                    ->where('office_id', $client->office_id)
                    ->exists()
                || DB::table('serpro_api_usage_entries')
                    ->where('client_id', $client->id)
                    ->where('office_id', $client->office_id)
                    ->exists();

            if ($hasEvidence) {
                throw new \RuntimeException(
                    'Exclusão física de Cliente bloqueada: existe evidência fiscal ou de consumo. Use inativação.',
                );
            }
        });
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'registration_source' => RegistrationSource::class,
            'registration_refreshed_at' => 'datetime',
        ];
    }

    public function establishments(): HasMany
    {
        return $this->hasMany(Establishment::class);
    }

    /** Matriz à qual esta filial está vinculada (null se for raiz/matriz). */
    public function matrix(): BelongsTo
    {
        return $this->belongsTo(self::class, 'matrix_client_id');
    }

    /** Filiais que apontam para este cliente como matriz. */
    public function branches(): HasMany
    {
        return $this->hasMany(self::class, 'matrix_client_id');
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(ClientContact::class);
    }

    public function customFields(): HasMany
    {
        return $this->hasMany(ClientCustomField::class);
    }

    public function primaryContact(): HasOne
    {
        return $this->hasOne(ClientContact::class)
            ->where('is_primary', true)
            ->where('is_active', true);
    }

    /**
     * Credencial A1 ativa (uso operacional: UI/sync).
     * Histórico (SUPERSEDED/EXPIRED/etc.) via credentials().
     */
    public function credential(): HasOne
    {
        return $this->hasOne(ClientCredential::class)
            ->where('status', CredentialStatus::Active);
    }

    /** Todas as credenciais do cliente (qualquer status). */
    public function credentials(): HasMany
    {
        return $this->hasMany(ClientCredential::class);
    }

    /**
     * Nome preferencial para UI (nome interno ou razão social).
     */
    public function displayLabel(): string
    {
        $display = trim((string) ($this->display_name ?? ''));

        return $display !== '' ? $display : (string) $this->legal_name;
    }
}

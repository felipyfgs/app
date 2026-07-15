<?php

namespace App\Models;

use App\Enums\SerproBillableClass;
use App\Enums\SerproEnvironment;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'catalog_version',
    'environment',
    'solution_code',
    'service_code',
    'operation_code',
    'label',
    'is_mutating',
    'is_enabled',
    'required_proxy_power',
    'billable_class',
    'cache_ttl_seconds',
    'rate_limit_per_minute',
    'coverage',
    'metadata',
    'effective_from',
    'effective_to',
])]
class SerproServiceCatalogEntry extends Model
{
    protected function casts(): array
    {
        return [
            'environment' => SerproEnvironment::class,
            'billable_class' => SerproBillableClass::class,
            'is_mutating' => 'boolean',
            'is_enabled' => 'boolean',
            'metadata' => 'array',
            'effective_from' => 'immutable_datetime',
            'effective_to' => 'immutable_datetime',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toPublicArray(): array
    {
        return [
            'id' => $this->id,
            'catalog_version' => $this->catalog_version,
            'environment' => $this->environment->value,
            'solution_code' => $this->solution_code,
            'service_code' => $this->service_code,
            'operation_code' => $this->operation_code,
            'label' => $this->label,
            'is_mutating' => $this->is_mutating,
            'is_enabled' => $this->is_enabled,
            'required_proxy_power' => $this->required_proxy_power,
            'billable_class' => $this->billable_class->value,
            'cache_ttl_seconds' => $this->cache_ttl_seconds,
            'rate_limit_per_minute' => $this->rate_limit_per_minute,
            'coverage' => $this->coverage,
        ];
    }
}

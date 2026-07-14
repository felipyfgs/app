<?php

namespace App\Models\Concerns;

use App\Models\Office;
use App\Support\CurrentOffice;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin Model
 */
trait BelongsToOffice
{
    public static function bootBelongsToOffice(): void
    {
        static::creating(function (Model $model): void {
            if ($model->getAttribute('office_id') === null) {
                $officeId = app(CurrentOffice::class)->id();
                if ($officeId !== null) {
                    $model->setAttribute('office_id', $officeId);
                }
            }
        });

        static::addGlobalScope('office', function (Builder $builder): void {
            $officeId = app(CurrentOffice::class)->id();

            if ($officeId !== null) {
                $builder->where($builder->getModel()->getTable().'.office_id', $officeId);
            }
        });
    }

    public function office(): BelongsTo
    {
        return $this->belongsTo(Office::class);
    }
}

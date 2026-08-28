<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

trait BelongsToOrganization
{
    protected static function bootBelongsToOrganization(): void
    {
        static::addGlobalScope('organization', function (Builder $builder) {
            if (!auth()->check()) return;
            $user = auth()->user();
            if ($user->is_superadmin) return;
            if ($user->organization_id) {
                $builder->where($builder->getModel()->getTable().'.organization_id', $user->organization_id);
            }
        });

        static::creating(function ($model) {
            if (!$model->organization_id && auth()->check() && !auth()->user()->is_superadmin) {
                $model->organization_id = auth()->user()->organization_id;
            }
        });
    }
}

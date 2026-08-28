<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

trait BelongsToOrganization
{
    protected static function bootBelongsToOrganization(): void
    {
        static::addGlobalScope('organization', function (Builder $builder) {
            if (auth()->check()) {
                $user=auth()->user();
                if($user->is_superadmin)return;
                if($user->organization_id)$builder->where($builder->getModel()->getTable().'.organization_id',$user->organization_id);
                return;
            }
            $contextId=(int)config('tenant.organization_id',0);
            if($contextId)$builder->where($builder->getModel()->getTable().'.organization_id',$contextId);
        });

        static::creating(function ($model) {
            if($model->organization_id)return;
            if(auth()->check()&&!auth()->user()->is_superadmin){$model->organization_id=auth()->user()->organization_id;return;}
            $contextId=(int)config('tenant.organization_id',0);
            if(!$contextId&&app()->runningInConsole()){
                $args=implode(' ',$_SERVER['argv']??[]);
                if(str_contains($args,'db:seed'))$contextId=(int)DB::table('organizations')->orderBy('id')->value('id');
            }
            if($contextId)$model->organization_id=$contextId;
        });
    }
}

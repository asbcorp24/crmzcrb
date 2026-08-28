<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

trait BelongsToOrganization
{
    protected static function bootBelongsToOrganization(): void
    {
        static::addGlobalScope('organization', function (Builder $builder) {
            $isSuperadmin = (bool) config('tenant.is_superadmin', false);
            if (!$isSuperadmin && app()->bound('session')) {
                try { $isSuperadmin = (bool) session('tenant_is_superadmin', false); } catch (\Throwable $e) {}
            }
            if ($isSuperadmin) return;

            $contextId = (int) config('tenant.organization_id', 0);
            if (!$contextId && app()->bound('session')) {
                try { $contextId = (int) session('tenant_organization_id', 0); } catch (\Throwable $e) {}
            }
            if ($contextId) {
                $builder->where($builder->getModel()->getTable().'.organization_id', $contextId);
            }
        });

        static::creating(function ($model) {
            if ($model->organization_id) return;

            $contextId = (int) config('tenant.organization_id', 0);
            if (!$contextId && app()->bound('session')) {
                try { $contextId = (int) session('tenant_organization_id', 0); } catch (\Throwable $e) {}
            }

            if (!$contextId && app()->runningInConsole()) {
                $args = implode(' ', $_SERVER['argv'] ?? []);
                if (str_contains($args, 'db:seed')) {
                    $contextId = (int) DB::table('organizations')->orderBy('id')->value('id');
                }
            }

            if ($contextId) $model->organization_id = $contextId;
        });
    }
}

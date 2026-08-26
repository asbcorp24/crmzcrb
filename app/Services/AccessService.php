<?php

namespace App\Services;

use App\Models\Department;
use App\Models\User;
use Illuminate\Support\Collection;

class AccessService
{
    public function userIds(User $viewer, bool $includeSelf = true): Collection
    {
        if ($viewer->isAdmin()) {
            $ids = User::where('is_active', true)->pluck('id');
            return $includeSelf ? $ids : $ids->reject(fn ($id) => (int)$id === (int)$viewer->id)->values();
        }

        if (!$viewer->isManager()) {
            return $includeSelf ? collect([$viewer->id]) : collect();
        }

        $result = collect();
        $frontier = collect([$viewer->id]);
        $seen = collect([$viewer->id => true]);

        while ($frontier->isNotEmpty()) {
            $children = User::where('is_active', true)
                ->whereIn('manager_id', $frontier)
                ->pluck('id');

            $next = collect();
            foreach ($children as $id) {
                if (!$seen->has($id)) {
                    $seen->put($id, true);
                    $result->push((int)$id);
                    $next->push((int)$id);
                }
            }
            $frontier = $next;
        }

        return $includeSelf ? collect([$viewer->id])->merge($result)->values() : $result->values();
    }

    public function canViewUser(User $viewer, User $target): bool
    {
        return $this->userIds($viewer, true)->contains((int)$target->id);
    }

    public function canManageUser(User $viewer, User $target): bool
    {
        if (!$viewer->isManager()) return false;
        if ($viewer->isAdmin()) return true;
        return (int)$viewer->id !== (int)$target->id && $this->userIds($viewer, false)->contains((int)$target->id);
    }

    public function departmentIds(User $viewer): Collection
    {
        if ($viewer->isAdmin()) return Department::where('is_active', true)->pluck('id');

        $userIds = $this->userIds($viewer, true);
        $ids = User::whereIn('id', $userIds)->whereNotNull('department_id')->pluck('department_id')->unique()->values();

        $frontier = $ids;
        $seen = $ids->mapWithKeys(fn ($id) => [(int)$id => true]);
        while ($frontier->isNotEmpty()) {
            $parents = Department::whereIn('id', $frontier)->whereNotNull('parent_id')->pluck('parent_id')->unique();
            $next = collect();
            foreach ($parents as $id) {
                if (!$seen->has($id)) {
                    $seen->put($id, true);
                    $ids->push((int)$id);
                    $next->push((int)$id);
                }
            }
            $frontier = $next;
        }

        return $ids->unique()->values();
    }

    public function canViewDepartment(User $viewer, Department $department): bool
    {
        return $this->departmentIds($viewer)->contains((int)$department->id);
    }
}

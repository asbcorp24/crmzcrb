<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExternalCrmDepartmentMapping extends Model
{
    use BelongsToOrganization;

    protected $fillable = [
        'organization_id',
        'department_id',
        'external_workshop_id',
        'external_workshop_name',
        'external_manager_user_id',
        'external_manager_name',
        'external_task_type_id',
        'external_task_type_name',
        'is_active',
    ];

    protected $casts = [
        'external_workshop_id' => 'integer',
        'external_manager_user_id' => 'integer',
        'external_task_type_id' => 'integer',
        'is_active' => 'boolean',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }
}

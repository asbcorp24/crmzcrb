<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StaffingPosition extends Model
{
    protected $fillable = ['department_id','position_id','planned_rate','note','is_active'];
    protected $casts = ['planned_rate'=>'decimal:2','is_active'=>'boolean'];
    protected $appends = ['occupied_rate','vacant_rate'];

    public function department(): BelongsTo { return $this->belongsTo(Department::class); }
    public function position(): BelongsTo { return $this->belongsTo(Position::class); }
    public function assignments(): HasMany { return $this->hasMany(EmployeeAssignment::class); }
    public function activeAssignments(): HasMany { return $this->hasMany(EmployeeAssignment::class)->whereNull('ended_at'); }

    public function getOccupiedRateAttribute(): float
    {
        return (float) $this->activeAssignments()->sum('rate');
    }

    public function getVacantRateAttribute(): float
    {
        return max(0, round((float)$this->planned_rate - $this->occupied_rate, 2));
    }
}

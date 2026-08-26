<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeAssignment extends Model
{
    protected $fillable = ['user_id','staffing_position_id','rate','is_primary','started_at','ended_at','order_number','note'];
    protected $casts = ['rate'=>'decimal:2','is_primary'=>'boolean','started_at'=>'date','ended_at'=>'date'];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function staffingPosition(): BelongsTo { return $this->belongsTo(StaffingPosition::class); }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Position extends Model
{
    protected $fillable = ['name','category','code','is_active'];
    protected $casts = ['is_active'=>'boolean'];
    public function staffingPositions(): HasMany { return $this->hasMany(StaffingPosition::class); }
}

<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Position extends Model
{
    use BelongsToOrganization;
    protected $fillable = ['organization_id','name','category','code','is_active'];
    protected $casts = ['is_active'=>'boolean'];
    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
    public function staffingPositions(): HasMany { return $this->hasMany(StaffingPosition::class); }
}

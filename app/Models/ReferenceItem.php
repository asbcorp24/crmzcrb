<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;

class ReferenceItem extends Model
{
    use BelongsToOrganization;

    protected $fillable = [
        'organization_id','type','code','name','system_key','color','notes','sort_order','is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];
}

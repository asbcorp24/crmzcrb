<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Department extends Model
{
    use BelongsToOrganization;
    protected $fillable = ['organization_id','parent_id','name','short_name','type','is_active','sort_order'];
    protected $casts = ['is_active'=>'boolean'];
    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
    public function parent(): BelongsTo { return $this->belongsTo(self::class,'parent_id'); }
    public function children(): HasMany { return $this->hasMany(self::class,'parent_id')->orderBy('sort_order')->orderBy('name'); }
    public function users(): HasMany { return $this->hasMany(User::class); }
}

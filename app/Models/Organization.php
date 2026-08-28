<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Organization extends Model
{
    protected $fillable = ['name','short_name','code','slug','logo_path','icon_path','primary_color','secondary_color','timezone','is_active','settings'];
    protected $casts = ['is_active'=>'boolean','settings'=>'array'];

    public function users(): HasMany { return $this->hasMany(User::class); }
    public function departments(): HasMany { return $this->hasMany(Department::class); }

    public function getDisplayNameAttribute(): string { return $this->short_name ?: $this->name; }
}

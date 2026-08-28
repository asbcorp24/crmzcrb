<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class TaskTag extends Model
{
    use BelongsToOrganization;
    protected $fillable = ['organization_id','name','slug','is_active'];
    protected $casts = ['is_active'=>'boolean'];
    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
    public function tasks(): BelongsToMany { return $this->belongsToMany(Task::class,'task_tag'); }
}

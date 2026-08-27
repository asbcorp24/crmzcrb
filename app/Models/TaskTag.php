<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class TaskTag extends Model
{
    protected $fillable = ['name','slug','is_active'];
    protected $casts = ['is_active'=>'boolean'];
    public function tasks(): BelongsToMany { return $this->belongsToMany(Task::class, 'task_tag'); }
}

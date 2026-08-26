<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskDeadlineChange extends Model
{
    protected $fillable = ['task_id','user_id','old_due_at','new_due_at','reason'];
    protected $casts = ['old_due_at'=>'datetime','new_due_at'=>'datetime'];
    public function task(): BelongsTo { return $this->belongsTo(Task::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}

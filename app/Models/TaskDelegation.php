<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskDelegation extends Model
{
    protected $fillable = ['task_id','from_user_id','to_user_id','delegated_by','reason'];

    public function task(): BelongsTo { return $this->belongsTo(Task::class); }
    public function fromUser(): BelongsTo { return $this->belongsTo(User::class, 'from_user_id'); }
    public function toUser(): BelongsTo { return $this->belongsTo(User::class, 'to_user_id'); }
    public function delegatedByUser(): BelongsTo { return $this->belongsTo(User::class, 'delegated_by'); }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskChecklistItem extends Model
{
    protected $fillable = ['task_id','title','is_done','completed_by','completed_at','sort_order'];
    protected $casts = ['is_done'=>'boolean','completed_at'=>'datetime'];
    public function task(): BelongsTo { return $this->belongsTo(Task::class); }
    public function completedBy(): BelongsTo { return $this->belongsTo(User::class, 'completed_by'); }
}

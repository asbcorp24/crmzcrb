<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeetingItem extends Model
{
    protected $fillable = ['meeting_id','number','instruction','assigned_to','due_at','priority','task_id','created_by'];
    protected $casts = ['due_at'=>'datetime'];

    public function meeting(): BelongsTo { return $this->belongsTo(Meeting::class); }
    public function assignee(): BelongsTo { return $this->belongsTo(User::class, 'assigned_to'); }
    public function task(): BelongsTo { return $this->belongsTo(Task::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
}

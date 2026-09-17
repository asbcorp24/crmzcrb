<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionMeetingItem extends Model
{
    protected $fillable = [
        'production_meeting_id','number','instruction','responsible_department_id','coexecutor_id',
        'start_at','due_at','duration_days','status','task_id','created_by',
    ];

    protected $casts = [
        'start_at' => 'date',
        'due_at' => 'date',
    ];

    public function meeting(): BelongsTo { return $this->belongsTo(ProductionMeeting::class, 'production_meeting_id'); }
    public function department(): BelongsTo { return $this->belongsTo(Department::class, 'responsible_department_id'); }
    public function coexecutor(): BelongsTo { return $this->belongsTo(User::class, 'coexecutor_id'); }
    public function task(): BelongsTo { return $this->belongsTo(Task::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Task extends Model
{
    protected $fillable = ['plan_id','created_by','assigned_to','title','description','priority','status','progress','started_at','due_at','completed_at','result'];
    protected $casts = ['started_at'=>'datetime','due_at'=>'datetime','completed_at'=>'datetime'];
    protected $appends = ['is_overdue'];

    public function plan(): BelongsTo { return $this->belongsTo(Plan::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function assignee(): BelongsTo { return $this->belongsTo(User::class, 'assigned_to'); }
    public function comments(): HasMany { return $this->hasMany(TaskComment::class)->latest(); }
    public function events(): HasMany { return $this->hasMany(TaskEvent::class)->latest(); }
    public function attachments(): HasMany { return $this->hasMany(TaskAttachment::class)->latest(); }

    public function getIsOverdueAttribute(): bool
    {
        return $this->due_at && $this->due_at->isPast() && !in_array($this->status, ['completed','cancelled'], true);
    }
}

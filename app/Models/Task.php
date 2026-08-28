<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Task extends Model
{
    use BelongsToOrganization;

    protected $fillable = ['organization_id','plan_id','parent_task_id','created_by','assigned_to','title','description','priority','status','progress','started_at','due_at','completed_at','archived_at','archived_by','result'];
    protected $casts = ['started_at'=>'datetime','due_at'=>'datetime','completed_at'=>'datetime','archived_at'=>'datetime'];
    protected $appends = ['is_overdue','is_blocked'];

    protected static function booted(): void
    {
        static::created(function (Task $task) {
            Plan::recalculateById($task->plan_id);
            if (!$task->due_at || !$task->assigned_to || !$task->created_by) return;
            $date = $task->due_at->toDateString();
            $absence = EmployeeAbsence::where('user_id',$task->assigned_to)->whereDate('date_from','<=',$date)->whereDate('date_to','>=',$date)->first();
            if (!$absence) return;
            $type = ['vacation'=>'отпуск','sick_leave'=>'больничный','business_trip'=>'командировка','training'=>'обучение','other'=>'отсутствие'][$absence->type] ?? 'отсутствие';
            $sub = EmployeeSubstitution::with('substituteUser')->where('absent_user_id',$task->assigned_to)->whereDate('date_from','<=',$date)->whereDate('date_to','>=',$date)->latest()->first();
            $body = 'Исполнитель отсутствует на дату срока: '.$type.' '.$absence->date_from->format('d.m.Y').'–'.$absence->date_to->format('d.m.Y').'.';
            if ($sub?->substituteUser) $body .= ' Заместитель: '.$sub->substituteUser->full_name.'.';
            CrmNotification::create(['user_id'=>$task->created_by,'task_id'=>$task->id,'type'=>'assignee_absent','title'=>'Исполнитель отсутствует в срок задачи','body'=>$body,'url'=>route('tasks.page',['task'=>$task->id],false)]);
        });
        static::updated(function (Task $task) {
            $oldPlanId=(int)($task->getOriginal('plan_id')??0); $newPlanId=(int)($task->plan_id??0);
            if($oldPlanId&&$oldPlanId!==$newPlanId) Plan::recalculateById($oldPlanId);
            if($newPlanId) Plan::recalculateById($newPlanId);
        });
        static::deleted(fn(Task $task)=>Plan::recalculateById($task->plan_id));
    }

    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
    public function plan(): BelongsTo { return $this->belongsTo(Plan::class); }
    public function parent(): BelongsTo { return $this->belongsTo(self::class,'parent_task_id'); }
    public function subtasks(): HasMany { return $this->hasMany(self::class,'parent_task_id')->orderBy('due_at'); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class,'created_by'); }
    public function assignee(): BelongsTo { return $this->belongsTo(User::class,'assigned_to'); }
    public function archivedBy(): BelongsTo { return $this->belongsTo(User::class,'archived_by'); }
    public function comments(): HasMany { return $this->hasMany(TaskComment::class)->latest(); }
    public function events(): HasMany { return $this->hasMany(TaskEvent::class)->latest(); }
    public function attachments(): HasMany { return $this->hasMany(TaskAttachment::class)->latest(); }
    public function checklistItems(): HasMany { return $this->hasMany(TaskChecklistItem::class)->orderBy('sort_order'); }
    public function deadlineChanges(): HasMany { return $this->hasMany(TaskDeadlineChange::class)->latest(); }
    public function overdueReasons(): HasMany { return $this->hasMany(TaskOverdueReason::class)->latest(); }
    public function delegations(): HasMany { return $this->hasMany(TaskDelegation::class)->latest(); }
    public function tags(): BelongsToMany { return $this->belongsToMany(TaskTag::class,'task_tag'); }
    public function blockers(): BelongsToMany { return $this->belongsToMany(self::class,'task_dependencies','task_id','blocked_by_task_id')->withTimestamps(); }
    public function blockedTasks(): BelongsToMany { return $this->belongsToMany(self::class,'task_dependencies','blocked_by_task_id','task_id')->withTimestamps(); }
    public function getIsOverdueAttribute(): bool { return $this->due_at&&$this->due_at->isPast()&&!in_array($this->status,['completed','cancelled'],true); }
    public function getIsBlockedAttribute(): bool { return $this->relationLoaded('blockers')?$this->blockers->contains(fn(Task $task)=>!in_array($task->status,['completed','cancelled'],true)):$this->blockers()->whereNotIn('status',['completed','cancelled'])->exists(); }
}

<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    use BelongsToOrganization;
    protected $fillable = ['organization_id','user_id','created_by','title','description','period_start','period_end','period_type','status','progress','archived_at','archived_by'];
    protected $casts = ['period_start'=>'date','period_end'=>'date','archived_at'=>'datetime'];
    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class,'created_by'); }
    public function archivedBy(): BelongsTo { return $this->belongsTo(User::class,'archived_by'); }
    public function tasks(): HasMany { return $this->hasMany(Task::class); }
    public function recalculateProgress(): int { $tasks=$this->tasks()->whereNull('archived_at')->get(['status','progress']); $progress=$tasks->isEmpty()?0:(int)round($tasks->avg(fn(Task $task)=>$task->status==='completed'?100:(int)$task->progress)); if((int)$this->progress!==$progress)$this->forceFill(['progress'=>$progress])->saveQuietly(); return $progress; }
    public static function recalculateById(?int $planId): void { if(!$planId)return; $plan=static::find($planId); if($plan)$plan->recalculateProgress(); }
}

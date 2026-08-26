<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    protected $fillable = ['user_id','created_by','title','description','period_start','period_end','period_type','status','progress'];
    protected $casts = ['period_start'=>'date','period_end'=>'date'];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function tasks(): HasMany { return $this->hasMany(Task::class); }

    /**
     * Пересчитать прогресс плана по фактическому состоянию его задач.
     * Выполненная задача всегда считается как 100%, остальные — по полю progress.
     */
    public function recalculateProgress(): int
    {
        $tasks = $this->tasks()->get(['status', 'progress']);
        $progress = $tasks->isEmpty()
            ? 0
            : (int) round($tasks->avg(fn (Task $task) => $task->status === 'completed' ? 100 : (int) $task->progress));

        if ((int) $this->progress !== $progress) {
            $this->forceFill(['progress' => $progress])->saveQuietly();
        }

        return $progress;
    }

    public static function recalculateById(?int $planId): void
    {
        if (!$planId) return;
        $plan = static::find($planId);
        if ($plan) $plan->recalculateProgress();
    }
}

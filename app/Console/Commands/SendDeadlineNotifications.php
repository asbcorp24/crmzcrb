<?php

namespace App\Console\Commands;

use App\Models\CrmNotification;
use App\Models\Task;
use Illuminate\Console\Command;

class SendDeadlineNotifications extends Command
{
    protected $signature = 'crm:deadlines';
    protected $description = 'Создаёт уведомления о сроках, просрочках и задачах без движения';

    public function handle(): int
    {
        $created = 0;

        $dueSoon = Task::with('assignee')->whereNotIn('status', ['completed','cancelled'])
            ->whereBetween('due_at', [now(), now()->copy()->addDay()])->get();
        foreach ($dueSoon as $task) {
            if (!$this->existsEver($task->id, 'task_due_soon')) {
                $this->notify($task->assigned_to,$task,'task_due_soon','Скоро срок выполнения задачи',$task->title.' · срок '.$task->due_at->format('d.m.Y H:i'));
                $created++;
            }
        }

        $overdue = Task::with('assignee')->whereNotIn('status', ['completed','cancelled'])
            ->whereNotNull('due_at')->where('due_at', '<', now())->get();
        foreach ($overdue as $task) {
            if (!$this->existsEver($task->id, 'task_overdue')) {
                $this->notify($task->assigned_to,$task,'task_overdue','Задача просрочена',$task->title.' · срок был '.$task->due_at->format('d.m.Y H:i'));
                $created++;
            }
            $managerId=$task->assignee?->manager_id;
            if ($managerId && !$this->existsToday($task->id,'manager_task_overdue',$managerId)) {
                $this->notify($managerId,$task,'manager_task_overdue','Просрочка у сотрудника',($task->assignee?->full_name ?? 'Сотрудник').' · '.$task->title);
                $created++;
            }
        }

        $stale = Task::with('assignee')->whereNotIn('status',['completed','cancelled','review'])
            ->where('updated_at','<',now()->subDays(3))->get();
        foreach ($stale as $task) {
            $managerId=$task->assignee?->manager_id;
            if ($managerId && !$this->existsToday($task->id,'task_stale',$managerId)) {
                $days=max(3,(int)$task->updated_at->diffInDays(now()));
                $this->notify($managerId,$task,'task_stale','Нет движения по задаче',($task->assignee?->full_name ?? 'Сотрудник').' · '.$task->title.' · без изменений '.$days.' дн.');
                $created++;
            }
        }

        $this->info('Создано уведомлений: '.$created);
        return self::SUCCESS;
    }

    private function notify(int $userId,Task $task,string $type,string $title,string $body): void
    {
        CrmNotification::create([
            'user_id'=>$userId,'task_id'=>$task->id,'type'=>$type,'title'=>$title,'body'=>$body,
            'url'=>route('tasks.page',['task'=>$task->id],false),
        ]);
    }

    private function existsEver(int $taskId,string $type): bool
    {
        return CrmNotification::where('task_id',$taskId)->where('type',$type)->exists();
    }

    private function existsToday(int $taskId,string $type,int $userId): bool
    {
        return CrmNotification::where('task_id',$taskId)->where('user_id',$userId)->where('type',$type)->whereDate('created_at',today())->exists();
    }
}

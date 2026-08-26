<?php

namespace App\Console\Commands;

use App\Models\CrmNotification;
use App\Models\Task;
use Illuminate\Console\Command;

class SendDeadlineNotifications extends Command
{
    protected $signature = 'crm:deadlines';
    protected $description = 'Создаёт уведомления о приближающихся и просроченных задачах';

    public function handle(): int
    {
        $created = 0;

        $dueSoon = Task::whereNotIn('status', ['completed','cancelled'])
            ->whereBetween('due_at', [now(), now()->copy()->addDay()])
            ->get();

        foreach ($dueSoon as $task) {
            if (!$this->exists($task->id, 'task_due_soon')) {
                CrmNotification::create([
                    'user_id' => $task->assigned_to,
                    'task_id' => $task->id,
                    'type' => 'task_due_soon',
                    'title' => 'Скоро срок выполнения задачи',
                    'body' => $task->title.' · срок '.$task->due_at->format('d.m.Y H:i'),
                    'url' => route('tasks.page', ['task' => $task->id]),
                ]);
                $created++;
            }
        }

        $overdue = Task::whereNotIn('status', ['completed','cancelled'])
            ->whereNotNull('due_at')
            ->where('due_at', '<', now())
            ->get();

        foreach ($overdue as $task) {
            if (!$this->exists($task->id, 'task_overdue')) {
                CrmNotification::create([
                    'user_id' => $task->assigned_to,
                    'task_id' => $task->id,
                    'type' => 'task_overdue',
                    'title' => 'Задача просрочена',
                    'body' => $task->title.' · срок был '.$task->due_at->format('d.m.Y H:i'),
                    'url' => route('tasks.page', ['task' => $task->id]),
                ]);
                $created++;
            }
        }

        $this->info('Создано уведомлений: '.$created);
        return self::SUCCESS;
    }

    private function exists(int $taskId, string $type): bool
    {
        return CrmNotification::where('task_id', $taskId)->where('type', $type)->exists();
    }
}

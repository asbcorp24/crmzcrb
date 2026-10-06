<?php

namespace App\Console\Commands;

use App\Models\Organization;
use App\Models\Task;
use App\Models\TaskDeadlineChange;
use App\Models\TaskEvent;
use App\Services\ExternalCrmService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncExternalCrmTasks extends Command
{
    protected $signature = 'crm:sync-external-tasks {--organization=} {--limit=500}';
    protected $description = 'Подтягивает статусы, сроки и результаты связанных задач из внешней CRM';

    public function handle(ExternalCrmService $crm): int
    {
        $query = Task::withoutGlobalScope('organization')
            ->whereNotNull('external_crm_task_id')
            ->whereNull('archived_at')
            ->orderBy('id');

        if ($this->option('organization')) {
            $query->where('organization_id', (int)$this->option('organization'));
        }

        $limit = max(1, min(5000, (int)$this->option('limit')));
        $tasks = $query->limit($limit)->get();
        $done = 0;
        $changed = 0;
        $failed = 0;

        foreach ($tasks as $task) {
            $organization = Organization::find($task->organization_id);
            if (!$organization || !$crm->configured($organization)) {
                continue;
            }

            try {
                $remote = $crm->taskDetails($organization, (int)$task->external_crm_task_id);
                $data = isset($remote['data']) && is_array($remote['data']) ? $remote['data'] : [];
                $changes = $this->applyRemote($task, $data);
                $done++;

                if ($changes) {
                    $changed++;
                    TaskEvent::create([
                        'task_id' => $task->id,
                        'user_id' => $task->created_by ?: $task->assigned_to,
                        'type' => 'external_crm_auto_pulled',
                        'from_status' => $changes['from_status'],
                        'to_status' => $changes['to_status'],
                        'message' => 'Автосинхронизация с внешней CRM #'.$task->external_crm_task_id.
                            ': '.implode('; ', $changes['messages']),
                    ]);
                }
            } catch (\Throwable $e) {
                $failed++;
                DB::table('tasks')->where('id', $task->id)->update([
                    'external_crm_sync_error' => mb_substr($e->getMessage(), 0, 5000),
                    'updated_at' => now(),
                ]);
                $this->warn('Task #'.$task->id.': '.$e->getMessage());
            }
        }

        $this->info('Проверено: '.$done.'; изменено: '.$changed.'; ошибок: '.$failed.'.');

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function applyRemote(Task $task, array $data): ?array
    {
        $oldStatus = $task->status;
        $oldDueAt = $task->due_at?->copy();
        $oldStartAt = $task->start_at?->copy();
        $oldResult = (string)($task->result ?? '');

        $remoteStatus = (string)($data['status'] ?? '');
        $localStatus = $this->localStatus($remoteStatus, $task->status);

        $update = [
            'external_crm_remote_status' => $remoteStatus ?: null,
            'external_crm_pulled_at' => now(),
            'external_crm_sync_error' => null,
            'updated_at' => now(),
        ];

        if ($localStatus !== '') {
            $update['status'] = $localStatus;

            if ($localStatus === 'completed') {
                $update['progress'] = 100;
                $update['completed_at'] = $task->completed_at ?: now();
                $update['started_at'] = $task->started_at ?: now();
            } elseif ($localStatus === 'cancelled') {
                $update['completed_at'] = null;
            } elseif ($localStatus === 'in_progress') {
                $update['completed_at'] = null;
                $update['started_at'] = $task->started_at ?: now();
                if ((int)$task->progress === 0) $update['progress'] = 1;
            }
        }

        if (!empty($data['start_date'])) {
            $update['start_at'] = Carbon::parse($data['start_date']);
        }

        if (array_key_exists('end_date', $data) || array_key_exists('postponed_to', $data)) {
            $remoteDue = ($remoteStatus === 'postponed' && !empty($data['postponed_to']))
                ? $data['postponed_to']
                : ($data['end_date'] ?? null);
            $newDueAt = !empty($remoteDue) ? Carbon::parse($remoteDue) : null;
            $update['due_at'] = $newDueAt;

            if (($oldDueAt?->timestamp) !== ($newDueAt?->timestamp)) {
                TaskDeadlineChange::create([
                    'task_id' => $task->id,
                    'user_id' => $task->created_by ?: $task->assigned_to,
                    'old_due_at' => $oldDueAt,
                    'new_due_at' => $newDueAt,
                    'reason' => 'Автоматическая синхронизация с внешней CRM',
                ]);
            }
        }

        $resultParts = [];
        foreach ([
            'decision' => 'Решение',
            'status_comment' => 'Комментарий',
            'approve_comment' => 'Комментарий утверждения',
        ] as $key => $label) {
            $value = trim((string)($data[$key] ?? ''));
            if ($value !== '') $resultParts[] = $label.': '.$value;
        }
        if ($resultParts) {
            $update['result'] = implode("\n", array_unique($resultParts));
        }

        DB::table('tasks')->where('id', $task->id)->update($update);
        $fresh = $task->fresh();

        $messages = [];
        if ($oldStatus !== $fresh->status) {
            $messages[] = 'статус '.$oldStatus.' → '.$fresh->status;
        }
        if (($oldDueAt?->timestamp) !== ($fresh->due_at?->timestamp)) {
            $messages[] = 'срок '.($oldDueAt?->format('d.m.Y H:i') ?? 'не задан').
                ' → '.($fresh->due_at?->format('d.m.Y H:i') ?? 'не задан');
        }
        if (($oldStartAt?->timestamp) !== ($fresh->start_at?->timestamp)) {
            $messages[] = 'изменена дата начала';
        }
        if ($oldResult !== (string)($fresh->result ?? '')) {
            $messages[] = 'обновлён результат';
        }

        if (!$messages) return null;

        return [
            'from_status' => $oldStatus,
            'to_status' => $fresh->status,
            'messages' => $messages,
        ];
    }

    private function localStatus(string $status, string $current): string
    {
        return [
            'in_progress' => $current === 'review' ? 'review' : 'in_progress',
            'done' => 'completed',
            'not_done' => 'in_progress',
            'postponed' => 'in_progress',
            'cancelled' => 'cancelled',
        ][$status] ?? $current;
    }
}

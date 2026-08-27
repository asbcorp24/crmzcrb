<?php

namespace App\Console\Commands;

use App\Models\CrmNotification;
use App\Models\EmployeeAbsence;
use App\Models\EmployeeSubstitution;
use App\Models\Plan;
use App\Models\Task;
use Illuminate\Console\Command;

class SendDeadlineNotifications extends Command
{
    protected $signature = 'crm:deadlines';
    protected $description = 'Создаёт уведомления о сроках, просрочках, отсутствии и планах';

    public function handle(): int
    {
        $created = 0;

        $dueSoon = Task::with('assignee')->whereNull('archived_at')->whereNotIn('status', ['completed','cancelled'])
            ->whereBetween('due_at', [now(), now()->copy()->addDay()])->get();
        foreach ($dueSoon as $task) {
            if (!$this->existsToday($task->id, 'task_due_soon', $task->assigned_to)) {
                $this->notifyTask($task->assigned_to,$task,'task_due_soon','Срок задачи завтра',$task->title.' · срок '.$task->due_at->format('d.m.Y H:i')); $created++;
            }
        }

        $overdue = Task::with('assignee')->whereNull('archived_at')->whereNotIn('status', ['completed','cancelled'])
            ->whereNotNull('due_at')->where('due_at', '<', now())->get();
        foreach ($overdue as $task) {
            if (!$this->existsEver($task->id, 'task_overdue')) { $this->notifyTask($task->assigned_to,$task,'task_overdue','Задача просрочена',$task->title.' · срок был '.$task->due_at->format('d.m.Y H:i')); $created++; }
            $managerId=$task->assignee?->manager_id;
            if ($managerId && !$this->existsToday($task->id,'manager_task_overdue',$managerId)) { $this->notifyTask($managerId,$task,'manager_task_overdue','Просрочка у сотрудника',($task->assignee?->full_name ?? 'Сотрудник').' · '.$task->title); $created++; }
        }

        $stale = Task::with('assignee')->whereNull('archived_at')->whereNotIn('status',['completed','cancelled','review'])
            ->where('updated_at','<',now()->subDays(3))->get();
        foreach ($stale as $task) {
            $managerId=$task->assignee?->manager_id;
            if ($managerId && !$this->existsToday($task->id,'task_stale',$managerId)) {
                $days=max(3,(int)$task->updated_at->diffInDays(now())); $this->notifyTask($managerId,$task,'task_stale','Задача без движения 3+ дня',($task->assignee?->full_name ?? 'Сотрудник').' · '.$task->title.' · без изменений '.$days.' дн.'); $created++;
            }
        }

        $absenceDate=today()->addDays(3);
        foreach(EmployeeAbsence::with('user.manager')->whereDate('date_from',$absenceDate)->get() as $absence){
            $managerId=$absence->user?->manager_id; if(!$managerId)continue;
            $key='absence_upcoming_'.$absence->id;
            if(!$this->existsGenericToday($managerId,$key)){$this->notifyGeneric($managerId,$key,'Отсутствие сотрудника через 3 дня',($absence->user?->full_name??'Сотрудник').' · '.$this->absenceName($absence->type).' с '.$absence->date_from->format('d.m.Y'),'availability.page');$created++;}
        }

        $subEnd=today()->addDays(3);
        foreach(EmployeeSubstitution::with(['absentUser','substituteUser'])->whereDate('date_to',$subEnd)->get() as $sub){
            $managerId=$sub->absentUser?->manager_id; if(!$managerId)continue; $key='substitution_ending_'.$sub->id;
            if(!$this->existsGenericToday($managerId,$key)){$this->notifyGeneric($managerId,$key,'Замещение заканчивается через 3 дня',($sub->absentUser?->full_name??'Сотрудник').' → '.($sub->substituteUser?->full_name??'заместитель').' · до '.$sub->date_to->format('d.m.Y'),'availability.page');$created++;}
        }

        $planEnd=today()->addDays(7);
        foreach(Plan::with(['user.manager'])->whereNull('archived_at')->whereNotIn('status',['completed','cancelled'])->whereDate('period_end',$planEnd)->get() as $plan){
            $recipients=array_unique(array_filter([$plan->user_id,$plan->user?->manager_id,$plan->created_by])); $key='plan_ending_'.$plan->id;
            foreach($recipients as $uid){if(!$this->existsGenericToday((int)$uid,$key)){$this->notifyGeneric((int)$uid,$key,'План заканчивается через неделю',$plan->title.' · прогресс '.$plan->progress.'% · до '.$plan->period_end->format('d.m.Y'),'plans.page');$created++;}}
        }

        $this->info('Создано уведомлений: '.$created); return self::SUCCESS;
    }

    private function notifyTask(int $userId,Task $task,string $type,string $title,string $body): void
    { CrmNotification::create(['user_id'=>$userId,'task_id'=>$task->id,'type'=>$type,'title'=>$title,'body'=>$body,'url'=>route('tasks.page',['task'=>$task->id],false)]); }

    private function notifyGeneric(int $userId,string $type,string $title,string $body,string $route): void
    { CrmNotification::create(['user_id'=>$userId,'task_id'=>null,'type'=>$type,'title'=>$title,'body'=>$body,'url'=>route($route,[],false)]); }

    private function existsEver(int $taskId,string $type): bool
    { return CrmNotification::where('task_id',$taskId)->where('type',$type)->exists(); }

    private function existsToday(int $taskId,string $type,int $userId): bool
    { return CrmNotification::where('task_id',$taskId)->where('user_id',$userId)->where('type',$type)->whereDate('created_at',today())->exists(); }

    private function existsGenericToday(int $userId,string $type): bool
    { return CrmNotification::where('user_id',$userId)->where('type',$type)->whereDate('created_at',today())->exists(); }

    private function absenceName(string $type):string
    { return ['vacation'=>'отпуск','sick_leave'=>'больничный','business_trip'=>'командировка','training'=>'обучение','other'=>'отсутствие'][$type]??$type; }
}

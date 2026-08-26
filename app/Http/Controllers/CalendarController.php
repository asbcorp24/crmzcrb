<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Plan;
use App\Models\Task;
use App\Models\User;
use App\Services\AccessService;
use Illuminate\Http\Request;

class CalendarController extends Controller
{
    public function page(Request $request)
    {
        $user = $request->user();
        $access = app(AccessService::class);
        $ids = $access->userIds($user, true);
        $users = User::whereIn('id',$ids)->where('is_active',true)->orderBy('last_name')->orderBy('first_name')->get();
        $departments = Department::whereIn('id',$access->departmentIds($user))->where('is_active',true)->orderBy('name')->get();
        return view('calendar.index', compact('users', 'departments'));
    }

    public function events(Request $request)
    {
        $user = $request->user();
        $access = app(AccessService::class);
        $ids = $access->userIds($user, true);
        $start = $request->date('start')?->startOfDay() ?? now()->startOfMonth();
        $end = $request->date('end')?->endOfDay() ?? now()->endOfMonth();

        $taskQuery = Task::with(['assignee.department'])->whereIn('assigned_to',$ids)->whereNotNull('due_at')->whereBetween('due_at', [$start, $end]);
        $planQuery = Plan::with(['user.department'])->whereIn('user_id',$ids)->whereBetween('period_end', [$start->toDateString(), $end->toDateString()]);

        if ($user->isManager() && $request->filled('employee_id')) {
            $employeeId = $request->integer('employee_id');
            abort_unless($ids->contains($employeeId),403);
            $taskQuery->where('assigned_to', $employeeId);
            $planQuery->where('user_id', $employeeId);
        }

        if ($user->isManager() && $request->filled('department_id')) {
            $departmentId = $request->integer('department_id');
            abort_unless($access->departmentIds($user)->contains($departmentId),403);
            $taskQuery->whereHas('assignee', fn($q) => $q->where('department_id', $departmentId));
            $planQuery->whereHas('user', fn($q) => $q->where('department_id', $departmentId));
        }

        if ($request->filled('status')) $taskQuery->where('status', $request->string('status')->toString());

        $kind = $request->input('kind', 'all');
        $events = [];
        $showNames = $user->isManager();

        if ($kind !== 'plans') {
            foreach ($taskQuery->get() as $task) {
                $assignee = $task->assignee?->full_name ?? 'Без исполнителя';
                $department = $task->assignee?->department?->name;
                $events[] = [
                    'id' => 'task-'.$task->id,
                    'title' => $showNames ? $assignee.' — '.$task->title : $task->title,
                    'start' => $task->due_at->toIso8601String(),
                    'url' => route('tasks.page', ['task' => $task->id]),
                    'extendedProps' => ['kind'=>'task','status'=>$task->status,'priority'=>$task->priority,'assignee'=>$assignee,'department'=>$department,'deadline'=>$task->due_at->format('d.m.Y H:i'),'rawTitle'=>$task->title,'overdue'=>$task->is_overdue],
                ];
            }
        }

        if ($kind !== 'tasks') {
            foreach ($planQuery->get() as $plan) {
                $assignee = $plan->user?->full_name ?? 'Без исполнителя';
                $department = $plan->user?->department?->name;
                $events[] = [
                    'id' => 'plan-'.$plan->id,
                    'title' => $showNames ? $assignee.' — План: '.$plan->title : 'План: '.$plan->title,
                    'start' => $plan->period_end->toDateString(),
                    'url' => route('plans.page', ['user_id' => $plan->user_id]),
                    'allDay' => true,
                    'extendedProps' => ['kind'=>'plan','status'=>$plan->status,'assignee'=>$assignee,'department'=>$department,'deadline'=>$plan->period_end->format('d.m.Y'),'rawTitle'=>$plan->title,'overdue'=>$plan->period_end->isPast() && !in_array($plan->status,['completed','cancelled'],true)],
                ];
            }
        }

        return response()->json($events);
    }
}

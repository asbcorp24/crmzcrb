<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\Task;
use Illuminate\Http\Request;

class CalendarController extends Controller
{
    public function page()
    {
        return view('calendar.index');
    }

    public function events(Request $request)
    {
        $user = $request->user();
        $start = $request->date('start')?->startOfDay() ?? now()->startOfMonth();
        $end = $request->date('end')?->endOfDay() ?? now()->endOfMonth();

        $taskQuery = Task::with('assignee')->whereNotNull('due_at')->whereBetween('due_at', [$start, $end]);
        $planQuery = Plan::with('user')->where(function ($q) use ($start, $end) {
            $q->whereBetween('period_start', [$start->toDateString(), $end->toDateString()])
              ->orWhereBetween('period_end', [$start->toDateString(), $end->toDateString()])
              ->orWhere(function ($w) use ($start, $end) {
                  $w->where('period_start', '<=', $start->toDateString())
                    ->where('period_end', '>=', $end->toDateString());
              });
        });

        if (!$user->isManager()) {
            $taskQuery->where('assigned_to', $user->id);
            $planQuery->where('user_id', $user->id);
        } elseif (!$user->isAdmin()) {
            $ids = $user->subordinates()->pluck('id')->push($user->id);
            $taskQuery->whereIn('assigned_to', $ids);
            $planQuery->whereIn('user_id', $ids);
        }

        $events = [];
        foreach ($taskQuery->get() as $task) {
            $events[] = [
                'id' => 'task-'.$task->id,
                'title' => 'Задача: '.$task->title,
                'start' => $task->due_at->toIso8601String(),
                'url' => route('tasks.page', ['task' => $task->id]),
                'extendedProps' => [
                    'kind' => 'task',
                    'status' => $task->status,
                    'priority' => $task->priority,
                    'assignee' => $task->assignee?->full_name,
                ],
            ];
        }

        foreach ($planQuery->get() as $plan) {
            $events[] = [
                'id' => 'plan-'.$plan->id,
                'title' => 'План: '.$plan->title,
                'start' => $plan->period_start->toDateString(),
                'end' => $plan->period_end->copy()->addDay()->toDateString(),
                'url' => route('plans.page'),
                'allDay' => true,
                'extendedProps' => [
                    'kind' => 'plan',
                    'status' => $plan->status,
                    'assignee' => $plan->user?->full_name,
                ],
            ];
        }

        return response()->json($events);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\User;
use App\Services\AccessService;
use Illuminate\Http\Request;

class ControlController extends Controller
{
    public function page(Request $request)
    {
        abort_unless($request->user()->isManager(), 403);
        return view('control.index');
    }

    public function data(Request $request)
    {
        abort_unless($request->user()->isManager(), 403);

        $access = app(AccessService::class);
        $ids = $access->userIds($request->user(), false);

        $employees = User::query()
            ->with('department')
            ->where('is_active', true)
            ->whereIn('id', $ids)
            ->orderBy('last_name')->orderBy('first_name')
            ->get();

        $stats = Task::query()
            ->select('assigned_to')
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed")
            ->selectRaw("SUM(CASE WHEN status = 'review' THEN 1 ELSE 0 END) as review_count")
            ->selectRaw("SUM(CASE WHEN status NOT IN ('completed','cancelled') AND due_at IS NOT NULL AND due_at < ? THEN 1 ELSE 0 END) as overdue_count", [now()])
            ->selectRaw("ROUND(AVG(CASE WHEN status = 'completed' THEN 100 ELSE progress END)) as avg_progress")
            ->whereIn('assigned_to', $ids)
            ->groupBy('assigned_to')
            ->get()->keyBy('assigned_to');

        $rows = $employees->map(function ($employee) use ($stats) {
            $s = $stats->get($employee->id);
            return [
                'id' => $employee->id,
                'full_name' => $employee->full_name,
                'position' => $employee->position,
                'department' => $employee->department?->name,
                'total' => (int) ($s->total ?? 0),
                'completed' => (int) ($s->completed ?? 0),
                'review' => (int) ($s->review_count ?? 0),
                'overdue' => (int) ($s->overdue_count ?? 0),
                'progress' => (int) ($s->avg_progress ?? 0),
            ];
        });

        $base = Task::with(['assignee.department','creator','overdueReasons.user'])
            ->whereIn('assigned_to',$ids)
            ->whereNotIn('status',['completed','cancelled']);

        $critical = (clone $base)->where('priority','critical')->orderByRaw('due_at IS NULL, due_at ASC')->limit(50)->get();
        $overdue = (clone $base)->whereNotNull('due_at')->where('due_at','<',now())->orderBy('due_at')->limit(100)->get();
        $review = Task::with(['assignee.department','creator'])->whereIn('assigned_to',$ids)->where('status','review')->oldest('updated_at')->limit(100)->get();
        $today = (clone $base)->whereBetween('due_at',[today()->startOfDay(),today()->endOfDay()])->orderBy('due_at')->limit(100)->get();
        $tomorrow = (clone $base)->whereBetween('due_at',[today()->copy()->addDay()->startOfDay(),today()->copy()->addDay()->endOfDay()])->orderBy('due_at')->limit(100)->get();
        $stale = (clone $base)->where('updated_at','<',now()->subDays(3))->orderBy('updated_at')->limit(100)->get();

        $serialize = fn($tasks) => $tasks->map(function(Task $task){
            $reason=$task->overdueReasons->first();
            return [
                'id'=>$task->id,'title'=>$task->title,'priority'=>$task->priority,'status'=>$task->status,'progress'=>(int)$task->progress,
                'due_at'=>$task->due_at?->toIso8601String(),'updated_at'=>$task->updated_at?->toIso8601String(),
                'assignee'=>$task->assignee?->full_name,'department'=>$task->assignee?->department?->name,
                'overdue'=>$task->is_overdue,
                'overdue_reason'=>$reason ? [
                    'code'=>$reason->reason_code,'comment'=>$reason->comment,'created_at'=>$reason->created_at?->toIso8601String(),
                    'user'=>$reason->user?->full_name,
                ] : null,
            ];
        })->values();

        return response()->json([
            'summary' => [
                'employees' => $rows->count(),
                'tasks' => $rows->sum('total'),
                'completed' => $rows->sum('completed'),
                'review' => $review->count(),
                'overdue' => $overdue->count(),
                'critical' => $critical->count(),
                'today' => $today->count(),
                'tomorrow' => $tomorrow->count(),
                'stale' => $stale->count(),
            ],
            'employees' => $rows,
            'buckets' => [
                'critical'=>$serialize($critical),
                'overdue'=>$serialize($overdue),
                'review'=>$serialize($review),
                'today'=>$serialize($today),
                'tomorrow'=>$serialize($tomorrow),
                'stale'=>$serialize($stale),
            ],
        ]);
    }
}

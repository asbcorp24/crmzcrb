<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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

        $user = $request->user();
        $employees = User::query()
            ->with('department')
            ->where('is_active', true)
            ->when(!$user->isAdmin(), fn ($q) => $q->where('manager_id', $user->id))
            ->orderBy('last_name')->orderBy('first_name')
            ->get();

        $ids = $employees->pluck('id');
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

        return response()->json([
            'summary' => [
                'employees' => $rows->count(),
                'tasks' => $rows->sum('total'),
                'completed' => $rows->sum('completed'),
                'review' => $rows->sum('review'),
                'overdue' => $rows->sum('overdue'),
            ],
            'employees' => $rows,
        ]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\EmployeeAbsence;
use App\Models\StaffingPosition;
use App\Models\Task;
use App\Models\User;
use App\Services\AccessService;
use Illuminate\Http\Request;

class DepartmentDashboardController extends Controller
{
    public function show(Request $request, Department $department)
    {
        abort_unless($request->user()->isManager(), 403);
        $access = app(AccessService::class);
        abort_unless($access->canViewDepartment($request->user(), $department), 403);

        $allowedUserIds = $access->userIds($request->user(), true);
        $users = User::with(['manager','activeAssignments.staffingPosition.position'])
            ->where('department_id',$department->id)
            ->whereIn('id',$allowedUserIds)
            ->where('is_active',true)
            ->orderBy('last_name')->orderBy('first_name')->get();
        $ids = $users->pluck('id');

        $tasks = Task::with(['assignee','creator'])
            ->whereIn('assigned_to',$ids)
            ->whereNotIn('status',['completed','cancelled'])
            ->orderByRaw('due_at IS NULL, due_at ASC')->get();

        $completedMonth = Task::whereIn('assigned_to',$ids)->where('status','completed')
            ->whereBetween('completed_at',[now()->copy()->startOfMonth(),now()->copy()->endOfMonth()])->count();

        $absences = EmployeeAbsence::with('user')->whereIn('user_id',$ids)
            ->where('date_from','<=',today())->where('date_to','>=',today())->get();

        $staffing = StaffingPosition::with(['position','activeAssignments.user'])
            ->where('department_id',$department->id)->where('is_active',true)->get();
        $plannedRate = $staffing->sum(fn($r)=>(float)$r->planned_rate);
        $occupiedRate = $staffing->sum(fn($r)=>(float)$r->activeAssignments->sum(fn($a)=>(float)$a->rate));

        $stats = [
            'employees'=>$users->count(),
            'open_tasks'=>$tasks->count(),
            'overdue'=>$tasks->filter(fn($t)=>$t->is_overdue)->count(),
            'review'=>$tasks->where('status','review')->count(),
            'critical'=>$tasks->where('priority','critical')->count(),
            'completed_month'=>$completedMonth,
            'absent_today'=>$absences->count(),
            'planned_rate'=>round($plannedRate,2),
            'occupied_rate'=>round($occupiedRate,2),
            'vacant_rate'=>round(max(0,$plannedRate-$occupiedRate),2),
        ];

        $children = Department::where('parent_id',$department->id)->where('is_active',true)->orderBy('sort_order')->orderBy('name')->get();

        return view('departments.dashboard360', compact('department','users','tasks','absences','staffing','stats','children'));
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\EmployeeAssignment;
use App\Models\Plan;
use App\Models\Task;
use App\Models\TaskEvent;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class EmployeeController extends Controller
{
    public function page()
    {
        return view('employees.index', [
            'departments' => Department::where('is_active', true)->orderBy('name')->get(),
            'managers' => User::whereIn('role', ['admin','manager'])->where('is_active', true)->orderBy('last_name')->get(),
        ]);
    }

    public function profile(Request $request, User $employee)
    {
        $viewer = $request->user();
        abort_unless($viewer->id === $employee->id || $viewer->isManager(), 403);

        $employee->load(['department','manager','subordinates']);

        $taskBase = Task::where('assigned_to', $employee->id);
        $stats = [
            'all' => (clone $taskBase)->count(),
            'open' => (clone $taskBase)->whereNotIn('status', ['completed','cancelled'])->count(),
            'overdue' => (clone $taskBase)->whereNotIn('status', ['completed','cancelled'])->whereNotNull('due_at')->where('due_at','<',now())->count(),
            'review' => (clone $taskBase)->where('status','review')->count(),
            'completed' => (clone $taskBase)->where('status','completed')->count(),
            'completed_month' => (clone $taskBase)->where('status','completed')->whereBetween('completed_at', [now()->copy()->startOfMonth(), now()->copy()->endOfMonth()])->count(),
        ];

        $progressRows = (clone $taskBase)->whereNotIn('status',['cancelled'])->get(['status','progress']);
        $stats['avg_progress'] = $progressRows->isEmpty() ? 0 : (int) round($progressRows->avg(fn($task) => $task->status === 'completed' ? 100 : (int)$task->progress));
        $stats['completion_rate'] = $stats['all'] > 0 ? (int) round(($stats['completed'] / $stats['all']) * 100) : 0;

        $activeTasks = Task::with(['creator','plan'])
            ->where('assigned_to',$employee->id)
            ->whereNotIn('status',['completed','cancelled'])
            ->orderByRaw('due_at IS NULL, due_at ASC')
            ->limit(12)->get();

        $recentCompleted = Task::with('creator')
            ->where('assigned_to',$employee->id)
            ->where('status','completed')
            ->latest('completed_at')->limit(10)->get();

        $plans = Plan::with('tasks')
            ->where('user_id',$employee->id)
            ->whereNotIn('status',['completed','cancelled'])
            ->orderByDesc('period_start')->limit(10)->get();

        $events = TaskEvent::with(['task','user'])
            ->whereHas('task', fn($q) => $q->where('assigned_to',$employee->id))
            ->latest()->limit(30)->get();

        $assignments = EmployeeAssignment::with(['staffingPosition.department','staffingPosition.position'])
            ->where('user_id',$employee->id)
            ->orderByRaw('ended_at IS NULL DESC')
            ->orderByDesc('started_at')
            ->get();

        return view('employees.profile', compact('employee','stats','activeTasks','recentCompleted','plans','events','assignments'));
    }

    public function index(Request $request)
    {
        $q = User::with(['department','manager']);
        if ($request->filled('department_id')) $q->where('department_id', $request->integer('department_id'));
        if ($request->filled('role')) $q->where('role', $request->role);
        if ($request->filled('q')) {
            $s = trim($request->q);
            $q->where(function($w) use ($s) {
                $w->where('last_name','like',"%{$s}%")->orWhere('first_name','like',"%{$s}%")
                  ->orWhere('middle_name','like',"%{$s}%")->orWhere('position','like',"%{$s}%")
                  ->orWhere('email','like',"%{$s}%");
            });
        }
        return response()->json($q->orderBy('last_name')->orderBy('first_name')->paginate(30));
    }

    public function store(Request $request)
    {
        abort_unless($request->user()->isManager(), 403);
        $data = $this->validated($request);
        $data['password'] = Hash::make($request->input('password', 'ChangeMe123!'));
        $user = User::create($data);
        return response()->json(['ok'=>true,'user'=>$user->load(['department','manager'])], 201);
    }

    public function update(Request $request, User $employee)
    {
        abort_unless($request->user()->isManager(), 403);
        $data = $this->validated($request, $employee->id);
        if ($request->filled('password')) $data['password'] = Hash::make($request->password);
        $employee->update($data);
        return response()->json(['ok'=>true,'user'=>$employee->fresh()->load(['department','manager'])]);
    }

    private function validated(Request $request, ?int $id = null): array
    {
        return $request->validate([
            'department_id'=>'nullable|exists:departments,id','manager_id'=>'nullable|exists:users,id',
            'last_name'=>'required|string|max:100','first_name'=>'required|string|max:100','middle_name'=>'nullable|string|max:100',
            'position'=>'required|string|max:190','email'=>['required','email','max:190',Rule::unique('users','email')->ignore($id)],
            'phone'=>'nullable|string|max:50','role'=>['required',Rule::in(['admin','manager','employee'])],
            'is_active'=>'required|boolean','employment_date'=>'nullable|date','password'=>'nullable|string|min:8|max:100'
        ]);
    }
}

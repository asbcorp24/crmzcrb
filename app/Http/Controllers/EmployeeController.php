<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\EmployeeAssignment;
use App\Models\Plan;
use App\Models\Task;
use App\Models\TaskEvent;
use App\Models\User;
use App\Services\AccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class EmployeeController extends Controller
{
    public function page(Request $request)
    {
        $access=app(AccessService::class); $ids=$access->userIds($request->user(),true); $departmentIds=$access->departmentIds($request->user());
        return view('employees.index',['departments'=>Department::whereIn('id',$departmentIds)->where('is_active',true)->orderBy('name')->get(),'managers'=>User::whereIn('id',$ids)->whereIn('role',['admin','manager'])->where('is_active',true)->whereNull('archived_at')->orderBy('last_name')->get()]);
    }

    public function profile(Request $request, User $employee)
    {
        $access=app(AccessService::class); abort_unless($access->canViewUser($request->user(),$employee),403); $employee->load(['department','manager','subordinates']);
        $taskBase=Task::whereNull('archived_at')->where('assigned_to',$employee->id);
        $stats=['all'=>(clone $taskBase)->count(),'open'=>(clone $taskBase)->whereNotIn('status',['completed','cancelled'])->count(),'overdue'=>(clone $taskBase)->whereNotIn('status',['completed','cancelled'])->whereNotNull('due_at')->where('due_at','<',now())->count(),'review'=>(clone $taskBase)->where('status','review')->count(),'completed'=>(clone $taskBase)->where('status','completed')->count(),'completed_month'=>(clone $taskBase)->where('status','completed')->whereBetween('completed_at',[now()->copy()->startOfMonth(),now()->copy()->endOfMonth()])->count()];
        $progressRows=(clone $taskBase)->whereNotIn('status',['cancelled'])->get(['status','progress']);
        $stats['avg_progress']=$progressRows->isEmpty()?0:(int)round($progressRows->avg(fn($task)=>$task->status==='completed'?100:(int)$task->progress));
        $stats['completion_rate']=$stats['all']>0?(int)round(($stats['completed']/$stats['all'])*100):0;
        $activeTasks=Task::with(['creator','plan'])->whereNull('archived_at')->where('assigned_to',$employee->id)->whereNotIn('status',['completed','cancelled'])->orderByRaw('due_at IS NULL, due_at ASC')->limit(12)->get();
        $recentCompleted=Task::with('creator')->whereNull('archived_at')->where('assigned_to',$employee->id)->where('status','completed')->latest('completed_at')->limit(10)->get();
        $plans=Plan::with('tasks')->whereNull('archived_at')->where('user_id',$employee->id)->whereNotIn('status',['completed','cancelled'])->orderByDesc('period_start')->limit(10)->get();
        $events=TaskEvent::with(['task','user'])->whereHas('task',fn($q)=>$q->whereNull('archived_at')->where('assigned_to',$employee->id))->latest()->limit(30)->get();
        $assignments=EmployeeAssignment::with(['staffingPosition.department','staffingPosition.position'])->where('user_id',$employee->id)->orderByRaw('ended_at IS NULL DESC')->orderByDesc('started_at')->get();
        return view('employees.profile',compact('employee','stats','activeTasks','recentCompleted','plans','events','assignments'));
    }

    public function index(Request $request)
    {
        $access=app(AccessService::class); $ids=$access->userIds($request->user(),true); $q=User::with(['department','manager'])->whereIn('id',$ids)->whereNull('archived_at');
        if($request->filled('department_id'))$q->where('department_id',$request->integer('department_id')); if($request->filled('role'))$q->where('role',$request->role);
        if($request->filled('q')){$s=trim($request->q);$q->where(function($w)use($s){$w->where('last_name','like',"%{$s}%")->orWhere('first_name','like',"%{$s}%")->orWhere('middle_name','like',"%{$s}%")->orWhere('position','like',"%{$s}%")->orWhere('email','like',"%{$s}%");});}
        return response()->json($q->orderBy('last_name')->orderBy('first_name')->paginate(30));
    }

    public function store(Request $request)
    {
        abort_unless($request->user()->isManager(),403); $data=$this->validated($request);
        if(!$request->user()->isAdmin()){abort_unless((int)($data['manager_id']??0)===(int)$request->user()->id,403,'Руководитель может создавать только непосредственных подчинённых');abort_if(($data['role']??'employee')==='admin',403);}
        $data['organization_id']=$request->user()->organization_id; $data['password']=Hash::make($request->input('password','ChangeMe123!')); $user=User::create($data);
        return response()->json(['ok'=>true,'user'=>$user->load(['department','manager'])],201);
    }

    public function update(Request $request, User $employee)
    {
        $access=app(AccessService::class); abort_unless($request->user()->isManager()&&($request->user()->isAdmin()||$access->canManageUser($request->user(),$employee)),403); $data=$this->validated($request,$employee->id);
        if(!$request->user()->isAdmin()){abort_if(($data['role']??$employee->role)==='admin',403);if(array_key_exists('manager_id',$data)&&$data['manager_id']){abort_unless($access->userIds($request->user(),true)->contains((int)$data['manager_id']),403,'Нельзя назначить руководителя вне доступной иерархии');}}
        unset($data['organization_id'],$data['is_superadmin']); if($request->filled('password'))$data['password']=Hash::make($request->password); $employee->update($data);
        return response()->json(['ok'=>true,'user'=>$employee->fresh()->load(['department','manager'])]);
    }

    private function validated(Request $request, ?int $id=null): array
    {
        $orgId=(int)$request->user()->organization_id;
        return $request->validate([
            'department_id'=>['nullable',Rule::exists('departments','id')->where(fn($q)=>$q->where('organization_id',$orgId))],
            'manager_id'=>['nullable',Rule::exists('users','id')->where(fn($q)=>$q->where('organization_id',$orgId)->where('is_superadmin',false))],
            'last_name'=>'required|string|max:100','first_name'=>'required|string|max:100','middle_name'=>'nullable|string|max:100','position'=>'required|string|max:190',
            'email'=>['required','email','max:190',Rule::unique('users','email')->where(fn($q)=>$q->where('organization_id',$orgId))->ignore($id)],
            'phone'=>'nullable|string|max:50','role'=>['required',Rule::in(['admin','manager','employee'])],'is_active'=>'required|boolean','employment_date'=>'nullable|date','password'=>'nullable|string|min:8|max:100'
        ]);
    }
}

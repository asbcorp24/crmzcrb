<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Plan;
use App\Models\Task;
use App\Models\User;
use App\Services\AccessService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class OrganizationAnalyticsController extends Controller
{
    private function context(Request $request, AccessService $access): array
    {
        $viewer=$request->user();
        abort_unless($viewer->isManager(),403);
        $userIds=$access->userIds($viewer,true)->map(fn($v)=>(int)$v);
        $departmentIds=$access->departmentIds($viewer)->map(fn($v)=>(int)$v);
        return [$viewer,$userIds,$departmentIds];
    }

    public function overview(Request $request, AccessService $access)
    {
        [$viewer,$userIds,$departmentIds]=$this->context($request,$access);
        $tasks=Task::whereIn('assigned_to',$userIds)->whereNull('archived_at')->get();
        $plans=Plan::whereIn('user_id',$userIds)->whereNull('archived_at')->get();
        $employees=User::whereIn('id',$userIds)->where('is_active',true)->whereNull('archived_at')->get();
        $departments=Department::whereIn('id',$departmentIds)->where('is_active',true)->get();

        $open=$tasks->whereNotIn('status',['completed','cancelled']);
        $done=$tasks->where('status','completed');
        $overdue=$open->filter(fn($t)=>$t->due_at&&$t->due_at->isPast());
        $activePlans=$plans->whereNotIn('status',['completed','cancelled']);
        $overduePlans=$activePlans->filter(fn($p)=>$p->period_end&&$p->period_end->isPast());

        $months=$this->months(6);
        $taskTrend=$this->taskTrend($tasks,$months);
        $departmentRows=$departments->map(function($d) use($employees,$tasks){
            $ids=$employees->where('department_id',$d->id)->pluck('id');
            $dt=$tasks->whereIn('assigned_to',$ids);
            $open=$dt->whereNotIn('status',['completed','cancelled']);
            return ['name'=>$d->name,'employees'=>$ids->count(),'tasks'=>$dt->count(),'open'=>$open->count(),'overdue'=>$open->filter(fn($t)=>$t->due_at&&$t->due_at->isPast())->count(),'done'=>$dt->where('status','completed')->count()];
        })->sortByDesc('tasks')->values();

        $kpi=[
            'employees'=>$employees->count(),'departments'=>$departments->count(),'tasks'=>$tasks->count(),'open'=>$open->count(),
            'overdue'=>$overdue->count(),'done'=>$done->count(),'plans'=>$plans->count(),'active_plans'=>$activePlans->count(),
            'overdue_plans'=>$overduePlans->count(),'avg_plan_progress'=>$activePlans->count()?round($activePlans->avg('progress'),1):0,
        ];
        return view('analytics.overview',compact('kpi','taskTrend','departmentRows'));
    }

    public function tasks(Request $request, AccessService $access)
    {
        [$viewer,$userIds,$departmentIds]=$this->context($request,$access);
        $tasks=Task::with(['assignee.department','creator'])->whereIn('assigned_to',$userIds)->whereNull('archived_at')->get();
        $employees=User::with('department')->whereIn('id',$userIds)->where('is_active',true)->get()->keyBy('id');
        $months=$this->months(6); $trend=$this->taskTrend($tasks,$months);
        $statuses=$tasks->groupBy('status')->map->count();
        $priorities=$tasks->groupBy('priority')->map->count();
        $open=$tasks->whereNotIn('status',['completed','cancelled']);
        $overdue=$open->filter(fn($t)=>$t->due_at&&$t->due_at->isPast());
        $done=$tasks->where('status','completed');
        $employeeRows=$userIds->map(function($id) use($employees,$tasks){
            $u=$employees->get($id); if(!$u)return null;
            $rows=$tasks->where('assigned_to',$id);$open=$rows->whereNotIn('status',['completed','cancelled']);$done=$rows->where('status','completed');
            return ['user'=>$u,'all'=>$rows->count(),'open'=>$open->count(),'overdue'=>$open->filter(fn($t)=>$t->due_at&&$t->due_at->isPast())->count(),'done'=>$done->count(),'avg_progress'=>$rows->count()?round($rows->avg(fn($t)=>$t->status==='completed'?100:(int)$t->progress),1):0];
        })->filter()->sortByDesc('overdue')->values();
        $departmentRows=collect($departmentIds)->map(function($depId) use($employees,$tasks){$ids=$employees->where('department_id',$depId)->pluck('id');$rows=$tasks->whereIn('assigned_to',$ids);$open=$rows->whereNotIn('status',['completed','cancelled']);return ['name'=>$employees->firstWhere('department_id',$depId)?->department?->name??('Подразделение '.$depId),'all'=>$rows->count(),'open'=>$open->count(),'overdue'=>$open->filter(fn($t)=>$t->due_at&&$t->due_at->isPast())->count(),'done'=>$rows->where('status','completed')->count()];})->sortByDesc('all')->values();
        $kpi=['all'=>$tasks->count(),'open'=>$open->count(),'overdue'=>$overdue->count(),'review'=>$tasks->where('status','review')->count(),'done'=>$done->count(),'completion'=>$tasks->count()?round($done->count()*100/$tasks->count(),1):0];
        return view('analytics.tasks',compact('kpi','trend','statuses','priorities','employeeRows','departmentRows'));
    }

    public function plans(Request $request, AccessService $access)
    {
        [$viewer,$userIds]=$this->context($request,$access);
        $plans=Plan::with('user.department')->whereIn('user_id',$userIds)->whereNull('archived_at')->get();
        $active=$plans->whereNotIn('status',['completed','cancelled']);$completed=$plans->where('status','completed');
        $overdue=$active->filter(fn($p)=>$p->period_end&&$p->period_end->isPast());
        $statuses=$plans->groupBy('status')->map->count();
        $progressBuckets=['0–24'=>0,'25–49'=>0,'50–74'=>0,'75–99'=>0,'100'=>0];
        foreach($plans as $p){$v=(int)$p->progress;if($v>=100)$progressBuckets['100']++;elseif($v>=75)$progressBuckets['75–99']++;elseif($v>=50)$progressBuckets['50–74']++;elseif($v>=25)$progressBuckets['25–49']++;else$progressBuckets['0–24']++;}
        $rows=$plans->sortBy(fn($p)=>[$p->status==='completed'?1:0,$p->period_end?->timestamp??PHP_INT_MAX])->values();
        $kpi=['all'=>$plans->count(),'active'=>$active->count(),'completed'=>$completed->count(),'overdue'=>$overdue->count(),'avg_progress'=>$active->count()?round($active->avg('progress'),1):0];
        return view('analytics.plans',compact('kpi','statuses','progressBuckets','rows'));
    }

    public function employees(Request $request, AccessService $access)
    {
        [$viewer,$userIds,$departmentIds]=$this->context($request,$access);
        $employees=User::with('department')->whereIn('id',$userIds)->whereNull('archived_at')->get();
        $active=$employees->where('is_active',true);$inactive=$employees->where('is_active',false);
        $roles=$employees->groupBy('role')->map->count();
        $departmentRows=Department::whereIn('id',$departmentIds)->get()->map(function($d)use($employees){$rows=$employees->where('department_id',$d->id);return['name'=>$d->name,'all'=>$rows->count(),'active'=>$rows->where('is_active',true)->count(),'managers'=>$rows->whereIn('role',['admin','manager'])->count()];})->sortByDesc('all')->values();
        $months=$this->months(12);$hires=[];foreach($months as $m){$hires[]=$employees->filter(fn($u)=>$u->employment_date&&$u->employment_date->format('Y-m')===$m['key'])->count();}
        $kpi=['all'=>$employees->count(),'active'=>$active->count(),'inactive'=>$inactive->count(),'departments'=>$departmentRows->count(),'managers'=>$employees->whereIn('role',['admin','manager'])->count()];
        return view('analytics.employees',compact('kpi','roles','departmentRows','months','hires'));
    }

    public function departments(Request $request, AccessService $access)
    {
        [$viewer,$userIds,$departmentIds]=$this->context($request,$access);
        $employees=User::whereIn('id',$userIds)->whereNull('archived_at')->get();
        $tasks=Task::whereIn('assigned_to',$userIds)->whereNull('archived_at')->get();
        $departments=Department::whereIn('id',$departmentIds)->where('is_active',true)->get();
        $rows=$departments->map(function($d)use($employees,$tasks){$ids=$employees->where('department_id',$d->id)->pluck('id');$t=$tasks->whereIn('assigned_to',$ids);$open=$t->whereNotIn('status',['completed','cancelled']);$done=$t->where('status','completed');return['name'=>$d->name,'employees'=>$ids->count(),'all'=>$t->count(),'open'=>$open->count(),'overdue'=>$open->filter(fn($x)=>$x->due_at&&$x->due_at->isPast())->count(),'done'=>$done->count(),'completion'=>$t->count()?round($done->count()*100/$t->count(),1):0];})->sortByDesc('all')->values();
        return view('analytics.departments',compact('rows'));
    }

    private function months(int $count): array
    {
        $out=[];for($i=$count-1;$i>=0;$i--){$d=now()->copy()->startOfMonth()->subMonths($i);$out[]=['key'=>$d->format('Y-m'),'label'=>$d->translatedFormat('M Y')];}return$out;
    }

    private function taskTrend($tasks,array $months): array
    {
        $created=[];$completed=[];
        foreach($months as $m){$created[]=$tasks->filter(fn($t)=>$t->created_at&&$t->created_at->format('Y-m')===$m['key'])->count();$completed[]=$tasks->filter(fn($t)=>$t->completed_at&&$t->completed_at->format('Y-m')===$m['key'])->count();}
        return ['labels'=>array_column($months,'label'),'created'=>$created,'completed'=>$completed];
    }
}

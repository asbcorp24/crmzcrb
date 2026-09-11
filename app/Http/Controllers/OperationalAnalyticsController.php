<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\EmployeeAbsence;
use App\Models\Meeting;
use App\Models\StaffingPosition;
use App\Models\User;
use App\Services\AccessService;
use Illuminate\Http\Request;

class OperationalAnalyticsController extends Controller
{
    private function scope(Request $request, AccessService $access): array
    {
        $viewer=$request->user();
        abort_unless($viewer->isManager(),403);
        return [$access->userIds($viewer,true)->map(fn($v)=>(int)$v),$access->departmentIds($viewer)->map(fn($v)=>(int)$v)];
    }

    public function meetings(Request $request, AccessService $access)
    {
        [$userIds]=$this->scope($request,$access);
        $meetings=Meeting::with(['participants','items'])->whereNull('archived_at')->get()->filter(function($m) use($userIds){
            return $userIds->contains((int)$m->chairman_id) || $userIds->contains((int)$m->secretary_id) || $m->participants->pluck('id')->intersect($userIds)->isNotEmpty();
        })->values();
        $statuses=$meetings->groupBy('status')->map->count();
        $months=[];$counts=[];
        for($i=5;$i>=0;$i--){$d=now()->copy()->startOfMonth()->subMonths($i);$k=$d->format('Y-m');$months[]=$d->translatedFormat('M Y');$counts[]=$meetings->filter(fn($m)=>$m->held_at&&$m->held_at->format('Y-m')===$k)->count();}
        $allItems=$meetings->sum(fn($m)=>$m->items->count());
        $withTasks=$meetings->sum(fn($m)=>$m->items->whereNotNull('task_id')->count());
        $kpi=['all'=>$meetings->count(),'upcoming'=>$meetings->filter(fn($m)=>$m->held_at&&$m->held_at->isFuture())->count(),'closed'=>$meetings->where('status','closed')->count(),'items'=>$allItems,'task_items'=>$withTasks];
        return view('analytics.meetings',compact('kpi','statuses','months','counts','meetings'));
    }

    public function availability(Request $request, AccessService $access)
    {
        [$userIds,$departmentIds]=$this->scope($request,$access);
        $absences=EmployeeAbsence::with('user.department')->whereIn('user_id',$userIds)->get();
        $types=$absences->groupBy('type')->map->count();
        $today=$absences->filter(fn($a)=>$a->date_from&&$a->date_to&&$a->date_from->lte(today())&&$a->date_to->gte(today()));
        $upcoming=$absences->filter(fn($a)=>$a->date_from&&$a->date_from->gt(today())&&$a->date_from->lte(today()->copy()->addDays(30)));
        $departmentRows=Department::whereIn('id',$departmentIds)->get()->map(function($d) use($absences){$rows=$absences->filter(fn($a)=>(int)$a->user?->department_id===(int)$d->id);$days=$rows->sum(fn($a)=>$a->date_from&&$a->date_to?$a->date_from->diffInDays($a->date_to)+1:0);return['name'=>$d->name,'cases'=>$rows->count(),'days'=>$days];})->sortByDesc('days')->values();
        $kpi=['all'=>$absences->count(),'today'=>$today->count(),'upcoming'=>$upcoming->count(),'days'=>$absences->sum(fn($a)=>$a->date_from&&$a->date_to?$a->date_from->diffInDays($a->date_to)+1:0)];
        return view('analytics.availability',compact('kpi','types','departmentRows','today','upcoming'));
    }

    public function staffing(Request $request, AccessService $access)
    {
        [$userIds,$departmentIds]=$this->scope($request,$access);
        $rows=StaffingPosition::with(['department','position','activeAssignments'])->whereIn('department_id',$departmentIds)->where('is_active',true)->get();
        $planned=$rows->sum(fn($r)=>(float)$r->planned_rate);$occupied=$rows->sum(fn($r)=>(float)$r->occupied_rate);$vacant=max(0,round($planned-$occupied,2));
        $departmentRows=Department::whereIn('id',$departmentIds)->get()->map(function($d) use($rows){$r=$rows->where('department_id',$d->id);$p=$r->sum(fn($x)=>(float)$x->planned_rate);$o=$r->sum(fn($x)=>(float)$x->occupied_rate);return['name'=>$d->name,'positions'=>$r->count(),'planned'=>round($p,2),'occupied'=>round($o,2),'vacant'=>max(0,round($p-$o,2)),'filled'=>$p?round($o*100/$p,1):0];})->sortByDesc('planned')->values();
        $kpi=['positions'=>$rows->count(),'planned'=>round($planned,2),'occupied'=>round($occupied,2),'vacant'=>$vacant,'filled'=>$planned?round($occupied*100/$planned,1):0];
        return view('analytics.staffing',compact('kpi','departmentRows','rows'));
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\EmployeeAbsence;
use App\Models\Plan;
use App\Models\Task;
use App\Models\User;
use App\Services\AccessService;
use Illuminate\Http\Request;

class Manager360Controller extends Controller
{
    public function page(Request $request)
    {
        abort_unless($request->user()->isManager(),403);
        $ids=app(AccessService::class)->userIds($request->user(),true);
        $users=User::with('department')->whereIn('id',$ids)->where('is_active',true)->get();
        $tasks=Task::whereNull('archived_at')->whereIn('assigned_to',$ids)->whereNotIn('status',['completed','cancelled'])->get();
        $absentIds=EmployeeAbsence::whereIn('user_id',$ids)->whereDate('date_from','<=',today())->whereDate('date_to','>=',today())->pluck('user_id');
        $plans=Plan::with('user')->whereNull('archived_at')->whereIn('user_id',$ids)->whereNotIn('status',['completed','cancelled'])->get();
        $loads=$users->map(function($u)use($tasks,$absentIds){$set=$tasks->where('assigned_to',$u->id);$active=$set->count();$critical=$set->where('priority','critical')->count();$overdue=$set->filter(fn($t)=>$t->is_overdue)->count();$soon=$set->filter(fn($t)=>$t->due_at&&$t->due_at->between(now(),now()->addDays(3)))->count();$score=$active+$critical*2+$overdue*3+$soon;return ['user'=>$u,'active'=>$active,'critical'=>$critical,'overdue'=>$overdue,'soon'=>$soon,'score'=>$score,'absent'=>$absentIds->contains($u->id)];})->sortByDesc('score')->values();
        $lagging=$plans->filter(fn($p)=>$p->period_end&&$p->period_end->lte(now()->addDays(7))&&$p->progress<80)->sortBy('period_end')->values();
        return view('manager360.index',['users'=>$users,'tasks'=>$tasks,'loads'=>$loads,'lagging'=>$lagging,'absentCount'=>$absentIds->count()]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\Task;
use App\Models\User;
use App\Services\AccessService;
use Illuminate\Http\Request;

class DashboardAnalyticsController extends Controller
{
    public function data(Request $request, AccessService $access)
    {
        $viewer=$request->user();
        $userIds=$viewer->isManager() ? $access->userIds($viewer,true) : collect([$viewer->id]);
        $tasks=Task::whereIn('assigned_to',$userIds)->whereNull('archived_at')->get();
        $plans=Plan::whereIn('user_id',$userIds)->whereNull('archived_at')->get();

        $open=$tasks->whereNotIn('status',['completed','cancelled']);
        $status=[
            'Открыто'=>$open->count(),
            'Выполнено'=>$tasks->where('status','completed')->count(),
            'На проверке'=>$tasks->where('status','review')->count(),
            'Просрочено'=>$open->filter(fn($t)=>$t->due_at&&$t->due_at->isPast())->count(),
        ];

        $months=[];$created=[];$completed=[];
        for($i=5;$i>=0;$i--){
            $m=now()->copy()->startOfMonth()->subMonths($i);$key=$m->format('Y-m');
            $months[]=$m->translatedFormat('M Y');
            $created[]=$tasks->filter(fn($t)=>$t->created_at&&$t->created_at->format('Y-m')===$key)->count();
            $completed[]=$tasks->filter(fn($t)=>$t->completed_at&&$t->completed_at->format('Y-m')===$key)->count();
        }

        $activePlans=$plans->whereNotIn('status',['completed','cancelled']);
        return response()->json([
            'is_manager'=>$viewer->isManager(),
            'statuses'=>$status,
            'trend'=>['labels'=>$months,'created'=>$created,'completed'=>$completed],
            'kpi'=>[
                'employees'=>$viewer->isManager()?User::whereIn('id',$userIds)->where('is_active',true)->count():1,
                'active_plans'=>$activePlans->count(),
                'overdue_plans'=>$activePlans->filter(fn($p)=>$p->period_end&&$p->period_end->isPast())->count(),
                'avg_plan_progress'=>$activePlans->count()?round($activePlans->avg('progress'),1):0,
            ],
        ]);
    }
}

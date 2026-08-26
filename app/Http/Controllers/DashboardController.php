<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\Task;
use App\Models\User;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $myTasks = Task::with(['creator','plan','comments.user'])
            ->where('assigned_to', $user->id)
            ->where(function ($q) {
                $q->whereNotIn('status', ['completed','cancelled'])
                  ->orWhere(function ($done) {
                      $done->where('status','completed')->where('completed_at','>=',now()->copy()->subDays(14));
                  });
            })
            ->orderByRaw("CASE WHEN status='completed' THEN 2 WHEN due_at IS NULL THEN 1 ELSE 0 END")
            ->orderBy('due_at')
            ->latest('id')
            ->limit(40)
            ->get();

        $upcomingTasks = Task::with('creator')
            ->where('assigned_to', $user->id)
            ->whereNotIn('status', ['completed','cancelled'])
            ->whereBetween('due_at', [now(), now()->copy()->addDays(7)->endOfDay()])
            ->orderBy('due_at')->get();

        $upcomingPlans = Plan::where('user_id', $user->id)
            ->whereNotIn('status', ['completed','cancelled'])
            ->whereBetween('period_end', [today(), today()->copy()->addDays(7)])
            ->orderBy('period_end')->get();

        $open = Task::where('assigned_to',$user->id)->whereNotIn('status',['completed','cancelled'])->count();
        $overdue = Task::where('assigned_to',$user->id)->whereNotIn('status',['completed','cancelled'])->where('due_at','<',now())->count();
        $review = Task::where('assigned_to',$user->id)->where('status','review')->count();
        $doneMonth = Task::where('assigned_to',$user->id)->where('status','completed')->whereBetween('completed_at',[now()->copy()->startOfMonth(),now()->copy()->endOfMonth()])->count();
        $today = Task::where('assigned_to',$user->id)->whereNotIn('status',['completed','cancelled'])->whereDate('due_at',today())->count();
        $monthScope = $open + $doneMonth;

        $stats = [
            'my_open' => $open,
            'my_overdue' => $overdue,
            'my_review' => $review,
            'my_done_month' => $doneMonth,
            'today' => $today,
            'done_percent' => $monthScope > 0 ? (int) round(($doneMonth / $monthScope) * 100) : 100,
            'due_7_days' => $upcomingTasks->count(),
            'plans_7_days' => $upcomingPlans->count(),
        ];

        if ($user->isManager()) {
            $teamIds = $user->isAdmin() ? User::pluck('id') : $user->subordinates()->pluck('id');
            $stats['team_open'] = Task::whereIn('assigned_to',$teamIds)->whereNotIn('status',['completed','cancelled'])->count();
            $stats['team_overdue'] = Task::whereIn('assigned_to',$teamIds)->whereNotIn('status',['completed','cancelled'])->where('due_at','<',now())->count();
        }

        return view('dashboard', compact('myTasks','upcomingTasks','upcomingPlans','stats'));
    }
}

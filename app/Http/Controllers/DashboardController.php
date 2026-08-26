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
        $myTasks = Task::with(['creator','plan'])->where('assigned_to', $user->id)
            ->whereNotIn('status', ['completed','cancelled'])
            ->orderByRaw('due_at IS NULL, due_at ASC')->limit(10)->get();

        $upcomingTasks = Task::with('creator')
            ->where('assigned_to', $user->id)
            ->whereNotIn('status', ['completed','cancelled'])
            ->whereBetween('due_at', [now(), now()->copy()->addDays(7)->endOfDay()])
            ->orderBy('due_at')->get();

        $upcomingPlans = Plan::where('user_id', $user->id)
            ->whereNotIn('status', ['completed','cancelled'])
            ->whereBetween('period_end', [today(), today()->copy()->addDays(7)])
            ->orderBy('period_end')->get();

        $stats = [
            'my_open' => Task::where('assigned_to',$user->id)->whereNotIn('status',['completed','cancelled'])->count(),
            'my_overdue' => Task::where('assigned_to',$user->id)->whereNotIn('status',['completed','cancelled'])->where('due_at','<',now())->count(),
            'my_review' => Task::where('assigned_to',$user->id)->where('status','review')->count(),
            'my_done_month' => Task::where('assigned_to',$user->id)->where('status','completed')->whereBetween('completed_at',[now()->startOfMonth(),now()->endOfMonth()])->count(),
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

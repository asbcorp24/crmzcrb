<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Meeting;
use App\Models\Plan;
use App\Models\Task;
use App\Models\TaskComment;
use App\Models\User;
use App\Services\AccessService;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function page(Request $request)
    {
        $q = trim((string)$request->get('q', ''));
        $results = $q !== '' ? $this->results($request, $q) : [];
        return view('search.index', compact('q','results'));
    }

    public function ajax(Request $request)
    {
        $q = trim((string)$request->get('q', ''));
        if (mb_strlen($q) < 2) return response()->json([]);
        return response()->json(array_slice($this->results($request, $q), 0, 20));
    }

    private function results(Request $request, string $q): array
    {
        $viewer = $request->user();
        $access = app(AccessService::class);
        $userIds = $access->userIds($viewer, true);
        $departmentIds = $access->departmentIds($viewer);
        $items = collect();

        User::with('department')->whereIn('id',$userIds)
            ->where(function($w) use($q){
                $w->where('last_name','like',"%{$q}%")->orWhere('first_name','like',"%{$q}%")
                  ->orWhere('middle_name','like',"%{$q}%")->orWhere('position','like',"%{$q}%")
                  ->orWhere('email','like',"%{$q}%");
            })->limit(10)->get()->each(function($u) use($items){
                $items->push(['type'=>'Сотрудник','title'=>$u->full_name,'subtitle'=>trim(($u->position ?: '').' · '.($u->department?->name ?: '')),'url'=>route('employees.profile',$u,false),'icon'=>'bi-person']);
            });

        Task::with('assignee')->whereIn('assigned_to',$userIds)
            ->where(function($w) use($q){$w->where('title','like',"%{$q}%")->orWhere('description','like',"%{$q}%")->orWhere('result','like',"%{$q}%");})
            ->limit(15)->get()->each(function($t) use($items){
                $items->push(['type'=>'Задача','title'=>$t->title,'subtitle'=>($t->assignee?->full_name ?: '').' · '.$this->statusName($t->status),'url'=>route('tasks.page',['task'=>$t->id],false),'icon'=>'bi-check2-square']);
            });

        Plan::with('user')->whereIn('user_id',$userIds)
            ->where(function($w) use($q){$w->where('title','like',"%{$q}%")->orWhere('description','like',"%{$q}%");})
            ->limit(10)->get()->each(function($p) use($items){
                $items->push(['type'=>'План','title'=>$p->title,'subtitle'=>$p->user?->full_name ?: '','url'=>route('plans.page',[],false).'?user_id='.$p->user_id,'icon'=>'bi-calendar3']);
            });

        if ($viewer->isManager()) {
            Meeting::where(function($w) use($q){$w->where('title','like',"%{$q}%")->orWhere('notes','like',"%{$q}%")->orWhere('location','like',"%{$q}%");})
                ->when(!$viewer->isAdmin(), fn($w)=>$w->where('created_by',$viewer->id))
                ->limit(10)->get()->each(function($m) use($items){
                    $items->push(['type'=>'Совещание','title'=>$m->title,'subtitle'=>$m->held_at?->format('d.m.Y H:i').' · '.($m->location ?: ''),'url'=>route('meetings.page',[],false),'icon'=>'bi-journal-check']);
                });

            Department::whereIn('id',$departmentIds)->where(function($w) use($q){$w->where('name','like',"%{$q}%")->orWhere('short_name','like',"%{$q}%");})
                ->limit(10)->get()->each(function($d) use($items){
                    $items->push(['type'=>'Подразделение','title'=>$d->name,'subtitle'=>$d->short_name ?: '','url'=>route('departments.show360',$d,false),'icon'=>'bi-diagram-3']);
                });
        }

        TaskComment::with(['task','user'])->whereHas('task',fn($w)=>$w->whereIn('assigned_to',$userIds))
            ->where('body','like',"%{$q}%")->latest()->limit(10)->get()->each(function($c) use($items){
                $items->push(['type'=>'Комментарий','title'=>$c->task?->title ?: 'Комментарий к задаче','subtitle'=>mb_strimwidth($c->body,0,120,'…'),'url'=>$c->task ? route('tasks.page',['task'=>$c->task_id],false) : '#','icon'=>'bi-chat-left-text']);
            });

        return $items->take(50)->values()->all();
    }

    private function statusName(string $status): string
    {
        return ['new'=>'Новая','in_progress'=>'В работе','review'=>'На проверке','completed'=>'Выполнено','cancelled'=>'Отменено'][$status] ?? $status;
    }
}

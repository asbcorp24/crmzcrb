<?php

namespace App\Http\Controllers;

use App\Models\CrmNotification;
use App\Models\Meeting;
use App\Models\MeetingItem;
use App\Models\Task;
use App\Models\TaskEvent;
use App\Models\User;
use App\Services\AccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class MeetingController extends Controller
{
    public function page(Request $request)
    {
        abort_unless($request->user()->isManager(), 403);
        $ids = app(AccessService::class)->userIds($request->user(), true);
        $users = User::whereIn('id', $ids)->where('is_active', true)->whereNull('archived_at')->orderBy('last_name')->orderBy('first_name')->get();
        return view('meetings.index', compact('users'));
    }

    public function index(Request $request)
    {
        abort_unless($request->user()->isManager(), 403);
        $ids = app(AccessService::class)->userIds($request->user(), true);
        $q = Meeting::with(['chairman','secretary','creator','items.assignee','items.task.assignee.department'])
            ->whereNull('archived_at')
            ->where(function ($w) use ($request, $ids) {
                $w->whereIn('created_by', $ids)->orWhereHas('participants', fn($p) => $p->where('users.id', $request->user()->id));
            });
        if ($request->filled('status')) $q->where('status',$request->status);
        if ($request->filled('q')) $q->where('title','like','%'.$request->q.'%');
        return response()->json($q->latest('held_at')->paginate(30));
    }

    public function show(Request $request, Meeting $meeting)
    {
        $this->authorizeMeetingView($request,$meeting);
        return response()->json($meeting->load(['chairman','secretary','creator','participants.department','items.assignee.department','items.task.assignee.department']));
    }

    public function store(Request $request)
    {
        abort_unless($request->user()->isManager(), 403);
        $data=$request->validate(['title'=>'required|string|max:255','held_at'=>'required|date','location'=>'nullable|string|max:255','chairman_id'=>'nullable|exists:users,id','secretary_id'=>'nullable|exists:users,id','notes'=>'nullable|string|max:10000','status'=>['required',Rule::in(['draft','active','closed'])],'participants'=>'nullable|array','participants.*'=>'integer|exists:users,id']);
        $allowed = app(AccessService::class)->userIds($request->user(), true);
        foreach (array_filter([$data['chairman_id'] ?? null, $data['secretary_id'] ?? null]) as $id) abort_unless($allowed->contains((int)$id), 403);
        $participants=$data['participants']??[]; foreach ($participants as $id) abort_unless($allowed->contains((int)$id), 403); unset($data['participants']); $data['created_by']=$request->user()->id;
        $meeting=DB::transaction(function() use($data,$participants){$m=Meeting::create($data);$m->participants()->sync($participants);return $m;});
        return response()->json(['ok'=>true,'meeting'=>$meeting->load(['chairman','secretary','participants'])],201);
    }

    public function update(Request $request, Meeting $meeting)
    {
        $this->authorizeMeetingManage($request,$meeting);
        $data=$request->validate(['title'=>'sometimes|required|string|max:255','held_at'=>'sometimes|required|date','location'=>'nullable|string|max:255','chairman_id'=>'nullable|exists:users,id','secretary_id'=>'nullable|exists:users,id','notes'=>'nullable|string|max:10000','status'=>[Rule::in(['draft','active','closed'])],'participants'=>'nullable|array','participants.*'=>'integer|exists:users,id']);
        $allowed = app(AccessService::class)->userIds($request->user(), true);
        foreach (array_filter([$data['chairman_id'] ?? null, $data['secretary_id'] ?? null]) as $id) abort_unless($allowed->contains((int)$id), 403);
        foreach (($data['participants'] ?? []) as $id) abort_unless($allowed->contains((int)$id), 403);
        DB::transaction(function() use($meeting,$data){if(array_key_exists('participants',$data)){$p=$data['participants'];unset($data['participants']);$meeting->participants()->sync($p);} $meeting->update($data);});
        return response()->json(['ok'=>true,'meeting'=>$meeting->fresh()->load(['chairman','secretary','participants','items.assignee','items.task.assignee.department'])]);
    }

    public function addItem(Request $request, Meeting $meeting)
    {
        $this->authorizeMeetingManage($request,$meeting); abort_if($meeting->status==='closed',422,'Совещание закрыто');
        $data=$request->validate(['instruction'=>'required|string|max:10000','assigned_to'=>'required|exists:users,id','due_at'=>'nullable|date','priority'=>['required',Rule::in(['low','normal','high','critical'])]]);
        abort_unless(app(AccessService::class)->userIds($request->user(), true)->contains((int)$data['assigned_to']), 403);
        $item=DB::transaction(function() use($request,$meeting,$data){$number=(int)$meeting->items()->lockForUpdate()->max('number')+1;$task=Task::create(['created_by'=>$request->user()->id,'assigned_to'=>$data['assigned_to'],'title'=>'Поручение по совещанию: '.str($data['instruction'])->limit(120),'description'=>$data['instruction'].'\n\nСовещание: '.$meeting->title.' от '.$meeting->held_at->format('d.m.Y H:i'),'priority'=>$data['priority'],'status'=>'new','progress'=>0,'due_at'=>$data['due_at']??null]);TaskEvent::create(['task_id'=>$task->id,'user_id'=>$request->user()->id,'type'=>'created','to_status'=>'new','message'=>'Создано из протокола совещания #'.$meeting->id]);$item=MeetingItem::create(['meeting_id'=>$meeting->id,'number'=>$number,'instruction'=>$data['instruction'],'assigned_to'=>$data['assigned_to'],'due_at'=>$data['due_at']??null,'priority'=>$data['priority'],'task_id'=>$task->id,'created_by'=>$request->user()->id]);if((int)$data['assigned_to']!==$request->user()->id)CrmNotification::create(['user_id'=>$data['assigned_to'],'task_id'=>$task->id,'type'=>'task_assigned','title'=>'Новое поручение по протоколу','body'=>$meeting->title.': '.$data['instruction'],'url'=>route('tasks.page',['task'=>$task->id],false)]);return $item;});
        return response()->json(['ok'=>true,'item'=>$item->load(['assignee','task.assignee.department'])],201);
    }

    public function close(Request $request, Meeting $meeting)
    { $this->authorizeMeetingManage($request,$meeting); $meeting->update(['status'=>'closed']); return response()->json(['ok'=>true]); }

    private function authorizeMeetingView(Request $request, Meeting $meeting): void
    { $user=$request->user(); if($user->isAdmin()||(int)$meeting->created_by===(int)$user->id)return; $ids=app(AccessService::class)->userIds($user,true); if($ids->contains((int)$meeting->created_by))return; abort_unless($meeting->participants()->where('users.id',$user->id)->exists(),403); }

    private function authorizeMeetingManage(Request $request, Meeting $meeting): void
    { $user=$request->user(); if($user->isAdmin()||(int)$meeting->created_by===(int)$user->id)return; $creator=User::find($meeting->created_by); abort_unless($creator&&app(AccessService::class)->canManageUser($user,$creator),403); }
}

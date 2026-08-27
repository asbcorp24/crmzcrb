<?php

namespace App\Http\Controllers;

use App\Models\CrmNotification;
use App\Models\Department;
use App\Models\EmployeeAssignment;
use App\Models\Position;
use App\Models\Task;
use App\Models\TaskEvent;
use App\Models\TaskTag;
use App\Models\User;
use App\Services\AccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AdvancedTaskController extends Controller
{
    public function bulkPage(Request $request)
    {
        abort_unless($request->user()->isManager(),403);
        $access=app(AccessService::class); $ids=$access->userIds($request->user(),true);
        return view('tasks.bulk',[
            'users'=>User::whereIn('id',$ids)->where('is_active',true)->whereNull('archived_at')->orderBy('last_name')->get(),
            'departments'=>Department::whereIn('id',$access->departmentIds($request->user()))->where('is_active',true)->orderBy('name')->get(),
            'positions'=>Position::where('is_active',true)->orderBy('name')->get(),
            'tags'=>TaskTag::where('is_active',true)->orderBy('name')->get(),
        ]);
    }

    public function bulkStore(Request $request)
    {
        abort_unless($request->user()->isManager(),403);
        $data=$request->validate([
            'target_type'=>['required',Rule::in(['users','department','managers','position'])],
            'user_ids'=>'nullable|array','user_ids.*'=>'integer|exists:users,id','department_id'=>'nullable|exists:departments,id','position_id'=>'nullable|exists:positions,id',
            'title'=>'required|string|max:255','description'=>'nullable|string','priority'=>['required',Rule::in(['low','normal','high','critical'])],
            'due_at'=>'nullable|date','tag_ids'=>'nullable|array','tag_ids.*'=>'integer|exists:task_tags,id',
        ]);
        $access=app(AccessService::class); $allowed=$access->userIds($request->user(),true)->map(fn($x)=>(int)$x);
        $targets=match($data['target_type']){
            'users'=>collect($data['user_ids']??[])->map(fn($x)=>(int)$x),
            'department'=>User::where('department_id',$data['department_id']??0)->where('is_active',true)->whereNull('archived_at')->pluck('id'),
            'managers'=>User::whereIn('id',$allowed)->whereIn('role',['manager','admin'])->where('is_active',true)->whereNull('archived_at')->pluck('id'),
            'position'=>EmployeeAssignment::whereNull('ended_at')->whereHas('user',fn($q)=>$q->where('is_active',true)->whereNull('archived_at'))->whereHas('staffingPosition',fn($q)=>$q->where('position_id',$data['position_id']??0))->pluck('user_id'),
        };
        $targets=$targets->unique()->filter(fn($id)=>$allowed->contains((int)$id))->values(); abort_if($targets->isEmpty(),422,'Не найдено доступных исполнителей');
        $created=DB::transaction(function()use($request,$data,$targets){$items=collect();foreach($targets as $id){$task=Task::create(['created_by'=>$request->user()->id,'assigned_to'=>$id,'title'=>$data['title'],'description'=>$data['description']??null,'priority'=>$data['priority'],'status'=>'new','progress'=>0,'due_at'=>$data['due_at']??null]);if(!empty($data['tag_ids']))$task->tags()->sync($data['tag_ids']);TaskEvent::create(['task_id'=>$task->id,'user_id'=>$request->user()->id,'type'=>'created','to_status'=>'new','message'=>'Создано массовой постановкой']);if((int)$id!==$request->user()->id)CrmNotification::create(['user_id'=>$id,'task_id'=>$task->id,'type'=>'task_assigned','title'=>'Новая массовая задача','body'=>$task->title,'url'=>route('tasks.page',['task'=>$task->id],false)]);$items->push($task);}return $items;});
        return response()->json(['ok'=>true,'created'=>$created->count(),'task_ids'=>$created->pluck('id')],201);
    }

    public function metadata(Request $request, Task $task)
    {
        $this->authorizeView($request,$task); $ids=app(AccessService::class)->userIds($request->user(),true);
        $available=Task::with('assignee')->whereNull('archived_at')->whereIn('assigned_to',$ids)->where('id','<>',$task->id)->whereNotIn('status',['cancelled'])->orderByRaw('due_at IS NULL, due_at ASC')->limit(150)->get(['id','assigned_to','title','status','due_at']);
        return response()->json([
            'subtasks'=>$task->subtasks()->whereNull('archived_at')->with('assignee')->get(),
            'blockers'=>$task->blockers()->with('assignee')->get(),
            'blocked_tasks'=>$task->blockedTasks()->with('assignee')->get(),
            'tags'=>$task->tags()->get(),
            'available_tags'=>TaskTag::where('is_active',true)->orderBy('name')->get(),
            'available_tasks'=>$available,
        ]);
    }

    public function addSubtask(Request $request, Task $task)
    {
        $this->authorizeManage($request,$task);
        $data=$request->validate(['title'=>'required|string|max:255','assigned_to'=>'nullable|exists:users,id','due_at'=>'nullable|date','priority'=>['nullable',Rule::in(['low','normal','high','critical'])]]);
        $assignee=(int)($data['assigned_to']??$task->assigned_to); $this->authorizeTarget($request,$assignee);
        $sub=Task::create(['parent_task_id'=>$task->id,'plan_id'=>$task->plan_id,'created_by'=>$request->user()->id,'assigned_to'=>$assignee,'title'=>$data['title'],'priority'=>$data['priority']??$task->priority,'status'=>'new','progress'=>0,'due_at'=>$data['due_at']??$task->due_at]);
        TaskEvent::create(['task_id'=>$sub->id,'user_id'=>$request->user()->id,'type'=>'created','to_status'=>'new','message'=>'Подзадача задачи #'.$task->id]);
        if($assignee!==$request->user()->id)CrmNotification::create(['user_id'=>$assignee,'task_id'=>$sub->id,'type'=>'task_assigned','title'=>'Новая подзадача','body'=>$sub->title,'url'=>route('tasks.page',['task'=>$sub->id],false)]);
        return response()->json(['ok'=>true,'subtask'=>$sub->load('assignee')],201);
    }

    public function addDependency(Request $request, Task $task)
    {
        $this->authorizeManage($request,$task); $data=$request->validate(['blocked_by_task_id'=>'required|exists:tasks,id']);
        $blocker=Task::findOrFail($data['blocked_by_task_id']); $this->authorizeView($request,$blocker);
        abort_if($blocker->id===$task->id,422,'Задача не может зависеть сама от себя');
        abort_if($this->wouldCreateCycle($task,$blocker),422,'Нельзя создать циклическую зависимость задач');
        $task->blockers()->syncWithoutDetaching([$blocker->id=>['created_by'=>$request->user()->id]]); return response()->json(['ok'=>true]);
    }

    public function removeDependency(Request $request, Task $task, Task $blocker)
    { $this->authorizeManage($request,$task); $task->blockers()->detach($blocker->id); return response()->json(['ok'=>true]); }

    public function syncTags(Request $request, Task $task)
    { $this->authorizeManage($request,$task); $data=$request->validate(['tag_ids'=>'nullable|array','tag_ids.*'=>'integer|exists:task_tags,id']); $task->tags()->sync($data['tag_ids']??[]); return response()->json(['ok'=>true,'tags'=>$task->tags]); }

    public function createTag(Request $request)
    { abort_unless($request->user()->isManager(),403); $data=$request->validate(['name'=>'required|string|max:100']); $slug=Str::slug($data['name']); if($slug==='')$slug='tag-'.Str::lower(Str::random(8)); $tag=TaskTag::firstOrCreate(['slug'=>$slug],['name'=>$data['name'],'is_active'=>true]); return response()->json(['ok'=>true,'tag'=>$tag],201); }

    public function archive(Request $request, Task $task)
    { $this->authorizeManage($request,$task); abort_unless(in_array($task->status,['completed','cancelled'],true),422,'В архив можно отправить только завершённую или отменённую задачу'); $task->update(['archived_at'=>now(),'archived_by'=>$request->user()->id]); return response()->json(['ok'=>true]); }

    private function wouldCreateCycle(Task $task,Task $blocker):bool
    {
        $target=(int)$task->id; $frontier=collect([(int)$blocker->id]); $seen=[];
        while($frontier->isNotEmpty()){
            if($frontier->contains($target))return true;
            $ids=$frontier->reject(fn($id)=>isset($seen[(int)$id]))->map(fn($id)=>(int)$id)->values();
            if($ids->isEmpty())break; foreach($ids as $id)$seen[$id]=true;
            $frontier=DB::table('task_dependencies')->whereIn('task_id',$ids)->pluck('blocked_by_task_id')->map(fn($id)=>(int)$id)->unique()->values();
        }
        return false;
    }

    private function authorizeView(Request $request,Task $task):void{$u=$request->user();if($u->isAdmin()||$task->assigned_to===$u->id||$task->created_by===$u->id)return;abort_unless($u->isManager()&&app(AccessService::class)->userIds($u,true)->contains((int)$task->assigned_to),403);}
    private function authorizeManage(Request $request,Task $task):void{$u=$request->user();if($u->isAdmin()||$task->created_by===$u->id)return;abort_unless($u->isManager()&&app(AccessService::class)->userIds($u,false)->contains((int)$task->assigned_to),403);}
    private function authorizeTarget(Request $request,int $id):void{abort_unless(app(AccessService::class)->userIds($request->user(),true)->contains($id),403);}
}

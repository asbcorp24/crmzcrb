<?php

namespace App\Http\Controllers;

use App\Models\CrmNotification;
use App\Models\Department;
use App\Models\EmployeeAssignment;
use App\Models\Position;
use App\Models\ReferenceItem;
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
            'projects'=>ReferenceItem::withoutGlobalScope('organization')->where('organization_id',$request->user()->organization_id)->where('type','project')->where('is_active',true)->orderBy('sort_order')->orderBy('name')->get(),
            'bases'=>ReferenceItem::withoutGlobalScope('organization')->where('organization_id',$request->user()->organization_id)->where('type','basis')->where('is_active',true)->orderBy('sort_order')->orderBy('name')->get(),
            'organizations'=>ReferenceItem::withoutGlobalScope('organization')->where('organization_id',$request->user()->organization_id)->where('type','organization')->where('is_active',true)->orderBy('sort_order')->orderBy('name')->get(),
            'businessStatuses'=>ReferenceItem::withoutGlobalScope('organization')->where('organization_id',$request->user()->organization_id)->where('type','task_status')->where('is_active',true)->orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }

    public function bulkStore(Request $request)
    {
        abort_unless($request->user()->isManager(),403);
        $data=$request->validate([
            'target_type'=>['required',Rule::in(['users','department','managers','position'])],
            'user_ids'=>'nullable|array','user_ids.*'=>'integer','department_id'=>'nullable|integer','position_id'=>'nullable|integer',
            'title'=>'nullable|string|max:255','description'=>'nullable|string','priority'=>['required',Rule::in(['low','normal','high','critical'])],
            'due_at'=>'nullable|date','tag_ids'=>'nullable|array','tag_ids.*'=>'integer',
            'project_id'=>'nullable|integer','basis_id'=>'nullable|integer','responsible_department_id'=>'nullable|integer',
            'start_at'=>'nullable|date','customer_type'=>['nullable',Rule::in(['organization','department'])],
            'customer_id'=>'nullable|integer','business_status_id'=>'nullable|integer',
        ]);
        $access=app(AccessService::class); $allowed=$access->userIds($request->user(),true)->map(fn($x)=>(int)$x);
        $orgId=(int)$request->user()->organization_id;
        $this->assertBulkReference($data['project_id']??null,'project',$orgId,'Проект');
        $this->assertBulkReference($data['basis_id']??null,'basis',$orgId,'Основание');
        $this->assertBulkReference($data['business_status_id']??null,'task_status',$orgId,'Статус');
        if(!empty($data['responsible_department_id'])) abort_unless($access->departmentIds($request->user())->contains((int)$data['responsible_department_id']),403);
        if(!empty($data['customer_type'])){
            abort_if(empty($data['customer_id']),422,'Выберите заказчика');
            if($data['customer_type']==='organization') $this->assertBulkReference($data['customer_id'],'organization',$orgId,'Заказчик');
            else abort_unless($access->departmentIds($request->user())->contains((int)$data['customer_id']),403);
        } else { $data['customer_type']=null; $data['customer_id']=null; }
        $title=trim((string)($data['title']??''));
        if($title===''){
            $title=$this->bulkFallbackTitle($data['project_id']??null,$data['basis_id']??null,$orgId);
            $data['title']=$title;
        }
        if(!empty($data['tag_ids'])) $this->assertTagsBelongToOrganization($data['tag_ids']);
        if($data['target_type']==='department' && !empty($data['department_id'])) abort_unless($access->departmentIds($request->user())->contains((int)$data['department_id']),403);
        if($data['target_type']==='position' && !empty($data['position_id'])) abort_unless(Position::whereKey($data['position_id'])->where('is_active',true)->exists(),422,'Должность не найдена в вашей организации');
        $targets=match($data['target_type']){
            'users'=>collect($data['user_ids']??[])->map(fn($x)=>(int)$x),
            'department'=>User::where('department_id',$data['department_id']??0)->where('is_active',true)->whereNull('archived_at')->pluck('id'),
            'managers'=>User::whereIn('id',$allowed)->whereIn('role',['manager','admin'])->where('is_active',true)->whereNull('archived_at')->pluck('id'),
            'position'=>EmployeeAssignment::whereNull('ended_at')->whereHas('user',fn($q)=>$q->where('is_active',true)->whereNull('archived_at'))->whereHas('staffingPosition',fn($q)=>$q->where('position_id',$data['position_id']??0))->pluck('user_id'),
        };
        $targets=$targets->unique()->filter(fn($id)=>$allowed->contains((int)$id))->values(); abort_if($targets->isEmpty(),422,'Не найдено доступных исполнителей');
        $created=DB::transaction(function()use($request,$data,$targets){$items=collect();foreach($targets as $id){$task=Task::create(['organization_id'=>$request->user()->organization_id,'created_by'=>$request->user()->id,'assigned_to'=>$id,'title'=>$data['title'],'description'=>$data['description']??null,'priority'=>$data['priority'],'status'=>'new','progress'=>0,'due_at'=>$data['due_at']??null]);DB::table('tasks')->where('id',$task->id)->update(['project_id'=>$data['project_id']??null,'basis_id'=>$data['basis_id']??null,'responsible_department_id'=>$data['responsible_department_id']??null,'start_at'=>$data['start_at']??null,'customer_type'=>$data['customer_type']??null,'customer_id'=>$data['customer_id']??null,'business_status_id'=>$data['business_status_id']??null,'updated_at'=>now()]);if(!empty($data['tag_ids']))$task->tags()->sync($data['tag_ids']);TaskEvent::create(['task_id'=>$task->id,'user_id'=>$request->user()->id,'type'=>'created','to_status'=>'new','message'=>'Создано массовой постановкой']);if((int)$id!==$request->user()->id)CrmNotification::create(['user_id'=>$id,'task_id'=>$task->id,'type'=>'task_assigned','title'=>'Новая массовая задача','body'=>$task->title,'url'=>route('tasks.page',['task'=>$task->id],false)]);$items->push($task->fresh());}return $items;});
        return response()->json(['ok'=>true,'created'=>$created->count(),'task_ids'=>$created->pluck('id')],201);
    }

    public function metadata(Request $request, Task $task)
    {
        $this->authorizeView($request,$task); $ids=app(AccessService::class)->userIds($request->user(),true);
        $available=Task::with('assignee')->whereNull('archived_at')->whereIn('assigned_to',$ids)->where('id','<>',$task->id)->whereNotIn('status',['cancelled'])->orderByRaw('due_at IS NULL, due_at ASC')->limit(150)->get(['id','assigned_to','title','status','due_at']);
        return response()->json(['subtasks'=>$task->subtasks()->whereNull('archived_at')->with('assignee')->get(),'blockers'=>$task->blockers()->with('assignee')->get(),'blocked_tasks'=>$task->blockedTasks()->with('assignee')->get(),'tags'=>$task->tags()->get(),'available_tags'=>TaskTag::where('is_active',true)->orderBy('name')->get(),'available_tasks'=>$available]);
    }

    public function addSubtask(Request $request, Task $task)
    {
        $this->authorizeManage($request,$task); $data=$request->validate(['title'=>'required|string|max:255','assigned_to'=>'nullable|integer','due_at'=>'nullable|date','priority'=>['nullable',Rule::in(['low','normal','high','critical'])]]);
        $assignee=(int)($data['assigned_to']??$task->assigned_to); $this->authorizeTarget($request,$assignee);
        $sub=Task::create(['organization_id'=>$request->user()->organization_id,'parent_task_id'=>$task->id,'plan_id'=>$task->plan_id,'created_by'=>$request->user()->id,'assigned_to'=>$assignee,'title'=>$data['title'],'priority'=>$data['priority']??$task->priority,'status'=>'new','progress'=>0,'due_at'=>$data['due_at']??$task->due_at]);
        TaskEvent::create(['task_id'=>$sub->id,'user_id'=>$request->user()->id,'type'=>'created','to_status'=>'new','message'=>'Подзадача задачи #'.$task->id]);
        if($assignee!==$request->user()->id)CrmNotification::create(['user_id'=>$assignee,'task_id'=>$sub->id,'type'=>'task_assigned','title'=>'Новая подзадача','body'=>$sub->title,'url'=>route('tasks.page',['task'=>$sub->id],false)]);
        return response()->json(['ok'=>true,'subtask'=>$sub->load('assignee')],201);
    }

    public function addDependency(Request $request, Task $task)
    {
        $this->authorizeManage($request,$task); $data=$request->validate(['blocked_by_task_id'=>'required|integer']);
        $blocker=Task::findOrFail($data['blocked_by_task_id']); $this->authorizeView($request,$blocker); abort_if($blocker->id===$task->id,422,'Задача не может зависеть сама от себя'); abort_if($this->wouldCreateCycle($task,$blocker),422,'Нельзя создать циклическую зависимость задач');
        $task->blockers()->syncWithoutDetaching([$blocker->id=>['created_by'=>$request->user()->id]]); return response()->json(['ok'=>true]);
    }

    public function removeDependency(Request $request, Task $task, Task $blocker){$this->authorizeManage($request,$task);$task->blockers()->detach($blocker->id);return response()->json(['ok'=>true]);}

    public function syncTags(Request $request, Task $task)
    {
        $this->authorizeManage($request,$task); $data=$request->validate(['tag_ids'=>'nullable|array','tag_ids.*'=>'integer']); $ids=$data['tag_ids']??[]; $this->assertTagsBelongToOrganization($ids); $task->tags()->sync($ids); return response()->json(['ok'=>true,'tags'=>$task->tags]);
    }

    public function createTag(Request $request)
    {
        abort_unless($request->user()->isManager(),403); $data=$request->validate(['name'=>'required|string|max:100']); $slug=Str::slug($data['name']); if($slug==='')$slug='tag-'.Str::lower(Str::random(8));
        $tag=TaskTag::firstOrCreate(['slug'=>$slug],['organization_id'=>$request->user()->organization_id,'name'=>$data['name'],'is_active'=>true]); return response()->json(['ok'=>true,'tag'=>$tag],201);
    }

    public function archive(Request $request, Task $task){$this->authorizeManage($request,$task);abort_unless(in_array($task->status,['completed','cancelled'],true),422,'В архив можно отправить только завершённую или отменённую задачу');$task->update(['archived_at'=>now(),'archived_by'=>$request->user()->id]);return response()->json(['ok'=>true]);}

    private function assertBulkReference($id,string $type,int $orgId,string $label):void{if(!$id)return;abort_unless(ReferenceItem::withoutGlobalScope('organization')->where('organization_id',$orgId)->whereKey((int)$id)->where('type',$type)->where('is_active',true)->exists(),422,$label.' не найден(о) в справочнике');}
    private function bulkFallbackTitle($projectId,$basisId,int $orgId):string{if($projectId){$name=ReferenceItem::withoutGlobalScope('organization')->where('organization_id',$orgId)->whereKey((int)$projectId)->where('type','project')->value('name');if($name)return $name;}if($basisId){$name=ReferenceItem::withoutGlobalScope('organization')->where('organization_id',$orgId)->whereKey((int)$basisId)->where('type','basis')->value('name');if($name)return $name;}return 'Задача';}
    private function assertTagsBelongToOrganization(array $ids):void{if(!$ids)return;$unique=collect($ids)->map(fn($x)=>(int)$x)->unique();abort_unless(TaskTag::whereIn('id',$unique)->count()===$unique->count(),422,'Одна или несколько меток не принадлежат вашей организации');}
    private function wouldCreateCycle(Task $task,Task $blocker):bool{$target=(int)$task->id;$frontier=collect([(int)$blocker->id]);$seen=[];while($frontier->isNotEmpty()){if($frontier->contains($target))return true;$ids=$frontier->reject(fn($id)=>isset($seen[(int)$id]))->map(fn($id)=>(int)$id)->values();if($ids->isEmpty())break;foreach($ids as $id)$seen[$id]=true;$frontier=Task::whereIn('id',$ids)->get()->flatMap(fn(Task $t)=>$t->blockers()->pluck('tasks.id'))->map(fn($id)=>(int)$id)->unique()->values();}return false;}
    private function authorizeView(Request $request,Task $task):void{$u=$request->user();if($u->isAdmin()||$task->assigned_to===$u->id||$task->created_by===$u->id)return;abort_unless($u->isManager()&&app(AccessService::class)->userIds($u,true)->contains((int)$task->assigned_to),403);}
    private function authorizeManage(Request $request,Task $task):void{$u=$request->user();if($u->isAdmin()||$task->created_by===$u->id)return;abort_unless($u->isManager()&&app(AccessService::class)->userIds($u,false)->contains((int)$task->assigned_to),403);}
    private function authorizeTarget(Request $request,int $id):void{abort_unless(app(AccessService::class)->userIds($request->user(),true)->contains($id),403);}
}

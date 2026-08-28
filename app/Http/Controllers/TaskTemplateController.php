<?php

namespace App\Http\Controllers;

use App\Models\CrmNotification;
use App\Models\Task;
use App\Models\TaskChecklistItem;
use App\Models\TaskEvent;
use App\Models\TaskTemplate;
use App\Models\TaskTemplateChecklistItem;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class TaskTemplateController extends Controller
{
    public function page(Request $request)
    {
        abort_unless($request->user()->isManager(),403);
        $users=$request->user()->isAdmin()?User::where('is_active',true)->orderBy('last_name')->get():User::where(function($q)use($request){$q->where('id',$request->user()->id)->orWhere('manager_id',$request->user()->id);})->where('is_active',true)->orderBy('last_name')->get();
        return view('task_templates.index',compact('users'));
    }

    public function index(Request $request){abort_unless($request->user()->isManager(),403);$q=TaskTemplate::with(['assignee','checklistItems']);if(!$request->user()->isAdmin())$q->where('created_by',$request->user()->id);return response()->json($q->latest()->get());}

    public function store(Request $request)
    {
        abort_unless($request->user()->isManager(),403);[$data,$checklist]=$this->validated($request);$data['created_by']=$request->user()->id;$data['organization_id']=$request->user()->organization_id;
        $template=DB::transaction(function()use($data,$checklist){$t=TaskTemplate::create($data);$this->syncChecklist($t,$checklist);return $t;});
        return response()->json(['ok'=>true,'template'=>$template->load(['assignee','checklistItems'])],201);
    }

    public function update(Request $request, TaskTemplate $template){$this->authorizeTemplate($request,$template);[$data,$checklist]=$this->validated($request);DB::transaction(function()use($template,$data,$checklist){$template->update($data);$this->syncChecklist($template,$checklist);});return response()->json(['ok'=>true,'template'=>$template->fresh()->load(['assignee','checklistItems'])]);}
    public function toggle(Request $request, TaskTemplate $template){$this->authorizeTemplate($request,$template);$data=$request->validate(['is_active'=>'required|boolean']);$template->update(['is_active'=>$data['is_active']]);return response()->json(['ok'=>true,'template'=>$template->fresh()]);}
    public function createTask(Request $request, TaskTemplate $template){$this->authorizeTemplate($request,$template);$task=$this->makeTask($template,$request->user()->id);return response()->json(['ok'=>true,'task'=>$task],201);}

    public function makeTask(TaskTemplate $template,int $creatorId):Task
    {
        return DB::transaction(function()use($template,$creatorId){
            $assignedTo=$template->assigned_to?:$creatorId;
            $task=Task::create(['organization_id'=>$template->organization_id,'created_by'=>$creatorId,'assigned_to'=>$assignedTo,'title'=>$template->title,'description'=>$template->description,'priority'=>$template->priority,'status'=>'new','progress'=>0,'due_at'=>now()->addDays($template->due_after_days)]);
            TaskEvent::create(['task_id'=>$task->id,'user_id'=>$creatorId,'type'=>'created','to_status'=>'new','message'=>'Задача создана по шаблону']);
            foreach($template->checklistItems as $item)TaskChecklistItem::create(['task_id'=>$task->id,'title'=>$item->title,'sort_order'=>$item->sort_order]);
            if($assignedTo!==$creatorId)CrmNotification::create(['user_id'=>$assignedTo,'task_id'=>$task->id,'type'=>'task_assigned','title'=>'Новая задача по шаблону','body'=>$task->title,'url'=>route('tasks.page',['task'=>$task->id],false)]);
            return $task;
        });
    }

    private function validated(Request $request):array
    {
        $orgId=(int)$request->user()->organization_id;
        $data=$request->validate(['assigned_to'=>['nullable',Rule::exists('users','id')->where(fn($q)=>$q->where('organization_id',$orgId)->where('is_superadmin',false))],'title'=>'required|string|max:255','description'=>'nullable|string','priority'=>['required',Rule::in(['low','normal','high','critical'])],'due_after_days'=>'required|integer|min:0|max:3650','recurrence'=>['required',Rule::in(['none','daily','weekly','monthly'])],'recurrence_interval'=>'required|integer|min:1|max:365','weekday'=>'nullable|integer|min:1|max:7','day_of_month'=>'nullable|integer|min:1|max:31','next_run_at'=>'nullable|date','is_active'=>'required|boolean','checklist'=>'nullable|array','checklist.*'=>'nullable|string|max:255']);
        $checklist=array_values(array_filter(array_map('trim',$data['checklist']??[])));unset($data['checklist']);
        if($data['recurrence']==='none'){$data['next_run_at']=null;$data['weekday']=null;$data['day_of_month']=null;}else{if(empty($data['next_run_at']))$data['next_run_at']=now();if($data['recurrence']!=='weekly')$data['weekday']=null;if($data['recurrence']!=='monthly')$data['day_of_month']=null;}
        return [$data,$checklist];
    }

    private function syncChecklist(TaskTemplate $template,array $checklist):void{$template->checklistItems()->delete();foreach($checklist as $i=>$title)TaskTemplateChecklistItem::create(['task_template_id'=>$template->id,'title'=>$title,'sort_order'=>$i]);}
    private function authorizeTemplate(Request $request,TaskTemplate $template):void{abort_unless($request->user()->isManager(),403);if(!$request->user()->isAdmin())abort_unless($template->created_by===$request->user()->id,403);}
}

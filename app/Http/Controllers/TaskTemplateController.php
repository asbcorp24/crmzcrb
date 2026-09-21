<?php

namespace App\Http\Controllers;

use App\Models\CrmNotification;
use App\Models\Department;
use App\Models\Plan;
use App\Models\ReferenceItem;
use App\Models\Task;
use App\Models\TaskChecklistItem;
use App\Models\TaskEvent;
use App\Models\TaskTemplate;
use App\Models\TaskTemplateChecklistItem;
use App\Models\User;
use App\Services\AccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class TaskTemplateController extends Controller
{
    public function page(Request $request)
    {
        abort_unless($request->user()->isManager(),403);
        $access=app(AccessService::class);
        $userIds=$access->userIds($request->user(),true);
        $departmentIds=$access->departmentIds($request->user());
        $orgId=(int)$request->user()->organization_id;
        $users=User::whereIn('id',$userIds)->where('is_active',true)->whereNull('archived_at')->orderBy('last_name')->orderBy('first_name')->get();
        $departments=Department::whereIn('id',$departmentIds)->where('is_active',true)->orderBy('name')->get();
        $projects=$this->refs('project',$orgId);
        $bases=$this->refs('basis',$orgId);
        $organizations=$this->refs('organization',$orgId);
        $businessStatuses=$this->refs('task_status',$orgId);
        return view('task_templates.index',compact('users','departments','projects','bases','organizations','businessStatuses'));
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
            $title=trim((string)$template->title);
            if($title==='')$title=$this->fallbackTitle($template->project_id,$template->basis_id,(int)$template->organization_id);
            $plan=null;
            if($template->add_to_plan){
                $monthStart=now()->copy()->startOfMonth()->toDateString();
                $monthEnd=now()->copy()->endOfMonth()->toDateString();
                $plan=Plan::where('user_id',$assignedTo)->where('period_type','month')
                    ->whereDate('period_start','<=',$monthEnd)->whereDate('period_end','>=',$monthStart)
                    ->whereIn('status',['active','draft'])
                    ->orderByRaw("CASE WHEN status = 'active' THEN 0 ELSE 1 END")->orderByDesc('period_start')->orderByDesc('id')->first();
            }
            $task=Task::create([
                'organization_id'=>$template->organization_id,'plan_id'=>$plan?->id,'created_by'=>$creatorId,'assigned_to'=>$assignedTo,
                'title'=>$title,'description'=>$template->description,'priority'=>$template->priority,'status'=>'new','progress'=>0,
                'due_at'=>now()->addDays((int)$template->due_after_days)
            ]);
            DB::table('tasks')->where('id',$task->id)->update([
                'project_id'=>$template->project_id,'basis_id'=>$template->basis_id,
                'responsible_department_id'=>$template->responsible_department_id,
                'start_at'=>$template->start_after_days===null?null:now()->addDays((int)$template->start_after_days),
                'customer_type'=>$template->customer_type,'customer_id'=>$template->customer_id,
                'business_status_id'=>$template->business_status_id,'updated_at'=>now(),
            ]);
            TaskEvent::create(['task_id'=>$task->id,'user_id'=>$creatorId,'type'=>'created','to_status'=>'new','message'=>$plan?'Задача создана по шаблону и добавлена в план #'.$plan->id:'Задача создана по шаблону']);
            foreach($template->checklistItems as $item)TaskChecklistItem::create(['task_id'=>$task->id,'title'=>$item->title,'sort_order'=>$item->sort_order]);
            if($assignedTo!==$creatorId)CrmNotification::create(['user_id'=>$assignedTo,'task_id'=>$task->id,'type'=>'task_assigned','title'=>'Новая задача по шаблону','body'=>$task->title,'url'=>route('tasks.page',['task'=>$task->id],false)]);
            return $task->fresh();
        });
    }

    private function validated(Request $request):array
    {
        $orgId=(int)$request->user()->organization_id;
        $data=$request->validate([
            'assigned_to'=>['nullable',Rule::exists('users','id')->where(fn($q)=>$q->where('organization_id',$orgId)->where('is_superadmin',false))],
            'project_id'=>'nullable|integer','basis_id'=>'nullable|integer','responsible_department_id'=>'nullable|integer',
            'start_after_days'=>'nullable|integer|min:0|max:3650','customer_type'=>['nullable',Rule::in(['organization','department'])],
            'customer_id'=>'nullable|integer','business_status_id'=>'nullable|integer','add_to_plan'=>'nullable|boolean',
            'title'=>'nullable|string|max:255','description'=>'nullable|string','priority'=>['required',Rule::in(['low','normal','high','critical'])],
            'due_after_days'=>'required|integer|min:0|max:3650','recurrence'=>['required',Rule::in(['none','daily','weekly','monthly'])],
            'recurrence_interval'=>'required|integer|min:1|max:365','weekday'=>'nullable|integer|min:1|max:7',
            'day_of_month'=>'nullable|integer|min:1|max:31','next_run_at'=>'nullable|date','is_active'=>'required|boolean',
            'checklist'=>'nullable|array','checklist.*'=>'nullable|string|max:255'
        ]);
        $access=app(AccessService::class);
        $this->assertReference($data['project_id']??null,'project',$orgId,'Проект');
        $this->assertReference($data['basis_id']??null,'basis',$orgId,'Основание');
        $this->assertReference($data['business_status_id']??null,'task_status',$orgId,'Статус');
        if(!empty($data['responsible_department_id']))abort_unless($access->departmentIds($request->user())->contains((int)$data['responsible_department_id']),403);
        if(empty($data['customer_type'])){$data['customer_type']=null;$data['customer_id']=null;}
        elseif($data['customer_type']==='organization'){abort_if(empty($data['customer_id']),422,'Выберите предприятие-заказчика');$this->assertReference($data['customer_id'],'organization',$orgId,'Заказчик');}
        else{abort_if(empty($data['customer_id']),422,'Выберите подразделение-заказчика');abort_unless($access->departmentIds($request->user())->contains((int)$data['customer_id']),403);}
        $data['title']=trim((string)($data['title']??''))?:null;
        $data['add_to_plan']=!empty($data['add_to_plan']);
        $checklist=array_values(array_filter(array_map('trim',$data['checklist']??[])));unset($data['checklist']);
        if($data['recurrence']==='none'){$data['next_run_at']=null;$data['weekday']=null;$data['day_of_month']=null;}else{if(empty($data['next_run_at']))$data['next_run_at']=now();if($data['recurrence']!=='weekly')$data['weekday']=null;if($data['recurrence']!=='monthly')$data['day_of_month']=null;}
        return [$data,$checklist];
    }

    private function refs(string $type,int $orgId){return ReferenceItem::withoutGlobalScope('organization')->where('organization_id',$orgId)->where('type',$type)->where('is_active',true)->orderBy('sort_order')->orderBy('name')->get(['id','code','name','system_key','color']);}
    private function assertReference($id,string $type,int $orgId,string $label):void{if(!$id)return;abort_unless(ReferenceItem::withoutGlobalScope('organization')->where('organization_id',$orgId)->whereKey((int)$id)->where('type',$type)->where('is_active',true)->exists(),422,$label.' не найден(о) в справочнике');}
    private function fallbackTitle($projectId,$basisId,int $orgId):string{if($projectId){$name=ReferenceItem::withoutGlobalScope('organization')->where('organization_id',$orgId)->whereKey((int)$projectId)->where('type','project')->value('name');if($name)return $name;}if($basisId){$name=ReferenceItem::withoutGlobalScope('organization')->where('organization_id',$orgId)->whereKey((int)$basisId)->where('type','basis')->value('name');if($name)return $name;}return 'Задача';}
    private function syncChecklist(TaskTemplate $template,array $checklist):void{$template->checklistItems()->delete();foreach($checklist as $i=>$title)TaskTemplateChecklistItem::create(['task_template_id'=>$template->id,'title'=>$title,'sort_order'=>$i]);}
    private function authorizeTemplate(Request $request,TaskTemplate $template):void{abort_unless($request->user()->isManager(),403);if(!$request->user()->isAdmin())abort_unless($template->created_by===$request->user()->id,403);}
}

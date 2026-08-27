<?php

namespace App\Http\Controllers;

use App\Models\CrmNotification;
use App\Models\Meeting;
use App\Models\MeetingItem;
use App\Models\TaskEvent;
use App\Models\User;
use App\Services\AccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class MeetingToolsController extends Controller
{
    public function updateItem(Request $request,Meeting $meeting,MeetingItem $item)
    {
        $this->authorizeMeeting($request,$meeting); abort_unless($item->meeting_id===$meeting->id,404); abort_if($meeting->status==='closed',422,'Закрытый протокол сначала нужно перевести в активный статус');
        $data=$request->validate(['instruction'=>'required|string|max:10000','assigned_to'=>'required|exists:users,id','due_at'=>'nullable|date','priority'=>['required',Rule::in(['low','normal','high','critical'])]]);
        $this->authorizeTarget($request,(int)$data['assigned_to']);
        DB::transaction(function()use($request,$item,$data,$meeting){$oldAssignee=$item->assigned_to;$item->update($data);if($item->task){$task=$item->task;$task->update(['assigned_to'=>$data['assigned_to'],'title'=>'Поручение по совещанию: '.str($data['instruction'])->limit(120),'description'=>$data['instruction'].'\n\nСовещание: '.$meeting->title.' от '.$meeting->held_at->format('d.m.Y H:i'),'priority'=>$data['priority'],'due_at'=>$data['due_at']??null]);TaskEvent::create(['task_id'=>$task->id,'user_id'=>$request->user()->id,'type'=>'updated','from_status'=>$task->status,'to_status'=>$task->status,'message'=>'Поручение изменено в протоколе совещания']);if($oldAssignee!=$data['assigned_to'])CrmNotification::create(['user_id'=>$data['assigned_to'],'task_id'=>$task->id,'type'=>'task_assigned','title'=>'Вам передано поручение по протоколу','body'=>$task->title,'url'=>route('tasks.page',['task'=>$task->id],false)]);}});
        return response()->json(['ok'=>true,'item'=>$item->fresh()->load(['assignee','task.assignee.department'])]);
    }

    public function destroyItem(Request $request,Meeting $meeting,MeetingItem $item)
    {
        $this->authorizeMeeting($request,$meeting); abort_unless($item->meeting_id===$meeting->id,404); abort_if($meeting->status==='closed',422,'Закрытый протокол нельзя изменять');
        DB::transaction(function()use($request,$item){if($item->task&&!in_array($item->task->status,['completed','cancelled'],true)){$task=$item->task;$from=$task->status;$task->update(['status'=>'cancelled','completed_at'=>now(),'result'=>'Отменено при удалении поручения из протокола']);TaskEvent::create(['task_id'=>$task->id,'user_id'=>$request->user()->id,'type'=>'cancelled','from_status'=>$from,'to_status'=>'cancelled','message'=>'Пункт удалён из протокола']);}$item->delete();});
        $this->renumber($meeting); return response()->json(['ok'=>true]);
    }

    public function reorder(Request $request,Meeting $meeting)
    {
        $this->authorizeMeeting($request,$meeting); abort_if($meeting->status==='closed',422,'Закрытый протокол нельзя изменять'); $data=$request->validate(['item_ids'=>'required|array','item_ids.*'=>'integer']);
        $ids=$meeting->items()->whereIn('id',$data['item_ids'])->pluck('id'); abort_unless($ids->count()===count($data['item_ids']),422,'Некорректный список поручений');
        DB::transaction(function()use($data,$meeting){foreach($data['item_ids'] as $i=>$id)$meeting->items()->whereKey($id)->update(['number'=>$i+1]);}); return response()->json(['ok'=>true]);
    }

    public function print(Request $request,Meeting $meeting)
    {
        $this->authorizeMeeting($request,$meeting,false); $meeting->load(['chairman','secretary','creator','participants.department','items.assignee.department','items.task.assignee.department']); return view('meetings.print',compact('meeting'));
    }

    private function renumber(Meeting $meeting):void{foreach($meeting->items()->orderBy('number')->orderBy('id')->get() as $i=>$item)$item->update(['number'=>$i+1]);}
    private function authorizeMeeting(Request $request,Meeting $meeting,bool $write=true):void{$u=$request->user();if($u->isAdmin())return;$ids=app(AccessService::class)->userIds($u,true);$ok=$meeting->created_by===$u->id||($u->isManager()&&$ids->contains((int)$meeting->created_by));if(!$write)$ok=$ok||$meeting->participants()->where('users.id',$u->id)->exists();abort_unless($ok,403);}
    private function authorizeTarget(Request $request,int $id):void{abort_unless(app(AccessService::class)->userIds($request->user(),true)->contains($id),403);}
}

<?php

namespace App\Http\Controllers;

use App\Models\CrmNotification;
use App\Models\Task;
use App\Models\TaskChecklistItem;
use App\Models\TaskComment;
use App\Models\TaskDeadlineChange;
use App\Models\TaskEvent;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TaskController extends Controller
{
    public function page(Request $request)
    {
        $users = $request->user()->isManager()
            ? User::where('is_active', true)->orderBy('last_name')->get()
            : collect([$request->user()]);
        return view('tasks.index', compact('users'));
    }

    public function index(Request $request)
    {
        $q = Task::with(['assignee.department','creator','plan']);
        if (!$request->user()->isManager()) $q->where('assigned_to', $request->user()->id);
        if ($request->filled('assigned_to') && $request->user()->isManager()) $q->where('assigned_to', $request->integer('assigned_to'));
        if ($request->filled('status')) $q->where('status', $request->status);
        if ($request->filled('priority')) $q->where('priority', $request->priority);
        if ($request->filled('q')) $q->where(function($w) use ($request){ $w->where('title','like','%'.$request->q.'%')->orWhere('description','like','%'.$request->q.'%'); });
        if ($request->boolean('overdue')) $q->whereNotIn('status',['completed','cancelled'])->where('due_at','<',now());
        return response()->json($q->orderByRaw("CASE WHEN due_at IS NULL THEN 1 ELSE 0 END")->orderBy('due_at')->latest('id')->paginate(30));
    }

    public function show(Request $request, Task $task)
    {
        $this->authorizeTask($request, $task);
        return response()->json($task->load([
            'assignee.department','creator','plan','comments.user','events.user','attachments.user',
            'checklistItems.completedBy','deadlineChanges.user'
        ]));
    }

    public function store(Request $request)
    {
        if (!$request->user()->isManager() && (int)$request->assigned_to !== $request->user()->id) abort(403);
        $data = $request->validate([
            'plan_id'=>'nullable|exists:plans,id','assigned_to'=>'required|exists:users,id','title'=>'required|string|max:255',
            'description'=>'nullable|string','priority'=>['required',Rule::in(['low','normal','high','critical'])],
            'due_at'=>'nullable|date'
        ]);
        $data['created_by'] = $request->user()->id;
        $data['status'] = 'new';
        $task = Task::create($data);
        $this->event($task, $request->user()->id, 'created', null, $task->status, 'Задача создана');
        if ($task->assigned_to !== $request->user()->id) $this->notify($task->assigned_to, $task, 'task_assigned', 'Новая задача', $task->title);
        return response()->json(['ok'=>true,'task'=>$task->load(['assignee','creator'])], 201);
    }

    public function update(Request $request, Task $task)
    {
        $this->authorizeTask($request, $task);
        $oldStatus = $task->status;
        $oldDueAt = $task->due_at?->copy();
        $data = $request->validate([
            'title'=>'sometimes|required|string|max:255','description'=>'nullable|string','priority'=>[Rule::in(['low','normal','high','critical'])],
            'progress'=>'nullable|integer|min:0|max:100','due_at'=>'nullable|date','result'=>'nullable|string',
            'deadline_reason'=>'nullable|string|max:5000'
        ]);

        if (array_key_exists('due_at', $data)) {
            $newDueAt = $data['due_at'] ? \Carbon\Carbon::parse($data['due_at']) : null;
            $changed = ($oldDueAt?->timestamp) !== ($newDueAt?->timestamp);
            if ($changed) {
                abort_unless($request->user()->isManager() || $task->created_by === $request->user()->id, 403);
                $reason = trim((string)($data['deadline_reason'] ?? ''));
                abort_if(mb_strlen($reason) < 3, 422, 'При изменении срока обязательно укажите причину');
                TaskDeadlineChange::create([
                    'task_id'=>$task->id,'user_id'=>$request->user()->id,'old_due_at'=>$oldDueAt,
                    'new_due_at'=>$newDueAt,'reason'=>$reason,
                ]);
                $this->event($task, $request->user()->id, 'deadline_changed', $task->status, $task->status,
                    'Срок изменён: '.($oldDueAt?->format('d.m.Y H:i') ?? 'не задан').' → '.($newDueAt?->format('d.m.Y H:i') ?? 'без срока').'. Причина: '.$reason);
            }
            unset($data['deadline_reason']);
        } else {
            unset($data['deadline_reason']);
        }

        if (isset($data['progress']) && $data['progress'] > 0 && !$task->started_at) $data['started_at'] = now();
        $task->update($data);
        $this->event($task, $request->user()->id, 'updated', $oldStatus, $task->status, 'Изменены данные задачи');
        return response()->json(['ok'=>true,'task'=>$task->fresh()->load(['assignee.department','creator','checklistItems','deadlineChanges.user'])]);
    }

    public function addChecklistItem(Request $request, Task $task)
    {
        $this->authorizeTask($request, $task);
        abort_unless($request->user()->isManager() || $task->created_by === $request->user()->id, 403);
        abort_if(in_array($task->status,['completed','cancelled'],true),422,'Нельзя менять чек-лист закрытой задачи');
        $data=$request->validate(['title'=>'required|string|max:255']);
        $item=TaskChecklistItem::create([
            'task_id'=>$task->id,'title'=>$data['title'],
            'sort_order'=>(int)$task->checklistItems()->max('sort_order')+1,
        ]);
        $this->recalculateChecklistProgress($task);
        $this->event($task,$request->user()->id,'checklist_added',$task->status,$task->status,'Добавлен пункт чек-листа: '.$item->title);
        return response()->json(['ok'=>true,'item'=>$item],201);
    }

    public function toggleChecklistItem(Request $request, Task $task, TaskChecklistItem $item)
    {
        $this->authorizeTask($request, $task);
        abort_unless($item->task_id===$task->id,404);
        abort_if(in_array($task->status,['completed','cancelled'],true),422,'Нельзя менять чек-лист закрытой задачи');
        $done=$request->boolean('is_done');
        $item->update([
            'is_done'=>$done,
            'completed_by'=>$done?$request->user()->id:null,
            'completed_at'=>$done?now():null,
        ]);
        $this->recalculateChecklistProgress($task);
        $this->event($task,$request->user()->id,'checklist_toggled',$task->status,$task->status,($done?'Выполнен: ':'Возвращён: ').$item->title);
        return response()->json(['ok'=>true,'item'=>$item->fresh(),'progress'=>$task->fresh()->progress]);
    }

    public function changeDeadline(Request $request, Task $task)
    {
        $this->authorizeTask($request, $task);
        abort_unless($request->user()->isManager() || $task->created_by === $request->user()->id, 403);
        $data=$request->validate(['due_at'=>'nullable|date','reason'=>'required|string|min:3|max:5000']);
        $old=$task->due_at?->copy();
        $new=$data['due_at']?\Carbon\Carbon::parse($data['due_at']):null;
        TaskDeadlineChange::create(['task_id'=>$task->id,'user_id'=>$request->user()->id,'old_due_at'=>$old,'new_due_at'=>$new,'reason'=>$data['reason']]);
        $task->update(['due_at'=>$new]);
        $this->event($task,$request->user()->id,'deadline_changed',$task->status,$task->status,
            'Срок изменён: '.($old?->format('d.m.Y H:i')??'не задан').' → '.($new?->format('d.m.Y H:i')??'без срока').'. Причина: '.$data['reason']);
        return response()->json(['ok'=>true,'task'=>$task->fresh(),'change'=>$task->deadlineChanges()->with('user')->first()]);
    }

    public function comment(Request $request, Task $task)
    {
        $this->authorizeTask($request, $task);
        $data = $request->validate(['body'=>'required|string|max:5000']);
        $comment = TaskComment::create(['task_id'=>$task->id,'user_id'=>$request->user()->id,'body'=>$data['body']]);
        $this->event($task, $request->user()->id, 'comment', $task->status, $task->status, 'Добавлен комментарий');
        $recipients = array_unique(array_filter([$task->assigned_to, $task->created_by]));
        foreach ($recipients as $userId) if ((int)$userId !== $request->user()->id) $this->notify((int)$userId, $task, 'task_comment', 'Новый комментарий к задаче', $task->title);
        return response()->json(['ok'=>true,'comment'=>$comment->load('user')], 201);
    }

    public function dashboardComplete(Request $request, Task $task)
    {
        abort_unless($task->assigned_to === $request->user()->id, 403);
        abort_if(in_array($task->status, ['completed','cancelled'], true), 422, 'Задача уже закрыта');
        abort_if($task->status === 'review', 422, 'Задача уже отправлена на проверку');
        $remaining=$task->checklistItems()->where('is_done',false)->count();
        abort_if($remaining>0,422,'Сначала выполните все пункты чек-листа');

        $data = $request->validate(['comment'=>'nullable|string|max:5000']);
        $message = trim((string)($data['comment'] ?? ''));
        $from = $task->status;

        if ($task->created_by === $request->user()->id) {
            $task->update(['status'=>'completed','progress'=>100,'started_at'=>$task->started_at ?: now(),'completed_at'=>now(),'result'=>$message !== '' ? $message : ($task->result ?: 'Выполнено')]);
            if ($message !== '') TaskComment::create(['task_id'=>$task->id,'user_id'=>$request->user()->id,'body'=>$message]);
            $this->event($task, $request->user()->id, 'completed_self', $from, 'completed', $message !== '' ? $message : 'Личная задача выполнена');
            return response()->json(['ok'=>true,'mode'=>'completed','task'=>$task->fresh()]);
        }

        abort_if($message === '', 422, 'Перед отправкой руководителю укажите краткий комментарий о выполнении');
        $task->update(['status'=>'review','progress'=>100,'started_at'=>$task->started_at ?: now(),'completed_at'=>null,'result'=>$message]);
        TaskComment::create(['task_id'=>$task->id,'user_id'=>$request->user()->id,'body'=>'Отчёт сотрудника: '.$message]);
        $this->event($task, $request->user()->id, 'submitted_for_review', $from, 'review', $message);
        $task->loadMissing('assignee');
        $recipients = array_unique(array_filter([$task->created_by, $task->assignee?->manager_id]));
        foreach ($recipients as $userId) if ((int)$userId !== $request->user()->id) $this->notify((int)$userId, $task, 'task_review', 'Задача ожидает проверки', $task->title);
        return response()->json(['ok'=>true,'mode'=>'review','task'=>$task->fresh()]);
    }

    public function submitReview(Request $request, Task $task)
    {
        abort_unless($task->assigned_to === $request->user()->id, 403);
        abort_unless(in_array($task->status,['new','in_progress'],true),422,'Эту задачу нельзя отправить на проверку');
        abort_if($task->checklistItems()->where('is_done',false)->exists(),422,'Сначала выполните все пункты чек-листа');
        $data = $request->validate(['result'=>'required|string|min:3|max:10000','progress'=>'nullable|integer|min:1|max:100']);
        $from = $task->status;
        $task->update(['result'=>$data['result'],'progress'=>100,'status'=>'review','started_at'=>$task->started_at ?: now(),'completed_at'=>null]);
        $this->event($task, $request->user()->id, 'submitted_for_review', $from, 'review', 'Сотрудник отправил отчёт на проверку');
        $task->loadMissing('assignee');
        $recipients = array_unique(array_filter([$task->created_by, $task->assignee?->manager_id]));
        foreach ($recipients as $userId) if ((int)$userId !== $request->user()->id) $this->notify((int)$userId, $task, 'task_review', 'Задача ожидает проверки', $task->title);
        return response()->json(['ok'=>true,'task'=>$task->fresh()]);
    }

    public function accept(Request $request, Task $task)
    {
        abort_unless($request->user()->isManager(), 403);
        abort_unless($task->status === 'review', 422, 'Задача не находится на проверке');
        $data = $request->validate(['message'=>'nullable|string|max:5000']);
        $task->update(['status'=>'completed','progress'=>100,'completed_at'=>now()]);
        if (!empty($data['message'])) TaskComment::create(['task_id'=>$task->id,'user_id'=>$request->user()->id,'body'=>$data['message']]);
        $this->event($task, $request->user()->id, 'accepted', 'review', 'completed', $data['message'] ?? 'Результат принят руководителем');
        $this->notify($task->assigned_to, $task, 'task_accepted', 'Задача принята', $task->title);
        return response()->json(['ok'=>true,'task'=>$task->fresh()]);
    }

    public function reject(Request $request, Task $task)
    {
        abort_unless($request->user()->isManager(), 403);
        abort_unless($task->status === 'review', 422, 'Задача не находится на проверке');
        $data = $request->validate(['message'=>'required|string|min:3|max:5000']);
        $task->update(['status'=>'in_progress','completed_at'=>null,'progress'=>min($task->progress, 99)]);
        TaskComment::create(['task_id'=>$task->id,'user_id'=>$request->user()->id,'body'=>'Возврат на доработку: '.$data['message']]);
        $this->event($task, $request->user()->id, 'rejected', 'review', 'in_progress', $data['message']);
        $this->notify($task->assigned_to, $task, 'task_rejected', 'Задача возвращена на доработку', $data['message']);
        return response()->json(['ok'=>true,'task'=>$task->fresh()]);
    }

    private function recalculateChecklistProgress(Task $task): void
    {
        $total=$task->checklistItems()->count();
        if ($total===0) return;
        $done=$task->checklistItems()->where('is_done',true)->count();
        $progress=(int)round(($done/$total)*100);
        $task->update(['progress'=>$progress,'started_at'=>$progress>0?($task->started_at?:now()):$task->started_at]);
    }

    private function authorizeTask(Request $request, Task $task): void
    {
        $u = $request->user();
        abort_unless($u->isManager() || $task->assigned_to === $u->id || $task->created_by === $u->id, 403);
    }

    private function event(Task $task, int $userId, string $type, ?string $from, ?string $to, ?string $message = null): void
    {
        TaskEvent::create(['task_id'=>$task->id,'user_id'=>$userId,'type'=>$type,'from_status'=>$from,'to_status'=>$to,'message'=>$message]);
    }

    private function notify(int $userId, Task $task, string $type, string $title, ?string $body = null): void
    {
        CrmNotification::create(['user_id'=>$userId,'task_id'=>$task->id,'type'=>$type,'title'=>$title,'body'=>$body,'url'=>route('tasks.page', ['task'=>$task->id], false)]);
    }
}

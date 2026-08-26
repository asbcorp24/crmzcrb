<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\TaskComment;
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
        return response()->json($task->load(['assignee.department','creator','plan','comments.user','events.user']));
    }

    public function store(Request $request)
    {
        if (!$request->user()->isManager() && (int)$request->assigned_to !== $request->user()->id) abort(403);
        $data = $request->validate([
            'plan_id'=>'nullable|exists:plans,id','assigned_to'=>'required|exists:users,id','title'=>'required|string|max:255',
            'description'=>'nullable|string','priority'=>['required',Rule::in(['low','normal','high','critical'])],
            'due_at'=>'nullable|date','status'=>['nullable',Rule::in(['new','in_progress','review','completed','cancelled'])]
        ]);
        $data['created_by'] = $request->user()->id;
        $task = Task::create($data);
        $this->event($task, $request->user()->id, 'created', null, $task->status, 'Задача создана');
        return response()->json(['ok'=>true,'task'=>$task->load(['assignee','creator'])], 201);
    }

    public function update(Request $request, Task $task)
    {
        $this->authorizeTask($request, $task);
        $oldStatus = $task->status;
        $data = $request->validate([
            'title'=>'sometimes|required|string|max:255','description'=>'nullable|string','priority'=>[Rule::in(['low','normal','high','critical'])],
            'progress'=>'nullable|integer|min:0|max:100','due_at'=>'nullable|date','result'=>'nullable|string'
        ]);
        if (isset($data['progress']) && $data['progress'] > 0 && !$task->started_at) $data['started_at'] = now();
        $task->update($data);
        $this->event($task, $request->user()->id, 'updated', $oldStatus, $task->status, 'Изменены данные задачи');
        return response()->json(['ok'=>true,'task'=>$task->fresh()->load(['assignee.department','creator'])]);
    }

    public function comment(Request $request, Task $task)
    {
        $this->authorizeTask($request, $task);
        $data = $request->validate(['body'=>'required|string|max:5000']);
        $comment = TaskComment::create(['task_id'=>$task->id,'user_id'=>$request->user()->id,'body'=>$data['body']]);
        $this->event($task, $request->user()->id, 'comment', $task->status, $task->status, 'Добавлен комментарий');
        return response()->json(['ok'=>true,'comment'=>$comment->load('user')], 201);
    }

    public function submitReview(Request $request, Task $task)
    {
        abort_unless($task->assigned_to === $request->user()->id, 403);
        $data = $request->validate(['result'=>'required|string|min:3|max:10000','progress'=>'nullable|integer|min:1|max:100']);
        $from = $task->status;
        $task->update(['result'=>$data['result'],'progress'=>$data['progress'] ?? max($task->progress, 100),'status'=>'review','started_at'=>$task->started_at ?: now(),'completed_at'=>null]);
        $this->event($task, $request->user()->id, 'submitted_for_review', $from, 'review', 'Сотрудник отправил отчёт на проверку');
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
        return response()->json(['ok'=>true,'task'=>$task->fresh()]);
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
}

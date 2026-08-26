<?php

namespace App\Http\Controllers;

use App\Models\CrmNotification;
use App\Models\Task;
use App\Models\TaskDelegation;
use App\Models\TaskEvent;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TaskDelegationController extends Controller
{
    public function store(Request $request, Task $task)
    {
        abort_unless($request->user()->isManager() || $task->created_by === $request->user()->id, 403);
        abort_if(in_array($task->status, ['completed','cancelled'], true), 422, 'Закрытую задачу нельзя передать');

        $data = $request->validate([
            'to_user_id'=>'required|exists:users,id|different:from_user_id',
            'reason'=>'required|string|min:3|max:5000',
        ]);
        $newUser = User::where('id', $data['to_user_id'])->where('is_active', true)->firstOrFail();
        $oldUserId = $task->assigned_to;
        abort_if($oldUserId === $newUser->id, 422, 'Этот сотрудник уже является исполнителем');

        if (!$request->user()->isAdmin()) {
            $allowed = $newUser->id === $request->user()->id || $newUser->manager_id === $request->user()->id;
            abort_unless($allowed, 403);
        }

        DB::transaction(function() use ($task,$request,$data,$newUser,$oldUserId) {
            TaskDelegation::create([
                'task_id'=>$task->id,'from_user_id'=>$oldUserId,'to_user_id'=>$newUser->id,
                'delegated_by'=>$request->user()->id,'reason'=>$data['reason'],
            ]);
            $task->update(['assigned_to'=>$newUser->id]);
            TaskEvent::create([
                'task_id'=>$task->id,'user_id'=>$request->user()->id,'type'=>'delegated',
                'from_status'=>$task->status,'to_status'=>$task->status,
                'message'=>'Задача передана другому исполнителю. Причина: '.$data['reason'],
            ]);
            CrmNotification::create([
                'user_id'=>$newUser->id,'task_id'=>$task->id,'type'=>'task_delegated',
                'title'=>'Вам передана задача','body'=>$task->title.' · '.$data['reason'],
                'url'=>route('tasks.page',['task'=>$task->id],false),
            ]);
        });

        return response()->json(['ok'=>true,'task'=>$task->fresh()->load('assignee')]);
    }

    public function history(Request $request, Task $task)
    {
        $u=$request->user();
        abort_unless($u->isManager() || $task->assigned_to===$u->id || $task->created_by===$u->id,403);
        return response()->json(TaskDelegation::with(['fromUser','toUser','delegatedByUser'])
            ->where('task_id',$task->id)->latest()->get());
    }
}

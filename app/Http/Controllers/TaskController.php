<?php

namespace App\Http\Controllers;

use App\Models\Task;
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
        return response()->json(['ok'=>true,'task'=>$task->load(['assignee','creator'])], 201);
    }

    public function update(Request $request, Task $task)
    {
        $user = $request->user();
        abort_unless($user->isManager() || $task->assigned_to === $user->id || $task->created_by === $user->id, 403);
        $data = $request->validate([
            'title'=>'sometimes|required|string|max:255','description'=>'nullable|string','priority'=>[Rule::in(['low','normal','high','critical'])],
            'status'=>[Rule::in(['new','in_progress','review','completed','cancelled'])],'progress'=>'nullable|integer|min:0|max:100',
            'due_at'=>'nullable|date','result'=>'nullable|string'
        ]);
        if (($data['status'] ?? null) === 'completed') { $data['progress']=100; $data['completed_at']=now(); }
        if (($data['status'] ?? null) === 'in_progress' && !$task->started_at) $data['started_at']=now();
        if (($data['status'] ?? null) !== 'completed' && array_key_exists('status',$data)) $data['completed_at']=null;
        $task->update($data);
        return response()->json(['ok'=>true,'task'=>$task->fresh()->load(['assignee.department','creator'])]);
    }
}

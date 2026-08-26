<?php

namespace App\Http\Controllers;

use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TaskController extends Controller
{
    public function index(Request $request)
    {
        $q = Task::with(['assignee.department','creator','plan']);
        if (!$request->user()->isManager()) $q->where('assigned_to', $request->user()->id);
        if ($request->filled('status')) $q->where('status', $request->status);
        if ($request->boolean('overdue')) $q->whereNotIn('status',['completed','cancelled'])->where('due_at','<',now());
        return response()->json($q->latest()->paginate(30));
    }

    public function store(Request $request)
    {
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
        $data = $request->validate([
            'title'=>'sometimes|required|string|max:255','description'=>'nullable|string','priority'=>[Rule::in(['low','normal','high','critical'])],
            'status'=>[Rule::in(['new','in_progress','review','completed','cancelled'])],'progress'=>'nullable|integer|min:0|max:100',
            'due_at'=>'nullable|date','result'=>'nullable|string'
        ]);
        if (($data['status'] ?? null) === 'completed') { $data['progress']=100; $data['completed_at']=now(); }
        if (($data['status'] ?? null) === 'in_progress' && !$task->started_at) $data['started_at']=now();
        $task->update($data);
        return response()->json(['ok'=>true,'task'=>$task->fresh()]);
    }
}

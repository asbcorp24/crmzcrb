<?php

namespace App\Http\Controllers;

use App\Models\CrmNotification;
use App\Models\EmployeeAbsence;
use App\Models\EmployeeSubstitution;
use App\Models\Task;
use App\Models\TaskDelegation;
use App\Models\TaskEvent;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AvailabilityController extends Controller
{
    public function page(Request $request)
    {
        abort_unless($request->user()->isManager(), 403);
        $users = $request->user()->isAdmin()
            ? User::where('is_active', true)->orderBy('last_name')->get()
            : User::where(function($q) use ($request) {
                $q->where('id', $request->user()->id)->orWhere('manager_id', $request->user()->id);
            })->where('is_active', true)->orderBy('last_name')->get();
        return view('availability.index', compact('users'));
    }

    public function events(Request $request)
    {
        abort_unless($request->user()->isManager(), 403);
        $start = $request->date('start')?->toDateString() ?? now()->startOfMonth()->toDateString();
        $end = $request->date('end')?->toDateString() ?? now()->endOfMonth()->toDateString();
        $ids = $request->user()->isAdmin()
            ? User::pluck('id')
            : User::where(function($q) use ($request) {
                $q->where('id', $request->user()->id)->orWhere('manager_id', $request->user()->id);
            })->pluck('id');

        $q = EmployeeAbsence::with(['user.department'])
            ->whereIn('user_id', $ids)
            ->where('date_from', '<=', $end)
            ->where('date_to', '>=', $start);
        if ($request->filled('user_id')) $q->where('user_id', $request->integer('user_id'));

        return response()->json($q->get()->map(function($a) {
            return [
                'id' => 'absence-'.$a->id,
                'title' => $a->user->full_name.' — '.$this->typeName($a->type),
                'start' => $a->date_from->toDateString(),
                'end' => $a->date_to->copy()->addDay()->toDateString(),
                'allDay' => true,
                'extendedProps' => [
                    'absence_id' => $a->id,
                    'user' => $a->user->full_name,
                    'department' => $a->user->department?->name,
                    'type' => $a->type,
                    'type_name' => $this->typeName($a->type),
                    'document_number' => $a->document_number,
                    'comment' => $a->comment,
                ],
            ];
        }));
    }

    public function storeAbsence(Request $request)
    {
        abort_unless($request->user()->isManager(), 403);
        $data = $request->validate([
            'user_id'=>'required|exists:users,id',
            'type'=>['required', Rule::in(['vacation','sick_leave','business_trip','training','other'])],
            'date_from'=>'required|date', 'date_to'=>'required|date|after_or_equal:date_from',
            'document_number'=>'nullable|string|max:100', 'comment'=>'nullable|string|max:3000',
        ]);
        $this->authorizeManagedUser($request, (int)$data['user_id']);
        $data['created_by'] = $request->user()->id;
        return response()->json(['ok'=>true,'absence'=>EmployeeAbsence::create($data)->load('user')], 201);
    }

    public function destroyAbsence(Request $request, EmployeeAbsence $absence)
    {
        abort_unless($request->user()->isManager(), 403);
        $this->authorizeManagedUser($request, $absence->user_id);
        $absence->delete();
        return response()->json(['ok'=>true]);
    }

    public function substitutions(Request $request)
    {
        abort_unless($request->user()->isManager(), 403);
        $ids = $request->user()->isAdmin() ? User::pluck('id') : User::where('manager_id',$request->user()->id)->pluck('id')->push($request->user()->id);
        return response()->json(EmployeeSubstitution::with(['absentUser.department','substituteUser.department'])
            ->whereIn('absent_user_id',$ids)->orderByDesc('date_from')->limit(100)->get());
    }

    public function storeSubstitution(Request $request)
    {
        abort_unless($request->user()->isManager(), 403);
        $data=$request->validate([
            'absent_user_id'=>'required|exists:users,id','substitute_user_id'=>'required|different:absent_user_id|exists:users,id',
            'date_from'=>'required|date','date_to'=>'required|date|after_or_equal:date_from','comment'=>'nullable|string|max:3000',
            'move_tasks'=>'nullable|boolean'
        ]);
        $this->authorizeManagedUser($request,(int)$data['absent_user_id']);
        $moveTasks=(bool)($data['move_tasks'] ?? false);
        unset($data['move_tasks']);
        $data['created_by']=$request->user()->id;

        $result=DB::transaction(function() use ($data,$moveTasks,$request) {
            $sub=EmployeeSubstitution::create($data);
            $moved=0;
            if ($moveTasks) {
                $tasks=Task::where('assigned_to',$data['absent_user_id'])
                    ->whereNotIn('status',['completed','cancelled'])
                    ->whereBetween('due_at',[$data['date_from'].' 00:00:00',$data['date_to'].' 23:59:59'])
                    ->get();
                foreach($tasks as $task) {
                    TaskDelegation::create([
                        'task_id'=>$task->id,'from_user_id'=>$data['absent_user_id'],'to_user_id'=>$data['substitute_user_id'],
                        'delegated_by'=>$request->user()->id,'reason'=>'Автоматическая передача на период замещения',
                    ]);
                    $task->update(['assigned_to'=>$data['substitute_user_id']]);
                    TaskEvent::create([
                        'task_id'=>$task->id,'user_id'=>$request->user()->id,'type'=>'delegated',
                        'from_status'=>$task->status,'to_status'=>$task->status,
                        'message'=>'Передано заместителю на период отсутствия сотрудника',
                    ]);
                    CrmNotification::create([
                        'user_id'=>$data['substitute_user_id'],'task_id'=>$task->id,'type'=>'task_delegated',
                        'title'=>'Задача передана вам на замещение','body'=>$task->title,
                        'url'=>route('tasks.page',['task'=>$task->id],false),
                    ]);
                    $moved++;
                }
            }
            return [$sub,$moved];
        });

        return response()->json(['ok'=>true,'substitution'=>$result[0]->load(['absentUser','substituteUser']),'moved_tasks'=>$result[1]],201);
    }

    public function check(Request $request)
    {
        $data=$request->validate(['user_id'=>'required|exists:users,id','date'=>'required|date']);
        $date=$data['date'];
        $absence=EmployeeAbsence::with('user')->where('user_id',$data['user_id'])->whereDate('date_from','<=',$date)->whereDate('date_to','>=',$date)->first();
        $substitution=$absence ? EmployeeSubstitution::with('substituteUser')->where('absent_user_id',$data['user_id'])->whereDate('date_from','<=',$date)->whereDate('date_to','>=',$date)->latest()->first() : null;
        return response()->json([
            'absent'=>(bool)$absence,
            'absence'=>$absence,
            'type_name'=>$absence?$this->typeName($absence->type):null,
            'substitute'=>$substitution?->substituteUser,
        ]);
    }

    private function authorizeManagedUser(Request $request, int $userId): void
    {
        if ($request->user()->isAdmin()) return;
        abort_unless($userId === $request->user()->id || User::where('id',$userId)->where('manager_id',$request->user()->id)->exists(),403);
    }

    private function typeName(string $type): string
    {
        return ['vacation'=>'Отпуск','sick_leave'=>'Больничный','business_trip'=>'Командировка','training'=>'Обучение','other'=>'Другое'][$type] ?? $type;
    }
}

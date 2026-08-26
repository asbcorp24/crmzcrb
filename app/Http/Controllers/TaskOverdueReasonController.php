<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\TaskEvent;
use App\Models\TaskOverdueReason;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TaskOverdueReasonController extends Controller
{
    public function store(Request $request, Task $task)
    {
        abort_unless($task->assigned_to === $request->user()->id, 403);
        abort_unless($task->is_overdue, 422, 'Причина указывается только для просроченной задачи');

        $data=$request->validate([
            'reason_code'=>['required',Rule::in(['waiting_data','technical','dependency','workload','other'])],
            'comment'=>'nullable|string|max:5000',
        ]);
        if ($data['reason_code']==='other') {
            abort_if(mb_strlen(trim((string)($data['comment']??'')))<3,422,'Для причины «Другое» добавьте пояснение');
        }

        $reason=TaskOverdueReason::create([
            'task_id'=>$task->id,'user_id'=>$request->user()->id,
            'reason_code'=>$data['reason_code'],'comment'=>$data['comment']??null,
        ]);
        TaskEvent::create([
            'task_id'=>$task->id,'user_id'=>$request->user()->id,'type'=>'overdue_reason',
            'from_status'=>$task->status,'to_status'=>$task->status,
            'message'=>'Указана причина просрочки: '.$this->label($data['reason_code']).(!empty($data['comment'])?'. '.$data['comment']:''),
        ]);

        return response()->json(['ok'=>true,'reason'=>$reason->load('user')],201);
    }

    private function label(string $code): string
    {
        return [
            'waiting_data'=>'Ожидаю данные','technical'=>'Техническая проблема','dependency'=>'Зависимость от другого подразделения',
            'workload'=>'Высокая загрузка','other'=>'Другое',
        ][$code]??$code;
    }
}

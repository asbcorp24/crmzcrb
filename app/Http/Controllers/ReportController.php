<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Meeting;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function page(Request $request)
    {
        abort_unless($request->user()->isManager(), 403);
        $users = $this->accessibleUsers($request)->get();
        $departments = Department::where('is_active', true)->orderBy('name')->get();
        return view('reports.index', compact('users', 'departments'));
    }

    public function data(Request $request)
    {
        abort_unless($request->user()->isManager(), 403);
        [$type, $from, $to] = $this->filters($request);
        $result = $this->buildReport($request, $type, $from, $to);
        return response()->json($result);
    }

    public function csv(Request $request): StreamedResponse
    {
        abort_unless($request->user()->isManager(), 403);
        [$type, $from, $to] = $this->filters($request);
        $report = $this->buildReport($request, $type, $from, $to);
        $name = 'crm_report_'.$type.'_'.$from->format('Y-m-d').'_'.$to->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($report) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $report['headers'], ';');
            foreach ($report['rows'] as $row) {
                $line = [];
                foreach ($report['keys'] as $key) $line[] = $row[$key] ?? '';
                fputcsv($out, $line, ';');
            }
            fclose($out);
        }, $name, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function print(Request $request)
    {
        abort_unless($request->user()->isManager(), 403);
        [$type, $from, $to] = $this->filters($request);
        $report = $this->buildReport($request, $type, $from, $to);
        return view('reports.print', compact('report', 'type', 'from', 'to'));
    }

    private function filters(Request $request): array
    {
        $type = in_array($request->get('type'), ['tasks','employees','departments','meetings'], true) ? $request->get('type') : 'tasks';
        $from = $request->date('date_from')?->startOfDay() ?? now()->startOfMonth();
        $to = $request->date('date_to')?->endOfDay() ?? now()->endOfMonth();
        abort_if($to->lt($from), 422, 'Конечная дата не может быть раньше начальной');
        return [$type, $from, $to];
    }

    private function buildReport(Request $request, string $type, $from, $to): array
    {
        return match ($type) {
            'employees' => $this->employeesReport($request, $from, $to),
            'departments' => $this->departmentsReport($request, $from, $to),
            'meetings' => $this->meetingsReport($request, $from, $to),
            default => $this->tasksReport($request, $from, $to),
        };
    }

    private function taskQuery(Request $request, $from, $to): Builder
    {
        $ids = $this->accessibleUsers($request)->pluck('id');
        $q = Task::with(['assignee.department','creator','overdueReasons'])
            ->whereIn('assigned_to', $ids)
            ->where(function ($w) use ($from, $to) {
                $w->whereBetween('due_at', [$from, $to])
                  ->orWhereBetween('completed_at', [$from, $to])
                  ->orWhereBetween('created_at', [$from, $to]);
            });
        if ($request->filled('user_id')) $q->where('assigned_to', $request->integer('user_id'));
        if ($request->filled('department_id')) $q->whereHas('assignee', fn($u) => $u->where('department_id', $request->integer('department_id')));
        if ($request->filled('status')) $q->where('status', $request->status);
        return $q;
    }

    private function tasksReport(Request $request, $from, $to): array
    {
        $tasks = $this->taskQuery($request, $from, $to)->orderBy('due_at')->get();
        $rows = $tasks->map(function (Task $t) {
            $reason = $t->overdueReasons->first();
            return [
                'id' => $t->id,
                'employee' => $t->assignee?->full_name,
                'department' => $t->assignee?->department?->name,
                'title' => $t->title,
                'status' => $this->statusName($t->status),
                'priority' => $this->priorityName($t->priority),
                'progress' => $t->progress.'%',
                'due_at' => $t->due_at?->format('d.m.Y H:i'),
                'completed_at' => $t->completed_at?->format('d.m.Y H:i'),
                'overdue' => $t->is_overdue ? 'Да' : 'Нет',
                'overdue_reason' => $reason ? $this->overdueReasonName($reason->reason_type).($reason->comment ? ': '.$reason->comment : '') : '',
            ];
        });
        return $this->pack('Исполнение задач', ['ID','Сотрудник','Подразделение','Задача','Статус','Приоритет','Прогресс','Срок','Выполнено','Просрочено','Причина просрочки'], ['id','employee','department','title','status','priority','progress','due_at','completed_at','overdue','overdue_reason'], $rows, [
            'Всего задач' => $tasks->count(),
            'Выполнено' => $tasks->where('status','completed')->count(),
            'На проверке' => $tasks->where('status','review')->count(),
            'Просрочено' => $tasks->filter(fn($t) => $t->is_overdue)->count(),
        ]);
    }

    private function employeesReport(Request $request, $from, $to): array
    {
        $users = $this->accessibleUsers($request)->with('department')
            ->when($request->filled('user_id'), fn($q) => $q->where('id',$request->integer('user_id')))
            ->when($request->filled('department_id'), fn($q) => $q->where('department_id',$request->integer('department_id')))
            ->get();
        $tasks = $this->taskQuery($request, $from, $to)->get()->groupBy('assigned_to');
        $rows = $users->map(function ($u) use ($tasks) {
            $set = $tasks->get($u->id, collect());
            $total = $set->count();
            $done = $set->where('status','completed')->count();
            return [
                'employee'=>$u->full_name,'department'=>$u->department?->name,'position'=>$u->position,
                'total'=>$total,'completed'=>$done,'review'=>$set->where('status','review')->count(),
                'overdue'=>$set->filter(fn($t)=>$t->is_overdue)->count(),
                'percent'=>$total ? round($done/$total*100).'%' : '—',
            ];
        });
        return $this->pack('Исполнение по сотрудникам', ['Сотрудник','Подразделение','Должность','Задач','Выполнено','На проверке','Просрочено','Исполнение'], ['employee','department','position','total','completed','review','overdue','percent'], $rows, ['Сотрудников'=>$rows->count(),'Всего задач'=>$rows->sum('total'),'Просрочено'=>$rows->sum('overdue')]);
    }

    private function departmentsReport(Request $request, $from, $to): array
    {
        $tasks = $this->taskQuery($request, $from, $to)->get();
        $groups = $tasks->groupBy(fn($t) => $t->assignee?->department?->name ?: 'Без подразделения');
        $rows = $groups->map(function ($set, $name) {
            $total=$set->count();$done=$set->where('status','completed')->count();
            return ['department'=>$name,'employees'=>$set->pluck('assigned_to')->unique()->count(),'total'=>$total,'completed'=>$done,'review'=>$set->where('status','review')->count(),'overdue'=>$set->filter(fn($t)=>$t->is_overdue)->count(),'percent'=>$total?round($done/$total*100).'%':'—'];
        })->values();
        return $this->pack('Исполнение по подразделениям', ['Подразделение','Сотрудников с задачами','Задач','Выполнено','На проверке','Просрочено','Исполнение'], ['department','employees','total','completed','review','overdue','percent'], $rows, ['Подразделений'=>$rows->count(),'Всего задач'=>$rows->sum('total'),'Просрочено'=>$rows->sum('overdue')]);
    }

    private function meetingsReport(Request $request, $from, $to): array
    {
        $userIds = $this->accessibleUsers($request)->pluck('id');
        $q = Meeting::with(['items.task.assignee.department'])->whereBetween('held_at', [$from,$to]);
        if (!$request->user()->isAdmin()) $q->where('created_by',$request->user()->id);
        $meetings=$q->orderBy('held_at')->get();
        $rows=collect();
        foreach($meetings as $m) foreach($m->items as $i) {
            $t=$i->task; if(!$t || !$userIds->contains($t->assigned_to)) continue;
            if($request->filled('user_id') && $t->assigned_to!=$request->integer('user_id')) continue;
            if($request->filled('department_id') && $t->assignee?->department_id!=$request->integer('department_id')) continue;
            if($request->filled('status') && $t->status!==$request->status) continue;
            $rows->push(['meeting'=>$m->title,'held_at'=>$m->held_at->format('d.m.Y H:i'),'number'=>$i->number,'instruction'=>$i->instruction,'employee'=>$t->assignee?->full_name,'department'=>$t->assignee?->department?->name,'due_at'=>$t->due_at?->format('d.m.Y H:i'),'status'=>$this->statusName($t->status),'progress'=>$t->progress.'%','overdue'=>$t->is_overdue?'Да':'Нет']);
        }
        return $this->pack('Исполнение протоколов совещаний', ['Совещание','Дата','№','Поручение','Исполнитель','Подразделение','Срок','Статус','Прогресс','Просрочено'], ['meeting','held_at','number','instruction','employee','department','due_at','status','progress','overdue'], $rows, ['Совещаний'=>$meetings->count(),'Поручений'=>$rows->count(),'Просрочено'=>$rows->where('overdue','Да')->count()]);
    }

    private function accessibleUsers(Request $request): Builder
    {
        return User::query()->where('is_active', true)
            ->when(!$request->user()->isAdmin(), fn($q) => $q->where(function($w) use($request){$w->where('id',$request->user()->id)->orWhere('manager_id',$request->user()->id);}));
    }

    private function pack(string $title, array $headers, array $keys, Collection $rows, array $summary): array
    { return compact('title','headers','keys','rows','summary'); }
    private function statusName(string $s): string { return ['new'=>'Новая','in_progress'=>'В работе','review'=>'На проверке','completed'=>'Выполнено','cancelled'=>'Отменено'][$s]??$s; }
    private function priorityName(string $p): string { return ['low'=>'Низкий','normal'=>'Обычный','high'=>'Высокий','critical'=>'Критический'][$p]??$p; }
    private function overdueReasonName(string $r): string { return ['waiting_data'=>'Ожидаю данные','technical'=>'Техническая проблема','dependency'=>'Зависимость от другого подразделения','workload'=>'Высокая загрузка','other'=>'Другое'][$r]??$r; }
}

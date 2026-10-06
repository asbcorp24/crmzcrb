<?php

namespace App\Http\Controllers;

use App\Models\CrmNotification;
use App\Models\Department;
use App\Models\ProductionMeeting;
use App\Models\ProductionMeetingItem;
use App\Models\Task;
use App\Models\TaskEvent;
use App\Models\User;
use App\Services\AccessService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ProductionMeetingController extends Controller
{
    public function page(Request $request)
    {
        $this->authorizeManager($request);
        $access = app(AccessService::class);
        $departmentIds = $access->departmentIds($request->user());
        $userIds = $access->userIds($request->user(), true);

        $departments = Department::whereIn('id', $departmentIds)
            ->where('is_active', true)->orderBy('name')->get(['id','name','short_name']);

        $managers = User::with('department:id,name')
            ->whereIn('id', $userIds)->where('is_active', true)->whereNull('archived_at')
            ->whereIn('role', ['manager','admin'])
            ->orderBy('last_name')->orderBy('first_name')->get();

        $departmentHeads = [];
        foreach ($departments as $department) {
            $head = $managers->first(function ($user) use ($department) {
                return (int)$user->department_id === (int)$department->id && $user->role === 'manager';
            });
            if (!$head) {
                $head = $managers->first(function ($user) use ($department) {
                    return (int)$user->department_id === (int)$department->id;
                });
            }
            if ($head) $departmentHeads[(string)$department->id] = (int)$head->id;
        }

        return view('production_meetings.index', compact('departments','managers','departmentHeads'));
    }

    public function index(Request $request)
    {
        $this->authorizeManager($request);
        $rows = ProductionMeeting::with(['chairman:id,last_name,first_name,middle_name','creator:id,last_name,first_name,middle_name'])
            ->withCount('items')->latest('held_at')->latest('id')->get();
        return response()->json($rows);
    }

    public function store(Request $request)
    {
        $this->authorizeManager($request);
        $data = $this->validateMeeting($request);
        $data['organization_id'] = $request->user()->organization_id;
        $data['created_by'] = $request->user()->id;
        $data['status'] = 'draft';
        $meeting = ProductionMeeting::create($data);
        return response()->json(['ok'=>true,'meeting'=>$this->loadMeeting($meeting)], 201);
    }

    public function show(Request $request, ProductionMeeting $productionMeeting)
    {
        $this->authorizeManager($request);
        return response()->json($this->loadMeeting($productionMeeting));
    }

    public function taskOptions(Request $request)
    {
        $this->authorizeManager($request);
        $access = app(AccessService::class);
        $userIds = $access->userIds($request->user(), true);
        $q = Task::with(['assignee:id,department_id,last_name,first_name,middle_name,role','assignee.department:id,name,short_name'])
            ->whereNull('archived_at')
            ->where(function ($w) use ($userIds, $request) {
                $w->whereIn('assigned_to', $userIds)->orWhere('created_by', $request->user()->id);
            })
            ->whereNotIn('id', ProductionMeetingItem::whereNotNull('task_id')->pluck('task_id'));

        if ($request->filled('q')) {
            $term = trim((string)$request->q);
            $q->where(function ($w) use ($term) {
                $w->where('title', 'like', '%'.$term.'%');
                if (ctype_digit($term)) $w->orWhere('id', (int)$term);
            });
        }

        $rows = $q->orderByDesc('id')->limit(50)->get();

        return response()->json($rows->map(function (Task $task) {
            return [
                'id' => $task->id,
                'title' => $task->title,
                'status' => $task->status,
                'priority' => $task->priority,
                'start_at' => optional($task->start_at ?: $task->started_at)->toDateString(),
                'due_at' => optional($task->due_at)->toDateString(),
                'responsible_department_id' => $task->responsible_department_id ?: $task->assignee?->department_id,
                'assignee' => $task->assignee ? [
                    'id' => $task->assignee->id,
                    'full_name' => $task->assignee->full_name,
                    'role' => $task->assignee->role,
                    'department_id' => $task->assignee->department_id,
                    'department' => $task->assignee->department ? [
                        'id' => $task->assignee->department->id,
                        'name' => $task->assignee->department->name,
                        'short_name' => $task->assignee->department->short_name,
                    ] : null,
                ] : null,
            ];
        })->values());
    }

    public function attachExistingTask(Request $request, ProductionMeeting $productionMeeting)
    {
        $this->authorizeManager($request);
        $data = $request->validate(['task_id' => 'required|integer']);

        $access = app(AccessService::class);
        $userIds = $access->userIds($request->user(), true);
        $task = Task::with('assignee')->whereKey((int)$data['task_id'])
            ->whereNull('archived_at')
            ->where(function ($w) use ($userIds, $request) {
                $w->whereIn('assigned_to', $userIds)->orWhere('created_by', $request->user()->id);
            })->firstOrFail();

        abort_if(
            ProductionMeetingItem::where('task_id', $task->id)->exists(),
            422,
            'Эта задача уже добавлена в производственное совещание.'
        );

        $departmentId = (int)($task->responsible_department_id ?: ($task->assignee?->department_id ?: 0));
        abort_if(!$departmentId, 422, 'У выбранной задачи невозможно определить ответственное подразделение.');
        abort_unless($access->departmentIds($request->user())->contains($departmentId), 403);

        $coexecutorId = 0;
        if ($task->assignee && in_array($task->assignee->role, ['manager','admin'], true)
            && $userIds->contains((int)$task->assignee->id)) {
            $coexecutorId = (int)$task->assignee->id;
        }
        if (!$coexecutorId) {
            $coexecutorId = (int)User::whereIn('id', $userIds)
                ->where('department_id', $departmentId)
                ->where('is_active', true)
                ->whereNull('archived_at')
                ->whereIn('role', ['manager','admin'])
                ->orderByRaw("CASE WHEN role = 'manager' THEN 0 ELSE 1 END")
                ->value('id');
        }
        abort_if(!$coexecutorId, 422, 'Для подразделения выбранной задачи не найден доступный руководитель.');

        $status = [
            'new' => 'pending',
            'in_progress' => 'in_progress',
            'review' => 'in_progress',
            'completed' => 'completed',
            'cancelled' => 'cancelled',
        ][$task->status] ?? 'pending';

        $itemData = [
            'production_meeting_id' => $productionMeeting->id,
            'number' => ((int)$productionMeeting->items()->max('number')) + 1,
            'instruction' => $task->title,
            'responsible_department_id' => $departmentId,
            'coexecutor_id' => $coexecutorId,
            'start_at' => $task->start_at ? $task->start_at->toDateString() : ($task->started_at ? $task->started_at->toDateString() : null),
            'due_at' => $task->due_at ? $task->due_at->toDateString() : null,
            'status' => $status,
            'task_id' => $task->id,
            'task_created_from_meeting' => false,
            'created_by' => $request->user()->id,
        ];
        $this->fillDuration($itemData);

        $item = ProductionMeetingItem::create($itemData);

        TaskEvent::create([
            'task_id' => $task->id,
            'user_id' => $request->user()->id,
            'type' => 'production_meeting_linked',
            'from_status' => $task->status,
            'to_status' => $task->status,
            'message' => 'Задача добавлена в производственное совещание «'.$productionMeeting->title.'»',
        ]);

        return response()->json([
            'ok' => true,
            'item' => $item->fresh()->load(['department','coexecutor','task']),
        ], 201);
    }

    public function update(Request $request, ProductionMeeting $productionMeeting)
    {
        $this->authorizeManager($request);
        $data = $this->validateMeeting($request);
        $productionMeeting->update($data);
        return response()->json(['ok'=>true,'meeting'=>$this->loadMeeting($productionMeeting)]);
    }

    public function storeItem(Request $request, ProductionMeeting $productionMeeting)
    {
        $this->authorizeManager($request);
        $data = $this->validateItem($request);
        $data['production_meeting_id'] = $productionMeeting->id;
        $data['created_by'] = $request->user()->id;
        $data['number'] = ((int)$productionMeeting->items()->max('number')) + 1;
        $this->fillDuration($data);
        $this->authorizeItemTargets($request, $data);

        $item = ProductionMeetingItem::create($data);
        if ($productionMeeting->status === 'protocol') $this->syncTask($request, $productionMeeting, $item);

        return response()->json(['ok'=>true,'item'=>$item->fresh()->load(['department','coexecutor','task'])], 201);
    }

    public function updateItem(Request $request, ProductionMeeting $productionMeeting, ProductionMeetingItem $item)
    {
        $this->authorizeManager($request);
        abort_unless((int)$item->production_meeting_id === (int)$productionMeeting->id, 404);
        $data = $this->validateItem($request);
        $this->fillDuration($data);
        $this->authorizeItemTargets($request, $data);
        $oldAssignee = (int)$item->coexecutor_id;
        $item->update($data);

        if ($productionMeeting->status === 'protocol') {
            $this->syncTask($request, $productionMeeting, $item, $oldAssignee);
        }

        return response()->json(['ok'=>true,'item'=>$item->fresh()->load(['department','coexecutor','task'])]);
    }

    public function destroyItem(Request $request, ProductionMeeting $productionMeeting, ProductionMeetingItem $item)
    {
        $this->authorizeManager($request);
        abort_unless((int)$item->production_meeting_id === (int)$productionMeeting->id, 404);

        DB::transaction(function () use ($request, $item) {
            if ($item->task_id && $item->task_created_from_meeting) {
                $task = Task::find($item->task_id);
                if ($task && !in_array($task->status, ['completed','cancelled'], true)) {
                    $from = $task->status;
                    $task->update(['status'=>'cancelled']);
                    TaskEvent::create([
                        'task_id'=>$task->id,'user_id'=>$request->user()->id,'type'=>'cancelled',
                        'from_status'=>$from,'to_status'=>'cancelled',
                        'message'=>'Пункт удалён из протокола производственного совещания',
                    ]);
                }
            }
            $item->delete();
        });

        $this->renumber($productionMeeting);
        return response()->json(['ok'=>true]);
    }

    public function generateProtocol(Request $request, ProductionMeeting $productionMeeting)
    {
        $this->authorizeManager($request);
        abort_if($productionMeeting->items()->count() === 0, 422, 'Добавьте хотя бы одно мероприятие.');

        $items = $productionMeeting->items()->get();
        foreach ($items as $item) {
            abort_if(!$item->responsible_department_id, 422, 'У пункта №'.$item->number.' не выбрано ответственное подразделение.');
            abort_if(!$item->coexecutor_id, 422, 'У пункта №'.$item->number.' не выбран соисполнитель/руководитель.');
            abort_if(!$item->due_at, 422, 'У пункта №'.$item->number.' не указан срок окончания.');
        }

        DB::transaction(function () use ($request, $productionMeeting, $items) {
            if (!$productionMeeting->protocol_number) {
                $productionMeeting->protocol_number = (string)$productionMeeting->id;
            }
            $productionMeeting->status = 'protocol';
            $productionMeeting->protocol_generated_at = now();
            $productionMeeting->save();

            foreach ($items as $item) $this->syncTask($request, $productionMeeting, $item);
        });

        return response()->json(['ok'=>true,'meeting'=>$this->loadMeeting($productionMeeting->fresh())]);
    }

    public function print(Request $request, ProductionMeeting $productionMeeting)
    {
        $this->authorizeManager($request);
        $productionMeeting->load(['chairman','secretary','items.department','items.coexecutor','items.task']);
        return view('production_meetings.print', ['meeting'=>$productionMeeting]);
    }

    private function validateMeeting(Request $request): array
    {
        return $request->validate([
            'title'=>'required|string|max:255',
            'protocol_number'=>'nullable|string|max:50',
            'held_at'=>'nullable|date',
            'chairman_id'=>'nullable|integer',
            'secretary_id'=>'nullable|integer',
            'notes'=>'nullable|string|max:10000',
        ]);
    }

    private function validateItem(Request $request): array
    {
        return $request->validate([
            'instruction'=>'required|string|max:10000',
            'responsible_department_id'=>'required|integer',
            'coexecutor_id'=>'required|integer',
            'start_at'=>'nullable|date',
            'due_at'=>'required|date',
            'status'=>['required', Rule::in(['pending','in_progress','completed','cancelled'])],
        ]);
    }

    private function authorizeItemTargets(Request $request, array $data): void
    {
        $access = app(AccessService::class);
        abort_unless($access->departmentIds($request->user())->contains((int)$data['responsible_department_id']), 403);
        abort_unless($access->userIds($request->user(), true)->contains((int)$data['coexecutor_id']), 403);
        abort_unless(User::whereKey($data['coexecutor_id'])->where('is_active', true)->whereNull('archived_at')->exists(), 422, 'Соисполнитель недоступен.');
    }

    private function fillDuration(array &$data): void
    {
        $data['duration_days'] = null;
        if (!empty($data['start_at']) && !empty($data['due_at'])) {
            $start = Carbon::parse($data['start_at'])->startOfDay();
            $finish = Carbon::parse($data['due_at'])->startOfDay();
            abort_if($finish->lt($start), 422, 'Дата окончания не может быть раньше даты начала.');
            $data['duration_days'] = $start->diffInDays($finish);
        }
    }

    private function syncTask(Request $request, ProductionMeeting $meeting, ProductionMeetingItem $item, int $oldAssignee = 0): void
    {
        $item->loadMissing(['department','coexecutor']);
        $description = 'Поручение по протоколу производственного совещания №'.($meeting->protocol_number ?: $meeting->id).'.';
        if ($meeting->held_at) $description .= ' Дата совещания: '.$meeting->held_at->format('d.m.Y').'.';
        if ($item->department) $description .= ' Ответственное подразделение: '.$item->department->name.'.';
        if ($item->start_at) $description .= ' Плановое начало: '.$item->start_at->format('d.m.Y').'.';

        $taskStatus = [
            'pending'=>'new',
            'in_progress'=>'in_progress',
            'completed'=>'completed',
            'cancelled'=>'cancelled',
        ][$item->status] ?? 'new';

        $task = $item->task_id ? Task::find($item->task_id) : null;
        if (!$task) {
            $task = Task::create([
                'organization_id'=>$request->user()->organization_id,
                'created_by'=>$request->user()->id,
                'assigned_to'=>$item->coexecutor_id,
                'title'=>$item->instruction,
                'description'=>$description,
                'priority'=>'normal',
                'status'=>$taskStatus,
                'progress'=>$taskStatus === 'completed' ? 100 : 0,
                'due_at'=>$item->due_at ? $item->due_at->copy()->endOfDay() : null,
                'completed_at'=>$taskStatus === 'completed' ? now() : null,
            ]);
            TaskEvent::create([
                'task_id'=>$task->id,'user_id'=>$request->user()->id,'type'=>'created',
                'to_status'=>$task->status,'message'=>'Создано из протокола производственного совещания №'.($meeting->protocol_number ?: $meeting->id),
            ]);
            $item->update(['task_id'=>$task->id, 'task_created_from_meeting'=>true]);
            if ((int)$item->coexecutor_id !== (int)$request->user()->id) {
                CrmNotification::create([
                    'user_id'=>$item->coexecutor_id,'task_id'=>$task->id,'type'=>'task_assigned',
                    'title'=>'Поручение производственного совещания','body'=>$task->title,
                    'url'=>route('tasks.page',['task'=>$task->id],false),
                ]);
            }
            return;
        }

        if (!$item->task_created_from_meeting) {
            return;
        }

        $fromStatus = $task->status;
        $task->update([
            'assigned_to'=>$item->coexecutor_id,
            'title'=>$item->instruction,
            'description'=>$description,
            'status'=>$taskStatus,
            'progress'=>$taskStatus === 'completed' ? 100 : ($taskStatus === 'new' ? 0 : $task->progress),
            'due_at'=>$item->due_at ? $item->due_at->copy()->endOfDay() : null,
            'completed_at'=>$taskStatus === 'completed' ? ($task->completed_at ?: now()) : null,
        ]);
        TaskEvent::create([
            'task_id'=>$task->id,'user_id'=>$request->user()->id,'type'=>'updated',
            'from_status'=>$fromStatus,'to_status'=>$taskStatus,
            'message'=>'Синхронизировано с протоколом производственного совещания',
        ]);

        if ($oldAssignee && $oldAssignee !== (int)$item->coexecutor_id && (int)$item->coexecutor_id !== (int)$request->user()->id) {
            CrmNotification::create([
                'user_id'=>$item->coexecutor_id,'task_id'=>$task->id,'type'=>'task_assigned',
                'title'=>'Вам передано поручение производственного совещания','body'=>$task->title,
                'url'=>route('tasks.page',['task'=>$task->id],false),
            ]);
        }
    }

    private function loadMeeting(ProductionMeeting $meeting): ProductionMeeting
    {
        return $meeting->load([
            'chairman:id,last_name,first_name,middle_name',
            'secretary:id,last_name,first_name,middle_name',
            'creator:id,last_name,first_name,middle_name',
            'items.department:id,name,short_name',
            'items.coexecutor:id,department_id,last_name,first_name,middle_name,position',
            'items.task:id,title,status,progress,due_at',
        ]);
    }

    private function renumber(ProductionMeeting $meeting): void
    {
        $n = 1;
        foreach ($meeting->items()->orderBy('number')->orderBy('id')->get() as $item) {
            if ((int)$item->number !== $n) $item->update(['number'=>$n]);
            $n++;
        }
    }

    private function authorizeManager(Request $request): void
    {
        abort_unless($request->user() && $request->user()->isManager(), 403);
    }
}

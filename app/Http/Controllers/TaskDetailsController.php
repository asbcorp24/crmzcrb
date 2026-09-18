<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\ReferenceItem;
use App\Models\Task;
use App\Models\TaskEvent;
use App\Models\TaskLink;
use App\Models\User;
use App\Services\AccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class TaskDetailsController extends Controller
{
    public function options(Request $request)
    {
        $user = $request->user();
        $departmentIds = app(AccessService::class)->departmentIds($user);

        return response()->json([
            'projects' => $this->refs('project', $user->organization_id),
            'bases' => $this->refs('basis', $user->organization_id),
            'organizations' => $this->refs('organization', $user->organization_id),
            'statuses' => $this->refs('task_status', $user->organization_id),
            'departments' => Department::whereIn('id', $departmentIds)->where('is_active', true)
                ->orderBy('name')->get(['id','name','short_name']),
            'can_create_reference' => $user->isManager(),
        ]);
    }

    public function show(Request $request, Task $task)
    {
        $this->authorizeView($request, $task);
        return response()->json($this->payload($request, $task));
    }

    public function batch(Request $request)
    {
        $ids = collect($request->input('ids', []))->map(fn($id)=>(int)$id)->filter()->unique()->take(50);
        if ($ids->isEmpty()) return response()->json([]);

        $rows = [];
        foreach (Task::whereIn('id', $ids)->get() as $task) {
            try {
                $this->authorizeView($request, $task);
                $rows[(string)$task->id] = $this->compactPayload($task);
            } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
                // Недоступные пользователю задачи просто не возвращаем.
            }
        }
        return response()->json($rows);
    }

    public function update(Request $request, Task $task)
    {
        $this->authorizeManage($request, $task);

        $data = $request->validate([
            'title' => 'nullable|string|max:255',
            'project_id' => 'nullable|integer',
            'basis_id' => 'nullable|integer',
            'responsible_department_id' => 'nullable|integer',
            'start_at' => 'nullable|date',
            'customer_type' => ['nullable', Rule::in(['organization','department'])],
            'customer_id' => 'nullable|integer',
            'business_status_id' => 'nullable|integer',
        ]);

        $this->assertReference($data['project_id'] ?? null, 'project', 'Проект', $request->user()->organization_id);
        $this->assertReference($data['basis_id'] ?? null, 'basis', 'Основание', $request->user()->organization_id);
        $this->assertReference($data['business_status_id'] ?? null, 'task_status', 'Статус', $request->user()->organization_id);

        if (!empty($data['responsible_department_id'])) {
            abort_unless(Department::whereKey($data['responsible_department_id'])->where('is_active', true)->exists(), 422, 'Ответственный отдел не найден.');
            abort_unless(app(AccessService::class)->departmentIds($request->user())->contains((int)$data['responsible_department_id']), 403);
        }

        if (empty($data['customer_type'])) {
            $data['customer_type'] = null;
            $data['customer_id'] = null;
        } elseif ($data['customer_type'] === 'organization') {
            abort_if(empty($data['customer_id']), 422, 'Выберите предприятие-заказчика.');
            $this->assertReference($data['customer_id'], 'organization', 'Заказчик', $request->user()->organization_id);
        } else {
            abort_if(empty($data['customer_id']), 422, 'Выберите подразделение-заказчика.');
            abort_unless(Department::whereKey($data['customer_id'])->where('is_active', true)->exists(), 422, 'Подразделение-заказчик не найдено.');
        }

        if (array_key_exists('title', $data)) {
            $title = trim((string)($data['title'] ?? ''));
            if ($title === '') {
                $title = $this->fallbackTitle($data['project_id'] ?? $task->project_id, $data['basis_id'] ?? $task->basis_id);
            }
            $data['title'] = $title;
        }

        DB::table('tasks')->where('id', $task->id)->update([
            'title' => $data['title'] ?? $task->title,
            'project_id' => $data['project_id'] ?? null,
            'basis_id' => $data['basis_id'] ?? null,
            'responsible_department_id' => $data['responsible_department_id'] ?? null,
            'start_at' => $data['start_at'] ?? null,
            'customer_type' => $data['customer_type'] ?? null,
            'customer_id' => $data['customer_id'] ?? null,
            'business_status_id' => $data['business_status_id'] ?? null,
            'updated_at' => now(),
        ]);

        TaskEvent::create([
            'task_id'=>$task->id,
            'user_id'=>$request->user()->id,
            'type'=>'business_details_updated',
            'from_status'=>$task->status,
            'to_status'=>$task->status,
            'message'=>'Изменены реквизиты задачи',
        ]);

        return response()->json(['ok'=>true,'task'=>$this->payload($request, $task->fresh())]);
    }

    public function storeLink(Request $request, Task $task)
    {
        $this->authorizeView($request, $task);
        $data = $request->validate([
            'title'=>'nullable|string|max:255',
            'url'=>'required|url|max:2048',
        ]);

        $link = TaskLink::create([
            'task_id'=>$task->id,
            'created_by'=>$request->user()->id,
            'title'=>trim((string)($data['title'] ?? '')) ?: null,
            'url'=>$data['url'],
        ]);

        TaskEvent::create([
            'task_id'=>$task->id,'user_id'=>$request->user()->id,'type'=>'link_added',
            'from_status'=>$task->status,'to_status'=>$task->status,
            'message'=>'Добавлена гиперссылка: '.($link->title ?: $link->url),
        ]);

        return response()->json(['ok'=>true,'link'=>$link->load('creator:id,last_name,first_name,middle_name')], 201);
    }

    public function destroyLink(Request $request, Task $task, TaskLink $link)
    {
        $this->authorizeView($request, $task);
        abort_unless((int)$link->task_id === (int)$task->id, 404);
        abort_unless(
            (int)$link->created_by === (int)$request->user()->id ||
            (int)$task->assigned_to === (int)$request->user()->id ||
            $this->canManage($request, $task),
            403
        );

        $label = $link->title ?: $link->url;
        $link->delete();
        TaskEvent::create([
            'task_id'=>$task->id,'user_id'=>$request->user()->id,'type'=>'link_deleted',
            'from_status'=>$task->status,'to_status'=>$task->status,
            'message'=>'Удалена гиперссылка: '.$label,
        ]);
        return response()->json(['ok'=>true]);
    }

    private function refs(string $type, int $organizationId)
    {
        return ReferenceItem::withoutGlobalScope('organization')
            ->where('organization_id', $organizationId)
            ->where('type',$type)->where('is_active',true)
            ->orderBy('sort_order')->orderBy('name')->get(['id','code','name','system_key','color']);
    }

    private function assertReference($id, string $type, string $label, int $organizationId): void
    {
        if (!$id) return;
        abort_unless(
            ReferenceItem::withoutGlobalScope('organization')
                ->where('organization_id', $organizationId)
                ->whereKey((int)$id)->where('type',$type)->where('is_active',true)->exists(),
            422,
            $label.' не найден(о) в справочнике.'
        );
    }

    private function fallbackTitle($projectId, $basisId): string
    {
        if ($projectId) {
            $name = ReferenceItem::whereKey((int)$projectId)->where('type','project')->value('name');
            if ($name) return $name;
        }
        if ($basisId) {
            $name = ReferenceItem::whereKey((int)$basisId)->where('type','basis')->value('name');
            if ($name) return $name;
        }
        return 'Задача';
    }

    private function compactPayload(Task $task): array
    {
        return [
            'project' => $this->refPayload($task->project_id),
            'basis' => $this->refPayload($task->basis_id),
            'responsible_department' => $task->responsible_department_id ? Department::find($task->responsible_department_id, ['id','name','short_name']) : null,
            'business_status' => $this->refPayload($task->business_status_id),
            'customer' => $this->customerPayload($task),
            'start_at' => $task->start_at,
        ];
    }

    private function payload(Request $request, Task $task): array
    {
        $compact = $this->compactPayload($task);
        return array_merge([
            'id'=>$task->id,
            'title'=>$task->title,
            'project_id'=>$task->project_id,
            'basis_id'=>$task->basis_id,
            'responsible_department_id'=>$task->responsible_department_id,
            'start_at'=>$task->start_at,
            'customer_type'=>$task->customer_type,
            'customer_id'=>$task->customer_id,
            'business_status_id'=>$task->business_status_id,
            'can_manage'=>$this->canManage($request, $task),
            'links'=>TaskLink::with('creator:id,last_name,first_name,middle_name')->where('task_id',$task->id)->latest()->get(),
        ], $compact);
    }

    private function refPayload($id): ?array
    {
        if (!$id) return null;
        $item = ReferenceItem::find($id, ['id','code','name','type','system_key','color']);
        return $item ? $item->toArray() : null;
    }

    private function customerPayload(Task $task): ?array
    {
        if (!$task->customer_type || !$task->customer_id) return null;
        if ($task->customer_type === 'organization') {
            $item = ReferenceItem::whereKey($task->customer_id)->where('type','organization')->first(['id','code','name']);
            return $item ? ['type'=>'organization','id'=>$item->id,'name'=>$item->name,'code'=>$item->code] : null;
        }
        $department = Department::find($task->customer_id, ['id','name','short_name']);
        return $department ? ['type'=>'department','id'=>$department->id,'name'=>$department->name,'code'=>$department->short_name] : null;
    }

    private function authorizeView(Request $request, Task $task): void
    {
        $user = $request->user();
        if ((int)$task->assigned_to === (int)$user->id || (int)$task->created_by === (int)$user->id || $user->isAdmin()) return;
        abort_unless($user->isManager() && app(AccessService::class)->userIds($user, true)->contains((int)$task->assigned_to), 403);
    }

    private function authorizeManage(Request $request, Task $task): void
    {
        abort_unless($this->canManage($request, $task), 403, 'Изменять реквизиты задачи может автор или руководитель.');
    }

    private function canManage(Request $request, Task $task): bool
    {
        $user = $request->user();
        if ($user->isAdmin() || (int)$task->created_by === (int)$user->id) return true;
        if (!$user->isManager()) return false;
        $assignee = User::find($task->assigned_to);
        return $assignee ? app(AccessService::class)->canManageUser($user, $assignee) : false;
    }
}

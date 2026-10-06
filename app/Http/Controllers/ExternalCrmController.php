<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\ExternalCrmDepartmentMapping;
use App\Models\Organization;
use App\Models\ReferenceItem;
use App\Models\Task;
use App\Models\TaskLink;
use App\Models\TaskEvent;
use App\Services\AccessService;
use App\Services\ExternalCrmService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ExternalCrmController extends Controller
{
    public function page(Request $request, ExternalCrmService $crm)
    {
        $this->admin($request);

        $organization = Organization::findOrFail($request->user()->organization_id);
        $settings = $organization->settings ?: [];
        $departments = Department::withoutGlobalScope('organization')
            ->where('organization_id', $organization->id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $mappings = ExternalCrmDepartmentMapping::withoutGlobalScope('organization')
            ->where('organization_id', $organization->id)
            ->get()
            ->keyBy('department_id');

        $workshops = [];
        $remoteUsers = [];
        $taskTypes = [];
        $remoteError = null;
        $health = null;

        if ($crm->configured($organization)) {
            try {
                $health = $crm->health($organization);
                $workshops = $crm->workshops($organization);
                $remoteUsers = $crm->users($organization);
                $taskTypes = $crm->taskTypes($organization);
            } catch (\Throwable $e) {
                $remoteError = $e->getMessage();
            }
        }

        return view('external_crm.index', [
            'organization' => $organization,
            'baseUrl' => $settings['external_crm_url'] ?? '',
            'tokenConfigured' => !empty($settings['external_crm_token']),
            'departments' => $departments,
            'mappings' => $mappings,
            'workshops' => $workshops,
            'remoteUsers' => $remoteUsers,
            'taskTypes' => $taskTypes,
            'remoteError' => $remoteError,
            'health' => $health,
        ]);
    }

    public function saveConnection(Request $request)
    {
        $this->admin($request);

        $data = $request->validate([
            'external_crm_url' => 'required|url|max:500',
            'external_crm_token' => 'nullable|string|max:2000',
        ]);

        $organization = Organization::findOrFail($request->user()->organization_id);
        $settings = $organization->settings ?: [];
        $token = trim((string)($data['external_crm_token'] ?? ''));

        if ($token === '' && empty($settings['external_crm_token'])) {
            throw ValidationException::withMessages([
                'external_crm_token' => 'Укажите API-токен внешней CRM.',
            ]);
        }

        $settings['external_crm_url'] = rtrim(trim($data['external_crm_url']), '/');
        if ($token !== '') {
            $settings['external_crm_token'] = Crypt::encryptString($token);
        }

        $organization->settings = $settings;
        $organization->save();

        return redirect()->route('external-crm.page')->with('success', 'Подключение к внешней CRM сохранено.');
    }

    public function test(Request $request, ExternalCrmService $crm)
    {
        $this->admin($request);
        $organization = Organization::findOrFail($request->user()->organization_id);

        try {
            return response()->json([
                'ok' => true,
                'health' => $crm->health($organization),
                'workshops_count' => count($crm->workshops($organization)),
                'users_count' => count($crm->users($organization)),
                'task_types_count' => count($crm->taskTypes($organization)),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'ok' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function saveMappings(Request $request, ExternalCrmService $crm)
    {
        $this->admin($request);

        $data = $request->validate([
            'mappings' => 'nullable|array',
            'mappings.*.workshop_id' => 'nullable|integer|min:1',
            'mappings.*.manager_user_id' => 'nullable|integer|min:1',
            'mappings.*.task_type_id' => 'nullable|integer|min:1',
        ]);

        $organization = Organization::findOrFail($request->user()->organization_id);

        try {
            $workshops = collect($crm->workshops($organization))->keyBy(fn ($row) => (int)($row['id'] ?? 0));
            $remoteUsers = collect($crm->users($organization))->keyBy(fn ($row) => (int)($row['id'] ?? 0));
            $taskTypes = collect($crm->taskTypes($organization))->keyBy(fn ($row) => (int)($row['id'] ?? 0));
        } catch (\Throwable $e) {
            throw ValidationException::withMessages(['connection' => $e->getMessage()]);
        }

        $localDepartments = Department::withoutGlobalScope('organization')
            ->where('organization_id', $organization->id)
            ->pluck('id')
            ->map(fn ($id) => (int)$id)
            ->flip();

        foreach (($data['mappings'] ?? []) as $departmentId => $row) {
            $departmentId = (int)$departmentId;
            if (!$localDepartments->has($departmentId)) {
                continue;
            }

            $workshopId = !empty($row['workshop_id']) ? (int)$row['workshop_id'] : null;
            $managerUserId = !empty($row['manager_user_id']) ? (int)$row['manager_user_id'] : null;
            $taskTypeId = !empty($row['task_type_id']) ? (int)$row['task_type_id'] : null;

            if (!$workshopId) {
                ExternalCrmDepartmentMapping::withoutGlobalScope('organization')
                    ->where('organization_id', $organization->id)
                    ->where('department_id', $departmentId)
                    ->delete();
                continue;
            }

            if (!$taskTypeId) {
                throw ValidationException::withMessages([
                    'mappings.'.$departmentId.'.task_type_id' => 'Для сопоставленного цеха выберите тип задачи.',
                ]);
            }

            $workshop = $workshops->get($workshopId);
            $taskType = $taskTypes->get($taskTypeId);

            if (!$workshop) {
                throw ValidationException::withMessages([
                    'mappings.'.$departmentId.'.workshop_id' => 'Выбранный цех не найден во внешней CRM.',
                ]);
            }

            if (!$taskType) {
                throw ValidationException::withMessages([
                    'mappings.'.$departmentId.'.task_type_id' => 'Выбранный тип задачи не найден во внешней CRM.',
                ]);
            }

            if (!$managerUserId && isset($workshop['manager']) && is_array($workshop['manager'])) {
                $managerUserId = !empty($workshop['manager']['id']) ? (int)$workshop['manager']['id'] : null;
            }

            if (!$managerUserId) {
                throw ValidationException::withMessages([
                    'mappings.'.$departmentId.'.manager_user_id' => 'Выберите получателя задачи во внешней CRM.',
                ]);
            }

            $manager = $remoteUsers->get($managerUserId);
            if (!$manager) {
                throw ValidationException::withMessages([
                    'mappings.'.$departmentId.'.manager_user_id' => 'Выбранный получатель не найден среди активных пользователей внешней CRM.',
                ]);
            }

            ExternalCrmDepartmentMapping::withoutGlobalScope('organization')->updateOrCreate(
                [
                    'organization_id' => $organization->id,
                    'department_id' => $departmentId,
                ],
                [
                    'external_workshop_id' => $workshopId,
                    'external_workshop_name' => $workshop['name'] ?? null,
                    'external_manager_user_id' => $managerUserId,
                    'external_manager_name' => $manager['full_name'] ?? null,
                    'external_task_type_id' => $taskTypeId,
                    'external_task_type_name' => $taskType['name'] ?? null,
                    'is_active' => true,
                ]
            );
        }

        return redirect()->route('external-crm.page')->with('success', 'Сопоставление отделов с удалёнными цехами сохранено.');
    }

    public function plansReport(Request $request, ExternalCrmService $crm)
    {
        $user = $request->user();
        abort_unless($user->isAdmin() || $user->isManager(), 403);

        $data = $request->validate([
            'month' => ['required','regex:/^\\d{4}-\\d{2}$/'],
            'details' => 'nullable|boolean',
        ]);

        $organization = Organization::findOrFail($user->organization_id);
        if (!$crm->configured($organization)) {
            return response()->json([
                'ok' => false,
                'message' => 'Подключение к внешней CRM не настроено.',
            ], 422);
        }

        try {
            $remote = !empty($data['details'])
                ? $crm->plansReport($organization, $data['month'])
                : $crm->plansAnalytics($organization, $data['month']);

            return response()->json([
                'ok' => true,
                'data' => isset($remote['data']) && is_array($remote['data']) ? $remote['data'] : [],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'ok' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function taskDetails(Request $request, Task $task, ExternalCrmService $crm)
    {
        $this->authorizeTaskSync($request, $task);

        if (!$task->external_crm_task_id) {
            return response()->json([
                'ok' => false,
                'message' => 'Задача ещё не связана с внешней CRM.',
            ], 422);
        }

        $organization = Organization::findOrFail($task->organization_id);

        try {
            $remote = $crm->taskDetails($organization, (int)$task->external_crm_task_id);

            return response()->json([
                'ok' => true,
                'data' => isset($remote['data']) && is_array($remote['data']) ? $remote['data'] : [],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'ok' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function downloadTaskDocument(Request $request, Task $task, int $documentId, ExternalCrmService $crm)
    {
        $this->authorizeTaskSync($request, $task);

        if (!$task->external_crm_task_id) {
            abort(404);
        }

        $organization = Organization::findOrFail($task->organization_id);

        try {
            $file = $crm->downloadTaskDocument(
                $organization,
                (int)$task->external_crm_task_id,
                $documentId
            );

            return response($file['body'], 200, [
                'Content-Type' => $file['content_type'] ?: 'application/octet-stream',
                'Content-Disposition' => 'attachment; filename*=UTF-8\'\''.rawurlencode($file['filename']),
                'Cache-Control' => 'private, no-store',
            ]);
        } catch (\Throwable $e) {
            abort(422, $e->getMessage());
        }
    }

    public function addTaskLog(Request $request, Task $task, ExternalCrmService $crm)
    {
        $this->authorizeTaskSync($request, $task);

        if (!$task->external_crm_task_id) {
            return response()->json([
                'ok' => false,
                'message' => 'Задача ещё не связана с внешней CRM.',
            ], 422);
        }

        $data = $request->validate([
            'text' => 'required|string|max:10000',
            'parent_id' => 'nullable|integer|min:1',
            'is_done' => 'nullable|boolean',
        ]);

        $organization = Organization::findOrFail($task->organization_id);

        try {
            $remote = $crm->addTaskLog($organization, (int)$task->external_crm_task_id, [
                'text' => trim($data['text']),
                'parent_id' => $data['parent_id'] ?? null,
                'is_done' => !empty($data['is_done']),
                'entry_date' => now()->toIso8601String(),
            ]);

            return response()->json([
                'ok' => true,
                'data' => isset($remote['data']) && is_array($remote['data']) ? $remote['data'] : [],
            ], 201);
        } catch (\Throwable $e) {
            return response()->json([
                'ok' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function syncTask(Request $request, Task $task, ExternalCrmService $crm)
    {
        $this->authorizeTaskSync($request, $task);

        if (!$task->responsible_department_id) {
            return response()->json([
                'ok' => false,
                'message' => 'У задачи не выбран ответственный отдел.',
            ], 422);
        }

        $mapping = ExternalCrmDepartmentMapping::withoutGlobalScope('organization')
            ->where('organization_id', $task->organization_id)
            ->where('department_id', $task->responsible_department_id)
            ->where('is_active', true)
            ->first();

        if (!$mapping) {
            return response()->json([
                'ok' => false,
                'message' => 'Для ответственного отдела не настроено сопоставление с удалённым цехом.',
            ], 422);
        }

        $organization = Organization::findOrFail($task->organization_id);
        if (!$crm->configured($organization)) {
            return response()->json([
                'ok' => false,
                'message' => 'Подключение к внешней CRM не настроено.',
            ], 422);
        }

        $remoteOwner = collect($crm->users($organization))->first(function ($row) use ($mapping) {
            return (int)($row['id'] ?? 0) === (int)$mapping->external_manager_user_id;
        });
        $remoteDepartmentId = is_array($remoteOwner)
            ? (int)($remoteOwner['department']['id'] ?? 0)
            : 0;

        $project = $this->referenceForTask($task, 'project', $task->project_id);
        $basis = $this->referenceForTask($task, 'basis', $task->basis_id);
        $businessStatus = $this->referenceForTask($task, 'task_status', $task->business_status_id);
        $responsibleDepartment = $task->responsible_department_id
            ? Department::withoutGlobalScope('organization')->whereKey($task->responsible_department_id)->first(['id','name','short_name'])
            : null;
        $customerName = $this->customerName($task);
        $links = TaskLink::where('task_id', $task->id)->orderBy('id')->get(['title','url']);
        $localTaskUrl = route('tasks.page', ['task' => $task->id]);

        $descriptionParts = [];
        if (trim((string)$task->description) !== '') {
            $descriptionParts[] = trim((string)$task->description);
        }

        $details = [];
        if ($project) $details[] = 'Проект: '.$project->name;
        if ($basis) $details[] = 'Основание: '.$basis->name;
        if ($customerName) $details[] = 'Заказчик: '.$customerName;
        if ($responsibleDepartment) {
            $details[] = 'Ответственный отдел: '.($responsibleDepartment->short_name ?: $responsibleDepartment->name);
        }
        if ($businessStatus) $details[] = 'Статус CRM: '.$businessStatus->name;
        $details[] = 'Исходная задача CRM: #'.$task->id;
        $details[] = 'Ссылка на задачу: '.$localTaskUrl;

        if ($links->isNotEmpty()) {
            $details[] = 'Ссылки:';
            foreach ($links as $link) {
                $details[] = '- '.(trim((string)$link->title) !== '' ? $link->title.': ' : '').$link->url;
            }
        }

        if (!empty($details)) {
            $descriptionParts[] = implode("\n", $details);
        }

        $payload = [
            'title' => trim((string)$task->title) ?: 'Задача',
            'description' => implode("\n\n", $descriptionParts) ?: null,
            'direction' => $project?->name,
            'outgoing_document' => $basis?->name,
            'status_comment' => $businessStatus?->name,
            'type_id' => (int)$mapping->external_task_type_id,
            'owner_id' => (int)$mapping->external_manager_user_id,
            'assigned_department_id' => $remoteDepartmentId ?: null,
            'start_date' => ($task->start_at ?: $task->created_at ?: now())->toIso8601String(),
            'end_date' => $task->due_at?->toIso8601String(),
            'priority' => $this->externalPriority($task->priority),
            'status' => $this->externalStatus($task->status),
        ];

        try {
            $mode = $task->external_crm_task_id ? 'updated' : 'created';
            $remote = $task->external_crm_task_id
                ? $crm->updateTask($organization, (int)$task->external_crm_task_id, $payload)
                : $crm->createTask($organization, $payload);

            $remoteTask = isset($remote['data']) && is_array($remote['data']) ? $remote['data'] : [];
            $externalId = (int)($remoteTask['id'] ?? $task->external_crm_task_id ?? 0);
            if (!$externalId) {
                throw new \RuntimeException('Внешняя CRM не вернула ID задачи.');
            }

            DB::table('tasks')->where('id', $task->id)->update([
                'external_crm_task_id' => $externalId,
                'external_crm_sync_status' => 'synced',
                'external_crm_sync_error' => null,
                'external_crm_synced_at' => now(),
                'updated_at' => now(),
            ]);

            TaskEvent::create([
                'task_id' => $task->id,
                'user_id' => $request->user()->id,
                'type' => 'external_crm_synced',
                'from_status' => $task->status,
                'to_status' => $task->status,
                'message' => ($mode === 'created' ? 'Задача отправлена' : 'Задача обновлена').' во внешней CRM #'.$externalId.
                    '. Цех: '.($mapping->external_workshop_name ?: $mapping->external_workshop_id).
                    '. Получатель: '.($mapping->external_manager_name ?: $mapping->external_manager_user_id).'.',
            ]);

            return response()->json([
                'ok' => true,
                'mode' => $mode,
                'external_task_id' => $externalId,
                'synced_at' => now()->toIso8601String(),
                'workshop' => [
                    'id' => $mapping->external_workshop_id,
                    'name' => $mapping->external_workshop_name,
                ],
                'manager' => [
                    'id' => $mapping->external_manager_user_id,
                    'name' => $mapping->external_manager_name,
                ],
                'task_type' => [
                    'id' => $mapping->external_task_type_id,
                    'name' => $mapping->external_task_type_name,
                ],
            ]);
        } catch (\Throwable $e) {
            DB::table('tasks')->where('id', $task->id)->update([
                'external_crm_sync_status' => 'error',
                'external_crm_sync_error' => mb_substr($e->getMessage(), 0, 5000),
                'updated_at' => now(),
            ]);

            return response()->json([
                'ok' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    private function authorizeTaskSync(Request $request, Task $task): void
    {
        abort_unless((int)$task->organization_id === (int)$request->user()->organization_id, 404);

        $user = $request->user();
        if ($user->isAdmin() || (int)$task->created_by === (int)$user->id) {
            return;
        }

        if ($user->isManager()) {
            $assignee = $task->assignee()->first();
            if ($assignee && app(AccessService::class)->canManageUser($user, $assignee)) {
                return;
            }
        }

        abort(403);
    }

    private function referenceForTask(Task $task, string $type, $id): ?ReferenceItem
    {
        if (!$id) return null;

        return ReferenceItem::withoutGlobalScope('organization')
            ->where('organization_id', $task->organization_id)
            ->whereKey((int)$id)
            ->where('type', $type)
            ->first();
    }

    private function customerName(Task $task): ?string
    {
        if (!$task->customer_type || !$task->customer_id) return null;

        if ($task->customer_type === 'organization') {
            return ReferenceItem::withoutGlobalScope('organization')
                ->where('organization_id', $task->organization_id)
                ->whereKey((int)$task->customer_id)
                ->where('type', 'organization')
                ->value('name');
        }

        if ($task->customer_type === 'department') {
            $department = Department::withoutGlobalScope('organization')
                ->where('organization_id', $task->organization_id)
                ->whereKey((int)$task->customer_id)
                ->first(['name','short_name']);

            return $department ? ($department->short_name ?: $department->name) : null;
        }

        return null;
    }

    private function externalPriority(?string $priority): int
    {
        return [
            'low' => 2,
            'normal' => 3,
            'high' => 4,
            'critical' => 5,
        ][$priority ?: 'normal'] ?? 3;
    }

    private function externalStatus(?string $status): string
    {
        return [
            'completed' => 'done',
            'cancelled' => 'cancelled',
            'new' => 'in_progress',
            'in_progress' => 'in_progress',
            'review' => 'in_progress',
        ][$status ?: 'new'] ?? 'in_progress';
    }

    private function admin(Request $request): void
    {
        abort_unless($request->user()->isAdmin(), 403);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\ExternalCrmDepartmentMapping;
use App\Models\Organization;
use App\Models\Task;
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
        $taskTypes = [];
        $remoteError = null;
        $health = null;

        if ($crm->configured($organization)) {
            try {
                $health = $crm->health($organization);
                $workshops = $crm->workshops($organization);
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
            'mappings.*.task_type_id' => 'nullable|integer|min:1',
        ]);

        $organization = Organization::findOrFail($request->user()->organization_id);

        try {
            $workshops = collect($crm->workshops($organization))->keyBy(fn ($row) => (int)($row['id'] ?? 0));
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

            $manager = isset($workshop['manager']) && is_array($workshop['manager']) ? $workshop['manager'] : null;
            if (!$manager || empty($manager['id'])) {
                throw ValidationException::withMessages([
                    'mappings.'.$departmentId.'.workshop_id' => 'У выбранного цеха во внешней CRM не назначен начальник.',
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
                    'external_manager_user_id' => (int)$manager['id'],
                    'external_manager_name' => $manager['full_name'] ?? null,
                    'external_task_type_id' => $taskTypeId,
                    'external_task_type_name' => $taskType['name'] ?? null,
                    'is_active' => true,
                ]
            );
        }

        return redirect()->route('external-crm.page')->with('success', 'Сопоставление отделов с удалёнными цехами сохранено.');
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

        $payload = [
            'title' => trim((string)$task->title) ?: 'Задача',
            'description' => $task->description,
            'type_id' => (int)$mapping->external_task_type_id,
            'owner_id' => (int)$mapping->external_manager_user_id,
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

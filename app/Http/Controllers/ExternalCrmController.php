<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\ExternalCrmDepartmentMapping;
use App\Models\Organization;
use App\Services\ExternalCrmService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
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

    private function admin(Request $request): void
    {
        abort_unless($request->user()->isAdmin(), 403);
    }
}

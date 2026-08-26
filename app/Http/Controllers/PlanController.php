<?php

namespace App\Http\Controllers;

use App\Models\CrmNotification;
use App\Models\Plan;
use App\Models\Task;
use App\Models\User;
use App\Services\AccessService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PlanController extends Controller
{
    public function page(Request $request)
    {
        $ids = app(AccessService::class)->userIds($request->user(), true);
        $users = User::whereIn('id', $ids)->where('is_active', true)->orderBy('last_name')->orderBy('first_name')->get();
        return view('plans.index', compact('users'));
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $ids = app(AccessService::class)->userIds($user, true);
        $q = Plan::with(['user.department','creator','tasks'])
            ->where(function ($w) use ($ids, $user) {
                $w->whereIn('user_id', $ids)->orWhere('created_by', $user->id);
            });

        if ($request->filled('user_id')) {
            $userId = $request->integer('user_id');
            abort_unless($ids->contains($userId), 403);
            $q->where('user_id', $userId);
        }

        if ($request->filled('status')) $q->where('status', $request->status);
        if ($request->filled('period_type')) $q->where('period_type', $request->period_type);
        if ($request->filled('q')) {
            $s = trim($request->q);
            $q->where(function ($w) use ($s) {
                $w->where('title', 'like', "%{$s}%")
                  ->orWhere('description', 'like', "%{$s}%");
            });
        }

        $plans = $q->orderByDesc('period_start')->orderByDesc('id')->paginate(30);
        $plans->getCollection()->transform(function (Plan $plan) {
            $plan->progress = $this->calculateProgress($plan);
            return $plan;
        });
        return response()->json($plans);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $ids = app(AccessService::class)->userIds($request->user(), true);
        abort_unless($ids->contains((int)$data['user_id']), 403);
        if (!$request->user()->isManager()) abort_unless((int)$data['user_id'] === (int)$request->user()->id, 403);

        $data['created_by'] = $request->user()->id;
        $plan = Plan::create($data);
        return response()->json(['ok' => true, 'plan' => $plan->load(['user.department','creator'])], 201);
    }

    public function update(Request $request, Plan $plan)
    {
        $this->authorizePlanManage($request, $plan);
        $data = $this->validated($request, true);

        if (isset($data['user_id'])) {
            $ids = app(AccessService::class)->userIds($request->user(), true);
            abort_unless($ids->contains((int)$data['user_id']), 403);
            if (!$request->user()->isManager()) abort_unless((int)$data['user_id'] === (int)$request->user()->id, 403);
        }

        $plan->update($data);
        $plan->progress = $this->calculateProgress($plan->fresh('tasks'));
        $plan->save();
        return response()->json(['ok' => true, 'plan' => $plan->fresh()->load(['user.department','creator','tasks'])]);
    }

    public function addTask(Request $request, Plan $plan)
    {
        $this->authorizePlanManage($request, $plan);
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'priority' => ['required', Rule::in(['low','normal','high','critical'])],
            'due_at' => 'nullable|date',
        ]);

        $task = Task::create([
            'plan_id' => $plan->id,
            'assigned_to' => $plan->user_id,
            'created_by' => $request->user()->id,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'priority' => $data['priority'],
            'due_at' => $data['due_at'] ?? null,
            'status' => 'new',
            'progress' => 0,
        ]);

        if ($task->assigned_to !== $request->user()->id) {
            CrmNotification::create([
                'user_id' => $task->assigned_to,
                'task_id' => $task->id,
                'type' => 'task_assigned',
                'title' => 'Новая задача из плана',
                'body' => $task->title,
                'url' => route('tasks.page', ['task' => $task->id], false),
            ]);
        }

        $plan->progress = $this->calculateProgress($plan->fresh('tasks'));
        $plan->save();
        return response()->json(['ok' => true, 'task' => $task, 'plan_progress' => $plan->progress], 201);
    }

    private function authorizePlanManage(Request $request, Plan $plan): void
    {
        $user = $request->user();
        if ($user->isAdmin() || (int)$plan->created_by === (int)$user->id || (int)$plan->user_id === (int)$user->id) return;
        $owner = User::find($plan->user_id);
        abort_unless($owner && app(AccessService::class)->canManageUser($user, $owner), 403);
    }

    private function validated(Request $request, bool $partial = false): array
    {
        $prefix = $partial ? 'sometimes|' : '';
        return $request->validate([
            'user_id' => $prefix.'required|exists:users,id',
            'title' => $prefix.'required|string|max:255',
            'description' => 'nullable|string',
            'period_start' => $prefix.'required|date',
            'period_end' => $prefix.'required|date|after_or_equal:period_start',
            'period_type' => [$partial ? 'sometimes' : 'required', Rule::in(['day','week','month','quarter','year','custom'])],
            'status' => [$partial ? 'sometimes' : 'required', Rule::in(['draft','active','completed','cancelled'])],
        ]);
    }

    private function calculateProgress(Plan $plan): int
    {
        $tasks = $plan->tasks;
        if ($tasks->isEmpty()) return (int) $plan->progress;
        return (int) round($tasks->avg(fn (Task $task) => $task->status === 'completed' ? 100 : (int)$task->progress));
    }
}

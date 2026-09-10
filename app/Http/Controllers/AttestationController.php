<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\User;
use App\Services\AccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AttestationController extends Controller
{
    public function page(Request $request, AccessService $access)
    {
        $viewer = $request->user();
        $orgId = (int) $viewer->organization_id;

        $campaigns = DB::table('attestation_campaigns')
            ->where('organization_id', $orgId)
            ->orderByDesc('id')->get();

        $selected = $campaigns->firstWhere('id', (int) $request->query('campaign')) ?: $campaigns->first();
        $departments = Department::where('is_active', true)->orderBy('name')->get();
        $myDepartment = $viewer->department_id ? Department::find($viewer->department_id) : null;

        $colleagues = collect();
        $myScores = collect();
        $analytics = null;

        if ($selected && $viewer->department_id) {
            $assigned = DB::table('attestation_campaign_departments')
                ->where('campaign_id', $selected->id)
                ->where('department_id', $viewer->department_id)
                ->exists();

            if ($assigned) {
                // Оцениваем сотрудников всей организации: своего и других подразделений.
                $colleagues = User::with('department')
                    ->where('is_active', true)
                    ->where('id', '<>', $viewer->id)
                    ->orderBy('department_id')
                    ->orderBy('last_name')
                    ->orderBy('first_name')
                    ->get();

                $myScores = DB::table('attestation_scores')
                    ->where('campaign_id', $selected->id)
                    ->where('evaluator_id', $viewer->id)
                    ->pluck('score', 'evaluatee_id');
            }
        }

        if ($selected && $viewer->isManager()) {
            $allowedDepartmentIds = $access->departmentIds($viewer)->map(fn ($id) => (int) $id);
            $selectedDepartmentId = (int) ($request->query('department') ?: ($viewer->department_id ?: $allowedDepartmentIds->first()));
            if (!$allowedDepartmentIds->contains($selectedDepartmentId)) {
                $selectedDepartmentId = (int) $allowedDepartmentIds->first();
            }

            if ($selectedDepartmentId) {
                $people = User::where('department_id', $selectedDepartmentId)
                    ->where('is_active', true)
                    ->orderBy('last_name')->orderBy('first_name')->get();

                // Для людей выбранного подразделения учитываем оценки от сотрудников всей организации.
                $scores = DB::table('attestation_scores')
                    ->where('campaign_id', $selected->id)
                    ->whereIn('evaluatee_id', $people->pluck('id'))
                    ->get();

                $evaluatorIds = $scores->pluck('evaluator_id')->unique()->values();
                $evaluators = User::with('department')
                    ->whereIn('id', $evaluatorIds)
                    ->orderBy('department_id')->orderBy('last_name')->orderBy('first_name')->get();

                $matrix = [];
                foreach ($scores as $s) {
                    $matrix[(int) $s->evaluatee_id][(int) $s->evaluator_id] = (int) $s->score;
                }

                $rows = $people->map(function ($p) use ($evaluators, $matrix) {
                    $vals = collect($matrix[$p->id] ?? []);
                    return [
                        'user' => $p,
                        'scores' => $evaluators->mapWithKeys(fn ($e) => [$e->id => ($matrix[$p->id][$e->id] ?? null)]),
                        'avg' => $vals->count() ? round($vals->avg(), 2) : null,
                        'count' => $vals->count(),
                        'sum' => $vals->sum(),
                    ];
                });

                $all = $scores->pluck('score');
                $expectedEvaluators = User::where('is_active', true)->where('id', '<>', 0)->count();
                $analytics = [
                    'department_id' => $selectedDepartmentId,
                    'people' => $people,
                    'evaluators' => $evaluators,
                    'rows' => $rows,
                    'overall_avg' => $all->count() ? round($all->avg(), 2) : null,
                    'score_counts' => [
                        1 => $all->filter(fn ($v) => (int) $v === 1)->count(),
                        2 => $all->filter(fn ($v) => (int) $v === 2)->count(),
                        3 => $all->filter(fn ($v) => (int) $v === 3)->count(),
                        4 => $all->filter(fn ($v) => (int) $v === 4)->count(),
                    ],
                    'completed_evaluators' => $scores->pluck('evaluator_id')->unique()->count(),
                    'expected_evaluators' => $expectedEvaluators,
                ];
            }
        }

        return view('attestation.index', compact('campaigns', 'selected', 'departments', 'myDepartment', 'colleagues', 'myScores', 'analytics'));
    }

    public function storeCampaign(Request $request)
    {
        abort_unless($request->user()->isManager(), 403);
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after_or_equal:starts_at',
            'department_ids' => 'required|array|min:1',
            'department_ids.*' => 'integer',
        ]);

        $orgId = (int) $request->user()->organization_id;
        DB::transaction(function () use ($data, $request, $orgId) {
            $id = DB::table('attestation_campaigns')->insertGetId([
                'organization_id' => $orgId,
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'starts_at' => $data['starts_at'] ?? null,
                'ends_at' => $data['ends_at'] ?? null,
                'is_active' => true,
                'created_by' => $request->user()->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach (array_unique($data['department_ids']) as $depId) {
                $dep = Department::where('id', $depId)->where('is_active', true)->firstOrFail();
                DB::table('attestation_campaign_departments')->insert([
                    'campaign_id' => $id,
                    'department_id' => $dep->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });

        return back()->with('success', 'Аттестация создана.');
    }

    public function saveScores(Request $request, int $campaign)
    {
        $viewer = $request->user();
        $camp = DB::table('attestation_campaigns')
            ->where('id', $campaign)
            ->where('organization_id', $viewer->organization_id)
            ->where('is_active', true)
            ->first();

        abort_unless($camp, 404);
        abort_unless($viewer->department_id, 422);
        abort_unless(
            DB::table('attestation_campaign_departments')
                ->where('campaign_id', $campaign)
                ->where('department_id', $viewer->department_id)
                ->exists(),
            403
        );

        $data = $request->validate([
            'scores' => 'required|array',
            'scores.*' => 'nullable|integer|min:1|max:4',
        ]);

        // Разрешены сотрудники всей организации, кроме самого оценивающего.
        $validUsers = User::where('is_active', true)
            ->where('id', '<>', $viewer->id)
            ->get(['id', 'department_id']);
        $validById = $validUsers->keyBy(fn ($u) => (string) $u->id);

        DB::transaction(function () use ($data, $validById, $viewer, $campaign) {
            foreach ($data['scores'] as $id => $score) {
                if ($score === null || $score === '') continue;
                $target = $validById->get((string) $id);
                abort_unless($target, 422);

                DB::table('attestation_scores')->updateOrInsert(
                    [
                        'campaign_id' => $campaign,
                        'evaluator_id' => $viewer->id,
                        'evaluatee_id' => (int) $id,
                    ],
                    [
                        'organization_id' => $viewer->organization_id,
                        // Храним подразделение оцениваемого, чтобы строить свод по отделу.
                        'department_id' => $target->department_id,
                        'score' => (int) $score,
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]
                );
            }
        });

        return back()->with('success', 'Оценки сохранены. Можно оценить коллег своего и других подразделений.');
    }
}

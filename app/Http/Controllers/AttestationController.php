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

        $campaigns = DB::table('attestation_campaigns')->where('organization_id', $orgId)->orderByDesc('id')->get();
        $selected = $campaigns->firstWhere('id', (int) $request->query('campaign')) ?: $campaigns->first();
        $departments = Department::where('is_active', true)->orderBy('name')->get();
        $allUsers = User::with('department')->where('is_active', true)->orderBy('department_id')->orderBy('last_name')->orderBy('first_name')->get();
        $myDepartment = $viewer->department_id ? Department::find($viewer->department_id) : null;

        $colleagues = collect();
        $myScores = collect();
        $analytics = null;
        $commissionMembers = collect();
        $commissionTargets = collect();
        $myCommissionScores = collect();
        $commissionAnalytics = null;
        $isCommissionMember = false;
        $assignedDepartments = collect();
        $workDepartmentId = null;
        $commissionDepartmentId = null;

        if ($selected) {
            $assignedDepartmentIds = DB::table('attestation_campaign_departments')
                ->where('campaign_id', $selected->id)->pluck('department_id')->map(fn ($v) => (int) $v);
            $assignedDepartments = $departments->whereIn('id', $assignedDepartmentIds)->values();

            if ($viewer->department_id && $assignedDepartmentIds->contains((int) $viewer->department_id)) {
                $requestedWorkDepartment = (int) $request->query('work_department');
                $workDepartmentId = $departments->contains('id', $requestedWorkDepartment)
                    ? $requestedWorkDepartment
                    : (int) ($viewer->department_id ?: $departments->first()?->id);

                $colleagues = $allUsers
                    ->where('department_id', $workDepartmentId)
                    ->where('id', '<>', $viewer->id)
                    ->values();

                $myScores = DB::table('attestation_scores')
                    ->where('campaign_id', $selected->id)->where('evaluator_id', $viewer->id)
                    ->pluck('score', 'evaluatee_id');
            }

            $commissionMemberIds = DB::table('attestation_commission_members')
                ->where('campaign_id', $selected->id)->pluck('user_id')->map(fn ($v) => (int) $v);
            $commissionMembers = $allUsers->whereIn('id', $commissionMemberIds)->values();
            $isCommissionMember = $commissionMemberIds->contains((int) $viewer->id);

            if ($isCommissionMember) {
                $requestedCommissionDepartment = (int) $request->query('commission_department');
                $commissionDepartmentId = $assignedDepartmentIds->contains($requestedCommissionDepartment)
                    ? $requestedCommissionDepartment
                    : (int) ($assignedDepartmentIds->first() ?: 0);

                if ($commissionDepartmentId) {
                    $commissionTargets = $allUsers->where('department_id', $commissionDepartmentId)->values();
                }

                $myCommissionScores = DB::table('attestation_commission_scores')
                    ->where('campaign_id', $selected->id)
                    ->where('commission_member_id', $viewer->id)
                    ->pluck('score', 'evaluatee_id');
            }

            if ($viewer->isManager()) {
                $allowedDepartmentIds = $access->departmentIds($viewer)->map(fn ($id) => (int) $id);
                $selectedDepartmentId = (int) ($request->query('department') ?: ($viewer->department_id ?: $allowedDepartmentIds->first()));
                if (!$allowedDepartmentIds->contains($selectedDepartmentId)) {
                    $selectedDepartmentId = (int) $allowedDepartmentIds->first();
                }

                if ($selectedDepartmentId) {
                    $people = $allUsers->where('department_id', $selectedDepartmentId)->values();

                    $scores = DB::table('attestation_scores')
                        ->where('campaign_id', $selected->id)
                        ->whereIn('evaluatee_id', $people->pluck('id'))
                        ->get();
                    $evaluatorIds = $scores->pluck('evaluator_id')->unique()->values();
                    $evaluators = $allUsers->whereIn('id', $evaluatorIds)->values();
                    $matrix = [];
                    foreach ($scores as $s) $matrix[(int) $s->evaluatee_id][(int) $s->evaluator_id] = (int) $s->score;
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
                    $analytics = [
                        'department_id' => $selectedDepartmentId,
                        'people' => $people,
                        'evaluators' => $evaluators,
                        'rows' => $rows,
                        'overall_avg' => $all->count() ? round($all->avg(), 2) : null,
                        'score_counts' => [1=>$all->where(1)->count(),2=>$all->where(2)->count(),3=>$all->where(3)->count(),4=>$all->where(4)->count()],
                        'completed_evaluators' => $scores->pluck('evaluator_id')->unique()->count(),
                        'expected_evaluators' => $allUsers->count(),
                    ];

                    $commissionScores = DB::table('attestation_commission_scores')
                        ->where('campaign_id', $selected->id)
                        ->whereIn('evaluatee_id', $people->pluck('id'))
                        ->get();
                    $commissionMatrix = [];
                    foreach ($commissionScores as $s) $commissionMatrix[(int)$s->evaluatee_id][(int)$s->commission_member_id] = (int)$s->score;
                    $commissionRows = $people->map(function ($p) use ($commissionMembers, $commissionMatrix) {
                        $vals = collect($commissionMatrix[$p->id] ?? []);
                        return [
                            'user' => $p,
                            'scores' => $commissionMembers->mapWithKeys(fn ($m) => [$m->id => ($commissionMatrix[$p->id][$m->id] ?? null)]),
                            'avg' => $vals->count() ? round($vals->avg(), 2) : null,
                            'count' => $vals->count(),
                            'sum' => $vals->sum(),
                        ];
                    });
                    $commissionAnalytics = [
                        'department_id' => $selectedDepartmentId,
                        'rows' => $commissionRows,
                        'members' => $commissionMembers,
                    ];
                }
            }
        }

        return view('attestation.index', compact(
            'campaigns','selected','departments','assignedDepartments','allUsers','myDepartment','colleagues','myScores','analytics',
            'commissionMembers','commissionTargets','myCommissionScores','commissionAnalytics','isCommissionMember',
            'workDepartmentId','commissionDepartmentId'
        ));
    }

    public function storeCampaign(Request $request)
    {
        abort_unless($request->user()->isManager(), 403);
        $data = $request->validate([
            'title'=>'required|string|max:255','description'=>'nullable|string','starts_at'=>'nullable|date',
            'ends_at'=>'nullable|date|after_or_equal:starts_at','department_ids'=>'required|array|min:1','department_ids.*'=>'integer',
            'commission_member_ids'=>'required|array|min:1','commission_member_ids.*'=>'integer',
        ]);
        $orgId = (int)$request->user()->organization_id;
        DB::transaction(function () use ($data,$request,$orgId) {
            $id = DB::table('attestation_campaigns')->insertGetId([
                'organization_id'=>$orgId,'title'=>$data['title'],'description'=>$data['description']??null,
                'starts_at'=>$data['starts_at']??null,'ends_at'=>$data['ends_at']??null,'is_active'=>true,
                'created_by'=>$request->user()->id,'created_at'=>now(),'updated_at'=>now(),
            ]);
            foreach (array_unique($data['department_ids']) as $depId) {
                $dep = Department::where('id',$depId)->where('is_active',true)->firstOrFail();
                DB::table('attestation_campaign_departments')->insert(['campaign_id'=>$id,'department_id'=>$dep->id,'created_at'=>now(),'updated_at'=>now()]);
            }
            foreach (array_unique($data['commission_member_ids']) as $userId) {
                $member = User::where('id',$userId)->where('is_active',true)->firstOrFail();
                DB::table('attestation_commission_members')->insert([
                    'organization_id'=>$orgId,'campaign_id'=>$id,'user_id'=>$member->id,'created_at'=>now(),'updated_at'=>now()
                ]);
            }
        });
        return back()->with('success','Аттестация создана, состав комиссии назначен.');
    }

    public function saveScores(Request $request, int $campaign)
    {
        $viewer=$request->user();
        $camp=DB::table('attestation_campaigns')->where('id',$campaign)->where('organization_id',$viewer->organization_id)->where('is_active',true)->first();
        abort_unless($camp,404);
        abort_unless($viewer->department_id,422);
        abort_unless(DB::table('attestation_campaign_departments')->where('campaign_id',$campaign)->where('department_id',$viewer->department_id)->exists(),403);
        $data=$request->validate(['scores'=>'required|array','scores.*'=>'nullable|integer|min:1|max:4']);
        $validUsers=User::where('is_active',true)->where('id','<>',$viewer->id)->get(['id','department_id'])->keyBy(fn($u)=>(string)$u->id);
        DB::transaction(function() use($data,$validUsers,$viewer,$campaign){
            foreach($data['scores'] as $id=>$score){
                if($score===null||$score==='') continue;
                $target=$validUsers->get((string)$id); abort_unless($target,422);
                DB::table('attestation_scores')->updateOrInsert(
                    ['campaign_id'=>$campaign,'evaluator_id'=>$viewer->id,'evaluatee_id'=>(int)$id],
                    ['organization_id'=>$viewer->organization_id,'department_id'=>$target->department_id,'score'=>(int)$score,'updated_at'=>now(),'created_at'=>now()]
                );
            }
        });
        return back()->with('success','Оценки рабочих связей сохранены.');
    }

    public function saveCommissionScores(Request $request, int $campaign)
    {
        $viewer=$request->user();
        $camp=DB::table('attestation_campaigns')->where('id',$campaign)->where('organization_id',$viewer->organization_id)->where('is_active',true)->first();
        abort_unless($camp,404);
        abort_unless(DB::table('attestation_commission_members')->where('campaign_id',$campaign)->where('user_id',$viewer->id)->exists(),403);
        $data=$request->validate(['scores'=>'required|array','scores.*'=>'nullable|integer|min:1|max:4']);
        $assignedDepartmentIds=DB::table('attestation_campaign_departments')->where('campaign_id',$campaign)->pluck('department_id');
        $validIds=User::where('is_active',true)->whereIn('department_id',$assignedDepartmentIds)->pluck('id')->map(fn($v)=>(string)$v)->flip();
        DB::transaction(function() use($data,$validIds,$viewer,$campaign){
            foreach($data['scores'] as $id=>$score){
                if($score===null||$score==='') continue;
                abort_unless($validIds->has((string)$id),422);
                DB::table('attestation_commission_scores')->updateOrInsert(
                    ['campaign_id'=>$campaign,'commission_member_id'=>$viewer->id,'evaluatee_id'=>(int)$id],
                    ['organization_id'=>$viewer->organization_id,'score'=>(int)$score,'updated_at'=>now(),'created_at'=>now()]
                );
            }
        });
        return back()->with('success','Оценки аттестационной комиссии сохранены.');
    }
}

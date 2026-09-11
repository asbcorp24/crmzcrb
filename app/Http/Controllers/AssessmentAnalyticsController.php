<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\User;
use App\Services\AccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AssessmentAnalyticsController extends Controller
{
    public function attestation(Request $request, AccessService $access)
    {
        $viewer = $request->user();
        abort_unless($viewer->isManager(), 403);
        $orgId = (int)$viewer->organization_id;

        $campaigns = DB::table('attestation_campaigns')->where('organization_id',$orgId)->orderByDesc('id')->get();
        $campaign = $campaigns->firstWhere('id',(int)$request->query('campaign')) ?: $campaigns->first();
        if (!$campaign) return view('analytics.attestation', ['campaigns'=>$campaigns,'campaign'=>null]);

        $allowedDeptIds = $access->departmentIds($viewer)->map(fn($v)=>(int)$v);
        $assignedDeptIds = DB::table('attestation_campaign_departments')->where('campaign_id',$campaign->id)->pluck('department_id')->map(fn($v)=>(int)$v);
        $deptIds = $assignedDeptIds->intersect($allowedDeptIds)->values();

        $departments = Department::whereIn('id',$deptIds)->orderBy('name')->get()->keyBy('id');
        $targets = User::with('department')->where('is_active',true)->whereIn('department_id',$deptIds)->get();
        $targetIds = $targets->pluck('id');

        $relationScores = DB::table('attestation_scores')->where('campaign_id',$campaign->id)->whereIn('evaluatee_id',$targetIds)->get();
        $commissionScores = DB::table('attestation_commission_scores')->where('campaign_id',$campaign->id)->whereIn('evaluatee_id',$targetIds)->get();
        $memberIds = DB::table('attestation_commission_members')->where('campaign_id',$campaign->id)->pluck('user_id');
        $members = User::with('department')->whereIn('id',$memberIds)->get()->keyBy('id');

        $distribution = fn($rows) => [
            1=>$rows->where('score',1)->count(),
            2=>$rows->where('score',2)->count(),
            3=>$rows->where('score',3)->count(),
            4=>$rows->where('score',4)->count(),
        ];

        $employeeRows = $targets->map(function($u) use($relationScores,$commissionScores){
            $r=$relationScores->where('evaluatee_id',$u->id)->pluck('score');
            $c=$commissionScores->where('evaluatee_id',$u->id)->pluck('score');
            $rAvg=$r->count()?round($r->avg(),2):null;
            $cAvg=$c->count()?round($c->avg(),2):null;
            $parts=collect([$rAvg,$cAvg])->filter(fn($v)=>$v!==null);
            return [
                'user'=>$u,
                'relation_avg'=>$rAvg,'relation_count'=>$r->count(),'relation_sum'=>$r->sum(),
                'commission_avg'=>$cAvg,'commission_count'=>$c->count(),'commission_sum'=>$c->sum(),
                'combined_avg'=>$parts->count()?round($parts->avg(),2):null,
            ];
        })->sortByDesc(fn($r)=>$r['combined_avg']??-1)->values();

        $departmentRows = $deptIds->map(function($deptId) use($departments,$targets,$relationScores,$commissionScores,$members){
            $ids=$targets->where('department_id',$deptId)->pluck('id');
            $r=$relationScores->whereIn('evaluatee_id',$ids);
            $c=$commissionScores->whereIn('evaluatee_id',$ids);
            $employeeCount=$ids->count();
            $possibleCommission=$employeeCount*$members->count();
            return [
                'id'=>$deptId,'name'=>$departments[$deptId]->name??('Подразделение '.$deptId),'employees'=>$employeeCount,
                'relation_avg'=>$r->count()?round($r->avg('score'),2):null,'relation_count'=>$r->count(),'relation_evaluators'=>$r->pluck('evaluator_id')->unique()->count(),
                'commission_avg'=>$c->count()?round($c->avg('score'),2):null,'commission_count'=>$c->count(),
                'commission_completion'=>$possibleCommission?round($c->count()*100/$possibleCommission,1):0,
            ];
        })->values();

        $memberProgress = $members->map(function($m) use($commissionScores,$targets){
            $count=$commissionScores->where('commission_member_id',$m->id)->count();
            $expected=$targets->count();
            return ['user'=>$m,'count'=>$count,'expected'=>$expected,'percent'=>$expected?round($count*100/$expected,1):0];
        })->sortByDesc('percent')->values();

        $relationAvg=$relationScores->count()?round($relationScores->avg('score'),2):null;
        $commissionAvg=$commissionScores->count()?round($commissionScores->avg('score'),2):null;
        $possibleCommission=$targets->count()*$members->count();

        return view('analytics.attestation', compact(
            'campaigns','campaign','departments','targets','members','employeeRows','departmentRows','memberProgress',
            'relationScores','commissionScores','relationAvg','commissionAvg','possibleCommission'
        ) + ['relationDistribution'=>$distribution($relationScores),'commissionDistribution'=>$distribution($commissionScores)]);
    }

    public function questionnaires(Request $request, AccessService $access)
    {
        $viewer=$request->user();
        abort_unless($viewer->isManager(),403);
        $orgId=(int)$viewer->organization_id;
        $questionnaires=DB::table('questionnaires')->where('organization_id',$orgId)->orderByDesc('id')->get();
        $questionnaire=$questionnaires->firstWhere('id',(int)$request->query('questionnaire')) ?: $questionnaires->first();
        if(!$questionnaire) return view('analytics.questionnaires',['questionnaires'=>$questionnaires,'questionnaire'=>null]);

        $schema=json_decode($questionnaire->schema,true)?:[];
        $allowedDeptIds=$access->departmentIds($viewer)->map(fn($v)=>(int)$v);
        $assignedDeptIds=DB::table('questionnaire_departments')->where('questionnaire_id',$questionnaire->id)->pluck('department_id')->map(fn($v)=>(int)$v);
        $deptIds=$assignedDeptIds->intersect($allowedDeptIds)->values();
        $departments=Department::whereIn('id',$deptIds)->orderBy('name')->get()->keyBy('id');
        $eligible=User::where('is_active',true)->whereIn('department_id',$deptIds)->get();
        $responses=DB::table('questionnaire_responses')->where('questionnaire_id',$questionnaire->id)->whereIn('department_id',$deptIds)->orderByDesc('submitted_at')->get();

        $departmentRows=$deptIds->map(function($deptId) use($departments,$eligible,$responses){
            $expected=$eligible->where('department_id',$deptId)->count();
            $submitted=$responses->where('department_id',$deptId)->count();
            return ['id'=>$deptId,'name'=>$departments[$deptId]->name??('Подразделение '.$deptId),'expected'=>$expected,'submitted'=>$submitted,'percent'=>$expected?round($submitted*100/$expected,1):0];
        })->values();

        $questionRows=[];
        $scaleAverages=[];
        foreach($schema as $q){
            $id=$q['id']??null; if(!$id) continue;
            $type=$q['type']??'text'; $values=[]; $peers=[];
            foreach($responses as $r){
                $a=json_decode($r->answers,true)?:[]; $e=$a[$id]??null; if(!$e) continue;
                $v=$e['value']??null; if($v!==null&&$v!==''&&$v!==[]) $values[]=$v;
                if(!empty($e['peer'])) $peers[]=$e['peer'];
            }
            $row=['id'=>$id,'section'=>$q['section']??'','title'=>$q['title']??$id,'type'=>$type,'count'=>count($values),'distribution'=>[],'average'=>null,'peer_mentions'=>[]];
            if($type==='scale'){
                $nums=array_map('intval',$values); $row['average']=count($nums)?round(array_sum($nums)/count($nums),2):null; $row['distribution']=array_count_values($nums);
                if($row['average']!==null)$scaleAverages[]=['title'=>$row['title'],'average'=>$row['average']];
            } elseif(in_array($type,['single','multiple'],true)){
                $counts=[]; foreach($values as $v) foreach((array)$v as $one) $counts[(string)$one]=($counts[(string)$one]??0)+1;
                $labels=[]; foreach($q['options']??[] as $o)$labels[(string)$o['value']]=$o['label'];
                foreach($counts as $key=>$count)$row['distribution'][]=['label'=>$labels[$key]??$key,'count'=>$count,'percent'=>count($values)?round($count*100/count($values),1):0];
            }
            if($peers){$c=[];foreach($peers as $text)foreach(preg_split('/[,;\n]+/u',$text) as $name){$name=trim($name);if(mb_strlen($name)<2)continue;$k=mb_strtolower($name);if(!isset($c[$k]))$c[$k]=['name'=>$name,'count'=>0];$c[$k]['count']++;}usort($c,fn($a,$b)=>$b['count']<=>$a['count']);$row['peer_mentions']=array_slice(array_values($c),0,10);}
            $questionRows[]=$row;
        }

        usort($scaleAverages,fn($a,$b)=>$b['average']<=>$a['average']);
        $expected=$eligible->count(); $submitted=$responses->count();
        return view('analytics.questionnaires',compact('questionnaires','questionnaire','schema','departmentRows','questionRows','responses','eligible','scaleAverages','expected','submitted'));
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Attempt;
use App\Models\Test;
use App\Models\User;
use App\Models\Workshop;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AnalyticsController extends Controller
{
    public function index(Request $request)
    {
        $testId=$request->integer('test');
        $test=$testId?Test::with('questions.options')->find($testId):null;
        $tests=Test::orderByDesc('id')->get();
        $workshops=Workshop::where('is_active',true)->orderBy('name')->get();

        $overview=$tests->map(function($t){
            $submitted=Attempt::where('test_id',$t->id)->where('status','submitted');
            return [
                'id'=>$t->id,'title'=>$t->title,'kind'=>$t->kind,
                'attempts'=>(clone $submitted)->count(),
                'avg_percent'=>round((float)((clone $submitted)->avg('percent')??0),1),
                'passed'=>(clone $submitted)->where('passed',true)->count(),
            ];
        });

        $detail=$test?$this->testDetail($test):null;

        return view('admin.analytics',compact('tests','test','overview','detail','workshops'));
    }

    public function attestation(Request $request)
    {
        $campaignId=$request->integer('campaign');
        $campaigns=DB::table('attestation_campaigns')->orderByDesc('id')->get();
        $campaign=$campaigns->firstWhere('id',$campaignId) ?: $campaigns->first();
        $rows=[];
        $distribution=[1=>0,2=>0,3=>0,4=>0];
        $overall=null;

        if($campaign){
            $scores=DB::table('attestation_scores')->where('campaign_id',$campaign->id)->get();
            $commission=DB::table('attestation_commission_scores')->where('campaign_id',$campaign->id)->get();
            $userIds=$scores->pluck('evaluatee_id')->merge($commission->pluck('evaluatee_id'))->unique();
            $users=User::with('workshop')->whereIn('id',$userIds)->get()->keyBy('id');

            foreach($userIds as $uid){
                $peer=$scores->where('evaluatee_id',$uid)->pluck('score');
                $com=$commission->where('evaluatee_id',$uid)->pluck('score');
                $all=$peer->merge($com);
                $rows[]=[
                    'user'=>$users->get($uid),
                    'peer_avg'=>$peer->count()?round($peer->avg(),2):null,
                    'commission_avg'=>$com->count()?round($com->avg(),2):null,
                    'total_avg'=>$all->count()?round($all->avg(),2):null,
                    'count'=>$all->count(),
                ];
            }
            $allScores=$scores->pluck('score')->merge($commission->pluck('score'));
            foreach([1,2,3,4] as $v)$distribution[$v]=$allScores->where($v)->count();
            $overall=$allScores->count()?round($allScores->avg(),2):null;
        }

        return view('admin.attestation_analytics',compact('campaigns','campaign','rows','distribution','overall'));
    }

    private function testDetail(Test $test): array
    {
        $attempts=Attempt::with(['user.workshop','answers'])->where('test_id',$test->id)->where('status','submitted')->get();
        $byWorkshop=[];
        foreach($test->workshops as $workshop){
            $eligible=User::where('workshop_id',$workshop->id)->where('role','employee')->where('is_active',true)->count();
            $rows=$attempts->where('workshop_id',$workshop->id);
            $byWorkshop[]=[
                'name'=>$workshop->name,'eligible'=>$eligible,'submitted'=>$rows->count(),
                'percent'=>$eligible?round($rows->count()*100/$eligible,1):0,
                'avg'=>round((float)($rows->avg('percent')??0),1),
            ];
        }

        $questions=[];
        foreach($test->questions as $q){
            $answers=DB::table('answers')->where('question_id',$q->id)->get()->map(function($a){
                $a->decoded=json_decode($a->value,true)?:[];
                return $a;
            });
            $stat=['id'=>$q->id,'text'=>$q->text,'type'=>$q->type,'count'=>$answers->count()];
            if($q->type==='scale'){
                $vals=$answers->flatMap(fn($a)=>$a->decoded)->filter(fn($v)=>is_numeric($v))->map(fn($v)=>(int)$v);
                $stat['average']=$vals->count()?round($vals->avg(),2):null;
                $stat['distribution']=$vals->countBy()->sortKeys()->all();
            } elseif(in_array($q->type,['single','multiple'],true)){
                $counts=$answers->flatMap(fn($a)=>$a->decoded)->map(fn($v)=>(string)$v)->countBy();
                $labels=$q->options->pluck('label','value');
                $stat['distribution']=$counts->map(fn($c,$v)=>['label'=>$labels[$v]??$v,'count'=>$c])->values()->all();
            } else {
                $stat['texts']=$answers->flatMap(fn($a)=>$a->decoded)->filter()->take(100)->values()->all();
            }
            $peers=$answers->pluck('peer_text')->filter();
            if($peers->count()) $stat['peer_mentions']=$this->peerMentions($peers->all());
            $questions[]=$stat;
        }

        return ['attempts'=>$attempts,'by_workshop'=>$byWorkshop,'questions'=>$questions];
    }

    private function peerMentions(array $texts): array
    {
        $counts=[];
        foreach($texts as $text){
            foreach(preg_split('/[,;\n]+/u',$text) as $name){
                $name=trim($name); if(mb_strlen($name)<2)continue;
                $key=mb_strtolower($name);
                if(!isset($counts[$key]))$counts[$key]=['name'=>$name,'count'=>0];
                $counts[$key]['count']++;
            }
        }
        usort($counts,fn($a,$b)=>$b['count']<=>$a['count']);
        return array_slice(array_values($counts),0,10);
    }
}

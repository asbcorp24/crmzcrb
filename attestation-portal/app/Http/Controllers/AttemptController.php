<?php

namespace App\Http\Controllers;

use App\Models\Answer;
use App\Models\Attempt;
use App\Models\Question;
use App\Models\Test;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AttemptController extends Controller
{
    public function show(Request $request, Test $test)
    {
        $user=$request->user();
        $this->assertAvailable($test,$user->workshop_id);
        $count=Attempt::where('test_id',$test->id)->where('user_id',$user->id)->where('status','submitted')->count();
        abort_if($count >= $test->attempts_limit,422,'Лимит попыток исчерпан.');

        $attempt=Attempt::where('test_id',$test->id)->where('user_id',$user->id)->where('status','in_progress')->latest('id')->first();
        if(!$attempt){
            $attempt=Attempt::create([
                'test_id'=>$test->id,'user_id'=>$user->id,'workshop_id'=>$user->workshop_id,
                'status'=>'in_progress','started_at'=>now(),
            ]);
        }

        $test->load('questions.options');
        return view('attempts.show',compact('test','attempt','count'));
    }

    public function submit(Request $request, Test $test, Attempt $attempt)
    {
        $user=$request->user();
        abort_unless((int)$attempt->user_id===(int)$user->id && (int)$attempt->test_id===(int)$test->id,403);
        abort_unless($attempt->status==='in_progress',422,'Попытка уже завершена.');
        $this->assertAvailable($test,$user->workshop_id);

        if($test->time_limit_minutes && $attempt->started_at){
            abort_if(now()->gt($attempt->started_at->copy()->addMinutes($test->time_limit_minutes)),422,'Время прохождения истекло.');
        }

        $test->load('questions.options');
        $incoming=$request->input('answers',[]);
        $peer=$request->input('peer',[]);
        $score=0.0;
        $max=0.0;

        DB::transaction(function() use($test,$attempt,$incoming,$peer,&$score,&$max){
            foreach($test->questions as $q){
                $value=$incoming[$q->id]??null;
                if($q->required && ($value===null || $value==='' || $value===[])){
                    abort(422,'Ответьте на обязательный вопрос: '.$q->text);
                }

                $questionScore=0.0;
                if($test->kind==='test'){
                    if(in_array($q->type,['single','multiple'],true)){
                        $correct=$q->options->where('is_correct',true)->pluck('value')->map(fn($v)=>(string)$v)->sort()->values();
                        $given=collect((array)$value)->map(fn($v)=>(string)$v)->sort()->values();
                        $max+=(float)$q->weight;
                        if($correct->isNotEmpty() && $correct->all()===$given->all()) $questionScore=(float)$q->weight;
                    } elseif($q->type==='scale'){
                        $max+=(float)$q->weight;
                        $num=is_numeric($value)?(int)$value:null;
                        if($num!==null && $num>=(int)$q->scale_min && $num<=(int)$q->scale_max){
                            $span=max(1,(int)$q->scale_max-(int)$q->scale_min);
                            $questionScore=((float)$q->weight)*(($num-(int)$q->scale_min)/$span);
                        }
                    }
                }

                $score+=$questionScore;
                Answer::updateOrCreate(
                    ['attempt_id'=>$attempt->id,'question_id'=>$q->id],
                    ['value'=>is_array($value)?$value:[$value],'peer_text'=>trim((string)($peer[$q->id]??''))?:null,'score'=>$questionScore]
                );
            }

            $percent=$test->kind==='test' && $max>0 ? round($score*100/$max,2) : 0;
            $passed=$test->kind==='test' && $test->pass_percent!==null ? $percent>=(float)$test->pass_percent : null;
            $attempt->update([
                'status'=>'submitted','submitted_at'=>now(),'score'=>$score,'max_score'=>$max,
                'percent'=>$percent,'passed'=>$passed,
            ]);
        });

        return redirect()->route('attempt.result',$attempt)->with('success','Ответы сохранены.');
    }

    public function result(Request $request, Attempt $attempt)
    {
        abort_unless((int)$attempt->user_id===(int)$request->user()->id || $request->user()->isSuperAdmin(),403);
        $attempt->load(['test','answers.question.options']);
        return view('attempts.result',compact('attempt'));
    }

    private function assertAvailable(Test $test, $workshopId): void
    {
        abort_unless($test->status==='active',422,'Тест сейчас недоступен.');
        abort_unless($workshopId && $test->workshops()->where('workshops.id',$workshopId)->exists(),403,'Тест не назначен вашему цеху.');
        if($test->opens_at) abort_if(now()->lt($test->opens_at),422,'Тестирование ещё не началось.');
        if($test->closes_at) abort_if(now()->gt($test->closes_at),422,'Тестирование завершено.');
    }
}

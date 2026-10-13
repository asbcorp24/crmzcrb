<?php

namespace App\Http\Controllers;

use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\Test;
use App\Models\Workshop;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class TestController extends Controller
{
    public function index()
    {
        return view('admin.tests.index',[
            'tests'=>Test::with('workshops')->withCount(['questions','attempts'])->orderByDesc('id')->paginate(30),
            'workshops'=>Workshop::where('is_active',true)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data=$request->validate([
            'title'=>'required|string|max:255',
            'description'=>'nullable|string',
            'instructions'=>'nullable|string',
            'kind'=>['required',Rule::in(['test','questionnaire'])],
            'status'=>['required',Rule::in(['draft','active','closed'])],
            'workshop_ids'=>'required|array|min:1',
            'workshop_ids.*'=>'integer|exists:workshops,id',
            'attempts_limit'=>'required|integer|min:1|max:100',
            'time_limit_minutes'=>'nullable|integer|min:1|max:1440',
            'pass_percent'=>'nullable|numeric|min:0|max:100',
            'opens_at'=>'nullable|date',
            'closes_at'=>'nullable|date|after_or_equal:opens_at',
            'is_anonymous'=>'nullable|boolean',
        ]);
        $test=DB::transaction(function() use($data,$request){
            $test=Test::create([
                'created_by'=>$request->user()->id,
                'title'=>$data['title'],
                'description'=>$data['description']??null,
                'instructions'=>$data['instructions']??null,
                'kind'=>$data['kind'],
                'status'=>$data['status'],
                'attempts_limit'=>$data['attempts_limit'],
                'time_limit_minutes'=>$data['time_limit_minutes']??null,
                'pass_percent'=>$data['kind']==='test'?($data['pass_percent']??null):null,
                'opens_at'=>$data['opens_at']??null,
                'closes_at'=>$data['closes_at']??null,
                'is_anonymous'=>$data['kind']==='questionnaire' && $request->boolean('is_anonymous'),
            ]);
            $test->workshops()->sync($data['workshop_ids']);
            return $test;
        });
        return redirect()->route('admin.tests.edit',$test)->with('success','Тест создан. Добавьте вопросы.');
    }

    public function edit(Test $test)
    {
        $test->load(['questions.options','workshops']);
        return view('admin.tests.edit',[
            'test'=>$test,
            'workshops'=>Workshop::where('is_active',true)->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Test $test)
    {
        $data=$request->validate([
            'title'=>'required|string|max:255',
            'description'=>'nullable|string',
            'instructions'=>'nullable|string',
            'kind'=>['required',Rule::in(['test','questionnaire'])],
            'status'=>['required',Rule::in(['draft','active','closed'])],
            'workshop_ids'=>'required|array|min:1',
            'workshop_ids.*'=>'integer|exists:workshops,id',
            'attempts_limit'=>'required|integer|min:1|max:100',
            'time_limit_minutes'=>'nullable|integer|min:1|max:1440',
            'pass_percent'=>'nullable|numeric|min:0|max:100',
            'opens_at'=>'nullable|date',
            'closes_at'=>'nullable|date|after_or_equal:opens_at',
            'is_anonymous'=>'nullable|boolean',
        ]);
        $test->update([
            'title'=>$data['title'],'description'=>$data['description']??null,'instructions'=>$data['instructions']??null,
            'kind'=>$data['kind'],'status'=>$data['status'],'attempts_limit'=>$data['attempts_limit'],
            'time_limit_minutes'=>$data['time_limit_minutes']??null,'pass_percent'=>$data['kind']==='test'?($data['pass_percent']??null):null,
            'opens_at'=>$data['opens_at']??null,'closes_at'=>$data['closes_at']??null,
            'is_anonymous'=>$data['kind']==='questionnaire' && $request->boolean('is_anonymous'),
        ]);
        $test->workshops()->sync($data['workshop_ids']);
        return back()->with('success','Настройки сохранены.');
    }

    public function addQuestion(Request $request, Test $test)
    {
        $data=$request->validate([
            'section'=>'nullable|string|max:255',
            'text'=>'required|string|max:5000',
            'type'=>['required',Rule::in(['single','multiple','scale','text'])],
            'required'=>'nullable|boolean',
            'weight'=>'nullable|numeric|min:0|max:1000',
            'scale_min'=>'nullable|integer|min:0|max:100',
            'scale_max'=>'nullable|integer|min:1|max:100',
            'peer_field'=>'nullable|boolean',
            'options_text'=>'nullable|string',
            'correct_values'=>'nullable|string',
        ]);
        $question=DB::transaction(function() use($data,$request,$test){
            $q=Question::create([
                'test_id'=>$test->id,'section'=>$data['section']??null,'text'=>$data['text'],'type'=>$data['type'],
                'required'=>$request->boolean('required'),'weight'=>$data['weight']??1,
                'scale_min'=>$data['type']==='scale'?($data['scale_min']??1):null,
                'scale_max'=>$data['type']==='scale'?($data['scale_max']??4):null,
                'peer_field'=>$request->boolean('peer_field'),
                'sort_order'=>(int)$test->questions()->max('sort_order')+1,
            ]);
            if(in_array($data['type'],['single','multiple'],true)){
                $correct=collect(preg_split('/[,;\n]+/',(string)($data['correct_values']??'')))->map(fn($v)=>trim($v))->filter()->flip();
                $lines=preg_split('/\r?\n/',trim((string)($data['options_text']??'')));
                foreach($lines as $i=>$line){
                    $line=trim($line); if($line==='') continue;
                    [$value,$label]=array_pad(array_map('trim',explode('|',$line,2)),2,null);
                    if($label===null){$label=$value;$value=(string)($i+1);}
                    QuestionOption::create([
                        'question_id'=>$q->id,'value'=>$value,'label'=>$label,
                        'is_correct'=>$correct->has($value),'score'=>$correct->has($value)?($data['weight']??1):0,'sort_order'=>$i,
                    ]);
                }
            }
            return $q;
        });
        return back()->with('success','Вопрос добавлен.');
    }

    public function updateQuestion(Request $request, Test $test, Question $question)
    {
        abort_unless((int)$question->test_id===(int)$test->id,404);
        $data=$request->validate([
            'section'=>'nullable|string|max:255',
            'text'=>'required|string|max:5000',
            'type'=>['required',Rule::in(['single','multiple','scale','text'])],
            'required'=>'nullable|boolean',
            'weight'=>'nullable|numeric|min:0|max:1000',
            'scale_min'=>'nullable|integer|min:0|max:100',
            'scale_max'=>'nullable|integer|min:1|max:100',
            'peer_field'=>'nullable|boolean',
            'options_text'=>'nullable|string',
            'correct_values'=>'nullable|string',
        ]);

        DB::transaction(function() use($data,$request,$question){
            $question->update([
                'section'=>$data['section']??null,
                'text'=>$data['text'],
                'type'=>$data['type'],
                'required'=>$request->boolean('required'),
                'weight'=>$data['weight']??1,
                'scale_min'=>$data['type']==='scale'?($data['scale_min']??1):null,
                'scale_max'=>$data['type']==='scale'?($data['scale_max']??4):null,
                'peer_field'=>$request->boolean('peer_field'),
            ]);

            $question->options()->delete();
            if(in_array($data['type'],['single','multiple'],true)){
                $correct=collect(preg_split('/[,;\\n]+/',(string)($data['correct_values']??'')))
                    ->map(fn($v)=>trim($v))->filter()->flip();
                $lines=preg_split('/\\r?\\n/',trim((string)($data['options_text']??'')));
                foreach($lines as $i=>$line){
                    $line=trim($line); if($line==='') continue;
                    [$value,$label]=array_pad(array_map('trim',explode('|',$line,2)),2,null);
                    if($label===null){$label=$value;$value=(string)($i+1);}
                    QuestionOption::create([
                        'question_id'=>$question->id,
                        'value'=>$value,'label'=>$label,
                        'is_correct'=>$correct->has($value),
                        'score'=>$correct->has($value)?($data['weight']??1):0,
                        'sort_order'=>$i,
                    ]);
                }
            }
        });

        return back()->with('success','Вопрос обновлён.');
    }

    public function deleteQuestion(Test $test, Question $question)
    {
        abort_unless((int)$question->test_id===(int)$test->id,404);
        $question->delete();
        return back()->with('success','Вопрос удалён.');
    }
}

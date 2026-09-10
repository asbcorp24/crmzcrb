<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\User;
use App\Services\AccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class QuestionnaireController extends Controller
{
    public function page(Request $request, AccessService $access)
    {
        $user = $request->user();
        $orgId = (int)$user->organization_id;
        $manageableDepartments = $access->departmentIds($user)->map(fn($v)=>(int)$v);

        $questionnaires = DB::table('questionnaires')
            ->where('organization_id', $orgId)
            ->orderByDesc('id')
            ->get()
            ->map(function ($q) use ($user, $manageableDepartments) {
                $q->schema = json_decode($q->schema, true) ?: [];
                $assigned = DB::table('questionnaire_departments')->where('questionnaire_id', $q->id)->pluck('department_id')->map(fn($v)=>(int)$v);
                $q->assigned_department_ids = $assigned->all();
                $q->available_to_user = $user->department_id && $assigned->contains((int)$user->department_id);
                $q->can_manage = $user->isAdmin() || ($user->isManager() && $assigned->intersect($manageableDepartments)->isNotEmpty());
                $q->my_response = DB::table('questionnaire_responses')->where('questionnaire_id', $q->id)->where('user_id', $user->id)->first();
                return $q;
            });

        $departments = Department::where('is_active', true)
            ->whereIn('id', $manageableDepartments)
            ->orderBy('sort_order')->orderBy('name')->get();

        $selected = null;
        if ($request->filled('questionnaire')) {
            $selected = $questionnaires->firstWhere('id', (int)$request->questionnaire);
        }
        if (!$selected) $selected = $questionnaires->first();

        $analytics = $selected && ($user->isAdmin() || $user->isManager())
            ? $this->buildAnalytics((int)$selected->id, $user, $access)
            : null;

        return view('questionnaires.index', compact('questionnaires','departments','selected','analytics'));
    }

    public function store(Request $request, AccessService $access)
    {
        $user = $request->user();
        abort_unless($user->isManager(), 403);

        $data = $request->validate([
            'title' => ['required','string','max:255'],
            'description' => ['nullable','string'],
            'instructions' => ['nullable','string'],
            'status' => ['required', Rule::in(['draft','active','closed'])],
            'is_anonymous' => ['nullable','boolean'],
            'opens_at' => ['nullable','date'],
            'closes_at' => ['nullable','date'],
            'schema' => ['required','string'],
            'department_ids' => ['required','array','min:1'],
            'department_ids.*' => ['integer'],
        ]);

        $schema = json_decode($data['schema'], true);
        abort_unless(is_array($schema) && count($schema) > 0, 422, 'Добавьте хотя бы один вопрос.');
        $this->validateSchema($schema);

        $allowed = $access->departmentIds($user)->map(fn($v)=>(int)$v);
        $departmentIds = collect($data['department_ids'])->map(fn($v)=>(int)$v)->unique();
        abort_if($departmentIds->diff($allowed)->isNotEmpty(), 403, 'Нет доступа к одному из подразделений.');

        $id = DB::transaction(function () use ($user, $data, $schema, $departmentIds) {
            $id = DB::table('questionnaires')->insertGetId([
                'organization_id' => $user->organization_id,
                'created_by' => $user->id,
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'instructions' => $data['instructions'] ?? null,
                'status' => $data['status'],
                'is_anonymous' => (bool)($data['is_anonymous'] ?? false),
                'opens_at' => $data['opens_at'] ?? null,
                'closes_at' => $data['closes_at'] ?? null,
                'schema' => json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'created_at' => now(), 'updated_at' => now(),
            ]);
            foreach ($departmentIds as $departmentId) {
                DB::table('questionnaire_departments')->insert([
                    'organization_id' => $user->organization_id,
                    'questionnaire_id' => $id,
                    'department_id' => $departmentId,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
            return $id;
        });

        return redirect()->route('questionnaires.page', ['questionnaire'=>$id])->with('success','Анкета создана.');
    }

    public function update(Request $request, int $questionnaire, AccessService $access)
    {
        $user = $request->user();
        abort_unless($user->isManager(), 403);
        $q = $this->findQuestionnaire($questionnaire, (int)$user->organization_id);

        $data = $request->validate([
            'title' => ['required','string','max:255'],
            'description' => ['nullable','string'],
            'instructions' => ['nullable','string'],
            'status' => ['required', Rule::in(['draft','active','closed'])],
            'is_anonymous' => ['nullable','boolean'],
            'schema' => ['required','string'],
            'department_ids' => ['required','array','min:1'],
            'department_ids.*' => ['integer'],
        ]);
        $schema = json_decode($data['schema'], true);
        abort_unless(is_array($schema) && count($schema) > 0, 422, 'Некорректная структура анкеты.');
        $this->validateSchema($schema);
        $allowed = $access->departmentIds($user)->map(fn($v)=>(int)$v);
        $departmentIds = collect($data['department_ids'])->map(fn($v)=>(int)$v)->unique();
        abort_if($departmentIds->diff($allowed)->isNotEmpty(), 403);

        DB::transaction(function () use ($q,$data,$schema,$departmentIds,$user) {
            DB::table('questionnaires')->where('id',$q->id)->update([
                'title'=>$data['title'],'description'=>$data['description']??null,'instructions'=>$data['instructions']??null,
                'status'=>$data['status'],'is_anonymous'=>(bool)($data['is_anonymous']??false),
                'schema'=>json_encode($schema, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),'updated_at'=>now(),
            ]);
            DB::table('questionnaire_departments')->where('questionnaire_id',$q->id)->delete();
            foreach($departmentIds as $departmentId) DB::table('questionnaire_departments')->insert([
                'organization_id'=>$user->organization_id,'questionnaire_id'=>$q->id,'department_id'=>$departmentId,'created_at'=>now(),'updated_at'=>now()
            ]);
        });
        return redirect()->route('questionnaires.page',['questionnaire'=>$q->id])->with('success','Анкета обновлена.');
    }

    public function submit(Request $request, int $questionnaire)
    {
        $user = $request->user();
        $q = $this->findQuestionnaire($questionnaire, (int)$user->organization_id);
        abort_unless($q->status === 'active', 422, 'Анкета сейчас недоступна.');
        if ($q->opens_at) abort_if(now()->lt($q->opens_at), 422, 'Анкетирование ещё не началось.');
        if ($q->closes_at) abort_if(now()->gt($q->closes_at), 422, 'Анкетирование завершено.');

        $assigned = DB::table('questionnaire_departments')->where('questionnaire_id',$q->id)->where('department_id',$user->department_id)->exists();
        abort_unless($assigned, 403, 'Анкета не назначена вашему подразделению.');

        $schema = json_decode($q->schema,true) ?: [];
        $incoming = $request->input('answers', []);
        $answers = [];
        foreach ($schema as $question) {
            $id = (string)($question['id'] ?? '');
            $value = $incoming[$id] ?? null;
            if (($question['required'] ?? false) && ($value === null || $value === '' || $value === [])) {
                return back()->withErrors(['answers'=>'Ответьте на обязательный вопрос: '.($question['title'] ?? $id)])->withInput();
            }
            if (($question['type'] ?? '') === 'scale' && $value !== null && $value !== '') {
                $min=(int)($question['min']??0); $max=(int)($question['max']??3);
                abort_if(!is_numeric($value) || (int)$value<$min || (int)$value>$max,422,'Некорректное значение шкалы.');
                $value=(int)$value;
            }
            if (($question['type'] ?? '') === 'multiple') $value = is_array($value) ? array_values($value) : [];
            $answers[$id] = ['value'=>$value];
            if (!empty($question['peer_field'])) $answers[$id]['peer'] = trim((string)($incoming[$id.'_peer'] ?? ''));
        }

        DB::table('questionnaire_responses')->updateOrInsert(
            ['questionnaire_id'=>$q->id,'user_id'=>$user->id],
            [
                'organization_id'=>$user->organization_id,
                'department_id'=>$user->department_id,
                'respondent_name'=>$q->is_anonymous ? null : ($user->full_name ?? trim($user->last_name.' '.$user->first_name.' '.$user->middle_name)),
                'position'=>$q->is_anonymous ? null : $user->position,
                'answers'=>json_encode($answers,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
                'submitted_at'=>now(),'created_at'=>now(),'updated_at'=>now(),
            ]
        );
        return redirect()->route('questionnaires.page',['questionnaire'=>$q->id])->with('success','Анкета сохранена. Спасибо за участие.');
    }

    private function buildAnalytics(int $questionnaireId, User $viewer, AccessService $access): array
    {
        $q = $this->findQuestionnaire($questionnaireId, (int)$viewer->organization_id);
        $schema = json_decode($q->schema,true) ?: [];
        $visibleDeptIds = $access->departmentIds($viewer)->map(fn($v)=>(int)$v);
        $assignedDeptIds = DB::table('questionnaire_departments')->where('questionnaire_id',$q->id)->pluck('department_id')->map(fn($v)=>(int)$v);
        $deptIds = $assignedDeptIds->intersect($visibleDeptIds)->values();

        $responses = DB::table('questionnaire_responses')->where('questionnaire_id',$q->id)->whereIn('department_id',$deptIds)->get();
        $departments = Department::whereIn('id',$deptIds)->get()->keyBy('id');
        $eligible = User::where('is_active',true)->whereIn('department_id',$deptIds)->get()->groupBy('department_id');

        $byDepartment=[];
        foreach($deptIds as $deptId){
            $submitted=$responses->where('department_id',$deptId)->count();
            $expected=($eligible[$deptId]??collect())->count();
            $byDepartment[]=['id'=>$deptId,'name'=>$departments[$deptId]->name??('Подразделение '.$deptId),'expected'=>$expected,'submitted'=>$submitted,'percent'=>$expected?round($submitted*100/$expected,1):0];
        }

        $questionStats=[];
        foreach($schema as $question){
            $qid=$question['id']??null; if(!$qid) continue;
            $type=$question['type']??'text'; $values=[]; $peers=[];
            foreach($responses as $response){
                $a=json_decode($response->answers,true)?:[]; $entry=$a[$qid]??null; if(!$entry) continue;
                $v=$entry['value']??null; if($v!==null && $v!=='') $values[]=$v;
                if(!empty($entry['peer'])) $peers[]=$entry['peer'];
            }
            $stat=['id'=>$qid,'title'=>$question['title']??$qid,'section'=>$question['section']??'','type'=>$type,'count'=>count($values)];
            if($type==='scale'){
                $nums=array_map('intval',$values); $stat['average']=count($nums)?round(array_sum($nums)/count($nums),2):null;
                $stat['distribution']=array_count_values($nums);
            } elseif(in_array($type,['single','multiple'],true)){
                $counts=[];
                foreach($values as $v){ foreach((array)$v as $one) $counts[(string)$one]=($counts[(string)$one]??0)+1; }
                $labels=[]; foreach($question['options']??[] as $o) $labels[(string)$o['value']]=$o['label'];
                $stat['distribution']=collect($counts)->map(fn($count,$key)=>['label'=>$labels[$key]??$key,'count'=>$count])->values()->all();
            }
            if($peers) $stat['peer_mentions']=$this->peerMentions($peers);
            $questionStats[]=$stat;
        }

        return ['responses'=>$responses->count(),'departments'=>$byDepartment,'questions'=>$questionStats,'schema'=>$schema];
    }

    private function peerMentions(array $peers): array
    {
        $counts=[];
        foreach($peers as $text){
            foreach(preg_split('/[,;\n]+/u',$text) as $name){
                $name=trim($name); if(mb_strlen($name)<2) continue;
                $key=mb_strtolower($name); if(!isset($counts[$key])) $counts[$key]=['name'=>$name,'count'=>0];
                $counts[$key]['count']++;
            }
        }
        usort($counts,fn($a,$b)=>$b['count']<=>$a['count']);
        return array_slice(array_values($counts),0,10);
    }

    private function validateSchema(array $schema): void
    {
        $ids=[];
        foreach($schema as $i=>$q){
            abort_unless(is_array($q),422,'Некорректный вопрос.');
            $id=(string)($q['id']??''); $title=trim((string)($q['title']??'')); $type=$q['type']??'';
            abort_if($id===''||$title==='',422,'У каждого вопроса должны быть ID и текст.');
            abort_if(isset($ids[$id]),422,'ID вопросов должны быть уникальными.'); $ids[$id]=true;
            abort_unless(in_array($type,['single','multiple','scale','text'],true),422,'Неизвестный тип вопроса.');
            if(in_array($type,['single','multiple'],true)) abort_unless(!empty($q['options'])&&is_array($q['options']),422,'Для выбора добавьте варианты ответа.');
        }
    }

    private function findQuestionnaire(int $id, int $orgId)
    {
        $q=DB::table('questionnaires')->where('id',$id)->where('organization_id',$orgId)->first();
        abort_unless($q,404); return $q;
    }
}

@extends('layouts.app')
@section('title','Анкетирование — CRM')
@section('header','Анкетирование')

@push('styles')
<style>
.question-card{border:1px solid #e4e7ec;border-radius:14px}.question-card+.question-card{margin-top:12px}.analytics-bar{height:9px;background:#e9ecef;border-radius:99px;overflow:hidden}.analytics-bar>span{display:block;height:100%;background:#0d6efd}.builder-question{border:1px dashed #cbd5e1;border-radius:12px;padding:12px;margin-bottom:10px}.sticky-tabs{position:sticky;top:58px;z-index:1000;background:#f4f6f9;padding-top:4px}.scale-grid{display:grid;grid-template-columns:repeat(4,minmax(68px,1fr));gap:8px}.scale-option{border:1px solid #dee2e6;border-radius:10px;padding:9px;text-align:center}.scale-option input{margin-right:4px}@media(max-width:767px){.scale-grid{grid-template-columns:repeat(2,1fr)}}
</style>
@endpush

@section('content')
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if($errors->any())<div class="alert alert-danger"><b>Проверьте данные:</b><ul class="mb-0 mt-1">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

<div class="row g-3">
  <div class="col-xl-3">
    <div class="card border-0 shadow-sm"><div class="card-header bg-white d-flex align-items-center"><b>Анкеты</b>@if(auth()->user()->isManager())<button class="btn btn-sm btn-primary ms-auto" data-bs-toggle="modal" data-bs-target="#builderModal"><i class="bi bi-plus-lg"></i></button>@endif</div>
      <div class="list-group list-group-flush">
      @forelse($questionnaires as $q)
        <a class="list-group-item list-group-item-action {{ $selected && $selected->id==$q->id?'active':'' }}" href="{{ route('questionnaires.page',['questionnaire'=>$q->id]) }}">
          <div class="fw-semibold">{{ $q->title }}</div><div class="small {{ $selected && $selected->id==$q->id?'text-white-50':'text-muted' }}">{{ ['draft'=>'Черновик','active'=>'Активна','closed'=>'Закрыта'][$q->status]??$q->status }} · {{ count($q->schema) }} вопросов</div>
        </a>
      @empty <div class="p-3 text-muted">Анкет пока нет.</div>@endforelse
      </div>
    </div>
  </div>

  <div class="col-xl-9">
  @if($selected)
    <div class="card border-0 shadow-sm mb-3"><div class="card-body">
      <div class="d-flex flex-wrap gap-2 align-items-start"><div><h4 class="mb-1">{{ $selected->title }}</h4><div class="text-muted">{{ $selected->description }}</div></div><span class="badge ms-auto {{ $selected->status==='active'?'text-bg-success':($selected->status==='closed'?'text-bg-secondary':'text-bg-warning') }}">{{ ['draft'=>'Черновик','active'=>'Активна','closed'=>'Закрыта'][$selected->status]??$selected->status }}</span></div>
      @if($selected->instructions)<div class="alert alert-light border mt-3 mb-0">{{ $selected->instructions }}</div>@endif
    </div></div>

    <ul class="nav nav-tabs sticky-tabs mb-3" role="tablist">
      @if($selected->available_to_user)<li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#surveyTab">Пройти анкету</button></li>@endif
      @if(auth()->user()->isManager())<li class="nav-item"><button class="nav-link {{ !$selected->available_to_user?'active':'' }}" data-bs-toggle="tab" data-bs-target="#analyticsTab">Свод и аналитика</button></li>@endif
      @if($selected->can_manage)<li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#manageTab">Настройки</button></li>@endif
    </ul>

    <div class="tab-content">
      @if($selected->available_to_user)
      <div class="tab-pane fade show active" id="surveyTab">
        @if($selected->my_response)<div class="alert alert-info"><i class="bi bi-check-circle me-1"></i>Вы уже заполняли эту анкету. Повторная отправка обновит ваши ответы.</div>@endif
        <form method="POST" action="{{ route('questionnaires.submit',$selected->id) }}">@csrf
        @php $currentSection=null; $oldAnswers=old('answers',[]); @endphp
        @foreach($selected->schema as $q)
          @if(($q['section']??null)!==$currentSection) @php $currentSection=$q['section']??null; @endphp @if($currentSection)<h5 class="mt-4 mb-3">Раздел {{ $currentSection }}</h5>@endif @endif
          <div class="question-card bg-white p-3">
            <div class="fw-semibold mb-1">{{ $q['title']??'' }} @if($q['required']??false)<span class="text-danger">*</span>@endif</div>
            @if(!empty($q['description']))<div class="small text-muted mb-3">{{ $q['description'] }}</div>@endif
            @php $qid=$q['id']; $existing=$oldAnswers[$qid]??null; @endphp
            @if(($q['type']??'')==='single')
              @foreach($q['options']??[] as $o)<div class="form-check my-2"><input class="form-check-input" type="radio" name="answers[{{ $qid }}]" value="{{ $o['value'] }}" id="{{ $qid }}_{{ $loop->index }}" {{ (string)$existing===(string)$o['value']?'checked':'' }}><label class="form-check-label" for="{{ $qid }}_{{ $loop->index }}">{{ $o['label'] }}</label></div>@endforeach
            @elseif(($q['type']??'')==='multiple')
              @foreach($q['options']??[] as $o)<div class="form-check my-2"><input class="form-check-input" type="checkbox" name="answers[{{ $qid }}][]" value="{{ $o['value'] }}" id="{{ $qid }}_{{ $loop->index }}" {{ in_array($o['value'],(array)$existing,true)?'checked':'' }}><label class="form-check-label" for="{{ $qid }}_{{ $loop->index }}">{{ $o['label'] }}</label></div>@endforeach
            @elseif(($q['type']??'')==='scale')
              <div class="scale-grid mt-3">@for($n=(int)($q['min']??0);$n<=(int)($q['max']??3);$n++)<label class="scale-option"><input type="radio" name="answers[{{ $qid }}]" value="{{ $n }}" {{ (string)$existing===(string)$n?'checked':'' }}><b>{{ $n }}</b><div class="small text-muted">{{ $q['labels'][(string)$n]??'' }}</div></label>@endfor</div>
              @if(!empty($q['peer_field']))<div class="mt-3"><label class="form-label small">{{ $q['peer_prompt']??'Кого вы считаете сильным в этой компетенции?' }}</label><input class="form-control" name="answers[{{ $qid }}_peer]" value="{{ $oldAnswers[$qid.'_peer']??'' }}" placeholder="ФИО через запятую"></div>@endif
            @else
              <textarea class="form-control mt-2" name="answers[{{ $qid }}]" rows="3">{{ $existing }}</textarea>
            @endif
          </div>
        @endforeach
        <button class="btn btn-primary mt-3"><i class="bi bi-send me-1"></i>Сохранить ответы</button>
        </form>
      </div>
      @endif

      @if(auth()->user()->isManager())
      <div class="tab-pane fade {{ !$selected->available_to_user?'show active':'' }}" id="analyticsTab">
        <div class="row g-3 mb-3"><div class="col-md-4"><div class="card border-0 shadow-sm"><div class="card-body"><div class="small text-muted">Заполнено анкет</div><div class="display-6 fw-semibold">{{ $analytics['responses']??0 }}</div></div></div></div></div>
        <div class="card border-0 shadow-sm mb-3"><div class="card-header bg-white"><b>Участие по подразделениям</b></div><div class="card-body">
          @forelse($analytics['departments']??[] as $d)<div class="mb-3"><div class="d-flex justify-content-between"><span>{{ $d['name'] }}</span><b>{{ $d['submitted'] }}/{{ $d['expected'] }} · {{ $d['percent'] }}%</b></div><div class="analytics-bar mt-1"><span style="width:{{ min(100,$d['percent']) }}%"></span></div></div>@empty<div class="text-muted">Нет доступных подразделений.</div>@endforelse
        </div></div>

        <div class="card border-0 shadow-sm"><div class="card-header bg-white"><b>Результаты по вопросам</b></div><div class="card-body">
        @foreach($analytics['questions']??[] as $s)
          <div class="border-bottom pb-3 mb-3"><div class="fw-semibold">{{ $s['section']?('Раздел '.$s['section'].' · '):'' }}{{ $s['title'] }}</div><div class="small text-muted mb-2">Ответов: {{ $s['count'] }}</div>
          @if($s['type']==='scale')
            <div class="d-flex align-items-center gap-3"><div class="display-6">{{ $s['average']===null?'—':$s['average'] }}</div><div class="text-muted">средний балл из 3</div></div>
            <div class="d-flex flex-wrap gap-2 mt-2">@for($n=0;$n<=3;$n++)<span class="badge text-bg-light border">{{ $n }}: {{ $s['distribution'][$n]??0 }}</span>@endfor</div>
          @elseif(in_array($s['type'],['single','multiple']))
            @foreach($s['distribution']??[] as $v)<div class="d-flex justify-content-between gap-3 small py-1"><span>{{ $v['label'] }}</span><b>{{ $v['count'] }}</b></div>@endforeach
          @endif
          @if(!empty($s['peer_mentions']))<div class="mt-2"><span class="small text-muted">Чаще всего отмечали:</span> @foreach($s['peer_mentions'] as $m)<span class="badge text-bg-info me-1">{{ $m['name'] }} · {{ $m['count'] }}</span>@endforeach</div>@endif
          </div>
        @endforeach
        </div></div>
      </div>
      @endif

      @if($selected->can_manage)
      <div class="tab-pane fade" id="manageTab"><div class="card border-0 shadow-sm"><div class="card-body">
        <h5>Редактирование анкеты</h5><p class="text-muted">Измените вопросы, статус и подразделения. Уже полученные ответы сохраняются.</p>
        <button class="btn btn-outline-primary" type="button" onclick='openExistingBuilder(@json($selected))'><i class="bi bi-pencil me-1"></i>Открыть конструктор</button>
      </div></div></div>
      @endif
    </div>
  @else
    <div class="card border-0 shadow-sm"><div class="card-body text-center py-5"><i class="bi bi-ui-checks-grid display-5 text-primary"></i><h4 class="mt-3">Анкетирование</h4><p class="text-muted">Создайте первую анкету и назначьте её подразделениям.</p>@if(auth()->user()->isManager())<button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#builderModal">Создать анкету</button>@endif</div></div>
  @endif
  </div>
</div>

@if(auth()->user()->isManager())
<div class="modal fade" id="builderModal" tabindex="-1"><div class="modal-dialog modal-xl modal-dialog-scrollable"><form class="modal-content" method="POST" id="builderForm" action="{{ route('questionnaires.store') }}">@csrf<input type="hidden" name="_method" id="builderMethod" value="POST"><input type="hidden" name="schema" id="builderSchema"><div class="modal-header"><h5 class="modal-title">Конструктор анкеты</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body">
<div class="row g-3"><div class="col-md-8"><label class="form-label">Название</label><input name="title" id="builderTitle" class="form-control" required></div><div class="col-md-4"><label class="form-label">Статус</label><select name="status" id="builderStatus" class="form-select"><option value="draft">Черновик</option><option value="active">Активна</option><option value="closed">Закрыта</option></select></div><div class="col-12"><label class="form-label">Описание</label><textarea name="description" id="builderDescription" class="form-control" rows="2"></textarea></div><div class="col-12"><label class="form-label">Инструкция</label><textarea name="instructions" id="builderInstructions" class="form-control" rows="2"></textarea></div><div class="col-12"><label class="form-label d-block">Подразделения</label><div class="row">@foreach($departments as $d)<div class="col-md-4"><label class="form-check"><input class="form-check-input builder-dept" type="checkbox" name="department_ids[]" value="{{ $d->id }}"><span class="form-check-label">{{ $d->name }}</span></label></div>@endforeach</div></div><div class="col-12"><label class="form-check"><input class="form-check-input" type="checkbox" name="is_anonymous" id="builderAnonymous" value="1"><span class="form-check-label">Анонимная анкета</span></label></div></div><hr><div class="d-flex align-items-center mb-2"><h6 class="mb-0">Вопросы</h6><div class="ms-auto btn-group"><button type="button" class="btn btn-sm btn-outline-primary" onclick="addQuestion('single')">Один ответ</button><button type="button" class="btn btn-sm btn-outline-primary" onclick="addQuestion('multiple')">Несколько</button><button type="button" class="btn btn-sm btn-outline-primary" onclick="addQuestion('scale')">Шкала 0–3</button><button type="button" class="btn btn-sm btn-outline-primary" onclick="addQuestion('text')">Текст</button></div></div><div id="builderQuestions"></div>
</div><div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Отмена</button><button class="btn btn-primary" onclick="return serializeBuilder()">Сохранить анкету</button></div></form></div></div>
@endif
@endsection

@push('scripts')
<script>
let builderQuestions=[];
function uid(){return 'q_'+Date.now().toString(36)+'_'+Math.random().toString(36).slice(2,7)}
function addQuestion(type,data=null){const q=data||{id:uid(),section:'',type,title:'',description:'',required:false,options:type==='single'||type==='multiple'?[{value:'1',label:'Вариант 1'},{value:'2',label:'Вариант 2'}]:[],min:0,max:3,peer_field:false,peer_prompt:''};builderQuestions.push(q);renderBuilder()}
function renderBuilder(){let h='';builderQuestions.forEach((q,i)=>{h+=`<div class="builder-question"><div class="d-flex gap-2 align-items-center mb-2"><span class="badge text-bg-secondary">${i+1}</span><select class="form-select form-select-sm" style="max-width:170px" onchange="builderQuestions[${i}].type=this.value;renderBuilder()"><option value="single" ${q.type==='single'?'selected':''}>Один ответ</option><option value="multiple" ${q.type==='multiple'?'selected':''}>Несколько ответов</option><option value="scale" ${q.type==='scale'?'selected':''}>Шкала 0–3</option><option value="text" ${q.type==='text'?'selected':''}>Текст</option></select><input class="form-control form-control-sm" style="max-width:90px" placeholder="Раздел" value="${escB(q.section||'')}" onchange="builderQuestions[${i}].section=this.value"><button type="button" class="btn btn-sm btn-outline-danger ms-auto" onclick="builderQuestions.splice(${i},1);renderBuilder()"><i class="bi bi-trash"></i></button></div><input class="form-control mb-2" placeholder="Текст вопроса" value="${escB(q.title||'')}" onchange="builderQuestions[${i}].title=this.value"><textarea class="form-control form-control-sm mb-2" placeholder="Описание (необязательно)" onchange="builderQuestions[${i}].description=this.value">${escB(q.description||'')}</textarea><label class="form-check mb-2"><input class="form-check-input" type="checkbox" ${q.required?'checked':''} onchange="builderQuestions[${i}].required=this.checked"><span class="form-check-label">Обязательный вопрос</span></label>`;
if(q.type==='single'||q.type==='multiple'){h+='<div class="small fw-semibold mb-1">Варианты ответа</div>';(q.options||[]).forEach((o,j)=>{h+=`<div class="input-group input-group-sm mb-1"><input class="form-control" value="${escB(o.label||'')}" onchange="builderQuestions[${i}].options[${j}].label=this.value"><button type="button" class="btn btn-outline-danger" onclick="builderQuestions[${i}].options.splice(${j},1);renderBuilder()">×</button></div>`});h+=`<button type="button" class="btn btn-sm btn-light border" onclick="builderQuestions[${i}].options=builderQuestions[${i}].options||[];builderQuestions[${i}].options.push({value:String(builderQuestions[${i}].options.length+1),label:'Новый вариант'});renderBuilder()">+ вариант</button>`}
if(q.type==='scale'){h+=`<label class="form-check mt-2"><input class="form-check-input" type="checkbox" ${q.peer_field?'checked':''} onchange="builderQuestions[${i}].peer_field=this.checked;renderBuilder()"><span class="form-check-label">Добавить поле для указания сильных коллег</span></label>`;if(q.peer_field)h+=`<input class="form-control form-control-sm mt-2" value="${escB(q.peer_prompt||'')}" placeholder="Текст дополнительного поля" onchange="builderQuestions[${i}].peer_prompt=this.value">`}
h+='</div>'});$('#builderQuestions').html(h)}
function escB(v){return $('<div>').text(v??'').html().replace(/"/g,'&quot;')}
function serializeBuilder(){for(const q of builderQuestions){if(!String(q.title||'').trim()){alert('Заполните текст всех вопросов');return false}if((q.type==='single'||q.type==='multiple')&&(!q.options||!q.options.length)){alert('Добавьте варианты ответа');return false}if(q.options)q.options=q.options.map((o,j)=>({...o,value:String(o.value||j+1)}))}if(!builderQuestions.length){alert('Добавьте хотя бы один вопрос');return false}$('#builderSchema').val(JSON.stringify(builderQuestions));return true}
function openExistingBuilder(q){builderQuestions=q.schema||[];$('#builderTitle').val(q.title||'');$('#builderDescription').val(q.description||'');$('#builderInstructions').val(q.instructions||'');$('#builderStatus').val(q.status||'draft');$('#builderAnonymous').prop('checked',!!q.is_anonymous);$('.builder-dept').prop('checked',false);(q.assigned_department_ids||[]).forEach(id=>$(`.builder-dept[value="${id}"]`).prop('checked',true));$('#builderForm').attr('action',`{{ url('/questionnaires') }}/${q.id}`);$('#builderMethod').val('PATCH');renderBuilder();bootstrap.Modal.getOrCreateInstance(document.getElementById('builderModal')).show()}
document.getElementById('builderModal')?.addEventListener('show.bs.modal',e=>{if(e.relatedTarget){$('#builderForm').attr('action','{{ route('questionnaires.store') }}');$('#builderMethod').val('POST');$('#builderTitle,#builderDescription,#builderInstructions').val('');$('#builderStatus').val('draft');$('#builderAnonymous,.builder-dept').prop('checked',false);builderQuestions=[];addQuestion('single')}})
</script>
@endpush

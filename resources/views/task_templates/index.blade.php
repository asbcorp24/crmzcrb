@extends('layouts.app')
@section('title','Шаблоны задач — CRM ЗЦРБ')
@section('header','Шаблоны и повторяющиеся задачи')
@section('content')
<div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
  <div class="text-muted">Создавайте типовые поручения один раз и запускайте их вручную или по расписанию.</div>
  <button class="btn btn-primary ms-auto" data-bs-toggle="modal" data-bs-target="#templateModal" onclick="newTemplate()"><i class="bi bi-plus-lg me-1"></i>Новый шаблон</button>
</div>
<div id="templateList" class="row g-3"></div>

<div class="modal fade" id="templateModal" tabindex="-1"><div class="modal-dialog modal-xl"><form id="templateForm" class="modal-content">
<div class="modal-header"><h5 class="modal-title">Шаблон задачи</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
<div class="modal-body">
<input type="hidden" id="templateId">
<div class="row g-3">
  <div class="col-md-8"><label class="form-label">Название <span class="text-muted">(необязательно)</span></label><input name="title" class="form-control" placeholder="Если пусто — возьмётся проект или основание"></div>
  <div class="col-md-4"><label class="form-label">Исполнитель</label><select name="assigned_to" class="form-select"><option value="">Автор шаблона</option>@foreach($users as $u)<option value="{{ $u->id }}">{{ $u->full_name }}</option>@endforeach</select></div>

  <div class="col-md-4"><label class="form-label">Проект</label><div class="input-group"><select name="project_id" id="templateProject" class="form-select"><option value="">Не выбран</option>@foreach($projects as $x)<option value="{{ $x->id }}">{{ $x->name }}</option>@endforeach</select><button type="button" class="btn btn-outline-secondary tpl-ref-add" data-type="project" data-target="templateProject" data-label="Проект"><i class="bi bi-plus-lg"></i></button></div></div>
  <div class="col-md-4"><label class="form-label">Основание</label><div class="input-group"><select name="basis_id" id="templateBasis" class="form-select"><option value="">Не выбрано</option>@foreach($bases as $x)<option value="{{ $x->id }}">{{ $x->name }}</option>@endforeach</select><button type="button" class="btn btn-outline-secondary tpl-ref-add" data-type="basis" data-target="templateBasis" data-label="Основание"><i class="bi bi-plus-lg"></i></button></div></div>
  <div class="col-md-4"><label class="form-label">Ответственный отдел</label><select name="responsible_department_id" class="form-select"><option value="">Не выбран</option>@foreach($departments as $d)<option value="{{ $d->id }}">{{ $d->name }}</option>@endforeach</select></div>

  <div class="col-md-3"><label class="form-label">Старт через, дней <span class="text-muted">(необязательно)</span></label><input name="start_after_days" type="number" min="0" max="3650" class="form-control" placeholder="Например: 2"></div>
  <div class="col-md-3"><label class="form-label">Тип заказчика</label><select name="customer_type" id="templateCustomerType" class="form-select"><option value="">Не выбран</option><option value="organization">Предприятие</option><option value="department">Отдел</option></select></div>
  <div class="col-md-3"><label class="form-label">Заказчик</label><div class="input-group"><select name="customer_id" id="templateCustomerId" class="form-select" disabled><option value="">Сначала выберите тип</option></select><button type="button" id="templateCustomerAdd" class="btn btn-outline-secondary d-none"><i class="bi bi-plus-lg"></i></button></div></div>
  <div class="col-md-3"><label class="form-label">Управленческий статус</label><div class="input-group"><select name="business_status_id" id="templateBusinessStatus" class="form-select"><option value="">Пусто</option>@foreach($businessStatuses as $x)<option value="{{ $x->id }}">{{ $x->name }}</option>@endforeach</select><button type="button" class="btn btn-outline-secondary tpl-ref-add" data-type="task_status" data-target="templateBusinessStatus" data-label="Статус"><i class="bi bi-plus-lg"></i></button></div></div>

  <div class="col-12"><div class="form-check"><input class="form-check-input" type="checkbox" name="add_to_plan" value="1" id="templateAddToPlan"><label class="form-check-label fw-semibold" for="templateAddToPlan">Добавлять созданную по шаблону задачу в месячный план сотрудника</label><div class="form-text">Если активный или черновой месячный план есть, задача автоматически привяжется к нему.</div></div></div>

  <div class="col-12"><label class="form-label">Описание</label><textarea name="description" class="form-control" rows="3"></textarea></div>
  <div class="col-md-3"><label class="form-label">Приоритет</label><select name="priority" class="form-select"><option value="normal">Обычный</option><option value="low">Низкий</option><option value="high">Высокий</option><option value="critical">Критический</option></select></div>
  <div class="col-md-3"><label class="form-label">Срок через, дней</label><input name="due_after_days" type="number" min="0" value="0" class="form-control"></div>
  <div class="col-md-3"><label class="form-label">Повторение</label><select name="recurrence" id="recurrence" class="form-select"><option value="none">Не повторять</option><option value="daily">По дням</option><option value="weekly">По неделям</option><option value="monthly">По месяцам</option></select></div>
  <div class="col-md-3"><label class="form-label">Каждые N</label><input name="recurrence_interval" type="number" min="1" value="1" class="form-control"><div class="form-text" id="intervalHint"></div></div>
  <div class="col-md-4 recurrence-extra d-none" id="weekdayWrap"><label class="form-label">День недели</label><select name="weekday" class="form-select"><option value="1">Понедельник</option><option value="2">Вторник</option><option value="3">Среда</option><option value="4">Четверг</option><option value="5">Пятница</option><option value="6">Суббота</option><option value="7">Воскресенье</option></select></div>
  <div class="col-md-4 recurrence-extra d-none" id="monthdayWrap"><label class="form-label">Число месяца</label><input name="day_of_month" type="number" min="1" max="31" value="1" class="form-control"></div>
  <div class="col-md-4 recurrence-extra d-none" id="nextRunWrap"><label class="form-label">Первый запуск</label><input name="next_run_at" type="datetime-local" class="form-control"></div>

  <div class="col-12"><label class="form-label">Чек-лист</label><div id="checklistEditor"></div><button type="button" class="btn btn-sm btn-outline-secondary mt-2" onclick="addChecklistEditor()"><i class="bi bi-plus"></i> Добавить пункт</button></div>
  <div class="col-12"><div class="form-check form-switch"><input id="templateActive" class="form-check-input" type="checkbox" checked><label class="form-check-label">Шаблон активен</label></div></div>
</div>
<div id="templateError" class="alert alert-danger mt-3 d-none"></div>
</div>
<div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Отмена</button><button class="btn btn-primary">Сохранить</button></div>
</form></div></div>

<div class="modal fade" id="templateReferenceModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
<div class="modal-header"><h5 class="modal-title" id="templateReferenceTitle">Добавить запись</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
<div class="modal-body">
  <div id="templateReferenceError" class="alert alert-danger d-none"></div>
  <div class="mb-3"><label class="form-label">Наименование</label><input id="templateReferenceName" class="form-control" maxlength="255"></div>
  <div class="mb-3"><label class="form-label">Код <span class="text-muted">(необязательно)</span></label><input id="templateReferenceCode" class="form-control" maxlength="80"></div>
  <div id="templateStatusFields" class="d-none">
    <div class="mb-3"><label class="form-label">Системный статус</label><select id="templateReferenceSystemKey" class="form-select"><option value="new">Новая</option><option value="in_progress">В работе</option><option value="review">На проверке</option><option value="completed">Выполнена</option><option value="cancelled">Отменена</option></select></div>
    <div class="mb-3"><label class="form-label">Цвет</label><input id="templateReferenceColor" type="color" class="form-control form-control-color" value="#0d6efd"></div>
  </div>
  <div><label class="form-label">Примечание</label><textarea id="templateReferenceNotes" class="form-control" rows="3"></textarea></div>
</div>
<div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Отмена</button><button type="button" id="saveTemplateReference" class="btn btn-primary">Добавить</button></div>
</div></div></div>
@endsection

@push('scripts')
<script>
let templateCache={};
let tplRefType=null,tplRefTarget=null;
const tplOrganizations=[
@foreach($organizations as $x)
{id:{{ $x->id }},name:@json($x->name)},
@endforeach
];
const tplDepartments=[
@foreach($departments as $d)
{id:{{ $d->id }},name:@json($d->name)},
@endforeach
];

function escT(v){return $('<div>').text(v??'').html()}
function weekName(n){return ['','понедельник','вторник','среда','четверг','пятница','суббота','воскресенье'][n]||''}
function recName(t){let n=Number(t.recurrence_interval||1);if(t.recurrence==='none')return'Разовый';if(t.recurrence==='daily')return n===1?'Каждый день':`Каждые ${n} дней`;if(t.recurrence==='weekly')return `${n===1?'Каждую неделю':`Каждые ${n} недели`} · ${weekName(t.weekday)}`;if(t.recurrence==='monthly')return `${n===1?'Каждый месяц':`Каждые ${n} месяца`} · ${t.day_of_month||1} числа`;return t.recurrence}

function loadTemplates(){
  $.get('{{ route('task-templates.index') }}',r=>{
    templateCache={};let h='';
    r.forEach(t=>{
      templateCache[t.id]=t;
      const checks=(t.checklist_items||[]).map(i=>`<li>${escT(i.title)}</li>`).join('');
      const meta=[];
      if(t.project_id)meta.push('Проект #'+t.project_id);
      if(t.responsible_department_id)meta.push('Отдел #'+t.responsible_department_id);
      if(t.add_to_plan)meta.push('в план');
      h+=`<div class="col-12 col-xl-6"><div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="d-flex gap-2"><div class="flex-grow-1"><h5>${escT(t.title||'Без названия')}</h5><div class="text-muted small">${escT(t.assignee?t.assignee.last_name+' '+t.assignee.first_name:'Исполнитель = автор')} · ${escT(recName(t))} · срок +${t.due_after_days} дн.</div>${meta.length?`<div class="small text-primary mt-1">${meta.join(' · ')}</div>`:''}</div><span class="badge ${t.is_active?'text-bg-success':'text-bg-secondary'}">${t.is_active?'Активен':'Отключён'}</span></div><p class="mt-3 mb-2">${escT(t.description||'')}</p>${checks?`<ol class="small mb-3">${checks}</ol>`:''}<div class="d-flex align-items-center gap-2 flex-wrap"><span class="badge text-bg-light border">${escT(t.priority)}</span>${t.next_run_at?`<span class="small text-muted">Следующий запуск: ${new Date(t.next_run_at).toLocaleString('ru-RU')}</span>`:''}<button class="btn btn-sm btn-outline-primary ms-auto" onclick="editTemplate(${t.id})"><i class="bi bi-pencil"></i></button><button class="btn btn-sm ${t.is_active?'btn-outline-secondary':'btn-outline-success'}" onclick="toggleTemplate(${t.id},${t.is_active?0:1})">${t.is_active?'Отключить':'Включить'}</button><button class="btn btn-sm btn-primary" onclick="runTemplate(${t.id})"><i class="bi bi-play-fill me-1"></i>Создать сейчас</button></div></div></div></div>`;
    });
    $('#templateList').html(h||'<div class="col-12"><div class="alert alert-light border text-center">Шаблонов пока нет</div></div>');
  });
}

function fillTemplateCustomer(selected=''){
  const type=$('#templateCustomerType').val();
  const rows=type==='organization'?tplOrganizations:type==='department'?tplDepartments:[];
  $('#templateCustomerId').html('<option value="">'+(type?'Выберите...':'Сначала выберите тип')+'</option>'+rows.map(x=>'<option value="'+x.id+'">'+escT(x.name)+'</option>').join('')).prop('disabled',!type);
  if(selected)$('#templateCustomerId').val(String(selected));
  $('#templateCustomerAdd').toggleClass('d-none',type!=='organization');
}

function newTemplate(){
  $('#templateForm')[0].reset();$('#templateId').val('');$('#templateActive').prop('checked',true);$('#templateAddToPlan').prop('checked',false);
  $('#checklistEditor').empty();addChecklistEditor();toggleRecurrence();fillTemplateCustomer();$('#templateError').addClass('d-none')
}
function editTemplate(id){
  let t=templateCache[id];newTemplate();$('#templateId').val(id);
  for(let k of ['title','assigned_to','project_id','basis_id','responsible_department_id','start_after_days','customer_type','business_status_id','description','priority','due_after_days','recurrence','recurrence_interval','weekday','day_of_month'])$('[name='+k+']').val(t[k]??'');
  fillTemplateCustomer(t.customer_id||'');
  if(t.next_run_at)$('[name=next_run_at]').val(t.next_run_at.slice(0,16));
  $('#templateActive').prop('checked',!!t.is_active);$('#templateAddToPlan').prop('checked',!!t.add_to_plan);
  $('#checklistEditor').empty();(t.checklist_items||[]).forEach(x=>addChecklistEditor(x.title));if(!(t.checklist_items||[]).length)addChecklistEditor();
  toggleRecurrence();bootstrap.Modal.getOrCreateInstance(document.getElementById('templateModal')).show()
}
function addChecklistEditor(value=''){const v=escT(value);$('#checklistEditor').append(`<div class="input-group mb-2"><span class="input-group-text">☐</span><input name="checklist[]" value="${v}" class="form-control" placeholder="Пункт чек-листа"><button type="button" class="btn btn-outline-danger" onclick="$(this).closest('.input-group').remove()"><i class="bi bi-x"></i></button></div>`)}
function toggleRecurrence(){const r=$('#recurrence').val();$('.recurrence-extra').toggleClass('d-none',r==='none');$('#weekdayWrap').toggleClass('d-none',r!=='weekly');$('#monthdayWrap').toggleClass('d-none',r!=='monthly');$('#intervalHint').text(r==='daily'?'Например 14 = каждые 14 дней':r==='weekly'?'Например 2 = каждые 2 недели':r==='monthly'?'Например 3 = каждые 3 месяца':'')}

function openTplReference(type,target,label){tplRefType=type;tplRefTarget=target;$('#templateReferenceTitle').text('Добавить: '+label);$('#templateReferenceName,#templateReferenceCode,#templateReferenceNotes').val('');$('#templateReferenceError').addClass('d-none').text('');$('#templateStatusFields').toggleClass('d-none',type!=='task_status');bootstrap.Modal.getOrCreateInstance(document.getElementById('templateReferenceModal')).show()}
function saveTplReference(){
  const name=$('#templateReferenceName').val().trim();if(!name)return $('#templateReferenceError').removeClass('d-none').text('Введите наименование.');
  const data={type:tplRefType,name,code:$('#templateReferenceCode').val(),notes:$('#templateReferenceNotes').val(),sort_order:0,is_active:1};
  if(tplRefType==='task_status'){data.system_key=$('#templateReferenceSystemKey').val();data.color=$('#templateReferenceColor').val()}
  $.ajax({url:'{{ route('directories.store') }}',method:'POST',data}).done(r=>{
    if(tplRefType==='organization'){tplOrganizations.push({id:r.item.id,name:r.item.name});fillTemplateCustomer(String(r.item.id))}
    else $('#'+tplRefTarget).append(new Option(r.item.name,r.item.id,true,true)).val(String(r.item.id));
    bootstrap.Modal.getInstance(document.getElementById('templateReferenceModal')).hide()
  }).fail(x=>$('#templateReferenceError').removeClass('d-none').text(x.responseJSON?.message||Object.values(x.responseJSON?.errors||{}).flat()[0]||'Ошибка сохранения'))
}

$('#recurrence').on('change',toggleRecurrence);
$('#templateCustomerType').on('change',()=>fillTemplateCustomer());
$('.tpl-ref-add').on('click',function(){openTplReference($(this).data('type'),$(this).data('target'),$(this).data('label'))});
$('#templateCustomerAdd').on('click',()=>openTplReference('organization','templateCustomerId','Предприятие'));
$('#saveTemplateReference').on('click',saveTplReference);

$('#templateForm').on('submit',function(e){
  e.preventDefault();let id=$('#templateId').val(),a=$(this).serializeArray(),d={};
  a.forEach(x=>{if(x.name==='checklist[]'){d.checklist=d.checklist||[];d.checklist.push(x.value)}else d[x.name]=x.value});
  d.is_active=$('#templateActive').is(':checked')?1:0;
  d.add_to_plan=$('#templateAddToPlan').is(':checked')?1:0;
  $.ajax({url:id?`{{ url('/ajax/task-templates') }}/${id}`:'{{ route('task-templates.store') }}',method:id?'PATCH':'POST',data:d})
    .done(()=>{bootstrap.Modal.getInstance(document.getElementById('templateModal')).hide();loadTemplates()})
    .fail(x=>$('#templateError').removeClass('d-none').text(x.responseJSON?.message||Object.values(x.responseJSON?.errors||{}).flat()[0]||'Ошибка сохранения'))
});
function toggleTemplate(id,v){$.post(`{{ url('/ajax/task-templates') }}/${id}/toggle`,{is_active:v}).done(loadTemplates).fail(x=>alert(x.responseJSON?.message||'Ошибка'))}
function runTemplate(id){if(!confirm('Создать задачу по этому шаблону сейчас?'))return;$.post(`{{ url('/ajax/task-templates') }}/${id}/create-task`).done(r=>{window.location.href=`{{ url('/tasks') }}?task=${r.task.id}`}).fail(x=>alert(x.responseJSON?.message||'Ошибка создания задачи'))}
loadTemplates();
</script>
@endpush

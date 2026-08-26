@extends('layouts.app')
@section('title','Шаблоны задач — CRM ЗЦРБ')
@section('header','Шаблоны и повторяющиеся задачи')
@section('content')
<div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
  <div class="text-muted">Создавайте типовые поручения один раз и запускайте их вручную или по расписанию.</div>
  <button class="btn btn-primary ms-auto" data-bs-toggle="modal" data-bs-target="#templateModal" onclick="newTemplate()"><i class="bi bi-plus-lg me-1"></i>Новый шаблон</button>
</div>
<div id="templateList" class="row g-3"></div>

<div class="modal fade" id="templateModal" tabindex="-1"><div class="modal-dialog modal-lg"><form id="templateForm" class="modal-content"><div class="modal-header"><h5 class="modal-title">Шаблон задачи</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body">
<div class="row g-3">
  <div class="col-md-8"><label class="form-label">Название</label><input name="title" class="form-control" required></div>
  <div class="col-md-4"><label class="form-label">Исполнитель</label><select name="assigned_to" class="form-select"><option value="">Автор шаблона</option>@foreach($users as $u)<option value="{{ $u->id }}">{{ $u->full_name }}</option>@endforeach</select></div>
  <div class="col-12"><label class="form-label">Описание</label><textarea name="description" class="form-control" rows="3"></textarea></div>
  <div class="col-md-3"><label class="form-label">Приоритет</label><select name="priority" class="form-select"><option value="normal">Обычный</option><option value="low">Низкий</option><option value="high">Высокий</option><option value="critical">Критический</option></select></div>
  <div class="col-md-3"><label class="form-label">Срок через, дней</label><input name="due_after_days" type="number" min="0" value="0" class="form-control"></div>
  <div class="col-md-3"><label class="form-label">Повторение</label><select name="recurrence" id="recurrence" class="form-select"><option value="none">Не повторять</option><option value="daily">Ежедневно</option><option value="weekly">Еженедельно</option><option value="monthly">Ежемесячно</option></select></div>
  <div class="col-md-3"><label class="form-label">Каждые N периодов</label><input name="recurrence_interval" type="number" min="1" value="1" class="form-control"></div>
  <div class="col-md-4 recurrence-extra d-none" id="weekdayWrap"><label class="form-label">День недели</label><select name="weekday" class="form-select"><option value="1">Понедельник</option><option value="2">Вторник</option><option value="3">Среда</option><option value="4">Четверг</option><option value="5">Пятница</option><option value="6">Суббота</option><option value="7">Воскресенье</option></select></div>
  <div class="col-md-4 recurrence-extra d-none" id="monthdayWrap"><label class="form-label">Число месяца</label><input name="day_of_month" type="number" min="1" max="31" value="1" class="form-control"></div>
  <div class="col-md-4 recurrence-extra d-none" id="nextRunWrap"><label class="form-label">Первый запуск</label><input name="next_run_at" type="datetime-local" class="form-control"></div>
  <div class="col-12"><label class="form-label">Чек-лист</label><div id="checklistEditor"></div><button type="button" class="btn btn-sm btn-outline-secondary mt-2" onclick="addChecklistEditor()"><i class="bi bi-plus"></i> Добавить пункт</button></div>
  <div class="col-12"><div class="form-check form-switch"><input id="templateActive" class="form-check-input" type="checkbox" checked><label class="form-check-label">Шаблон активен</label></div></div>
</div><div id="templateError" class="alert alert-danger mt-3 d-none"></div>
</div><div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Отмена</button><button class="btn btn-primary">Сохранить</button></div></form></div></div>
@endsection
@push('scripts')
<script>
function escT(v){return $('<div>').text(v??'').html()}
function recName(v){return {none:'Разовый',daily:'Ежедневно',weekly:'Еженедельно',monthly:'Ежемесячно'}[v]||v}
function loadTemplates(){$.get('{{ route('task-templates.index') }}',r=>{let h='';r.forEach(t=>{const checks=(t.checklist_items||[]).map(i=>`<li>${escT(i.title)}</li>`).join('');h+=`<div class="col-12 col-xl-6"><div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="d-flex gap-2"><div class="flex-grow-1"><h5>${escT(t.title)}</h5><div class="text-muted small">${escT(t.assignee?t.assignee.last_name+' '+t.assignee.first_name:'Исполнитель = автор')} · ${recName(t.recurrence)} · срок +${t.due_after_days} дн.</div></div><span class="badge ${t.is_active?'text-bg-success':'text-bg-secondary'}">${t.is_active?'Активен':'Отключён'}</span></div><p class="mt-3 mb-2">${escT(t.description||'')}</p>${checks?`<ol class="small mb-3">${checks}</ol>`:''}<div class="d-flex align-items-center gap-2 flex-wrap"><span class="badge text-bg-light border">${escT(t.priority)}</span>${t.next_run_at?`<span class="small text-muted">Следующий запуск: ${new Date(t.next_run_at).toLocaleString('ru-RU')}</span>`:''}<button class="btn btn-sm btn-primary ms-auto" onclick="runTemplate(${t.id})"><i class="bi bi-play-fill me-1"></i>Создать задачу сейчас</button></div></div></div></div>`});$('#templateList').html(h||'<div class="col-12"><div class="alert alert-light border text-center">Шаблонов пока нет</div></div>')})}
function newTemplate(){$('#templateForm')[0].reset();$('#templateActive').prop('checked',true);$('#checklistEditor').empty();addChecklistEditor();toggleRecurrence();$('#templateError').addClass('d-none')}
function addChecklistEditor(){const i=$('#checklistEditor .input-group').length;$('#checklistEditor').append(`<div class="input-group mb-2"><span class="input-group-text">☐</span><input name="checklist[]" class="form-control" placeholder="Пункт чек-листа"><button type="button" class="btn btn-outline-danger" onclick="$(this).closest('.input-group').remove()"><i class="bi bi-x"></i></button></div>`)}
function toggleRecurrence(){const r=$('#recurrence').val();$('.recurrence-extra').toggleClass('d-none',r==='none');$('#weekdayWrap').toggleClass('d-none',r!=='weekly');$('#monthdayWrap').toggleClass('d-none',r!=='monthly')}
$('#recurrence').on('change',toggleRecurrence);
$('#templateForm').on('submit',function(e){e.preventDefault();let a=$(this).serializeArray(),d={};a.forEach(x=>{if(x.name==='checklist[]'){d.checklist=d.checklist||[];d.checklist.push(x.value)}else d[x.name]=x.value});d.is_active=$('#templateActive').is(':checked')?1:0;$.post('{{ route('task-templates.store') }}',d).done(()=>{bootstrap.Modal.getInstance(document.getElementById('templateModal')).hide();loadTemplates()}).fail(x=>$('#templateError').removeClass('d-none').text(x.responseJSON?.message||'Ошибка сохранения'))});
function runTemplate(id){if(!confirm('Создать задачу по этому шаблону сейчас?'))return;$.post(`{{ url('/ajax/task-templates') }}/${id}/create-task`).done(r=>{loadTemplates();window.location.href=`{{ url('/tasks') }}?task=${r.task.id}`}).fail(x=>alert(x.responseJSON?.message||'Ошибка создания задачи'))}
loadTemplates();
</script>
@endpush

@extends('layouts.app')
@section('title','Планы — CRM ЗЦРБ')
@section('header','Планы')
@section('content')
<div class="d-flex gap-2 mb-3 flex-wrap"><input id="q" class="form-control" style="max-width:280px" placeholder="Поиск по планам"><select id="statusFilter" class="form-select" style="max-width:190px"><option value="">Все статусы</option><option value="draft">Черновик</option><option value="active">Активный</option><option value="completed">Выполнен</option><option value="cancelled">Отменён</option></select><select id="periodFilter" class="form-select" style="max-width:190px"><option value="">Все периоды</option><option value="day">День</option><option value="week">Неделя</option><option value="month">Месяц</option><option value="quarter">Квартал</option><option value="year">Год</option><option value="custom">Произвольный</option></select>@if(auth()->user()->isManager())<select id="userFilter" class="form-select" style="max-width:260px"><option value="">Все сотрудники</option>@foreach($users as $u)<option value="{{ $u->id }}">{{ $u->full_name }}</option>@endforeach</select>@endif<button class="btn btn-primary ms-auto" data-bs-toggle="modal" data-bs-target="#planModal" onclick="newPlan()"><i class="bi bi-plus-lg me-1"></i>Новый план</button></div>
@if(auth()->user()->isManager() || auth()->user()->isAdmin())
<div class="card border-0 shadow-sm mb-4" id="externalPlanReportCard">
  <div class="card-header bg-white d-flex align-items-center gap-2 flex-wrap">
    <div>
      <b><i class="bi bi-bar-chart-line me-1"></i>Отчёт по планам внешней CRM</b>
      <div class="small text-muted">Сводные показатели из документооборота по выбранному календарному месяцу.</div>
    </div>
    <div class="ms-auto d-flex gap-2 align-items-center flex-wrap">
      <input type="month" id="externalPlanReportMonth" class="form-control form-control-sm" style="width:170px">
      <button type="button" class="btn btn-sm btn-outline-primary" id="loadExternalPlanReport"><i class="bi bi-arrow-clockwise me-1"></i>Обновить</button>
      <button type="button" class="btn btn-sm btn-outline-secondary" id="toggleExternalPlanItems"><i class="bi bi-list-task me-1"></i>Показать строки</button>
    </div>
  </div>
  <div class="card-body">
    <div id="externalPlanReportError" class="alert alert-warning d-none"></div>
    <div id="externalPlanReportLoading" class="text-muted small">Выберите месяц для загрузки отчёта.</div>
    <div id="externalPlanReportContent" class="d-none">
      <div class="row g-3 mb-4" id="externalPlanSummary"></div>

      <div class="row g-4">
        <div class="col-xl-6">
          <h6>По отделам</h6>
          <div class="table-responsive border rounded">
            <table class="table table-sm align-middle mb-0">
              <thead><tr><th>Отдел</th><th class="text-end">Всего</th><th class="text-end">Вып.</th><th class="text-end">%</th><th class="text-end">Часы</th></tr></thead>
              <tbody id="externalPlanDepartments"></tbody>
            </table>
          </div>
        </div>
        <div class="col-xl-6">
          <h6>По исполнителям</h6>
          <div class="table-responsive border rounded">
            <table class="table table-sm align-middle mb-0">
              <thead><tr><th>Исполнитель</th><th class="text-end">Всего</th><th class="text-end">Вып.</th><th class="text-end">%</th><th class="text-end">Часы</th></tr></thead>
              <tbody id="externalPlanExecutors"></tbody>
            </table>
          </div>
        </div>
      </div>

      <div id="externalPlanItemsWrap" class="mt-4 d-none">
        <h6>Строки отчёта</h6>
        <div class="table-responsive border rounded">
          <table class="table table-sm align-middle mb-0">
            <thead><tr><th>Исполнитель</th><th>Пункт плана</th><th>Срок</th><th class="text-end">Часы</th><th>Статус</th><th>Комментарий</th></tr></thead>
            <tbody id="externalPlanItems"></tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>
@endif

<div class="card border-0 shadow-sm"><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>План</th><th>Сотрудник</th><th>Период</th><th>Статус</th><th style="width:180px">Выполнение</th><th></th></tr></thead><tbody id="planRows"><tr><td colspan="6" class="text-center py-4">Загрузка...</td></tr></tbody></table></div></div>

<div class="modal fade" id="planModal" tabindex="-1"><div class="modal-dialog modal-lg"><form class="modal-content" id="planForm"><div class="modal-header"><h5 class="modal-title">План</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><input type="hidden" id="plan_id"><div class="row g-3"><div class="col-md-6"><label class="form-label">Сотрудник</label><select name="user_id" class="form-select" required>@foreach($users as $u)<option value="{{ $u->id }}" @selected($u->id===auth()->id())>{{ $u->full_name }}</option>@endforeach</select></div><div class="col-md-6"><label class="form-label">Название</label><input name="title" class="form-control" required></div><div class="col-12"><label class="form-label">Описание</label><textarea name="description" class="form-control" rows="3"></textarea></div><div class="col-md-4"><label class="form-label">Тип периода</label><select name="period_type" class="form-select"><option value="day">День</option><option value="week">Неделя</option><option value="month" selected>Месяц</option><option value="quarter">Квартал</option><option value="year">Год</option><option value="custom">Произвольный</option></select></div><div class="col-md-4"><label class="form-label">Начало</label><input type="date" name="period_start" class="form-control" required></div><div class="col-md-4"><label class="form-label">Окончание</label><input type="date" name="period_end" class="form-control" required></div><div class="col-md-4"><label class="form-label">Статус</label><select name="status" class="form-select"><option value="draft">Черновик</option><option value="active" selected>Активный</option><option value="completed">Выполнен</option><option value="cancelled">Отменён</option></select></div></div><div id="planError" class="alert alert-danger mt-3 d-none"></div></div><div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Отмена</button><button class="btn btn-primary">Сохранить</button></div></form></div></div>

<div class="modal fade" id="taskModal" tabindex="-1"><div class="modal-dialog"><form class="modal-content" id="taskForm"><div class="modal-header"><h5 class="modal-title">Добавить пункт плана</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><input type="hidden" id="task_plan_id"><div class="mb-3"><label class="form-label">Пункт / задача</label><input name="title" class="form-control" required></div><div class="mb-3"><label class="form-label">Описание</label><textarea name="description" class="form-control" rows="3"></textarea></div><div class="row g-3"><div class="col-md-6"><label class="form-label">Приоритет</label><select name="priority" class="form-select"><option value="normal">Обычный</option><option value="high">Высокий</option><option value="critical">Критический</option><option value="low">Низкий</option></select></div><div class="col-md-6"><label class="form-label">Срок</label><input type="datetime-local" name="due_at" class="form-control"></div></div><div id="taskError" class="alert alert-danger mt-3 d-none"></div></div><div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Отмена</button><button class="btn btn-primary">Добавить</button></div></form></div></div>
@endsection
@push('scripts')
<script>
let plans=[]; const esc=v=>$('<div>').text(v??'').html(); const statusName={draft:'Черновик',active:'Активный',completed:'Выполнен',cancelled:'Отменён'};
function params(){let p={q:$('#q').val(),status:$('#statusFilter').val(),period_type:$('#periodFilter').val()};if($('#userFilter').length)p.user_id=$('#userFilter').val();return p}
function loadPlans(){$.get('{{ route('plans.index') }}',params(),r=>{plans=r.data;let h='';r.data.forEach(p=>{let tasks=p.tasks||[];let taskInfo=tasks.length?`<div class="mt-2"><div class="small text-muted mb-1">Задачи плана: ${tasks.length}</div>${tasks.map(t=>`<div class="small mb-1"><a href="{{ route('tasks.page') }}?task=${t.id}" class="text-decoration-none"><i class="bi bi-box-arrow-up-right me-1"></i>#${t.id} ${esc(t.title||'Задача')}</a></div>`).join('')}</div>`:'';let archive=['completed','cancelled'].includes(p.status)?`<button class="btn btn-outline-secondary" onclick="archiveEntity('plan',${p.id})" title="В архив"><i class="bi bi-archive"></i></button>`:'';h+=`<tr><td><strong>${esc(p.title)}</strong><div class="small text-muted">${esc(p.description||'')}</div>${taskInfo}</td><td>${esc(p.user?.full_name||'')}</td><td>${p.period_start} — ${p.period_end}</td><td><span class="badge text-bg-${p.status==='completed'?'success':p.status==='cancelled'?'secondary':p.status==='active'?'primary':'warning'}">${statusName[p.status]||p.status}</span></td><td><div class="progress" style="height:18px"><div class="progress-bar" style="width:${p.progress||0}%">${p.progress||0}%</div></div></td><td class="text-end"><div class="btn-group btn-group-sm"><button class="btn btn-outline-primary" onclick="editPlan(${p.id})" title="Изменить"><i class="bi bi-pencil"></i></button><button class="btn btn-outline-success" onclick="addTask(${p.id})" title="Добавить задачу"><i class="bi bi-plus-lg"></i></button><button class="btn btn-outline-secondary" onclick="openEntityCollab('plan',${p.id},'Материалы плана: ${esc(p.title).replace(/'/g,"&#39;")}')" title="Файлы и комментарии"><i class="bi bi-chat-paperclip"></i></button>${archive}</div></td></tr>`});$('#planRows').html(h||'<tr><td colspan="6" class="text-center py-4 text-muted">Планы не найдены</td></tr>')})}
function setDefaultDates(){let d=new Date(),s=new Date(d.getFullYear(),d.getMonth(),1),e=new Date(d.getFullYear(),d.getMonth()+1,0),f=x=>x.toISOString().slice(0,10);$('[name=period_start]').val(f(s));$('[name=period_end]').val(f(e))}
function newPlan(){$('#planForm')[0].reset();$('#plan_id').val('');setDefaultDates();$('#planError').addClass('d-none')}
function editPlan(id){let p=plans.find(x=>x.id==id);newPlan();$('#plan_id').val(id);for(let k of ['user_id','title','description','period_start','period_end','period_type','status'])$(`[name=${k}]`).val(p[k]??'');bootstrap.Modal.getOrCreateInstance(document.getElementById('planModal')).show()}
function addTask(id){$('#taskForm')[0].reset();$('#task_plan_id').val(id);$('#taskError').addClass('d-none');bootstrap.Modal.getOrCreateInstance(document.getElementById('taskModal')).show()}
$('#planForm').on('submit',function(e){e.preventDefault();let id=$('#plan_id').val(),data=Object.fromEntries(new FormData(this).entries());$.ajax({url:id?'{{ url('/ajax/plans') }}/'+id:'{{ route('plans.store') }}',method:id?'PATCH':'POST',data}).done(()=>{bootstrap.Modal.getInstance(document.getElementById('planModal')).hide();loadPlans()}).fail(r=>$('#planError').removeClass('d-none').text(r.responseJSON?.message||'Ошибка сохранения'))});
$('#taskForm').on('submit',function(e){e.preventDefault();let id=$('#task_plan_id').val(),data=Object.fromEntries(new FormData(this).entries());$.post('{{ url('/ajax/plans') }}/'+id+'/tasks',data).done(()=>{bootstrap.Modal.getInstance(document.getElementById('taskModal')).hide();loadPlans()}).fail(r=>$('#taskError').removeClass('d-none').text(r.responseJSON?.message||'Ошибка добавления'))});
let externalPlanDetailsLoaded=false;
function externalReportEsc(v){return $('<div>').text(v??'').html()}
function externalStatusLabel(v){return ({planned:'Запланировано',done:'Выполнено',not_done:'Не выполнено',cancelled:'Отменено'})[v]||v||'—'}
function renderExternalPlanReport(data,withItems=false){
  const s=data.summary||{};
  $('#externalPlanReportContent').removeClass('d-none');
  $('#externalPlanReportLoading').addClass('d-none');
  $('#externalPlanSummary').html([
    ['Планов',s.plans_count??0,'bi-journal-check'],
    ['Пунктов',s.items_total??0,'bi-list-check'],
    ['Выполнено',s.done_count??0,'bi-check-circle'],
    ['Не выполнено',s.not_done_count??0,'bi-x-circle'],
    ['Выполнение',(s.completion_percent??0)+'%','bi-percent'],
    ['План, часов',s.planned_hours_total??0,'bi-clock'],
    ['Выполнено, часов',s.done_hours_total??0,'bi-clock-history'],
    ['Не выполнено, часов',s.not_done_hours_total??0,'bi-exclamation-circle']
  ].map(x=>`<div class="col-6 col-md-4 col-xl-3"><div class="border rounded p-3 h-100"><div class="small text-muted"><i class="bi ${x[2]} me-1"></i>${externalReportEsc(x[0])}</div><div class="fs-4 fw-semibold mt-1">${externalReportEsc(x[1])}</div></div></div>`).join(''));

  const deps=data.departments||[];
  $('#externalPlanDepartments').html(deps.length?deps.map(r=>`<tr><td><b>${externalReportEsc(r.name||'Без отдела')}</b></td><td class="text-end">${r.total??0}</td><td class="text-end">${r.done??0}</td><td class="text-end">${r.completion_percent??0}%</td><td class="text-end">${r.done_hours??0} / ${r.planned_hours??0}</td></tr>`).join(''):'<tr><td colspan="5" class="text-center text-muted py-3">Нет данных</td></tr>');

  const users=data.executors||[];
  $('#externalPlanExecutors').html(users.length?users.map(r=>`<tr><td><b>${externalReportEsc(r.full_name||'Без исполнителя')}</b><div class="small text-muted">${externalReportEsc(r.department?.name||'')}</div></td><td class="text-end">${r.total??0}</td><td class="text-end">${r.done??0}</td><td class="text-end">${r.completion_percent??0}%</td><td class="text-end">${r.done_hours??0} / ${r.planned_hours??0}</td></tr>`).join(''):'<tr><td colspan="5" class="text-center text-muted py-3">Нет данных</td></tr>');

  if(withItems){
    const rows=data.items||[];
    $('#externalPlanItems').html(rows.length?rows.map(r=>`<tr><td>${externalReportEsc(r.executor?.full_name||'—')}</td><td><div class="fw-semibold">${externalReportEsc(r.text||'')}</div>${r.linked_task?`<div class="small text-muted">Связанная задача #${externalReportEsc(r.linked_task.id)}: ${externalReportEsc(r.linked_task.title||'')}</div>`:''}</td><td>${externalReportEsc(r.deadline_date||r.deadline_kind||'—')}</td><td class="text-end">${externalReportEsc(r.planned_hours??0)}</td><td>${externalReportEsc(externalStatusLabel(r.status))}</td><td>${externalReportEsc(r.comment||'')}</td></tr>`).join(''):'<tr><td colspan="6" class="text-center text-muted py-3">Строк нет</td></tr>');
    $('#externalPlanItemsWrap').removeClass('d-none');
    externalPlanDetailsLoaded=true;
    $('#toggleExternalPlanItems').html('<i class="bi bi-list-task me-1"></i>Скрыть строки');
  }
}
async function loadExternalPlanReport(details=false){
  const month=$('#externalPlanReportMonth').val();
  if(!month)return;
  $('#externalPlanReportError').addClass('d-none').text('');
  $('#externalPlanReportLoading').removeClass('d-none').text('Загрузка отчёта...');
  try{
    const r=await $.get('{{ route('external-crm.plans.report') }}',{month,details:details?1:0});
    renderExternalPlanReport(r.data||{},details);
  }catch(x){
    $('#externalPlanReportLoading').addClass('d-none');
    $('#externalPlanReportError').removeClass('d-none').text(x.responseJSON?.message||'Не удалось загрузить отчёт внешней CRM.');
  }
}
function initExternalPlanReport(){
  if(!$('#externalPlanReportMonth').length)return;
  const d=new Date(),m=String(d.getMonth()+1).padStart(2,'0');
  $('#externalPlanReportMonth').val(d.getFullYear()+'-'+m);
  $('#loadExternalPlanReport').on('click',()=>{externalPlanDetailsLoaded=false;$('#externalPlanItemsWrap').addClass('d-none');$('#toggleExternalPlanItems').html('<i class="bi bi-list-task me-1"></i>Показать строки');loadExternalPlanReport(false)});
  $('#externalPlanReportMonth').on('change',()=>{externalPlanDetailsLoaded=false;$('#externalPlanItemsWrap').addClass('d-none');$('#toggleExternalPlanItems').html('<i class="bi bi-list-task me-1"></i>Показать строки');loadExternalPlanReport(false)});
  $('#toggleExternalPlanItems').on('click',()=>{
    if(!externalPlanDetailsLoaded){loadExternalPlanReport(true);return}
    const wrap=$('#externalPlanItemsWrap');wrap.toggleClass('d-none');
    $('#toggleExternalPlanItems').html(wrap.hasClass('d-none')?'<i class="bi bi-list-task me-1"></i>Показать строки':'<i class="bi bi-list-task me-1"></i>Скрыть строки');
  });
  loadExternalPlanReport(false);
}

let timer;$('#q').on('input',()=>{clearTimeout(timer);timer=setTimeout(loadPlans,300)});$('#statusFilter,#periodFilter,#userFilter').on('change',loadPlans);loadPlans();
initExternalPlanReport();
</script>
@endpush

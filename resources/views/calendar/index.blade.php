@extends('layouts.app')
@section('title','Календарь — CRM ЗЦРБ')
@section('header',auth()->user()->isManager()?'Сводный календарь':'Мой календарь')
@push('styles')
<style>
#calendar{background:#fff;padding:16px;border-radius:16px;box-shadow:0 1px 3px rgba(16,24,40,.08)}
.fc .fc-toolbar-title{font-size:1.2rem}.fc-event{cursor:pointer}.calendar-summary td{vertical-align:middle}.calendar-filter-card{border-radius:16px}
@media(max-width:767px){.fc .fc-toolbar{flex-direction:column;gap:10px;align-items:stretch}.fc .fc-toolbar-chunk{text-align:center}}
</style>
@endpush
@section('content')
<div class="card border-0 shadow-sm calendar-filter-card mb-3"><div class="card-body">
  <div class="d-flex align-items-center flex-wrap gap-2 mb-3">
    <div>
      <div class="fw-semibold">{{ auth()->user()->isManager()?'Сводный календарь сроков':'Личный календарь сроков' }}</div>
      <div class="small text-muted">Что нужно сделать, кто отвечает и к какому числу.</div>
    </div>
    <div class="ms-auto d-flex gap-2 flex-wrap"><span class="badge text-bg-primary">Задачи</span><span class="badge text-bg-success">Планы</span><span class="badge text-bg-danger">Просрочено</span></div>
  </div>

  <div class="row g-2">
    @if(auth()->user()->isManager())
    <div class="col-md-4"><label class="form-label small">Сотрудник</label><select id="employeeFilter" class="form-select"><option value="">Все сотрудники</option>@foreach($users as $u)<option value="{{ $u->id }}">{{ $u->full_name }}</option>@endforeach</select></div>
    <div class="col-md-4"><label class="form-label small">Подразделение</label><select id="departmentFilter" class="form-select"><option value="">Все подразделения</option>@foreach($departments as $d)<option value="{{ $d->id }}">{{ $d->name }}</option>@endforeach</select></div>
    @endif
    <div class="col-md-{{ auth()->user()->isManager()?2:6 }}"><label class="form-label small">Показывать</label><select id="kindFilter" class="form-select"><option value="all">Задачи и планы</option><option value="tasks">Только задачи</option><option value="plans">Только планы</option></select></div>
    <div class="col-md-{{ auth()->user()->isManager()?2:6 }}"><label class="form-label small">Статус задач</label><select id="statusFilter" class="form-select"><option value="">Все статусы</option><option value="new">Новые</option><option value="in_progress">В работе</option><option value="review">На проверке</option><option value="completed">Выполнено</option><option value="cancelled">Отменено</option></select></div>
  </div>
</div>

<div id="calendar"></div>

<div class="card border-0 shadow-sm mt-4"><div class="card-header bg-white d-flex align-items-center"><b>Сводка по видимому периоду</b><span id="summaryCount" class="badge text-bg-light border ms-2">0</span></div><div class="table-responsive"><table class="table table-hover mb-0 calendar-summary"><thead><tr><th>Срок</th>@if(auth()->user()->isManager())<th>Кто должен сделать</th><th>Подразделение</th>@endif<th>Что сделать</th><th>Тип</th><th>Статус</th><th></th></tr></thead><tbody id="summaryRows"><tr><td colspan="{{ auth()->user()->isManager()?7:5 }}" class="text-center py-4 text-muted">Загрузка...</td></tr></tbody></table></div></div>
@endsection
@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/locales-all.global.min.js"></script>
<script>
const isManager={{ auth()->user()->isManager()?'true':'false' }};
function escCal(v){return $('<div>').text(v??'').html()}
function statusName(s){return {new:'Новая',in_progress:'В работе',review:'На проверке',completed:'Выполнено',cancelled:'Отменено',draft:'Черновик',active:'Активен'}[s]||s}
function eventParams(){return {employee_id:$('#employeeFilter').val()||'',department_id:$('#departmentFilter').val()||'',kind:$('#kindFilter').val(),status:$('#statusFilter').val()}}
function renderSummary(events){
  let rows='';const sorted=[...events].sort((a,b)=>a.start-b.start);$('#summaryCount').text(sorted.length);
  sorted.forEach(ev=>{const p=ev.extendedProps;const type=p.kind==='task'?'Задача':'План';const badge=p.kind==='task'?'text-bg-primary':'text-bg-success';const st=p.overdue?'<span class="badge text-bg-danger">Просрочено</span>':`<span class="badge text-bg-light border">${escCal(statusName(p.status))}</span>`;rows+=`<tr><td class="text-nowrap">${escCal(p.deadline||'—')}</td>${isManager?`<td><strong>${escCal(p.assignee||'—')}</strong></td><td>${escCal(p.department||'—')}</td>`:''}<td>${escCal(p.rawTitle||ev.title)}</td><td><span class="badge ${badge}">${type}</span></td><td>${st}</td><td class="text-end"><a href="${escCal(ev.url||'#')}" class="btn btn-sm btn-outline-primary">Открыть</a></td></tr>`});
  $('#summaryRows').html(rows||`<tr><td colspan="${isManager?7:5}" class="text-center py-4 text-muted">На выбранный период событий нет</td></tr>`)
}
document.addEventListener('DOMContentLoaded',function(){
 const c=new FullCalendar.Calendar(document.getElementById('calendar'),{
   locale:'ru',firstDay:1,height:'auto',initialView:'dayGridMonth',
   headerToolbar:{left:'prev,next today',center:'title',right:'dayGridMonth,timeGridWeek,listWeek'},
   buttonText:{today:'Сегодня',month:'Месяц',week:'Неделя',list:'Список'},
   events:function(info,success,failure){$.get('{{ route('calendar.events') }}',{start:info.startStr,end:info.endStr,...eventParams()}).done(success).fail(failure)},
   eventDidMount:function(info){const p=info.event.extendedProps;if(p.overdue){info.el.style.backgroundColor='#dc3545'}else if(p.kind==='plan'){info.el.style.backgroundColor='#198754'}else{info.el.style.backgroundColor='#0d6efd'}info.el.style.borderColor='transparent';info.el.title=`${p.assignee||''}${p.department?' · '+p.department:''}${p.deadline?' · срок '+p.deadline:''}`},
   eventsSet:function(events){renderSummary(events)}
 });
 c.render();
 $('#employeeFilter,#departmentFilter,#kindFilter,#statusFilter').on('change',()=>c.refetchEvents());
});
</script>
@endpush

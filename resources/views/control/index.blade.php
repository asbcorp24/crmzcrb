@extends('layouts.app')
@section('title','На контроле — CRM ЗЦРБ')
@section('header','На контроле')
@section('content')
<div class="row g-3 mb-4">
  @foreach([
    ['Сотрудников','sumEmployees','text-body'],['Всего задач','sumTasks','text-body'],['Критические','sumCritical','text-danger'],['Просрочено','sumOverdue','text-danger'],['На проверке','sumReview','text-warning'],['Сегодня','sumToday','text-primary'],['Завтра','sumTomorrow','text-info'],['Без движения >3 дней','sumStale','text-secondary']
  ] as $c)
  <div class="col-6 col-md-4 col-xl-3"><div class="card stat-card h-100"><div class="card-body"><div class="text-muted small">{{ $c[0] }}</div><div class="fs-3 fw-bold {{ $c[2] }}" id="{{ $c[1] }}">0</div></div></div></div>
  @endforeach
</div>

<div class="card border-0 shadow-sm mb-4"><div class="card-header bg-white d-flex align-items-center flex-wrap gap-2"><strong>Очереди контроля</strong><button class="btn btn-sm btn-outline-secondary ms-auto" onclick="loadControl()"><i class="bi bi-arrow-clockwise me-1"></i>Обновить</button></div><div class="card-body">
<ul class="nav nav-pills gap-2 mb-3" id="controlTabs">
  <li class="nav-item"><button class="nav-link active" data-bucket="critical">Критические <span class="badge text-bg-light border ms-1" id="cntCritical">0</span></button></li>
  <li class="nav-item"><button class="nav-link" data-bucket="overdue">Просрочено <span class="badge text-bg-light border ms-1" id="cntOverdue">0</span></button></li>
  <li class="nav-item"><button class="nav-link" data-bucket="review">На проверке <span class="badge text-bg-light border ms-1" id="cntReview">0</span></button></li>
  <li class="nav-item"><button class="nav-link" data-bucket="today">Сегодня <span class="badge text-bg-light border ms-1" id="cntToday">0</span></button></li>
  <li class="nav-item"><button class="nav-link" data-bucket="tomorrow">Завтра <span class="badge text-bg-light border ms-1" id="cntTomorrow">0</span></button></li>
  <li class="nav-item"><button class="nav-link" data-bucket="stale">Без движения <span class="badge text-bg-light border ms-1" id="cntStale">0</span></button></li>
</ul>
<div id="controlQueue"></div>
</div></div>

<div class="card border-0 shadow-sm"><div class="card-header bg-white"><strong>Исполнение по сотрудникам</strong></div><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>Сотрудник</th><th>Подразделение</th><th class="text-center">Задач</th><th class="text-center">Выполнено</th><th class="text-center">На проверке</th><th class="text-center">Просрочено</th><th style="width:220px">Средний прогресс</th><th></th></tr></thead><tbody id="controlRows"><tr><td colspan="8" class="text-center py-5">Загрузка...</td></tr></tbody></table></div></div>
@endsection
@push('scripts')
<script>
const esc=v=>$('<div>').text(v??'').html();let controlData=null,currentBucket='critical';
function st(s){return {new:'Новая',in_progress:'В работе',review:'На проверке',completed:'Выполнена',cancelled:'Отменена'}[s]||s}
function reasonLabel(c){return {waiting_data:'Ожидаю данные',technical:'Техническая проблема',dependency:'Зависимость от другого подразделения',workload:'Высокая загрузка',other:'Другое'}[c]||c}
function fmtDate(v){return v?new Date(v).toLocaleString('ru-RU'):'—'}
function renderQueue(){if(!controlData)return;let a=controlData.buckets[currentBucket]||[],h='';a.forEach(t=>{let reason=t.overdue_reason?`<div class="small mt-2"><span class="badge text-bg-light border">Причина: ${esc(reasonLabel(t.overdue_reason.code))}</span>${t.overdue_reason.comment?` <span class="text-muted">${esc(t.overdue_reason.comment)}</span>`:''}</div>`:'';h+=`<div class="border rounded p-3 mb-2 ${t.overdue?'border-danger':''}"><div class="d-flex gap-3 align-items-start flex-wrap"><div class="flex-grow-1"><div class="d-flex gap-2 flex-wrap"><a class="fw-semibold text-decoration-none" href="{{ url('/tasks') }}?task=${t.id}">${esc(t.title)}</a>${t.priority==='critical'?'<span class="badge text-bg-danger">Критическая</span>':''}<span class="badge text-bg-light border">${esc(st(t.status))}</span></div><div class="small text-muted mt-1"><b>${esc(t.assignee||'—')}</b> · ${esc(t.department||'—')} · срок ${fmtDate(t.due_at)} · прогресс ${t.progress}%</div>${reason}<div class="small text-secondary mt-1">Последняя активность: ${fmtDate(t.updated_at)}</div></div><a class="btn btn-sm btn-outline-primary" href="{{ url('/tasks') }}?task=${t.id}">Открыть</a></div></div>`});$('#controlQueue').html(h||'<div class="text-center text-muted py-4">В этой очереди задач нет</div>')}
function loadControl(){$.get('{{ route('control.data') }}',r=>{controlData=r;let s=r.summary;$('#sumEmployees').text(s.employees);$('#sumTasks').text(s.tasks);$('#sumCritical,#cntCritical').text(s.critical);$('#sumOverdue,#cntOverdue').text(s.overdue);$('#sumReview,#cntReview').text(s.review);$('#sumToday,#cntToday').text(s.today);$('#sumTomorrow,#cntTomorrow').text(s.tomorrow);$('#sumStale,#cntStale').text(s.stale);let h='';r.employees.forEach(x=>{h+=`<tr class="${x.overdue?'table-danger':''}"><td><strong>${esc(x.full_name)}</strong><div class="small text-muted">${esc(x.position||'')}</div></td><td>${esc(x.department||'—')}</td><td class="text-center">${x.total}</td><td class="text-center"><span class="badge text-bg-success">${x.completed}</span></td><td class="text-center"><span class="badge text-bg-warning">${x.review}</span></td><td class="text-center"><span class="badge text-bg-${x.overdue?'danger':'secondary'}">${x.overdue}</span></td><td><div class="progress" style="height:20px"><div class="progress-bar" style="width:${x.progress}%">${x.progress}%</div></div></td><td class="text-end"><a class="btn btn-sm btn-outline-primary" href="{{ route('tasks.page') }}?assigned_to=${x.id}">Задачи</a></td></tr>`});$('#controlRows').html(h||'<tr><td colspan="8" class="text-center py-5 text-muted">Нет сотрудников для контроля</td></tr>');renderQueue()}).fail(r=>$('#controlRows').html(`<tr><td colspan="8" class="text-center py-5 text-danger">${esc(r.responseJSON?.message||'Ошибка загрузки')}</td></tr>`))}
$('#controlTabs').on('click','[data-bucket]',function(){$('#controlTabs .nav-link').removeClass('active');$(this).addClass('active');currentBucket=$(this).data('bucket');renderQueue()});
loadControl();
</script>
@endpush

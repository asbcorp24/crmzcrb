@extends('layouts.app')
@section('title','Контроль — CRM ЗЦРБ')
@section('header','Контроль исполнения')
@section('content')
<div class="row g-3 mb-4" id="summaryCards">
  <div class="col-md-4 col-xl"><div class="card stat-card h-100"><div class="card-body"><div class="text-muted small">Сотрудников</div><div class="fs-3 fw-bold" id="sumEmployees">0</div></div></div></div>
  <div class="col-md-4 col-xl"><div class="card stat-card h-100"><div class="card-body"><div class="text-muted small">Всего задач</div><div class="fs-3 fw-bold" id="sumTasks">0</div></div></div></div>
  <div class="col-md-4 col-xl"><div class="card stat-card h-100"><div class="card-body"><div class="text-muted small">Выполнено</div><div class="fs-3 fw-bold text-success" id="sumCompleted">0</div></div></div></div>
  <div class="col-md-4 col-xl"><div class="card stat-card h-100"><div class="card-body"><div class="text-muted small">На проверке</div><div class="fs-3 fw-bold text-warning" id="sumReview">0</div></div></div></div>
  <div class="col-md-4 col-xl"><div class="card stat-card h-100"><div class="card-body"><div class="text-muted small">Просрочено</div><div class="fs-3 fw-bold text-danger" id="sumOverdue">0</div></div></div></div>
</div>
<div class="card border-0 shadow-sm"><div class="card-header bg-white d-flex align-items-center"><strong>Исполнение по сотрудникам</strong><button class="btn btn-sm btn-outline-secondary ms-auto" onclick="loadControl()"><i class="bi bi-arrow-clockwise me-1"></i>Обновить</button></div><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>Сотрудник</th><th>Подразделение</th><th class="text-center">Задач</th><th class="text-center">Выполнено</th><th class="text-center">На проверке</th><th class="text-center">Просрочено</th><th style="width:220px">Средний прогресс</th><th></th></tr></thead><tbody id="controlRows"><tr><td colspan="8" class="text-center py-5">Загрузка...</td></tr></tbody></table></div></div>
@endsection
@push('scripts')
<script>
const esc=v=>$('<div>').text(v??'').html();
function loadControl(){$.get('{{ route('control.data') }}',r=>{let s=r.summary;$('#sumEmployees').text(s.employees);$('#sumTasks').text(s.tasks);$('#sumCompleted').text(s.completed);$('#sumReview').text(s.review);$('#sumOverdue').text(s.overdue);let h='';r.employees.forEach(x=>{h+=`<tr class="${x.overdue?'table-danger':''}"><td><strong>${esc(x.full_name)}</strong><div class="small text-muted">${esc(x.position||'')}</div></td><td>${esc(x.department||'—')}</td><td class="text-center">${x.total}</td><td class="text-center"><span class="badge text-bg-success">${x.completed}</span></td><td class="text-center"><span class="badge text-bg-warning">${x.review}</span></td><td class="text-center"><span class="badge text-bg-${x.overdue?'danger':'secondary'}">${x.overdue}</span></td><td><div class="progress" style="height:20px"><div class="progress-bar" style="width:${x.progress}%">${x.progress}%</div></div></td><td class="text-end"><a class="btn btn-sm btn-outline-primary" href="{{ route('tasks.page') }}?assigned_to=${x.id}">Задачи</a></td></tr>`});$('#controlRows').html(h||'<tr><td colspan="8" class="text-center py-5 text-muted">Нет сотрудников для контроля</td></tr>')}).fail(r=>$('#controlRows').html(`<tr><td colspan="8" class="text-center py-5 text-danger">${esc(r.responseJSON?.message||'Ошибка загрузки')}</td></tr>`))}
loadControl();
</script>
@endpush

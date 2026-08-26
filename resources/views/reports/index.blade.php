@extends('layouts.app')
@section('title','Отчёты — CRM ЗЦРБ')
@section('header','Отчёты для руководства')
@section('content')
<div class="card border-0 shadow-sm mb-3"><div class="card-body"><div class="row g-2 align-items-end">
  <div class="col-md-3"><label class="form-label">Вид отчёта</label><select id="reportType" class="form-select"><option value="tasks">Исполнение задач</option><option value="employees">По сотрудникам</option><option value="departments">По подразделениям</option><option value="meetings">Протоколы совещаний</option></select></div>
  <div class="col-md-2"><label class="form-label">С</label><input id="dateFrom" type="date" class="form-control" value="{{ now()->startOfMonth()->format('Y-m-d') }}"></div>
  <div class="col-md-2"><label class="form-label">По</label><input id="dateTo" type="date" class="form-control" value="{{ now()->endOfMonth()->format('Y-m-d') }}"></div>
  <div class="col-md-3"><label class="form-label">Сотрудник</label><select id="reportUser" class="form-select"><option value="">Все сотрудники</option>@foreach($users as $u)<option value="{{ $u->id }}">{{ $u->full_name }}</option>@endforeach</select></div>
  <div class="col-md-2"><label class="form-label">Статус</label><select id="reportStatus" class="form-select"><option value="">Все</option><option value="new">Новая</option><option value="in_progress">В работе</option><option value="review">На проверке</option><option value="completed">Выполнено</option><option value="cancelled">Отменено</option></select></div>
  <div class="col-md-4"><label class="form-label">Подразделение</label><select id="reportDepartment" class="form-select"><option value="">Все подразделения</option>@foreach($departments as $d)<option value="{{ $d->id }}">{{ $d->name }}</option>@endforeach</select></div>
  <div class="col-md-8 d-flex gap-2 justify-content-md-end"><button class="btn btn-primary" onclick="loadReport()"><i class="bi bi-funnel me-1"></i>Сформировать</button><button class="btn btn-outline-success" onclick="exportCsv()"><i class="bi bi-file-earmark-spreadsheet me-1"></i>Excel CSV</button><button class="btn btn-outline-danger" onclick="exportPdf()"><i class="bi bi-file-earmark-pdf me-1"></i>PDF</button></div>
</div></div></div>

<div id="reportSummary" class="row g-3 mb-3"></div>
<div class="card border-0 shadow-sm"><div class="card-header bg-white d-flex align-items-center"><b id="reportTitle">Отчёт</b><span id="reportCount" class="badge text-bg-light border ms-2">0</span></div><div class="table-responsive"><table class="table table-hover table-sm align-middle mb-0"><thead id="reportHead"></thead><tbody id="reportRows"><tr><td class="text-center py-5 text-muted">Загрузка...</td></tr></tbody></table></div></div>
@endsection
@push('scripts')
<script>
function escR(v){return $('<div>').text(v??'').html()}
function params(){return {type:$('#reportType').val(),date_from:$('#dateFrom').val(),date_to:$('#dateTo').val(),user_id:$('#reportUser').val(),department_id:$('#reportDepartment').val(),status:$('#reportStatus').val()}}
function queryString(){return new URLSearchParams(params()).toString()}
function loadReport(){$('#reportRows').html('<tr><td class="text-center py-5 text-muted">Загрузка...</td></tr>');$.get('{{ route('reports.data') }}',params(),r=>{$('#reportTitle').text(r.title);$('#reportCount').text(r.rows.length);$('#reportSummary').html(Object.entries(r.summary||{}).map(([k,v])=>`<div class="col-6 col-md-3 col-xl"><div class="card stat-card h-100"><div class="card-body"><div class="small text-muted">${escR(k)}</div><div class="fs-4 fw-bold">${escR(v)}</div></div></div></div>`).join(''));$('#reportHead').html('<tr>'+r.headers.map(h=>`<th>${escR(h)}</th>`).join('')+'</tr>');let h='';r.rows.forEach(row=>{h+='<tr>'+r.keys.map(k=>`<td>${escR(row[k]??'')}</td>`).join('')+'</tr>'});$('#reportRows').html(h||`<tr><td colspan="${r.headers.length}" class="text-center py-5 text-muted">Нет данных за выбранный период</td></tr>`)}).fail(x=>$('#reportRows').html(`<tr><td class="text-center py-5 text-danger">${escR(x.responseJSON?.message||'Ошибка формирования отчёта')}</td></tr>`))}
function exportCsv(){window.location.href='{{ route('reports.csv') }}?'+queryString()}
function exportPdf(){window.open('{{ route('reports.print') }}?'+queryString(),'_blank')}
$('#reportType,#reportUser,#reportDepartment,#reportStatus,#dateFrom,#dateTo').on('change',loadReport);loadReport();
</script>
@endpush

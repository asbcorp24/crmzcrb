@extends('layouts.app')
@section('title','Аналитика аттестации — CRM')
@section('header','Аналитика аттестации')
@section('content')
@include('analytics._nav')
<div class="d-flex flex-wrap gap-2 align-items-center mb-3">
<a href="{{ route('attestation.page') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> К аттестации</a>
@if($campaign)
<form class="ms-auto" method="GET"><select class="form-select" name="campaign" onchange="this.form.submit()">@foreach($campaigns as $c)<option value="{{ $c->id }}" {{ $campaign->id==$c->id?'selected':'' }}>{{ $c->title }}</option>@endforeach</select></form>
@endif
</div>
@if(!$campaign)
<div class="alert alert-info">Периодов аттестации пока нет.</div>
@else
<div class="card border-0 shadow-sm mb-3"><div class="card-body"><h4 class="mb-1">{{ $campaign->title }}</h4><div class="text-muted">{{ $campaign->description }}</div></div></div>
<div class="row g-3 mb-3">
<div class="col-md-3"><div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="text-muted small">Аттестуемых</div><div class="display-6 fw-semibold">{{ $targets->count() }}</div></div></div></div>
<div class="col-md-3"><div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="text-muted small">Средняя рабочих связей</div><div class="display-6 fw-semibold">{{ $relationAvg ?? '—' }}</div><div class="small text-muted">{{ $relationScores->count() }} оценок</div></div></div></div>
<div class="col-md-3"><div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="text-muted small">Средняя комиссии</div><div class="display-6 fw-semibold">{{ $commissionAvg ?? '—' }}</div><div class="small text-muted">{{ $commissionScores->count() }} оценок</div></div></div></div>
<div class="col-md-3"><div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="text-muted small">Готовность комиссии</div><div class="display-6 fw-semibold">{{ $possibleCommission?round($commissionScores->count()*100/$possibleCommission,1):0 }}%</div><div class="small text-muted">{{ $commissionScores->count() }}/{{ $possibleCommission }}</div></div></div></div>
</div>
<div class="row g-3 mb-3">
<div class="col-lg-6"><div class="card border-0 shadow-sm h-100"><div class="card-header bg-white"><b>Распределение рабочих связей</b></div><div class="card-body"><canvas data-height="300" data-crm-chart="donut" data-chart='@json(["labels"=>["Оценка 1","Оценка 2","Оценка 3","Оценка 4"],"values"=>array_values($relationDistribution)])'></canvas></div></div></div>
<div class="col-lg-6"><div class="card border-0 shadow-sm h-100"><div class="card-header bg-white"><b>Распределение оценок комиссии</b></div><div class="card-body"><canvas data-height="300" data-crm-chart="donut" data-chart='@json(["labels"=>["Оценка 1","Оценка 2","Оценка 3","Оценка 4"],"values"=>array_values($commissionDistribution)])'></canvas></div></div></div>
</div>
<div class="card border-0 shadow-sm mb-3"><div class="card-header bg-white"><b>Средние оценки по подразделениям</b></div><div class="card-body"><canvas data-height="320" data-crm-chart="bar" data-chart='@json(["labels"=>$departmentRows->pluck("name"),"values"=>$departmentRows->pluck("commission_avg")->map(fn($v)=>(float)($v??0))])'></canvas></div></div>
<div class="card border-0 shadow-sm mb-3"><div class="card-header bg-white"><b>Подразделения</b></div><div class="table-responsive"><table class="table table-hover mb-0 align-middle"><thead><tr><th>Подразделение</th><th>Сотрудников</th><th>Средняя связей</th><th>Оценок связей</th><th>Оценивали</th><th>Средняя комиссии</th><th>Оценок комиссии</th><th>Готовность</th></tr></thead><tbody>@foreach($departmentRows as $r)<tr><td><b>{{ $r['name'] }}</b></td><td>{{ $r['employees'] }}</td><td>{{ $r['relation_avg'] ?? '—' }}</td><td>{{ $r['relation_count'] }}</td><td>{{ $r['relation_evaluators'] }}</td><td>{{ $r['commission_avg'] ?? '—' }}</td><td>{{ $r['commission_count'] }}</td><td>{{ $r['commission_completion'] }}%</td></tr>@endforeach</tbody></table></div></div>
<div class="card border-0 shadow-sm mb-3"><div class="card-header bg-white"><b>Рейтинг сотрудников</b><div class="small text-muted">Сводная оценка = среднее между средней оценкой рабочих связей и средней оценкой комиссии, если оба показателя есть.</div></div><div class="table-responsive"><table class="table table-hover mb-0 align-middle"><thead><tr><th>#</th><th>ФИО</th><th>Подразделение</th><th>Должность</th><th>Связи</th><th>Кол.</th><th>Комиссия</th><th>Кол.</th><th>Итог</th></tr></thead><tbody>@foreach($employeeRows as $r)<tr><td>{{ $loop->iteration }}</td><td><b>{{ $r['user']->full_name }}</b></td><td>{{ $r['user']->department?->name }}</td><td>{{ $r['user']->position }}</td><td>{{ $r['relation_avg'] ?? '—' }}</td><td>{{ $r['relation_count'] }}</td><td>{{ $r['commission_avg'] ?? '—' }}</td><td>{{ $r['commission_count'] }}</td><td class="fw-bold">{{ $r['combined_avg'] ?? '—' }}</td></tr>@endforeach</tbody></table></div></div>
<div class="card border-0 shadow-sm"><div class="card-header bg-white"><b>Прогресс членов комиссии</b></div><div class="card-body"><canvas data-height="280" data-crm-chart="bar" data-chart='@json(["labels"=>$memberProgress->map(fn($r)=>$r["user"]->full_name),"values"=>$memberProgress->pluck("percent")])'></canvas></div><div class="table-responsive"><table class="table mb-0"><thead><tr><th>Член комиссии</th><th>Подразделение</th><th>Оценено</th><th>Всего</th><th>Готовность</th></tr></thead><tbody>@foreach($memberProgress as $r)<tr><td>{{ $r['user']->full_name }}</td><td>{{ $r['user']->department?->name }}</td><td>{{ $r['count'] }}</td><td>{{ $r['expected'] }}</td><td><b>{{ $r['percent'] }}%</b></td></tr>@endforeach</tbody></table></div></div>
@endif
@endsection
@push('scripts')<script src="/js/crm-charts.js"></script>@endpush

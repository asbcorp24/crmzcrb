@extends('layouts.app')
@section('title','Аналитика планов — CRM')
@section('header','Аналитика планов')
@section('content')
@include('analytics._nav')
@php
$kpiCards=[['Всего',$kpi['all']],['Активные',$kpi['active']],['Выполнено',$kpi['completed']],['Просрочено',$kpi['overdue']],['Средний прогресс',$kpi['avg_progress'].'%']];
$statusChart=['labels'=>$statuses->keys()->values()->all(),'values'=>$statuses->values()->all()];
$progressChart=['labels'=>array_keys($progressBuckets),'values'=>array_values($progressBuckets)];
@endphp
<div class="row g-3 mb-3">@foreach($kpiCards as $card)<div class="col-6 col-xl"><div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="small text-muted">{{ $card[0] }}</div><div class="fs-2 fw-semibold">{{ $card[1] }}</div></div></div></div>@endforeach</div>
<div class="row g-3 mb-3"><div class="col-xl-6"><div class="card border-0 shadow-sm h-100"><div class="card-header bg-white"><b>Статусы планов</b></div><div class="card-body"><canvas data-height="300" data-crm-chart="donut" data-chart='@json($statusChart)'></canvas></div></div></div><div class="col-xl-6"><div class="card border-0 shadow-sm h-100"><div class="card-header bg-white"><b>Распределение по прогрессу</b></div><div class="card-body"><canvas data-height="300" data-crm-chart="bar" data-chart='@json($progressChart)'></canvas></div></div></div></div>
<div class="card border-0 shadow-sm"><div class="card-header bg-white"><b>Все планы</b></div><div class="table-responsive"><table class="table table-hover mb-0"><thead><tr><th>План</th><th>Сотрудник</th><th>Подразделение</th><th>Статус</th><th>Прогресс</th><th>Период</th></tr></thead><tbody>@foreach($rows as $p)<tr><td><b>{{ $p->title }}</b></td><td>{{ $p->user?->full_name ?: '—' }}</td><td>{{ $p->user?->department?->name ?: '—' }}</td><td>{{ $p->status }}</td><td style="min-width:160px"><div class="progress" style="height:8px"><div class="progress-bar" style="width:{{ min(100,(int)$p->progress) }}%"></div></div><div class="small mt-1">{{ $p->progress }}%</div></td><td>{{ $p->period_start?->format('d.m.Y') }} — {{ $p->period_end?->format('d.m.Y') }}</td></tr>@endforeach</tbody></table></div></div>
@endsection
@push('scripts')<script src="/js/crm-charts.js"></script>@endpush

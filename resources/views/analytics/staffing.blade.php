@extends('layouts.app')
@section('title','Аналитика штатного расписания — CRM')
@section('header','Аналитика штатного расписания')
@section('content')
@include('analytics._nav')
@php
$kpiCards=[['Штатных позиций',$kpi['positions']],['План ставок',$kpi['planned']],['Занято ставок',$kpi['occupied']],['Вакантно ставок',$kpi['vacant']],['Укомплектованность',$kpi['filled'].'%']];
$plannedChart=['labels'=>$departmentRows->pluck('name')->values()->all(),'values'=>$departmentRows->pluck('planned')->values()->all()];
$vacantChart=['labels'=>$departmentRows->pluck('name')->values()->all(),'values'=>$departmentRows->pluck('vacant')->values()->all()];
@endphp
<div class="row g-3 mb-3">@foreach($kpiCards as $card)<div class="col-6 col-xl"><div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="small text-muted">{{ $card[0] }}</div><div class="fs-2 fw-semibold">{{ $card[1] }}</div></div></div></div>@endforeach</div>
<div class="row g-3 mb-3"><div class="col-xl-6"><div class="card border-0 shadow-sm h-100"><div class="card-header bg-white"><b>План ставок по подразделениям</b></div><div class="card-body"><canvas data-height="320" data-crm-chart="bar" data-chart='@json($plannedChart)'></canvas></div></div></div><div class="col-xl-6"><div class="card border-0 shadow-sm h-100"><div class="card-header bg-white"><b>Вакантные ставки</b></div><div class="card-body"><canvas data-height="320" data-crm-chart="bar" data-chart='@json($vacantChart)'></canvas></div></div></div></div>
<div class="card border-0 shadow-sm"><div class="card-header bg-white"><b>Укомплектованность подразделений</b></div><div class="table-responsive"><table class="table table-hover mb-0"><thead><tr><th>Подразделение</th><th>Позиций</th><th>План ставок</th><th>Занято</th><th>Вакантно</th><th>Укомплектованность</th></tr></thead><tbody>@foreach($departmentRows as $r)<tr><td><b>{{ $r['name'] }}</b></td><td>{{ $r['positions'] }}</td><td>{{ $r['planned'] }}</td><td>{{ $r['occupied'] }}</td><td>{{ $r['vacant'] }}</td><td>{{ $r['filled'] }}%</td></tr>@endforeach</tbody></table></div></div>
@endsection
@push('scripts')<script src="/js/crm-charts.js"></script>@endpush

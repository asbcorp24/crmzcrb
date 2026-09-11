@extends('layouts.app')
@section('title','Аналитика отсутствий — CRM')
@section('header','Аналитика отсутствий и замещений')
@section('content')
@include('analytics._nav')
@php
$kpiCards=[['Всего отсутствий',$kpi['all']],['Отсутствуют сегодня',$kpi['today']],['В ближайшие 30 дней',$kpi['upcoming']],['Дней отсутствий',$kpi['days']]];
$typeNames=['vacation'=>'Отпуск','sick_leave'=>'Больничный','business_trip'=>'Командировка','training'=>'Обучение','other'=>'Другое'];
$typeLabels=[]; foreach($types->keys() as $type){$typeLabels[]=$typeNames[$type]??$type;}
$typeChart=['labels'=>$typeLabels,'values'=>$types->values()->all()];
$departmentChart=['labels'=>$departmentRows->pluck('name')->values()->all(),'values'=>$departmentRows->pluck('days')->values()->all()];
@endphp
<div class="row g-3 mb-3">@foreach($kpiCards as $card)<div class="col-6 col-xl-3"><div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="small text-muted">{{ $card[0] }}</div><div class="fs-2 fw-semibold">{{ $card[1] }}</div></div></div></div>@endforeach</div>
<div class="row g-3 mb-3"><div class="col-xl-5"><div class="card border-0 shadow-sm h-100"><div class="card-header bg-white"><b>Причины отсутствий</b></div><div class="card-body"><canvas data-height="300" data-crm-chart="donut" data-chart='@json($typeChart)'></canvas></div></div></div><div class="col-xl-7"><div class="card border-0 shadow-sm h-100"><div class="card-header bg-white"><b>Дни отсутствий по подразделениям</b></div><div class="card-body"><canvas data-height="300" data-crm-chart="bar" data-chart='@json($departmentChart)'></canvas></div></div></div></div>
<div class="card border-0 shadow-sm"><div class="card-header bg-white"><b>Свод по подразделениям</b></div><div class="table-responsive"><table class="table table-hover mb-0"><thead><tr><th>Подразделение</th><th>Случаев отсутствий</th><th>Дней</th></tr></thead><tbody>@foreach($departmentRows as $r)<tr><td><b>{{ $r['name'] }}</b></td><td>{{ $r['cases'] }}</td><td>{{ $r['days'] }}</td></tr>@endforeach</tbody></table></div></div>
@endsection
@push('scripts')<script src="/js/crm-charts.js"></script>@endpush

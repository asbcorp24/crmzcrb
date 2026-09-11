@extends('layouts.app')
@section('title','Аналитика отсутствий — CRM')
@section('header','Аналитика отсутствий и замещений')
@section('content')
@include('analytics._nav')
<div class="row g-3 mb-3">@foreach([['Всего отсутствий',$kpi['all']],['Отсутствуют сегодня',$kpi['today']],['В ближайшие 30 дней',$kpi['upcoming']],['Дней отсутствий',$kpi['days']]] as $x)<div class="col-6 col-xl-3"><div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="small text-muted">{{ $x[0] }}</div><div class="fs-2 fw-semibold">{{ $x[1] }}</div></div></div></div>@endforeach</div>
<div class="row g-3 mb-3"><div class="col-xl-5"><div class="card border-0 shadow-sm h-100"><div class="card-header bg-white"><b>Причины отсутствий</b></div><div class="card-body"><canvas data-height="300" data-crm-chart="donut" data-chart='@json(["labels"=>$types->keys()->map(fn($t)=>["vacation"=>"Отпуск","sick_leave"=>"Больничный","business_trip"=>"Командировка","training"=>"Обучение","other"=>"Другое"][$t]??$t)->values(),"values"=>$types->values()])'></canvas></div></div></div><div class="col-xl-7"><div class="card border-0 shadow-sm h-100"><div class="card-header bg-white"><b>Дни отсутствий по подразделениям</b></div><div class="card-body"><canvas data-height="300" data-crm-chart="bar" data-chart='@json(["labels"=>$departmentRows->pluck("name"),"values"=>$departmentRows->pluck("days")])'></canvas></div></div></div></div>
<div class="card border-0 shadow-sm"><div class="card-header bg-white"><b>Свод по подразделениям</b></div><div class="table-responsive"><table class="table table-hover mb-0"><thead><tr><th>Подразделение</th><th>Случаев отсутствий</th><th>Дней</th></tr></thead><tbody>@foreach($departmentRows as $r)<tr><td><b>{{ $r['name'] }}</b></td><td>{{ $r['cases'] }}</td><td>{{ $r['days'] }}</td></tr>@endforeach</tbody></table></div></div>
@endsection
@push('scripts')<script src="/js/crm-charts.js"></script>@endpush

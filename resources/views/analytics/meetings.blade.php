@extends('layouts.app')
@section('title','Аналитика совещаний — CRM')
@section('header','Аналитика совещаний')
@section('content')
@include('analytics._nav')
@php
$kpiCards=[['Совещаний',$kpi['all']],['Предстоящие',$kpi['upcoming']],['Закрытые',$kpi['closed']],['Поручений',$kpi['items']],['Связано с задачами',$kpi['task_items']]];
$meetingsChart=['labels'=>$months,'series'=>[['label'=>'Совещания','values'=>$counts]]];
$statusChart=['labels'=>$statuses->keys()->values()->all(),'values'=>$statuses->values()->all()];
@endphp
<div class="row g-3 mb-3">@foreach($kpiCards as $card)<div class="col-6 col-xl"><div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="small text-muted">{{ $card[0] }}</div><div class="fs-2 fw-semibold">{{ $card[1] }}</div></div></div></div>@endforeach</div>
<div class="row g-3 mb-3"><div class="col-xl-7"><div class="card border-0 shadow-sm h-100"><div class="card-header bg-white"><b>Совещания по месяцам</b></div><div class="card-body"><canvas data-height="300" data-crm-chart="line" data-chart='@json($meetingsChart)'></canvas></div></div></div><div class="col-xl-5"><div class="card border-0 shadow-sm h-100"><div class="card-header bg-white"><b>Статусы</b></div><div class="card-body"><canvas data-height="300" data-crm-chart="donut" data-chart='@json($statusChart)'></canvas></div></div></div></div>
<div class="card border-0 shadow-sm"><div class="card-header bg-white"><b>Совещания</b></div><div class="table-responsive"><table class="table table-hover mb-0"><thead><tr><th>Название</th><th>Дата</th><th>Статус</th><th>Участников</th><th>Поручений</th></tr></thead><tbody>@foreach($meetings as $m)<tr><td><b>{{ $m->title }}</b></td><td>{{ $m->held_at?->format('d.m.Y H:i') }}</td><td>{{ $m->status }}</td><td>{{ $m->participants->count() }}</td><td>{{ $m->items->count() }}</td></tr>@endforeach</tbody></table></div></div>
@endsection
@push('scripts')<script src="/js/crm-charts.js"></script>@endpush

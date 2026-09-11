@extends('layouts.app')
@section('title','Аналитика совещаний — CRM')
@section('header','Аналитика совещаний')
@section('content')
@include('analytics._nav')
<div class="row g-3 mb-3">@foreach([['Совещаний',$kpi['all']],['Предстоящие',$kpi['upcoming']],['Закрытые',$kpi['closed']],['Поручений',$kpi['items']],['Связано с задачами',$kpi['task_items']]] as $x)<div class="col-6 col-xl"><div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="small text-muted">{{ $x[0] }}</div><div class="fs-2 fw-semibold">{{ $x[1] }}</div></div></div></div>@endforeach</div>
<div class="row g-3 mb-3"><div class="col-xl-7"><div class="card border-0 shadow-sm h-100"><div class="card-header bg-white"><b>Совещания по месяцам</b></div><div class="card-body"><canvas data-height="300" data-crm-chart="line" data-chart='@json(["labels"=>$months,"series"=>[["label"=>"Совещания","values"=>$counts]]])'></canvas></div></div></div><div class="col-xl-5"><div class="card border-0 shadow-sm h-100"><div class="card-header bg-white"><b>Статусы</b></div><div class="card-body"><canvas data-height="300" data-crm-chart="donut" data-chart='@json(["labels"=>$statuses->keys()->values(),"values"=>$statuses->values()])'></canvas></div></div></div></div>
<div class="card border-0 shadow-sm"><div class="card-header bg-white"><b>Совещания</b></div><div class="table-responsive"><table class="table table-hover mb-0"><thead><tr><th>Название</th><th>Дата</th><th>Статус</th><th>Участников</th><th>Поручений</th></tr></thead><tbody>@foreach($meetings as $m)<tr><td><b>{{ $m->title }}</b></td><td>{{ $m->held_at?->format('d.m.Y H:i') }}</td><td>{{ $m->status }}</td><td>{{ $m->participants->count() }}</td><td>{{ $m->items->count() }}</td></tr>@endforeach</tbody></table></div></div>
@endsection
@push('scripts')<script src="/js/crm-charts.js"></script>@endpush

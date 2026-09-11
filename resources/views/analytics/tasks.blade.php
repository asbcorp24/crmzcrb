@extends('layouts.app')
@section('title','Аналитика задач — CRM')
@section('header','Аналитика задач и контроля')
@section('content')
@include('analytics._nav')
<div class="row g-3 mb-3">
@foreach([['Всего',$kpi['all']],['Открыто',$kpi['open']],['Просрочено',$kpi['overdue']],['На проверке',$kpi['review']],['Выполнено',$kpi['done']],['Выполнение',$kpi['completion'].'%']] as $x)<div class="col-6 col-xl-2"><div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="small text-muted">{{ $x[0] }}</div><div class="fs-2 fw-semibold">{{ $x[1] }}</div></div></div></div>@endforeach
</div>
<div class="row g-3 mb-3">
<div class="col-xl-7"><div class="card border-0 shadow-sm h-100"><div class="card-header bg-white"><b>Создано и выполнено по месяцам</b></div><div class="card-body"><canvas data-height="300" data-crm-chart="line" data-chart='@json(["labels"=>$trend["labels"],"series"=>[["label"=>"Создано","values"=>$trend["created"]],["label"=>"Выполнено","values"=>$trend["completed"]]]])'></canvas></div></div></div>
<div class="col-xl-5"><div class="card border-0 shadow-sm h-100"><div class="card-header bg-white"><b>Статусы задач</b></div><div class="card-body"><canvas data-height="300" data-crm-chart="donut" data-chart='@json(["labels"=>$statuses->keys()->values(),"values"=>$statuses->values()])'></canvas></div></div></div>
</div>
<div class="row g-3 mb-3"><div class="col-xl-6"><div class="card border-0 shadow-sm h-100"><div class="card-header bg-white"><b>Приоритеты</b></div><div class="card-body"><canvas data-height="280" data-crm-chart="bar" data-chart='@json(["labels"=>$priorities->keys()->values(),"values"=>$priorities->values()])'></canvas></div></div></div><div class="col-xl-6"><div class="card border-0 shadow-sm h-100"><div class="card-header bg-white"><b>Задачи по подразделениям</b></div><div class="card-body"><canvas data-height="280" data-crm-chart="bar" data-chart='@json(["labels"=>$departmentRows->pluck("name"),"values"=>$departmentRows->pluck("all")])'></canvas></div></div></div></div>
<div class="card border-0 shadow-sm"><div class="card-header bg-white"><b>Контроль по сотрудникам</b></div><div class="table-responsive"><table class="table table-hover mb-0"><thead><tr><th>Сотрудник</th><th>Подразделение</th><th>Всего</th><th>Открыто</th><th>Просрочено</th><th>Выполнено</th><th>Средний прогресс</th></tr></thead><tbody>@foreach($employeeRows as $r)<tr><td><b>{{ $r['user']->full_name }}</b></td><td>{{ $r['user']->department?->name ?: '—' }}</td><td>{{ $r['all'] }}</td><td>{{ $r['open'] }}</td><td>{{ $r['overdue'] }}</td><td>{{ $r['done'] }}</td><td>{{ $r['avg_progress'] }}%</td></tr>@endforeach</tbody></table></div></div>
@endsection
@push('scripts')<script src="/js/crm-charts.js"></script>@endpush

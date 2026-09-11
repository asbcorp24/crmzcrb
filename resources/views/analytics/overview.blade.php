@extends('layouts.app')
@section('title','Аналитика — CRM')
@section('header','Аналитика организации')
@section('content')
@include('analytics._nav')
<div class="row g-3 mb-3">
@foreach([['Сотрудники',$kpi['employees'],'bi-people'],['Подразделения',$kpi['departments'],'bi-diagram-3'],['Открытые задачи',$kpi['open'],'bi-list-task'],['Просрочено',$kpi['overdue'],'bi-exclamation-triangle'],['Активные планы',$kpi['active_plans'],'bi-calendar3'],['Просроченные планы',$kpi['overdue_plans'],'bi-calendar-x']] as $x)
<div class="col-6 col-xl-2"><div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="small text-muted"><i class="bi {{ $x[2] }} me-1"></i>{{ $x[0] }}</div><div class="fs-2 fw-semibold">{{ $x[1] }}</div></div></div></div>
@endforeach
</div>
<div class="row g-3 mb-3">
<div class="col-xl-7"><div class="card border-0 shadow-sm h-100"><div class="card-header bg-white"><b>Динамика задач за 6 месяцев</b></div><div class="card-body"><canvas data-height="300" data-crm-chart="line" data-chart='@json(["labels"=>$taskTrend["labels"],"series"=>[["label"=>"Создано","values"=>$taskTrend["created"]],["label"=>"Выполнено","values"=>$taskTrend["completed"]]]])'></canvas></div></div></div>
<div class="col-xl-5"><div class="card border-0 shadow-sm h-100"><div class="card-header bg-white"><b>Общая загрузка</b></div><div class="card-body"><canvas data-height="300" data-crm-chart="donut" data-chart='@json(["labels"=>["Открыто","Выполнено","Просрочено"],"values"=>[$kpi["open"],$kpi["done"],$kpi["overdue"]]])'></canvas></div></div></div>
</div>
<div class="card border-0 shadow-sm"><div class="card-header bg-white"><b>Подразделения</b></div><div class="table-responsive"><table class="table table-hover mb-0"><thead><tr><th>Подразделение</th><th>Сотрудники</th><th>Всего задач</th><th>Открыто</th><th>Просрочено</th><th>Выполнено</th></tr></thead><tbody>@foreach($departmentRows as $r)<tr><td><b>{{ $r['name'] }}</b></td><td>{{ $r['employees'] }}</td><td>{{ $r['tasks'] }}</td><td>{{ $r['open'] }}</td><td>{{ $r['overdue'] }}</td><td>{{ $r['done'] }}</td></tr>@endforeach</tbody></table></div></div>
@endsection
@push('scripts')<script src="/js/crm-charts.js"></script>@endpush

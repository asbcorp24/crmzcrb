@extends('layouts.app')
@section('title','Аналитика подразделений — CRM')
@section('header','Аналитика подразделений')
@section('content')
@include('analytics._nav')
<div class="row g-3 mb-3"><div class="col-xl-6"><div class="card border-0 shadow-sm h-100"><div class="card-header bg-white"><b>Задачи по подразделениям</b></div><div class="card-body"><canvas data-height="320" data-crm-chart="bar" data-chart='@json(["labels"=>$rows->pluck("name"),"values"=>$rows->pluck("all")])'></canvas></div></div></div><div class="col-xl-6"><div class="card border-0 shadow-sm h-100"><div class="card-header bg-white"><b>Просроченные задачи</b></div><div class="card-body"><canvas data-height="320" data-crm-chart="bar" data-chart='@json(["labels"=>$rows->pluck("name"),"values"=>$rows->pluck("overdue")])'></canvas></div></div></div></div>
<div class="card border-0 shadow-sm"><div class="card-header bg-white"><b>Свод по подразделениям</b></div><div class="table-responsive"><table class="table table-hover mb-0"><thead><tr><th>Подразделение</th><th>Сотрудники</th><th>Всего задач</th><th>Открыто</th><th>Просрочено</th><th>Выполнено</th><th>Выполнение</th></tr></thead><tbody>@foreach($rows as $r)<tr><td><b>{{ $r['name'] }}</b></td><td>{{ $r['employees'] }}</td><td>{{ $r['all'] }}</td><td>{{ $r['open'] }}</td><td>{{ $r['overdue'] }}</td><td>{{ $r['done'] }}</td><td>{{ $r['completion'] }}%</td></tr>@endforeach</tbody></table></div></div>
@endsection
@push('scripts')<script src="/js/crm-charts.js"></script>@endpush

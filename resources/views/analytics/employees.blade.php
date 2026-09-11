@extends('layouts.app')
@section('title','Аналитика сотрудников — CRM')
@section('header','Аналитика сотрудников')
@section('content')
@include('analytics._nav')
@php
$kpiCards=[['Всего',$kpi['all']],['Активные',$kpi['active']],['Отключены',$kpi['inactive']],['Подразделения',$kpi['departments']],['Руководители',$kpi['managers']]];
$roleNames=['admin'=>'Администратор','manager'=>'Руководитель','hr'=>'Отдел кадров','employee'=>'Сотрудник'];
$roleLabels=[]; foreach($roles->keys() as $role){$roleLabels[]=$roleNames[$role]??$role;}
$rolesChart=['labels'=>$roleLabels,'values'=>$roles->values()->all()];
$departmentChart=['labels'=>$departmentRows->pluck('name')->values()->all(),'values'=>$departmentRows->pluck('all')->values()->all()];
$hiresChart=['labels'=>collect($months)->pluck('label')->values()->all(),'series'=>[['label'=>'Принято','values'=>$hires]]];
@endphp
<div class="row g-3 mb-3">@foreach($kpiCards as $card)<div class="col-6 col-xl"><div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="small text-muted">{{ $card[0] }}</div><div class="fs-2 fw-semibold">{{ $card[1] }}</div></div></div></div>@endforeach</div>
<div class="row g-3 mb-3"><div class="col-xl-6"><div class="card border-0 shadow-sm h-100"><div class="card-header bg-white"><b>Распределение по ролям</b></div><div class="card-body"><canvas data-height="300" data-crm-chart="donut" data-chart='@json($rolesChart)'></canvas></div></div></div><div class="col-xl-6"><div class="card border-0 shadow-sm h-100"><div class="card-header bg-white"><b>Численность подразделений</b></div><div class="card-body"><canvas data-height="300" data-crm-chart="bar" data-chart='@json($departmentChart)'></canvas></div></div></div></div>
<div class="card border-0 shadow-sm mb-3"><div class="card-header bg-white"><b>Приём сотрудников за 12 месяцев</b></div><div class="card-body"><canvas data-height="280" data-crm-chart="line" data-chart='@json($hiresChart)'></canvas></div></div>
<div class="card border-0 shadow-sm"><div class="card-header bg-white"><b>Подразделения</b></div><div class="table-responsive"><table class="table table-hover mb-0"><thead><tr><th>Подразделение</th><th>Всего</th><th>Активные</th><th>Руководители</th></tr></thead><tbody>@foreach($departmentRows as $r)<tr><td><b>{{ $r['name'] }}</b></td><td>{{ $r['all'] }}</td><td>{{ $r['active'] }}</td><td>{{ $r['managers'] }}</td></tr>@endforeach</tbody></table></div></div>
@endsection
@push('scripts')<script src="/js/crm-charts.js"></script>@endpush

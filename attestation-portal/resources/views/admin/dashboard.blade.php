@extends('layouts.app')
@section('title','Панель управления')
@section('content')
<div class="d-flex justify-content-between align-items-end mb-4"><div><div class="circuit-title">CONTROL NODE</div><h1 class="h3 mb-0">Панель супер-администратора</h1></div><span class="badge badge-tech">ONLINE</span></div>
<div class="row g-3 mb-4">
@foreach([['Цехов',$workshops->count(),'bi-diagram-3'],['Сотрудников',$usersCount,'bi-people'],['Тестов/анкет',$testsCount,'bi-ui-checks-grid'],['Пройдено',$attemptsCount,'bi-activity']] as $m)
<div class="col-6 col-xl-3"><div class="card h-100"><div class="card-body"><div class="circuit-title"><i class="bi {{ $m[2] }} me-1"></i>{{ $m[0] }}</div><div class="metric mt-2">{{ $m[1] }}</div></div></div></div>
@endforeach
</div>
<div class="row g-3">
<div class="col-lg-8"><div class="card"><div class="card-header"><b>Цеха</b></div><div class="table-responsive"><table class="table mb-0"><thead><tr><th>Цех</th><th>Код</th><th>Сотрудников</th></tr></thead><tbody>@foreach($workshops as $w)<tr><td>{{ $w->name }}</td><td>{{ $w->code ?: '—' }}</td><td>{{ $w->users_count }}</td></tr>@endforeach</tbody></table></div></div></div>
<div class="col-lg-4"><div class="card"><div class="card-body"><div class="circuit-title mb-3">Быстрые действия</div><div class="d-grid gap-2"><a class="btn btn-outline-primary" href="{{ route('admin.users') }}">Создать сотрудников</a><a class="btn btn-outline-primary" href="{{ route('admin.tests.index') }}">Конструктор тестов</a><a class="btn btn-outline-primary" href="{{ route('admin.analytics') }}">Открыть аналитику</a></div></div></div></div>
</div>
@endsection

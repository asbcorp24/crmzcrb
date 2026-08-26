@extends('layouts.app')
@section('title','Главная — CRM ЗЦРБ')
@section('header','Панель сотрудника')
@section('content')
<div class="row g-3 mb-4">
@foreach([['Открытые задачи',$stats['my_open'],'bi-list-check'],['Просрочено',$stats['my_overdue'],'bi-exclamation-triangle'],['На проверке',$stats['my_review'],'bi-eye'],['Выполнено за месяц',$stats['my_done_month'],'bi-check-circle']] as $card)
<div class="col-12 col-md-6 col-xl-3"><div class="card stat-card h-100"><div class="card-body d-flex align-items-center"><i class="bi {{ $card[2] }} fs-2 me-3"></i><div><div class="text-secondary small">{{ $card[0] }}</div><div class="fs-3 fw-semibold">{{ $card[1] }}</div></div></div></div></div>
@endforeach
</div>
@if(isset($stats['team_open']))<div class="alert alert-primary d-flex gap-4"><span><b>{{ $stats['team_open'] }}</b> открытых задач у подчинённых</span><span><b>{{ $stats['team_overdue'] }}</b> просрочено</span></div>@endif
<div class="card border-0 shadow-sm"><div class="card-header bg-white d-flex align-items-center"><b>Ближайшие задачи</b><button class="btn btn-primary btn-sm ms-auto" id="btnNewTask"><i class="bi bi-plus-lg"></i> Новая задача</button></div><div class="list-group list-group-flush">
@forelse($myTasks as $task)<div class="list-group-item py-3 {{ $task->is_overdue ? 'task-overdue' : '' }}"><div class="d-flex"><div class="flex-grow-1"><div class="fw-semibold">{{ $task->title }}</div><div class="small text-secondary">Поставил: {{ $task->creator->full_name }} @if($task->due_at) · срок {{ $task->due_at->format('d.m.Y H:i') }} @endif</div></div><div class="text-end"><span class="badge text-bg-light">{{ $task->progress }}%</span><div class="progress mt-2" style="width:120px;height:6px"><div class="progress-bar" style="width:{{ $task->progress }}%"></div></div></div></div></div>@empty<div class="p-4 text-center text-secondary">Открытых задач нет</div>@endforelse
</div></div>
@endsection

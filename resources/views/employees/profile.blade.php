@extends('layouts.app')
@section('title',$employee->full_name.' — CRM ЗЦРБ')
@section('header','Профиль сотрудника')
@section('content')
<div class="d-flex flex-wrap gap-2 mb-3">
  <a href="{{ route('employees.page') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Сотрудники</a>
  <a href="{{ route('tasks.page',['assigned_to'=>$employee->id]) }}" class="btn btn-outline-primary"><i class="bi bi-check2-square me-1"></i>Все задачи</a>
  <a href="{{ route('plans.page',['user_id'=>$employee->id]) }}" class="btn btn-outline-primary"><i class="bi bi-calendar3 me-1"></i>Планы</a>
</div>

<div class="row g-3 mb-4">
  <div class="col-xl-4">
    <div class="card border-0 shadow-sm h-100"><div class="card-body">
      <div class="d-flex align-items-center mb-3"><div class="rounded-circle bg-primary-subtle text-primary d-flex align-items-center justify-content-center me-3" style="width:64px;height:64px"><i class="bi bi-person fs-2"></i></div><div><h4 class="mb-1">{{ $employee->full_name }}</h4><div class="text-muted">{{ $employee->position }}</div></div></div>
      <dl class="row mb-0 small">
        <dt class="col-5 text-muted">Подразделение</dt><dd class="col-7">{{ $employee->department?->name ?? '—' }}</dd>
        <dt class="col-5 text-muted">Руководитель</dt><dd class="col-7">{{ $employee->manager?->full_name ?? '—' }}</dd>
        <dt class="col-5 text-muted">Email</dt><dd class="col-7">{{ $employee->email }}</dd>
        <dt class="col-5 text-muted">Телефон</dt><dd class="col-7">{{ $employee->phone ?: '—' }}</dd>
        <dt class="col-5 text-muted">Дата приёма</dt><dd class="col-7">{{ $employee->employment_date?->format('d.m.Y') ?? '—' }}</dd>
        <dt class="col-5 text-muted">Статус</dt><dd class="col-7">{!! $employee->is_active ? '<span class="badge text-bg-success">Активен</span>' : '<span class="badge text-bg-secondary">Отключён</span>' !!}</dd>
      </dl>
    </div></div>
  </div>
  <div class="col-xl-8">
    <div class="row g-3">
      @foreach([
        ['Открытые',$stats['open'],'bi-list-check'],['Просрочено',$stats['overdue'],'bi-exclamation-triangle'],['На проверке',$stats['review'],'bi-eye'],['Выполнено',$stats['completed'],'bi-check-circle'],['За месяц',$stats['completed_month'],'bi-calendar-check'],['Средний прогресс',$stats['avg_progress'].'%','bi-graph-up'],['Доля выполнения',$stats['completion_rate'].'%','bi-pie-chart'],['Всего задач',$stats['all'],'bi-collection']
      ] as $card)
      <div class="col-6 col-lg-3"><div class="card stat-card h-100"><div class="card-body"><i class="bi {{ $card[2] }} fs-4"></i><div class="text-muted small mt-2">{{ $card[0] }}</div><div class="fs-4 fw-semibold">{{ $card[1] }}</div></div></div></div>
      @endforeach
    </div>
  </div>
</div>

<div class="row g-4">
  <div class="col-xl-7">
    <div class="card border-0 shadow-sm mb-4"><div class="card-header bg-white"><b>Текущие задачи</b></div><div class="list-group list-group-flush">
      @forelse($activeTasks as $task)
      <a href="{{ route('tasks.page',['task'=>$task->id]) }}" class="list-group-item list-group-item-action py-3 {{ $task->is_overdue?'task-overdue':'' }}"><div class="d-flex justify-content-between gap-3"><div><div class="fw-semibold">{{ $task->title }}</div><div class="small text-muted">{{ $task->creator?->full_name }} @if($task->due_at) · срок {{ $task->due_at->format('d.m.Y H:i') }} @endif</div></div><div class="text-end"><span class="badge text-bg-light border">{{ $task->progress }}%</span><div class="small text-muted mt-1">{{ ['new'=>'Новая','in_progress'=>'В работе','review'=>'На проверке'][$task->status] ?? $task->status }}</div></div></div></a>
      @empty<div class="p-4 text-center text-muted">Активных задач нет</div>@endforelse
    </div></div>

    <div class="card border-0 shadow-sm"><div class="card-header bg-white"><b>Последние выполненные задачи</b></div><div class="list-group list-group-flush">
      @forelse($recentCompleted as $task)
      <a href="{{ route('tasks.page',['task'=>$task->id]) }}" class="list-group-item list-group-item-action"><div class="d-flex justify-content-between"><span>{{ $task->title }}</span><span class="small text-muted">{{ $task->completed_at?->format('d.m.Y') }}</span></div></a>
      @empty<div class="p-4 text-center text-muted">Выполненных задач пока нет</div>@endforelse
    </div></div>
  </div>

  <div class="col-xl-5">
    <div class="card border-0 shadow-sm mb-4"><div class="card-header bg-white"><b>Активные планы</b></div><div class="card-body">
      @forelse($plans as $plan)
      @php($planProgress = $plan->tasks->isEmpty() ? $plan->progress : (int) round($plan->tasks->avg(fn($t) => $t->status === 'completed' ? 100 : $t->progress)))
      <div class="mb-3 pb-3 border-bottom"><div class="d-flex justify-content-between"><div class="fw-semibold">{{ $plan->title }}</div><span>{{ $planProgress }}%</span></div><div class="small text-muted">{{ $plan->period_start->format('d.m.Y') }} — {{ $plan->period_end->format('d.m.Y') }}</div><div class="progress mt-2" style="height:7px"><div class="progress-bar" style="width:{{ $planProgress }}%"></div></div></div>
      @empty<div class="text-center text-muted py-3">Активных планов нет</div>@endforelse
    </div></div>

    <div class="card border-0 shadow-sm"><div class="card-header bg-white"><b>Последняя активность</b></div><div class="list-group list-group-flush">
      @forelse($events as $event)
      <a href="{{ route('tasks.page',['task'=>$event->task_id]) }}" class="list-group-item list-group-item-action"><div class="fw-semibold small">{{ ['created'=>'Создание задачи','updated'=>'Изменение задачи','comment'=>'Комментарий','submitted_for_review'=>'Отправлено на проверку','accepted'=>'Задача принята','rejected'=>'Возврат на доработку','attachment_added'=>'Добавлен файл','attachment_deleted'=>'Удалён файл'][$event->type] ?? $event->type }}</div><div>{{ $event->task?->title }}</div>@if($event->message)<div class="small text-muted">{{ $event->message }}</div>@endif<div class="small text-secondary mt-1">{{ $event->created_at->format('d.m.Y H:i') }}</div></a>
      @empty<div class="p-4 text-center text-muted">История пока пуста</div>@endforelse
    </div></div>
  </div>
</div>
@endsection

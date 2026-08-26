@extends('layouts.app')
@section('title','Мой 360° — CRM ЗЦРБ')
@section('header','Мой рабочий день')
@push('styles')
<style>
.dashboard360-hero{background:linear-gradient(135deg,#fff,#f4f8ff);border:1px solid #e7eef8;border-radius:22px}.kpi360{position:relative;width:112px;height:112px;border-radius:50%;display:grid;place-items:center;background:conic-gradient(var(--ring) calc(var(--p)*1%),#edf1f6 0)}.kpi360:after{content:"";position:absolute;inset:9px;background:#fff;border-radius:50%}.kpi360>div{position:relative;z-index:1;text-align:center}.kpi360 .num{font-size:26px;font-weight:700;line-height:1}.kpi360 .lbl{font-size:11px;color:#667085;margin-top:5px}.task360{border:1px solid #eaecf0;border-radius:16px;background:#fff;transition:.15s}.task360:hover{box-shadow:0 6px 20px rgba(16,24,40,.07);transform:translateY(-1px)}.task360.overdue{border-left:5px solid #dc3545}.task360.review{border-left:5px solid #ffc107}.task360.completed{opacity:.82;background:#fbfdfb}.task360-check{width:28px;height:28px}.quick-comment{background:#f8fafc;border-radius:12px}.dashboard-filter .btn.active{background:#0d6efd;color:white;border-color:#0d6efd}.date-pill{font-size:12px;border-radius:999px;padding:5px 9px;background:#f2f4f7}.status-icon{width:36px;height:36px;border-radius:50%;display:flex;align-items:center;justify-content:center}.status-open{background:#eef4ff;color:#175cd3}.status-review{background:#fff8db;color:#b54708}.status-done{background:#ecfdf3;color:#027a48}.status-overdue{background:#fef3f2;color:#b42318}
</style>
@endpush
@section('content')
<div class="dashboard360-hero p-4 mb-4">
  <div class="row align-items-center g-4">
    <div class="col-xl-5">
      <div class="text-muted small mb-1">{{ now()->translatedFormat('l, d F Y') }}</div>
      <h2 class="mb-2">{{ auth()->user()->first_name }}, ваш рабочий 360°</h2>
      <div class="text-muted">Все задачи, сроки, выполнение и комментарии — на одном экране.</div>
      <div class="d-flex gap-2 mt-3 flex-wrap">
        <a href="{{ route('tasks.page') }}" class="btn btn-primary"><i class="bi bi-check2-square me-1"></i>Все задачи</a>
        <a href="{{ route('calendar.page') }}" class="btn btn-outline-primary"><i class="bi bi-calendar-week me-1"></i>Календарь</a>
      </div>
    </div>
    <div class="col-xl-7">
      <div class="d-flex justify-content-xl-end justify-content-center gap-3 flex-wrap">
        <div class="kpi360" style="--p:{{ $stats['done_percent'] }};--ring:#198754"><div><div class="num">{{ $stats['done_percent'] }}%</div><div class="lbl">Выполнено</div></div></div>
        <div class="kpi360" style="--p:{{ min(100,$stats['my_open']*10) }};--ring:#0d6efd"><div><div class="num">{{ $stats['my_open'] }}</div><div class="lbl">Открыто</div></div></div>
        <div class="kpi360" style="--p:{{ min(100,$stats['my_overdue']*20) }};--ring:#dc3545"><div><div class="num">{{ $stats['my_overdue'] }}</div><div class="lbl">Просрочено</div></div></div>
        <div class="kpi360" style="--p:{{ min(100,$stats['my_review']*20) }};--ring:#ffc107"><div><div class="num">{{ $stats['my_review'] }}</div><div class="lbl">На проверке</div></div></div>
        <div class="kpi360" style="--p:{{ min(100,$stats['today']*20) }};--ring:#6f42c1"><div><div class="num">{{ $stats['today'] }}</div><div class="lbl">На сегодня</div></div></div>
      </div>
    </div>
  </div>
</div>

@if(isset($stats['team_open']))
<div class="alert alert-primary d-flex gap-4 flex-wrap align-items-center">
  <i class="bi bi-people fs-4"></i><span><b>{{ $stats['team_open'] }}</b> открытых задач у подчинённых</span><span><b>{{ $stats['team_overdue'] }}</b> просрочено</span><a href="{{ route('control.page') }}" class="ms-auto alert-link">Открыть контроль</a>
</div>
@endif

<div class="d-flex align-items-center gap-2 flex-wrap mb-3 dashboard-filter">
  <h4 class="mb-0 me-auto">Мои задачи</h4>
  <button class="btn btn-sm btn-outline-secondary active" data-filter="all">Все</button>
  <button class="btn btn-sm btn-outline-secondary" data-filter="today">Сегодня</button>
  <button class="btn btn-sm btn-outline-danger" data-filter="overdue">Просрочено</button>
  <button class="btn btn-sm btn-outline-warning" data-filter="review">На проверке</button>
  <button class="btn btn-sm btn-outline-success" data-filter="completed">Выполнено</button>
</div>

<div id="dashboardTasks" class="d-grid gap-3 mb-4">
@forelse($myTasks as $task)
@php
  $lastComment = $task->comments->first();
  $isOverdue = $task->is_overdue;
  $isToday = $task->due_at && $task->due_at->isToday();
  $done = $task->status === 'completed';
  $review = $task->status === 'review';
@endphp
<div class="task360 p-3 {{ $isOverdue?'overdue':'' }} {{ $review?'review':'' }} {{ $done?'completed':'' }}" id="taskRow{{ $task->id }}" data-state="{{ $task->status }}" data-overdue="{{ $isOverdue?1:0 }}" data-today="{{ $isToday?1:0 }}">
  <div class="row align-items-start g-3">
    <div class="col-auto pt-1">
      @if($done)
        <div class="status-icon status-done" title="Выполнено"><i class="bi bi-check-lg fs-5"></i></div>
      @elseif($review)
        <div class="status-icon status-review" title="На проверке"><i class="bi bi-hourglass-split fs-5"></i></div>
      @else
        <input class="form-check-input task360-check" type="checkbox" id="done{{ $task->id }}" onchange="complete360({{ $task->id }},this,{{ $task->created_by===auth()->id()?'true':'false' }})" title="Отметить как выполненную">
      @endif
    </div>
    <div class="col">
      <div class="d-flex gap-2 flex-wrap align-items-center">
        <a href="{{ route('tasks.page',['task'=>$task->id]) }}" class="fw-semibold fs-5 text-decoration-none text-body">{{ $task->title }}</a>
        @if($isOverdue)<span class="badge text-bg-danger">Просрочено</span>@endif
        @if($review)<span class="badge text-bg-warning">На проверке</span>@endif
        @if($done)<span class="badge text-bg-success">Выполнено</span>@endif
        @if(!$done && !$review && $task->status==='in_progress')<span class="badge text-bg-primary">В работе</span>@endif
      </div>
      <div class="d-flex gap-2 flex-wrap mt-2 text-muted small">
        <span><i class="bi bi-person-up me-1"></i>{{ $task->created_by===auth()->id()?'Личная задача':($task->creator?->full_name ?? '—') }}</span>
        @if($task->due_at)<span class="date-pill {{ $isOverdue?'text-danger':'' }}"><i class="bi bi-clock me-1"></i>Срок {{ $task->due_at->format('d.m.Y H:i') }}</span>@else<span class="date-pill">Без срока</span>@endif
        @if($done && $task->completed_at)<span class="date-pill text-success"><i class="bi bi-check-circle me-1"></i>Выполнено {{ $task->completed_at->format('d.m.Y H:i') }}</span>@endif
        <span class="date-pill">{{ $task->progress }}%</span>
      </div>
      @if($task->description)<div class="small mt-2">{{ \Illuminate\Support\Str::limit($task->description,180) }}</div>@endif

      <div class="quick-comment p-2 mt-3">
        <div class="small text-muted mb-1" id="lastComment{{ $task->id }}">
          @if($lastComment)<i class="bi bi-chat-left-text me-1"></i><b>{{ $lastComment->user?->full_name }}:</b> {{ $lastComment->body }} <span class="ms-1">{{ $lastComment->created_at->format('d.m H:i') }}</span>@else<i class="bi bi-chat-left-text me-1"></i>Комментариев пока нет@endif
        </div>
        @if(!$done)
        <div class="input-group input-group-sm">
          <input id="comment{{ $task->id }}" class="form-control" placeholder="Быстрый комментарий{{ $task->created_by!==auth()->id()?' / отчёт о выполнении':'' }}">
          <button class="btn btn-outline-primary" onclick="comment360({{ $task->id }})"><i class="bi bi-send"></i></button>
        </div>
        @endif
      </div>
    </div>
    <div class="col-auto">
      <a href="{{ route('tasks.page',['task'=>$task->id]) }}" class="btn btn-sm btn-light border" title="Открыть подробно"><i class="bi bi-box-arrow-up-right"></i></a>
    </div>
  </div>
</div>
@empty
<div class="card border-0 shadow-sm"><div class="card-body text-center py-5"><i class="bi bi-check2-circle fs-1 text-success"></i><h5 class="mt-2">Задач нет</h5><div class="text-muted">На данный момент всё выполнено.</div></div></div>
@endforelse
</div>

<div class="row g-3">
  <div class="col-lg-7"><div class="card border-0 shadow-sm h-100"><div class="card-header bg-white d-flex align-items-center"><b>Ближайшие 7 дней</b><a class="btn btn-sm btn-outline-primary ms-auto" href="{{ route('calendar.page') }}">Календарь</a></div><div class="list-group list-group-flush">
    @forelse($upcomingTasks as $task)<a href="{{ route('tasks.page',['task'=>$task->id]) }}" class="list-group-item list-group-item-action py-3"><div class="d-flex gap-3"><i class="bi bi-calendar-event text-primary fs-5"></i><div class="flex-grow-1"><div class="fw-semibold">{{ $task->title }}</div><div class="small text-secondary">{{ $task->due_at->format('d.m.Y H:i') }} · {{ $task->progress }}%</div></div></div></a>@empty<div class="p-4 text-center text-secondary">Ближайших задач нет</div>@endforelse
  </div></div></div>
  <div class="col-lg-5"><div class="card border-0 shadow-sm h-100"><div class="card-header bg-white"><b>Планы</b></div><div class="list-group list-group-flush">
    @forelse($upcomingPlans as $plan)<a href="{{ route('plans.page') }}" class="list-group-item list-group-item-action py-3"><div class="fw-semibold">{{ $plan->title }}</div><div class="small text-secondary">До {{ $plan->period_end->format('d.m.Y') }} · {{ $plan->progress }}%</div></a>@empty<div class="p-4 text-center text-secondary">Планов с близким сроком нет</div>@endforelse
  </div></div></div>
</div>
@endsection
@push('scripts')
<script>
$('.dashboard-filter [data-filter]').on('click',function(){
  $('.dashboard-filter [data-filter]').removeClass('active');$(this).addClass('active');const f=$(this).data('filter');
  $('.task360').each(function(){let show=f==='all'||(f==='today'&&$(this).data('today')==1)||(f==='overdue'&&$(this).data('overdue')==1)||(f==='review'&&$(this).data('state')==='review')||(f==='completed'&&$(this).data('state')==='completed');$(this).toggle(show)})
});
function complete360(id,checkbox,selfCreated){
  let comment=$(`#comment${id}`).val()?.trim()||'';
  if(!selfCreated&&!comment){alert('Перед отправкой руководителю напишите краткий комментарий о выполнении.');checkbox.checked=false;$(`#comment${id}`).focus();return}
  checkbox.disabled=true;
  $.post(`{{ url('/ajax/tasks') }}/${id}/dashboard-complete`,{comment}).done(r=>{
    const row=$(`#taskRow${id}`);row.removeClass('overdue').attr('data-overdue',0);
    if(r.mode==='completed'){row.addClass('completed').attr('data-state','completed');checkbox.replaceWith('<div class="status-icon status-done"><i class="bi bi-check-lg fs-5"></i></div>')}
    else{row.addClass('review').attr('data-state','review');checkbox.replaceWith('<div class="status-icon status-review"><i class="bi bi-hourglass-split fs-5"></i></div>')}
    setTimeout(()=>window.location.reload(),450);
  }).fail(x=>{checkbox.checked=false;checkbox.disabled=false;alert(x.responseJSON?.message||'Не удалось изменить задачу')})
}
function comment360(id){
  const input=$(`#comment${id}`),body=input.val().trim();if(!body)return;
  $.post(`{{ url('/ajax/tasks') }}/${id}/comments`,{body}).done(r=>{input.val('');let u=r.comment?.user;$('#lastComment'+id).html(`<i class="bi bi-chat-left-text me-1"></i><b>${$('<div>').text((u?.last_name||'')+' '+(u?.first_name||'')).html()}:</b> ${$('<div>').text(body).html()} <span class="ms-1">сейчас</span>`)}).fail(x=>alert(x.responseJSON?.message||'Не удалось добавить комментарий'))
}
</script>
@endpush

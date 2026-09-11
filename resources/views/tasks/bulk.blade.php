@extends('layouts.app')
@section('title','Массовая постановка задач — CRM ЗЦРБ')
@section('header','Массовая постановка задач')
@section('content')
<div class="row justify-content-center"><div class="col-xxl-10"><div class="alert alert-info border-0"><i class="bi bi-people-fill me-2"></i>Одна форма создаёт отдельную задачу каждому выбранному исполнителю. У каждой задачи будет собственный статус, срок и история.</div><div class="card border-0 shadow-sm"><form id="bulkForm" class="card-body p-lg-4"><div class="row g-3">
<div class="col-md-4"><label class="form-label fw-semibold">Кому поставить</label><select name="target_type" id="targetType" class="form-select"><option value="users">Нескольким сотрудникам</option><option value="department">Всему подразделению</option><option value="managers">Всем руководителям моей ветки</option><option value="position">По должности</option></select></div>

<div class="col-md-8 target target-users">
  <label class="form-label fw-semibold">Сотрудники</label>
  <div class="row g-2 mb-2">
    <div class="col-md-7"><div class="input-group"><span class="input-group-text bg-white"><i class="bi bi-search"></i></span><input type="text" id="bulkUserSearch" class="form-control" placeholder="ФИО, должность или подразделение"></div></div>
    <div class="col-md-5"><select id="bulkDepartmentFilter" class="form-select"><option value="">Все подразделения</option>@foreach($departments as $d)<option value="{{ $d->id }}">{{ $d->name }}</option>@endforeach</select></div>
  </div>
  <div class="d-flex gap-2 flex-wrap align-items-center mb-2">
    <button type="button" class="btn btn-sm btn-outline-primary" id="selectVisibleUsers"><i class="bi bi-check2-square me-1"></i>Выбрать всех найденных</button>
    <button type="button" class="btn btn-sm btn-outline-secondary" id="clearUsers"><i class="bi bi-x-lg me-1"></i>Снять выбор</button>
    <span class="badge text-bg-light border ms-md-auto" id="selectedUsersCount">Выбрано: 0</span>
    <span class="small text-muted" id="visibleUsersCount"></span>
  </div>
  <div id="bulkUsersList" class="border rounded overflow-auto" style="max-height:420px">
    @foreach($users as $u)
      <label class="bulk-user-row d-flex align-items-start gap-3 p-2 border-bottom mb-0" data-user-id="{{ $u->id }}" data-department-id="{{ $u->department_id }}" data-search="{{ mb_strtolower($u->full_name.' '.$u->position.' '.($u->department?->name ?? '')) }}" style="cursor:pointer">
        <input class="form-check-input mt-1 bulk-user-check" type="checkbox" name="user_ids[]" value="{{ $u->id }}">
        <span class="flex-grow-1"><span class="fw-semibold d-block">{{ $u->full_name }}</span><span class="small text-muted">{{ $u->department?->name ?: 'Без подразделения' }}@if($u->position) · {{ $u->position }}@endif</span></span>
      </label>
    @endforeach
  </div>
  <div id="bulkUsersEmpty" class="text-center text-muted py-3 d-none">Сотрудники не найдены.</div>
  <div class="form-text">Можно выбрать сотрудников из разных подразделений. Каждому будет создана отдельная задача.</div>
</div>

<div class="col-md-8 target target-department d-none"><label class="form-label fw-semibold">Подразделение</label><select name="department_id" class="form-select"><option value="">Выберите...</option>@foreach($departments as $d)<option value="{{ $d->id }}">{{ $d->name }}</option>@endforeach</select></div>
<div class="col-md-8 target target-position d-none"><label class="form-label fw-semibold">Должность</label><select name="position_id" class="form-select"><option value="">Выберите...</option>@foreach($positions as $p)<option value="{{ $p->id }}">{{ $p->name }}</option>@endforeach</select></div>

<div class="col-12"><hr></div><div class="col-md-8"><label class="form-label fw-semibold">Название задачи</label><input name="title" class="form-control" required placeholder="Например: Подготовить отчёт по итогам августа"></div><div class="col-md-4"><label class="form-label">Приоритет</label><select name="priority" class="form-select"><option value="normal">Обычный</option><option value="high">Высокий</option><option value="critical">Критический</option><option value="low">Низкий</option></select></div><div class="col-12"><label class="form-label">Описание</label><textarea name="description" rows="5" class="form-control" placeholder="Что именно нужно сделать, какой результат ожидается"></textarea></div><div class="col-md-5"><label class="form-label">Срок</label><input name="due_at" type="datetime-local" class="form-control"></div><div class="col-md-7"><label class="form-label">Метки</label><select name="tag_ids[]" class="form-select" multiple>@foreach($tags as $tag)<option value="{{ $tag->id }}">{{ $tag->name }}</option>@endforeach</select></div><div class="col-12"><div id="bulkError" class="alert alert-danger d-none"></div><div id="bulkSuccess" class="alert alert-success d-none"></div></div></div><div class="d-flex gap-2 mt-4"><button class="btn btn-primary"><i class="bi bi-send me-1"></i>Поставить задачи</button><a href="{{ route('tasks.page') }}" class="btn btn-light">К задачам</a></div></form></div></div></div>
@endsection

@push('scripts')
<script>
function showTarget(){const v=$('#targetType').val();$('.target').addClass('d-none');if(v==='users')$('.target-users').removeClass('d-none');if(v==='department')$('.target-department').removeClass('d-none');if(v==='position')$('.target-position').removeClass('d-none')}

function normalizeBulkSearch(value){return String(value||'').toLowerCase().replace(/ё/g,'е').trim()}

function filterBulkUsers(){
  const q=normalizeBulkSearch($('#bulkUserSearch').val());
  const dep=String($('#bulkDepartmentFilter').val()||'');
  let visible=0;
  $('.bulk-user-row').each(function(){
    const row=$(this);
    const text=normalizeBulkSearch(row.data('search'));
    const depId=String(row.data('department-id')||'');
    const matchText=!q || text.includes(q);
    const matchDep=!dep || depId===dep;
    const show=matchText && matchDep;
    row.toggle(show);
    if(show) visible++;
  });
  $('#bulkUsersEmpty').toggleClass('d-none',visible>0);
  $('#visibleUsersCount').text('Найдено: '+visible);
}

function updateSelectedUsersCount(){
  const count=$('.bulk-user-check:checked').length;
  $('#selectedUsersCount').text('Выбрано: '+count);
}

$('#targetType').on('change',showTarget);
$('#bulkUserSearch').on('input',filterBulkUsers);
$('#bulkDepartmentFilter').on('change',filterBulkUsers);
$('.bulk-user-check').on('change',updateSelectedUsersCount);

$('#selectVisibleUsers').on('click',function(){
  $('.bulk-user-row:visible .bulk-user-check').prop('checked',true);
  updateSelectedUsersCount();
});

$('#clearUsers').on('click',function(){
  $('.bulk-user-check').prop('checked',false);
  updateSelectedUsersCount();
});

$('#bulkForm').on('submit',function(e){
  e.preventDefault();
  $('#bulkError,#bulkSuccess').addClass('d-none');
  if($('#targetType').val()==='users' && $('.bulk-user-check:checked').length===0){
    $('#bulkError').removeClass('d-none').text('Выберите хотя бы одного сотрудника.');
    return;
  }
  $.ajax({url:'{{ route('tasks.bulk.store') }}',method:'POST',data:$(this).serialize()})
    .done(r=>{$('#bulkSuccess').removeClass('d-none').html(`<b>Готово.</b> Создано задач: ${r.created}. <a href="{{ route('tasks.page') }}">Открыть список задач</a>`);$('.bulk-user-check').prop('checked',false);updateSelectedUsersCount();})
    .fail(x=>{$('#bulkError').removeClass('d-none').text(x.responseJSON?.message||'Ошибка массовой постановки')});
});

showTarget();
filterBulkUsers();
updateSelectedUsersCount();
</script>
@endpush

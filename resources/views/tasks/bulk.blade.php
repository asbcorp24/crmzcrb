@extends('layouts.app')
@section('title','Массовая постановка задач — CRM ЗЦРБ')
@section('header','Массовая постановка задач')
@section('content')
<div class="row justify-content-center"><div class="col-xxl-11">
<div class="alert alert-info border-0"><i class="bi bi-people-fill me-2"></i>Одна форма создаёт отдельную задачу каждому выбранному исполнителю. Общие реквизиты копируются во все созданные задачи, а дальше каждая задача ведётся отдельно.</div>
<div class="card border-0 shadow-sm"><form id="bulkForm" class="card-body p-lg-4"><div class="row g-3">

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

<div class="col-12"><hr class="my-1"></div>
<div class="col-12"><div class="fw-semibold fs-5">Реквизиты задач</div><div class="small text-muted">Эти значения будут одинаковыми у всех создаваемых задач.</div></div>

<div class="col-lg-4 col-md-6"><label class="form-label">Проект</label><div class="input-group"><select name="project_id" id="bulkProject" class="form-select"><option value="">Не выбран</option>@foreach($projects as $x)<option value="{{ $x->id }}">{{ $x->name }}</option>@endforeach</select><button type="button" class="btn btn-outline-secondary ref-add" data-type="project" data-target="bulkProject" data-label="Проект" title="Добавить проект"><i class="bi bi-plus-lg"></i></button></div></div>
<div class="col-lg-4 col-md-6"><label class="form-label">Основание</label><div class="input-group"><select name="basis_id" id="bulkBasis" class="form-select"><option value="">Не выбрано</option>@foreach($bases as $x)<option value="{{ $x->id }}">{{ $x->name }}</option>@endforeach</select><button type="button" class="btn btn-outline-secondary ref-add" data-type="basis" data-target="bulkBasis" data-label="Основание" title="Добавить основание"><i class="bi bi-plus-lg"></i></button></div></div>
<div class="col-lg-4 col-md-6"><label class="form-label">Ответственный отдел</label><select name="responsible_department_id" class="form-select"><option value="">Не выбран</option>@foreach($departments as $d)<option value="{{ $d->id }}">{{ $d->name }}</option>@endforeach</select></div>

<div class="col-lg-4 col-md-6"><label class="form-label">Старт <span class="text-muted fw-normal">(необязательно)</span></label><input name="start_at" type="datetime-local" class="form-control"></div>
<div class="col-lg-4 col-md-6"><label class="form-label">Управленческий статус</label><div class="input-group"><select name="business_status_id" id="bulkBusinessStatus" class="form-select"><option value="">Пусто</option>@foreach($businessStatuses as $x)<option value="{{ $x->id }}">{{ $x->name }}</option>@endforeach</select><button type="button" class="btn btn-outline-secondary ref-add" data-type="task_status" data-target="bulkBusinessStatus" data-label="Статус" title="Добавить статус"><i class="bi bi-plus-lg"></i></button></div></div>
<div class="col-lg-4 col-md-6"><label class="form-label">Тип заказчика</label><select name="customer_type" id="bulkCustomerType" class="form-select"><option value="">Не выбран</option><option value="organization">Предприятие</option><option value="department">Отдел</option></select></div>
<div class="col-lg-6 col-md-6"><label class="form-label">Заказчик</label><div class="input-group"><select name="customer_id" id="bulkCustomerId" class="form-select" disabled><option value="">Сначала выберите тип</option></select><button type="button" id="bulkCustomerAdd" class="btn btn-outline-secondary d-none" title="Добавить предприятие"><i class="bi bi-plus-lg"></i></button></div></div>

<div class="col-lg-6"><label class="form-label fw-semibold">Название задачи <span class="text-muted fw-normal">(необязательно)</span></label><input name="title" class="form-control" placeholder="Если пусто — возьмётся проект или основание"></div>
<div class="col-md-4"><label class="form-label">Приоритет</label><select name="priority" class="form-select"><option value="normal">Обычный</option><option value="high">Высокий</option><option value="critical">Критический</option><option value="low">Низкий</option></select></div>
<div class="col-md-4"><label class="form-label">Срок</label><input name="due_at" type="datetime-local" class="form-control"></div>
<div class="col-md-4"><label class="form-label">Метки</label><select name="tag_ids[]" class="form-select" multiple>@foreach($tags as $tag)<option value="{{ $tag->id }}">{{ $tag->name }}</option>@endforeach</select></div>
<div class="col-12"><label class="form-label">Описание</label><textarea name="description" rows="4" class="form-control" placeholder="Что именно нужно сделать, какой результат ожидается"></textarea></div>

<div class="col-12"><div id="bulkError" class="alert alert-danger d-none"></div><div id="bulkSuccess" class="alert alert-success d-none"></div></div>
</div>
<div class="d-flex gap-2 mt-4"><button class="btn btn-primary"><i class="bi bi-send me-1"></i>Поставить задачи</button><a href="{{ route('tasks.page') }}" class="btn btn-light">К задачам</a></div>
</form></div></div></div>

<div class="modal fade" id="bulkReferenceModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
<div class="modal-header"><h5 class="modal-title" id="bulkReferenceTitle">Добавить запись</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
<div class="modal-body">
  <div id="bulkReferenceError" class="alert alert-danger d-none"></div>
  <div class="mb-3"><label class="form-label">Наименование</label><input id="bulkReferenceName" class="form-control" maxlength="255"></div>
  <div class="mb-3"><label class="form-label">Код <span class="text-muted">(необязательно)</span></label><input id="bulkReferenceCode" class="form-control" maxlength="80"></div>
  <div id="bulkStatusFields" class="d-none">
    <div class="mb-3"><label class="form-label">Системный статус</label><select id="bulkReferenceSystemKey" class="form-select"><option value="new">Новая</option><option value="in_progress">В работе</option><option value="review">На проверке</option><option value="completed">Выполнена</option><option value="cancelled">Отменена</option></select></div>
    <div class="mb-3"><label class="form-label">Цвет</label><input id="bulkReferenceColor" type="color" class="form-control form-control-color" value="#0d6efd"></div>
  </div>
  <div><label class="form-label">Примечание <span class="text-muted">(необязательно)</span></label><textarea id="bulkReferenceNotes" class="form-control" rows="3"></textarea></div>
</div>
<div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Отмена</button><button type="button" id="saveBulkReference" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Добавить</button></div>
</div></div></div>
@endsection

@push('scripts')
<script>
const bulkOrganizations=@json($organizations->map(fn($x)=>['id'=>$x->id,'name'=>$x->name])->values());
const bulkDepartments=@json($departments->map(fn($x)=>['id'=>$x->id,'name'=>$x->name])->values());
let bulkRefType=null,bulkRefTarget=null,bulkRefLabel='';

function showTarget(){const v=$('#targetType').val();$('.target').addClass('d-none');if(v==='users')$('.target-users').removeClass('d-none');if(v==='department')$('.target-department').removeClass('d-none');if(v==='position')$('.target-position').removeClass('d-none')}
function normalizeBulkSearch(value){return String(value||'').toLowerCase().replace(/ё/g,'е').trim()}
function filterBulkUsers(){const q=normalizeBulkSearch($('#bulkUserSearch').val());const dep=String($('#bulkDepartmentFilter').val()||'');let visible=0;$('.bulk-user-row').each(function(){const row=$(this),text=normalizeBulkSearch(row.data('search')),depId=String(row.data('department-id')||'');const show=(!q||text.includes(q))&&(!dep||depId===dep);row.toggle(show);if(show)visible++});$('#bulkUsersEmpty').toggleClass('d-none',visible>0);$('#visibleUsersCount').text('Найдено: '+visible)}
function updateSelectedUsersCount(){$('#selectedUsersCount').text('Выбрано: '+$('.bulk-user-check:checked').length)}
function fillBulkCustomer(){const type=$('#bulkCustomerType').val();const rows=type==='organization'?bulkOrganizations:type==='department'?bulkDepartments:[];$('#bulkCustomerId').html('<option value="">'+(type?'Выберите...':'Сначала выберите тип')+'</option>'+rows.map(x=>'<option value="'+x.id+'">'+$('<div>').text(x.name).html()+'</option>').join('')).prop('disabled',!type);$('#bulkCustomerAdd').toggleClass('d-none',type!=='organization')}

function openBulkReference(type,target,label){bulkRefType=type;bulkRefTarget=target;bulkRefLabel=label;$('#bulkReferenceTitle').text('Добавить: '+label);$('#bulkReferenceName,#bulkReferenceCode,#bulkReferenceNotes').val('');$('#bulkReferenceError').addClass('d-none').text('');$('#bulkStatusFields').toggleClass('d-none',type!=='task_status');bootstrap.Modal.getOrCreateInstance(document.getElementById('bulkReferenceModal')).show();setTimeout(()=>$('#bulkReferenceName').trigger('focus'),150)}
function addOptionAndSelect(target,item){const select=$('#'+target);if(!select.length)return;select.append(new Option(item.name,item.id,true,true)).val(String(item.id)).trigger('change')}
function saveBulkReference(){const name=$('#bulkReferenceName').val().trim();if(!name){$('#bulkReferenceError').removeClass('d-none').text('Введите наименование.');return}const data={type:bulkRefType,name,code:$('#bulkReferenceCode').val(),notes:$('#bulkReferenceNotes').val(),sort_order:0,is_active:1};if(bulkRefType==='task_status'){data.system_key=$('#bulkReferenceSystemKey').val();data.color=$('#bulkReferenceColor').val()}$.ajax({url:'{{ route('directories.store') }}',method:'POST',data}).done(r=>{if(bulkRefType==='organization'){bulkOrganizations.push({id:r.item.id,name:r.item.name});fillBulkCustomer();$('#bulkCustomerId').val(String(r.item.id))}else addOptionAndSelect(bulkRefTarget,r.item);bootstrap.Modal.getInstance(document.getElementById('bulkReferenceModal')).hide()}).fail(x=>$('#bulkReferenceError').removeClass('d-none').text(x.responseJSON?.message||Object.values(x.responseJSON?.errors||{}).flat()[0]||'Ошибка сохранения'))}

$('#targetType').on('change',showTarget);
$('#bulkUserSearch').on('input',filterBulkUsers);
$('#bulkDepartmentFilter').on('change',filterBulkUsers);
$('.bulk-user-check').on('change',updateSelectedUsersCount);
$('#selectVisibleUsers').on('click',function(){$('.bulk-user-row:visible .bulk-user-check').prop('checked',true);updateSelectedUsersCount()});
$('#clearUsers').on('click',function(){$('.bulk-user-check').prop('checked',false);updateSelectedUsersCount()});
$('#bulkCustomerType').on('change',fillBulkCustomer);
$('.ref-add').on('click',function(){openBulkReference($(this).data('type'),$(this).data('target'),$(this).data('label'))});
$('#bulkCustomerAdd').on('click',()=>openBulkReference('organization','bulkCustomerId','Предприятие'));
$('#saveBulkReference').on('click',saveBulkReference);

$('#bulkForm').on('submit',function(e){
  e.preventDefault();$('#bulkError,#bulkSuccess').addClass('d-none');
  if($('#targetType').val()==='users'&&$('.bulk-user-check:checked').length===0){$('#bulkError').removeClass('d-none').text('Выберите хотя бы одного сотрудника.');return}
  $.ajax({url:'{{ route('tasks.bulk.store') }}',method:'POST',data:$(this).serialize()})
    .done(r=>{$('#bulkSuccess').removeClass('d-none').html('<b>Готово.</b> Создано задач: '+r.created+'. <a href="{{ route('tasks.page') }}">Открыть список задач</a>');$('.bulk-user-check').prop('checked',false);updateSelectedUsersCount()})
    .fail(x=>$('#bulkError').removeClass('d-none').text(x.responseJSON?.message||Object.values(x.responseJSON?.errors||{}).flat()[0]||'Ошибка массовой постановки'));
});
showTarget();filterBulkUsers();updateSelectedUsersCount();fillBulkCustomer();
</script>
@endpush

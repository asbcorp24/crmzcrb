@extends('layouts.app')
@section('title','Сотрудники — CRM ЗЦРБ')
@section('header','Сотрудники')
@section('content')
<div class="d-flex gap-2 mb-3 flex-wrap">
  <input id="q" class="form-control" style="max-width:280px" placeholder="Поиск по ФИО, должности, email">
  <select id="departmentFilter" class="form-select" style="max-width:260px"><option value="">Все подразделения</option>@foreach($departments as $d)<option value="{{ $d->id }}">{{ $d->name }}</option>@endforeach</select>
  <select id="roleFilter" class="form-select" style="max-width:200px"><option value="">Все роли</option><option value="admin">Администратор</option><option value="manager">Руководитель</option><option value="employee">Сотрудник</option></select>
  @if(auth()->user()->isManager())<button class="btn btn-primary ms-auto" data-bs-toggle="modal" data-bs-target="#employeeModal" onclick="newEmployee()"><i class="bi bi-person-plus me-1"></i>Добавить сотрудника</button>@endif
</div>
<div class="card border-0 shadow-sm"><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>ФИО</th><th>Должность</th><th>Подразделение</th><th>Руководитель</th><th>Роль</th><th>Статус</th><th></th></tr></thead><tbody id="rows"><tr><td colspan="7" class="text-center py-4">Загрузка...</td></tr></tbody></table></div></div>

@if(auth()->user()->isManager())
<div class="modal fade" id="employeeModal" tabindex="-1"><div class="modal-dialog modal-lg"><form class="modal-content" id="employeeForm"><div class="modal-header"><h5 class="modal-title">Сотрудник</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><input type="hidden" id="employee_id"><div class="row g-3">
<div class="col-md-4"><label class="form-label">Фамилия</label><input name="last_name" class="form-control" required></div><div class="col-md-4"><label class="form-label">Имя</label><input name="first_name" class="form-control" required></div><div class="col-md-4"><label class="form-label">Отчество</label><input name="middle_name" class="form-control"></div>
<div class="col-md-6"><label class="form-label">Должность</label><input name="position" class="form-control" required></div><div class="col-md-6"><label class="form-label">Подразделение</label><select name="department_id" class="form-select"><option value="">—</option>@foreach($departments as $d)<option value="{{ $d->id }}">{{ $d->name }}</option>@endforeach</select></div>
<div class="col-md-6"><label class="form-label">Руководитель</label><select name="manager_id" class="form-select"><option value="">—</option>@foreach($managers as $m)<option value="{{ $m->id }}">{{ $m->full_name }}</option>@endforeach</select></div><div class="col-md-3"><label class="form-label">Роль</label><select name="role" class="form-select"><option value="employee">Сотрудник</option><option value="manager">Руководитель</option><option value="admin">Администратор</option></select></div><div class="col-md-3"><label class="form-label">Принят</label><input type="date" name="employment_date" class="form-control"></div>
<div class="col-md-6"><label class="form-label">Email / логин</label><input type="email" name="email" class="form-control" required></div><div class="col-md-3"><label class="form-label">Телефон</label><input name="phone" class="form-control"></div><div class="col-md-3"><label class="form-label">Пароль</label><input type="password" name="password" class="form-control" placeholder="не менять"></div>
<div class="col-12"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" id="is_active" checked><label class="form-check-label" for="is_active">Сотрудник активен</label></div></div>
</div><div id="formError" class="alert alert-danger mt-3 d-none"></div></div><div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Отмена</button><button class="btn btn-primary">Сохранить</button></div></form></div></div>
@endif
@endsection
@push('scripts')
<script>
let employeeCache={};
function esc(v){return $('<div>').text(v??'').html()}
function roleName(r){return {admin:'Администратор',manager:'Руководитель',employee:'Сотрудник'}[r]||r}
function loadEmployees(){ $.get('{{ route('employees.index') }}',{q:$('#q').val(),department_id:$('#departmentFilter').val(),role:$('#roleFilter').val()},function(r){ employeeCache={}; let h=''; r.data.forEach(u=>{employeeCache[u.id]=u;h+=`<tr><td><strong>${esc(u.last_name+' '+u.first_name+' '+(u.middle_name||''))}</strong><div class="small text-muted">${esc(u.email)}</div></td><td>${esc(u.position)}</td><td>${esc(u.department?.name||'—')}</td><td>${esc(u.manager?u.manager.last_name+' '+u.manager.first_name:'—')}</td><td>${roleName(u.role)}</td><td>${u.is_active?'<span class="badge text-bg-success">Активен</span>':'<span class="badge text-bg-secondary">Отключён</span>'}</td><td class="text-end">@if(auth()->user()->isManager())<button class="btn btn-sm btn-outline-primary" onclick="editEmployee(${u.id})"><i class="bi bi-pencil"></i></button>@endif</td></tr>`}); $('#rows').html(h||'<tr><td colspan="7" class="text-center py-4 text-muted">Сотрудники не найдены</td></tr>') }) }
$('#q').on('input',()=>{clearTimeout(window.et);window.et=setTimeout(loadEmployees,300)}); $('#departmentFilter,#roleFilter').on('change',loadEmployees);
function newEmployee(){ $('#employeeForm')[0].reset();$('#employee_id').val('');$('#is_active').prop('checked',true);$('#formError').addClass('d-none') }
function editEmployee(id){let u=employeeCache[id];newEmployee();$('#employee_id').val(id);for(let k of ['last_name','first_name','middle_name','position','department_id','manager_id','role','employment_date','email','phone']) $(`[name=${k}]`).val(u[k]??'');$('#is_active').prop('checked',!!u.is_active);bootstrap.Modal.getOrCreateInstance(document.getElementById('employeeModal')).show()}
$('#employeeForm').on('submit',function(e){e.preventDefault();let id=$('#employee_id').val(),data=Object.fromEntries(new FormData(this).entries());data.is_active=$('#is_active').is(':checked')?1:0;if(!data.password)delete data.password;$.ajax({url:id?'{{ url('/ajax/employees') }}/'+id:'{{ route('employees.store') }}',method:id?'PATCH':'POST',data}).done(()=>{bootstrap.Modal.getInstance(document.getElementById('employeeModal')).hide();loadEmployees()}).fail(x=>{$('#formError').removeClass('d-none').text(x.responseJSON?.message||'Ошибка сохранения')})});
loadEmployees();
</script>
@endpush

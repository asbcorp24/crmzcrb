@extends('layouts.app')
@section('title','Сотрудники')
@section('content')
<div class="d-flex flex-wrap justify-content-between gap-2 align-items-end mb-4"><div><div class="circuit-title">IDENTITY MATRIX</div><h1 class="h3 mb-0">Сотрудники и пароли</h1></div>
<div class="d-flex gap-2 flex-wrap"><a class="btn btn-outline-primary" href="{{ route('admin.users.credentials') }}"><i class="bi bi-download me-1"></i>Выгрузить логины/пароли</a>
<form class="d-flex gap-2"><input name="q" class="form-control" value="{{ request('q') }}" placeholder="ФИО / логин"><select name="workshop_id" class="form-select"><option value="">Все цеха</option>@foreach($workshops as $w)<option value="{{ $w->id }}" @selected(request('workshop_id')==$w->id)>{{ $w->name }}</option>@endforeach</select><button class="btn btn-outline-primary">Найти</button></form></div></div>
<div class="card mb-4"><div class="card-header"><b>Массовое создание</b></div><div class="card-body">
<form method="post" action="{{ route('admin.users.bulk') }}" class="row g-3">@csrf
<div class="col-md-4"><label class="form-label">Цех</label><select name="workshop_id" class="form-select" required>@foreach($workshops as $w)<option value="{{ $w->id }}">{{ $w->name }}</option>@endforeach</select></div>
<div class="col-md-8"><label class="form-label">Сотрудники</label><textarea name="people" class="form-control" rows="5" required placeholder="Иванов Иван Иванович&#10;Петров Петр Петрович|petrov|QWERTY123&#10;Сидоров Сидор Сидорович"></textarea><div class="form-text">Одна строка — один сотрудник. Формат: ФИО или ФИО|логин|пароль. Если логин/пароль не указаны — генерируются.</div></div>
<div class="col-12"><button class="btn btn-outline-primary">Создать список сотрудников</button></div>
</form></div></div>

<div class="card mb-4"><div class="card-header"><b>Добавить сотрудника</b></div><div class="card-body"><form method="post" action="{{ route('admin.users.store') }}" class="row g-3">@csrf
<div class="col-md-3"><label class="form-label">Цех</label><select name="workshop_id" class="form-select" required>@foreach($workshops as $w)<option value="{{ $w->id }}">{{ $w->name }}</option>@endforeach</select></div>
<div class="col-md-3"><label class="form-label">ФИО</label><input name="full_name" class="form-control" required></div>
<div class="col-md-2"><label class="form-label">Должность</label><input name="position" class="form-control"></div>
<div class="col-md-2"><label class="form-label">Логин</label><input name="login" class="form-control" required></div>
<div class="col-md-2"><label class="form-label">Пароль</label><input name="password" class="form-control" placeholder="пусто = сгенерировать"></div>
<div class="col-12"><button class="btn btn-primary">Создать сотрудника</button></div></form></div></div>
<div class="card"><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>ФИО</th><th>Цех</th><th>Логин</th><th>Выданный пароль</th><th>Должность</th><th>Активен</th><th></th></tr></thead><tbody>
@foreach($users as $u)<tr>
<td><form id="user-{{ $u->id }}" method="post" action="{{ route('admin.users.update',$u) }}">@csrf @method('PATCH')</form><input form="user-{{ $u->id }}" name="full_name" class="form-control" value="{{ $u->full_name }}"></td>
<td><select form="user-{{ $u->id }}" name="workshop_id" class="form-select">@foreach($workshops as $w)<option value="{{ $w->id }}" @selected($u->workshop_id==$w->id)>{{ $w->name }}</option>@endforeach</select></td>
<td><input form="user-{{ $u->id }}" name="login" class="form-control" value="{{ $u->login }}"></td>
<td><code class="text-info">{{ $passwords[$u->id] ?? 'недоступен' }}</code></td>
<td><input form="user-{{ $u->id }}" name="position" class="form-control" value="{{ $u->position }}"></td>
<td><input form="user-{{ $u->id }}" class="form-check-input" name="is_active" value="1" type="checkbox" @checked($u->is_active)></td>
<td class="text-end"><button form="user-{{ $u->id }}" class="btn btn-sm btn-outline-primary">Сохранить</button>
<form method="post" action="{{ route('admin.users.password',$u) }}" class="d-inline">@csrf<button class="btn btn-sm btn-outline-warning" title="Сгенерировать новый пароль"><i class="bi bi-key"></i></button></form></td>
</tr>@endforeach</tbody></table></div></div>
<div class="mt-3">{{ $users->links() }}</div>
@endsection

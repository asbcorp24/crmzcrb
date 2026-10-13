@extends('layouts.app')
@section('title','Цеха')
@section('content')
<div class="d-flex justify-content-between align-items-end mb-4"><div><div class="circuit-title">STRUCTURE</div><h1 class="h3 mb-0">Цеха</h1></div></div>
<div class="row g-3">
<div class="col-lg-4"><div class="card"><div class="card-header"><b>Новый цех</b></div><div class="card-body"><form method="post" action="{{ route('admin.workshops.store') }}">@csrf
<div class="mb-3"><label class="form-label">Название</label><input name="name" class="form-control" required></div>
<div class="mb-3"><label class="form-label">Код</label><input name="code" class="form-control" placeholder="ЦЕХ-01"></div>
<button class="btn btn-primary w-100">Создать</button></form></div></div></div>
<div class="col-lg-8"><div class="card"><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Название</th><th>Код</th><th>Сотрудников</th><th></th></tr></thead><tbody>
@foreach($workshops as $w)<tr><form method="post" action="{{ route('admin.workshops.update',$w) }}">@csrf @method('PATCH')
<td><input name="name" class="form-control" value="{{ $w->name }}"></td><td><input name="code" class="form-control" value="{{ $w->code }}"></td>
<td>{{ $w->users_count }}</td><td class="text-end"><div class="form-check form-switch d-inline-flex me-2"><input class="form-check-input" name="is_active" value="1" type="checkbox" @checked($w->is_active)></div><button class="btn btn-sm btn-outline-primary">Сохранить</button></td>
</form></tr>@endforeach
</tbody></table></div></div></div>
</div>
@endsection

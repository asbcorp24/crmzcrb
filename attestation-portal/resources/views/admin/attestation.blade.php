@extends('layouts.app')
@section('title','Кампании аттестации')
@section('content')
<div class="d-flex justify-content-between align-items-end mb-4"><div><div class="circuit-title">ATTESTATION CONTROL</div><h1 class="h3 mb-0">Кампании аттестации</h1></div><a class="btn btn-outline-primary" href="{{ route('admin.analytics.attestation') }}">Аналитика</a></div>
<div class="row g-3">
<div class="col-lg-5"><div class="card"><div class="card-header"><b>Новая кампания</b></div><div class="card-body"><form method="post" action="{{ route('admin.attestation.store') }}">@csrf
<div class="mb-3"><label class="form-label">Название</label><input name="title" class="form-control" required></div>
<div class="mb-3"><label class="form-label">Описание</label><textarea name="description" class="form-control" rows="3"></textarea></div>
<div class="row g-2 mb-3"><div class="col"><label class="form-label">Начало</label><input name="starts_at" type="date" class="form-control"></div><div class="col"><label class="form-label">Окончание</label><input name="ends_at" type="date" class="form-control"></div></div>
<div class="mb-3"><label class="form-label">Цеха</label><select name="workshop_ids[]" class="form-select" multiple size="6" required>@foreach($workshops as $w)<option value="{{ $w->id }}">{{ $w->name }}</option>@endforeach</select></div>
<div class="mb-3"><label class="form-label">Члены комиссии</label><select name="commission_member_ids[]" class="form-select" multiple size="8">@foreach($users as $u)<option value="{{ $u->id }}">{{ $u->full_name }}</option>@endforeach</select></div>
<button class="btn btn-primary w-100">Создать кампанию</button></form></div></div></div>
<div class="col-lg-7"><div class="card"><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Кампания</th><th>Период</th><th>Активна</th></tr></thead><tbody>@foreach($campaigns as $c)<tr><td><b>{{ $c->title }}</b><div class="small text-muted">{{ $c->description }}</div></td><td>{{ $c->starts_at ?: '—' }}<br>{{ $c->ends_at ?: '—' }}</td><td><span class="badge {{ $c->is_active?'badge-tech':'text-bg-secondary' }}">{{ $c->is_active?'Да':'Нет' }}</span></td></tr>@endforeach</tbody></table></div></div></div>
</div>
@endsection

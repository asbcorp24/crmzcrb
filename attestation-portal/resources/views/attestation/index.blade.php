@extends('layouts.app')
@section('title','Аттестация')
@section('content')
<div class="d-flex flex-wrap justify-content-between gap-2 align-items-end mb-4">
  <div><div class="circuit-title">ASSESSMENT MATRIX</div><h1 class="h3 mb-0">Аттестация</h1></div>
  <form><select name="campaign" class="form-select" onchange="this.form.submit()">@foreach($campaigns as $c)<option value="{{ $c->id }}" @selected($selected && $selected->id==$c->id)>{{ $c->title }}</option>@endforeach</select></form>
</div>

@if(!$selected)
<div class="alert alert-secondary">Кампаний аттестации пока нет.</div>
@else
<div class="card mb-4"><div class="card-body"><h5>{{ $selected->title }}</h5><div class="text-muted">{{ $selected->description }}</div><div class="small mt-2">{{ $selected->starts_at ?: '—' }} — {{ $selected->ends_at ?: '—' }}</div></div></div>

@if($colleagues->count())
<div class="card mb-4">
  <div class="card-header"><b>Оценка коллег своего цеха</b></div>
  <div class="card-body">
    <div class="text-muted mb-3">Шкала 1–4: 1 — минимальная оценка взаимодействия, 4 — максимальная.</div>
    <form method="post" action="{{ route('attestation.peer',$selected->id) }}">@csrf
      <div class="table-responsive"><table class="table align-middle"><thead><tr><th>Сотрудник</th><th>Должность</th><th style="width:260px">Оценка</th></tr></thead><tbody>
      @foreach($colleagues as $u)<tr><td class="fw-semibold">{{ $u->full_name }}</td><td>{{ $u->position ?: '—' }}</td><td><div class="d-flex gap-2">@for($v=1;$v<=4;$v++)<label class="option-box py-2 px-3"><input class="form-check-input me-1" type="radio" name="scores[{{ $u->id }}]" value="{{ $v }}" @checked((int)($myScores[$u->id]??0)===$v)> {{ $v }}</label>@endfor</div></td></tr>@endforeach
      </tbody></table></div>
      <button class="btn btn-primary">Сохранить оценки</button>
    </form>
  </div>
</div>
@endif

@if($isCommission)
<div class="card">
  <div class="card-header"><b>Аттестационная комиссия</b></div>
  <div class="card-body">
    <form class="mb-3"><input type="hidden" name="campaign" value="{{ $selected->id }}"><div class="row g-2"><div class="col-md-6"><select name="workshop" class="form-select" onchange="this.form.submit()">@foreach($workshops as $w)<option value="{{ $w->id }}" @selected(request('workshop')==$w->id)>{{ $w->name }}</option>@endforeach</select></div></div></form>
    @if($commissionTargets->count())
    <form method="post" action="{{ route('attestation.commission',$selected->id) }}">@csrf
      <div class="table-responsive"><table class="table align-middle"><thead><tr><th>Сотрудник</th><th>Оценка</th><th>Комментарий</th></tr></thead><tbody>
      @foreach($commissionTargets as $u)<tr><td>{{ $u->full_name }}<div class="small text-muted">{{ $u->position }}</div></td><td><div class="d-flex gap-1">@for($v=1;$v<=4;$v++)<label class="option-box py-2 px-2"><input class="form-check-input me-1" type="radio" name="scores[{{ $u->id }}]" value="{{ $v }}" @checked((int)($myCommissionScores[$u->id]??0)===$v)> {{ $v }}</label>@endfor</div></td><td><input name="comments[{{ $u->id }}]" class="form-control" placeholder="Комментарий комиссии"></td></tr>@endforeach
      </tbody></table></div><button class="btn btn-primary">Сохранить оценки комиссии</button>
    </form>
    @endif
  </div>
</div>
@endif
@endif
@endsection

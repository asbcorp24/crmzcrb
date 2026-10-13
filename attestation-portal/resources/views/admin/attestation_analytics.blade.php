@extends('layouts.app')
@section('title','Аналитика аттестации')
@section('content')
<div class="d-flex flex-wrap justify-content-between gap-2 align-items-end mb-4">
  <div><div class="circuit-title">ATTESTATION ANALYTICS</div><h1 class="h3 mb-0">Аналитика аттестации</h1></div>
  <form><select name="campaign" class="form-select" onchange="this.form.submit()">@foreach($campaigns as $c)<option value="{{ $c->id }}" @selected($campaign && $campaign->id==$c->id)>{{ $c->title }}</option>@endforeach</select></form>
</div>

@if(!$campaign)<div class="alert alert-secondary">Нет кампаний для анализа.</div>@else
<div class="row g-3 mb-4">
  <div class="col-md-4"><div class="card h-100"><div class="card-body"><div class="circuit-title">Общий средний балл</div><div class="metric mt-2">{{ $overall ?? '—' }}</div><div class="text-muted">из 4</div></div></div></div>
  <div class="col-md-8"><div class="card h-100"><div class="card-header"><b>Распределение оценок</b></div><div class="card-body"><canvas id="scoreChart" height="220" data-tech-chart="bar"
  data-labels="{{ e(json_encode(['1','2','3','4'])) }}"
  data-datasets="{{ e(json_encode([['label'=>'Количество оценок','data'=>array_values($distribution)]])) }}"></canvas></div></div></div>
</div>

<div class="card"><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Сотрудник</th><th>Цех</th><th>Средняя коллег</th><th>Средняя комиссии</th><th>Итоговая</th><th>Оценок</th></tr></thead><tbody>
@foreach($rows as $r)<tr><td class="fw-semibold">{{ $r['user']?->full_name ?? 'Удалённый сотрудник' }}</td><td>{{ $r['user']?->workshop?->name ?? '—' }}</td><td>{{ $r['peer_avg'] ?? '—' }}</td><td>{{ $r['commission_avg'] ?? '—' }}</td><td><span class="badge badge-tech">{{ $r['total_avg'] ?? '—' }}</span></td><td>{{ $r['count'] }}</td></tr>@endforeach
</tbody></table></div></div>
@endif
@endsection

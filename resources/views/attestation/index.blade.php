@extends('layouts.app')
@section('title','Аттестация — CRM')
@section('header','Аттестация')

@push('styles')
<style>
.score-card{border:1px solid #e5e7eb;border-radius:14px}.score-options{display:grid;grid-template-columns:repeat(4,minmax(80px,1fr));gap:8px}.score-choice{border:1px solid #dee2e6;border-radius:10px;padding:10px;text-align:center;cursor:pointer}.score-choice input{margin-right:5px}.matrix-wrap{overflow:auto;max-height:70vh}.matrix-table{white-space:nowrap}.matrix-table th,.matrix-table td{text-align:center;vertical-align:middle}.matrix-table .person{text-align:left;position:sticky;left:0;background:#fff;z-index:2;min-width:230px}.matrix-table thead th{position:sticky;top:0;background:#ffc107;z-index:3}.matrix-table thead .person{z-index:4}.score-1{background:#f8d7da!important}.score-2{background:#fff3cd!important}.score-3{background:#cff4fc!important}.score-4{background:#d1e7dd!important}.legend-box{width:18px;height:18px;border-radius:4px;display:inline-block;vertical-align:middle;margin-right:5px}@media(max-width:767px){.score-options{grid-template-columns:repeat(2,1fr)}.matrix-table .person{min-width:180px}}
</style>
@endpush

@section('content')
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

<div class="row g-3">
    <div class="col-xl-3">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white d-flex align-items-center"><b>Периоды аттестации</b>@if(auth()->user()->isManager())<button class="btn btn-sm btn-primary ms-auto" data-bs-toggle="modal" data-bs-target="#createCampaign"><i class="bi bi-plus-lg"></i></button>@endif</div>
            <div class="list-group list-group-flush">
                @forelse($campaigns as $c)
                    <a class="list-group-item list-group-item-action {{ $selected && $selected->id==$c->id?'active':'' }}" href="{{ route('attestation.page',['campaign'=>$c->id]) }}">
                        <div class="fw-semibold">{{ $c->title }}</div>
                        <div class="small {{ $selected && $selected->id==$c->id?'text-white-50':'text-muted' }}">{{ $c->starts_at ?: 'без даты' }} — {{ $c->ends_at ?: 'без даты' }}</div>
                    </a>
                @empty
                    <div class="p-3 text-muted">Аттестаций пока нет.</div>
                @endforelse
            </div>
        </div>
    </div>

    <div class="col-xl-9">
        @if($selected)
            <div class="card border-0 shadow-sm mb-3"><div class="card-body">
                <div class="d-flex flex-wrap align-items-center gap-2"><div><h4 class="mb-1">{{ $selected->title }}</h4><div class="text-muted">{{ $selected->description }}</div></div><span class="badge {{ $selected->is_active?'text-bg-success':'text-bg-secondary' }} ms-auto">{{ $selected->is_active?'Активна':'Закрыта' }}</span></div>
                <hr>
                <div class="d-flex flex-wrap gap-3 small">
                    <span><span class="legend-box score-1"></span><b>1</b> — связи нет</span>
                    <span><span class="legend-box score-2"></span><b>2</b> — связь есть, но плохая</span>
                    <span><span class="legend-box score-3"></span><b>3</b> — средняя</span>
                    <span><span class="legend-box score-4"></span><b>4</b> — хорошая</span>
                </div>
            </div>

            @if($myDepartment && $colleagues->count())
            <div class="card border-0 shadow-sm mb-3"><div class="card-header bg-white"><b>Моя оценка коллег</b><div class="small text-muted">Подразделение: {{ $myDepartment->name }}. Нужно оценить каждого сотрудника, кроме себя.</div></div><div class="card-body">
                <form method="POST" action="{{ route('attestation.scores.save',$selected->id) }}">@csrf
                @foreach($colleagues as $person)
                    <div class="score-card p-3 mb-3">
                        <div class="d-flex flex-wrap align-items-center gap-2 mb-2"><div><div class="fw-semibold">{{ $person->last_name }} {{ $person->first_name }} {{ $person->middle_name }}</div><div class="small text-muted">{{ $person->position ?: 'Должность не указана' }}</div></div>@if(isset($myScores[$person->id]))<span class="badge text-bg-light border ms-auto">Текущая оценка: {{ $myScores[$person->id] }}</span>@endif</div>
                        <div class="score-options">
                            @foreach([1=>'Связи нет',2=>'Плохая',3=>'Средняя',4=>'Хорошая'] as $score=>$label)
                                <label class="score-choice score-{{ $score }}"><input type="radio" name="scores[{{ $person->id }}]" value="{{ $score }}" {{ (int)($myScores[$person->id]??0)===$score?'checked':'' }} required><b>{{ $score }}</b> — {{ $label }}</label>
                            @endforeach
                        </div>
                    </div>
                @endforeach
                <button class="btn btn-primary"><i class="bi bi-check2-circle me-1"></i>Сохранить все оценки</button>
                </form>
            </div></div>
            @elseif($myDepartment)
                <div class="alert alert-info">В этом подразделении нет других активных сотрудников для оценки либо аттестация ему не назначена.</div>
            @endif

            @if(auth()->user()->isManager() && $analytics)
            <div class="card border-0 shadow-sm mb-3"><div class="card-header bg-white d-flex flex-wrap align-items-center gap-2"><div><b>Свод и аналитика</b><div class="small text-muted">Начальник видит оценки по доступному подразделению.</div></div><form class="ms-auto d-flex gap-2" method="GET"><input type="hidden" name="campaign" value="{{ $selected->id }}"><select class="form-select form-select-sm" name="department" onchange="this.form.submit()">@foreach($departments as $d)<option value="{{ $d->id }}" {{ (int)$analytics['department_id']===(int)$d->id?'selected':'' }}>{{ $d->name }}</option>@endforeach</select></form></div>
                <div class="card-body">
                    <div class="row g-3 mb-3">
                        <div class="col-md-3"><div class="border rounded p-3"><div class="text-muted small">Средняя связь</div><div class="fs-3 fw-bold">{{ $analytics['overall_avg'] ?? '—' }}</div></div></div>
                        <div class="col-md-3"><div class="border rounded p-3"><div class="text-muted small">Прошли</div><div class="fs-3 fw-bold">{{ $analytics['completed_evaluators'] }}/{{ $analytics['expected_evaluators'] }}</div></div></div>
                        @foreach([1=>'Нет связи',2=>'Плохая',3=>'Средняя',4=>'Хорошая'] as $k=>$label)
                        <div class="col-md-3"><div class="border rounded p-3"><div class="text-muted small">{{ $label }}</div><div class="fs-3 fw-bold">{{ $analytics['score_counts'][$k] }}</div></div></div>
                        @endforeach
                    </div>
                    <div class="matrix-wrap border rounded">
                        <table class="table table-bordered table-sm mb-0 matrix-table">
                            <thead><tr><th class="person">ФИО / должность</th><th>СРЕД</th><th>Кол.</th><th>Сумма</th>@foreach($analytics['people'] as $e)<th title="{{ $e->last_name }} {{ $e->first_name }}">{{ $e->id }}</th>@endforeach</tr></thead>
                            <tbody>
                            @foreach($analytics['rows'] as $row)
                                <tr><td class="person"><div class="fw-semibold">{{ $row['user']->last_name }} {{ $row['user']->first_name }} {{ $row['user']->middle_name }}</div><div class="small text-muted">{{ $row['user']->position }}</div></td><td class="fw-bold">{{ $row['avg'] ?? '—' }}</td><td>{{ $row['count'] }}</td><td>{{ $row['sum'] }}</td>@foreach($analytics['people'] as $e)@php($v=$row['scores'][$e->id]??null)<td class="{{ $v?'score-'.$v:'' }}">{{ $e->id==$row['user']->id?'—':($v??'') }}</td>@endforeach</tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="small text-muted mt-2">Число в колонке сотрудника — оценка, которую этот сотрудник поставил человеку в строке. Пустая ячейка означает, что оценка ещё не дана.</div>
                </div>
            </div>
            @endif
        @else
            <div class="alert alert-info">Создайте первую аттестацию.</div>
        @endif
    </div>
</div>

@if(auth()->user()->isManager())
<div class="modal fade" id="createCampaign" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content"><form method="POST" action="{{ route('attestation.campaigns.store') }}">@csrf<div class="modal-header"><h5 class="modal-title">Новая аттестация</h5><button class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><div class="mb-3"><label class="form-label">Название</label><input class="form-control" name="title" value="Аттестация рабочих связей" required></div><div class="mb-3"><label class="form-label">Описание</label><textarea class="form-control" name="description" rows="2">Оценка качества рабочих связей между сотрудниками подразделения по шкале 1–4.</textarea></div><div class="row g-2 mb-3"><div class="col-md-6"><label class="form-label">Начало</label><input type="date" class="form-control" name="starts_at"></div><div class="col-md-6"><label class="form-label">Окончание</label><input type="date" class="form-control" name="ends_at"></div></div><label class="form-label">Подразделения</label><div class="row">@foreach($departments as $d)<div class="col-md-6"><label class="form-check mb-2"><input class="form-check-input" type="checkbox" name="department_ids[]" value="{{ $d->id }}"><span class="form-check-label">{{ $d->name }}</span></label></div>@endforeach</div></div><div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Отмена</button><button class="btn btn-primary">Создать</button></div></form></div></div></div>
@endif
@endsection

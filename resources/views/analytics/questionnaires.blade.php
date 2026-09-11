@extends('layouts.app')

@section('title', 'Аналитика анкет — CRM')
@section('header', 'Аналитика анкет')

@section('content')
<div class="d-flex flex-wrap gap-2 align-items-center mb-3">
    <a href="{{ route('questionnaires.page') }}" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> К анкетам
    </a>

    @if($questionnaire)
        <form class="ms-auto" method="GET">
            <select class="form-select" name="questionnaire" onchange="this.form.submit()">
                @foreach($questionnaires as $q)
                    <option value="{{ $q->id }}" {{ $questionnaire->id == $q->id ? 'selected' : '' }}>
                        {{ $q->title }}
                    </option>
                @endforeach
            </select>
        </form>
    @endif
</div>

@if(!$questionnaire)
    <div class="alert alert-info">Анкет пока нет.</div>
@else
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <h4 class="mb-1">{{ $questionnaire->title }}</h4>
            <div class="text-muted">{{ $questionnaire->description }}</div>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="small text-muted">Должны заполнить</div>
                    <div class="display-6 fw-semibold">{{ $expected }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="small text-muted">Заполнили</div>
                    <div class="display-6 fw-semibold">{{ $submitted }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="small text-muted">Готовность</div>
                    <div class="display-6 fw-semibold">{{ $expected ? round($submitted * 100 / $expected, 1) : 0 }}%</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="small text-muted">Вопросов</div>
                    <div class="display-6 fw-semibold">{{ count($schema) }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-white"><b>Участие по подразделениям</b></div>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Подразделение</th>
                        <th>Заполнили</th>
                        <th>Всего</th>
                        <th>Готовность</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($departmentRows as $r)
                        <tr>
                            <td><b>{{ $r['name'] }}</b></td>
                            <td>{{ $r['submitted'] }}</td>
                            <td>{{ $r['expected'] }}</td>
                            <td><b>{{ $r['percent'] }}%</b></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    @if(count($scaleAverages) > 0)
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white">
                <b>Рейтинг компетенций</b>
                <div class="small text-muted">От наиболее высокой средней оценки к наиболее низкой.</div>
            </div>
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Компетенция</th>
                            <th>Средний балл</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($scaleAverages as $r)
                            <tr>
                                <td>{{ $loop->iteration }}</td>
                                <td>{{ $r['title'] }}</td>
                                <td><b>{{ $r['average'] }}</b></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-white"><b>Полная аналитика по вопросам</b></div>
        <div class="card-body">
            @forelse($questionRows as $r)
                <div class="border-bottom pb-3 mb-3">
                    <div class="fw-semibold">
                        @if(!empty($r['section']))
                            Раздел {{ $r['section'] }} ·
                        @endif
                        {{ $r['title'] }}
                    </div>
                    <div class="small text-muted mb-2">Ответов: {{ $r['count'] }}</div>

                    @if($r['type'] === 'scale')
                        <div class="mb-2">
                            <span class="fs-4 fw-bold">{{ $r['average'] ?? '—' }}</span>
                            <span class="text-muted">средний балл</span>
                        </div>
                        <div class="d-flex flex-wrap gap-2">
                            @foreach(($r['distribution'] ?? []) as $score => $count)
                                <span class="badge text-bg-light border">{{ $score }}: {{ $count }}</span>
                            @endforeach
                        </div>
                    @elseif(in_array($r['type'], ['single', 'multiple'], true))
                        @foreach(($r['distribution'] ?? []) as $d)
                            <div class="d-flex justify-content-between gap-3 py-1">
                                <span>{{ $d['label'] }}</span>
                                <b>{{ $d['count'] }} · {{ $d['percent'] }}%</b>
                            </div>
                        @endforeach
                    @elseif($r['type'] === 'text')
                        <div class="text-muted small">Текстовые ответы учитываются в общем количестве ответов.</div>
                    @endif

                    @if(!empty($r['peer_mentions']))
                        <div class="mt-2">
                            <span class="small text-muted">Чаще всего упоминали:</span>
                            @foreach($r['peer_mentions'] as $m)
                                <span class="badge text-bg-info me-1">{{ $m['name'] }} · {{ $m['count'] }}</span>
                            @endforeach
                        </div>
                    @endif
                </div>
            @empty
                <div class="text-muted">Нет данных по вопросам.</div>
            @endforelse
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white"><b>Кто заполнил</b></div>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>ФИО</th>
                        <th>Должность</th>
                        <th>Подразделение</th>
                        <th>Дата</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($responses as $r)
                        @php
                            $departmentRow = $departmentRows->firstWhere('id', (int) $r->department_id);
                        @endphp
                        <tr>
                            <td>{{ $r->respondent_name ?: 'Анонимно' }}</td>
                            <td>{{ $r->position ?: '—' }}</td>
                            <td>{{ $departmentRow['name'] ?? '—' }}</td>
                            <td>{{ $r->submitted_at }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="text-center text-muted py-4">Ответов пока нет.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endif
@endsection

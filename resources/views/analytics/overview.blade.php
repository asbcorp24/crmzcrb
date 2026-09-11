@extends('layouts.app')

@section('title', 'Аналитика — CRM')
@section('header', 'Аналитика организации')

@section('content')
@include('analytics._nav')

@php
    $kpiCards = [
        ['Сотрудники', $kpi['employees'], 'bi-people'],
        ['Подразделения', $kpi['departments'], 'bi-diagram-3'],
        ['Открытые задачи', $kpi['open'], 'bi-list-task'],
        ['Просрочено', $kpi['overdue'], 'bi-exclamation-triangle'],
        ['Активные планы', $kpi['active_plans'], 'bi-calendar3'],
        ['Просроченные планы', $kpi['overdue_plans'], 'bi-calendar-x'],
    ];

    $taskTrendChart = [
        'labels' => $taskTrend['labels'],
        'series' => [
            [
                'label' => 'Создано',
                'values' => $taskTrend['created'],
            ],
            [
                'label' => 'Выполнено',
                'values' => $taskTrend['completed'],
            ],
        ],
    ];

    $workloadChart = [
        'labels' => ['Открыто', 'Выполнено', 'Просрочено'],
        'values' => [$kpi['open'], $kpi['done'], $kpi['overdue']],
    ];
@endphp

<div class="row g-3 mb-3">
    @foreach($kpiCards as $card)
        <div class="col-6 col-xl-2">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="small text-muted">
                        <i class="bi {{ $card[2] }} me-1"></i>{{ $card[0] }}
                    </div>
                    <div class="fs-2 fw-semibold">{{ $card[1] }}</div>
                </div>
            </div>
        </div>
    @endforeach
</div>

<div class="row g-3 mb-3">
    <div class="col-xl-7">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white"><b>Динамика задач за 6 месяцев</b></div>
            <div class="card-body">
                <canvas
                    data-height="300"
                    data-crm-chart="line"
                    data-chart='@json($taskTrendChart)'></canvas>
            </div>
        </div>
    </div>

    <div class="col-xl-5">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white"><b>Общая загрузка</b></div>
            <div class="card-body">
                <canvas
                    data-height="300"
                    data-crm-chart="donut"
                    data-chart='@json($workloadChart)'></canvas>
            </div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white"><b>Подразделения</b></div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>Подразделение</th>
                    <th>Сотрудники</th>
                    <th>Всего задач</th>
                    <th>Открыто</th>
                    <th>Просрочено</th>
                    <th>Выполнено</th>
                </tr>
            </thead>
            <tbody>
                @forelse($departmentRows as $row)
                    <tr>
                        <td><b>{{ $row['name'] }}</b></td>
                        <td>{{ $row['employees'] }}</td>
                        <td>{{ $row['tasks'] }}</td>
                        <td>{{ $row['open'] }}</td>
                        <td>{{ $row['overdue'] }}</td>
                        <td>{{ $row['done'] }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">Нет данных по подразделениям.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection

@push('scripts')
<script src="/js/crm-charts.js"></script>
@endpush

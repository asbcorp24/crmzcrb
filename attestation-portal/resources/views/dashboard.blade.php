@extends('layouts.app')
@section('title','Мои задания')
@section('content')
<div class="mb-4"><div class="circuit-title">PERSONAL PANEL</div><h1 class="h3">Мои тесты и анкеты</h1><div class="text-muted">{{ auth()->user()->workshop?->name }} · {{ auth()->user()->position }}</div></div>
<div class="row g-3">
@forelse($tests as $test)
@php($done=($attempts[$test->id]??collect())->where('status','submitted')->count())
<div class="col-md-6 col-xl-4"><div class="card h-100"><div class="card-body d-flex flex-column">
<div class="d-flex justify-content-between"><span class="badge badge-tech">{{ $test->kind==='test'?'ТЕСТ':'АНКЕТА' }}</span><span class="small text-muted">{{ $done }}/{{ $test->attempts_limit }}</span></div>
<h5 class="mt-3">{{ $test->title }}</h5><p class="text-muted flex-grow-1">{{ $test->description }}</p>
<div class="small mb-3">@if($test->time_limit_minutes)<i class="bi bi-stopwatch me-1"></i>{{ $test->time_limit_minutes }} мин. @endif @if($test->closes_at)<span class="ms-2">до {{ $test->closes_at->format('d.m.Y H:i') }}</span>@endif</div>
@if($done<$test->attempts_limit)<a class="btn btn-primary" href="{{ route('attempt.show',$test) }}">{{ $done?'Повторить':'Начать' }}</a>@else<span class="btn btn-outline-secondary disabled">Лимит попыток исчерпан</span>@endif
</div></div></div>
@empty<div class="col-12"><div class="alert alert-secondary">Активных тестов и анкет для вашего цеха сейчас нет.</div></div>@endforelse
</div>
@if($campaigns->count())<div class="section-line mt-5 pt-4"><h2 class="h4">Аттестация</h2><p class="text-muted">Доступны кампании взаимной оценки сотрудников вашего цеха.</p><a class="btn btn-outline-primary" href="{{ route('attestation.index') }}">Перейти к аттестации</a></div>@endif
@endsection

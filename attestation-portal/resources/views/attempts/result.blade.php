@extends('layouts.app')
@section('title','Результат — '.$attempt->test->title)
@section('content')
<div class="row justify-content-center"><div class="col-xl-8">
<div class="card">
  <div class="card-body p-4">
    <div class="circuit-title">RESULT NODE #{{ $attempt->id }}</div>
    <h1 class="h3">{{ $attempt->test->title }}</h1>
    @if($attempt->test->kind==='test')
      <div class="row g-3 my-2">
        <div class="col-4"><div class="option-box"><div class="circuit-title">Баллы</div><div class="metric">{{ $attempt->score }} / {{ $attempt->max_score }}</div></div></div>
        <div class="col-4"><div class="option-box"><div class="circuit-title">Результат</div><div class="metric">{{ $attempt->percent }}%</div></div></div>
        <div class="col-4"><div class="option-box"><div class="circuit-title">Статус</div><div class="metric fs-4">{{ is_null($attempt->passed)?'Завершено':($attempt->passed?'Сдан':'Не сдан') }}</div></div></div>
      </div>
    @else
      <div class="alert alert-success mt-3">Анкета заполнена. Ответы сохранены.</div>
    @endif
    <div class="text-muted mt-3">Отправлено: {{ $attempt->submitted_at?->format('d.m.Y H:i:s') }}</div>
    <a href="{{ route('dashboard') }}" class="btn btn-outline-primary mt-4">К списку заданий</a>
  </div>
</div>
</div></div>
@endsection

@extends('layouts.app')
@section('title',$test->title)
@section('content')
<div class="mb-4">
  <div class="circuit-title">{{ $test->kind==='test'?'TEST RUN':'QUESTIONNAIRE' }} // #{{ $test->id }}</div>
  <h1 class="h3 mb-1">{{ $test->title }}</h1>
  @if($test->description)<div class="text-muted">{{ $test->description }}</div>@endif
  @if($test->instructions)<div class="alert alert-secondary mt-3 mb-0">{{ $test->instructions }}</div>@endif
</div>

<form method="post" action="{{ route('attempt.submit',[$test,$attempt]) }}">
  @csrf
  @foreach($test->questions as $q)
    <div class="card mb-3">
      <div class="card-body">
        <div class="d-flex justify-content-between gap-2">
          <div>
            @if($q->section)<div class="circuit-title mb-1">{{ $q->section }}</div>@endif
            <h5 class="mb-1">{{ $loop->iteration }}. {{ $q->text }}</h5>
            <div class="small text-muted">@if($q->required)Обязательный вопрос@endif</div>
          </div>
          <span class="badge badge-tech">{{ $q->type }}</span>
        </div>

        <div class="mt-3">
          @if($q->type==='single')
            @foreach($q->options as $o)
              <label class="option-box d-flex gap-2 align-items-start mb-2">
                <input class="form-check-input mt-1" type="radio" name="answers[{{ $q->id }}]" value="{{ $o->value }}" @required($q->required)>
                <span>{{ $o->label }}</span>
              </label>
            @endforeach
          @elseif($q->type==='multiple')
            @foreach($q->options as $o)
              <label class="option-box d-flex gap-2 align-items-start mb-2">
                <input class="form-check-input mt-1" type="checkbox" name="answers[{{ $q->id }}][]" value="{{ $o->value }}">
                <span>{{ $o->label }}</span>
              </label>
            @endforeach
          @elseif($q->type==='scale')
            <div class="d-flex flex-wrap gap-2">
              @for($v=$q->scale_min;$v<=$q->scale_max;$v++)
                <label class="option-box text-center" style="min-width:64px"><input class="form-check-input me-1" type="radio" name="answers[{{ $q->id }}]" value="{{ $v }}" @required($q->required)> {{ $v }}</label>
              @endfor
            </div>
          @else
            <textarea name="answers[{{ $q->id }}]" class="form-control" rows="4" @required($q->required)></textarea>
          @endif
          @if($q->peer_field)
            <input name="peer[{{ $q->id }}]" class="form-control mt-2" placeholder="Укажите коллег через запятую">
          @endif
        </div>
      </div>
    </div>
  @endforeach

  <div class="card"><div class="card-body d-flex flex-wrap justify-content-between gap-2 align-items-center">
    <div class="small text-muted">Попытка #{{ $attempt->id }} · начата {{ $attempt->started_at?->format('d.m.Y H:i') }}</div>
    <button class="btn btn-primary btn-lg" onclick="return confirm('Завершить и отправить ответы?')">Завершить прохождение</button>
  </div></div>
</form>

@if($test->time_limit_minutes)
<div class="position-fixed bottom-0 end-0 m-3 badge badge-tech p-3"
     data-countdown-deadline="{{ $attempt->started_at?->copy()->addMinutes($test->time_limit_minutes)->toIso8601String() }}">
</div>
@endif
@endsection

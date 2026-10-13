@extends('layouts.app')
@section('title','Конструктор — '.$test->title)
@section('content')
<div class="d-flex flex-wrap justify-content-between gap-2 align-items-end mb-4">
  <div><div class="circuit-title">TEST SCHEMA #{{ $test->id }}</div><h1 class="h3 mb-0">{{ $test->title }}</h1></div>
  <a href="{{ route('admin.analytics',['test'=>$test->id]) }}" class="btn btn-outline-primary">Аналитика</a>
</div>

<div class="card mb-4">
  <div class="card-header"><b>Параметры</b></div>
  <div class="card-body">
    <form method="post" action="{{ route('admin.tests.update',$test) }}" class="row g-3">
      @csrf @method('PATCH')
      <div class="col-md-6"><label class="form-label">Название</label><input name="title" class="form-control" value="{{ $test->title }}" required></div>
      <div class="col-md-3"><label class="form-label">Тип</label><select name="kind" class="form-select"><option value="test" @selected($test->kind==='test')>Тест</option><option value="questionnaire" @selected($test->kind==='questionnaire')>Анкета</option></select></div>
      <div class="col-md-3"><label class="form-label">Статус</label><select name="status" class="form-select">@foreach(['draft'=>'Черновик','active'=>'Активен','closed'=>'Закрыт'] as $v=>$l)<option value="{{ $v }}" @selected($test->status===$v)>{{ $l }}</option>@endforeach</select></div>
      <div class="col-12"><textarea name="description" class="form-control" rows="2" placeholder="Описание">{{ $test->description }}</textarea></div>
      <div class="col-12"><textarea name="instructions" class="form-control" rows="2" placeholder="Инструкция">{{ $test->instructions }}</textarea></div>
      <div class="col-md-4"><label class="form-label">Цеха</label><select name="workshop_ids[]" class="form-select" multiple size="5">@foreach($workshops as $w)<option value="{{ $w->id }}" @selected($test->workshops->contains('id',$w->id))>{{ $w->name }}</option>@endforeach</select></div>
      <div class="col-md-2"><label class="form-label">Попыток</label><input name="attempts_limit" type="number" min="1" class="form-control" value="{{ $test->attempts_limit }}"></div>
      <div class="col-md-2"><label class="form-label">Минут</label><input name="time_limit_minutes" type="number" min="1" class="form-control" value="{{ $test->time_limit_minutes }}"></div>
      <div class="col-md-2"><label class="form-label">Проходной %</label><input name="pass_percent" type="number" step=".01" min="0" max="100" class="form-control" value="{{ $test->pass_percent }}"></div>
      <div class="col-md-2 d-flex align-items-end"><div class="form-check"><input class="form-check-input" type="checkbox" name="is_anonymous" value="1" @checked($test->is_anonymous)><label class="form-check-label">Анонимно</label></div></div>
      <div class="col-md-3"><label class="form-label">Открыть</label><input name="opens_at" type="datetime-local" class="form-control" value="{{ $test->opens_at?->format('Y-m-d\TH:i') }}"></div>
      <div class="col-md-3"><label class="form-label">Закрыть</label><input name="closes_at" type="datetime-local" class="form-control" value="{{ $test->closes_at?->format('Y-m-d\TH:i') }}"></div>
      <div class="col-12"><button class="btn btn-primary">Сохранить параметры</button></div>
    </form>
  </div>
</div>

<div class="row g-3">
  <div class="col-lg-7">
    <div class="card">
      <div class="card-header d-flex justify-content-between"><b>Вопросы</b><span class="badge badge-tech">{{ $test->questions->count() }}</span></div>
      <div class="card-body">
        @forelse($test->questions as $q)
          <div class="option-box mb-3">
            <div class="d-flex justify-content-between gap-2">
              <div>
                <span class="badge badge-tech">{{ $q->type }}</span>
                @if($q->section)<span class="small text-muted ms-2">{{ $q->section }}</span>@endif
                <h6 class="mt-2 mb-1">{{ $q->text }}</h6>
                <div class="small text-muted">Вес: {{ $q->weight }} @if($q->required) · обязательный @endif @if($q->peer_field) · поле коллег @endif</div>
              </div>
              <form method="post" action="{{ route('admin.tests.questions.destroy',[$test,$q]) }}">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger" onclick="return confirm('Удалить вопрос?')"><i class="bi bi-trash"></i></button></form>
            </div>
            @if($q->options->count())
              <div class="small mt-2">
                @foreach($q->options as $o)<div>{{ $o->is_correct?'✓':'○' }} <code>{{ $o->value }}</code> — {{ $o->label }}</div>@endforeach
              </div>
            @endif
            @if($q->type==='scale')<div class="small text-muted mt-2">Шкала {{ $q->scale_min }}–{{ $q->scale_max }}</div>@endif
            <details class="mt-3">
              <summary class="small text-info">Редактировать вопрос</summary>
              <form method="post" action="{{ route('admin.tests.questions.update',[$test,$q]) }}" class="mt-3">
                @csrf @method('PATCH')
                <div class="mb-2"><input name="section" class="form-control" value="{{ $q->section }}" placeholder="Раздел"></div>
                <div class="mb-2"><textarea name="text" class="form-control" rows="3" required>{{ $q->text }}</textarea></div>
                <div class="row g-2">
                  <div class="col-6"><select name="type" class="form-select">@foreach(['single'=>'Один вариант','multiple'=>'Несколько','scale'=>'Шкала','text'=>'Текст'] as $v=>$l)<option value="{{ $v }}" @selected($q->type===$v)>{{ $l }}</option>@endforeach</select></div>
                  <div class="col-3"><input name="weight" type="number" step=".1" min="0" class="form-control" value="{{ $q->weight }}"></div>
                  <div class="col-3 d-flex align-items-center"><div class="form-check"><input name="required" value="1" class="form-check-input" type="checkbox" @checked($q->required)><label class="small">обяз.</label></div></div>
                </div>
                <div class="row g-2 mt-1"><div class="col-6"><input name="scale_min" type="number" class="form-control" value="{{ $q->scale_min ?? 1 }}"></div><div class="col-6"><input name="scale_max" type="number" class="form-control" value="{{ $q->scale_max ?? 4 }}"></div></div>
                <div class="form-check my-2"><input name="peer_field" value="1" type="checkbox" class="form-check-input" @checked($q->peer_field)><label class="form-check-label">Поле коллег</label></div>
                <textarea name="options_text" rows="4" class="form-control mb-2">@foreach($q->options as $o){{ $o->value }}|{{ $o->label }}@if(!$loop->last){{ "\n" }}@endif @endforeach</textarea>
                <input name="correct_values" class="form-control mb-2" value="{{ $q->options->where('is_correct',true)->pluck('value')->join(',') }}" placeholder="Правильные значения">
                <button class="btn btn-sm btn-outline-primary">Сохранить вопрос</button>
              </form>
            </details>
          </div>
        @empty
          <div class="text-muted">Вопросов пока нет.</div>
        @endforelse
      </div>
    </div>
  </div>

  <div class="col-lg-5">
    <div class="card sticky-lg-top" style="top:80px">
      <div class="card-header"><b>Добавить вопрос</b></div>
      <div class="card-body">
        <form method="post" action="{{ route('admin.tests.questions.store',$test) }}">@csrf
          <div class="mb-2"><input name="section" class="form-control" placeholder="Раздел"></div>
          <div class="mb-2"><textarea name="text" class="form-control" rows="3" placeholder="Текст вопроса" required></textarea></div>
          <div class="row g-2">
            <div class="col-6"><select name="type" class="form-select"><option value="single">Один вариант</option><option value="multiple">Несколько вариантов</option><option value="scale">Шкала</option><option value="text">Свободный текст</option></select></div>
            <div class="col-3"><input name="weight" type="number" step=".1" value="1" min="0" class="form-control" title="Вес"></div>
            <div class="col-3 d-flex align-items-center"><div class="form-check"><input name="required" value="1" class="form-check-input" type="checkbox" checked><label class="small">обяз.</label></div></div>
          </div>
          <div class="row g-2 mt-1"><div class="col-6"><input name="scale_min" type="number" value="1" class="form-control" placeholder="min"></div><div class="col-6"><input name="scale_max" type="number" value="4" class="form-control" placeholder="max"></div></div>
          <div class="form-check my-2"><input name="peer_field" value="1" type="checkbox" class="form-check-input" id="peerField"><label class="form-check-label" for="peerField">Доп. поле «укажите коллег»</label></div>
          <label class="form-label small">Варианты: по одному на строке, формат <code>значение|подпись</code></label>
          <textarea name="options_text" rows="5" class="form-control mb-2" placeholder="A|Вариант A&#10;B|Вариант B"></textarea>
          <input name="correct_values" class="form-control mb-3" placeholder="Правильные значения через запятую: A,B">
          <button class="btn btn-primary w-100">Добавить вопрос</button>
        </form>
      </div>
    </div>
  </div>
</div>
@endsection

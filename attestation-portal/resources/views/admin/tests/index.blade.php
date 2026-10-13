@extends('layouts.app')
@section('title','Конструктор тестов')
@section('content')
<div class="mb-4"><div class="circuit-title">LOGIC BUILDER</div><h1 class="h3">Конструктор тестов и анкет</h1></div>
<div class="card mb-4"><div class="card-header"><b>Создать</b></div><div class="card-body"><form method="post" action="{{ route('admin.tests.store') }}" class="row g-3">@csrf
<div class="col-md-6"><label class="form-label">Название</label><input name="title" class="form-control" required></div>
<div class="col-md-3"><label class="form-label">Тип</label><select name="kind" class="form-select"><option value="test">Тест</option><option value="questionnaire">Анкета</option></select></div>
<div class="col-md-3"><label class="form-label">Статус</label><select name="status" class="form-select"><option value="draft">Черновик</option><option value="active">Активен</option><option value="closed">Закрыт</option></select></div>
<div class="col-12"><label class="form-label">Описание</label><textarea name="description" class="form-control" rows="2"></textarea></div>
<div class="col-12"><label class="form-label">Инструкция</label><textarea name="instructions" class="form-control" rows="2"></textarea></div>
<div class="col-md-4"><label class="form-label">Цеха</label><select name="workshop_ids[]" class="form-select" multiple size="5" required>@foreach($workshops as $w)<option value="{{ $w->id }}">{{ $w->name }}</option>@endforeach</select></div>
<div class="col-md-2"><label class="form-label">Попыток</label><input name="attempts_limit" type="number" min="1" value="1" class="form-control"></div>
<div class="col-md-2"><label class="form-label">Минут</label><input name="time_limit_minutes" type="number" min="1" class="form-control"></div>
<div class="col-md-2"><label class="form-label">Проходной %</label><input name="pass_percent" type="number" min="0" max="100" step=".01" class="form-control"></div>
<div class="col-md-2 d-flex align-items-end"><div class="form-check"><input class="form-check-input" name="is_anonymous" value="1" type="checkbox" id="anon"><label class="form-check-label" for="anon">Анонимно</label></div></div>
<div class="col-md-3"><label class="form-label">Открыть</label><input name="opens_at" type="datetime-local" class="form-control"></div><div class="col-md-3"><label class="form-label">Закрыть</label><input name="closes_at" type="datetime-local" class="form-control"></div>
<div class="col-12"><button class="btn btn-primary">Создать и открыть конструктор</button></div></form></div></div>
<div class="card"><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Название</th><th>Тип</th><th>Цеха</th><th>Вопросов</th><th>Ответов</th><th>Статус</th><th></th></tr></thead><tbody>
@foreach($tests as $t)<tr><td class="fw-semibold">{{ $t->title }}</td><td>{{ $t->kind==='test'?'Тест':'Анкета' }}</td><td>{{ $t->workshops->pluck('name')->join(', ') }}</td><td>{{ $t->questions_count }}</td><td>{{ $t->attempts_count }}</td><td><span class="badge badge-tech">{{ $t->status }}</span></td><td><a class="btn btn-sm btn-outline-primary" href="{{ route('admin.tests.edit',$t) }}">Конструктор</a></td></tr>@endforeach
</tbody></table></div></div><div class="mt-3">{{ $tests->links() }}</div>
@endsection

@extends('layouts.app')
@section('title','Аналитика тестирования')
@section('content')
<div class="d-flex flex-wrap justify-content-between gap-2 align-items-end mb-4">
  <div><div class="circuit-title">ANALYTICS BUS</div><h1 class="h3 mb-0">Тесты и анкетирование</h1></div>
  <form><select name="test" class="form-select" onchange="this.form.submit()"><option value="">Общая аналитика</option>@foreach($tests as $t)<option value="{{ $t->id }}" @selected($test && $test->id==$t->id)>{{ $t->title }}</option>@endforeach</select></form>
</div>

<div class="row g-3 mb-4">
  <div class="col-xl-7"><div class="card h-100"><div class="card-header"><b>Количество прохождений</b></div><div class="card-body"><canvas id="overviewChart" height="110"></canvas></div></div></div>
  <div class="col-xl-5"><div class="card h-100"><div class="card-header"><b>Сводка</b></div><div class="table-responsive"><table class="table mb-0"><thead><tr><th>Тест</th><th>Попыток</th><th>Средний %</th><th>Сдано</th></tr></thead><tbody>@foreach($overview as $r)<tr><td><a href="{{ route('admin.analytics',['test'=>$r['id']]) }}">{{ $r['title'] }}</a></td><td>{{ $r['attempts'] }}</td><td>{{ $r['avg_percent'] }}</td><td>{{ $r['passed'] }}</td></tr>@endforeach</tbody></table></div></div></div>
</div>

@if($test && $detail)
<div class="section-line pt-4">
  <div class="d-flex justify-content-between align-items-center mb-3"><div><div class="circuit-title">DETAIL #{{ $test->id }}</div><h2 class="h4 mb-0">{{ $test->title }}</h2></div><span class="badge badge-tech">{{ $test->kind }}</span></div>

  <div class="row g-3 mb-4">
    <div class="col-xl-6"><div class="card h-100"><div class="card-header"><b>Охват по цехам</b></div><div class="card-body"><canvas id="workshopChart" height="140"></canvas></div></div></div>
    <div class="col-xl-6"><div class="card h-100"><div class="table-responsive"><table class="table mb-0"><thead><tr><th>Цех</th><th>Ожидалось</th><th>Прошли</th><th>Охват</th><th>Средний %</th></tr></thead><tbody>@foreach($detail['by_workshop'] as $r)<tr><td>{{ $r['name'] }}</td><td>{{ $r['eligible'] }}</td><td>{{ $r['submitted'] }}</td><td>{{ $r['percent'] }}%</td><td>{{ $r['avg'] }}</td></tr>@endforeach</tbody></table></div></div></div>
  </div>

  <h3 class="h5 mb-3">Аналитика по вопросам</h3>
  <div class="row g-3">
  @foreach($detail['questions'] as $q)
    <div class="col-xl-6"><div class="card h-100"><div class="card-body">
      <div class="circuit-title">{{ $q['type'] }} · ответов {{ $q['count'] }}</div><h6 class="mt-2">{{ $q['text'] }}</h6>
      @if(isset($q['average']))<div class="metric">{{ $q['average'] }}</div><div class="text-muted small">Среднее значение</div>@endif
      @if(isset($q['distribution']))
        <div class="mt-3">
        @foreach($q['distribution'] as $k=>$v)
          @if(is_array($v))<div class="d-flex justify-content-between border-bottom py-1"><span>{{ $v['label'] }}</span><b>{{ $v['count'] }}</b></div>
          @else<div class="d-flex justify-content-between border-bottom py-1"><span>{{ $k }}</span><b>{{ $v }}</b></div>@endif
        @endforeach
        </div>
      @endif
      @if(!empty($q['peer_mentions']))<div class="mt-3"><div class="small fw-semibold mb-1">Чаще упомянутые коллеги</div>@foreach($q['peer_mentions'] as $p)<span class="badge badge-tech me-1 mb-1">{{ $p['name'] }} · {{ $p['count'] }}</span>@endforeach</div>@endif
      @if(!empty($q['texts']))<details class="mt-3"><summary>Текстовые ответы</summary><div class="mt-2">@foreach($q['texts'] as $txt)<div class="option-box mb-2">{{ $txt }}</div>@endforeach</div></details>@endif
    </div></div></div>
  @endforeach
  </div>

  <div class="card mt-4"><div class="card-header"><b>Результаты сотрудников</b></div><div class="table-responsive"><table class="table mb-0"><thead><tr><th>Сотрудник</th><th>Цех</th><th>Дата</th><th>Баллы</th><th>%</th><th>Результат</th></tr></thead><tbody>
  @foreach($detail['attempts'] as $a)<tr><td>{{ $test->is_anonymous?'Анонимно':$a->user?->full_name }}</td><td>{{ $a->user?->workshop?->name }}</td><td>{{ $a->submitted_at?->format('d.m.Y H:i') }}</td><td>{{ $a->score }}/{{ $a->max_score }}</td><td>{{ $a->percent }}</td><td>@if(is_null($a->passed))—@else{{ $a->passed?'Сдан':'Не сдан' }}@endif</td></tr>@endforeach
  </tbody></table></div></div>
</div>
@endif
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
<script>
const overview=@json($overview->values());
new Chart(document.getElementById('overviewChart'),{type:'bar',data:{labels:overview.map(x=>x.title),datasets:[{label:'Прохождений',data:overview.map(x=>x.attempts)}]},options:{responsive:true,plugins:{legend:{display:false}},scales:{x:{ticks:{color:'#9fb8c5'}},y:{beginAtZero:true,ticks:{color:'#9fb8c5'}}}}});
@if($test && $detail)
const workshop=@json($detail['by_workshop']);
new Chart(document.getElementById('workshopChart'),{type:'bar',data:{labels:workshop.map(x=>x.name),datasets:[{label:'Прошли',data:workshop.map(x=>x.submitted)},{label:'Ожидалось',data:workshop.map(x=>x.eligible)}]},options:{responsive:true,scales:{x:{ticks:{color:'#9fb8c5'}},y:{beginAtZero:true,ticks:{color:'#9fb8c5'}}}}});
@endif
</script>
@endpush

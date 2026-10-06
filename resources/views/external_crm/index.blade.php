@extends('layouts.app')
@section('title','Внешняя CRM — CRM ЗЦРБ')
@section('header','Внешняя CRM')
@section('content')

@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

<div class="row g-4">
  <div class="col-12">
    <div class="card border-0 shadow-sm">
      <div class="card-header bg-white d-flex align-items-center justify-content-between">
        <div><b>Подключение к документообороту</b><div class="small text-muted">Организация: {{ $organization->display_name }}</div></div>
        @if($health)
          <span class="badge text-bg-success"><i class="bi bi-check-circle me-1"></i>API {{ $health['api_version'] ?? 'v1' }}</span>
        @elseif($tokenConfigured)
          <span class="badge text-bg-warning">Не проверено</span>
        @endif
      </div>
      <div class="card-body">
        <form method="POST" action="{{ route('external-crm.connection') }}" class="row g-3">
          @csrf @method('PATCH')
          <div class="col-lg-7">
            <label class="form-label fw-semibold">Адрес внешней CRM</label>
            <input name="external_crm_url" type="url" class="form-control" value="{{ old('external_crm_url',$baseUrl) }}" placeholder="http://192.168.0.100:5001" required>
          </div>
          <div class="col-lg-5">
            <label class="form-label fw-semibold">API-токен</label>
            <input name="external_crm_token" type="password" class="form-control" autocomplete="new-password" placeholder="{{ $tokenConfigured ? 'Токен сохранён — оставьте пустым, чтобы не менять' : 'Вставьте токен' }}">
          </div>
          <div class="col-12 d-flex gap-2 flex-wrap">
            <button class="btn btn-primary"><i class="bi bi-save me-1"></i>Сохранить подключение</button>
            @if($tokenConfigured && $baseUrl)
              <button type="button" class="btn btn-outline-primary" id="testExternalCrm"><i class="bi bi-plug me-1"></i>Проверить соединение</button>
            @endif
            <span id="externalCrmTestState" class="align-self-center small text-muted"></span>
          </div>
        </form>
        @if($remoteError)
          <div class="alert alert-warning mt-3 mb-0"><i class="bi bi-exclamation-triangle me-1"></i>{{ $remoteError }}</div>
        @endif
      </div>
    </div>
  </div>

  <div class="col-12">
    <div class="card border-0 shadow-sm">
      <div class="card-header bg-white">
        <b>Сопоставление отделов</b>
        <div class="small text-muted">Наш отдел = удалённый цех. Получателя задачи можно выбрать отдельно; начальник цеха подставляется автоматически, если он назначен во внешней CRM.</div>
      </div>
      <div class="card-body p-0">
        @if(!$tokenConfigured || !$baseUrl)
          <div class="p-4 text-muted">Сначала настройте подключение к внешней CRM.</div>
        @elseif($remoteError)
          <div class="p-4 text-muted">Исправьте подключение, чтобы загрузить цеха и типы задач.</div>
        @else
          <form method="POST" action="{{ route('external-crm.mappings') }}">
            @csrf @method('PATCH')
            <div class="table-responsive">
              <table class="table align-middle mb-0">
                <thead><tr><th>Наш отдел</th><th>Удалённый цех</th><th>Получатель / начальник</th><th>Тип задачи</th><th>Состояние</th></tr></thead>
                <tbody>
                @forelse($departments as $department)
                  @php $mapping = $mappings->get($department->id); @endphp
                  <tr>
                    <td><b>{{ $department->name }}</b>@if($department->short_name)<div class="small text-muted">{{ $department->short_name }}</div>@endif</td>
                    <td style="min-width:260px">
                      <select class="form-select workshop-select" name="mappings[{{ $department->id }}][workshop_id]" data-row="{{ $department->id }}">
                        <option value="">Не отправлять</option>
                        @foreach($workshops as $workshop)
                          <option value="{{ $workshop['id'] }}" data-manager-id="{{ $workshop['manager']['id'] ?? '' }}" data-manager-name="{{ $workshop['manager']['full_name'] ?? '' }}" @selected((int)($mapping->external_workshop_id ?? 0)===(int)$workshop['id'])>
                            {{ $workshop['name'] }}
                          </option>
                        @endforeach
                      </select>
                    </td>
                    <td style="min-width:280px">
                      <select class="form-select manager-select" name="mappings[{{ $department->id }}][manager_user_id]" data-row="{{ $department->id }}">
                        <option value="">Выберите получателя...</option>
                        @foreach($remoteUsers as $remoteUser)
                          <option value="{{ $remoteUser['id'] }}" @selected((int)($mapping->external_manager_user_id ?? 0)===(int)$remoteUser['id'])>
                            {{ $remoteUser['full_name'] ?? ('Пользователь #'.$remoteUser['id']) }}@if(!empty($remoteUser['department']['name'])) — {{ $remoteUser['department']['name'] }}@endif
                          </option>
                        @endforeach
                      </select>
                      <div class="form-text manager-hint" data-row="{{ $department->id }}"></div>
                    </td>
                    <td style="min-width:240px">
                      <select class="form-select" name="mappings[{{ $department->id }}][task_type_id]">
                        <option value="">Выберите тип...</option>
                        @foreach($taskTypes as $type)
                          <option value="{{ $type['id'] }}" @selected((int)($mapping->external_task_type_id ?? 0)===(int)$type['id'])>{{ $type['name'] }}</option>
                        @endforeach
                      </select>
                    </td>
                    <td>
                      @if($mapping)
                        <span class="badge text-bg-success">Настроено</span>
                      @else
                        <span class="badge text-bg-light border">Не настроено</span>
                      @endif
                    </td>
                  </tr>
                @empty
                  <tr><td colspan="5" class="text-center text-muted py-4">В организации нет активных отделов.</td></tr>
                @endforelse
                </tbody>
              </table>
            </div>
            <div class="p-3 border-top d-flex gap-2 align-items-center">
              <button class="btn btn-primary"><i class="bi bi-check2 me-1"></i>Сохранить сопоставление</button>
              <span class="small text-muted">Если выбрать «Не отправлять», сопоставление для отдела будет удалено.</span>
            </div>
          </form>
        @endif
      </div>
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script>
document.querySelectorAll('.workshop-select').forEach(select=>{
  const update=(force=false)=>{
    const option=select.options[select.selectedIndex];
    const row=select.dataset.row;
    const managerSelect=document.querySelector('.manager-select[data-row="'+row+'"]');
    const hint=document.querySelector('.manager-hint[data-row="'+row+'"]');
    const managerId=option?.dataset.managerId||'';
    const managerName=option?.dataset.managerName||'';

    if(hint){
      hint.textContent=managerId
        ? 'Начальник цеха во внешней CRM: '+managerName
        : 'У цеха начальник не назначен — выберите получателя вручную.';
    }

    if(managerSelect && managerId && (force || !managerSelect.value)){
      const exists=[...managerSelect.options].some(x=>x.value===String(managerId));
      if(exists) managerSelect.value=String(managerId);
    }

    if(managerSelect && !select.value){
      managerSelect.value='';
      managerSelect.disabled=true;
    }else if(managerSelect){
      managerSelect.disabled=false;
    }
  };
  select.addEventListener('change',()=>update(true));
  update(false);
});

document.getElementById('testExternalCrm')?.addEventListener('click',async function(){
  const state=document.getElementById('externalCrmTestState');
  state.textContent='Проверка...';
  this.disabled=true;
  try{
    const response=await fetch('{{ route('external-crm.test') }}',{
      method:'POST',
      headers:{
        'Accept':'application/json',
        'X-Requested-With':'XMLHttpRequest',
        'X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]')?.content||''
      }
    });
    const data=await response.json();
    if(!response.ok) throw new Error(data.message||'Ошибка подключения');
    state.innerHTML='<span class="text-success"><i class="bi bi-check-circle me-1"></i>Соединение установлено. Цехов: '+data.workshops_count+', пользователей: '+(data.users_count??0)+', типов задач: '+data.task_types_count+'</span>';
  }catch(e){
    state.innerHTML='<span class="text-danger"><i class="bi bi-x-circle me-1"></i>'+String(e.message||e)+'</span>';
  }finally{this.disabled=false}
});
</script>
@endpush

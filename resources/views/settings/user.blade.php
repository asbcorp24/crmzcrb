@extends('layouts.app')
@section('title','Мои настройки — CRM')
@section('header','Мои настройки')
@push('styles')
<style>
.theme-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:1rem}.theme-option{position:relative}.theme-option input{position:absolute;opacity:0;pointer-events:none}.theme-card{border:2px solid transparent;border-radius:16px;padding:14px;cursor:pointer;transition:.15s ease;background:var(--bs-body-bg,#fff)}.theme-option input:checked+.theme-card{border-color:var(--bs-primary,#0d6efd);box-shadow:0 0 0 3px rgba(13,110,253,.12)}.theme-preview{height:118px;border-radius:12px;overflow:hidden;border:1px solid rgba(0,0,0,.08);display:grid;grid-template-columns:52px 1fr}.theme-preview .tp-side{padding:10px}.theme-preview .tp-main{padding:10px}.tp-line{height:8px;border-radius:5px;margin-bottom:8px;opacity:.9}.tp-card{height:28px;border-radius:7px;margin-top:8px}.preview-light{background:#f4f6f9}.preview-light .tp-side,.preview-light .tp-card{background:#fff}.preview-light .tp-line{background:#0d6efd}.preview-dark{background:#111827}.preview-dark .tp-side,.preview-dark .tp-card{background:#1f2937}.preview-dark .tp-line{background:#60a5fa}.preview-blue{background:#eef6ff}.preview-blue .tp-side,.preview-blue .tp-card{background:#fff}.preview-blue .tp-line{background:#2563eb}.preview-green{background:#f0fdf4}.preview-green .tp-side,.preview-green .tp-card{background:#fff}.preview-green .tp-line{background:#16a34a}.preview-purple{background:#faf5ff}.preview-purple .tp-side,.preview-purple .tp-card{background:#fff}.preview-purple .tp-line{background:#9333ea}.preview-contrast{background:#fff}.preview-contrast .tp-side{background:#111}.preview-contrast .tp-card{background:#fff;border:2px solid #111}.preview-contrast .tp-line{background:#000}
</style>
@endpush
@section('content')
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
<div class="row justify-content-center"><div class="col-xxl-10">
<div class="card border-0 shadow-sm"><div class="card-header bg-white"><b>Оформление интерфейса</b><div class="small text-muted">Настройка сохраняется только для вашей учётной записи.</div></div><div class="card-body p-lg-4">
<form method="POST" action="{{ route('user-settings.update') }}">@csrf @method('PATCH')
<div class="theme-grid">
@foreach($themes as $key=>$theme)
<label class="theme-option">
<input type="radio" name="ui_theme" value="{{ $key }}" {{ $currentTheme===$key?'checked':'' }}>
<div class="theme-card">
<div class="theme-preview preview-{{ $key }}"><div class="tp-side"><div class="tp-line"></div><div class="tp-line"></div><div class="tp-line"></div></div><div class="tp-main"><div class="tp-line" style="width:55%"></div><div class="tp-card"></div><div class="tp-card"></div></div></div>
<div class="d-flex align-items-center mt-3"><div><div class="fw-semibold">{{ $theme['name'] }}</div><div class="small text-muted">{{ $theme['description'] }}</div></div>@if($currentTheme===$key)<i class="bi bi-check-circle-fill text-primary ms-auto fs-5"></i>@endif</div>
</div>
</label>
@endforeach
</div>
<div class="d-flex gap-2 mt-4"><button class="btn btn-primary"><i class="bi bi-palette me-1"></i>Сохранить тему</button><a href="{{ route('dashboard') }}" class="btn btn-light">На главную</a></div>
</form>
</div></div>
</div></div>
@endsection
@push('scripts')
<script>
document.querySelectorAll('input[name="ui_theme"]').forEach(function(el){el.addEventListener('change',function(){localStorage.setItem('crm-ui-theme',this.value);document.documentElement.setAttribute('data-crm-theme',this.value);});});
</script>
@endpush

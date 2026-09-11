@extends('layouts.app')
@section('title','Настройки — CRM')
@section('header','Настройки')
@section('content')
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
<div class="row justify-content-center"><div class="col-xl-8"><div class="card border-0 shadow-sm"><div class="card-header bg-white"><b>Настройки входа сотрудников</b><div class="small text-muted">Организация: {{ $organization->display_name }}</div></div><div class="card-body">
<form method="POST" action="{{ route('settings.update') }}">@csrf @method('PATCH')
<div class="mb-3"><label class="form-label fw-semibold">Способ входа</label>
<div class="form-check border rounded p-3 mb-2"><input class="form-check-input ms-0 me-2" type="radio" name="login_mode" id="login_email" value="email" {{ $loginMode==='email'?'checked':'' }}><label class="form-check-label" for="login_email"><b>Email + пароль</b><div class="small text-muted">Пользователь вручную вводит свой email.</div></label></div>
<div class="form-check border rounded p-3"><input class="form-check-input ms-0 me-2" type="radio" name="login_mode" id="login_fio" value="fio" {{ $loginMode==='fio'?'checked':'' }}><label class="form-check-label" for="login_fio"><b>Выбор ФИО + пароль</b><div class="small text-muted">После ввода кода организации сотрудник выбирает себя из списка и вводит пароль.</div></label></div>
</div>
<div class="alert alert-info"><i class="bi bi-info-circle me-1"></i>Настройка действует только для сотрудников этой организации. Вход суперадминистратора работает отдельно через логин и пароль из <code>.env</code>.</div>
<button class="btn btn-primary"><i class="bi bi-check2 me-1"></i>Сохранить</button>
</form>
</div></div></div></div>
@endsection

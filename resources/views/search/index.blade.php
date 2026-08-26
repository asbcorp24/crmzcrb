@extends('layouts.app')
@section('title','Поиск — CRM ЗЦРБ')
@section('header','Глобальный поиск')
@section('content')
<form class="mb-4" method="GET" action="{{ route('search.page') }}">
  <div class="input-group input-group-lg"><span class="input-group-text bg-white"><i class="bi bi-search"></i></span><input name="q" value="{{ $q }}" class="form-control" placeholder="Сотрудник, задача, план, протокол, комментарий, подразделение..." autofocus><button class="btn btn-primary">Найти</button></div>
</form>
@if($q==='')
<div class="card border-0 shadow-sm"><div class="card-body text-center py-5 text-muted"><i class="bi bi-search fs-1"></i><div class="mt-2">Введите минимум 2 символа для поиска по доступным данным CRM.</div></div></div>
@else
<div class="d-flex align-items-center mb-3"><h5 class="mb-0">Результаты по «{{ $q }}»</h5><span class="badge text-bg-light border ms-2">{{ count($results) }}</span></div>
<div class="list-group shadow-sm">
@forelse($results as $r)
<a href="{{ $r['url'] }}" class="list-group-item list-group-item-action py-3">
  <div class="d-flex gap-3 align-items-start"><div class="fs-4 text-primary"><i class="bi {{ $r['icon'] }}"></i></div><div class="flex-grow-1"><div class="d-flex gap-2 align-items-center"><span class="fw-semibold">{{ $r['title'] }}</span><span class="badge text-bg-light border">{{ $r['type'] }}</span></div>@if($r['subtitle'])<div class="small text-muted mt-1">{{ $r['subtitle'] }}</div>@endif</div><i class="bi bi-chevron-right text-muted"></i></div>
</a>
@empty
<div class="list-group-item text-center py-5 text-muted">Ничего не найдено в доступной вам части CRM.</div>
@endforelse
</div>
@endif
@endsection

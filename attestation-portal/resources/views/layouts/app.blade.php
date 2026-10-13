<!doctype html>
<html lang="ru">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>@yield('title','Аттестация ЗЦРБ')</title>
<link href="{{ asset('assets/vendor/bootstrap/bootstrap.min.css') }}" rel="stylesheet">
<link href="{{ asset('assets/css/icons.css') }}" rel="stylesheet">
<link href="{{ asset('assets/css/app.css') }}" rel="stylesheet">
</head>
<body class="tech-grid">
<nav class="navbar navbar-expand-lg navbar-dark sticky-top"><div class="container-fluid px-3 px-lg-4">
<a class="navbar-brand d-flex align-items-center gap-2" href="{{ route('dashboard') }}"><span class="node"></span><span>АТТЕСТАЦИЯ // ЗЦРБ</span></a>
<button class="navbar-toggler" data-bs-toggle="collapse" data-bs-target="#mainNav"><span class="navbar-toggler-icon"></span></button>
<div class="collapse navbar-collapse" id="mainNav"><div class="navbar-nav ms-auto align-items-lg-center gap-lg-2">
@if(auth()->user()?->isSuperAdmin())
<a class="nav-link" href="{{ route('admin.workshops') }}">Цеха</a>
<a class="nav-link" href="{{ route('admin.users') }}">Сотрудники</a>
<a class="nav-link" href="{{ route('admin.tests.index') }}">Тесты</a>
<a class="nav-link" href="{{ route('admin.attestation') }}">Аттестация</a>
<a class="nav-link" href="{{ route('admin.analytics') }}">Аналитика</a>
@else
<a class="nav-link" href="{{ route('dashboard') }}">Мои задания</a>
<a class="nav-link" href="{{ route('attestation.index') }}">Аттестация</a>
@endif
<span class="nav-link text-muted">{{ auth()->user()?->full_name }}</span>
<form method="post" action="{{ route('logout') }}">@csrf<button class="btn btn-sm btn-outline-light">Выйти</button></form>
</div></div></div></nav>
<main class="container-fluid px-3 px-lg-4 py-4">
@if(session('success'))<div class="alert alert-success border-0">{{ session('success') }}</div>@endif
@if($errors->any())<div class="alert alert-danger"><b>Проверьте данные:</b><ul class="mb-0 mt-2">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif
@yield('content')
</main>
<script src="{{ asset('assets/vendor/bootstrap/bootstrap.bundle.min.js') }}"></script>
<script src="{{ asset('assets/js/app.js') }}"></script>
<script src="{{ asset('assets/js/charts.js') }}"></script>
@stack('scripts')
</body></html>

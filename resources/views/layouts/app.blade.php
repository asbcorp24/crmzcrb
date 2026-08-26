<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}"><title>@yield('title','CRM ЗЦРБ')</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>body{background:#f4f6f9}.sidebar{width:260px;min-height:100vh;background:#fff;border-right:1px solid #e7ebef}.sidebar a{color:#344054;text-decoration:none}.sidebar .nav-link.active,.sidebar .nav-link:hover{background:#eef6ff;color:#0d6efd}.content{min-width:0}.stat-card{border:0;box-shadow:0 1px 3px rgba(16,24,40,.08)}.task-overdue{border-left:4px solid #dc3545}</style>
    @stack('styles')
</head>
<body><div class="d-flex">
<aside class="sidebar p-3 d-none d-lg-block"><div class="fw-bold fs-5 mb-4"><i class="bi bi-hospital me-2 text-primary"></i>CRM ЗЦРБ</div>
<nav class="nav flex-column gap-1"><a class="nav-link active rounded" href="{{ route('dashboard') }}"><i class="bi bi-speedometer2 me-2"></i>Главная</a><a class="nav-link rounded" href="#"><i class="bi bi-check2-square me-2"></i>Задачи</a><a class="nav-link rounded" href="#"><i class="bi bi-calendar3 me-2"></i>Планы</a><a class="nav-link rounded" href="#"><i class="bi bi-people me-2"></i>Сотрудники</a><a class="nav-link rounded" href="#"><i class="bi bi-diagram-3 me-2"></i>Подразделения</a><a class="nav-link rounded" href="#"><i class="bi bi-bar-chart me-2"></i>Контроль</a></nav></aside>
<main class="content flex-grow-1"><nav class="navbar bg-white border-bottom px-4"><span class="navbar-brand mb-0 h1">@yield('header','CRM ЗЦРБ')</span><span class="ms-auto">{{ auth()->user()->full_name ?? '' }}</span></nav><div class="container-fluid p-4">@yield('content')</div></main>
</div><script src="https://code.jquery.com/jquery-3.7.1.min.js"></script><script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script><script>$.ajaxSetup({headers:{'X-CSRF-TOKEN':$('meta[name="csrf-token"]').attr('content')}});</script>@stack('scripts')</body></html>

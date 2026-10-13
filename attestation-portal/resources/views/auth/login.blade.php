<!doctype html><html lang="ru"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Вход — Аттестация ЗЦРБ</title><link href="{{ asset('assets/vendor/bootstrap/bootstrap.min.css') }}" rel="stylesheet"><link href="{{ asset('assets/css/app.css') }}" rel="stylesheet"></head>
<body class="tech-grid d-flex align-items-center justify-content-center p-3"><div class="card border-0" style="width:min(440px,100%)"><div class="card-body p-4 p-lg-5">
<div class="circuit-title mb-2">SYSTEM ACCESS</div><h2 class="mb-1">Аттестация ЗЦРБ</h2><div class="text-muted mb-4">Вход сотрудника в систему тестирования</div>
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
<form method="post" action="{{ route('login.submit') }}">@csrf
<div class="mb-3"><label class="form-label">Логин</label><input name="login" class="form-control form-control-lg" required autofocus value="{{ old('login') }}"></div>
<div class="mb-3"><label class="form-label">Пароль</label><input name="password" type="password" class="form-control form-control-lg" required></div>
<div class="form-check mb-4"><input class="form-check-input" name="remember" value="1" type="checkbox" id="remember"><label class="form-check-label" for="remember">Запомнить вход</label></div>
<button class="btn btn-primary btn-lg w-100">Войти</button></form>
</div></div></body></html>

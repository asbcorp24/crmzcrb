<div class="card border-0 shadow-sm mb-3"><div class="card-body py-2"><div class="d-flex flex-wrap gap-2">
<a class="btn btn-sm {{ request()->routeIs('analytics.overview')?'btn-primary':'btn-outline-secondary' }}" href="{{ route('analytics.overview') }}"><i class="bi bi-grid-1x2 me-1"></i>Обзор</a>
<a class="btn btn-sm {{ request()->routeIs('analytics.tasks')?'btn-primary':'btn-outline-secondary' }}" href="{{ route('analytics.tasks') }}"><i class="bi bi-check2-square me-1"></i>Задачи и контроль</a>
<a class="btn btn-sm {{ request()->routeIs('analytics.plans')?'btn-primary':'btn-outline-secondary' }}" href="{{ route('analytics.plans') }}"><i class="bi bi-calendar3 me-1"></i>Планы</a>
<a class="btn btn-sm {{ request()->routeIs('analytics.employees')?'btn-primary':'btn-outline-secondary' }}" href="{{ route('analytics.employees') }}"><i class="bi bi-people me-1"></i>Сотрудники</a>
<a class="btn btn-sm {{ request()->routeIs('analytics.departments')?'btn-primary':'btn-outline-secondary' }}" href="{{ route('analytics.departments') }}"><i class="bi bi-diagram-3 me-1"></i>Подразделения</a>
<a class="btn btn-sm {{ request()->routeIs('analytics.questionnaires')?'btn-primary':'btn-outline-secondary' }}" href="{{ route('analytics.questionnaires') }}"><i class="bi bi-ui-checks-grid me-1"></i>Анкеты</a>
<a class="btn btn-sm {{ request()->routeIs('analytics.attestation')?'btn-primary':'btn-outline-secondary' }}" href="{{ route('analytics.attestation') }}"><i class="bi bi-person-check me-1"></i>Аттестация</a>
</div></div></div>

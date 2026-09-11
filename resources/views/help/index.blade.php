@extends('layouts.app')
@section('title','Помощь — CRM ЗЦРБ')
@section('header','Помощь и руководство пользователя')

@push('styles')
<style>
.help-nav{position:sticky;top:1rem}.help-section{scroll-margin-top:90px}.help-card{border:0;box-shadow:0 1px 3px rgba(16,24,40,.08)}.help-step{display:flex;gap:.75rem;margin-bottom:.65rem}.help-step .n{width:28px;height:28px;border-radius:50%;background:#0d6efd;color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;flex:0 0 28px}.help-tip{border-left:4px solid #0d6efd;background:#f5f9ff}.help-warn{border-left:4px solid #ffc107;background:#fffaf0}.help-example{background:#f8f9fa;border:1px dashed #cfd4da;border-radius:.5rem;padding:1rem}.role-table td,.role-table th{vertical-align:middle}.help-search-hit{outline:3px solid rgba(13,110,253,.15)}
</style>
@endpush

@section('content')
<div class="row g-4">
<div class="col-lg-3">
<div class="card help-card help-nav"><div class="card-body">
<div class="input-group mb-3"><span class="input-group-text bg-white"><i class="bi bi-search"></i></span><input id="helpSearch" class="form-control" placeholder="Найти в справке..."></div>
<div class="list-group list-group-flush small" id="helpNav">
<a class="list-group-item list-group-item-action" href="#start"><i class="bi bi-play-circle me-2"></i>С чего начать</a>
<a class="list-group-item list-group-item-action" href="#dashboard"><i class="bi bi-speedometer2 me-2"></i>Главная</a>
<a class="list-group-item list-group-item-action" href="#tasks"><i class="bi bi-check2-square me-2"></i>Задачи</a>
<a class="list-group-item list-group-item-action" href="#plans"><i class="bi bi-calendar3 me-2"></i>Планы</a>
<a class="list-group-item list-group-item-action" href="#calendar"><i class="bi bi-calendar-week me-2"></i>Календарь</a>
<a class="list-group-item list-group-item-action" href="#employees"><i class="bi bi-people me-2"></i>Сотрудники</a>
<a class="list-group-item list-group-item-action" href="#departments"><i class="bi bi-diagram-3 me-2"></i>Подразделения</a>
<a class="list-group-item list-group-item-action" href="#staffing"><i class="bi bi-person-workspace me-2"></i>Штатное расписание</a>
<a class="list-group-item list-group-item-action" href="#meetings"><i class="bi bi-journal-check me-2"></i>Совещания</a>
<a class="list-group-item list-group-item-action" href="#availability"><i class="bi bi-calendar-x me-2"></i>Отсутствия</a>
<a class="list-group-item list-group-item-action" href="#control"><i class="bi bi-bar-chart me-2"></i>Контроль</a>
<a class="list-group-item list-group-item-action" href="#questionnaires"><i class="bi bi-ui-checks-grid me-2"></i>Анкетирование</a>
<a class="list-group-item list-group-item-action" href="#attestation"><i class="bi bi-person-check me-2"></i>Аттестация</a>
<a class="list-group-item list-group-item-action" href="#analytics"><i class="bi bi-graph-up-arrow me-2"></i>Аналитика</a>
<a class="list-group-item list-group-item-action" href="#settings"><i class="bi bi-gear me-2"></i>Настройки организации</a>
<a class="list-group-item list-group-item-action" href="#reports"><i class="bi bi-file-earmark-bar-graph me-2"></i>Отчёты</a>
<a class="list-group-item list-group-item-action" href="#notifications"><i class="bi bi-bell me-2"></i>Уведомления</a>
<a class="list-group-item list-group-item-action" href="#roles"><i class="bi bi-shield-check me-2"></i>Роли и права</a>
<a class="list-group-item list-group-item-action" href="#faq"><i class="bi bi-question-circle me-2"></i>Частые вопросы</a>
</div></div></div>
</div>

<div class="col-lg-9" id="helpContent">
<section id="start" class="help-section card help-card mb-4"><div class="card-body p-4">
<h3><i class="bi bi-play-circle text-primary me-2"></i>С чего начать</h3>
<p>CRM объединяет задачи, планы, сотрудников, подразделения, штатное расписание, совещания, отсутствия, анкетирование, аттестацию и аналитику.</p>
<div class="help-step"><div class="n">1</div><div>Администратор создаёт структуру организации и сотрудников.</div></div>
<div class="help-step"><div class="n">2</div><div>Назначаются руководители, должности и роли.</div></div>
<div class="help-step"><div class="n">3</div><div>Руководители создают задачи, планы и контролируют сроки.</div></div>
<div class="help-step"><div class="n">4</div><div>Кадровые процессы ведутся через сотрудников, штатное расписание, анкеты и аттестацию.</div></div>
<div class="help-step"><div class="n">5</div><div>Результаты анализируются в разделе <b>Аналитика</b>.</div></div>
</div></section>

<section id="dashboard" class="help-section card help-card mb-4"><div class="card-body p-4">
<h3><i class="bi bi-speedometer2 text-primary me-2"></i>Главная</h3>
<p>Главная показывает рабочий 360° пользователя: открытые задачи, просрочки, задачи на проверке, ближайшие сроки и планы.</p>
<p>Для руководителя дополнительно выводится сводная аналитика по подразделениям и динамике задач. Кнопка <b>Вся аналитика</b> открывает полный BI-раздел.</p>
</div></section>

<section id="tasks" class="help-section card help-card mb-4"><div class="card-body p-4">
<h3><i class="bi bi-check2-square text-primary me-2"></i>Задачи</h3>
<p>Задача содержит исполнителя, автора, срок, приоритет, прогресс, результат, чек-лист, комментарии, файлы и историю изменений.</p>
<ul><li><b>Новая</b> — работа ещё не начата.</li><li><b>В работе</b> — исполнитель работает.</li><li><b>На проверке</b> — результат отправлен руководителю.</li><li><b>Выполнено</b> — результат принят.</li><li><b>Отменено</b> — задача закрыта без выполнения.</li></ul>
<p>Для просроченной задачи можно зафиксировать причину. Все переносы срока и делегирования сохраняются в истории.</p>
</div></section>

<section id="plans" class="help-section card help-card mb-4"><div class="card-body p-4">
<h3><i class="bi bi-calendar3 text-primary me-2"></i>Планы</h3>
<p>План объединяет задачи сотрудника на период. Прогресс рассчитывается автоматически по связанным задачам.</p>
<div class="help-example">Пример: четыре задачи имеют прогресс 100%, 100%, 50% и 0%. Средний прогресс плана — около 63%.</div>
</div></section>

<section id="calendar" class="help-section card help-card mb-4"><div class="card-body p-4">
<h3><i class="bi bi-calendar-week text-primary me-2"></i>Календарь</h3>
<p>Календарь показывает сроки задач и окончание планов. Используйте фильтры по сотруднику, подразделению, типу и статусу.</p>
</div></section>

<section id="employees" class="help-section card help-card mb-4"><div class="card-body p-4">
<h3><i class="bi bi-people text-primary me-2"></i>Сотрудники</h3>
<p>В карточке сотрудника хранятся ФИО, должность, подразделение, руководитель, контакты, роль и кадровые назначения.</p>
<h5>Пароли</h5><p>При создании сотрудника администратор может сгенерировать пароль, показать и скопировать его. Для постоянного просмотра пароль хранится в отдельном зашифрованном поле, а для авторизации используется хэш.</p>
<div class="help-warn p-3"><b>Старые пароли:</b> если пароль был создан до появления зашифрованной копии, восстановить его из хэша нельзя. Нужно один раз установить новый пароль.</div>
<h5 class="mt-3">Способ входа</h5><p>В зависимости от настройки организации сотрудник входит либо по email и паролю, либо выбирает своё ФИО из списка и вводит пароль.</p>
</div></section>

<section id="departments" class="help-section card help-card mb-4"><div class="card-body p-4">
<h3><i class="bi bi-diagram-3 text-primary me-2"></i>Подразделения</h3>
<p>Подразделения образуют иерархию организации. Руководитель видит только доступную ему структуру, администратор — всю организацию.</p>
</div></section>

<section id="staffing" class="help-section card help-card mb-4"><div class="card-body p-4">
<h3><i class="bi bi-person-workspace text-primary me-2"></i>Штатное расписание</h3>
<p><b>Должность</b> — справочник. <b>Штатная позиция</b> — количество ставок в подразделении. <b>Назначение</b> — сотрудник и доля ставки.</p>
<p>CRM не позволяет занять больше ставок, чем предусмотрено штатной позицией.</p>
</div></section>

<section id="meetings" class="help-section card help-card mb-4"><div class="card-body p-4">
<h3><i class="bi bi-journal-check text-primary me-2"></i>Совещания</h3>
<p>Совещание содержит дату, председателя, секретаря, участников и поручения. Из пункта протокола можно формировать связанную задачу с исполнителем и сроком.</p>
</div></section>

<section id="availability" class="help-section card help-card mb-4"><div class="card-body p-4">
<h3><i class="bi bi-calendar-x text-primary me-2"></i>Отсутствия и замещения</h3>
<p>Фиксируются отпуск, больничный, командировка, обучение и другие отсутствия. На период отсутствия можно назначить заместителя и при необходимости передать задачи.</p>
</div></section>

<section id="control" class="help-section card help-card mb-4"><div class="card-body p-4">
<h3><i class="bi bi-bar-chart text-primary me-2"></i>Контроль</h3>
<p>Основной экран руководителя для ежедневной работы. В первую очередь проверяйте задачи на проверке, критические, просроченные, сроки на сегодня и задачи без движения.</p>
</div></section>

<section id="questionnaires" class="help-section card help-card mb-4"><div class="card-body p-4">
<h3><i class="bi bi-ui-checks-grid text-primary me-2"></i>Анкетирование</h3>
<p>Анкеты создаются в конструкторе и назначаются выбранным подразделениям. Поддерживаются один ответ, несколько ответов, шкала и текст.</p>
<p>Для шкальных компетенций можно дополнительно собирать ФИО сотрудников, у которых соответствующая компетенция проявляется сильнее.</p>
<h5>Аналитика анкет</h5><p>Отображаются количество ожидаемых и заполненных анкет, процент участия по подразделениям, распределение ответов, средние баллы компетенций, рейтинг компетенций и упоминания сотрудников.</p>
<p>Для анонимной анкеты ФИО в аналитике не отображается.</p>
</div></section>

<section id="attestation" class="help-section card help-card mb-4"><div class="card-body p-4">
<h3><i class="bi bi-person-check text-primary me-2"></i>Аттестация</h3>
<p>Аттестация состоит из двух независимых оценок: <b>рабочие связи сотрудников</b> и <b>оценка аттестационной комиссии</b>.</p>
<p>Если подразделений много, сначала выбирается подразделение, затем отображаются только его сотрудники. Это относится и к оцениванию, и к управленческому своду.</p>
<p>Оценка используется по шкале 1–4. Система считает среднее, количество и сумму оценок по каждому сотруднику.</p>
<h5>Комиссия</h5><p>При создании периода аттестации выбираются подразделения и члены комиссии. Каждый член комиссии заполняет свою оценку, после чего руководитель видит общий свод и процент готовности.</p>
</div></section>

<section id="analytics" class="help-section card help-card mb-4"><div class="card-body p-4">
<h3><i class="bi bi-graph-up-arrow text-primary me-2"></i>Аналитика</h3>
<p>Раздел <b>Аналитика</b> объединяет показатели всей доступной пользователю структуры. Графики работают локально и не требуют подключения к интернету.</p>
<div class="table-responsive"><table class="table table-sm"><thead><tr><th>Страница</th><th>Что показывает</th></tr></thead><tbody>
<tr><td>Обзор</td><td>Сводные KPI организации, динамика задач, сравнение подразделений.</td></tr>
<tr><td>Задачи и контроль</td><td>Открытые, выполненные, просроченные, на проверке, приоритеты, нагрузка сотрудников.</td></tr>
<tr><td>Планы</td><td>Активные, завершённые и просроченные планы, средний прогресс.</td></tr>
<tr><td>Сотрудники</td><td>Численность, роли, подразделения, приём сотрудников.</td></tr>
<tr><td>Подразделения</td><td>Сравнение загрузки и исполнения задач.</td></tr>
<tr><td>Штат</td><td>Плановые, занятые и вакантные ставки, укомплектованность.</td></tr>
<tr><td>Совещания</td><td>Количество совещаний, поручения и их связь с задачами.</td></tr>
<tr><td>Отсутствия</td><td>Отпуска, больничные, командировки, обучение и текущие отсутствия.</td></tr>
<tr><td>Анкеты</td><td>Заполняемость, компетенции и распределения ответов.</td></tr>
<tr><td>Аттестация</td><td>Рабочие связи, комиссия, рейтинги и готовность оценки.</td></tr>
</tbody></table></div>
<div class="help-tip p-3"><b>Доступ:</b> аналитика соблюдает права пользователя. Руководитель видит свою управленческую структуру, администратор — всю организацию.</div>
</div></section>

<section id="settings" class="help-section card help-card mb-4"><div class="card-body p-4">
<h3><i class="bi bi-gear text-primary me-2"></i>Настройки организации</h3>
<p>Раздел доступен администратору организации через пункт <b>Система → Настройки организации</b>.</p>
<h5>Способ входа сотрудников</h5><ul><li><b>Email + пароль</b> — пользователь вводит email вручную.</li><li><b>ФИО + пароль</b> — после ввода кода организации отображается список активных сотрудников.</li></ul>
<p>Настройка хранится отдельно для каждой организации.</p>
<h5>Суперадминистратор</h5><p>Логин и пароль суперадминистратора задаются через переменные <code>SUPERADMIN_LOGIN</code> и <code>SUPERADMIN_PASSWORD</code> в <code>.env</code>. Код организации при входе суперадминистратором не указывается.</p>
</div></section>

<section id="reports" class="help-section card help-card mb-4"><div class="card-body p-4">
<h3><i class="bi bi-file-earmark-bar-graph text-primary me-2"></i>Отчёты</h3>
<p>Отчёты дают табличное представление исполнения задач по сотрудникам и подразделениям. Для визуального анализа используйте раздел <b>Аналитика</b>.</p>
</div></section>

<section id="notifications" class="help-section card help-card mb-4"><div class="card-body p-4">
<h3><i class="bi bi-bell text-primary me-2"></i>Уведомления</h3>
<p>Колокольчик показывает новые задачи, комментарии, проверку, просрочки, переносы, делегирование и другие события. Push-уведомления настраиваются в разделе «Push и офлайн».</p>
</div></section>

<section id="roles" class="help-section card help-card mb-4"><div class="card-body p-4">
<h3><i class="bi bi-shield-check text-primary me-2"></i>Роли и права</h3>
<div class="table-responsive"><table class="table role-table"><thead><tr><th>Возможность</th><th>Сотрудник</th><th>Руководитель</th><th>Отдел кадров</th><th>Администратор</th></tr></thead><tbody>
<tr><td>Свои задачи и планы</td><td>✓</td><td>✓</td><td>✓</td><td>✓</td></tr>
<tr><td>Управление подчинёнными</td><td>—</td><td>✓</td><td>кадровые функции</td><td>✓</td></tr>
<tr><td>Все сотрудники организации</td><td>—</td><td>—</td><td>✓</td><td>✓</td></tr>
<tr><td>Штатное расписание</td><td>свои назначения</td><td>своя структура</td><td>✓</td><td>✓</td></tr>
<tr><td>Анкеты и аттестация</td><td>участие</td><td>по доступу</td><td>✓</td><td>✓</td></tr>
<tr><td>Кадровая аналитика</td><td>—</td><td>по структуре</td><td>✓</td><td>✓</td></tr>
<tr><td>Настройки организации</td><td>—</td><td>—</td><td>—</td><td>✓</td></tr>
<tr><td>Создание администраторов</td><td>—</td><td>—</td><td>—</td><td>✓</td></tr>
</tbody></table></div>
<div class="help-warn p-3"><b>Важно:</b> роль «Отдел кадров» предназначена для кадровых операций и не должна автоматически давать системные права администратора.</div>
</div></section>

<section id="faq" class="help-section card help-card mb-4"><div class="card-body p-4">
<h3><i class="bi bi-question-circle text-primary me-2"></i>Частые вопросы</h3>
<div class="accordion" id="faqAccordion">
@php($faq=[
['Почему я не вижу сотрудника?','Проверьте роль, непосредственного руководителя и принадлежность к подразделению.'],
['Где находится полная аналитика?','Откройте пункт «Аналитика» в меню руководителя. Там есть отдельные страницы по основным разделам CRM.'],
['Почему нет старого пароля сотрудника?','Хэш пароля расшифровать нельзя. Если зашифрованная копия ранее не сохранялась, установите сотруднику новый пароль.'],
['Как включить вход по ФИО?','Администратор открывает «Настройки организации» и выбирает режим «Выбор ФИО + пароль».'],
['Почему в аттестации не все сотрудники сразу?','При большом количестве подразделений сначала выбирается подразделение, после чего отображаются только его сотрудники.'],
['Почему руководитель не видит всю организацию?','Права руководителя ограничены его управленческой веткой. Полный доступ имеет администратор и профильные кадровые функции — отдел кадров.'],
['Где смотреть графики анкет и аттестации?','В разделе «Аналитика → Анкеты» и «Аналитика → Аттестация».'],
])
@foreach($faq as $i=>$f)
<div class="accordion-item"><h2 class="accordion-header"><button class="accordion-button {{ $i?'collapsed':'' }}" type="button" data-bs-toggle="collapse" data-bs-target="#faq{{ $i }}">{{ $f[0] }}</button></h2><div id="faq{{ $i }}" class="accordion-collapse collapse {{ !$i?'show':'' }}" data-bs-parent="#faqAccordion"><div class="accordion-body">{{ $f[1] }}</div></div></div>
@endforeach
</div>
</div></section>
</div>
</div>
@endsection

@push('scripts')
<script>
(function(){
 const requested=@json($section);
 if(requested){setTimeout(()=>document.getElementById(requested)?.scrollIntoView({behavior:'smooth',block:'start'}),100)}
 $('#helpSearch').on('input',function(){const q=this.value.trim().toLowerCase();$('.help-section').removeClass('help-search-hit').show();if(!q)return;$('.help-section').each(function(){const hit=$(this).text().toLowerCase().includes(q);$(this).toggle(hit);if(hit)$(this).addClass('help-search-hit')})});
 $('#helpNav a').on('click',function(e){e.preventDefault();const id=$(this).attr('href');$(id)[0]?.scrollIntoView({behavior:'smooth',block:'start'});history.replaceState(null,'',id)});
})();
</script>
@endpush

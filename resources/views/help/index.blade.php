@extends('layouts.app')
@section('title','Помощь — CRM ЗЦРБ')
@section('header','Помощь и руководство пользователя')
@push('styles')
<style>
.help-nav{position:sticky;top:1rem}.help-section{scroll-margin-top:90px}.help-card{border:0;box-shadow:0 1px 3px rgba(16,24,40,.08)}.help-step{display:flex;gap:.75rem;margin-bottom:.65rem}.help-step .n{width:28px;height:28px;border-radius:50%;background:#0d6efd;color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;flex:0 0 28px}.help-tip{border-left:4px solid #0d6efd;background:#f5f9ff}.help-warn{border-left:4px solid #ffc107;background:#fffaf0}.help-example{background:#f8f9fa;border:1px dashed #cfd4da;border-radius:.5rem;padding:1rem}.role-table td,.role-table th{vertical-align:middle}.kbd{font-family:monospace;background:#212529;color:#fff;border-radius:.25rem;padding:.1rem .35rem}.help-search-hit{outline:3px solid rgba(13,110,253,.15)}
</style>
@endpush
@section('content')
<div class="row g-4">
  <div class="col-lg-3">
    <div class="card help-card help-nav"><div class="card-body">
      <div class="input-group mb-3"><span class="input-group-text bg-white"><i class="bi bi-search"></i></span><input id="helpSearch" class="form-control" placeholder="Найти в справке..."></div>
      <div class="list-group list-group-flush small" id="helpNav">
        <a class="list-group-item list-group-item-action" href="#start"><i class="bi bi-play-circle me-2"></i>С чего начать</a>
        <a class="list-group-item list-group-item-action" href="#dashboard"><i class="bi bi-speedometer2 me-2"></i>Главная / 360°</a>
        <a class="list-group-item list-group-item-action" href="#tasks"><i class="bi bi-check2-square me-2"></i>Задачи</a>
        <a class="list-group-item list-group-item-action" href="#plans"><i class="bi bi-calendar3 me-2"></i>Планы</a>
        <a class="list-group-item list-group-item-action" href="#calendar"><i class="bi bi-calendar-week me-2"></i>Календарь</a>
        <a class="list-group-item list-group-item-action" href="#employees"><i class="bi bi-people me-2"></i>Сотрудники</a>
        <a class="list-group-item list-group-item-action" href="#departments"><i class="bi bi-diagram-3 me-2"></i>Подразделения</a>
        <a class="list-group-item list-group-item-action" href="#meetings"><i class="bi bi-journal-check me-2"></i>Совещания</a>
        <a class="list-group-item list-group-item-action" href="#availability"><i class="bi bi-calendar-x me-2"></i>Отсутствия и замещение</a>
        <a class="list-group-item list-group-item-action" href="#templates"><i class="bi bi-repeat me-2"></i>Шаблоны задач</a>
        <a class="list-group-item list-group-item-action" href="#staffing"><i class="bi bi-person-workspace me-2"></i>Штатное расписание</a>
        <a class="list-group-item list-group-item-action" href="#control"><i class="bi bi-bar-chart me-2"></i>На контроле</a>
        <a class="list-group-item list-group-item-action" href="#reports"><i class="bi bi-file-earmark-bar-graph me-2"></i>Отчёты</a>
        <a class="list-group-item list-group-item-action" href="#search"><i class="bi bi-search me-2"></i>Поиск</a>
        <a class="list-group-item list-group-item-action" href="#notifications"><i class="bi bi-bell me-2"></i>Уведомления</a>
        <a class="list-group-item list-group-item-action" href="#roles"><i class="bi bi-shield-check me-2"></i>Роли и права</a>
        <a class="list-group-item list-group-item-action" href="#faq"><i class="bi bi-question-circle me-2"></i>Частые вопросы</a>
      </div>
    </div></div>
  </div>
  <div class="col-lg-9" id="helpContent">

    <section id="start" class="help-section card help-card mb-4"><div class="card-body p-4">
      <h3><i class="bi bi-play-circle text-primary me-2"></i>С чего начать</h3>
      <p class="text-muted">CRM помогает руководителю ставить поручения и планы, контролировать сроки и загрузку сотрудников, вести протоколы совещаний, штатное расписание, отсутствия и отчётность.</p>
      <h5>Быстрый сценарий руководителя</h5>
      <div class="help-step"><div class="n">1</div><div>Проверьте структуру в <b>Подразделения</b> и назначьте каждому сотруднику руководителя.</div></div>
      <div class="help-step"><div class="n">2</div><div>Проверьте сотрудников и должности в <b>Сотрудники</b> и <b>Штатное расписание</b>.</div></div>
      <div class="help-step"><div class="n">3</div><div>Создайте задачи вручную или сформируйте поручения из <b>Совещания</b>.</div></div>
      <div class="help-step"><div class="n">4</div><div>Для повторяющейся работы создайте <b>Шаблоны</b>.</div></div>
      <div class="help-step"><div class="n">5</div><div>Ежедневно проверяйте <b>На контроле</b>, а по итогам периода — <b>Отчёты</b>.</div></div>
      <div class="help-tip p-3 mt-3"><b>Подсказка.</b> Если не знаете, где искать объект, используйте глобальный поиск в верхней панели. Он ищет задачи, планы, сотрудников, подразделения, комментарии и совещания.</div>
    </div></section>

    <section id="dashboard" class="help-section card help-card mb-4"><div class="card-body p-4">
      <h3><i class="bi bi-speedometer2 text-primary me-2"></i>Главная и 360° сотрудника</h3>
      <p>Главная показывает текущий рабочий день сотрудника: активные задачи, сроки, просрочки, задачи на проверке и быстрые действия.</p>
      <h5>Что означают показатели</h5>
      <ul><li><b>Открытые</b> — все задачи кроме выполненных и отменённых.</li><li><b>Просроченные</b> — срок уже прошёл, а задача не закрыта.</li><li><b>На проверке</b> — сотрудник закончил работу и ждёт решения руководителя.</li><li><b>Прогресс</b> — процент выполнения задачи; при наличии чек-листа может рассчитываться по отмеченным пунктам.</li></ul>
      <div class="help-example"><b>Пример.</b> У сотрудника 8 открытых задач, 2 просрочены и 1 отправлена на проверку. Руководителю сначала стоит открыть просроченные и задачу на проверке, а затем оценить оставшуюся загрузку.</div>
      <div class="help-tip p-3 mt-3"><b>Совет сотруднику.</b> Не ждите конца дня: отмечайте чек-лист и прогресс по мере выполнения. Тогда руководитель видит реальную картину без дополнительных звонков.</div>
    </div></section>

    <section id="tasks" class="help-section card help-card mb-4"><div class="card-body p-4">
      <h3><i class="bi bi-check2-square text-primary me-2"></i>Задачи</h3>
      <p>Задача — основная единица контроля. Она имеет исполнителя, автора, приоритет, срок, прогресс, результат, чек-лист, комментарии, файлы и историю изменений.</p>
      <h5>Как поставить задачу</h5>
      <div class="help-step"><div class="n">1</div><div>Откройте <b>Задачи → Новая задача</b>.</div></div>
      <div class="help-step"><div class="n">2</div><div>Выберите исполнителя. Руководителю доступны сотрудники его управленческой ветки.</div></div>
      <div class="help-step"><div class="n">3</div><div>Сформулируйте результат в названии: лучше «Подготовить отчёт за август», чем «Отчёт».</div></div>
      <div class="help-step"><div class="n">4</div><div>Укажите описание, приоритет и срок. Если исполнитель отсутствует на дату срока, CRM предупредит и покажет заместителя.</div></div>
      <h5 class="mt-3">Статусы</h5>
      <div class="table-responsive"><table class="table table-sm"><thead><tr><th>Статус</th><th>Значение</th></tr></thead><tbody><tr><td>Новая</td><td>Работа ещё не начата.</td></tr><tr><td>В работе</td><td>Исполнитель приступил к выполнению.</td></tr><tr><td>На проверке</td><td>Исполнитель отправил результат руководителю.</td></tr><tr><td>Выполнено</td><td>Результат принят.</td></tr><tr><td>Отменено</td><td>Задача больше не требуется.</td></tr></tbody></table></div>
      <h5>Чек-лист</h5><p>Используйте его для задач, состоящих из нескольких обязательных шагов. Пока обязательные пункты не отмечены, задача не должна уходить на проверку.</p>
      <div class="help-example"><b>Пример чек-листа:</b><br>☑ Получить исходные данные<br>☑ Проверить показатели<br>☐ Согласовать с заведующим<br>☐ Загрузить итоговый файл</div>
      <h5 class="mt-3">Перенос срока</h5><p>При изменении срока CRM требует причину и сохраняет старый и новый срок в истории.</p>
      <div class="help-warn p-3"><b>Важно.</b> Не используйте перенос срока просто для «исчезновения просрочки». Причина должна объяснять управленческое решение: ожидание данных, изменение приоритета, зависимость от другого подразделения и т.п.</div>
      <h5 class="mt-3">Просрочка</h5><p>Исполнитель может указать причину просрочки. Руководитель видит её в контроле и отчётах.</p>
      <div class="help-example"><b>Пример:</b> Причина — «Ожидаю данные». Комментарий — «Финансовый отдел обещал выгрузку 28.08 до 12:00».</div>
      <h5 class="mt-3">Делегирование</h5><p>Руководитель может передать открытую задачу другому сотруднику своей ветки. История старого и нового исполнителя сохраняется.</p>
    </div></section>

    <section id="plans" class="help-section card help-card mb-4"><div class="card-body p-4">
      <h3><i class="bi bi-calendar3 text-primary me-2"></i>Планы</h3>
      <p>План объединяет несколько задач одного сотрудника на период: день, неделю, месяц, квартал, год или произвольный срок.</p>
      <p><b>Прогресс плана рассчитывается автоматически</b> по связанным задачам. Выполненная задача считается как 100%, остальные — по своему текущему прогрессу.</p>
      <div class="help-example"><b>Пример.</b> В плане 4 задачи: 100%, 100%, 50%, 0%. Итоговый прогресс плана = 62,5%, отображается округлённо.</div>
      <div class="help-tip p-3 mt-3"><b>Подсказка.</b> Если руководитель создал план сотруднику, сотрудник видит его и выполняет задачи, но не должен менять саму структуру руководительского плана.</div>
      <h5 class="mt-3">Когда использовать план, а когда задачу?</h5><p>Если результат один и конкретный — задача. Если это набор связанных результатов за период — план с несколькими задачами.</p>
    </div></section>

    <section id="calendar" class="help-section card help-card mb-4"><div class="card-body p-4">
      <h3><i class="bi bi-calendar-week text-primary me-2"></i>Календарь</h3>
      <p>Календарь показывает задачи по сроку и планы по дате окончания. Руководитель видит свою управленческую ветку, сотрудник — свои данные.</p>
      <h5>Фильтры</h5><ul><li>сотрудник;</li><li>подразделение;</li><li>тип: задачи / планы;</li><li>статус задачи.</li></ul>
      <div class="help-example"><b>Пример.</b> Выберите подразделение «Приёмное отделение» и период «Неделя», чтобы быстро увидеть, у кого какие контрольные сроки приходятся на ближайшие дни.</div>
      <div class="help-tip p-3 mt-3"><b>Совет руководителю.</b> Используйте календарь для планирования нагрузки, а раздел «На контроле» — для ежедневной работы с рисками.</div>
    </div></section>

    <section id="employees" class="help-section card help-card mb-4"><div class="card-body p-4">
      <h3><i class="bi bi-people text-primary me-2"></i>Сотрудники</h3>
      <p>Раздел содержит пользователей CRM, их подразделение, должность, непосредственного руководителя, роль, контакты и кадровые назначения.</p>
      <h5>Логин и пароль</h5><p>Логином является email. При создании пользователя администратор или уполномоченный руководитель задаёт его данные. Если пароль явно не указан, текущая система может использовать временный пароль по умолчанию — его следует сменить.</p>
      <h5>Руководитель</h5><p>Поле «Руководитель» определяет управленческое дерево. От него зависят доступ к задачам, календарю, отчётам, совещаниям и контролю.</p>
      <div class="help-warn p-3"><b>Важно.</b> Неправильный руководитель = неправильные права доступа. После кадрового перевода обязательно проверяйте это поле.</div>
      <h5 class="mt-3">360° сотрудника</h5><p>В профиле видно текущие задачи, завершённые работы, планы, показатели, историю событий и кадровых назначений.</p>
    </div></section>

    <section id="departments" class="help-section card help-card mb-4"><div class="card-body p-4">
      <h3><i class="bi bi-diagram-3 text-primary me-2"></i>Подразделения</h3>
      <p>Подразделения образуют дерево: администрация → служба → отделение → кабинет и т.д.</p>
      <div class="help-example"><b>Пример:</b><br>Администрация<br>└─ Медицинская служба<br>&nbsp;&nbsp;&nbsp;└─ Приёмное отделение<br>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;└─ Кабинет экстренного осмотра</div>
      <p class="mt-3">CRM блокирует циклы, когда подразделение пытаются сделать потомком самого себя.</p>
      <h5>360° подразделения</h5><p>Кнопка «360°» открывает сводную карточку: сотрудники, открытые и просроченные задачи, критические поручения, отсутствия, штатные ставки и вакансии.</p>
      <div class="help-tip p-3"><b>Подсказка.</b> 360° подразделения удобно использовать заведующему как рабочую стартовую страницу.</div>
    </div></section>

    <section id="meetings" class="help-section card help-card mb-4"><div class="card-body p-4">
      <h3><i class="bi bi-journal-check text-primary me-2"></i>Совещания и протоколы</h3>
      <p>Совещание хранит дату, место, председателя, секретаря, участников, заметки и поручения.</p>
      <h5>Как оформить протокол</h5>
      <div class="help-step"><div class="n">1</div><div>Создайте совещание и выберите участников.</div></div>
      <div class="help-step"><div class="n">2</div><div>Добавляйте каждое поручение отдельным пунктом.</div></div>
      <div class="help-step"><div class="n">3</div><div>Для пункта укажите исполнителя, срок и приоритет.</div></div>
      <div class="help-step"><div class="n">4</div><div>CRM автоматически создаст связанную задачу.</div></div>
      <div class="help-step"><div class="n">5</div><div>После фиксации протокола нажмите «Закрыть протокол».</div></div>
      <div class="help-example"><b>Пример пункта:</b> «До 30.08 подготовить предложения по сокращению времени ожидания в приёмном отделении». Исполнитель: Иванова М.С. Приоритет: высокий.</div>
      <div class="help-tip p-3 mt-3"><b>Важно.</b> Если связанная задача позже делегирована или срок перенесён, протокол показывает актуального исполнителя и актуальный срок.</div>
    </div></section>

    <section id="availability" class="help-section card help-card mb-4"><div class="card-body p-4">
      <h3><i class="bi bi-calendar-x text-primary me-2"></i>Отсутствия и замещение</h3>
      <p>Раздел используется для отпусков, больничных, командировок, обучения и других периодов отсутствия.</p>
      <h5>Замещение</h5><p>Для отсутствующего сотрудника можно назначить заместителя на период. При необходимости CRM может автоматически передать ему открытые задачи, срок которых попадает в период замещения.</p>
      <div class="help-example"><b>Пример.</b> Иванов в отпуске 01.09–14.09. Заместитель — Петров. Задача со сроком 05.09 может быть автоматически передана Петрову с сохранением истории делегирования.</div>
      <div class="help-warn p-3 mt-3"><b>Проверьте перед передачей.</b> Не каждая задача должна автоматически уходить заместителю. Для персональных или уже почти завершённых задач может быть правильнее изменить срок.</div>
    </div></section>

    <section id="templates" class="help-section card help-card mb-4"><div class="card-body p-4">
      <h3><i class="bi bi-repeat text-primary me-2"></i>Шаблоны и повторяющиеся задачи</h3>
      <p>Шаблон нужен для регулярной работы: ежедневной, еженедельной или ежемесячной.</p>
      <h5>Что задаётся в шаблоне</h5><ul><li>название и описание;</li><li>исполнитель;</li><li>приоритет;</li><li>через сколько дней наступает срок;</li><li>периодичность;</li><li>чек-лист;</li><li>дата следующего запуска.</li></ul>
      <div class="help-example"><b>Пример.</b> «Проверить журнал дефектов» — каждую пятницу, исполнитель старшая медсестра, срок в тот же день, чек-лист из 3 пунктов.</div>
      <div class="help-tip p-3 mt-3"><b>Подсказка.</b> Кнопка «Создать сейчас» позволяет проверить шаблон, не ожидая планового запуска.</div>
    </div></section>

    <section id="staffing" class="help-section card help-card mb-4"><div class="card-body p-4">
      <h3><i class="bi bi-person-workspace text-primary me-2"></i>Штатное расписание</h3>
      <p>Раздел разделяет <b>должность</b>, <b>штатную позицию</b> и <b>назначение сотрудника</b>.</p>
      <ul><li><b>Должность</b> — справочник: врач-хирург, медицинская сестра, инженер и т.д.</li><li><b>Штатная позиция</b> — сколько ставок этой должности предусмотрено в конкретном подразделении.</li><li><b>Назначение</b> — какой сотрудник занимает какую долю ставки и с какой даты.</li></ul>
      <div class="help-example"><b>Пример.</b> В приёмном отделении предусмотрено 2,0 ставки врача-хирурга. Иванов занимает 1,0, Петров — 0,5. Вакантно 0,5 ставки.</div>
      <div class="help-warn p-3 mt-3"><b>Защита CRM.</b> Нельзя назначить ставок больше, чем предусмотрено штатной позицией. При параллельном назначении применяется блокировка строки БД.</div>
      <p class="mt-3">Глобальный справочник должностей изменяет администратор. Руководитель работает со штатными строками и сотрудниками своей доступной структуры.</p>
    </div></section>

    <section id="control" class="help-section card help-card mb-4"><div class="card-body p-4">
      <h3><i class="bi bi-bar-chart text-primary me-2"></i>На контроле</h3>
      <p>Это основной управленческий экран руководителя.</p>
      <h5>Очереди контроля</h5><ul><li><b>Критические</b> — приоритет critical.</li><li><b>Просроченные</b> — срок прошёл.</li><li><b>На проверке</b> — требуют решения руководителя.</li><li><b>Сегодня / Завтра</b> — ближайшие сроки.</li><li><b>Без движения</b> — задачи, давно не изменявшиеся.</li></ul>
      <div class="help-tip p-3"><b>Рекомендуемый порядок утром:</b> 1) на проверке, 2) критические, 3) просроченные, 4) сроки сегодня, 5) задачи без движения.</div>
      <div class="help-example mt-3"><b>Пример решения.</b> Если просроченная задача имеет причину «Ожидаю данные», руководитель может проверить зависимость и поставить отдельное поручение подразделению, которое задерживает данные.</div>
    </div></section>

    <section id="reports" class="help-section card help-card mb-4"><div class="card-body p-4">
      <h3><i class="bi bi-file-earmark-bar-graph text-primary me-2"></i>Отчёты</h3>
      <p>Отчёты формируются по тому же доступному дереву сотрудников, которое видит руководитель.</p>
      <h5>Доступные отчёты</h5><ul><li>исполнение задач;</li><li>по сотрудникам;</li><li>по подразделениям;</li><li>исполнение протоколов совещаний.</li></ul>
      <h5>Фильтры</h5><p>Период, сотрудник, подразделение, статус.</p>
      <h5>Экспорт</h5><ul><li><b>Excel</b> — файл, открываемый Microsoft Excel.</li><li><b>CSV</b> — универсальный табличный формат.</li><li><b>PDF</b> — печатная форма, которую можно сохранить как PDF через браузер.</li></ul>
      <div class="help-example"><b>Пример.</b> Выберите «По подразделениям», период 01.08–31.08 и сравните процент исполнения и количество просрочек между подразделениями.</div>
    </div></section>

    <section id="search" class="help-section card help-card mb-4"><div class="card-body p-4">
      <h3><i class="bi bi-search text-primary me-2"></i>Глобальный поиск</h3>
      <p>Введите минимум 2 символа в поле поиска в верхней панели.</p>
      <p>Поиск охватывает сотрудников, задачи, планы, совещания, подразделения и комментарии. Результаты ограничиваются правами текущего пользователя.</p>
      <div class="help-example"><b>Примеры запросов:</b> «август», «Иванова», «ремонт», «приёмное», «отчёт ТФОМС».</div>
    </div></section>

    <section id="notifications" class="help-section card help-card mb-4"><div class="card-body p-4">
      <h3><i class="bi bi-bell text-primary me-2"></i>Уведомления</h3>
      <p>Колокольчик в верхней панели показывает события, требующие внимания.</p>
      <p>Примеры: новая задача, комментарий, задача отправлена на проверку, принята/возвращена, приближается срок, просрочка, делегирование.</p>
      <div class="help-tip p-3"><b>Подсказка.</b> Нажатие на уведомление помечает его прочитанным и открывает связанный объект.</div>
    </div></section>

    <section id="roles" class="help-section card help-card mb-4"><div class="card-body p-4">
      <h3><i class="bi bi-shield-check text-primary me-2"></i>Роли и права</h3>
      <div class="table-responsive"><table class="table role-table"><thead><tr><th>Действие</th><th>Сотрудник</th><th>Руководитель</th><th>Администратор</th></tr></thead><tbody>
        <tr><td>Свои задачи и планы</td><td>✓</td><td>✓</td><td>✓</td></tr>
        <tr><td>Задачи подчинённой ветки</td><td>—</td><td>✓</td><td>✓</td></tr>
        <tr><td>Контроль / отчёты</td><td>—</td><td>✓</td><td>✓</td></tr>
        <tr><td>Совещания</td><td>как участник</td><td>✓</td><td>✓</td></tr>
        <tr><td>Штатка своей структуры</td><td>просмотр своих назначений</td><td>✓</td><td>✓</td></tr>
        <tr><td>Глобальный справочник должностей</td><td>—</td><td>—</td><td>✓</td></tr>
        <tr><td>Все ветки организации</td><td>—</td><td>—</td><td>✓</td></tr>
      </tbody></table></div>
      <div class="help-warn p-3"><b>Ключевой принцип:</b> роль manager сама по себе не даёт доступ ко всей организации. Руководитель работает только внутри своей рекурсивной управленческой ветки.</div>
    </div></section>

    <section id="faq" class="help-section card help-card mb-4"><div class="card-body p-4">
      <h3><i class="bi bi-question-circle text-primary me-2"></i>Частые вопросы и проблемы</h3>
      <div class="accordion" id="faqAccordion">
        @php($faq=[
          ['Почему я не вижу сотрудника?','Проверьте, входит ли он в вашу управленческую ветку и правильно ли указан его непосредственный руководитель. Администратор видит всю структуру.'],
          ['Почему сотрудник не может изменить план?','Если план создал руководитель, сотрудник выполняет связанные задачи, но не меняет структуру самого плана.'],
          ['Почему задачу нельзя отправить на проверку?','Проверьте незавершённые пункты чек-листа и наличие результата/комментария о выполнении.'],
          ['Почему нельзя изменить срок?','Перенос срока доступен автору/уполномоченному руководителю и требует обязательную причину.'],
          ['Почему нельзя назначить сотрудника на ставку?','Возможно, свободной доли ставки недостаточно или у сотрудника уже есть активное назначение на эту штатную позицию.'],
          ['Почему в отчёте нет чужого подразделения?','Отчёты соблюдают управленческую иерархию. Руководителю доступны только его сотрудники и связанные подразделения.'],
          ['Что делать при отпуске сотрудника?','Создайте отсутствие, назначьте заместителя и решите, нужно ли автоматически передать задачи со сроком внутри периода отсутствия.'],
          ['Чем шаблон отличается от плана?','Шаблон автоматически создаёт повторяющиеся задачи. План объединяет конкретный набор задач на определённый период.'],
          ['Где посмотреть историю изменения срока или исполнителя?','Откройте карточку задачи: там хранится история событий, переносов сроков и делегирования.'],
          ['Как быстро проверить проблемы утром?','Откройте «На контроле»: сначала задачи на проверке, затем критические, просроченные, сроки сегодня и задачи без движения.'],
        ])
        @foreach($faq as $i=>$f)
        <div class="accordion-item"><h2 class="accordion-header"><button class="accordion-button {{ $i?'collapsed':'' }}" type="button" data-bs-toggle="collapse" data-bs-target="#faq{{ $i }}">{{ $f[0] }}</button></h2><div id="faq{{ $i }}" class="accordion-collapse collapse {{ !$i?'show':'' }}" data-bs-parent="#faqAccordion"><div class="accordion-body">{{ $f[1] }}</div></div></div>
        @endforeach
      </div>
      <div class="help-tip p-3 mt-3"><b>Если что-то «не видно» или «нельзя нажать»:</b> сначала проверьте роль пользователя, его руководителя и принадлежность к подразделению. Большинство ограничений CRM строятся именно на этих данных.</div>
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

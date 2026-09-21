(() => {
  if (location.pathname !== '/tasks') return;

  const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content || '';
  let options = null;
  let currentTaskId = null;
  let pendingCreateMeta = null;
  let batchTimer = null;

  const esc = value => String(value ?? '').replace(/[&<>'"]/g, ch => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[ch]));
  const toLocal = value => {
    if (!value) return '';
    const d = new Date(value);
    if (Number.isNaN(d.getTime())) return String(value).slice(0,16);
    const z = n => String(n).padStart(2,'0');
    return `${d.getFullYear()}-${z(d.getMonth()+1)}-${z(d.getDate())}T${z(d.getHours())}:${z(d.getMinutes())}`;
  };

  async function json(url, init = {}) {
    init.credentials = 'same-origin';
    init.headers = Object.assign({'Accept':'application/json','X-Requested-With':'XMLHttpRequest'}, init.headers || {});
    if (init.method && init.method !== 'GET') init.headers['X-CSRF-TOKEN'] = csrf();
    const r = await fetch(url, init);
    const data = await r.json().catch(() => ({}));
    if (!r.ok) throw new Error(data.message || 'Ошибка выполнения операции');
    return data;
  }

  const makeOptions = (rows, placeholder = 'Не выбрано') =>
    `<option value="">${esc(placeholder)}</option>` + (rows || []).map(x => `<option value="${x.id}">${esc(x.name)}</option>`).join('');

  async function loadOptions() {
    if (options) return options;
    options = await json('/ajax/task-details/options');
    return options;
  }

  function customerOptions(type, selected = '') {
    const rows = type === 'organization' ? (options?.organizations || []) : type === 'department' ? (options?.departments || []) : [];
    const select = document.getElementById('taskCustomerId');
    if (!select) return;
    select.innerHTML = makeOptions(rows, type ? 'Выберите...' : 'Сначала выберите тип заказчика');
    select.disabled = !type;
    if (selected) select.value = String(selected);
  }

  let referenceTarget = null;
  let referenceType = null;

  function ensureReferenceModal() {
    if (document.getElementById('taskReferenceModal')) return;
    const modal = document.createElement('div');
    modal.className = 'modal fade';
    modal.id = 'taskReferenceModal';
    modal.tabIndex = -1;
    modal.innerHTML = `
      <div class="modal-dialog"><div class="modal-content">
        <div class="modal-header"><h5 class="modal-title" id="taskReferenceModalTitle">Добавить запись</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
          <div id="taskReferenceError" class="alert alert-danger d-none"></div>
          <div class="mb-3"><label class="form-label">Наименование</label><input id="taskReferenceName" class="form-control" maxlength="255"></div>
          <div class="mb-3"><label class="form-label">Код <span class="text-muted">(необязательно)</span></label><input id="taskReferenceCode" class="form-control" maxlength="80"></div>
          <div id="taskReferenceStatusFields" class="d-none">
            <div class="mb-3"><label class="form-label">Системный статус</label><select id="taskReferenceSystemKey" class="form-select">
              <option value="new">Новая</option><option value="in_progress">В работе</option><option value="review">На проверке</option><option value="completed">Выполнена</option><option value="cancelled">Отменена</option>
            </select></div>
            <div class="mb-3"><label class="form-label">Цвет</label><input id="taskReferenceColor" type="color" class="form-control form-control-color" value="#0d6efd"></div>
          </div>
          <div class="mb-0"><label class="form-label">Примечание <span class="text-muted">(необязательно)</span></label><textarea id="taskReferenceNotes" class="form-control" rows="3"></textarea></div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Отмена</button><button type="button" id="saveTaskReference" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Добавить</button></div>
      </div></div>`;
    document.body.appendChild(modal);
    document.getElementById('saveTaskReference')?.addEventListener('click', saveReferenceFromTask);
  }

  function addReferenceButton(select, type, label) {
    if (!select || !options?.can_create_reference) return null;
    if (select.dataset.referenceAddReady === '1') return select.parentElement?.querySelector('[data-reference-add-button]') || null;
    select.dataset.referenceAddReady = '1';
    const wrap = document.createElement('div');
    wrap.className = 'input-group';
    select.parentNode.insertBefore(wrap, select);
    wrap.appendChild(select);
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'btn btn-outline-secondary';
    btn.dataset.referenceAddButton = '1';
    btn.title = 'Добавить в справочник: ' + label;
    btn.innerHTML = '<i class="bi bi-plus-lg"></i>';
    btn.addEventListener('click', () => openReferenceModal(select, type, label));
    wrap.appendChild(btn);
    return btn;
  }

  function toggleCustomerReferenceButton(select, visible) {
    if (!select || !options?.can_create_reference) return;
    const btn = addReferenceButton(select, 'organization', 'Предприятие');
    if (btn) btn.classList.toggle('d-none', !visible);
  }

  function openReferenceModal(select, type, label) {
    ensureReferenceModal();
    referenceTarget = select;
    referenceType = type;
    document.getElementById('taskReferenceModalTitle').textContent = 'Добавить: ' + label;
    document.getElementById('taskReferenceName').value = '';
    document.getElementById('taskReferenceCode').value = '';
    document.getElementById('taskReferenceNotes').value = '';
    document.getElementById('taskReferenceError').classList.add('d-none');
    document.getElementById('taskReferenceStatusFields').classList.toggle('d-none', type !== 'task_status');
    bootstrap.Modal.getOrCreateInstance(document.getElementById('taskReferenceModal')).show();
    setTimeout(() => document.getElementById('taskReferenceName')?.focus(), 150);
  }

  async function saveReferenceFromTask() {
    const name = document.getElementById('taskReferenceName').value.trim();
    if (!name) return showReferenceError('Введите наименование.');
    const payload = {
      type: referenceType,
      name,
      code: document.getElementById('taskReferenceCode').value.trim() || null,
      notes: document.getElementById('taskReferenceNotes').value.trim() || null,
      sort_order: 0,
      is_active: true
    };
    if (referenceType === 'task_status') {
      payload.system_key = document.getElementById('taskReferenceSystemKey').value;
      payload.color = document.getElementById('taskReferenceColor').value || '#0d6efd';
    }
    try {
      const r = await json('/ajax/directories', {method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(payload)});
      options = null;
      await loadOptions();
      refreshReferenceSelects();
      if (referenceTarget && r.item?.id) {
        referenceTarget.value = String(r.item.id);
        referenceTarget.dispatchEvent(new Event('change', {bubbles:true}));
      }
      bootstrap.Modal.getOrCreateInstance(document.getElementById('taskReferenceModal')).hide();
    } catch (e) { showReferenceError(e.message); }
  }

  function showReferenceError(message) {
    const box = document.getElementById('taskReferenceError');
    if (!box) return alert(message);
    box.textContent = message;
    box.classList.remove('d-none');
  }

  function refillSelect(select, rows, placeholder) {
    if (!select) return;
    const current = select.value;
    select.innerHTML = makeOptions(rows, placeholder);
    if ([...select.options].some(x => x.value === current)) select.value = current;
  }

  function refreshReferenceSelects() {
    refillSelect(document.querySelector('#taskBusinessCreateFields [name="project_id"]'), options.projects, 'Не выбрано');
    refillSelect(document.querySelector('#taskBusinessCreateFields [name="basis_id"]'), options.bases, 'Не выбрано');
    refillSelect(document.querySelector('#taskBusinessCreateFields [name="business_status_id"]'), options.statuses, 'Пусто');
    refillSelect(document.getElementById('taskProject'), options.projects, 'Не выбрано');
    refillSelect(document.getElementById('taskBasis'), options.bases, 'Не выбрано');
    refillSelect(document.getElementById('taskBusinessStatus'), options.statuses, 'Пусто');
    const createType = document.getElementById('createCustomerType')?.value;
    if (createType === 'organization') refillSelect(document.getElementById('createCustomerId'), options.organizations, 'Выберите...');
    const detailType = document.getElementById('taskCustomerType')?.value;
    if (detailType === 'organization') refillSelect(document.getElementById('taskCustomerId'), options.organizations, 'Выберите...');
  }

  async function injectCreateFields() {
    const form = document.getElementById('taskForm');
    if (!form || form.dataset.businessReady === '1') return;
    form.dataset.businessReady = '1';
    await loadOptions();

    const title = form.querySelector('[name="title"]');
    if (title) {
      title.removeAttribute('required');
      const label = title.closest('div')?.querySelector('label');
      if (label) label.textContent = 'Название (необязательно)';
      title.placeholder = 'Можно оставить пустым';
    }

    const description = form.querySelector('[name="description"]')?.closest('.col-12');
    if (!description) return;
    const block = document.createElement('div');
    block.className = 'col-12';
    block.id = 'taskBusinessCreateFields';
    block.innerHTML = `
      <div class="border rounded p-3 bg-light-subtle">
        <div class="fw-semibold mb-3"><i class="bi bi-briefcase me-1"></i>Реквизиты задачи</div>
        <div class="row g-3">
          <div class="col-md-4"><label class="form-label">Проект</label><select name="project_id" class="form-select">${makeOptions(options.projects)}</select></div>
          <div class="col-md-4"><label class="form-label">Основание</label><select name="basis_id" class="form-select">${makeOptions(options.bases)}</select></div>
          <div class="col-md-4"><label class="form-label">Ответственный отдел</label><select name="responsible_department_id" class="form-select">${makeOptions(options.departments)}</select></div>
          <div class="col-md-4"><label class="form-label">Старт <span class="text-muted fw-normal">(необязательно)</span></label><input name="start_at" type="datetime-local" class="form-control"></div>
          <div class="col-md-4"><label class="form-label">Тип заказчика</label><select name="customer_type" id="createCustomerType" class="form-select"><option value="">Не выбран</option><option value="organization">Предприятие</option><option value="department">Отдел</option></select></div>
          <div class="col-md-4"><label class="form-label">Заказчик</label><select name="customer_id" id="createCustomerId" class="form-select" disabled><option value="">Сначала выберите тип</option></select></div>
          <div class="col-md-4"><label class="form-label">Статус</label><select name="business_status_id" class="form-select">${makeOptions(options.statuses, 'Пусто')}</select></div>
          <div class="col-12"><div class="form-check mt-1"><input class="form-check-input" type="checkbox" name="add_to_plan" value="1" id="createAddToPlan"><label class="form-check-label fw-semibold" for="createAddToPlan">Добавить в план сотрудника на текущий месяц</label><div class="form-text">Если у выбранного сотрудника есть активный или черновой месячный план, задача будет автоматически добавлена в него.</div></div></div>
        </div>
      </div>`;
    description.parentNode.insertBefore(block, description);

    addReferenceButton(block.querySelector('[name="project_id"]'), 'project', 'Проект');
    addReferenceButton(block.querySelector('[name="basis_id"]'), 'basis', 'Основание');
    addReferenceButton(block.querySelector('[name="business_status_id"]'), 'task_status', 'Статус');

    const typeSelect = document.getElementById('createCustomerType');
    const customerSelect = document.getElementById('createCustomerId');
    typeSelect?.addEventListener('change', () => {
      const rows = typeSelect.value === 'organization' ? options.organizations : typeSelect.value === 'department' ? options.departments : [];
      customerSelect.innerHTML = makeOptions(rows, typeSelect.value ? 'Выберите...' : 'Сначала выберите тип');
      customerSelect.disabled = !typeSelect.value;
      toggleCustomerReferenceButton(customerSelect, typeSelect.value === 'organization');
    });

    form.addEventListener('submit', () => {
      const fd = new FormData(form);
      const projectId = fd.get('project_id') || '';
      const basisId = fd.get('basis_id') || '';
      let titleValue = String(fd.get('title') || '').trim();
      if (!titleValue) {
        const project = (options.projects || []).find(x => String(x.id) === String(projectId));
        const basis = (options.bases || []).find(x => String(x.id) === String(basisId));
        titleValue = project?.name || basis?.name || 'Задача';
        const titleInput = form.querySelector('[name="title"]');
        if (titleInput) titleInput.value = titleValue;
      }
      pendingCreateMeta = {
        title: titleValue,
        project_id: projectId || null,
        basis_id: basisId || null,
        responsible_department_id: fd.get('responsible_department_id') || null,
        start_at: fd.get('start_at') || null,
        customer_type: fd.get('customer_type') || null,
        customer_id: fd.get('customer_id') || null,
        business_status_id: fd.get('business_status_id') || null,
      };
    }, true);
  }

  function injectDetailsBlock() {
    if (document.getElementById('taskBusinessBlock')) return;
    const description = document.getElementById('detailDescription');
    if (!description) return;
    const block = document.createElement('div');
    block.id = 'taskBusinessBlock';
    block.className = 'card border mb-4';
    block.innerHTML = `
      <div class="card-header bg-light d-flex align-items-center"><b><i class="bi bi-briefcase me-1"></i>Реквизиты задачи</b><span id="taskBusinessSaveState" class="small text-muted ms-auto"></span></div>
      <div class="card-body"><div class="row g-2">
        <div class="col-md-6"><label class="form-label small">Название <span class="text-muted">(необязательно)</span></label><input id="taskBusinessTitle" class="form-control"></div>
        <div class="col-md-6"><label class="form-label small">Проект</label><select id="taskProject" class="form-select"></select></div>
        <div class="col-md-6"><label class="form-label small">Основание</label><select id="taskBasis" class="form-select"></select></div>
        <div class="col-md-6"><label class="form-label small">Ответственный отдел</label><select id="taskResponsibleDepartment" class="form-select"></select></div>
        <div class="col-md-6"><label class="form-label small">Старт <span class="text-muted">(необязательно)</span></label><input id="taskStartAt" type="datetime-local" class="form-control"></div>
        <div class="col-md-6"><label class="form-label small">Статус</label><select id="taskBusinessStatus" class="form-select"></select></div>
        <div class="col-md-6"><label class="form-label small">Тип заказчика</label><select id="taskCustomerType" class="form-select"><option value="">Не выбран</option><option value="organization">Предприятие</option><option value="department">Отдел</option></select></div>
        <div class="col-md-6"><label class="form-label small">Заказчик</label><select id="taskCustomerId" class="form-select"></select></div>
        <div class="col-12"><button id="saveTaskBusiness" type="button" class="btn btn-sm btn-outline-primary"><i class="bi bi-check2 me-1"></i>Сохранить реквизиты</button></div>
      </div></div>`;
    description.insertAdjacentElement('afterend', block);
    addReferenceButton(document.getElementById('taskProject'), 'project', 'Проект');
    addReferenceButton(document.getElementById('taskBasis'), 'basis', 'Основание');
    addReferenceButton(document.getElementById('taskBusinessStatus'), 'task_status', 'Статус');
    document.getElementById('taskCustomerType')?.addEventListener('change', e => {
      customerOptions(e.target.value);
      toggleCustomerReferenceButton(document.getElementById('taskCustomerId'), e.target.value === 'organization');
    });
    document.getElementById('saveTaskBusiness')?.addEventListener('click', saveTaskDetails);
  }

  function injectLinksBlock() {
    if (document.getElementById('taskLinksBlock')) return;
    const attachments = document.getElementById('attachmentsList');
    if (!attachments) return;
    const heading = attachments.previousElementSibling;
    if (heading && heading.tagName === 'DIV') {
      const h6s = [...document.querySelectorAll('h6')];
      const fileHeading = h6s.find(x => x.textContent.trim() === 'Вложения');
      if (fileHeading) fileHeading.textContent = 'Документы';
    } else {
      const fileHeading = [...document.querySelectorAll('h6')].find(x => x.textContent.trim() === 'Вложения');
      if (fileHeading) fileHeading.textContent = 'Документы';
    }
    const block = document.createElement('div');
    block.id = 'taskLinksBlock';
    block.className = 'mb-4';
    block.innerHTML = `
      <h6>Гиперссылки</h6>
      <div class="row g-2 mb-2"><div class="col-md-4"><input id="taskLinkTitle" class="form-control" placeholder="Название ссылки"></div><div class="col-md-6"><input id="taskLinkUrl" type="url" class="form-control" placeholder="https://..."></div><div class="col-md-2"><button id="addTaskLinkBtn" type="button" class="btn btn-outline-primary w-100">Добавить</button></div></div>
      <div id="taskLinksList"></div>`;
    attachments.insertAdjacentElement('afterend', block);
    document.getElementById('addTaskLinkBtn')?.addEventListener('click', addTaskLink);
  }

  async function loadTaskDetails(id) {
    currentTaskId = Number(id);
    await loadOptions();
    injectDetailsBlock();
    injectLinksBlock();
    const d = await json(`/ajax/tasks/${id}/details`);
    document.getElementById('taskBusinessTitle').value = d.title || '';
    document.getElementById('taskProject').innerHTML = makeOptions(options.projects);
    document.getElementById('taskBasis').innerHTML = makeOptions(options.bases);
    document.getElementById('taskResponsibleDepartment').innerHTML = makeOptions(options.departments);
    document.getElementById('taskBusinessStatus').innerHTML = makeOptions(options.statuses, 'Пусто');
    document.getElementById('taskProject').value = d.project_id || '';
    document.getElementById('taskBasis').value = d.basis_id || '';
    document.getElementById('taskResponsibleDepartment').value = d.responsible_department_id || '';
    document.getElementById('taskStartAt').value = toLocal(d.start_at);
    document.getElementById('taskBusinessStatus').value = d.business_status_id || '';
    document.getElementById('taskCustomerType').value = d.customer_type || '';
    customerOptions(d.customer_type || '', d.customer_id || '');
    toggleCustomerReferenceButton(document.getElementById('taskCustomerId'), (d.customer_type || '') === 'organization');
    const canManage = !!d.can_manage;
    document.querySelectorAll('#taskBusinessBlock input,#taskBusinessBlock select,#saveTaskBusiness').forEach(el => el.disabled = !canManage);
    renderLinks(d.links || []);
  }

  async function saveTaskDetails() {
    if (!currentTaskId) return;
    const payload = {
      title: document.getElementById('taskBusinessTitle').value.trim() || null,
      project_id: document.getElementById('taskProject').value || null,
      basis_id: document.getElementById('taskBasis').value || null,
      responsible_department_id: document.getElementById('taskResponsibleDepartment').value || null,
      start_at: document.getElementById('taskStartAt').value || null,
      customer_type: document.getElementById('taskCustomerType').value || null,
      customer_id: document.getElementById('taskCustomerId').value || null,
      business_status_id: document.getElementById('taskBusinessStatus').value || null,
    };
    const state = document.getElementById('taskBusinessSaveState');
    try {
      state.textContent = 'Сохранение...';
      const r = await json(`/ajax/tasks/${currentTaskId}/details`, {method:'PATCH',headers:{'Content-Type':'application/json'},body:JSON.stringify(payload)});
      state.textContent = 'Сохранено';
      document.getElementById('detailTitle').textContent = r.task.title || 'Задача';
      setTimeout(() => state.textContent = '', 1500);
      scheduleBatchBadges();
    } catch (e) { state.textContent = ''; alert(e.message); }
  }

  function renderLinks(rows) {
    const box = document.getElementById('taskLinksList');
    if (!box) return;
    if (!rows.length) { box.innerHTML = '<div class="text-muted small">Ссылок пока нет.</div>'; return; }
    box.innerHTML = rows.map(x => `<div class="border rounded p-2 mb-2 d-flex align-items-center gap-2"><i class="bi bi-link-45deg fs-5"></i><div class="flex-grow-1 min-w-0"><a href="${esc(x.url)}" target="_blank" rel="noopener" class="fw-semibold text-break">${esc(x.title || x.url)}</a><div class="small text-muted text-break">${esc(x.url)}</div></div><button type="button" class="btn btn-sm btn-outline-danger" data-delete-task-link="${x.id}" title="Удалить"><i class="bi bi-trash"></i></button></div>`).join('');
    box.querySelectorAll('[data-delete-task-link]').forEach(btn => btn.addEventListener('click', () => deleteTaskLink(btn.dataset.deleteTaskLink)));
  }

  async function addTaskLink() {
    if (!currentTaskId) return;
    const title = document.getElementById('taskLinkTitle').value.trim();
    const url = document.getElementById('taskLinkUrl').value.trim();
    if (!url) return alert('Укажите ссылку.');
    try {
      await json(`/ajax/tasks/${currentTaskId}/links`, {method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({title,url})});
      document.getElementById('taskLinkTitle').value = '';
      document.getElementById('taskLinkUrl').value = '';
      const d = await json(`/ajax/tasks/${currentTaskId}/details`);
      renderLinks(d.links || []);
    } catch (e) { alert(e.message); }
  }

  async function deleteTaskLink(id) {
    if (!currentTaskId || !confirm('Удалить ссылку?')) return;
    try {
      await json(`/ajax/tasks/${currentTaskId}/links/${id}`, {method:'DELETE'});
      const d = await json(`/ajax/tasks/${currentTaskId}/details`);
      renderLinks(d.links || []);
    } catch (e) { alert(e.message); }
  }

  function installOpenTaskHook() {
    if (typeof window.openTask !== 'function' || window.openTask.__businessWrapped) return;
    const original = window.openTask;
    const wrapped = function(id) {
      const result = original.apply(this, arguments);
      loadTaskDetails(id).catch(e => console.error(e));
      return result;
    };
    wrapped.__businessWrapped = true;
    window.openTask = wrapped;
  }

  function scheduleBatchBadges() {
    clearTimeout(batchTimer);
    batchTimer = setTimeout(loadBatchBadges, 120);
  }

  async function loadBatchBadges() {
    const list = document.getElementById('taskList');
    if (!list) return;
    const buttons = [...list.querySelectorAll('button[onclick^="openTask("]')];
    const ids = buttons.map(btn => Number((btn.getAttribute('onclick').match(/openTask\((\d+)\)/) || [])[1])).filter(Boolean);
    if (!ids.length) return;
    try {
      const query = ids.map(id => `ids[]=${encodeURIComponent(id)}`).join('&');
      const rows = await json(`/ajax/task-details/batch?${query}`);
      buttons.forEach(btn => {
        const id = Number((btn.getAttribute('onclick').match(/openTask\((\d+)\)/) || [])[1]);
        const data = rows[String(id)];
        const card = btn.closest('.card-body');
        const title = card?.querySelector('.fw-semibold.fs-5');
        if (!title || !data) return;
        let summary = card.querySelector(`[data-task-business-summary="${id}"]`);
        if (!summary) {
          summary = document.createElement('div');
          summary.dataset.taskBusinessSummary = String(id);
          summary.className = 'small mt-1 d-flex flex-wrap gap-1';
          title.insertAdjacentElement('afterend', summary);
        }
        const parts = [];
        if (data.project?.name) parts.push(`<span class="badge text-bg-light border"><i class="bi bi-briefcase me-1"></i>${esc(data.project.name)}</span>`);
        if (data.responsible_department?.name) parts.push(`<span class="badge text-bg-light border"><i class="bi bi-building me-1"></i>${esc(data.responsible_department.name)}</span>`);
        if (data.business_status?.name) parts.push(`<span class="badge border" style="background:${esc(data.business_status.color || '#f8f9fa')};color:#212529">${esc(data.business_status.name)}</span>`);
        summary.innerHTML = parts.join('');
      });
    } catch (_) {}
  }

  function observeTaskList() {
    const list = document.getElementById('taskList');
    if (!list) return;
    new MutationObserver(scheduleBatchBadges).observe(list, {childList:true,subtree:true});
    scheduleBatchBadges();
  }

  function installAjaxCreateSync() {
    if (!window.jQuery) return;
    window.jQuery(document).ajaxSuccess((event, xhr, settings, response) => {
      let url;
      try { url = new URL(settings.url, location.origin); } catch (_) { return; }
      const method = String(settings.type || settings.method || 'GET').toUpperCase();
      if (method !== 'POST' || url.pathname !== '/ajax/tasks' || !pendingCreateMeta) return;
      const data = response || xhr.responseJSON;
      const id = data?.task?.id;
      if (!id) return;
      if (data?.plan_attached && data?.plan?.title) {
        setTimeout(() => alert('Задача добавлена в план: ' + data.plan.title), 50);
      } else if (data?.plan_message) {
        setTimeout(() => alert(data.plan_message), 50);
      }
      const meta = pendingCreateMeta;
      pendingCreateMeta = null;
      json(`/ajax/tasks/${id}/details`, {method:'PATCH',headers:{'Content-Type':'application/json'},body:JSON.stringify(meta)})
        .then(scheduleBatchBadges).catch(e => console.error(e));
    });
  }

  async function init() {
    try {
      await loadOptions();
      await injectCreateFields();
      injectDetailsBlock();
      injectLinksBlock();
      installOpenTaskHook();
      installAjaxCreateSync();
      observeTaskList();
    } catch (e) { console.error('Task business fields:', e); }
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})();

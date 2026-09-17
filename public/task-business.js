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
        </div>
      </div>`;
    description.parentNode.insertBefore(block, description);

    const typeSelect = document.getElementById('createCustomerType');
    const customerSelect = document.getElementById('createCustomerId');
    typeSelect?.addEventListener('change', () => {
      const rows = typeSelect.value === 'organization' ? options.organizations : typeSelect.value === 'department' ? options.departments : [];
      customerSelect.innerHTML = makeOptions(rows, typeSelect.value ? 'Выберите...' : 'Сначала выберите тип');
      customerSelect.disabled = !typeSelect.value;
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
    document.getElementById('taskCustomerType')?.addEventListener('change', e => customerOptions(e.target.value));
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

(() => {
  const csrf = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
  const encoding = () => (window.PushManager?.supportedContentEncodings || ['aes128gcm'])[0];

  function ensureThemeStylesheet() {
    if (document.querySelector('link[href="/user-themes.css"]')) return;
    const link = document.createElement('link');
    link.rel = 'stylesheet';
    link.href = '/user-themes.css';
    document.head.appendChild(link);
  }

  function applyTheme(theme) {
    const allowed = ['light','dark','blue','green','purple','contrast'];
    const value = allowed.includes(theme) ? theme : 'light';
    ensureThemeStylesheet();
    document.documentElement.setAttribute('data-crm-theme', value);
    try { localStorage.setItem('crm-ui-theme', value); } catch (_) {}
  }

  function applyCachedTheme() {
    try { applyTheme(localStorage.getItem('crm-ui-theme') || 'light'); }
    catch (_) { applyTheme('light'); }
  }

  async function syncThemeFromServer() {
    try {
      const r = await fetch('/ajax/user-theme', {credentials:'same-origin', headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest'}});
      if (!r.ok) return;
      const data = await r.json();
      applyTheme(data.theme || 'light');
    } catch (_) {}
  }

  function addUserSettingsMenu() {
    if (document.querySelector('a[href="/my-settings"]')) return;

    const systemSection = [...document.querySelectorAll('.sidebar-section')].find(x => x.textContent.trim() === 'Система');
    if (systemSection) {
      const a = document.createElement('a');
      a.className = 'nav-link rounded' + (location.pathname === '/my-settings' ? ' active' : '');
      a.href = '/my-settings';
      a.innerHTML = '<i class="bi bi-palette me-2"></i>Мои настройки';
      systemSection.parentNode.insertBefore(a, systemSection.nextSibling);
    }

    const mobileSystem = [...document.querySelectorAll('.mobile-section-title')].find(x => x.textContent.trim() === 'Система');
    if (mobileSystem) {
      const a = document.createElement('a');
      a.className = 'mobile-menu-link' + (location.pathname === '/my-settings' ? ' active' : '');
      a.href = '/my-settings';
      a.innerHTML = '<i class="bi bi-palette"></i><span>Мои настройки</span>';
      mobileSystem.parentNode.insertBefore(a, mobileSystem.nextSibling);
    }
  }

  applyCachedTheme();

  async function currentSubscription() {
    if (!window.isSecureContext || !('serviceWorker' in navigator) || !('PushManager' in window)) return null;
    const reg = await navigator.serviceWorker.ready;
    return reg.pushManager.getSubscription();
  }

  async function syncSubscription() {
    if (!('Notification' in window) || Notification.permission !== 'granted') return;
    try {
      const sub = await currentSubscription();
      if (!sub) return;
      const json = sub.toJSON();
      await fetch('/ajax/push/subscribe', {
        method: 'POST', credentials: 'same-origin',
        headers: {'Content-Type':'application/json','X-CSRF-TOKEN':csrf(),'X-Requested-With':'XMLHttpRequest'},
        body: JSON.stringify({endpoint: sub.endpoint, keys: json.keys, contentEncoding: encoding()})
      });
    } catch (_) {}
  }

  let loggingOut = false;
  document.addEventListener('submit', event => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement) || loggingOut) return;
    let url; try { url = new URL(form.action, location.href); } catch (_) { return; }
    if (!url.pathname.endsWith('/logout')) return;
    event.preventDefault();
    (async () => {
      try {
        const sub = await currentSubscription();
        if (sub) {
          await fetch('/ajax/push/unsubscribe', {
            method:'DELETE',credentials:'same-origin',
            headers:{'Content-Type':'application/json','X-CSRF-TOKEN':csrf(),'X-Requested-With':'XMLHttpRequest'},
            body:JSON.stringify({endpoint:sub.endpoint})
          });
          await sub.unsubscribe();
        }
      } catch (_) {}
      loggingOut = true; form.submit();
    })();
  });

  function addAnalyticsMenu() {
    if (document.querySelector('a[href="/analytics"]')) return;
    const managerSection = [...document.querySelectorAll('.sidebar-section')].find(x => x.textContent.trim() === 'Руководителю');
    if (managerSection) {
      const a=document.createElement('a');
      a.className='nav-link rounded'+(location.pathname.startsWith('/analytics')?' active':'');
      a.href='/analytics'; a.innerHTML='<i class="bi bi-graph-up-arrow me-2"></i>Аналитика';
      managerSection.parentNode.insertBefore(a, managerSection.nextSibling);
    }
    const mobileManager=[...document.querySelectorAll('.mobile-section-title')].find(x=>x.textContent.trim()==='Руководителю');
    if(mobileManager){
      const a=document.createElement('a');
      a.className='mobile-menu-link'+(location.pathname.startsWith('/analytics')?' active':'');
      a.href='/analytics';a.innerHTML='<i class="bi bi-graph-up-arrow"></i><span>Аналитика</span>';
      mobileManager.parentNode.insertBefore(a,mobileManager.nextSibling);
    }
  }

  function loadScript(src){return new Promise((resolve,reject)=>{if(document.querySelector(`script[src="${src}"]`))return resolve();const s=document.createElement('script');s.src=src;s.onload=resolve;s.onerror=reject;document.head.appendChild(s)})}

  async function addDashboardAnalytics(){
    if(location.pathname!=='/' || document.getElementById('dashboardAnalyticsBlock'))return;
    try{
      const r=await fetch('/ajax/analytics/dashboard',{credentials:'same-origin',headers:{'X-Requested-With':'XMLHttpRequest'}});
      if(!r.ok)return; const d=await r.json();
      await loadScript('/js/crm-charts.js');
      const hero=document.querySelector('.dashboard360-hero'); if(!hero)return;
      const wrap=document.createElement('div');wrap.id='dashboardAnalyticsBlock';wrap.className='mb-4';
      wrap.innerHTML=`<div class="d-flex align-items-center mb-2"><h4 class="mb-0">${d.is_manager?'Сводная аналитика':'Моя аналитика'}</h4>${d.is_manager?'<a href="/analytics" class="btn btn-sm btn-outline-primary ms-auto">Вся аналитика</a>':''}</div><div class="row g-3"><div class="col-xl-7"><div class="card border-0 shadow-sm h-100"><div class="card-header bg-white"><b>Динамика задач за 6 месяцев</b></div><div class="card-body"><canvas data-height="280" data-crm-chart="line"></canvas></div></div></div><div class="col-xl-5"><div class="card border-0 shadow-sm h-100"><div class="card-header bg-white"><b>Структура задач</b></div><div class="card-body"><canvas data-height="280" data-crm-chart="donut"></canvas></div></div></div></div>`;
      hero.insertAdjacentElement('afterend',wrap);
      const canvases=wrap.querySelectorAll('canvas');
      canvases[0].dataset.chart=JSON.stringify({labels:d.trend.labels,series:[{label:'Создано',values:d.trend.created},{label:'Выполнено',values:d.trend.completed}]});
      canvases[1].dataset.chart=JSON.stringify({labels:Object.keys(d.statuses),values:Object.values(d.statuses)});
      window.CRMCharts?.renderAll();
    }catch(_){ }
  }

  window.addEventListener('load', () => {
    syncThemeFromServer();
    syncSubscription();
    addUserSettingsMenu();
    addAnalyticsMenu();
    addDashboardAnalytics();
  });
})();

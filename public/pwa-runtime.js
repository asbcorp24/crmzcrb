(() => {
  const csrf = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
  const encoding = () => (window.PushManager?.supportedContentEncodings || ['aes128gcm'])[0];

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
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': csrf(),
          'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({endpoint: sub.endpoint, keys: json.keys, contentEncoding: encoding()})
      });
    } catch (_) {}
  }

  let loggingOut = false;
  document.addEventListener('submit', event => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement) || loggingOut) return;
    let url;
    try { url = new URL(form.action, location.href); } catch (_) { return; }
    if (!url.pathname.endsWith('/logout')) return;

    event.preventDefault();
    (async () => {
      try {
        const sub = await currentSubscription();
        if (sub) {
          await fetch('/ajax/push/unsubscribe', {
            method: 'DELETE',
            credentials: 'same-origin',
            headers: {
              'Content-Type': 'application/json',
              'X-CSRF-TOKEN': csrf(),
              'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({endpoint: sub.endpoint})
          });
          await sub.unsubscribe();
        }
      } catch (_) {}
      loggingOut = true;
      form.submit();
    })();
  });

  window.addEventListener('load', syncSubscription);
})();

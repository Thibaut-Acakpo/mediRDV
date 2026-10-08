/* MédiRDV — utilitaires communs : API, toasts, actions, formulaires */
(function () {
  'use strict';
  const body = document.body;
  const BASE = body.dataset.base || '';
  const CSRF = body.dataset.csrf || '';

  /** Appel JSON vers /api/<endpoint>. Rejette avec un Error portant le message serveur. */
  async function api(endpoint, data, method) {
    const isPost = method ? method === 'POST' : data !== undefined;
    const url = BASE + '/api/' + endpoint;
    const res = await fetch(url, {
      method: isPost ? 'POST' : 'GET',
      headers: Object.assign({ 'Accept': 'application/json' }, isPost ? { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF } : {}),
      credentials: 'same-origin',
      body: isPost ? JSON.stringify(data || {}) : undefined
    });
    let json = null;
    try { json = await res.json(); } catch (e) { /* réponse non JSON */ }
    if (!res.ok || !json || json.ok === false) {
      const err = new Error((json && json.error) || 'Une erreur est survenue. Réessayez.');
      err.status = res.status;
      if (res.status === 401) { setTimeout(() => { location.href = BASE + '/login.php'; }, 1200); }
      throw err;
    }
    return json;
  }

  function toast(message, type) {
    const box = document.getElementById('toasts');
    if (!box || !window.bootstrap) return;
    const el = document.createElement('div');
    el.className = 'toast align-items-center ' + (type === 'err' ? 'err' : 'ok');
    el.setAttribute('role', 'status');
    el.innerHTML = '<div class="d-flex"><div class="toast-body"><i class="bi ' + (type === 'err' ? 'bi-exclamation-octagon' : 'bi-check-circle-fill') + ' me-2"></i><span></span></div>' +
      '<button type="button" class="btn-close me-2 m-auto" data-bs-dismiss="toast" aria-label="Fermer"></button></div>';
    el.querySelector('span').textContent = message;
    box.appendChild(el);
    const t = new bootstrap.Toast(el, { delay: 4200 });
    el.addEventListener('hidden.bs.toast', () => el.remove());
    t.show();
  }

  /** Fenêtre de confirmation (Bootstrap) → Promise<boolean>. */
  function confirmDialog(text) {
    const modalEl = document.getElementById('confirmModal');
    if (!modalEl || !window.bootstrap) return Promise.resolve(window.confirm(text));
    return new Promise((resolve) => {
      modalEl.querySelector('#confirmText').textContent = text;
      const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
      const ok = modalEl.querySelector('#confirmOk');
      let answered = false;
      const done = (v) => { if (answered) return; answered = true; ok.removeEventListener('click', onOk); modalEl.removeEventListener('hidden.bs.modal', onHide); resolve(v); };
      const onOk = () => { done(true); modal.hide(); };
      const onHide = () => done(false);
      ok.addEventListener('click', onOk);
      modalEl.addEventListener('hidden.bs.modal', onHide);
      modal.show();
    });
  }

  // Boutons génériques : <button data-api="cancel.php" data-payload='{"id":1}' data-confirm="…">
  document.addEventListener('click', async (e) => {
    const btn = e.target.closest('[data-api]');
    if (!btn || btn.disabled) return;
    e.preventDefault();
    if (btn.dataset.confirm && !(await confirmDialog(btn.dataset.confirm))) return;
    const original = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm" aria-hidden="true"></span>';
    try {
      const res = await api(btn.dataset.api, JSON.parse(btn.dataset.payload || '{}'));
      toast(res.message || 'C\'est fait.', 'ok');
      const row = btn.closest('.appt-card, tr, .pending-row, .tl-item');
      if (row) { row.style.transition = 'opacity .4s, transform .4s'; row.style.opacity = '.35'; }
      setTimeout(() => location.reload(), 700);
    } catch (err) {
      toast(err.message, 'err');
      btn.disabled = false;
      btn.innerHTML = original;
    }
  });

  // Afficher / masquer un mot de passe
  document.addEventListener('click', (e) => {
    const b = e.target.closest('[data-toggle-pass]');
    if (!b) return;
    const input = document.getElementById(b.dataset.togglePass);
    const show = input.type === 'password';
    input.type = show ? 'text' : 'password';
    b.innerHTML = '<i class="bi bi-eye' + (show ? '-slash' : '') + '"></i>';
  });

  // Comptes de démonstration (mode dev)
  document.addEventListener('click', (e) => {
    const b = e.target.closest('[data-fill-email]');
    if (!b) return;
    document.getElementById('email').value = b.dataset.fillEmail;
    document.getElementById('password').value = b.dataset.fillPass;
    document.querySelector('form button[type=submit]').focus();
  });

  // Fermeture automatique des messages flash
  document.querySelectorAll('.flash-stack .alert-success').forEach((a) => {
    setTimeout(() => { if (window.bootstrap && a.isConnected) bootstrap.Alert.getOrCreateInstance(a).close(); }, 6000);
  });

  window.MediRDV = { api, toast, confirmDialog, BASE };
})();

/* Disponibilités : éditeur d'horaires hebdomadaires et de congés */
(function () {
  'use strict';
  const editor = document.getElementById('weekEditor');
  if (!editor) return;
  const { api, toast } = MediRDV;
  const NAMES = ['lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi', 'dimanche'];
  const rows = document.getElementById('weekRows');
  const state = document.getElementById('weekState'), errBox = document.getElementById('weekError');
  const raw = JSON.parse(editor.dataset.week || '{}');
  const week = {};
  for (let d = 1; d <= 7; d++) week[d] = (raw[d] || []).map((r) => ({ start: r.start, end: r.end }));
  let dirty = false;

  const markDirty = () => { dirty = true; state.textContent = 'Modifications non enregistrées'; };

  function render() {
    rows.innerHTML = '';
    const tools = document.createElement('div');
    tools.className = 'mb-2';
    tools.innerHTML = '<button type="button" class="link-btn" id="copyMon"><i class="bi bi-copy"></i> Copier les horaires du lundi sur mardi à vendredi</button>';
    rows.appendChild(tools);
    tools.querySelector('#copyMon').addEventListener('click', () => {
      for (let d = 2; d <= 5; d++) week[d] = week[1].map((r) => ({ start: r.start, end: r.end }));
      markDirty(); render();
    });

    for (let d = 1; d <= 7; d++) {
      const open = week[d].length > 0;
      const row = document.createElement('div');
      row.className = 'week-row' + (open ? '' : ' closed');

      const head = document.createElement('div');
      head.innerHTML = '<div class="form-check form-switch"><input class="form-check-input" type="checkbox" role="switch" id="sw' + d + '" ' + (open ? 'checked' : '') + '>' +
        '<label class="form-check-label day-name" for="sw' + d + '">' + NAMES[d - 1] + '</label></div>';
      head.querySelector('input').addEventListener('change', (e) => {
        week[d] = e.target.checked ? [{ start: '09:00', end: '12:00' }] : [];
        markDirty(); render();
      });
      row.appendChild(head);

      const body = document.createElement('div');
      if (!open) {
        body.innerHTML = '<span class="closed-label">Fermé</span>';
      } else {
        const list = document.createElement('div');
        list.className = 'ranges';
        week[d].forEach((r, i) => {
          const line = document.createElement('div');
          line.className = 'range';
          line.innerHTML = '<input type="time" class="form-control form-control-sm" value="' + r.start + '" step="300" aria-label="Début ' + NAMES[d - 1] + '">' +
            '<span class="sep">à</span><input type="time" class="form-control form-control-sm" value="' + r.end + '" step="300" aria-label="Fin ' + NAMES[d - 1] + '">' +
            '<button type="button" class="mini-btn danger" aria-label="Supprimer la plage"><i class="bi bi-x-lg"></i></button>';
          const [s, e] = line.querySelectorAll('input');
          s.addEventListener('change', () => { r.start = s.value; markDirty(); });
          e.addEventListener('change', () => { r.end = e.value; markDirty(); });
          line.querySelector('button').addEventListener('click', () => { week[d].splice(i, 1); markDirty(); render(); });
          list.appendChild(line);
        });
        body.appendChild(list);
        const add = document.createElement('button');
        add.type = 'button'; add.className = 'link-btn mt-2';
        add.innerHTML = '<i class="bi bi-plus-lg"></i> Ajouter une plage';
        add.addEventListener('click', () => {
          const last = week[d][week[d].length - 1];
          week[d].push({ start: last ? last.end : '14:00', end: last && last.end < '17:00' ? '18:00' : '17:00' });
          markDirty(); render();
        });
        body.appendChild(add);
      }
      row.appendChild(body);
      rows.appendChild(row);
    }
  }

  document.getElementById('saveWeek').addEventListener('click', async (ev) => {
    const btn = ev.currentTarget;
    errBox.hidden = true;
    btn.disabled = true;
    try {
      const res = await api('availability.php', { action: 'save_weekly', week });
      dirty = false; state.textContent = 'Enregistré'; toast(res.message, 'ok');
    } catch (e) { errBox.textContent = e.message; errBox.hidden = false; }
    btn.disabled = false;
  });
  window.addEventListener('beforeunload', (e) => { if (dirty) { e.preventDefault(); e.returnValue = ''; } });

  /* ---- Congés ---- */
  const panel = document.getElementById('offPanel'), list = document.getElementById('offList');
  const start = document.getElementById('offStart'), end = document.getElementById('offEnd'), reason = document.getElementById('offReason');
  let off = JSON.parse(panel.dataset.off || '[]');
  const esc = (s) => String(s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

  function renderOff() {
    list.innerHTML = off.length ? '' : '<li class="justify-content-center text-body-secondary">Aucune fermeture prévue</li>';
    off.forEach((o) => {
      const li = document.createElement('li');
      li.innerHTML = '<div><strong class="d-block">' + esc(o.label) + '</strong>' + (o.reason ? '<small>' + esc(o.reason) + '</small>' : '') + '</div>' +
        '<button type="button" class="mini-btn danger" aria-label="Supprimer"><i class="bi bi-trash"></i></button>';
      li.querySelector('button').addEventListener('click', async () => {
        try { await api('availability.php', { action: 'delete_off', id: o.id }); off = off.filter((x) => x.id !== o.id); renderOff(); toast('Période supprimée.', 'ok'); }
        catch (e) { toast(e.message, 'err'); }
      });
      list.appendChild(li);
    });
  }
  start.addEventListener('change', () => { if (!end.value || end.value < start.value) end.value = start.value; end.min = start.value; });
  document.getElementById('addOff').addEventListener('click', async () => {
    if (!start.value) { toast('Choisissez au moins la date de début.', 'err'); return; }
    try {
      const res = await api('availability.php', { action: 'add_off', start: start.value, end: end.value || start.value, reason: reason.value });
      off = res.off; renderOff(); toast(res.message, res.warn ? 'err' : 'ok');
      start.value = end.value = reason.value = '';
    } catch (e) { toast(e.message, 'err'); }
  });

  render(); renderOff();
})();

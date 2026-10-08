/* Annuaire : recherche en direct */
(function () {
  'use strict';
  const form = document.getElementById('filterForm');
  if (!form) return;
  const q = document.getElementById('fq'), s = document.getElementById('fs');
  const results = document.getElementById('results'), count = document.getElementById('resultCount');
  let timer = null, ctrl = null;

  async function search() {
    if (ctrl) ctrl.abort();
    ctrl = new AbortController();
    const params = new URLSearchParams({ q: q.value.trim(), specialty: s.value });
    results.style.opacity = '.45';
    try {
      const res = await fetch(MediRDV.BASE + '/api/practitioners.php?' + params, { signal: ctrl.signal, credentials: 'same-origin' });
      const json = await res.json();
      results.innerHTML = json.html;
      count.textContent = json.count + ' praticien' + (json.count > 1 ? 's' : '');
      history.replaceState(null, '', '?' + new URLSearchParams(Array.from(params).filter(([, v]) => v)).toString());
      if (window.MediFX) MediFX.initTilt(results);
    } catch (e) { if (e.name !== 'AbortError') MediRDV.toast('Recherche impossible pour le moment.', 'err'); }
    results.style.opacity = '';
  }
  form.addEventListener('submit', (e) => { e.preventDefault(); search(); });
  q.addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(search, 280); });
  s.addEventListener('change', search);
  if (window.MediFX) MediFX.initTilt(results);
})();

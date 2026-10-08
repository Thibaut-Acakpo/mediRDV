/* Inscription : bascule patient / praticien et force du mot de passe */
(function () {
  'use strict';
  const form = document.getElementById('registerForm');
  if (!form) return;
  const fields = document.getElementById('practitionerFields');
  form.querySelectorAll('input[name=role]').forEach((r) => r.addEventListener('change', () => {
    fields.hidden = form.querySelector('input[name=role]:checked').value !== 'practitioner';
  }));

  const pw = document.getElementById('password'), bar = document.querySelector('#pwMeter span');
  pw.addEventListener('input', () => {
    const v = pw.value;
    let s = 0;
    if (v.length >= 8) s++;
    if (/[A-Za-z]/.test(v) && /\d/.test(v)) s++;
    if (v.length >= 12) s++;
    if (/[^A-Za-z0-9]/.test(v)) s++;
    bar.style.width = (v ? Math.max(15, s * 25) : 0) + '%';
    bar.style.background = ['#fb7185', '#fb7185', '#fbbf24', '#5eead4', '#5eead4'][s];
  });
})();

/* Fiche praticien : choix du jour, du créneau, et réservation */
(function () {
  'use strict';
  const root = document.getElementById('booking');
  if (!root) return;
  const { api, toast } = MediRDV;
  const pid = root.dataset.practitioner, mode = root.dataset.mode, loginUrl = root.dataset.login;
  const auto = root.dataset.auto === '1', duration = root.dataset.duration;
  const strip = document.getElementById('dayStrip'), slotsEl = document.getElementById('slots');
  const slotsTitle = document.getElementById('slotsTitle'), confirmBox = document.getElementById('bookConfirm');
  const summary = document.getElementById('summary'), bookBtn = document.getElementById('bookBtn');
  const errBox = document.getElementById('bookError'), reason = document.getElementById('reason');
  let days = [], selectedDate = null, selectedSlot = null;

  const MONTHS = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
  const DAYS = ['dimanche', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'];
  const longDate = (iso) => { const d = new Date(iso + 'T12:00:00'); return DAYS[d.getDay()] + ' ' + d.getDate() + ' ' + MONTHS[d.getMonth()]; };

  function renderDays() {
    strip.innerHTML = '';
    days.forEach((d) => {
      const b = document.createElement('button');
      b.type = 'button';
      b.className = 'day-pill' + (d.date === selectedDate ? ' active' : '');
      b.disabled = d.count === 0;
      b.dataset.date = d.date;
      b.setAttribute('role', 'option');
      b.setAttribute('aria-label', longDate(d.date) + (d.count ? ', ' + d.count + ' créneaux' : ', complet'));
      b.innerHTML = '<span class="dow">' + d.dow + '.</span><span class="num">' + d.day + '</span><span class="mon">' + d.month + (d.month.length < 4 ? '' : '.') + '</span>' + (d.count ? '<span class="dot"></span>' : '');
      b.addEventListener('click', () => selectDay(d.date));
      strip.appendChild(b);
    });
  }

  async function loadDays(keepSelection) {
    try {
      const res = await api('overview.php?practitioner=' + pid);
      days = res.days;
      const first = days.find((d) => d.count > 0);
      if (!first) {
        strip.innerHTML = '<div class="strip-loading">Aucune disponibilité dans les prochaines semaines.</div>';
        slotsTitle.textContent = '';
        return;
      }
      if (!keepSelection || !days.some((d) => d.date === selectedDate && d.count > 0)) selectedDate = first.date;
      renderDays();
      await loadSlots(selectedDate);
      const active = strip.querySelector('.active');
      if (active) strip.scrollTo({ left: Math.max(0, active.offsetLeft - 60), behavior: 'smooth' });
    } catch (e) {
      strip.innerHTML = '<div class="strip-loading">' + e.message + '</div>';
    }
  }

  async function selectDay(date) {
    selectedDate = date; selectedSlot = null;
    confirmBox.hidden = true;
    strip.querySelectorAll('.day-pill').forEach((p) => p.classList.toggle('active', p.dataset.date === date));
    await loadSlots(date);
  }

  async function loadSlots(date) {
    slotsTitle.textContent = longDate(date);
    slotsEl.innerHTML = '<span class="slots-empty">Chargement…</span>';
    try {
      const res = await api('slots.php?practitioner=' + pid + '&date=' + date);
      slotsTitle.textContent = longDate(date) + ' · ' + res.slots.length + ' créneau' + (res.slots.length > 1 ? 'x' : '') + ' disponible' + (res.slots.length > 1 ? 's' : '');
      slotsEl.innerHTML = '';
      if (!res.slots.length) { slotsEl.innerHTML = '<span class="slots-empty">Plus aucun créneau ce jour-là. Essayez un autre jour.</span>'; return; }
      res.slots.forEach((s, i) => {
        const b = document.createElement('button');
        b.type = 'button'; b.className = 'slot-btn'; b.textContent = s.time; b.style.setProperty('--i', i);
        b.setAttribute('role', 'option');
        b.addEventListener('click', () => pickSlot(s, b));
        slotsEl.appendChild(b);
      });
    } catch (e) { slotsEl.innerHTML = '<span class="slots-empty">' + e.message + '</span>'; }
  }

  function pickSlot(slot, btn) {
    selectedSlot = slot;
    slotsEl.querySelectorAll('.slot-btn').forEach((b) => b.classList.toggle('active', b === btn));
    summary.innerHTML = '<i class="bi bi-calendar-check"></i><div><strong>' + longDate(selectedDate) + ' à ' + slot.time + '</strong>' +
      '<small>Consultation de ' + duration + ' min · ' + (auto ? 'confirmation immédiate' : 'à confirmer par le praticien') + '</small></div>';
    errBox.hidden = true;
    confirmBox.hidden = false;
    confirmBox.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  }

  bookBtn.addEventListener('click', async () => {
    if (!selectedSlot) return;
    if (mode === 'guest') { location.href = loginUrl; return; }
    errBox.hidden = true;
    const label = bookBtn.innerHTML;
    bookBtn.disabled = true;
    bookBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Réservation…';
    try {
      const res = await api('book.php', { practitioner_id: Number(pid), start: selectedSlot.start, reason: reason.value.trim() });
      bookBtn.innerHTML = '<i class="bi bi-check-lg me-1"></i> Réservé !';
      setTimeout(() => { location.href = res.redirect; }, 500);
    } catch (e) {
      errBox.textContent = e.message; errBox.hidden = false;
      bookBtn.disabled = false; bookBtn.innerHTML = label;
      if (e.status === 409) { selectedSlot = null; confirmBox.hidden = true; toast('Mise à jour des créneaux…', 'err'); loadDays(true); }
    }
  });

  const step = (dir) => strip.scrollBy({ left: dir * 280, behavior: 'smooth' });
  document.getElementById('stripPrev').addEventListener('click', () => step(-1));
  document.getElementById('stripNext').addEventListener('click', () => step(1));

  loadDays(false);
})();

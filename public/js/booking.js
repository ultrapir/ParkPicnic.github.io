(() => {
  'use strict';

  if (window.__bookingScriptLoaded) return;
  window.__bookingScriptLoaded = true;

  const modal = document.getElementById('booking-modal');
  const form = (modal ? modal.querySelector('#booking-form') : null) || document.getElementById('bookForm');
  if (!modal || !form) return;

  const overlay = modal.querySelector('.modal-overlay');
  const closeBtns = modal.querySelectorAll('.modal-close');
  const openBtns = document.querySelectorAll('.book-now, #openBooking, [data-open-booking]');
  // const form = modal.querySelector('#booking-form');
  const submitBtn = form?.querySelector('button[type="submit"], input[type="submit"]');

  const calEl = modal.querySelector('#calendar');
  const calTitleEl = modal.querySelector('#cal-title');
  const prevBtn = modal.querySelector('.cal-prev');
  const nextBtn = modal.querySelector('.cal-next');

  const gazeboSelect = modal.querySelector('#gazebo');
  const dateInput = modal.querySelector('#date');
  const nameInput = modal.querySelector('#name');
  const phoneInput = modal.querySelector('#phone');
  const emailInput = modal.querySelector('#email');
  const commentInput = modal.querySelector('#comment');
  const qtyInput = modal.querySelector('#qty'); 

  const API = {
    gazebosJson: '/api/products.json',   
    gazebosPhp:  '/api/products.php',    
    booked:      '/api/booked.php',
    orderCreate: '/api/order-create.php' 
  };

  const today = new Date();
  today.setHours(0, 0, 0, 0);

  const state = {
    gazebos: [],          
    bookedSet: new Set(),   
    month: today.getMonth(),
    year: today.getFullYear(),
    selected: null
  };

  const FALLBACK_GAZEBOS = [
    { id: '1', name: 'Беседка №1', gazeboId: 1 },
    { id: '2', name: 'Беседка №2', gazeboId: 2 },
    { id: '3', name: 'Беседка №3', gazeboId: 3 },
    { id: '4', name: 'Беседка №4', gazeboId: 4 },
    { id: '5', name: 'Беседка №5', gazeboId: 5 },
    { id: '6', name: 'Беседка №6', gazeboId: 6 }
  ];

  function pad(n) { return String(n).padStart(2, '0'); }
  function fmtYMD(d) { return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`; }
  function monthRange(y, m) {
    const first = new Date(y, m, 1);
    const last = new Date(y, m + 1, 0);
    return { start: fmtYMD(first), end: fmtYMD(last) };
  }

  async function getJSON(url) {
    const r = await fetch(url, { cache: 'no-cache' });
    if (!r.ok) {
      let body = '';
      try { body = await r.text(); } catch {}
      throw new Error(`HTTP ${r.status} ${r.statusText} @ ${url} :: ${body}`);
    }
    return await r.json();
  }

  function normalizeGazebos(list) {
    const out = [];
    const arr = Array.isArray(list) ? list : [];
    for (let i = 0; i < arr.length; i++) {
      const it = arr[i] || {};
      const id  = (it.id != null ? it.id : (it.gazeboId != null ? it.gazeboId : null));
      const gid = Number(it.gazeboId != null ? it.gazeboId : (it.id != null ? it.id : 0)) || 0;
      const name = (it.title || it.name || ('ID ' + (id != null ? id : '')));
      if (id == null || !gid) continue;
      out.push({ id: String(id), name: String(name), gazeboId: gid });
    }
    return out;
  }

  // function normalizeGazebos(list) {
  //   const out = [];
  //   for (const it of Array.isArray(list) ? list : []) {
  //     const id = it.id ?? it.gazeboId ?? null;
  //     const name = it.title ?? it.name ?? `ID ${id ?? ''}`;
  //     const gid = Number(it.gazeboId ?? it.id ?? 0) || 0;
  //     if (id == null || !gid) continue;
  //     out.push({ id: String(id), name: String(name), gazeboId: gid });
  //   }
  //   return out;
  // }

  async function fetchGazebos() {
    try {
      const a = await getJSON(API.gazebosJson).catch(() => null);
      let list = normalizeGazebos(a || []);
      if (!list.length) {
        const b = await getJSON(API.gazebosPhp).catch(() => null);
        list = normalizeGazebos(b || []);
      }
      if (!list.length) {
        console.warn('[booking] gazebos empty, using fallback');
        list = FALLBACK_GAZEBOS.slice();
      }
      state.gazebos = list;
    } catch (err) {
      console.error('gazebos fetch failed:', err);
      state.gazebos = FALLBACK_GAZEBOS.slice();
    }
  }

  
  async function fetchBooked() {
    state.bookedSet = new Set();
    const gid = Number(gazeboSelect?.value || state.gazebos[0]?.gazeboId || 0);
    if (!gid) return;

    // const qty = Math.max(1, Number(qtyInput?.value || 1));
    // const { start, end } = monthRange(state.year, state.month);

    // const url = `${API.booked}?gazebo_id=${encodeURIComponent(gid)}&start=${encodeURIComponent(start)}&end=${encodeURIComponent(end)}&qty=${encodeURIComponent(qty)}`;
    const qty = Math.max(1, Number((qtyInput && qtyInput.value) ? qtyInput.value : 1));
    const range = monthRange(state.year, state.month);

    const url = API.booked + '?gazebo_id=' + encodeURIComponent(gid) +
      '&start=' + encodeURIComponent(range.start) +
      '&end='   + encodeURIComponent(range.end) +
      '&qty='   + encodeURIComponent(qty);
    try {
      const j = await getJSON(url);
      const disabled = Array.isArray(j.disabled) ? j.disabled : [];
      state.bookedSet = new Set(disabled);
    } catch (e) {
      console.warn('[booking] booked fetch failed:', e);
      state.bookedSet = new Set();
    }
  }

  // function renderGazebos() {
  //   if (!gazeboSelect) return;
  //   gazeboSelect.innerHTML = '';
  //   state.gazebos.forEach(g => {
  //     const opt = document.createElement('option');
  //     opt.value = String(g.gazeboId);
  //     opt.textContent = g.name;
  //     gazeboSelect.appendChild(opt);
  //   });
  // }

  function renderGazebos() {
    if (!gazeboSelect) return;
    gazeboSelect.innerHTML = '';
    for (let i = 0; i < state.gazebos.length; i++) {
      const g = state.gazebos[i];
      const opt = document.createElement('option');
      opt.value = String(g.gazeboId);
      opt.textContent = g.name;
      gazeboSelect.appendChild(opt);
    }
  }

  function renderCalendar() {
    if (!calEl || !calTitleEl) return;

    const y = state.year;
    const m = state.month;
    const first = new Date(y, m, 1);
    const last = new Date(y, m + 1, 0);
    const dim = last.getDate();
    const mon = ['Январь','Февраль','Март','Апрель','Май','Июнь','Июль','Август','Сентябрь','Октябрь','Ноябрь','Декабрь'];
    calTitleEl.textContent = `${mon[m]} ${y}`;

    const firstDow = (first.getDay() + 6) % 7; // 0=Пн

    let html = '';
    const dows = ['Пн','Вт','Ср','Чт','Пт','Сб','Вс'];
    for (let i = 0; i < dows.length; i++) html += '<div class="dow">' + dows[i] + '</div>';
    for (let i = 0; i < firstDow; i++) html += '<div class="day out" aria-hidden="true"></div>';
    // ['Пн','Вт','Ср','Чт','Пт','Сб','Вс'].forEach(d => html += `<div class="dow">${d}</div>`);
    // for (let i = 0; i < firstDow; i++) html += `<div class="day out" aria-hidden="true"></div>`;

    for (let day = 1; day <= dim; day++) {
      const d = new Date(y, m, day);
      d.setHours(0, 0, 0, 0);

      const ymd = fmtYMD(d);
      const isPast = d < today;
      const isBooked = state.bookedSet.has(ymd);
      const isSelected = state.selected && fmtYMD(state.selected) === ymd;

      const cls = ['day'];
      if (isPast || isBooked) cls.push('disabled');
      if (isSelected) cls.push('selected');

      html += `<button type="button" class="${cls.join(' ')}" data-date="${ymd}" ${(isPast || isBooked) ? 'disabled aria-disabled="true"' : ''}>${day}</button>`;
    }

    // calEl.innerHTML = html;
    // calEl.querySelectorAll('.day:not(.disabled)')
    //   .forEach(btn => {
    //     btn.addEventListener('click', () => {
    //       const ymd = btn.getAttribute('data-date');
    //       state.selected = new Date(ymd);
    //       state.selected.setHours(0, 0, 0, 0);
    //       calEl.querySelectorAll('.day.selected').forEach(b => b.classList.remove('selected'));
    //       btn.classList.add('selected');
    //       if (dateInput) dateInput.value = ymd;
    //     });
    //   });

    calEl.innerHTML = html;
    const btns = calEl.querySelectorAll('.day:not(.disabled)');
    for (let i = 0; i < btns.length; i++) {
      const btn = btns[i];
      btn.addEventListener('click', function () {
        const ymd = btn.getAttribute('data-date');
        state.selected = new Date(ymd);
        state.selected.setHours(0, 0, 0, 0);
        const selected = calEl.querySelectorAll('.day.selected');
        for (let j = 0; j < selected.length; j++) selected[j].classList.remove('selected');
        btn.classList.add('selected');
        if (dateInput) dateInput.value = ymd;
      });
    }
  }

  async function refreshCalendar() {
    await fetchBooked();
    if (state.selected && state.bookedSet.has(fmtYMD(state.selected))) {
      state.selected = null;
      if (dateInput) dateInput.value = '';
    }
    renderCalendar();
  }

  function prevMonth() {
    state.month--;
    if (state.month < 0) { state.month = 11; state.year--; }
    refreshCalendar().catch(console.warn);
  }

  function nextMonth() {
    state.month++;
    if (state.month > 11) { state.month = 0; state.year++; }
    refreshCalendar().catch(console.warn);
  }

  function formatPhone(raw) {
    let d = String(raw || '').replace(/\D/g, '');
    if (d.startsWith('8')) d = '7' + d.slice(1);
    if (!d.startsWith('7')) d = '7' + d;
    d = d.slice(0, 11);

    const p = (i, j) => d.slice(i, j);
    const out = `+7 (${p(1,4)}${p(1,4).length < 3 ? '_'.repeat(3 - p(1,4).length) : ''}) ${p(4,7)}${p(4,7).length < 3 ? '_'.repeat(3 - p(4,7).length) : ''}-${p(7,9)}${p(7,9).length < 2 ? '_'.repeat(2 - p(7,9).length) : ''}-${p(9,11)}${p(9,11).length < 2 ? '_'.repeat(2 - p(9,11).length) : ''}`;
    return out;
  }

  function cleanPhoneToE164(masked) {
    const d = String(masked || '').replace(/\D/g, '');
    if (d.length === 11) return '+' + d;
    return '';
  }

  function attachPhoneMask(input) {
    if (!input) return;
    const set = (v) => { input.value = v; };

    input.addEventListener('focus', () => {
      if (!input.value.trim()) set(formatPhone('+7'));
      requestAnimationFrame(() => {
        try { input.setSelectionRange(input.value.length, input.value.length); } catch {}
      });
    });

    input.addEventListener('input', () => set(formatPhone(input.value)));

    input.addEventListener('blur', () => {
      const e164 = cleanPhoneToE164(input.value);
      if (!e164) input.value = '';
    });
  }

  attachPhoneMask(phoneInput);

  function isEmail(v) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(String(v || '').toLowerCase());
  }

  let lastFocused = null;

  function openModal() {
    lastFocused = document.activeElement;
    modal.style.display = 'flex';
    modal.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';

    // [modal.querySelector('.modal-overlay'), modal.querySelector('.modal-panel')]
    //   .forEach((el, i) => {
    //     if (!el) return;
    //     el.classList.add('aos-fade-up');
    //     el.style.transitionDelay = `${i * 60}ms`;
    //     requestAnimationFrame(() =>
    //       requestAnimationFrame(() => el.classList.add('aos-animate'))
    //     );
    //   });

    // modal.querySelector('.modal-close')?.focus();

    const parts = [modal.querySelector('.modal-overlay'), modal.querySelector('.modal-panel')];
    for (let i = 0; i < parts.length; i++) {
      const el = parts[i];
      if (!el) continue;
      el.classList.add('aos-fade-up');
      el.style.transitionDelay = (i * 60) + 'ms';
      requestAnimationFrame(function(){ requestAnimationFrame(function(){ el.classList.add('aos-animate'); }); });
    }

    const closer = modal.querySelector('.modal-close');
    if (closer && typeof closer.focus === 'function') closer.focus();
  }

  function closeModal() {
    const parts = [modal.querySelector('.modal-overlay'), modal.querySelector('.modal-panel')];
    for (let i = 0; i < parts.length; i++) {
      const el = parts[i];
      if (!el) continue;
      el.style.transitionDelay = ((parts.length - 1 - i) * 28) + 'ms';
      el.classList.remove('aos-animate');
    }
    // parts.forEach((el, i) => {
    //   if (!el) return;
    //   el.style.transitionDelay = `${(parts.length - 1 - i) * 28}ms`;
    //   el.classList.remove('aos-animate');
    // });

    const wait = 360 + 28 * 2 + 40;
    setTimeout(() => {
      modal.style.display = 'none';
      modal.setAttribute('aria-hidden', 'true');
      document.body.style.overflow = '';
      if (lastFocused && typeof lastFocused.focus === 'function') lastFocused.focus();
    }, wait);
  }

  
  async function openWithPreselect({ gazeboId, gazeboName, date } = {}) {
    if (!state.gazebos.length) {
      await fetchGazebos();
      renderGazebos();
    }

    if (gazeboId || gazeboName) {
      let gid = gazeboId ? Number(gazeboId) : null;
      if (!gid && gazeboName) {
        const found = state.gazebos.find(g =>
          g.name === gazeboName || new RegExp(gazeboName, 'i').test(g.name)
        );
        gid = found?.gazeboId || null;
      }
      if (gid && gazeboSelect) {
        gazeboSelect.value = String(gid);
      }
    }

    state.month = today.getMonth();
    state.year = today.getFullYear();
    await refreshCalendar();

    if (date && /^\d{4}-\d{2}-\d{2}$/.test(date) && !state.bookedSet.has(date)) {
      state.selected = new Date(date);
      state.selected.setHours(0, 0, 0, 0);
      if (dateInput) dateInput.value = date;
      renderCalendar();
    }

    openModal();
  }

  async function prepareAndOpen() {
    try {
      form?.reset();
      state.selected = null;
      state.month = today.getMonth();
      state.year = today.getFullYear();

      if (!state.gazebos.length) {
        await fetchGazebos();
        renderGazebos();
      }
      await refreshCalendar();
      openModal();
    } catch (err) {
      console.error('[booking] prepare failed:', err);
      alert('Не удалось открыть форму бронирования. Проверьте подключение к сети и обновите страницу.');
    }
  }

  let submitting = false;
  function setSubmitting(flag) {
    submitting = flag;
    if (!submitBtn) return;
    if (flag) {
      submitBtn.dataset.prevText = submitBtn.textContent || submitBtn.value || '';
      const txt = 'Отправка...';
      if (submitBtn.tagName === 'BUTTON') submitBtn.textContent = txt; else submitBtn.value = txt;
      submitBtn.disabled = true;
    } else {
      const prev = submitBtn.dataset.prevText || '';
      if (submitBtn.tagName === 'BUTTON') submitBtn.textContent = prev; else submitBtn.value = prev;
      submitBtn.disabled = false;
    }
  }


  // Отправка заявки в /api/order-create.php
  async function onSubmit(e) {
    e.preventDefault();
    if (submitting) return;      
    setSubmitting(true);

    const payload = {
      gazebo_id: gazeboSelect?.value || (state.gazebos[0]?.gazeboId ? String(state.gazebos[0].gazeboId) : ''),
      date: dateInput?.value || '',
      name: nameInput?.value.trim() || '',
      phone: cleanPhoneToE164(phoneInput?.value || ''),
      email: emailInput?.value.trim() || '',
      comment: commentInput?.value.trim() || '',
      qty: String(Math.max(1, Number(qtyInput?.value || 1))) // Новое
    };

    if (!payload.date) { alert('Пожалуйста, выберите дату.'); setSubmitting(false); return; }
    if (!payload.name) { alert('Укажите ваше имя.'); setSubmitting(false); return; }
    if (!payload.phone && !payload.email) { alert('Укажите телефон или e‑mail.'); setSubmitting(false); return; }
    if (payload.email && !isEmail(payload.email)) { alert('Укажите корректный e‑mail.'); setSubmitting(false); return; }

    const body = new URLSearchParams();
    Object.entries(payload).forEach(([k, v]) => body.set(k, v));


    try {
      const resp = await fetch(API.orderCreate, { method: 'POST', body });
      const json = await resp.json().catch(() => ({}));
      if (resp.ok && json && json.success) {
        alert('Спасибо! Заявка отправлена. Номер: ' + json.order_id);
        await refreshCalendar();
        closeModal();
      } else {
        const errText = (json && (json.error || json.message)) || ('HTTP ' + resp.status);
        alert('Ошибка отправки: ' + errText);
      }
    } catch (err) {
      console.error('order-create network error:', err);
      alert('Сеть недоступна. Попробуйте позже.');
    } finally {
      setSubmitting(false);
    }
  }

  form?.addEventListener('submit', onSubmit);

  // Отправка заявки в /api/book.php (письмо клиенту)
  // async function onSubmit(e) {
  //   e.preventDefault();
  //   if (submitting) return; 
  //   setSubmitting(true);
  //   const payload = {
  //     gazebo_id: (gazeboSelect && gazeboSelect.value) ? gazeboSelect.value : (state.gazebos[0] ? String(state.gazebos[0].gazeboId) : ''),
  //     date: (dateInput && dateInput.value) ? dateInput.value : '',
  //     name: (nameInput && nameInput.value ? nameInput.value : '').trim(),
  //     phone: cleanPhoneToE164(phoneInput && phoneInput.value ? phoneInput.value : ''),
  //     email: (emailInput && emailInput.value ? emailInput.value : '').trim(),
  //     comment: (commentInput && commentInput.value ? commentInput.value : '').trim(),
  //     qty: String(Math.max(1, Number(qtyInput && qtyInput.value ? qtyInput.value : 1)))
  //   };

    
  //   if (!payload.date) { alert('Пожалуйста, выберите дату.'); setSubmitting(false); return; }
  //   if (!payload.name) { alert('Укажите ваше имя.'); setSubmitting(false); return; }
  //   if (!payload.email) { alert('Укажите e‑mail для подтверждения заявки.'); setSubmitting(false); return; }
  //   if (!isEmail(payload.email)) { alert('Укажите корректный e‑mail.'); setSubmitting(false); return; }

    
  //   const body = new URLSearchParams();
  //   body.set('gazebo_id', payload.gazebo_id);
  //   body.set('date', payload.date);
  //   body.set('name', payload.name);
  //   if (payload.phone) body.set('phone', payload.phone);
  //   body.set('email', payload.email);
  //   if (payload.comment) {
  //     body.set('message', payload.comment); 
  //     body.set('comment', payload.comment); 
  //   }
  //   if (payload.qty) body.set('qty', payload.qty);

    // CSRF токен из <meta>
    // const form  = e.currentTarget;
    // const token = document.querySelector('meta[name="csrf-token"]')?.content || '';
    // const formData = new FormData(form);
    // if (token) formData.set('csrf_token', token);
    

    // const headers = { 'X-Requested-With': 'XMLHttpRequest' }; 
    // if (token) headers['X-CSRF-Token'] = token;


  //   try {
  //     const resp = await fetch(API.orderCreate, {
  //     method: 'POST',
  //     credentials: 'same-origin',
  //     headers,
  //     body: formData
  //   });
  //     const json = await resp.json().catch(function(){ return {}; });
  //     const ok = resp.ok && json && (json.ok === true || json.success === true);

  //     if (ok) {
  //       const id = json.bookingId || json.order_id || '';
  //       alert('Спасибо! Заявка отправлена.' + (id ? (' Номер: ' + id) : ''));
  //       await refreshCalendar();
  //       closeModal();
  //     } else {
  //       const errText = (json && (json.message || json.error)) || ('HTTP ' + resp.status);
  //       alert('Ошибка отправки: ' + errText);
  //     }
  //   } catch (err) {
  //     console.error('book.php network error:', err);
  //     alert('Сеть недоступна. Попробуйте позже.');
  //   } finally {
  //     setSubmitting(false);
  //   }
  // }

  // if (form) form.addEventListener('submit', onSubmit);

  // const opens = Array.prototype.slice.call(openBtns);
  // opens.forEach(function(btn){ btn.addEventListener('click', function(){
  //   prepareAndOpen().catch(function(err){
  //     console.error(err);
  //     alert('Не удалось открыть форму бронирования. Повторите попытку позже.');
  //   });
  // }); });

  openBtns.forEach(btn => btn.addEventListener('click', () => {
    prepareAndOpen().catch(err => {
      console.error(err);
      alert('Не удалось открыть форму бронирования. Повторите попытку позже.');
    });
  }));

  overlay?.addEventListener('click', closeModal);
  closeBtns.forEach(btn => btn.addEventListener('click', closeModal));

  // if (overlay) overlay.addEventListener('click', closeModal);
  // const closers = Array.prototype.slice.call(closeBtns);
  // closers.forEach(function(btn){ btn.addEventListener('click', closeModal); });

  document.addEventListener('keydown', (e) => {
    if (modal.getAttribute('aria-hidden') === 'false' && e.key === 'Escape') closeModal();
  });

  // prevBtn?.addEventListener('click', prevMonth);
  // nextBtn?.addEventListener('click', nextMonth);
  // gazeboSelect?.addEventListener('change', () => refreshCalendar().catch(console.warn));
  // form?.addEventListener('submit', onSubmit);

  if (prevBtn) prevBtn.addEventListener('click', prevMonth);
  if (nextBtn) nextBtn.addEventListener('click', nextMonth);
  if (gazeboSelect) gazeboSelect.addEventListener('change', function(){ refreshCalendar().catch(console.warn); });

  // window.openBooking = (opts) => openWithPreselect(opts).catch(console.error);
  window.openBooking = function (opts) { openWithPreselect(opts || {}).catch(console.error); };
})();
(function(){
  
  const CFG = Object.assign({
    apiUrl: '/api/order-create.php',
    
    i18n: {
      loading: 'Отправка...',
      success: 'Заявка отправлена! Мы свяжемся с вами.',
      error: 'Не удалось отправить заявку. Проверьте поля или попробуйте позже.',
      required: 'Заполните обязательные поля',
    }
  }, window.ParkPicnicBookingConfig || {});

  
  const STYLE = `
  .pp-invalid{outline:2px solid #dc2626; outline-offset:1px}
  .pp-status{margin:10px 0;padding:10px 12px;border-radius:10px;border:1px solid #222;font:14px/1.4 system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Arial,sans-serif}
  .pp-status.pp-ok{background:#0e1d12;color:#b8f3c5;border-color:#1f3a25}
  .pp-status.pp-err{background:#2a0f10;color:#f3b8bd;border-color:#3a1f21}
  `;

  (function(){
  
  if (window.__ppBookingInitLoaded) return;
  window.__ppBookingInitLoaded = true;

  const CFG = Object.assign({
    apiUrl: '/api/order-create.php',
    i18n: { /* ... */ }
  }, window.ParkPicnicBookingConfig || {});

  
  if (document.getElementById('booking-form')) {
    console.info('[booking-init] Обнаружен booking.js (#booking-form). Пропускаю авто-привязку.');
    return;
  }

  (function injectStyles(){
    if (document.getElementById('pp-booking-style')) return;
    const s = document.createElement('style');
    s.id = 'pp-booking-style';
    s.textContent = STYLE;
    document.head.appendChild(s);
  })();

  
  function norm(s){ return (s||'').toLowerCase(); }
  function elText(el){ return norm(el?.textContent||''); }
  function matchesText(el, substr){ return elText(el).includes(norm(substr)); }

  function byNames(form, arr) {
    const sel = arr.map(n => `[name*="${n}"]`).join(','); 
    return form.querySelector(sel);
  }
  function byType(form, type) { return form.querySelector(`input[type="${type}"]`); }
  function byPlaceholder(form, part) {
    return form.querySelector(`input[placeholder*="${part}"], textarea[placeholder*="${part}"]`);
  }
  function byLabelText(form, textPart) {
    const lbls = Array.from(form.querySelectorAll('label'));
    for (const l of lbls) {
      if (matchesText(l, textPart)) {
        const forId = l.getAttribute('for');
        if (forId) {
          const inp = form.querySelector('#'+CSS.escape(forId));
          if (inp) return inp;
        }
        const nested = l.querySelector('input, textarea, select');
        if (nested) return nested;
      }
    }
    return null;
  }

  function findSubmitBtn(form){
    
    let btn = form.querySelector('button[type="submit"], input[type="submit"]');
    if (btn) return btn;
    
    const candidates = Array.from(form.querySelectorAll('button, input[type="button"], input[type="submit"]'));
    btn = candidates.find(b => matchesText(b, 'отправить заявку'));
    if (btn) return btn;
    
    return candidates[0] || null;
  }

  function findBookingForm(){
    const forms = Array.from(document.querySelectorAll('form'));
    for (const f of forms) {
      if (f.id === 'booking-form') continue;                 
      if (f.hasAttribute('data-booking-ignore')) continue;   
      const btn = findSubmitBtn(f);
      if (!btn) continue;
      const hasAnyField =
        f.querySelector('input, textarea, select') &&
        /отправ/.test((btn.textContent||btn.value||'').toLowerCase());
      if (hasAnyField) return f;
    }
    
    for (const f of forms) {
      if (f.id === 'booking-form' || f.hasAttribute('data-booking-ignore')) continue;
      const btn = findSubmitBtn(f);
      if (btn) return f;
    }
    return null;
  }

  function fieldMap(form){
    
    let gazeboId = byNames(form, ['gazebo_id','gazeboId','gazebo']);
    if (!gazeboId) {
      
      gazeboId = document.createElement('input');
      gazeboId.type = 'hidden';
      gazeboId.name = 'gazebo_id';
      form.appendChild(gazeboId);
    }

    const date = byType(form,'date') || byNames(form,['date','data']) || byPlaceholder(form,'Дата') || byLabelText(form,'Дата');

    const name = byNames(form,['name','fio','fullname']) || byPlaceholder(form,'имя') || byLabelText(form,'имя');
    const phone = byType(form,'tel') || byNames(form,['phone','tel','phon']) || byPlaceholder(form,'Телефон') || byLabelText(form,'Телефон');
    const email = byType(form,'email') || byNames(form,['email','mail']) || byPlaceholder(form,'E‑mail') || byPlaceholder(form,'Email') || byLabelText(form,'E‑mail') || byLabelText(form,'Email');
    const comment = form.querySelector('textarea[name*="comment"], textarea[name*="message"], textarea') || byPlaceholder(form,'Комментарий') || byLabelText(form,'Комментарий');

    const submit = findSubmitBtn(form);

    return { form, gazeboId, date, name, phone, email, comment, submit };
  }

  function setInvalid(el, flag){
    if (!el) return;
    el.classList.toggle('pp-invalid', !!flag);
  }

  function clearInvalids(map){
    ['gazeboId','date','name','phone','email','comment'].forEach(k => setInvalid(map[k], false));
  }

  function showStatus(form, msg, ok){
    
    let box = form.querySelector('.pp-status');
    if (!box) {
      box = document.createElement('div');
      box.className = 'pp-status';
      
      const submit = findSubmitBtn(form);
      if (submit && submit.parentElement) {
        submit.parentElement.insertBefore(box, submit);
      } else {
        form.appendChild(box);
      }
    }
    box.className = 'pp-status ' + (ok ? 'pp-ok' : 'pp-err');
    box.textContent = msg;
  }

  function parseGazeboIdFromText(s){
    const m = String(s||'').match(/№\s*(\d+)/i) || String(s||'').match(/(\d+)/);
    return m ? parseInt(m[1],10) : 0;
  }

  function resolveGazeboId(map){
    
    let val = (map.gazeboId && ('value' in map.gazeboId)) ? map.gazeboId.value : '';
    if (val && /^\d+$/.test(val)) return parseInt(val,10);

    
    if (map.gazeboId && map.gazeboId.tagName === 'SELECT') {
      const opt = map.gazeboId.options[map.gazeboId.selectedIndex];
      if (opt) {
        
        const v = opt.value;
        if (/^\d+$/.test(v)) return parseInt(v,10);
        const fromText = parseGazeboIdFromText(opt.textContent);
        if (fromText > 0) return fromText;
      }
    }

    
    const candidate = byPlaceholder(map.form, 'Беседка') || byLabelText(map.form, 'Беседка') || byNames(map.form, ['gazebo','place','object']);
    if (candidate) {
      const fromText = parseGazeboIdFromText(candidate.value || candidate.textContent);
      if (fromText > 0) return fromText;
    }

   
    return 0;
  }

  function setSubmitState(btn, loading){
    if (!btn) return;
    if (loading) {
      btn.dataset.prevText = btn.textContent || btn.value || '';
      const txt = CFG.i18n.loading;
      if (btn.tagName === 'BUTTON') btn.textContent = txt;
      else btn.value = txt;
      btn.disabled = true;
    } else {
      const prev = btn.dataset.prevText || '';
      if (btn.tagName === 'BUTTON') btn.textContent = prev;
      else btn.value = prev;
      btn.disabled = false;
    }
  }

  
  function init(){
    const form = findBookingForm();
    if (!form) {
      console.warn('[booking-init] Форма бронирования не найдена.');
      window.openBooking = function(opts){ console.warn('[booking-init] openBooking: форма не найдена', opts); };
      return;
    }

    const map = fieldMap(form);

    if (form.dataset.ppBound === '1') return;
    form.dataset.ppBound = '1';


    
    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      clearInvalids(map);

      
      const gazebo_id = resolveGazeboId(map);
      const date = map.date && ('value' in map.date) ? String(map.date.value).trim() : '';
      const name = map.name && ('value' in map.name) ? String(map.name.value).trim() : '';
      const phone = map.phone && ('value' in map.phone) ? String(map.phone.value).trim() : '';
      const email = map.email && ('value' in map.email) ? String(map.email.value).trim() : '';
      const comment = map.comment && ('value' in map.comment) ? String(map.comment.value).trim() : '';

      
      let valid = true;
      if (!gazebo_id) { setInvalid(map.gazeboId, true); valid = false; }
      if (!date) { setInvalid(map.date, true); valid = false; }
      if (!name) { setInvalid(map.name, true); valid = false; }
      if (!phone && !email) { setInvalid(map.phone, true); setInvalid(map.email, true); valid = false; }

      if (!valid) {
        showStatus(form, CFG.i18n.required, false);
        return;
      }

     
      setSubmitState(map.submit, true);
      try{
        const body = new URLSearchParams();
        body.set('gazebo_id', String(gazebo_id));
        body.set('date', date);
        body.set('name', name);
        body.set('phone', phone);
        body.set('email', email);
        body.set('comment', comment);

        const r = await fetch(CFG.apiUrl, { method:'POST', body });
        const j = await r.json().catch(()=> ({}));
        if (r.ok && j && j.success) {
          showStatus(form, CFG.i18n.success, true);
         
        } else {
          const msg = j && j.error ? j.error : CFG.i18n.error;
          showStatus(form, msg, false);
        }
      } catch(err){
        showStatus(form, CFG.i18n.error, false);
      } finally {
        setSubmitState(map.submit, false);
      }
    });

   
    window.openBooking = function(opts){
      try{
        const o = opts || {};
        
        if (o.gazeboId && map.gazeboId) {
          if (map.gazeboId.tagName === 'SELECT') {
           
            const opts = Array.from(map.gazeboId.options);
            let matched = opts.find(x => String(x.value) === String(o.gazeboId));
            if (!matched) {
              matched = opts.find(x => parseGazeboIdFromText(x.textContent) === Number(o.gazeboId));
            }
            if (matched) map.gazeboId.value = matched.value;
          } else {
            map.gazeboId.value = String(o.gazeboId);
          }
        }
      
        if (!o.gazeboId && o.gazeboName && map.gazeboId && map.gazeboId.tagName === 'SELECT') {
          const opts = Array.from(map.gazeboId.options);
          const matched = opts.find(x => elText(x).includes(norm(o.gazeboName)));
          if (matched) map.gazeboId.value = matched.value;
        }
       
        if (o.date && map.date && 'value' in map.date) {
          map.date.value = o.date;
        }

        
        form.scrollIntoView({ behavior:'smooth', block:'start' });
        setTimeout(() => { map.name?.focus?.(); }, 300);
      } catch(e){
        console.warn('[booking-init] openBooking error:', e);
      }
    };

    console.log('[booking-init] Инициализировано');
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init, { once:true });
  } else {
    init();
  }
})
})();
(function(){
  console.log('[availability] init');

  const selGazebo = document.getElementById('bookingGazebo'); // <select>
  const inpDate   = document.getElementById('bookingDate');   // <input type="date"> или flatpickr input
  const inpQty    = document.getElementById('bookingQty');    // <input type="number">
  const infoEl    = document.getElementById('bookingAvailInfo'); // <span> для текста
  const submitBtn = document.getElementById('bookingSubmit'); // <button>

  if (!selGazebo || !inpDate || !inpQty || !infoEl || !submitBtn) {
    console.warn('[availability] elements not found, skip wiring');
    return;
  }

  async function jsonGet(url){
    const r = await fetch(url, {cache:'no-cache'});
    if (!r.ok) throw new Error('HTTP '+r.status+' '+await r.text());
    return await r.json();
  }

  async function updateAvailability() {
    const gazeboId = parseInt(selGazebo.value || '0', 10);
    const date = (inpDate.value || '').trim();
    if (!gazeboId || !date) {
      infoEl.textContent = 'Выберите беседку и дату';
      inpQty.max = 1;
      submitBtn.disabled = true;
      return;
    }
    try {
      const a = await jsonGet(`/api/availability.php?gazebo_id=${encodeURIComponent(gazeboId)}&date=${encodeURIComponent(date)}`);
      infoEl.textContent = `Свободно: ${a.available} из ${a.total}`;
      inpQty.min = 1;
      inpQty.max = Math.max(0, a.available);
      if (+inpQty.value < 1) inpQty.value = 1;
      if (+inpQty.value > a.available) inpQty.value = a.available;

      submitBtn.disabled = (a.available <= 0);
    } catch (e) {
      console.error('[availability] fetch failed', e);
      infoEl.textContent = 'Не удалось получить доступность';
      submitBtn.disabled = true;
    }
  }

  // Опционально: выключаем полностью занятые даты (пример для flatpickr)
  async function disableFullyBookedDates() {
    const gazeboId = parseInt(selGazebo.value || '0', 10);
    if (!gazeboId) return;

    try {
      const d = await jsonGet(`/api/availability-dates.php?gazebo_id=${encodeURIComponent(gazeboId)}&days=180`);
      // Если используете flatpickr:
      if (inpDate._flatpickr && Array.isArray(d.fully_booked)) {
        const disabled = d.fully_booked.slice();
        inpDate._flatpickr.set('disable', disabled);
      }
    } catch (e) {
      console.warn('[availability] flatpickr disable failed', e);
    }
  }

  selGazebo.addEventListener('change', () => { updateAvailability(); disableFullyBookedDates(); });
  inpDate.addEventListener('change', () => { updateAvailability(); });

  // Первичная инициализация
  updateAvailability();
  disableFullyBookedDates();

  // На отправке формы гарантируем qty в допустимых пределах
  const form = submitBtn.closest('form');
  if (form) {
    form.addEventListener('submit', async (e) => {
      const max = parseInt(inpQty.max || '0', 10);
      const val = parseInt(inpQty.value || '1', 10);
      if (max <= 0 || val > max) {
        e.preventDefault();
        await updateAvailability();
        alert('Недостаточно свободных беседок на выбранную дату.');
      }
    });
  }
})();
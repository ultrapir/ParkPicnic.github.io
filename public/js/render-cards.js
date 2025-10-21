(function(){
  const grid = document.getElementById('gazebo-grid');
  if (!grid) return;

  grid.classList.add('tours-container');

  const placeholder = '/img/no-photo.png';
  const esc = s => String(s ?? '').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));

  function cardHTML(p){
    const cover = p.cover || (p.images && p.images[0]) || placeholder;
    return `
      <div class="tour-card" data-aos="fade-up" data-id="${esc(p.gazeboId)}" title="${esc(p.title)}">
        <img class="tour-img" src="${esc(cover)}" alt="${esc(p.title)}" loading="lazy">
        <div class="tour-body">
            <h3 class="tour-name">${esc(p.title)}</h3>
            <p class="tour-action">Подробнее...</p>
        </div>
      </div>`;
  }

  async function fetchProducts() {
    const endpoints = ['/api/products.php', '/products.php', 'products.php'];
    for (const u of endpoints) {
      try {
        const res = await fetch(u + '?ts=' + Date.now(), { headers: { 'Accept': 'application/json' } });
        if (res.ok) return res.json();
      } catch (_) {}
    }
    throw new Error('Не найден endpoint products.php');
  }

  async function loadGazebos(){
    try{
      const data = await fetchProducts();
      if (!Array.isArray(data) || data.length === 0) {
        grid.innerHTML = '<p class="muted" style="opacity:.7">Пока нет активных беседок.</p>';
        return;
      }
      grid.innerHTML = data.map(cardHTML).join('');
    }catch(e){
      grid.innerHTML = '<p class="muted" style="opacity:.7">Не удалось загрузить список беседок.</p>';
      console.error(e);
    }
  }

  function goToBooking(id, title){
    // Сохраняем выбранную беседку в адресной строке (не влияет на скролл)
    try {
        const url = new URL(location.href);
        url.searchParams.set('gazeboId', id);
        history.replaceState(null, '', url);
    } catch(_) {}

    prefillBooking(id, title);  // заполнить
    scrollToBooking();          // прокрутить

    }

  function prefillBooking(id, title){
  // Проставляем в форму
  const field = document.getElementById('booking-gazebo')
            || document.querySelector('select[name="gazeboId"], [name="gazeboId"], [name="gazebo"]');
  if (field) {
    if (field.tagName === 'SELECT') {
      let opt = Array.from(field.options).find(o => o.value == id);
      if (!opt) {
        opt = new Option(title || ('Беседка #' + id), id, true, true);
        field.add(opt);
      }
      field.value = String(id);
    } else {
      field.value = String(id);
    }
  }
    const selected = document.getElementById('booking-selected');
    if (selected) selected.textContent = title ? `Вы выбрали: ${title}` : `Выбрана беседка #${id}`;
    }


    function scrollToBooking(){
        const section = document.getElementById('booking') || document.querySelector('.book-section');
        if (section && section.scrollIntoView) section.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

  // Клик по карточке
  grid.addEventListener('click', (e) => {
    const card = e.target.closest('.tour-card');
    if (!card) return;
    const id = card.getAttribute('data-id');
    const title = card.querySelector('.tour-name')?.textContent?.trim();
    if (id) goToBooking(id, title);
  });

  // Если пришли на страницу уже с ?gazeboId=...
    document.addEventListener('DOMContentLoaded', () => {
        const params = new URLSearchParams(location.search);
        const gid = params.get('gazeboId');
        if (gid) prefillBooking(gid);
    });

  loadGazebos();
  setInterval(loadGazebos, 30000);
})();
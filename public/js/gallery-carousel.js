document.addEventListener('DOMContentLoaded', function () {
    
    var galleryContainer = document.getElementById('travel');
    if (!galleryContainer) return;

    var jsonPath = '/api/images.php'; 

    
    var GAP = 12;                  
    var BREAKPOINTS = {            
      lg: { min: 1200, perView: 3 },
      md: { min: 768,  perView: 2 },
      sm: { min: 0,    perView: 1 },
    };

   
  function buildCarousel(images) {
    galleryContainer.classList.add('carousel-gal');
    galleryContainer.setAttribute('tabindex', '0'); 

   
    var track = document.createElement('div');
    track.className = 'carousel-gal-track';
    track.style.gap = GAP + 'px';

    var prevBtn = document.createElement('button');
    prevBtn.className = 'carousel-gal-nav prev-gal';
    prevBtn.setAttribute('aria-label', 'Предыдущие');
    prevBtn.type = 'button';
    prevBtn.innerHTML = '‹';

    var nextBtn = document.createElement('button');
    nextBtn.className = 'carousel-gal-nav next-gal';
    nextBtn.setAttribute('aria-label', 'Следующие');
    nextBtn.type = 'button';
    nextBtn.innerHTML = '›';

    var dots = document.createElement('div');
    dots.className = 'carousel-gal-dots';

    
    images.forEach(function (item, index) {
      if (!item || !item.src) return;
      var slide = document.createElement('div');
      slide.className = 'carousel-gal-slide';

      var fig = document.createElement('figure');
      fig.className = 'travel-item';
      if (item.aos) fig.setAttribute('data-aos', item.aos);

      var img = document.createElement('img');
      img.src = item.src;
      img.alt = (item.alt && item.alt.trim()) ? item.alt : ('Фото ' + (index + 1));
      img.loading = 'lazy';
      img.addEventListener('error', function () {
        img.style.filter = 'grayscale(1)';
        img.alt = 'Изображение недоступно';
      });

      fig.appendChild(img);
      slide.appendChild(fig);
      track.appendChild(slide);
    });

    galleryContainer.innerHTML = '';
    galleryContainer.appendChild(track);
    galleryContainer.appendChild(prevBtn);
    galleryContainer.appendChild(nextBtn);
    galleryContainer.appendChild(dots);

    
    var state = {
      current: 0,
      perView: 1,
      slideWidth: 0,
      slides: Array.from(track.children),
      pages: 1
    };

    function calcPerView() {
      var w = galleryContainer.clientWidth;
      if (w >= BREAKPOINTS.lg.min) state.perView = BREAKPOINTS.lg.perView;
      else if (w >= BREAKPOINTS.md.min) state.perView = BREAKPOINTS.md.perView;
      else state.perView = BREAKPOINTS.sm.perView;

      state.pages = Math.max(1, Math.ceil(state.slides.length / state.perView));
    }

    function layout() {
      calcPerView();
      var containerWidth = galleryContainer.clientWidth;
      var sw = Math.max(50, Math.floor((containerWidth - GAP * (state.perView - 1)) / state.perView));
      state.slideWidth = sw;
      state.slides.forEach(function (sl) { sl.style.width = sw + 'px'; });

     
      var maxIndex = Math.max(0, state.slides.length - state.perView);
      if (state.current > maxIndex) state.current = maxIndex;

      buildDots();
      update();
    }

    function update() {
      var offset = state.current * (state.slideWidth + GAP);
      track.style.transform = 'translate3d(' + (-offset) + 'px,0,0)';
      updateButtons();
      updateDots();
    }

    function updateButtons() {
      prevBtn.disabled = (state.current <= 0);
      nextBtn.disabled = (state.current >= state.slides.length - state.perView);
    }

    function pageIndex() {
      return Math.floor(state.current / state.perView);
    }

    function buildDots() {
      dots.innerHTML = '';
      for (var p = 0; p < state.pages; p++) {
        var b = document.createElement('button');
        b.type = 'button';
        b.className = 'dot';
        (function (page) {
          b.addEventListener('click', function () {
            state.current = Math.min(state.slides.length - state.perView, page * state.perView);
            update();
          });
        })(p);
        dots.appendChild(b);
      }
    }

    function updateDots() {
      var children = Array.from(dots.children);
      var active = pageIndex();
      children.forEach(function (d, i) {
        if (i === active) d.classList.add('active');
        else d.classList.remove('active');
      });
    }

    
    prevBtn.addEventListener('click', function () {
      state.current = Math.max(0, state.current - state.perView);
      update();
    });
    nextBtn.addEventListener('click', function () {
      state.current = Math.min(state.slides.length - state.perView, state.current + state.perView);
      update();
    });

    
    galleryContainer.addEventListener('keydown', function (e) {
      if (e.key === 'ArrowLeft') prevBtn.click();
      if (e.key === 'ArrowRight') nextBtn.click();
    });

   
    var ro = new ResizeObserver(layout);
    ro.observe(galleryContainer);

   
    layout();

    
    if (window.AOS && typeof window.AOS.init === 'function') window.AOS.init();
  }

  
  fetch(jsonPath, { cache: 'no-cache' })
    .then(function (resp) { if (!resp.ok) throw new Error('HTTP ' + resp.status); return resp.json(); })
    .then(function (data) {
      if (!Array.isArray(data) || data.length === 0) {
        galleryContainer.innerHTML = '<div class="muted">Пока нет фотографий</div>';
        return;
      }
      buildCarousel(data);
    })
    .catch(function (err) {
      console.warn('Не удалось загрузить images.json. Ошибка:', err);
      galleryContainer.innerHTML = '<div class="muted">Галерея временно недоступна</div>';
    });
});
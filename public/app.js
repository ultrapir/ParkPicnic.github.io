'use strict';

(function () {
 
  if (window.AOS && typeof window.AOS.init === 'function') {
    window.AOS.init();
  }

  
  if (window.location.hash === '#booking') {
    try { history.replaceState(null, '', location.pathname + location.search); } catch (e) {}
  }

  // ------------------------------ СЛАЙДЕР ФОНА ------------------------------
  var sliderImgs = ['forest.png', 'beach.png', 'prud.png', 'smotr.png', 'tropa.png', 'winter_forest.png'];
  var sliderImage = document.querySelector('.background-image');
  var sliderGrids = Array.prototype.slice.call(document.querySelectorAll('.grid-item'));
  var currentImage = 0;

  if (sliderImage && sliderGrids.length) {
    function changeSliderImage() {
      sliderGrids.forEach(function (gridItem, index) {
        setTimeout(function () {
          gridItem.classList.remove('hide');
          setTimeout(function () {
            if (index === sliderGrids.length - 1) {
              currentImage = (currentImage + 1) % sliderImgs.length;
              sliderImage.src = 'img/' + sliderImgs[currentImage];
              sliderGrids.forEach(function (item, i) {
                setTimeout(function () { item.classList.add('hide'); }, i * 100);
              });
            }
          }, 100);
        }, index * 100);
      });
    }
    setInterval(changeSliderImage, 5000);
  }

  // ------------------------------ НАВБАР ------------------------------
  var navbar = document.querySelector('.navbar');
  if (navbar) {
    function onScroll() { navbar.classList.toggle('bg', window.scrollY >= 188); }
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();
  }

  // ------------------------------ DOMContentLoaded ------------------------------
  document.addEventListener('DOMContentLoaded', function () {
    // ---------------- Gallery (/api/images.php) ----------------
    // var galleryContainer = document.getElementById('travel');
    // if (galleryContainer) {
    //   var jsonPath = '/api/images.php';

    //   function renderImages(images) {
    //     var frag = document.createDocumentFragment();
    //     images.forEach(function (item, index) {
    //       if (!item || !item.src) return;
    //       var fig = document.createElement('figure');
    //       fig.className = 'travel-item';
    //       if (item.aos) fig.setAttribute('data-aos', item.aos);
    //       var img = document.createElement('img');
    //       img.src = item.src;
    //       img.alt = (item.alt && item.alt.trim()) ? item.alt : ('Фото ' + (index + 1));
    //       img.loading = 'lazy';
    //       img.addEventListener('error', function () {
    //         img.style.filter = 'grayscale(1)';
    //         img.alt = 'Изображение недоступно';
    //       });
    //       fig.appendChild(img);
    //       frag.appendChild(fig);
    //     });
    //     galleryContainer.innerHTML = '';
    //     galleryContainer.appendChild(frag);
    //     if (window.AOS && typeof window.AOS.init === 'function') window.AOS.init();
    //   }

    //   (function loadJson() {
    //     fetch(jsonPath, { cache: 'no-cache' })
    //       .then(function (resp) { if (!resp.ok) throw new Error('HTTP ' + resp.status); return resp.json(); })
    //       .then(function (data) {
    //         if (!Array.isArray(data) || data.length === 0) {
    //           galleryContainer.innerHTML = '<div class="muted">Пока нет фотографий</div>';
    //           return;
    //         }
    //         renderImages(data);
    //       })
    //       .catch(function (err) {
    //         console.warn('Не удалось загрузить images.json. Ошибка:', err);
    //         galleryContainer.innerHTML = '<div class="muted">Галерея временно недоступна</div>';
    //       });
    //   })();
    // }

    // ---------------- Modal + Carousel (products) ----------------
    var cardsContainer = document.querySelector('.tours-container');
    var modal = document.getElementById('product-modal');
    if (!cardsContainer || !modal) return;

    var overlay = modal.querySelector('.modal-overlay');
    var closeBtn = modal.querySelector('.modal-close');

    var state = {
      productsMap: null,
      cache: {},
      carousel: null,
      currentProduct: null,
      lastFocused: null
    };

    function showLoading() {
      var titleEl = modal.querySelector('#modal-title');
      if (titleEl) titleEl.textContent = 'Загрузка...';
      var galleryEl = modal.querySelector('#modal-gallery');
      if (galleryEl) galleryEl.innerHTML = '' +
        '<div class="modal-loading" role="status" aria-live="polite" aria-busy="true">' +
        '  <svg class="spinner" width="44" height="44" viewBox="0 0 44 44" aria-hidden="true">' +
        '    <g fill="none" fill-rule="evenodd" stroke-width="4">' +
        '      <circle cx="22" cy="22" r="18" stroke="#e6e6e6"></circle>' +
        '      <path d="M40 22c0-9.94-8.06-18-18-18" stroke="#007bff" stroke-linecap="round"></path>' +
        '    </g>' +
        '  </svg>' +
        '  <div class="loading-text">Загрузка...</div>' +
        '</div>';
      var priceEl = modal.querySelector('#modal-price');
      if (priceEl) priceEl.textContent = '';
      var descEl = modal.querySelector('#modal-desc');
      if (descEl) descEl.textContent = '';
    }

    function showError(message) {
      if (!message) message = 'Ошибка загрузки данных';
      var titleEl = modal.querySelector('#modal-title');
      if (titleEl) titleEl.textContent = 'Ошибка';
      var galleryEl = modal.querySelector('#modal-gallery');
      if (galleryEl) galleryEl.innerHTML = '' +
        '<div class="modal-error" role="alert">' +
        '  <div class="error-text">' + message + '</div>' +
        '  <button class="modal-error-close">Закрыть</button>' +
        '</div>';
      var errBtn = modal.querySelector('.modal-error-close');
      if (errBtn) errBtn.addEventListener('click', function () { closeModal(true); });
    }

    function loadProducts() {
      if (state.productsMap) return Promise.resolve(state.productsMap);
      return fetch('/api/products.php', { cache: 'no-cache' })
        .then(function (res) { if (!res.ok) throw new Error('Fetch error ' + res.status); return res.json(); })
        .then(function (arr) {
          var map = {};
          if (Array.isArray(arr)) {
            arr.forEach(function (p) { if (p && p.id != null) map[p.id] = p; });
          }
          state.productsMap = map;
          return map;
        });
    }

    function getProduct(id) {
      if (!id) return Promise.reject(new Error('ID не передан'));
      if (state.cache[id]) return Promise.resolve(state.cache[id]);
      return loadProducts().then(function (map) {
        var product = map[id] || null;
        if (!product) throw new Error('Данные не найдены: ' + id);
        state.cache[id] = product;
        return product;
      });
    }

    function resolveGazeboId(data) {
      if (!data) return null;
      if (data.gazebo_id != null) return Number(data.gazebo_id);
      if (data.gazeboId != null) return Number(data.gazeboId);
      return null;
    }

    function renderModal(data) {
      var tEl = modal.querySelector('#modal-title'); if (tEl) tEl.textContent = data.title || '—';
      var pEl = modal.querySelector('#modal-price'); if (pEl) pEl.textContent = data.price || '';
      var dEl = modal.querySelector('#modal-desc');  if (dEl) dEl.textContent = data.description || '';

      var galleryEl = modal.querySelector('#modal-gallery');
      if (!galleryEl) return;
      galleryEl.innerHTML = '' +
        '<div class="carousel" aria-roledescription="carousel">' +
        '  <button class="carousel-nav carousel-prev" aria-label="Предыдущее изображение">‹</button>' +
        '  <div class="carousel-viewport" aria-live="polite">' +
        '    <div class="carousel-track"></div>' +
        '  </div>' +
        '  <button class="carousel-nav carousel-next" aria-label="Следующее изображение">›</button>' +
        '</div>' +
        '<div class="gallery-thumbs" id="gallery-thumbs" role="tablist" aria-label="Превью изображений"></div>';

      var track = modal.querySelector('.carousel-track');
      var thumbs = modal.querySelector('#gallery-thumbs');

      var imgs = Array.isArray(data.images) ? data.images : [];
      imgs.forEach(function (src, idx) {
        var slide = document.createElement('div');
        slide.className = 'carousel-slide';
        slide.setAttribute('data-index', String(idx));
        slide.innerHTML = '<img src="' + src + '" alt="' + (data.title || '') + ' ' + (idx + 1) + '" loading="lazy" draggable="false">';
        if (track) track.appendChild(slide);

        var t = document.createElement('button');
        t.className = 'thumb';
        t.type = 'button';
        t.setAttribute('data-index', String(idx));
        t.setAttribute('role', 'tab');
        t.setAttribute('aria-selected', 'false');
        t.innerHTML = '<img src="' + src + '" alt="Превью ' + (idx + 1) + '" loading="lazy">';
        if (thumbs) thumbs.appendChild(t);
      });

      initCarousel();

      
      var details = modal.querySelector('.details');
      var actions = modal.querySelector('.details .actions');
      if (!actions && details) {
        actions = document.createElement('div');
        actions.className = 'actions';
        details.appendChild(actions);
      }
      var btn = actions ? actions.querySelector('.btn-book') : null;
      if (!btn && actions) {
        btn = document.createElement('button');
        btn.className = 'btn btn-primary btn-book';
        btn.type = 'button';
        btn.textContent = 'Забронировать';
        actions.appendChild(btn);
      }
      var gid = resolveGazeboId(data);
      if (btn) {
        if (gid) btn.setAttribute('data-gazebo-id', String(gid)); else btn.removeAttribute('data-gazebo-id');
        if (data.gazeboName) btn.setAttribute('data-gazebo-name', data.gazeboName); else btn.removeAttribute('data-gazebo-name');
        if (data.defaultDate) btn.setAttribute('data-default-date', data.defaultDate); else btn.removeAttribute('data-default-date');
      }

      state.currentProduct = data;
    }

    function initCarousel() {
      
      if (state.carousel && typeof state.carousel.destroy === 'function') state.carousel.destroy();

      var track = modal.querySelector('.carousel-track');
      var slides = track ? Array.prototype.slice.call(track.querySelectorAll('.carousel-slide')) : [];
      var thumbs = Array.prototype.slice.call(modal.querySelectorAll('#gallery-thumbs .thumb'));
      var prevBtn = modal.querySelector('.carousel-prev');
      var nextBtn = modal.querySelector('.carousel-next');

      var current = 0;
      var isAnimating = false;
      var TRANS_DUR = 300;

      slides.forEach(function (s, i) {
        s.id = 'carousel-slide-' + i;
        s.style.minWidth = '100%';
      });
      if (track) {
        track.style.display = 'flex';
        track.style.transition = 'transform ' + TRANS_DUR + 'ms ease';
      }

      function clamp(i) { return (i < 0) ? (slides.length - 1) : (i >= slides.length ? 0 : i); }

      function updateUI() {
        if (track) track.style.transform = 'translateX(' + (-current * 100) + '%)';
        thumbs.forEach(function (t, idx) {
          var selected = idx === current;
          if (selected) {
            t.classList.add('active');
            t.setAttribute('aria-selected', 'true');
            t.setAttribute('tabindex', '0');
          } else {
            t.classList.remove('active');
            t.setAttribute('aria-selected', 'false');
            t.setAttribute('tabindex', '-1');
          }
        });
        if (slides.length <= 1) {
          if (prevBtn) prevBtn.setAttribute('aria-hidden', 'true');
          if (nextBtn) nextBtn.setAttribute('aria-hidden', 'true');
        } else {
          if (prevBtn) prevBtn.removeAttribute('aria-hidden');
          if (nextBtn) nextBtn.removeAttribute('aria-hidden');
        }
      }

      function goTo(index) {
        if (isAnimating) return;
        isAnimating = true;
        current = clamp(index);
        updateUI();
        setTimeout(function () { isAnimating = false; }, TRANS_DUR + 20);
      }
      function next() { goTo(current + 1); }
      function prev() { goTo(current - 1); }

      thumbs.forEach(function (t) {
        t.addEventListener('click', function () {
          var idx = parseInt(t.getAttribute('data-index') || '0', 10);
          if (!isNaN(idx)) goTo(idx);
        });
        t.addEventListener('keydown', function (e) {
          if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); var i = parseInt(t.getAttribute('data-index') || '0', 10); if (!isNaN(i)) goTo(i); }
        });
      });
      if (prevBtn) prevBtn.addEventListener('click', prev);
      if (nextBtn) nextBtn.addEventListener('click', next);

      function onKey(e) {
        if (e.key === 'ArrowRight') { e.preventDefault(); next(); }
        if (e.key === 'ArrowLeft')  { e.preventDefault(); prev(); }
      }
      document.addEventListener('keydown', onKey);

      function destroy() {
        if (prevBtn) prevBtn.removeEventListener('click', prev);
        if (nextBtn) nextBtn.removeEventListener('click', next);
        document.removeEventListener('keydown', onKey);
      }

      updateUI();
      state.carousel = { destroy: destroy };
    }

    function openModal(pushHist, id) {
      if (pushHist === void 0) pushHist = true;
      state.lastFocused = document.activeElement;
      modal.style.display = 'flex';
      modal.setAttribute('aria-hidden', 'false');
      document.body.style.overflow = 'hidden';
      if (pushHist && id) {
        try { history.pushState({ modal: true, id: id }, '', '?product=' + id); } catch (e) {}
      }
      if (closeBtn && typeof closeBtn.focus === 'function') closeBtn.focus();
    }

    function closeModal(useHistoryBack) {
      if (useHistoryBack === void 0) useHistoryBack = false;
      if (state.carousel && typeof state.carousel.destroy === 'function') { state.carousel.destroy(); state.carousel = null; }
      modal.style.display = 'none';
      modal.setAttribute('aria-hidden', 'true');
      document.body.style.overflow = '';
      if (state.lastFocused && typeof state.lastFocused.focus === 'function') state.lastFocused.focus();
      if (useHistoryBack) { try { history.back(); } catch (e) {} }
    }

    
    cardsContainer.addEventListener('click', function (e) {
      var card = e.target.closest ? e.target.closest('.tour-card') : null;
      if (!card) return;
      var id = card.getAttribute('data-id');
      if (!id) return;
      showLoading();
      openModal(true, id);
      getProduct(id).then(function (data) { renderModal(data); })
                    .catch(function () { showError('Данные не найдены или ошибка сети.'); });
    });

    if (overlay) overlay.addEventListener('click', function () { closeModal(true); });
    if (closeBtn) closeBtn.addEventListener('click', function () { closeModal(true); });

    
    modal.addEventListener('click', function (e) {
      var btn = e.target.closest ? e.target.closest('.btn-book') : null;
      if (!btn) return;
      var p = state.currentProduct || {};
      var opts = {};
      var gid = resolveGazeboId(p);
      if (gid) opts.gazeboId = gid; else if (p.gazeboName) opts.gazeboName = p.gazeboName;
      if (p.defaultDate) opts.date = p.defaultDate;
      if (typeof window.openBooking === 'function') {
        try { window.openBooking(opts); } catch (err) { console.warn('openBooking error:', err); }
      } else {
        console.warn('openBooking не найден. Подключите booking.js и экспортируйте openBooking');
      }
      closeModal(true);
    });

    
    window.addEventListener('popstate', function (e) {
      var st = e.state;
      if (st && st.modal && st.id) {
        showLoading();
        getProduct(st.id).then(function (data) { renderModal(data); openModal(false); })
                          .catch(function () { showError(); });
      } else {
        if (modal.style.display !== 'none') closeModal(false);
      }
    });
  });
})();
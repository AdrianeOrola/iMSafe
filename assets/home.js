(() => {
  const strip = document.querySelector('.hero-route');
  if (!strip) return;
  const preference = matchMedia('(prefers-reduced-motion: reduce)');
  let visible = false;
  let stopped = false;
  const render = () => {
    strip.classList.toggle('route-running', visible && !document.hidden && !stopped && !preference.matches);
    if (preference.matches) {
      strip.removeAttribute('role');
      strip.removeAttribute('tabindex');
      strip.removeAttribute('aria-pressed');
      strip.setAttribute('aria-label', 'Response journey');
      strip.removeAttribute('title');
    } else {
      strip.setAttribute('role', 'button');
      strip.tabIndex = 0;
      strip.setAttribute('aria-pressed', String(stopped));
      strip.setAttribute('aria-label', 'Response journey: Observe, Report, Coordinate, Track. Pause sliding sequence');
      strip.title = 'Select to stop or resume sliding';
    }
  };
  strip.classList.add('route-ready');
  strip.addEventListener('click', () => { stopped = !stopped; render(); });
  strip.addEventListener('keydown', event => {
    if (event.key === ' ' || event.key === 'Enter') {
      event.preventDefault();
      stopped = !stopped;
      render();
    }
  });
  preference.addEventListener('change', render);
  document.addEventListener('visibilitychange', render);
  if ('IntersectionObserver' in window) {
    new IntersectionObserver(entries => { visible = entries[0].isIntersecting; render(); }).observe(strip);
  }
  render();
})();

// Public-domain archive photos alternate automatically, without a control bar.
(() => {
  const gallery = document.querySelector('.response-gallery');
  if (!gallery) return;
  const stage = gallery.querySelector('.gallery-stage');
  const images = [...gallery.querySelectorAll('.gallery-photo')];
  const hazard = gallery.querySelector('.gallery-hazard');
  const preference = matchMedia('(prefers-reduced-motion: reduce)');
  let active = 0;
  let visible = false;
  let stopped = false;
  let timer = null;
  const show = index => {
    if (!images[index].complete || !images[index].naturalWidth) return false;
    active = index;
    if (hazard) hazard.textContent = images[index].dataset.hazard;
    images.forEach((image, i) => {
      image.classList.toggle('is-active', i === active);
      image.setAttribute('aria-hidden', String(i !== active));
    });
    return true;
  };
  const next = (direction = 1) => {
    for (let offset = 1; offset <= images.length; offset++) {
      if (show((active + direction * offset + images.length) % images.length)) return;
    }
  };
  const render = () => {
    const running = visible && !document.hidden && !stopped && !preference.matches;
    gallery.classList.toggle('gallery-running', running);
    stage.setAttribute('role', 'button');
    stage.tabIndex = 0;
    if (preference.matches) {
      stage.removeAttribute('aria-pressed');
      stage.setAttribute('aria-label', 'Show next archive photograph');
      stage.title = 'Select to show the next photograph';
    } else {
      stage.setAttribute('aria-pressed', String(stopped));
      stage.setAttribute('aria-label', 'Pause archive photo slideshow');
      stage.title = 'Select to stop or resume the slideshow';
    }
    if (!running) {
      clearTimeout(timer);
      timer = null;
    } else if (timer === null) {
      timer = setTimeout(() => {
        timer = null;
        next();
        render();
      }, 4000);
    }
  };
  const activate = () => {
    if (preference.matches) next();
    else stopped = !stopped;
    render();
  };
  stage.addEventListener('click', activate);
  stage.addEventListener('keydown', event => {
    if (event.key === ' ' || event.key === 'Enter') {
      event.preventDefault();
      activate();
    } else if (event.key === 'ArrowRight' || event.key === 'ArrowLeft') {
      event.preventDefault();
      stopped = true;
      next(event.key === 'ArrowRight' ? 1 : -1);
      render();
    }
  });
  images.forEach(image => {
    const recover = () => {
      if (!images[active].naturalWidth) next();
      render();
    };
    image.addEventListener('load', recover);
    image.addEventListener('error', recover);
  });
  document.addEventListener('visibilitychange', render);
  preference.addEventListener('change', render);
  if ('IntersectionObserver' in window) {
    new IntersectionObserver(entries => {
      visible = entries[0].isIntersecting;
      render();
    }).observe(gallery);
  }
  gallery.classList.add('gallery-ready');
  render();
})();

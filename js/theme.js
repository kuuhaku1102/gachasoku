(function() {
  const toggle = document.querySelector('.site-header__toggle');
  const nav = document.querySelector('.site-header__nav');

  if (!toggle || !nav) {
    return;
  }

  const closeMenu = () => {
    toggle.setAttribute('aria-expanded', 'false');
    nav.classList.remove('is-open');
  };

  toggle.addEventListener('click', () => {
    const isExpanded = toggle.getAttribute('aria-expanded') === 'true';
    toggle.setAttribute('aria-expanded', String(!isExpanded));
    nav.classList.toggle('is-open', !isExpanded);
  });

  window.addEventListener('resize', () => {
    if (window.innerWidth >= 961) {
      closeMenu();
    }
  });

  document.addEventListener('keyup', (event) => {
    if (event.key === 'Escape') {
      closeMenu();
    }
  });
})();

(function() {
  const sliders = document.querySelectorAll('[data-ranking-slider]');

  if (!sliders.length) {
    return;
  }

  sliders.forEach((slider) => {
    const track = slider.querySelector('[data-slider-track]');
    const prev = slider.querySelector('[data-slider-prev]');
    const next = slider.querySelector('[data-slider-next]');

    if (!track) {
      return;
    }

    const getScrollAmount = () => track.clientWidth * 0.85;

    const updateNavState = () => {
      if (!prev && !next) {
        return;
      }

      const maxScrollLeft = track.scrollWidth - track.clientWidth;
      const current = track.scrollLeft;

      if (prev) {
        prev.disabled = current <= 4;
      }

      if (next) {
        next.disabled = current >= maxScrollLeft - 4;
      }
    };

    const scrollByAmount = (amount) => {
      track.scrollBy({
        left: amount,
        behavior: 'smooth',
      });
    };

    if (prev) {
      prev.addEventListener('click', () => {
        scrollByAmount(-getScrollAmount());
      });
    }

    if (next) {
      next.addEventListener('click', () => {
        scrollByAmount(getScrollAmount());
      });
    }

    track.addEventListener('scroll', () => {
      window.requestAnimationFrame(updateNavState);
    });

    window.addEventListener('resize', () => {
      window.requestAnimationFrame(updateNavState);
    });

    updateNavState();
  });
})();

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

    const getGap = () => {
      const styles = window.getComputedStyle(track);
      const gapValue = styles.columnGap || styles.gap || styles.rowGap || '0';
      const gap = parseFloat(gapValue);
      return Number.isNaN(gap) ? 0 : gap;
    };

    const getScrollAmount = () => {
      const firstItem = track.querySelector(':scope > li');

      if (!firstItem) {
        return track.clientWidth * 0.8;
      }

      return firstItem.getBoundingClientRect().width + getGap();
    };

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

(function() {
  const calendars = document.querySelectorAll('[data-calendar]');

  if (!calendars.length) {
    return;
  }

  calendars.forEach((calendar) => {
    const weeks = calendar.querySelectorAll('[data-calendar-week]');
    const trigger = calendar.querySelector('[data-calendar-more]');

    if (!trigger || weeks.length <= 1) {
      return;
    }

    let isExpanded = false;

    const setCollapsedState = () => {
      calendar.classList.add('gachasoku-calendar--collapsed');
      calendar.classList.remove('gachasoku-calendar--expanded');

      weeks.forEach((week, index) => {
        week.hidden = index > 0;
      });

      trigger.hidden = false;
      trigger.setAttribute('aria-expanded', 'false');
    };

    const setExpandedState = () => {
      calendar.classList.remove('gachasoku-calendar--collapsed');
      calendar.classList.add('gachasoku-calendar--expanded');

      weeks.forEach((week) => {
        week.hidden = false;
      });

      trigger.hidden = true;
      trigger.setAttribute('aria-expanded', 'true');
    };

    const mq = window.matchMedia('(max-width: 600px)');

    const syncState = () => {
      if (mq.matches) {
        if (isExpanded) {
          setExpandedState();
        } else {
          setCollapsedState();
        }
      } else {
        isExpanded = false;
        setExpandedState();
      }
    };

    const handleTriggerClick = () => {
      isExpanded = true;
      setExpandedState();
    };

    trigger.addEventListener('click', handleTriggerClick);

    if (typeof mq.addEventListener === 'function') {
      mq.addEventListener('change', syncState);
    } else if (typeof mq.addListener === 'function') {
      mq.addListener(syncState);
    }

    syncState();
  });
})();

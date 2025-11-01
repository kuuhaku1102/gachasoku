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
    const weeks = Array.from(calendar.querySelectorAll('[data-calendar-week]'));
    const trigger = calendar.querySelector('[data-calendar-more]');
    const dayCells = Array.from(
      calendar.querySelectorAll(
        '.gachasoku-calendar__cell:not(.gachasoku-calendar__cell--head):not(.gachasoku-calendar__cell--empty)'
      )
    ).map((cell) => {
      const dayEl = cell.querySelector('.gachasoku-calendar__day');
      const day = dayEl ? parseInt(dayEl.textContent, 10) : NaN;

      return {
        cell,
        day,
      };
    }).filter((item) => Number.isFinite(item.day));

    if (!trigger || weeks.length <= 1 || !dayCells.length) {
      return;
    }

    const mq = window.matchMedia('(max-width: 600px)');
    const baseLabel = trigger.dataset.moreLabel || trigger.textContent.trim() || 'もっと見る';
    const finalLabel = trigger.dataset.moreFinal || baseLabel;

    const segments = (() => {
      const maxDay = dayCells.reduce((acc, item) => Math.max(acc, item.day), 0);

      if (!maxDay) {
        return [];
      }

      const result = [];
      const chunkSize = 10;
      let start = 1;

      while (start <= maxDay) {
        let end = Math.min(start + chunkSize - 1, maxDay);
        const remaining = maxDay - end;

        if (remaining > 0 && remaining < chunkSize) {
          end = maxDay;
          result.push({ start, end });
          break;
        }

        result.push({ start, end });
        start = end + 1;
      }

      return result;
    })();

    if (segments.length <= 1) {
      trigger.hidden = true;
      trigger.setAttribute('aria-expanded', 'true');
      trigger.textContent = finalLabel;
      return;
    }

    let currentSegment = 0;
    let lastMatch = mq.matches;

    const showAll = () => {
      dayCells.forEach(({ cell }) => {
        cell.hidden = false;
      });

      weeks.forEach((week) => {
        week.hidden = false;
      });

      trigger.hidden = true;
      trigger.setAttribute('aria-expanded', 'true');
      trigger.textContent = finalLabel;

      calendar.classList.remove('gachasoku-calendar--collapsed');
      calendar.classList.add('gachasoku-calendar--expanded');
    };

    const applySegment = () => {
      const segment = segments[currentSegment];

      dayCells.forEach(({ cell, day }) => {
        const inRange = day >= segment.start && day <= segment.end;
        cell.hidden = !inRange;
      });

      weeks.forEach((week) => {
        const hasVisibleDay = Array.from(
          week.querySelectorAll(
            '.gachasoku-calendar__cell:not(.gachasoku-calendar__cell--head):not(.gachasoku-calendar__cell--empty)'
          )
        ).some((cell) => !cell.hidden);

        week.hidden = !hasVisibleDay;
      });

      const hasMore = currentSegment < segments.length - 1;

      trigger.hidden = !hasMore;
      trigger.setAttribute('aria-expanded', hasMore ? 'false' : 'true');
      trigger.textContent = hasMore
        ? `${segments[currentSegment + 1].start}日〜${segments[currentSegment + 1].end}日を表示`
        : finalLabel;

      if (hasMore) {
        calendar.classList.add('gachasoku-calendar--collapsed');
        calendar.classList.remove('gachasoku-calendar--expanded');
      } else {
        calendar.classList.remove('gachasoku-calendar--collapsed');
        calendar.classList.add('gachasoku-calendar--expanded');
      }
    };

    const syncState = () => {
      const matches = mq.matches;

      if (!matches) {
        showAll();
      } else {
        if (!lastMatch) {
          currentSegment = 0;
        }

        applySegment();
      }

      lastMatch = matches;
    };

    trigger.addEventListener('click', (event) => {
      if (!mq.matches) {
        return;
      }

      event.preventDefault();

      if (currentSegment < segments.length - 1) {
        currentSegment += 1;
        applySegment();
      }
    });

    if (typeof mq.addEventListener === 'function') {
      mq.addEventListener('change', syncState);
    } else if (typeof mq.addListener === 'function') {
      mq.addListener(syncState);
    }

    syncState();
  });
})();

(function() {
  const settings = window.gachasokuRanking || {};
  const ajaxUrl = settings.ajaxUrl;
  if (!ajaxUrl) {
    return;
  }

  const messages = Object.assign({
    genericError: '投票中にエラーが発生しました。時間をおいて再度お試しください。',
    loginRequired: '投票にはログインが必要です。',
    cooldown: '同じランキングには1時間に1度しか投票できません。',
    success: '投票ありがとうございました。'
  }, settings.messages || {});

  const buttons = document.querySelectorAll('[data-ranking-vote]');
  if (!buttons.length) {
    return;
  }

  const formatNumber = (value) => {
    if (typeof value !== 'number') {
      value = parseFloat(value);
    }
    if (Number.isNaN(value)) {
      return '0';
    }
    return value.toLocaleString('ja-JP');
  };

  const updateStats = (entryId, payload) => {
    if (!payload) {
      return;
    }
    const statsContainer = document.querySelector('[data-ranking-stats="' + entryId + '"]');
    if (statsContainer) {
      const stats = payload.stats || {};
      const wins = stats.wins || 0;
      const losses = stats.losses || 0;
      const logpos = stats.logpos || 0;
      const winRate = stats.formatted || '0.0%';
      const favorites = payload.favorites || {};
      const winEl = statsContainer.querySelector('[data-stat="wins"]');
      const lossEl = statsContainer.querySelector('[data-stat="losses"]');
      const logpoEl = statsContainer.querySelector('[data-stat="logpos"]');
      const rateEl = statsContainer.querySelector('[data-stat="win-rate"]');
      const favoriteEl = statsContainer.querySelector('[data-stat="favorites"]');
      if (winEl) { winEl.textContent = formatNumber(wins); }
      if (lossEl) { lossEl.textContent = formatNumber(losses); }
      if (logpoEl) { logpoEl.textContent = formatNumber(logpos); }
      if (rateEl) { rateEl.textContent = winRate; }
      if (favoriteEl) {
        const total = typeof favorites.total === 'number' ? favorites.total : parseFloat(favorites.total || 0);
        const safeTotal = Number.isNaN(total) ? 0 : total;
        favoriteEl.textContent = formatNumber(safeTotal);
      }
    }

    const personalContainer = document.querySelector('[data-ranking-personal="' + entryId + '"]');
    if (personalContainer) {
      const personal = payload.member || {};
      const wins = personal.wins || 0;
      const losses = personal.losses || 0;
      const logpos = personal.logpos || 0;
      const winRate = personal.formatted || '0.0%';
      const winEl = personalContainer.querySelector('[data-personal="wins"]');
      const lossEl = personalContainer.querySelector('[data-personal="losses"]');
      const logpoEl = personalContainer.querySelector('[data-personal="logpos"]');
      const rateEl = personalContainer.querySelector('[data-personal="win-rate"]');
      if (winEl) { winEl.textContent = formatNumber(wins); }
      if (lossEl) { lossEl.textContent = formatNumber(losses); }
      if (logpoEl) { logpoEl.textContent = formatNumber(logpos); }
      if (rateEl) { rateEl.textContent = winRate; }
    }
  };

  const setLoading = (container, isLoading) => {
    if (!container) {
      return;
    }
    container.querySelectorAll('[data-ranking-vote]').forEach((btn) => {
      btn.disabled = isLoading;
      btn.classList.toggle('is-loading', isLoading);
    });
  };

  const handleClick = (event) => {
    event.preventDefault();
    const button = event.currentTarget;
    if (button.disabled || button.classList.contains('is-loading')) {
      return;
    }

    const entryId = button.getAttribute('data-entry-id');
    const voteType = button.getAttribute('data-ranking-vote');
    const nonce = button.getAttribute('data-nonce');
    if (!entryId || !voteType || !nonce) {
      alert(messages.genericError);
      return;
    }

    const container = button.closest('[data-ranking-actions]');
    setLoading(container, true);

    const formData = new URLSearchParams();
    formData.append('action', 'gachasoku_ranking_vote');
    formData.append('entry_id', entryId);
    formData.append('vote_type', voteType);
    formData.append('nonce', nonce);

    fetch(ajaxUrl, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
      },
      body: formData.toString()
    }).then(async (response) => {
      let payload;
      try {
        payload = await response.json();
      } catch (error) {
        payload = null;
      }

      if (!response.ok || !payload) {
        const message = payload && payload.data && payload.data.message ? payload.data.message : messages.genericError;
        throw new Error(message);
      }

      if (!payload.success) {
        const message = payload.data && payload.data.message ? payload.data.message : messages.genericError;
        throw new Error(message);
      }

      updateStats(entryId, payload.data || {});
      if (payload.data && payload.data.message) {
        alert(payload.data.message);
      } else {
        alert(messages.success);
      }
    }).catch((error) => {
      alert(error.message || messages.genericError);
    }).finally(() => {
      setLoading(container, false);
    });
  };

  buttons.forEach((button) => {
    button.addEventListener('click', handleClick);
  });
})();

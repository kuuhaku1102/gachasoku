(function() {
  const settings = window.gachasokuMembership || {};
  if (!settings.ajaxUrl) {
    return;
  }

  const messages = Object.assign({
    genericError: 'エラーが発生しました。時間をおいて再度お試しください。',
    loginRequired: '応募にはログインが必要です。',
    missingUrl: '応募先URLが見つかりません。',
  }, settings.messages || {});

  const labels = Object.assign({
    applied: '応募済み',
    visit: '公式サイトへ',
  }, settings.labels || {});

  const voteMessages = Object.assign({
    missingEntry: 'ランキングを選択してください。',
    missingType: '投票内容を選択してください。',
    missingNonce: '投票情報を取得できませんでした。',
    success: '投票を受け付けました。',
    cooldown: '同じランキングには1時間に1度しか投票できません。',
    genericError: '投票中にエラーが発生しました。時間をおいて再度お試しください。',
  }, settings.vote || {});

  const campaignButtons = document.querySelectorAll('[data-campaign-apply]');
  const voteForm = document.querySelector('[data-member-vote-form]');

  if (!campaignButtons.length && !voteForm) {
    return;
  }

  const disableButton = (button) => {
    if (!button) {
      return;
    }
    button.disabled = true;
    button.classList.add('is-loading');
  };

  const enableButton = (button) => {
    if (!button) {
      return;
    }
    button.disabled = false;
    button.classList.remove('is-loading');
  };

  const formatNumber = (value) => {
    const number = typeof value === 'number' ? value : parseFloat(value);
    if (Number.isNaN(number)) {
      return '0';
    }
    return number.toLocaleString('ja-JP');
  };

  const updateMemberRankingStats = (entryId, payload) => {
    if (!entryId || !payload) {
      return;
    }

    const stats = payload.stats || {};
    const statsContainer = document.querySelector('[data-member-ranking-stats="' + entryId + '"]');
    if (statsContainer) {
      const wins = formatNumber(stats.wins || 0);
      const losses = formatNumber(stats.losses || 0);
      const logpos = formatNumber(stats.logpos || 0);
      const winRate = stats.formatted || '0.0%';
      const winEl = statsContainer.querySelector('[data-member-stat="wins"]');
      const lossEl = statsContainer.querySelector('[data-member-stat="losses"]');
      const logpoEl = statsContainer.querySelector('[data-member-stat="logpos"]');
      const rateEl = statsContainer.querySelector('[data-member-stat="win-rate"]');
      if (winEl) { winEl.textContent = wins; }
      if (lossEl) { lossEl.textContent = losses; }
      if (logpoEl) { logpoEl.textContent = logpos; }
      if (rateEl) { rateEl.textContent = winRate; }
    }

    const personal = payload.member || {};
    const personalContainer = document.querySelector('[data-member-ranking-personal="' + entryId + '"]');
    if (personalContainer) {
      const winsValue = Number(personal.wins) || 0;
      const lossesValue = Number(personal.losses) || 0;
      const logposValue = Number(personal.logpos) || 0;
      const total = winsValue + lossesValue + logposValue;
      const winRate = personal.formatted || '0.0%';
      const wrapper = personalContainer.querySelector('[data-member-personal-wrapper]');
      const emptyEl = personalContainer.querySelector('[data-member-personal-empty]');
      const winEl = personalContainer.querySelector('[data-member-personal="wins"]');
      const lossEl = personalContainer.querySelector('[data-member-personal="losses"]');
      const logpoEl = personalContainer.querySelector('[data-member-personal="logpos"]');
      const rateEl = personalContainer.querySelector('[data-member-personal="win-rate"]');

      if (wrapper) {
        wrapper.hidden = total === 0;
      }
      if (emptyEl) {
        emptyEl.hidden = total !== 0;
      }
      if (winEl) {
        winEl.textContent = formatNumber(winsValue);
      }
      if (lossEl) {
        lossEl.textContent = formatNumber(lossesValue);
      }
      if (logpoEl) {
        logpoEl.textContent = formatNumber(logposValue);
      }
      if (rateEl) {
        rateEl.textContent = winRate;
      }
    }
  };

  const refreshMemberRankingSummary = (rankingMap) => {
    if (!rankingMap || typeof rankingMap !== 'object') {
      return;
    }

    const rows = Array.from(document.querySelectorAll('[data-member-ranking-row]'));
    if (!rows.length) {
      return;
    }

    const tbody = rows[0].parentElement;
    if (!tbody) {
      return;
    }

    const rowsWithData = rows.filter((row) => {
      const entryId = row.getAttribute('data-member-ranking-row');
      return Boolean(entryId && rankingMap[entryId]);
    });

    if (!rowsWithData.length) {
      return;
    }

    if (rowsWithData.length !== rows.length) {
      rowsWithData.forEach((row) => {
        const entryId = row.getAttribute('data-member-ranking-row');
        const data = rankingMap[entryId] || {};
        const rankCell = row.querySelector('[data-member-rank]');
        if (rankCell && data.label) {
          rankCell.textContent = data.label;
        }
        updateMemberRankingStats(entryId, data);
      });
      return;
    }

    const sortedRows = rowsWithData.slice().sort((a, b) => {
      const dataA = rankingMap[a.getAttribute('data-member-ranking-row')] || {};
      const dataB = rankingMap[b.getAttribute('data-member-ranking-row')] || {};
      const rankA = typeof dataA.rank === 'number' ? dataA.rank : parseInt(dataA.rank, 10);
      const rankB = typeof dataB.rank === 'number' ? dataB.rank : parseInt(dataB.rank, 10);
      const safeRankA = isFinite(rankA) ? rankA : Number.MAX_SAFE_INTEGER;
      const safeRankB = isFinite(rankB) ? rankB : Number.MAX_SAFE_INTEGER;
      return safeRankA - safeRankB;
    });

    sortedRows.forEach((row) => {
      const entryId = row.getAttribute('data-member-ranking-row');
      const data = rankingMap[entryId] || {};
      const rankCell = row.querySelector('[data-member-rank]');
      if (rankCell && data.label) {
        rankCell.textContent = data.label;
      }
      updateMemberRankingStats(entryId, data);
      tbody.appendChild(row);
    });
  };

  if (campaignButtons.length) {
    const updateButtonState = (button, url) => {
      const container = button.closest('.gachasoku-campaign-card__actions') || button.parentElement;
      if (!container) {
        button.remove();
        return;
      }

      const appliedLabel = button.getAttribute('data-applied-label') || labels.applied;
      const visitLabel = button.getAttribute('data-visit-label') || labels.visit;

      button.remove();

      let status = container.querySelector('.gachasoku-campaign-card__status');
      if (!status) {
        status = document.createElement('span');
        status.className = 'gachasoku-campaign-card__status';
        container.insertBefore(status, container.firstChild);
      }
      status.textContent = appliedLabel;

      if (url) {
        let link = container.querySelector('.gachasoku-button--outline');
        if (!link) {
          link = document.createElement('a');
          link.className = 'gachasoku-button gachasoku-button--outline';
          link.target = '_blank';
          link.rel = 'noopener noreferrer';
          container.appendChild(link);
        }
        link.href = url;
        link.textContent = visitLabel;
      }
    };

    const handleCampaignSuccess = (campaignId, url) => {
      const selector = '[data-campaign-apply][data-campaign-id="' + campaignId + '"]';
      document.querySelectorAll(selector).forEach((button) => {
        updateButtonState(button, url);
      });

      if (url) {
        window.open(url, '_blank', 'noopener');
      }
    };

    const handleCampaignClick = (event) => {
      event.preventDefault();
      const button = event.currentTarget;
      if (!button || button.classList.contains('is-loading')) {
        return;
      }

      const campaignId = button.getAttribute('data-campaign-id');
      const campaignUrl = button.getAttribute('data-campaign-url');
      const nonce = button.getAttribute('data-campaign-nonce');

      if (!campaignId) {
        return;
      }

      if (!campaignUrl) {
        alert(messages.missingUrl);
        return;
      }

      if (!nonce) {
        alert(messages.loginRequired);
        return;
      }

      disableButton(button);
      let shouldReset = true;

      const params = new URLSearchParams();
      params.append('action', 'gachasoku_apply_campaign');
      params.append('campaign_id', campaignId);
      params.append('nonce', nonce);

      fetch(settings.ajaxUrl, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
        },
        body: params.toString(),
      }).then(async (response) => {
        let payload = null;
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

        shouldReset = false;
        return payload.data || {};
      }).then((data) => {
        const destination = data.url || campaignUrl;
        handleCampaignSuccess(campaignId, destination);
      }).catch((error) => {
        alert(error.message || messages.genericError);
      }).finally(() => {
        if (shouldReset) {
          enableButton(button);
        }
      });
    };

    campaignButtons.forEach((button) => {
      button.addEventListener('click', handleCampaignClick);
    });
  }

  if (voteForm) {
    const entrySelect = voteForm.querySelector('[data-member-vote-entry]');
    const typeSelect = voteForm.querySelector('[data-member-vote-type]');
    const submitButton = voteForm.querySelector('[data-member-vote-submit]');
    const messageEl = voteForm.querySelector('[data-member-vote-message]');

    const setMessage = (text, isError = false) => {
      if (!messageEl) {
        return;
      }
      if (!text) {
        messageEl.textContent = '';
        messageEl.hidden = true;
        messageEl.classList.remove('is-error');
        return;
      }
      messageEl.textContent = text;
      messageEl.hidden = false;
      messageEl.classList.toggle('is-error', Boolean(isError));
    };

    voteForm.addEventListener('submit', (event) => {
      event.preventDefault();
      if (!submitButton || submitButton.disabled) {
        return;
      }

      const selectedOption = entrySelect ? entrySelect.options[entrySelect.selectedIndex] : null;
      const entryId = selectedOption ? selectedOption.value : '';
      const nonce = selectedOption ? selectedOption.getAttribute('data-nonce') : '';
      const voteType = typeSelect ? typeSelect.value : '';

      setMessage('');

      if (!entryId) {
        setMessage(voteMessages.missingEntry, true);
        return;
      }
      if (!voteType) {
        setMessage(voteMessages.missingType, true);
        return;
      }
      if (!nonce) {
        setMessage(voteMessages.missingNonce, true);
        return;
      }

      disableButton(submitButton);
      voteForm.classList.add('is-loading');

      const params = new URLSearchParams();
      params.append('action', 'gachasoku_ranking_vote');
      params.append('entry_id', entryId);
      params.append('vote_type', voteType);
      params.append('nonce', nonce);

      fetch(settings.ajaxUrl, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
        },
        body: params.toString(),
      }).then(async (response) => {
        let payload = null;
        try {
          payload = await response.json();
        } catch (error) {
          payload = null;
        }

        if (!response.ok || !payload) {
          const message = payload && payload.data && payload.data.message ? payload.data.message : voteMessages.genericError;
          throw new Error(message);
        }

        if (!payload.success) {
          const message = payload.data && payload.data.message ? payload.data.message : voteMessages.genericError;
          throw new Error(message);
        }

        return payload.data || {};
      }).then((data) => {
        if (data.ranking) {
          refreshMemberRankingSummary(data.ranking);
        } else {
          updateMemberRankingStats(entryId, data);
        }
        if (typeSelect) {
          typeSelect.value = '';
        }
        const message = data.message || voteMessages.success;
        setMessage(message, false);
      }).catch((error) => {
        setMessage(error.message || voteMessages.genericError, true);
      }).finally(() => {
        enableButton(submitButton);
        voteForm.classList.remove('is-loading');
      });
    });
  }
})();

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

  const buttons = document.querySelectorAll('[data-campaign-apply]');
  if (!buttons.length) {
    return;
  }

  const disableButton = (button) => {
    button.disabled = true;
    button.classList.add('is-loading');
  };

  const enableButton = (button) => {
    button.disabled = false;
    button.classList.remove('is-loading');
  };

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

  const handleSuccess = (campaignId, url) => {
    const selector = '[data-campaign-apply][data-campaign-id="' + campaignId + '"]';
    document.querySelectorAll(selector).forEach((button) => {
      updateButtonState(button, url);
    });

    if (url) {
      window.open(url, '_blank', 'noopener');
    }
  };

  const handleClick = (event) => {
    event.preventDefault();
    const button = event.currentTarget;
    if (button.classList.contains('is-loading')) {
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
      handleSuccess(campaignId, destination);
    }).catch((error) => {
      alert(error.message || messages.genericError);
    }).finally(() => {
      if (shouldReset) {
        enableButton(button);
      }
    });
  };

  buttons.forEach((button) => {
    button.addEventListener('click', handleClick);
  });
})();

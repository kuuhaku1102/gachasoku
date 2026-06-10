/**
 * ガチャ速 オークション 入札速報トースト
 *
 * オークション一覧・詳細ページで定期的に最近の入札を取得し、
 * 新しい入札を画面隅にトースト表示する（ポーリング型）。
 */
(function () {
  'use strict';

  if (typeof window.GachasokuAuctionFeed === 'undefined') {
    return;
  }

  var cfg = window.GachasokuAuctionFeed;
  var lastId = null;
  var container = null;

  function ensureContainer() {
    if (container) {
      return container;
    }
    container = document.createElement('div');
    container.className = 'gachasoku-toast-container';
    document.body.appendChild(container);
    return container;
  }

  function showToast(item) {
    var box = ensureContainer();
    var toast = document.createElement('a');
    toast.className = 'gachasoku-toast';
    toast.href = item.url;
    toast.innerHTML =
      '<span class="gachasoku-toast__icon">🔨</span>' +
      '<span class="gachasoku-toast__body">' +
      '<span class="gachasoku-toast__title"></span>' +
      '<span class="gachasoku-toast__price">現在 ' + item.price + ' 円</span>' +
      '</span>';
    // タイトルはテキストとして安全に挿入。
    toast.querySelector('.gachasoku-toast__title').textContent =
      '「' + item.title + '」に新しい入札！';

    box.appendChild(toast);

    // アニメーション用クラス。
    requestAnimationFrame(function () {
      toast.classList.add('is-visible');
    });

    var remove = function () {
      toast.classList.remove('is-visible');
      setTimeout(function () {
        if (toast.parentNode) {
          toast.parentNode.removeChild(toast);
        }
      }, 300);
    };

    setTimeout(remove, 7000);
  }

  function poll(initial) {
    var url =
      cfg.ajaxUrl +
      '?action=gachasoku_auction_feed' +
      '&after=' + (lastId === null ? 0 : lastId) +
      '&current=' + (cfg.currentAuction || 0);

    fetch(url, { credentials: 'same-origin' })
      .then(function (res) {
        return res.ok ? res.json() : null;
      })
      .then(function (json) {
        if (!json || !json.success || !json.data) {
          return;
        }
        var data = json.data;

        if (!initial && Array.isArray(data.items)) {
          // 古い順に表示（id昇順）。
          data.items
            .slice()
            .sort(function (a, b) { return a.id - b.id; })
            .forEach(showToast);
        }

        if (typeof data.last_id !== 'undefined') {
          lastId = data.last_id;
        }
      })
      .catch(function () {
        // ネットワークエラーは無視（次回ポーリングで回復）。
      });
  }

  // 初回はベースライン取得（通知しない）。
  poll(true);

  var timer = setInterval(function () {
    // タブが非表示のときはスキップして負荷を抑える。
    if (document.hidden) {
      return;
    }
    poll(false);
  }, cfg.interval || 25000);

  window.addEventListener('beforeunload', function () {
    clearInterval(timer);
  });
})();

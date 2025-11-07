(function() {
  function copyToClipboard(textarea) {
    textarea.select();
    textarea.setSelectionRange(0, textarea.value.length);
    try {
      var successful = document.execCommand('copy');
      if (!successful && navigator.clipboard) {
        navigator.clipboard.writeText(textarea.value).catch(function() {});
      }
    } catch (err) {
      if (navigator.clipboard) {
        navigator.clipboard.writeText(textarea.value).catch(function() {});
      }
    }
  }

  document.addEventListener('DOMContentLoaded', function() {
    var container = document.querySelector('.gachasoku-calendar-copy');
    if (!container) {
      return;
    }

    var button = container.querySelector('.gachasoku-calendar-copy__button');
    var textarea = container.querySelector('.gachasoku-calendar-copy__textarea');
    if (!button || !textarea) {
      return;
    }

    button.addEventListener('click', function(e) {
      e.preventDefault();
      copyToClipboard(textarea);
      button.classList.add('is-copied');
      button.textContent = 'コピーしました';
      setTimeout(function() {
        button.classList.remove('is-copied');
        button.textContent = 'イベント情報をコピー';
      }, 2000);
    });
  });
})();

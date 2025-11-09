(function () {
  document.addEventListener('DOMContentLoaded', function () {
    var sections = document.querySelectorAll('.gachasoku-draw-admin__chance');
    sections.forEach(function (section) {
      var selectAll = section.querySelector('[data-chance-select-all]');
      var checkboxes = Array.prototype.slice.call(section.querySelectorAll('[data-chance-select]'));
      var bulkInput = section.querySelector('[data-chance-bulk-input]');
      var applyButton = section.querySelector('[data-chance-bulk-apply]');
      var clearButton = section.querySelector('[data-chance-bulk-clear]');

      if (!checkboxes.length) {
        return;
      }

      var updateSelectAll = function () {
        if (!selectAll) {
          return;
        }
        var enabled = checkboxes.filter(function (checkbox) {
          return !checkbox.disabled;
        });
        if (!enabled.length) {
          selectAll.checked = false;
          selectAll.indeterminate = false;
          return;
        }
        var checkedCount = enabled.filter(function (checkbox) {
          return checkbox.checked;
        }).length;
        selectAll.checked = checkedCount === enabled.length;
        selectAll.indeterminate = checkedCount > 0 && checkedCount < enabled.length;
      };

      checkboxes.forEach(function (checkbox) {
        checkbox.addEventListener('change', updateSelectAll);
      });

      if (selectAll) {
        selectAll.addEventListener('change', function () {
          checkboxes.forEach(function (checkbox) {
            if (!checkbox.disabled) {
              checkbox.checked = selectAll.checked;
            }
          });
          updateSelectAll();
        });
      }

      if (clearButton) {
        clearButton.addEventListener('click', function () {
          checkboxes.forEach(function (checkbox) {
            checkbox.checked = false;
          });
          if (selectAll) {
            selectAll.checked = false;
            selectAll.indeterminate = false;
          }
        });
      }

      if (applyButton && bulkInput) {
        applyButton.addEventListener('click', function () {
          var value = parseInt(bulkInput.value, 10);
          if (isNaN(value)) {
            window.alert('倍率を入力してください。');
            bulkInput.focus();
            return;
          }

          if (value < 1) {
            value = 1;
          } else if (value > 10) {
            value = 10;
          }
          bulkInput.value = value;

          var targets = checkboxes.filter(function (checkbox) {
            return checkbox.checked && !checkbox.disabled;
          });

          if (!targets.length) {
            window.alert('倍率を設定する応募者を選択してください。');
            return;
          }

          targets.forEach(function (checkbox) {
            var row = checkbox.closest('[data-chance-row]');
            if (!row) {
              return;
            }
            var input = row.querySelector('[data-chance-input]');
            if (!input) {
              return;
            }
            input.value = value;
            row.classList.add('has-updated');
            window.setTimeout(function () {
              row.classList.remove('has-updated');
            }, 1200);
          });
        });
      }

      updateSelectAll();
    });

    var forms = document.querySelectorAll('.gachasoku-draw-admin__form');
    forms.forEach(function (form) {
      var runButton = form.querySelector('button[name="gachasoku_draw_action"][value="run"]');
      if (!runButton) {
        return;
      }

      var submitterFallback = null;
      var isDelayComplete = false;
      var countdownTimer = null;
      var remainingSeconds = 0;
      var status = form.querySelector('[data-draw-status]');
      var actions = form.querySelector('.gachasoku-draw-admin__actions');

      if (!status && actions) {
        status = document.createElement('p');
        status.className = 'gachasoku-draw-admin__status';
        status.setAttribute('data-draw-status', '');
        status.setAttribute('aria-live', 'polite');
        actions.appendChild(status);
      }

      var updateStatus = function () {
        if (!status) {
          return;
        }
        if (remainingSeconds > 0) {
          status.textContent = '抽選中です…（あと約' + remainingSeconds + '秒）';
        } else {
          status.textContent = '結果を表示しています…';
        }
      };

      form.querySelectorAll('button[type="submit"]').forEach(function (button) {
        button.addEventListener('click', function () {
          submitterFallback = button;
        });
      });

      form.addEventListener('submit', function (event) {
        var submitter = event.submitter || submitterFallback;

        if (isDelayComplete) {
          isDelayComplete = false;
          submitterFallback = null;
          return;
        }

        if (!submitter || submitter !== runButton) {
          submitterFallback = null;
          return;
        }

        event.preventDefault();

        var buttons = form.querySelectorAll('button[type="submit"]');
        buttons.forEach(function (button) {
          button.disabled = true;
        });

        var delay = 10000 + Math.floor(Math.random() * 5000);
        remainingSeconds = Math.ceil(delay / 1000);
        updateStatus();

        if (countdownTimer) {
          window.clearInterval(countdownTimer);
        }

        countdownTimer = window.setInterval(function () {
          remainingSeconds -= 1;
          if (remainingSeconds <= 0) {
            window.clearInterval(countdownTimer);
            countdownTimer = null;
          }
          updateStatus();
        }, 1000);

        window.setTimeout(function () {
          if (countdownTimer) {
            window.clearInterval(countdownTimer);
            countdownTimer = null;
          }
          remainingSeconds = 0;
          updateStatus();
          isDelayComplete = true;
          buttons.forEach(function (button) {
            button.disabled = false;
          });
          form.submit();
        }, delay);
      });
    });
  });
})();

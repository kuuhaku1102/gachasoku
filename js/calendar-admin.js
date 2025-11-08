(function($) {
  var weekdayLabels = {
    0: '日曜日',
    1: '月曜日',
    2: '火曜日',
    3: '水曜日',
    4: '木曜日',
    5: '金曜日',
    6: '土曜日',
  };

  function formatDateLabel(value) {
    if (!value) {
      return '';
    }
    var parts = value.split('-');
    if (parts.length !== 3) {
      return value;
    }
    var year = parts[0];
    var month = String(parseInt(parts[1], 10));
    var day = String(parseInt(parts[2], 10));
    if (!month || !day || month === 'NaN' || day === 'NaN') {
      return value;
    }
    return year + '年' + month + '月' + day + '日';
  }

  function updateSummary($entry) {
    var title = $entry.find('input[name*="[title]"]').val() || '（タイトル未設定）';
    $entry.find('.gachasoku-calendar-entry__summary-title').text(title);

    var type = $entry.find('.gachasoku-calendar-type').val() || 'single';
    var parts = [];

    if (type === 'single') {
      var singleDate = $entry.find('.gachasoku-calendar-dates[data-type="single"] input[type="date"]').val();
      if (singleDate) {
        parts.push(formatDateLabel(singleDate));
      }
    } else if (type === 'range') {
      var start = $entry.find('.gachasoku-calendar-dates[data-type="range"] input[type="date"]').eq(0).val();
      var end = $entry.find('.gachasoku-calendar-dates[data-type="range"] input[type="date"]').eq(1).val();
      var startLabel = start ? formatDateLabel(start) : '';
      var endLabel = end ? formatDateLabel(end) : '';
      if (startLabel && endLabel) {
        parts.push(startLabel + ' 〜 ' + endLabel);
      } else if (startLabel) {
        parts.push(startLabel);
      } else if (endLabel) {
        parts.push(endLabel);
      }
    } else if (type === 'monthly') {
      var monthDay = $entry.find('input[name*="[month_day]"]').val();
      if (monthDay) {
        parts.push('毎月' + parseInt(monthDay, 10) + '日');
      }
    } else if (type === 'weekday') {
      var weekday = $entry.find('select[name*="[weekday]"]').val();
      if (weekday !== undefined && weekday !== '' && weekdayLabels.hasOwnProperty(weekday)) {
        parts.push('毎週' + weekdayLabels[weekday]);
      }
    }

    var time = $entry.find('input[name*="[time_text]"]').val();
    if (time) {
      parts.push(time);
    }

    var notes = $entry.find('textarea[name*="[notes]"]').val();
    if (notes) {
      var trimmed = notes.replace(/\s+/g, ' ').trim();
      if (trimmed.length > 20) {
        trimmed = trimmed.slice(0, 20) + '…';
      }
      if (trimmed) {
        parts.push(trimmed);
      }
    }

    if (!parts.length) {
      parts.push('日程未設定');
    }

    $entry.find('.gachasoku-calendar-entry__summary-meta').text(parts.join(' / '));
  }

  function updateEntryNumbers($container) {
    $container.find('.gachasoku-calendar-entry').each(function(index) {
      var $entry = $(this);
      $entry.attr('data-index', index);
      $entry.find('.gachasoku-calendar-entry__number').text(index + 1);

      $entry.find('[name^="gachasoku_calendar_events"]').each(function() {
        var name = $(this).attr('name');
        if (!name) {
          return;
        }
        var updated = name.replace(/gachasoku_calendar_events\[[^\]]+\]/, 'gachasoku_calendar_events[' + index + ']');
        $(this).attr('name', updated);
      });
    });
  }

  function switchDateFields($entry) {
    var type = $entry.find('.gachasoku-calendar-type').val();
    $entry.find('.gachasoku-calendar-dates').each(function() {
      var $block = $(this);
      var isActive = $block.data('type') === type;
      $block.toggleClass('is-active', isActive);
      $block.find('input, select').prop('disabled', !isActive);
    });
  }

  function openEntry($entry) {
    $entry.addClass('is-open');
    $entry.find('.gachasoku-calendar-entry__body').prop('hidden', false);
    $entry.find('.gachasoku-calendar-entry__toggle').attr('aria-expanded', 'true');
  }

  function closeEntry($entry) {
    $entry.removeClass('is-open');
    $entry.find('.gachasoku-calendar-entry__body').prop('hidden', true);
    $entry.find('.gachasoku-calendar-entry__toggle').attr('aria-expanded', 'false');
  }

  function initializeEntry($entry) {
    switchDateFields($entry);
    updateSummary($entry);
  }

  $(function() {
    var $entries = $('#gachasoku-calendar-entries');
    var template = $('#gachasoku-calendar-entry-template').html() || '';

    $entries.find('.gachasoku-calendar-entry').each(function() {
      initializeEntry($(this));
    });
    updateEntryNumbers($entries);

    var $firstEntry = $entries.find('.gachasoku-calendar-entry').first();
    if ($firstEntry.length) {
      openEntry($firstEntry);
    }

    $('#gachasoku-add-calendar').on('click', function(e) {
      e.preventDefault();
      var index = $entries.find('.gachasoku-calendar-entry').length;
      var html = template.replace(/__INDEX__/g, index);
      var $content = $(html);
      $entries.append($content);
      updateEntryNumbers($entries);
      initializeEntry($content);
      openEntry($content);
    });

    $entries.on('click', '.gachasoku-calendar-remove', function(e) {
      e.preventDefault();
      var $entry = $(this).closest('.gachasoku-calendar-entry');
      var wasOpen = $entry.hasClass('is-open');
      var $nextTarget = $entry.next('.gachasoku-calendar-entry');
      if (!$nextTarget.length) {
        $nextTarget = $entry.prev('.gachasoku-calendar-entry');
      }
      $entry.remove();
      updateEntryNumbers($entries);
      if (wasOpen && $nextTarget && $nextTarget.length) {
        openEntry($nextTarget);
      }
    });

    $entries.on('click', '.gachasoku-calendar-entry__toggle', function(e) {
      e.preventDefault();
      var $entry = $(this).closest('.gachasoku-calendar-entry');
      if ($entry.hasClass('is-open')) {
        closeEntry($entry);
      } else {
        openEntry($entry);
      }
    });

    $entries.on('change', '.gachasoku-calendar-type', function() {
      var $entry = $(this).closest('.gachasoku-calendar-entry');
      switchDateFields($entry);
      updateSummary($entry);
    });

    $entries.on('input change', 'input, textarea, select', function() {
      var $entry = $(this).closest('.gachasoku-calendar-entry');
      updateSummary($entry);
    });

    $entries.on('click', '.gachasoku-calendar-duplicate', function(e) {
      e.preventDefault();
      var $entry = $(this).closest('.gachasoku-calendar-entry');
      var $clone = $entry.clone(false);
      $clone.removeClass('is-open');
      $clone.find('.gachasoku-calendar-entry__body').prop('hidden', true);
      $clone.find('.gachasoku-calendar-entry__toggle').attr('aria-expanded', 'false');
      $entry.after($clone);
      updateEntryNumbers($entries);
      initializeEntry($clone);
      openEntry($clone);
    });
  });
})(jQuery);

(function($) {
  function updateEntryNumbers($container) {
    $container.find('.gachasoku-calendar-entry').each(function(index) {
      $(this)
        .attr('data-index', index)
        .find('.gachasoku-calendar-entry__number')
        .text(index + 1);

      $(this)
        .find('[name^="gachasoku_calendar_events"]').each(function() {
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
      $block.toggle(isActive);
      $block.find('input, select').prop('disabled', !isActive);
    });
  }

  function initializeEntry($entry) {
    switchDateFields($entry);
  }

  $(function() {
    var $entries = $('#gachasoku-calendar-entries');
    var template = $('#gachasoku-calendar-entry-template').html() || '';

    $entries.find('.gachasoku-calendar-entry').each(function() {
      initializeEntry($(this));
    });
    updateEntryNumbers($entries);

    $('#gachasoku-add-calendar').on('click', function(e) {
      e.preventDefault();
      var index = $entries.find('.gachasoku-calendar-entry').length;
      var html = template.replace(/__INDEX__/g, index);
      var $content = $(html);
      $entries.append($content);
      updateEntryNumbers($entries);
      initializeEntry($content);
    });

    $entries.on('click', '.gachasoku-calendar-remove', function(e) {
      e.preventDefault();
      $(this).closest('.gachasoku-calendar-entry').remove();
      updateEntryNumbers($entries);
    });

    $entries.on('change', '.gachasoku-calendar-type', function() {
      var $entry = $(this).closest('.gachasoku-calendar-entry');
      switchDateFields($entry);
    });
  });
})(jQuery);

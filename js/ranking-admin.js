(function($) {
  function generateEntryId() {
    return 'rk_' + Math.random().toString(36).slice(2, 10) + Date.now().toString(36);
  }

  function refreshEntries() {
    $('#gachasoku-ranking-entries .gachasoku-ranking-entry').each(function(index) {
      $(this).attr('data-index', index);
      $(this).find('.gachasoku-entry-number').text(index + 1);
      $(this).find('input, textarea').each(function() {
        var name = $(this).attr('name');
        if (!name) {
          return;
        }
        $(this).attr('name', name.replace(/\[[^\]]+\]/, '[' + index + ']'));
      });
    });
  }

  function addEntry() {
    var template = $('#gachasoku-ranking-entry-template').html();
    if (!template) {
      return;
    }
    var newIndex = $('#gachasoku-ranking-entries .gachasoku-ranking-entry').length;
    var compiled = template.replace(/__INDEX__/g, newIndex);
    var $entry = $(compiled);
    $entry.find('input[name$="[id]"]').each(function() {
      if (!$(this).val()) {
        $(this).val(generateEntryId());
      }
    });
    $('#gachasoku-ranking-entries').append($entry);
    refreshEntries();
  }

  $(document).on('click', '#gachasoku-add-entry', function(e) {
    e.preventDefault();
    addEntry();
  });

  $(document).on('click', '.gachasoku-remove-entry', function(e) {
    e.preventDefault();
    $(this).closest('.gachasoku-ranking-entry').remove();
    refreshEntries();
  });

  $(document).on('click', '.gachasoku-select-image', function(e) {
    e.preventDefault();
    var button = $(this);
    var input = button.closest('.gachasoku-media-field').find('.gachasoku-image-url');
    var frame = wp.media({
      title: '画像を選択',
      button: {
        text: '選択'
      },
      multiple: false
    });

    frame.on('select', function() {
      var attachment = frame.state().get('selection').first().toJSON();
      input.val(attachment.url);
    });

    frame.open();
  });

  $(function() {
    if (!$('#gachasoku-ranking-entries .gachasoku-ranking-entry').length) {
      addEntry();
    } else {
      refreshEntries();
    }
  });
})(jQuery);

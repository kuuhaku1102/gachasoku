(function($) {
  function refreshEntries() {
    $('#gachasoku-link-entries .gachasoku-link-entry').each(function(index) {
      $(this).attr('data-index', index);
      $(this).find('.gachasoku-link-entry__number').text(index + 1);
      $(this).find('input').each(function() {
        var name = $(this).attr('name');
        if (!name) {
          return;
        }
        $(this).attr('name', name.replace(/\[[^\]]+\]/, '[' + index + ']'));
      });
    });
  }

  function addEntry() {
    var template = $('#gachasoku-link-entry-template').html();
    if (!template) {
      return;
    }
    var newIndex = $('#gachasoku-link-entries .gachasoku-link-entry').length;
    var compiled = template.replace(/__INDEX__/g, newIndex);
    $('#gachasoku-link-entries').append(compiled);
    refreshEntries();
  }

  $(document).on('click', '#gachasoku-add-link', function(e) {
    e.preventDefault();
    addEntry();
  });

  $(document).on('click', '.gachasoku-link-remove', function(e) {
    e.preventDefault();
    $(this).closest('.gachasoku-link-entry').remove();
    refreshEntries();
  });

  $(function() {
    if (!$('#gachasoku-link-entries .gachasoku-link-entry').length) {
      addEntry();
    } else {
      refreshEntries();
    }
  });
})(jQuery);

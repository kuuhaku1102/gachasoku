(function($) {
  $(function() {
    var frame;

    $('.gachasoku-campaign-image__select').on('click', function(event) {
      event.preventDefault();

      var $button = $(this);
      var $container = $button.closest('.gachasoku-campaign-image');
      var $input = $container.find('#gachasoku_campaign_image_id');
      var $preview = $container.find('.gachasoku-campaign-image__preview');

      if (frame) {
        frame.open();
        return;
      }

      frame = wp.media({
        title: 'キャンペーン画像を選択',
        multiple: false,
        library: { type: 'image' }
      });

      frame.on('select', function() {
        var attachment = frame.state().get('selection').first().toJSON();
        $input.val(attachment.id);
        $preview.html('<img src="' + attachment.url + '" alt="" />');
      });

      frame.open();
    });

    $('.gachasoku-campaign-image__remove').on('click', function(event) {
      event.preventDefault();
      var $container = $(this).closest('.gachasoku-campaign-image');
      $container.find('#gachasoku_campaign_image_id').val('');
      $container.find('.gachasoku-campaign-image__preview').html('<span class="gachasoku-campaign-image__placeholder">未選択</span>');
    });
  });
})(jQuery);

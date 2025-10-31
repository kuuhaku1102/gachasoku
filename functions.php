<?php
add_action('after_setup_theme', function() {
  add_theme_support('title-tag');
  add_theme_support('post-thumbnails');
  register_nav_menus(['main-menu' => 'メインメニュー']);
});

add_action('wp_enqueue_scripts', function() {
  wp_enqueue_style('yellowsmile-style', get_stylesheet_uri());
});

function gachasoku_get_ranking_entries() {
  $entries = get_option('gachasoku_ranking_entries', []);
  if (!is_array($entries)) {
    $entries = [];
  }
  return $entries;
}

function gachasoku_register_ranking_admin_page() {
  add_menu_page(
    'ランキング管理',
    'ランキング管理',
    'manage_options',
    'gachasoku-ranking',
    'gachasoku_render_ranking_admin_page',
    'dashicons-awards',
    20
  );
}
add_action('admin_menu', 'gachasoku_register_ranking_admin_page');

function gachasoku_save_ranking_entries($raw_entries) {
  $entries = [];

  foreach ($raw_entries as $entry) {
    $position = isset($entry['position']) ? sanitize_text_field($entry['position']) : '';
    $image_url = isset($entry['image_url']) ? esc_url_raw($entry['image_url']) : '';
    $image_link = isset($entry['image_link']) ? esc_url_raw($entry['image_link']) : '';
    $content = isset($entry['content']) ? wp_kses_post($entry['content']) : '';
    $detail_label = isset($entry['detail_label']) ? sanitize_text_field($entry['detail_label']) : '';
    $detail_url = isset($entry['detail_url']) ? esc_url_raw($entry['detail_url']) : '';
    $official_label = isset($entry['official_label']) ? sanitize_text_field($entry['official_label']) : '';
    $official_url = isset($entry['official_url']) ? esc_url_raw($entry['official_url']) : '';

    if ($position === '' && $image_url === '' && $image_link === '' && $content === '' && $detail_label === '' && $detail_url === '' && $official_label === '' && $official_url === '') {
      continue;
    }

    $entries[] = [
      'position' => $position,
      'image_url' => $image_url,
      'image_link' => $image_link,
      'content' => $content,
      'detail_label' => $detail_label,
      'detail_url' => $detail_url,
      'official_label' => $official_label,
      'official_url' => $official_url,
    ];
  }

  update_option('gachasoku_ranking_entries', $entries);
  return $entries;
}

function gachasoku_enqueue_ranking_admin_assets($hook) {
  if ($hook !== 'toplevel_page_gachasoku-ranking') {
    return;
  }

  wp_enqueue_media();
  wp_enqueue_script(
    'gachasoku-ranking-admin',
    get_template_directory_uri() . '/js/ranking-admin.js',
    ['jquery'],
    wp_get_theme()->get('Version'),
    true
  );
  wp_enqueue_style(
    'gachasoku-ranking-admin',
    get_template_directory_uri() . '/css/ranking-admin.css',
    [],
    wp_get_theme()->get('Version')
  );
}
add_action('admin_enqueue_scripts', 'gachasoku_enqueue_ranking_admin_assets');

function gachasoku_render_ranking_admin_page() {
  if (!current_user_can('manage_options')) {
    return;
  }

  $entries = gachasoku_get_ranking_entries();

  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_admin_referer('gachasoku_ranking_save', 'gachasoku_ranking_nonce');

    $raw_entries = isset($_POST['gachasoku_ranking_entries']) && is_array($_POST['gachasoku_ranking_entries'])
      ? $_POST['gachasoku_ranking_entries']
      : [];

    $entries = gachasoku_save_ranking_entries($raw_entries);
    add_settings_error('gachasoku_ranking', 'gachasoku_ranking_updated', 'ランキングを更新しました。', 'updated');
  }

  settings_errors('gachasoku_ranking');
  ?>
  <div class="wrap">
    <h1>ランキング管理</h1>
    <form method="post">
      <?php wp_nonce_field('gachasoku_ranking_save', 'gachasoku_ranking_nonce'); ?>
      <p>ランキングの順番や内容を編集し、必要に応じて項目を追加・削除してください。</p>
      <div id="gachasoku-ranking-entries">
        <?php if (!empty($entries)) : ?>
          <?php foreach ($entries as $index => $entry) : ?>
            <?php gachasoku_render_ranking_entry_fields($index, $entry); ?>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
      <button type="button" class="button" id="gachasoku-add-entry">項目を追加</button>
      <p class="submit">
        <button type="submit" class="button button-primary">ランキングを保存</button>
      </p>
    </form>
    <script type="text/template" id="gachasoku-ranking-entry-template">
      <?php gachasoku_render_ranking_entry_fields('__INDEX__', []); ?>
    </script>
  </div>
  <?php
}

function gachasoku_render_ranking_entry_fields($index, $entry) {
  $position = isset($entry['position']) ? $entry['position'] : '';
  $image_url = isset($entry['image_url']) ? $entry['image_url'] : '';
  $image_link = isset($entry['image_link']) ? $entry['image_link'] : '';
  $content = isset($entry['content']) ? $entry['content'] : '';
  $detail_label = isset($entry['detail_label']) ? $entry['detail_label'] : '';
  $detail_url = isset($entry['detail_url']) ? $entry['detail_url'] : '';
  $official_label = isset($entry['official_label']) ? $entry['official_label'] : '';
  $official_url = isset($entry['official_url']) ? $entry['official_url'] : '';
  ?>
  <div class="gachasoku-ranking-entry" data-index="<?php echo esc_attr($index); ?>">
    <h2>項目 <span class="gachasoku-entry-number"></span></h2>
    <div class="gachasoku-ranking-fields">
      <label>
        順位
        <input type="text" name="gachasoku_ranking_entries[<?php echo esc_attr($index); ?>][position]" value="<?php echo esc_attr($position); ?>" placeholder="例: 1位" />
      </label>
      <label>
        画像URL
        <div class="gachasoku-media-field">
          <input type="text" class="gachasoku-image-url" name="gachasoku_ranking_entries[<?php echo esc_attr($index); ?>][image_url]" value="<?php echo esc_attr($image_url); ?>" />
          <button type="button" class="button gachasoku-select-image">画像を選択</button>
        </div>
      </label>
      <label>
        画像リンクURL
        <input type="url" name="gachasoku_ranking_entries[<?php echo esc_attr($index); ?>][image_link]" value="<?php echo esc_attr($image_link); ?>" placeholder="https://" />
      </label>
      <label>
        内容
        <textarea name="gachasoku_ranking_entries[<?php echo esc_attr($index); ?>][content]" rows="4" placeholder="紹介文などを入力"><?php echo esc_textarea($content); ?></textarea>
      </label>
      <div class="gachasoku-ranking-buttons">
        <div>
          <label>
            詳細ボタンテキスト
            <input type="text" name="gachasoku_ranking_entries[<?php echo esc_attr($index); ?>][detail_label]" value="<?php echo esc_attr($detail_label); ?>" placeholder="例: 詳細はこちら" />
          </label>
          <label>
            詳細ボタンURL
            <input type="url" name="gachasoku_ranking_entries[<?php echo esc_attr($index); ?>][detail_url]" value="<?php echo esc_attr($detail_url); ?>" placeholder="https://" />
          </label>
        </div>
        <div>
          <label>
            公式ボタンテキスト
            <input type="text" name="gachasoku_ranking_entries[<?php echo esc_attr($index); ?>][official_label]" value="<?php echo esc_attr($official_label); ?>" placeholder="例: 公式サイト" />
          </label>
          <label>
            公式ボタンURL
            <input type="url" name="gachasoku_ranking_entries[<?php echo esc_attr($index); ?>][official_url]" value="<?php echo esc_attr($official_url); ?>" placeholder="https://" />
          </label>
        </div>
      </div>
    </div>
    <button type="button" class="button-link-delete gachasoku-remove-entry">この項目を削除</button>
    <hr />
  </div>
  <?php
}

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

function gachasoku_get_sorted_ranking_entries() {
  $entries = gachasoku_get_ranking_entries();

  if (!empty($entries)) {
    usort($entries, function($a, $b) {
      $posA = isset($a['position']) ? $a['position'] : '';
      $posB = isset($b['position']) ? $b['position'] : '';

      $numA = floatval(preg_replace('/[^0-9.]/', '', $posA));
      $numB = floatval(preg_replace('/[^0-9.]/', '', $posB));

      if ($numA === $numB) {
        return strcmp($posA, $posB);
      }

      return ($numA < $numB) ? -1 : 1;
    });
  }

  return $entries;
}

function gachasoku_render_ranking_list($entries = null, $args = []) {
  if ($entries === null) {
    $entries = gachasoku_get_sorted_ranking_entries();
  }

  $defaults = [
    'list_class' => 'ranking-list',
    'item_class' => 'ranking-list__item',
    'empty_message' => '',
  ];
  $args = wp_parse_args($args, $defaults);

  if (empty($entries)) {
    return $args['empty_message'];
  }

  ob_start();
  ?>
  <ol class="<?php echo esc_attr($args['list_class']); ?>">
    <?php foreach ($entries as $entry) :
      $position = isset($entry['position']) ? $entry['position'] : '';
      $image_url = isset($entry['image_url']) ? $entry['image_url'] : '';
      $image_link = isset($entry['image_link']) ? $entry['image_link'] : '';
      $content = isset($entry['content']) ? $entry['content'] : '';
      $detail_label = isset($entry['detail_label']) ? $entry['detail_label'] : '';
      $detail_url = isset($entry['detail_url']) ? $entry['detail_url'] : '';
      $official_label = isset($entry['official_label']) ? $entry['official_label'] : '';
      $official_url = isset($entry['official_url']) ? $entry['official_url'] : '';
      ?>
      <li class="<?php echo esc_attr($args['item_class']); ?>">
        <div class="ranking-card">
          <?php if ($position) : ?>
            <div class="ranking-card__position"><?php echo esc_html($position); ?></div>
          <?php endif; ?>
          <div class="ranking-card__body">
            <?php if ($image_url) : ?>
              <div class="ranking-card__image">
                <?php if ($image_link) : ?>
                  <a href="<?php echo esc_url($image_link); ?>" target="_blank" rel="noopener noreferrer">
                    <img src="<?php echo esc_url($image_url); ?>" alt="">
                  </a>
                <?php else : ?>
                  <img src="<?php echo esc_url($image_url); ?>" alt="">
                <?php endif; ?>
              </div>
            <?php endif; ?>
            <?php if ($content) : ?>
              <div class="ranking-card__content"><?php echo wpautop(wp_kses_post($content)); ?></div>
            <?php endif; ?>
            <div class="ranking-card__actions">
              <?php if ($detail_label && $detail_url) : ?>
                <a class="ranking-card__button ranking-card__button--detail" href="<?php echo esc_url($detail_url); ?>" target="_blank" rel="noopener noreferrer">
                  <?php echo esc_html($detail_label); ?>
                </a>
              <?php endif; ?>
              <?php if ($official_label && $official_url) : ?>
                <a class="ranking-card__button ranking-card__button--official" href="<?php echo esc_url($official_url); ?>" target="_blank" rel="noopener noreferrer">
                  <?php echo esc_html($official_label); ?>
                </a>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </li>
    <?php endforeach; ?>
  </ol>
  <?php
  return ob_get_clean();
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

function gachasoku_ranking_shortcode($atts, $content = '') {
  $atts = shortcode_atts([
    'title' => '',
    'lead' => '',
  ], $atts, 'gachasoku_ranking');

  $lead_text = $atts['lead'];
  if ($lead_text === '' && $content !== '') {
    $lead_text = $content;
  }

  $entries = gachasoku_get_sorted_ranking_entries();
  $list_html = gachasoku_render_ranking_list($entries, [
    'empty_message' => '<p class="ranking-page__empty ranking-shortcode__empty">ランキングが設定されていません。</p>',
  ]);

  $title_html = '';
  if ($atts['title'] !== '') {
    $title_html = '<h2 class="ranking-page__title ranking-shortcode__title">' . esc_html($atts['title']) . '</h2>';
  }

  $lead_html = '';
  if ($lead_text !== '') {
    $lead_html = '<div class="ranking-page__lead ranking-shortcode__lead">' . wpautop(wp_kses_post($lead_text)) . '</div>';
  }

  $header_html = '';
  if ($title_html || $lead_html) {
    $header_html = '<header class="ranking-page__header ranking-shortcode__header">' . $title_html . $lead_html . '</header>';
  }

  return '<div class="ranking-page ranking-shortcode"><div class="ranking-container">' . $header_html . $list_html . '</div></div>';
}
add_shortcode('gachasoku_ranking', 'gachasoku_ranking_shortcode');

function gachasoku_get_calendar_events() {
  $events = get_option('gachasoku_calendar_events', []);
  if (!is_array($events)) {
    $events = [];
  }

  return $events;
}

function gachasoku_save_calendar_events($raw_events) {
  $events = [];

  foreach ($raw_events as $event) {
    $title = isset($event['title']) ? sanitize_text_field($event['title']) : '';
    $url = isset($event['url']) ? esc_url_raw($event['url']) : '';
    $type = isset($event['type']) ? sanitize_key($event['type']) : 'single';
    $time_text = isset($event['time_text']) ? sanitize_text_field($event['time_text']) : '';
    $notes = isset($event['notes']) ? wp_kses_post($event['notes']) : '';

    $allowed_types = ['single', 'range', 'monthly', 'weekday'];
    if (!in_array($type, $allowed_types, true)) {
      $type = 'single';
    }

    $start_date = isset($event['start_date']) ? gachasoku_sanitize_calendar_date($event['start_date']) : '';
    $end_date = isset($event['end_date']) ? gachasoku_sanitize_calendar_date($event['end_date']) : '';
    $month_day = isset($event['month_day']) ? gachasoku_sanitize_calendar_day($event['month_day']) : '';
    $weekday = isset($event['weekday']) ? gachasoku_sanitize_calendar_weekday($event['weekday']) : '';

    if ($type === 'single') {
      if ($start_date === '') {
        continue;
      }
      $end_date = $start_date;
    } elseif ($type === 'range') {
      if ($start_date === '' && $end_date === '') {
        continue;
      }
      if ($start_date === '') {
        $start_date = $end_date;
      }
      if ($end_date === '' || $end_date < $start_date) {
        $end_date = $start_date;
      }
    } elseif ($type === 'monthly') {
      if ($month_day === '') {
        continue;
      }
      $start_date = '';
      $end_date = '';
    } elseif ($type === 'weekday') {
      if ($weekday === '') {
        continue;
      }
      $start_date = '';
      $end_date = '';
    }

    if ($title === '' && $url === '' && $time_text === '' && $notes === '') {
      continue;
    }

    $events[] = [
      'title' => $title,
      'url' => $url,
      'type' => $type,
      'start_date' => $start_date,
      'end_date' => $end_date,
      'month_day' => $month_day,
      'weekday' => $weekday,
      'time_text' => $time_text,
      'notes' => $notes,
    ];
  }

  update_option('gachasoku_calendar_events', $events);
  return $events;
}

function gachasoku_sanitize_calendar_date($value) {
  $value = trim($value);
  if ($value === '') {
    return '';
  }

  if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
    return '';
  }

  $parts = explode('-', $value);
  if (count($parts) !== 3) {
    return '';
  }

  if (!checkdate((int) $parts[1], (int) $parts[2], (int) $parts[0])) {
    return '';
  }

  return $value;
}

function gachasoku_sanitize_calendar_day($value) {
  $value = intval($value);
  if ($value < 1 || $value > 31) {
    return '';
  }

  return $value;
}

function gachasoku_sanitize_calendar_weekday($value) {
  $value = intval($value);
  if ($value < 0 || $value > 6) {
    return '';
  }

  return $value;
}

function gachasoku_register_calendar_admin_page() {
  add_menu_page(
    'カレンダー管理',
    'カレンダー管理',
    'manage_options',
    'gachasoku-calendar',
    'gachasoku_render_calendar_admin_page',
    'dashicons-calendar-alt',
    21
  );
}
add_action('admin_menu', 'gachasoku_register_calendar_admin_page');

function gachasoku_enqueue_calendar_admin_assets($hook) {
  if ($hook === 'toplevel_page_gachasoku-calendar') {
    wp_enqueue_media();
    wp_enqueue_script(
      'gachasoku-calendar-admin',
      get_template_directory_uri() . '/js/calendar-admin.js',
      ['jquery'],
      wp_get_theme()->get('Version'),
      true
    );
    wp_enqueue_style(
      'gachasoku-calendar-admin',
      get_template_directory_uri() . '/css/calendar-admin.css',
      [],
      wp_get_theme()->get('Version')
    );
  } elseif (in_array($hook, ['post.php', 'post-new.php'], true)) {
    wp_enqueue_script(
      'gachasoku-calendar-editor',
      get_template_directory_uri() . '/js/calendar-editor.js',
      [],
      wp_get_theme()->get('Version'),
      true
    );
    wp_enqueue_style(
      'gachasoku-calendar-editor',
      get_template_directory_uri() . '/css/calendar-editor.css',
      [],
      wp_get_theme()->get('Version')
    );
  }
}
add_action('admin_enqueue_scripts', 'gachasoku_enqueue_calendar_admin_assets');

function gachasoku_render_calendar_admin_page() {
  if (!current_user_can('manage_options')) {
    return;
  }

  $events = gachasoku_get_calendar_events();

  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_admin_referer('gachasoku_calendar_save', 'gachasoku_calendar_nonce');

    $raw_events = isset($_POST['gachasoku_calendar_events']) && is_array($_POST['gachasoku_calendar_events'])
      ? $_POST['gachasoku_calendar_events']
      : [];

    $events = gachasoku_save_calendar_events($raw_events);
    add_settings_error('gachasoku_calendar', 'gachasoku_calendar_updated', 'カレンダーを更新しました。', 'updated');
  }

  settings_errors('gachasoku_calendar');
  ?>
  <div class="wrap">
    <h1>カレンダー管理</h1>
    <form method="post" class="gachasoku-calendar-admin">
      <?php wp_nonce_field('gachasoku_calendar_save', 'gachasoku_calendar_nonce'); ?>
      <p>イベントのタイトルやリンク、開催日時を入力し、必要に応じて項目を追加・削除してください。</p>
      <div id="gachasoku-calendar-entries" class="gachasoku-calendar-entries">
        <?php if (!empty($events)) : ?>
          <?php foreach ($events as $index => $event) : ?>
            <?php gachasoku_render_calendar_event_fields($index, $event); ?>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
      <button type="button" class="button" id="gachasoku-add-calendar">イベントを追加</button>
      <p class="submit">
        <button type="submit" class="button button-primary">カレンダーを保存</button>
      </p>
    </form>
    <script type="text/template" id="gachasoku-calendar-entry-template">
      <?php gachasoku_render_calendar_event_fields('__INDEX__', []); ?>
    </script>
  </div>
  <?php
}

function gachasoku_render_calendar_event_fields($index, $event) {
  $title = isset($event['title']) ? $event['title'] : '';
  $url = isset($event['url']) ? $event['url'] : '';
  $type = isset($event['type']) ? $event['type'] : 'single';
  $start_date = isset($event['start_date']) ? $event['start_date'] : '';
  $end_date = isset($event['end_date']) ? $event['end_date'] : '';
  $month_day = isset($event['month_day']) ? $event['month_day'] : '';
  $weekday = isset($event['weekday']) ? $event['weekday'] : '';
  $time_text = isset($event['time_text']) ? $event['time_text'] : '';
  $notes = isset($event['notes']) ? $event['notes'] : '';

  $weekday_options = [
    '0' => '日曜日',
    '1' => '月曜日',
    '2' => '火曜日',
    '3' => '水曜日',
    '4' => '木曜日',
    '5' => '金曜日',
    '6' => '土曜日',
  ];
  ?>
  <div class="gachasoku-calendar-entry" data-index="<?php echo esc_attr($index); ?>">
    <h2>イベント <span class="gachasoku-calendar-entry__number"></span></h2>
    <div class="gachasoku-calendar-entry__fields">
      <label>
        イベント名
        <input type="text" name="gachasoku_calendar_events[<?php echo esc_attr($index); ?>][title]" value="<?php echo esc_attr($title); ?>" placeholder="イベント名" />
      </label>
      <label>
        リンクURL
        <input type="url" name="gachasoku_calendar_events[<?php echo esc_attr($index); ?>][url]" value="<?php echo esc_attr($url); ?>" placeholder="https://" />
      </label>
      <label>
        種別
        <select name="gachasoku_calendar_events[<?php echo esc_attr($index); ?>][type]" class="gachasoku-calendar-type">
          <option value="single" <?php selected($type, 'single'); ?>>単日（1日のみ）</option>
          <option value="range" <?php selected($type, 'range'); ?>>期間（開始〜終了）</option>
          <option value="monthly" <?php selected($type, 'monthly'); ?>>毎月（日付指定）</option>
          <option value="weekday" <?php selected($type, 'weekday'); ?>>毎週（曜日指定）</option>
        </select>
      </label>
      <div class="gachasoku-calendar-dates" data-type="single">
        <label>
          開催日
          <input type="date" name="gachasoku_calendar_events[<?php echo esc_attr($index); ?>][start_date]" value="<?php echo esc_attr($start_date); ?>" />
        </label>
      </div>
      <div class="gachasoku-calendar-dates" data-type="range">
        <label>
          開始日
          <input type="date" name="gachasoku_calendar_events[<?php echo esc_attr($index); ?>][start_date]" value="<?php echo esc_attr($start_date); ?>" />
        </label>
        <label>
          終了日
          <input type="date" name="gachasoku_calendar_events[<?php echo esc_attr($index); ?>][end_date]" value="<?php echo esc_attr($end_date); ?>" />
        </label>
      </div>
      <div class="gachasoku-calendar-dates" data-type="monthly">
        <label>
          毎月の日付（1〜31）
          <input type="number" min="1" max="31" name="gachasoku_calendar_events[<?php echo esc_attr($index); ?>][month_day]" value="<?php echo esc_attr($month_day); ?>" />
        </label>
      </div>
      <div class="gachasoku-calendar-dates" data-type="weekday">
        <label>
          曜日
          <select name="gachasoku_calendar_events[<?php echo esc_attr($index); ?>][weekday]">
            <option value="">選択してください</option>
            <?php foreach ($weekday_options as $weekday_value => $weekday_label) : ?>
              <option value="<?php echo esc_attr($weekday_value); ?>" <?php selected(strval($weekday), strval($weekday_value)); ?>><?php echo esc_html($weekday_label); ?></option>
            <?php endforeach; ?>
          </select>
        </label>
      </div>
      <label>
        時間・補足
        <input type="text" name="gachasoku_calendar_events[<?php echo esc_attr($index); ?>][time_text]" value="<?php echo esc_attr($time_text); ?>" placeholder="例: 10:00〜 / 夜開催" />
      </label>
      <label>
        メモ（任意）
        <textarea name="gachasoku_calendar_events[<?php echo esc_attr($index); ?>][notes]" rows="3" placeholder="詳細メモなど"><?php echo esc_textarea($notes); ?></textarea>
      </label>
    </div>
    <button type="button" class="button-link-delete gachasoku-calendar-remove">このイベントを削除</button>
    <hr />
  </div>
  <?php
}

function gachasoku_get_calendar_month_events($year, $month) {
  $events = gachasoku_get_calendar_events();
  $days_in_month = cal_days_in_month(CAL_GREGORIAN, $month, $year);
  $first_day = new DateTime("$year-$month-01");
  $last_day = new DateTime("$year-$month-$days_in_month");

  $calendar = [];
  for ($day = 1; $day <= $days_in_month; $day++) {
    $calendar[$day] = [];
  }

  foreach ($events as $event) {
    $type = isset($event['type']) ? $event['type'] : 'single';
    $title = isset($event['title']) ? $event['title'] : '';
    if ($title === '') {
      continue;
    }

    if ($type === 'monthly') {
      $day = isset($event['month_day']) ? intval($event['month_day']) : 0;
      if ($day < 1) {
        continue;
      }
      if ($day > $days_in_month) {
        $day = $days_in_month;
      }
      $calendar[$day][] = $event;
      continue;
    }

    if ($type === 'weekday') {
      $weekday = isset($event['weekday']) ? intval($event['weekday']) : -1;
      if ($weekday < 0 || $weekday > 6) {
        continue;
      }

      foreach (range(1, $days_in_month) as $day) {
        $current = new DateTime("$year-$month-$day");
        if (intval($current->format('w')) === $weekday) {
          $calendar[$day][] = $event;
        }
      }
      continue;
    }

    $start_date = isset($event['start_date']) ? $event['start_date'] : '';
    $end_date = isset($event['end_date']) ? $event['end_date'] : $start_date;

    if ($start_date === '') {
      continue;
    }

    try {
      $start = new DateTime($start_date);
      $end = new DateTime($end_date);
    } catch (Exception $e) {
      continue;
    }

    if ($end < $start) {
      $end = clone $start;
    }

    if ($end < $first_day || $start > $last_day) {
      continue;
    }

    $period_start = $start < $first_day ? clone $first_day : clone $start;
    $period_end = $end > $last_day ? clone $last_day : clone $end;

    $current = clone $period_start;
    while ($current <= $period_end) {
      $day = intval($current->format('j'));
      $calendar[$day][] = $event;
      $current->modify('+1 day');
    }
  }

  return $calendar;
}

function gachasoku_get_calendar_pickup_events($date = null) {
  if ($date === null) {
    $date = current_time('Y-m-d');
  }

  try {
    $target = new DateTime($date);
  } catch (Exception $e) {
    return [];
  }

  $year = intval($target->format('Y'));
  $month = intval($target->format('n'));
  $day = intval($target->format('j'));

  $calendar = gachasoku_get_calendar_month_events($year, $month);
  if (!isset($calendar[$day])) {
    return [];
  }

  return $calendar[$day];
}

function gachasoku_render_calendar_shortcode($atts = []) {
  $atts = shortcode_atts([
    'month' => '',
  ], $atts, 'gachasoku_calendar');

  if ($atts['month'] === '') {
    $current = current_time('timestamp');
    $year = intval(date_i18n('Y', $current));
    $month = intval(date_i18n('n', $current));
  } else {
    $parts = explode('-', $atts['month']);
    if (count($parts) !== 2) {
      return '';
    }
    $year = intval($parts[0]);
    $month = intval($parts[1]);
  }

  if ($year < 1970 || $month < 1 || $month > 12) {
    return '';
  }

  return gachasoku_render_calendar($year, $month);
}
add_shortcode('gachasoku_calendar', 'gachasoku_render_calendar_shortcode');

function gachasoku_render_calendar($year, $month) {
  $events_by_day = gachasoku_get_calendar_month_events($year, $month);
  $first_day = new DateTime("$year-$month-01");
  $days_in_month = cal_days_in_month(CAL_GREGORIAN, $month, $year);
  $start_weekday = intval($first_day->format('w'));

  $weeks = [];
  $week = [];

  for ($i = 0; $i < $start_weekday; $i++) {
    $week[] = null;
  }

  for ($day = 1; $day <= $days_in_month; $day++) {
    $week[] = $day;
    if (count($week) === 7) {
      $weeks[] = $week;
      $week = [];
    }
  }

  if (!empty($week)) {
    while (count($week) < 7) {
      $week[] = null;
    }
    $weeks[] = $week;
  }

  $month_label = sprintf('%d年%d月', $year, $month);
  $weekday_labels = ['日', '月', '火', '水', '木', '金', '土'];

  ob_start();
  ?>
  <div class="gachasoku-calendar">
    <header class="gachasoku-calendar__header">
      <h2 class="gachasoku-calendar__title"><?php echo esc_html($month_label); ?></h2>
    </header>
    <div class="gachasoku-calendar__table">
      <div class="gachasoku-calendar__row gachasoku-calendar__row--head">
        <?php foreach ($weekday_labels as $label) : ?>
          <div class="gachasoku-calendar__cell gachasoku-calendar__cell--head"><?php echo esc_html($label); ?></div>
        <?php endforeach; ?>
      </div>
      <?php foreach ($weeks as $week_days) : ?>
        <div class="gachasoku-calendar__row">
          <?php foreach ($week_days as $day) : ?>
            <?php if ($day === null) : ?>
              <div class="gachasoku-calendar__cell gachasoku-calendar__cell--empty"></div>
            <?php else :
              $events = isset($events_by_day[$day]) ? $events_by_day[$day] : [];
              $has_events = !empty($events);
              ?>
              <div class="gachasoku-calendar__cell <?php echo $has_events ? 'gachasoku-calendar__cell--has-events' : ''; ?>">
                <div class="gachasoku-calendar__day"><?php echo esc_html($day); ?></div>
                <?php if ($has_events) : ?>
                  <ul class="gachasoku-calendar__events">
                    <?php foreach ($events as $index => $event) :
                      $color = gachasoku_get_calendar_event_color($event, $index);
                      $title = isset($event['title']) ? $event['title'] : '';
                      $url = isset($event['url']) ? $event['url'] : '';
                      $time_text = isset($event['time_text']) ? $event['time_text'] : '';
                      $notes = isset($event['notes']) ? $event['notes'] : '';
                      ?>
                      <li class="gachasoku-calendar__event" style="--event-color: <?php echo esc_attr($color); ?>">
                        <div class="gachasoku-calendar__event-name">
                          <?php if ($url) : ?>
                            <a href="<?php echo esc_url($url); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html($title); ?></a>
                          <?php else : ?>
                            <?php echo esc_html($title); ?>
                          <?php endif; ?>
                        </div>
                        <?php if ($time_text) : ?>
                          <div class="gachasoku-calendar__event-time"><?php echo esc_html($time_text); ?></div>
                        <?php endif; ?>
                        <?php if ($notes) : ?>
                          <div class="gachasoku-calendar__event-notes"><?php echo wp_kses_post(wpautop($notes)); ?></div>
                        <?php endif; ?>
                      </li>
                    <?php endforeach; ?>
                  </ul>
                <?php endif; ?>
              </div>
            <?php endif; ?>
          <?php endforeach; ?>
        </div>
      <?php endforeach; ?>
    </div>
    <?php $pickup_events = gachasoku_get_calendar_pickup_events(); ?>
    <?php if (!empty($pickup_events)) :
      $pickup = $pickup_events[0];
      $pickup_title = isset($pickup['title']) ? $pickup['title'] : '';
      $pickup_url = isset($pickup['url']) ? $pickup['url'] : '';
      ?>
      <div class="gachasoku-calendar__pickup">
        <p class="gachasoku-calendar__pickup-label">本日のピックアップイベント</p>
        <?php if ($pickup_url) : ?>
          <a class="gachasoku-calendar__pickup-button" href="<?php echo esc_url($pickup_url); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html($pickup_title); ?></a>
        <?php else : ?>
          <span class="gachasoku-calendar__pickup-button gachasoku-calendar__pickup-button--static"><?php echo esc_html($pickup_title); ?></span>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </div>
  <?php
  return ob_get_clean();
}

function gachasoku_get_calendar_event_color($event, $index = 0) {
  $palette = ['#FAD7A0', '#AED6F1', '#F5B7B1', '#D7BDE2', '#A3E4D7', '#F9E79F', '#F8C471'];
  $seed = $index;
  if (isset($event['title'])) {
    $seed += abs(crc32($event['title']));
  }
  $color = $palette[$seed % count($palette)];
  return $color;
}

function gachasoku_add_calendar_metabox() {
  add_meta_box(
    'gachasoku-calendar-copy',
    'カレンダーイベントコピー',
    'gachasoku_render_calendar_metabox',
    ['post', 'page'],
    'side',
    'default'
  );
}
add_action('add_meta_boxes', 'gachasoku_add_calendar_metabox');

function gachasoku_render_calendar_metabox($post) {
  $today_events = gachasoku_get_calendar_pickup_events();
  $upcoming = gachasoku_get_upcoming_calendar_events();

  $copy_lines = [];
  foreach ($upcoming as $event_info) {
    $line = $event_info['date_label'] . '：' . $event_info['title'];
    if ($event_info['time_text']) {
      $line .= '（' . $event_info['time_text'] . '）';
    }
    if ($event_info['url']) {
      $line .= ' ' . $event_info['url'];
    }
    $copy_lines[] = $line;
  }

  $copy_text = implode("\n", $copy_lines);
  ?>
  <div class="gachasoku-calendar-copy">
    <p>直近のイベント情報をコピーできます。投稿本文に貼り付けてご利用ください。</p>
    <?php if (!empty($today_events)) : ?>
      <p class="gachasoku-calendar-copy__today">本日のイベント数：<?php echo count($today_events); ?></p>
    <?php endif; ?>
    <textarea class="gachasoku-calendar-copy__textarea" readonly><?php echo esc_textarea($copy_text); ?></textarea>
    <button type="button" class="button gachasoku-calendar-copy__button">イベント情報をコピー</button>
  </div>
  <?php
}

function gachasoku_get_upcoming_calendar_events($limit = 10) {
  $events = gachasoku_get_calendar_events();
  $now = new DateTime(current_time('mysql'));
  $current_year = intval($now->format('Y'));
  $current_month = intval($now->format('n'));

  $items = [];

  foreach ([$current_year, $current_year + 1] as $year) {
    for ($month = 1; $month <= 12; $month++) {
      if ($year === $current_year && $month < $current_month) {
        continue;
      }

      $events_by_day = gachasoku_get_calendar_month_events($year, $month);
      foreach ($events_by_day as $day => $day_events) {
        $date = new DateTime(sprintf('%04d-%02d-%02d', $year, $month, $day));
        if ($date < $now) {
          continue;
        }

        foreach ($day_events as $event) {
          $items[] = [
            'date' => clone $date,
            'title' => isset($event['title']) ? $event['title'] : '',
            'url' => isset($event['url']) ? $event['url'] : '',
            'time_text' => isset($event['time_text']) ? $event['time_text'] : '',
          ];
        }
      }
    }
  }

  usort($items, function($a, $b) {
    return $a['date'] <=> $b['date'];
  });

  $items = array_slice($items, 0, $limit);

  foreach ($items as &$item) {
    $item['date_label'] = date_i18n('n月j日', $item['date']->getTimestamp());
  }

  return $items;
}

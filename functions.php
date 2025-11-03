<?php
add_action('after_setup_theme', function() {
  add_theme_support('title-tag');
  add_theme_support('post-thumbnails');
  register_nav_menus([
    'main-menu'   => 'メインメニュー',
    'footer-menu' => 'フッターメニュー',
  ]);
});

add_action('wp_enqueue_scripts', function() {
  wp_enqueue_style('yellowsmile-style', get_stylesheet_uri());
  wp_enqueue_script(
    'gachasoku-theme',
    get_template_directory_uri() . '/js/theme.js',
    [],
    '1.0.0',
    true
  );
  wp_enqueue_script(
    'gachasoku-membership',
    get_template_directory_uri() . '/js/membership.js',
    [],
    '1.0.0',
    true
  );
  $hit_posts_map = [];
  if (function_exists('gachasoku_is_member_logged_in') && gachasoku_is_member_logged_in() && function_exists('gachasoku_get_current_member_id')) {
    $member_id = gachasoku_get_current_member_id();
    if ($member_id && function_exists('gachasoku_get_member_hit_posts_map')) {
      $hit_posts_map = gachasoku_get_member_hit_posts_map($member_id);
    }
  }
  wp_localize_script(
    'gachasoku-membership',
    'gachasokuMembership',
    [
      'ajaxUrl'  => admin_url('admin-ajax.php'),
      'messages' => [
        'genericError'  => 'エラーが発生しました。時間をおいて再度お試しください。',
        'loginRequired' => '応募にはログインが必要です。',
        'missingUrl'    => '応募先URLが見つかりません。',
      ],
      'labels'   => [
        'applied' => '応募済み',
        'visit'   => '公式サイトへ',
      ],
      'vote'     => [
        'missingEntry' => 'ランキングを選択してください。',
        'missingType'  => '投票内容を選択してください。',
        'missingNonce' => '投票情報を取得できませんでした。',
        'success'      => '投票を受け付けました。',
        'cooldown'     => '同じランキングには1時間に1度しか投票できません。',
        'genericError' => '投票中にエラーが発生しました。時間をおいて再度お試しください。',
      ],
      'hits'    => [
        'posts' => $hit_posts_map,
      ],
    ]
  );

  wp_enqueue_script(
    'gachasoku-ranking',
    get_template_directory_uri() . '/js/ranking.js',
    [],
    '1.0.0',
    true
  );

  $login_url    = function_exists('gachasoku_get_membership_page_url') ? gachasoku_get_membership_page_url('member-login') : wp_login_url();
  $register_url = function_exists('gachasoku_get_membership_page_url') ? gachasoku_get_membership_page_url('member-register') : wp_registration_url();

  wp_localize_script(
    'gachasoku-ranking',
    'gachasokuRanking',
    [
      'ajaxUrl'  => admin_url('admin-ajax.php'),
      'messages' => [
        'genericError'  => '投票中にエラーが発生しました。時間をおいて再度お試しください。',
        'loginRequired' => '投票にはログインが必要です。',
        'cooldown'      => '同じランキングには1時間に1度しか投票できません。',
        'success'       => '投票を受け付けました。',
      ],
      'links'   => [
        'login'    => $login_url,
        'register' => $register_url,
      ],
    ]
  );
});

function gachasoku_get_archive_site_terms() {
  $terms = get_terms([
    'taxonomy'   => 'category',
    'hide_empty' => true,
    'orderby'    => 'name',
    'order'      => 'ASC',
  ]);

  if (is_wp_error($terms)) {
    return [];
  }

  return apply_filters('gachasoku_archive_site_terms', $terms);
}

function gachasoku_apply_archive_filters($query) {
  if (is_admin() || !$query->is_main_query()) {
    return;
  }

  if (!$query->is_home() && !$query->is_archive() && !$query->is_search()) {
    return;
  }

  $site = isset($_GET['site']) ? sanitize_text_field(wp_unslash($_GET['site'])) : '';

  if ($site !== '') {
    $term = get_term_by('slug', $site, 'category');

    if ($term && !is_wp_error($term)) {
      $tax_query = $query->get('tax_query');

      if (!is_array($tax_query)) {
        $tax_query = [];
      }

      $tax_query[] = [
        'taxonomy' => 'category',
        'field'    => 'term_id',
        'terms'    => [$term->term_id],
      ];

      $query->set('tax_query', $tax_query);
    }
  }
}
add_action('pre_get_posts', 'gachasoku_apply_archive_filters');

function gachasoku_generate_ranking_entry_id() {
  return sanitize_key('rk_' . wp_generate_password(12, false));
}

function gachasoku_normalize_ranking_entries($entries, $persist = false) {
  $normalized = [];
  $updated = false;

  foreach ($entries as $entry) {
    if (!is_array($entry)) {
      continue;
    }

    if (empty($entry['id'])) {
      $entry['id'] = gachasoku_generate_ranking_entry_id();
      $updated = true;
    } else {
      $entry['id'] = sanitize_key($entry['id']);
    }

    if (!isset($entry['name'])) {
      $entry['name'] = '';
      $updated = true;
    } else {
      $entry['name'] = sanitize_text_field($entry['name']);
    }

    $normalized[] = $entry;
  }

  if ($updated && $persist) {
    update_option('gachasoku_ranking_entries', $normalized);
  }

  return $normalized;
}

function gachasoku_get_ranking_votes_table() {
  global $wpdb;
  return $wpdb->prefix . 'gachasoku_ranking_votes';
}

function gachasoku_calculate_win_rate($wins, $losses) {
  $wins = max(0, intval($wins));
  $losses = max(0, intval($losses));
  $total = $wins + $losses;
  if ($total <= 0) {
    return 0.0;
  }
  return round(($wins / $total) * 100, 1);
}

function gachasoku_get_ranking_vote_totals($entry_ids) {
  global $wpdb;

  $entry_ids = array_values(array_filter(array_map('sanitize_key', (array) $entry_ids)));
  if (empty($entry_ids)) {
    return [];
  }

  $table = gachasoku_get_ranking_votes_table();
  $placeholders = implode(',', array_fill(0, count($entry_ids), '%s'));

  $query = $wpdb->prepare(
    "SELECT entry_id, SUM(vote_type = 'win') AS wins, SUM(vote_type = 'lose') AS losses, SUM(vote_type = 'logpo') AS logpos FROM {$table} WHERE entry_id IN ({$placeholders}) GROUP BY entry_id",
    $entry_ids
  );

  $rows = $wpdb->get_results($query, ARRAY_A);
  $totals = [];

  if ($rows) {
    foreach ($rows as $row) {
      $entry_id = sanitize_key($row['entry_id']);
      $totals[$entry_id] = [
        'wins'   => intval($row['wins']),
        'losses' => intval($row['losses']),
        'logpos' => intval($row['logpos']),
      ];
    }
  }

  return $totals;
}

function gachasoku_get_member_ranking_vote_totals($member_id, $entry_ids = []) {
  global $wpdb;

  $member_id = intval($member_id);
  if ($member_id <= 0) {
    return [];
  }

  $table = gachasoku_get_ranking_votes_table();
  $where = 'member_id = %d';
  $params = [$member_id];

  $entry_ids = array_values(array_filter(array_map('sanitize_key', (array) $entry_ids)));
  if (!empty($entry_ids)) {
    $where .= ' AND entry_id IN (' . implode(',', array_fill(0, count($entry_ids), '%s')) . ')';
    $params = array_merge($params, $entry_ids);
  }

  $query = $wpdb->prepare(
    "SELECT entry_id, SUM(vote_type = 'win') AS wins, SUM(vote_type = 'lose') AS losses, SUM(vote_type = 'logpo') AS logpos FROM {$table} WHERE {$where} GROUP BY entry_id",
    $params
  );

  $rows = $wpdb->get_results($query, ARRAY_A);
  $totals = [];

  if ($rows) {
    foreach ($rows as $row) {
      $entry_id = sanitize_key($row['entry_id']);
      $totals[$entry_id] = [
        'wins'   => intval($row['wins']),
        'losses' => intval($row['losses']),
        'logpos' => intval($row['logpos']),
      ];
    }
  }

  return $totals;
}

function gachasoku_get_member_last_ranking_vote($entry_id, $member_id) {
  global $wpdb;

  $entry_id = sanitize_key($entry_id);
  $member_id = intval($member_id);

  if ($entry_id === '' || $member_id <= 0) {
    return null;
  }

  $table = gachasoku_get_ranking_votes_table();
  $query = $wpdb->prepare(
    "SELECT created_at FROM {$table} WHERE entry_id = %s AND member_id = %d ORDER BY created_at DESC LIMIT 1",
    $entry_id,
    $member_id
  );

  $result = $wpdb->get_var($query);
  return $result ? strtotime($result) : null;
}

function gachasoku_get_member_vote_cooldown($entry_id, $member_id, $interval = HOUR_IN_SECONDS) {
  $last = gachasoku_get_member_last_ranking_vote($entry_id, $member_id);
  if (!$last) {
    return 0;
  }

  $elapsed = current_time('timestamp') - $last;
  if ($elapsed >= $interval) {
    return 0;
  }

  return max(0, $interval - $elapsed);
}

function gachasoku_record_ranking_vote($entry_id, $member_id, $vote_type) {
  global $wpdb;

  $entry_id = sanitize_key($entry_id);
  $member_id = intval($member_id);
  $vote_type = sanitize_key($vote_type);

  if ($entry_id === '' || $member_id <= 0) {
    return new WP_Error('invalid_vote', '投票情報が正しくありません。');
  }

  $allowed = ['win', 'lose', 'logpo'];
  if (!in_array($vote_type, $allowed, true)) {
    return new WP_Error('invalid_vote_type', '選択された投票は利用できません。');
  }

  $now = current_time('mysql');
  $table = gachasoku_get_ranking_votes_table();
  $inserted = $wpdb->insert(
    $table,
    [
      'entry_id'   => $entry_id,
      'member_id'  => $member_id,
      'vote_type'  => $vote_type,
      'created_at' => $now,
    ],
    ['%s', '%d', '%s', '%s']
  );

  if ($inserted === false) {
    return new WP_Error('db_insert_error', '投票を保存できませんでした。');
  }

  return $now;
}

function gachasoku_find_ranking_entry($entry_id) {
  $entries = gachasoku_get_ranking_entries();
  foreach ($entries as $entry) {
    if (isset($entry['id']) && sanitize_key($entry['id']) === $entry_id) {
      return $entry;
    }
  }
  return null;
}

function gachasoku_get_ranking_entry_display_name($entry) {
  if (!is_array($entry)) {
    return '';
  }

  $candidates = [];

  if (!empty($entry['name'])) {
    $candidates[] = $entry['name'];
  }

  if (!empty($entry['content'])) {
    $text = trim(wp_strip_all_tags($entry['content']));
    if ($text !== '') {
      $parts = preg_split('/[\r\n]+/', $text);
      if (!empty($parts)) {
        $text = trim($parts[0]);
      }
      if ($text !== '') {
        $candidates[] = $text;
      }
    }
  }

  if (!empty($entry['detail_label'])) {
    $candidates[] = $entry['detail_label'];
  }

  if (!empty($entry['official_label'])) {
    $candidates[] = $entry['official_label'];
  }

  if (!empty($entry['position'])) {
    $candidates[] = $entry['position'];
  }

  foreach ($candidates as $candidate) {
    $candidate = trim(preg_replace('/\s+/', ' ', $candidate));
    if ($candidate !== '') {
      return $candidate;
    }
  }

  return '';
}

function gachasoku_get_ranking_entries() {
  $entries = get_option('gachasoku_ranking_entries', []);
  if (!is_array($entries)) {
    $entries = [];
  }
  return gachasoku_normalize_ranking_entries($entries, true);
}

function gachasoku_get_sorted_ranking_entries($member_id = null) {
  $entries = gachasoku_get_ranking_entries();

  if (empty($entries)) {
    return [];
  }

  $entry_ids = wp_list_pluck($entries, 'id');
  $totals = gachasoku_get_ranking_vote_totals($entry_ids);
  if ($member_id === null && function_exists('gachasoku_get_current_member_id')) {
    $member_id = gachasoku_get_current_member_id();
  }
  $member_id = intval($member_id);
  $member_totals = $member_id > 0 ? gachasoku_get_member_ranking_vote_totals($member_id, $entry_ids) : [];

  foreach ($entries as &$entry) {
    $entry_id = $entry['id'];
    $stats = isset($totals[$entry_id]) ? $totals[$entry_id] : ['wins' => 0, 'losses' => 0, 'logpos' => 0];
    $wins = isset($stats['wins']) ? intval($stats['wins']) : 0;
    $losses = isset($stats['losses']) ? intval($stats['losses']) : 0;
    $logpos = isset($stats['logpos']) ? intval($stats['logpos']) : 0;
    $win_rate = gachasoku_calculate_win_rate($wins, $losses);

    $entry['vote_stats'] = [
      'wins'      => $wins,
      'losses'    => $losses,
      'logpos'    => $logpos,
      'win_rate'  => $win_rate,
      'formatted' => number_format_i18n($win_rate, 1) . '%',
    ];

    if (isset($member_totals[$entry_id])) {
      $member_stats = $member_totals[$entry_id];
      $member_wins = intval($member_stats['wins']);
      $member_losses = intval($member_stats['losses']);
      $entry['member_vote_stats'] = [
        'wins'      => $member_wins,
        'losses'    => $member_losses,
        'logpos'    => intval($member_stats['logpos']),
        'win_rate'  => gachasoku_calculate_win_rate($member_wins, $member_losses),
      ];
      $entry['member_vote_stats']['formatted'] = number_format_i18n($entry['member_vote_stats']['win_rate'], 1) . '%';
    } else {
      $entry['member_vote_stats'] = [
        'wins'      => 0,
        'losses'    => 0,
        'logpos'    => 0,
        'win_rate'  => 0,
        'formatted' => number_format_i18n(0, 1) . '%',
      ];
    }
  }
  unset($entry);

  usort($entries, function($a, $b) {
    $statsA = isset($a['vote_stats']) ? $a['vote_stats'] : ['win_rate' => 0, 'wins' => 0];
    $statsB = isset($b['vote_stats']) ? $b['vote_stats'] : ['win_rate' => 0, 'wins' => 0];

    $rateA = isset($statsA['win_rate']) ? $statsA['win_rate'] : 0;
    $rateB = isset($statsB['win_rate']) ? $statsB['win_rate'] : 0;

    if ($rateA === $rateB) {
      $winsA = isset($statsA['wins']) ? $statsA['wins'] : 0;
      $winsB = isset($statsB['wins']) ? $statsB['wins'] : 0;
      if ($winsA === $winsB) {
        $lossA = isset($statsA['losses']) ? $statsA['losses'] : 0;
        $lossB = isset($statsB['losses']) ? $statsB['losses'] : 0;
        if ($lossA === $lossB) {
          $labelA = isset($a['position']) ? $a['position'] : '';
          $labelB = isset($b['position']) ? $b['position'] : '';
          return strcmp($labelA, $labelB);
        }
        return ($lossA < $lossB) ? -1 : 1;
      }
      return ($winsA > $winsB) ? -1 : 1;
    }

    return ($rateA > $rateB) ? -1 : 1;
  });

  foreach ($entries as $index => &$entry) {
    $entry['current_rank'] = $index + 1;
    $entry['current_rank_label'] = sprintf('%d位', $entry['current_rank']);
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

  $entry_count = count($entries);
  $wrapper_classes = 'ranking-slider';
  if ($entry_count <= 1) {
    $wrapper_classes .= ' ranking-slider--static';
  }

  $member_logged_in = function_exists('gachasoku_is_member_logged_in') ? gachasoku_is_member_logged_in() : false;
  $member_id = $member_logged_in && function_exists('gachasoku_get_current_member_id') ? gachasoku_get_current_member_id() : 0;
  $member_status = '';
  if ($member_logged_in && function_exists('gachasoku_get_member_status')) {
    $member_status = gachasoku_get_member_status($member_id);
  }
  $member_can_vote = $member_logged_in && defined('GACHASOKU_MEMBER_STATUS_ACTIVE') && $member_status === GACHASOKU_MEMBER_STATUS_ACTIVE;
  $login_url = function_exists('gachasoku_get_membership_page_url') ? gachasoku_get_membership_page_url('member-login') : wp_login_url();
  $register_url = function_exists('gachasoku_get_membership_page_url') ? gachasoku_get_membership_page_url('member-register') : wp_registration_url();

  $vote_labels = [
    'win'   => '勝ちに投票',
    'lose'  => '負けに投票',
    'logpo' => 'ログポに投票',
  ];

  ob_start();
  ?>
  <div class="<?php echo esc_attr($wrapper_classes); ?>" data-ranking-slider>
    <?php if ($entry_count > 1) : ?>
      <button class="ranking-slider__nav ranking-slider__nav--prev" type="button" aria-label="前のランキング" data-slider-prev>&lsaquo;</button>
    <?php endif; ?>
    <div class="ranking-slider__viewport">
      <ol class="<?php echo esc_attr($args['list_class']); ?>" data-slider-track>
        <?php foreach ($entries as $entry) :
      $name = isset($entry['name']) ? $entry['name'] : '';
      $position = isset($entry['position']) ? $entry['position'] : '';
      $rank_label = isset($entry['current_rank_label']) ? $entry['current_rank_label'] : '';
      $entry_id = isset($entry['id']) ? sanitize_key($entry['id']) : '';
      $image_url = isset($entry['image_url']) ? $entry['image_url'] : '';
      $image_link = isset($entry['image_link']) ? $entry['image_link'] : '';
      $image_link = gachasoku_apply_affiliate_url($image_link);
      $content = isset($entry['content']) ? $entry['content'] : '';
      $detail_label = isset($entry['detail_label']) ? $entry['detail_label'] : '';
      $detail_url = isset($entry['detail_url']) ? $entry['detail_url'] : '';
      $detail_url = gachasoku_apply_affiliate_url($detail_url);
      $official_label = isset($entry['official_label']) ? $entry['official_label'] : '';
      $official_url = isset($entry['official_url']) ? $entry['official_url'] : '';
      $official_url = gachasoku_apply_affiliate_url($official_url);
      $has_detail = $detail_label && $detail_url;
      $has_official = $official_label && $official_url;
      $has_actions = $has_detail || $has_official;
      $has_body = $name || $content;
      $stats = isset($entry['vote_stats']) ? $entry['vote_stats'] : ['wins' => 0, 'losses' => 0, 'logpos' => 0, 'formatted' => '0.0%'];
      $member_stats = isset($entry['member_vote_stats']) ? $entry['member_vote_stats'] : ['wins' => 0, 'losses' => 0, 'logpos' => 0, 'formatted' => '0.0%'];
      $vote_nonce = $entry_id ? wp_create_nonce('gachasoku_ranking_vote_' . $entry_id) : '';
      $card_classes = ['ranking-card'];
      if ($rank_label) {
        $card_classes[] = 'ranking-card--has-badge';
      }
      if (!$image_url) {
        $card_classes[] = 'ranking-card--no-image';
      }
      ?>
        <li class="<?php echo esc_attr($args['item_class']); ?>">
          <article class="<?php echo esc_attr(implode(' ', $card_classes)); ?>" <?php if ($entry_id) : ?>data-ranking-entry="<?php echo esc_attr($entry_id); ?>"<?php endif; ?>>
            <?php if ($rank_label) :
              $additional_label = ($position && $position !== $rank_label) ? $position : '';
              ?>
              <span class="ranking-card__badge">
                <strong class="ranking-card__badge-rank"><?php echo esc_html($rank_label); ?></strong>
                <?php if ($additional_label) : ?>
                  <span class="ranking-card__badge-label"><?php echo esc_html($additional_label); ?></span>
                <?php endif; ?>
              </span>
            <?php endif; ?>
            <div class="ranking-card__main">
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
              <?php if ($has_body) : ?>
                <div class="ranking-card__body">
                  <?php if ($name) : ?>
                    <h3 class="ranking-card__name"><?php echo esc_html($name); ?></h3>
                  <?php endif; ?>
                  <?php if ($content) : ?>
                    <div class="ranking-card__content"><?php echo wpautop(wp_kses_post($content)); ?></div>
                  <?php endif; ?>
                </div>
              <?php endif; ?>
            </div>
            <div class="ranking-card__metrics" <?php if ($entry_id) : ?>data-ranking-stats="<?php echo esc_attr($entry_id); ?>"<?php endif; ?>>
              <div class="ranking-card__metric">
                <span class="ranking-card__metric-label">勝ち</span>
                <span class="ranking-card__metric-value" data-stat="wins"><?php echo esc_html(number_format_i18n($stats['wins'])); ?></span>
              </div>
              <div class="ranking-card__metric">
                <span class="ranking-card__metric-label">負け</span>
                <span class="ranking-card__metric-value" data-stat="losses"><?php echo esc_html(number_format_i18n($stats['losses'])); ?></span>
              </div>
              <div class="ranking-card__metric">
                <span class="ranking-card__metric-label">ログポ</span>
                <span class="ranking-card__metric-value" data-stat="logpos"><?php echo esc_html(number_format_i18n($stats['logpos'])); ?></span>
              </div>
              <div class="ranking-card__metric ranking-card__metric--rate">
                <span class="ranking-card__metric-label">勝率</span>
                <span class="ranking-card__metric-value" data-stat="win-rate"><?php echo esc_html($stats['formatted']); ?></span>
              </div>
            </div>
            <?php if ($member_logged_in) : ?>
              <div class="ranking-card__personal" <?php if ($entry_id) : ?>data-ranking-personal="<?php echo esc_attr($entry_id); ?>"<?php endif; ?>>
                <h4 class="ranking-card__personal-title">あなたの戦績</h4>
                <ul class="ranking-card__personal-stats">
                  <li><span>勝ち</span><span data-personal="wins"><?php echo esc_html(number_format_i18n($member_stats['wins'])); ?></span></li>
                  <li><span>負け</span><span data-personal="losses"><?php echo esc_html(number_format_i18n($member_stats['losses'])); ?></span></li>
                  <li><span>ログポ</span><span data-personal="logpos"><?php echo esc_html(number_format_i18n($member_stats['logpos'])); ?></span></li>
                  <li class="ranking-card__personal-rate"><span>勝率</span><span data-personal="win-rate"><?php echo esc_html($member_stats['formatted']); ?></span></li>
                </ul>
              </div>
            <?php endif; ?>
            <div class="ranking-card__votes"<?php if ($entry_id) : ?> data-ranking-actions="<?php echo esc_attr($entry_id); ?>"<?php endif; ?>>
              <?php if (!$member_logged_in) : ?>
                <p class="ranking-card__vote-note">投票するには<a href="<?php echo esc_url($login_url); ?>">ログイン</a>してください。未登録の方は<a href="<?php echo esc_url($register_url); ?>">会員登録</a>が必要です。</p>
              <?php elseif (!$member_can_vote) : ?>
                <p class="ranking-card__vote-note">現在のステータスでは投票できません。</p>
              <?php elseif ($entry_id && $vote_nonce) : ?>
                <div class="ranking-card__vote-buttons">
                  <?php foreach ($vote_labels as $type => $label) : ?>
                    <button type="button" class="ranking-card__vote-button ranking-card__vote-button--<?php echo esc_attr($type); ?>" data-ranking-vote="<?php echo esc_attr($type); ?>" data-entry-id="<?php echo esc_attr($entry_id); ?>" data-nonce="<?php echo esc_attr($vote_nonce); ?>">
                      <?php echo esc_html($label); ?>
                    </button>
                  <?php endforeach; ?>
                </div>
                <p class="ranking-card__vote-note ranking-card__vote-note--hint">各ランキングには1時間に1度投票できます。</p>
              <?php else : ?>
                <p class="ranking-card__vote-note">投票情報を取得できませんでした。</p>
              <?php endif; ?>
            </div>
            <?php if ($has_actions) : ?>
              <div class="ranking-card__cta">
                <div class="ranking-card__actions">
                  <?php if ($has_detail) : ?>
                    <a class="ranking-card__button ranking-card__button--detail" href="<?php echo esc_url($detail_url); ?>" target="_blank" rel="noopener noreferrer">
                      <?php echo esc_html($detail_label); ?>
                    </a>
                  <?php endif; ?>
                  <?php if ($has_official) : ?>
                    <a class="ranking-card__button ranking-card__button--official" href="<?php echo esc_url($official_url); ?>" target="_blank" rel="noopener noreferrer">
                      <?php echo esc_html($official_label); ?>
                    </a>
                  <?php endif; ?>
                </div>
              </div>
            <?php endif; ?>
          </article>
        </li>
        <?php endforeach; ?>
      </ol>
    </div>
    <?php if ($entry_count > 1) : ?>
      <button class="ranking-slider__nav ranking-slider__nav--next" type="button" aria-label="次のランキング" data-slider-next>&rsaquo;</button>
    <?php endif; ?>
  </div>
  <?php
  return ob_get_clean();
}

add_action('wp_ajax_gachasoku_ranking_vote', 'gachasoku_ajax_ranking_vote');
add_action('wp_ajax_nopriv_gachasoku_ranking_vote', 'gachasoku_ajax_ranking_vote');
function gachasoku_ajax_ranking_vote() {
  $entry_id = isset($_POST['entry_id']) ? sanitize_key(wp_unslash($_POST['entry_id'])) : '';
  $vote_type = isset($_POST['vote_type']) ? sanitize_key(wp_unslash($_POST['vote_type'])) : '';
  $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';

  if ($entry_id === '' || $vote_type === '' || $nonce === '') {
    wp_send_json_error(['message' => '不正なリクエストです。'], 400);
  }

  if (!wp_verify_nonce($nonce, 'gachasoku_ranking_vote_' . $entry_id)) {
    wp_send_json_error(['message' => 'リクエストの有効期限が切れました。再度お試しください。'], 400);
  }

  $allowed = ['win', 'lose', 'logpo'];
  if (!in_array($vote_type, $allowed, true)) {
    wp_send_json_error(['message' => '選択された投票は利用できません。'], 400);
  }

  $entry = gachasoku_find_ranking_entry($entry_id);
  if (!$entry) {
    wp_send_json_error(['message' => '指定されたランキングが見つかりません。'], 404);
  }

  if (!function_exists('gachasoku_is_member_logged_in') || !gachasoku_is_member_logged_in()) {
    wp_send_json_error(['message' => '投票にはログインが必要です。'], 403);
  }

  $member_id = gachasoku_get_current_member_id();
  if (!$member_id) {
    wp_send_json_error(['message' => '会員情報を取得できませんでした。'], 403);
  }

  if (!function_exists('gachasoku_get_member_status')) {
    wp_send_json_error(['message' => '会員機能が利用できません。'], 500);
  }

  $status = gachasoku_get_member_status($member_id);
  if (!defined('GACHASOKU_MEMBER_STATUS_ACTIVE') || $status !== GACHASOKU_MEMBER_STATUS_ACTIVE) {
    wp_send_json_error(['message' => '現在のステータスでは投票できません。'], 403);
  }

  $cooldown = gachasoku_get_member_vote_cooldown($entry_id, $member_id);
  if ($cooldown > 0) {
    $minutes = ceil($cooldown / MINUTE_IN_SECONDS);
    $message = $minutes > 1
      ? sprintf('次の投票まであと%d分お待ちください。', $minutes)
      : '次の投票までしばらくお待ちください。';
    wp_send_json_error([
      'message' => $message,
      'retry_after' => $cooldown,
    ], 400);
  }

  $result = gachasoku_record_ranking_vote($entry_id, $member_id, $vote_type);
  if (is_wp_error($result)) {
    wp_send_json_error(['message' => $result->get_error_message()], 500);
  }

  $totals = gachasoku_get_ranking_vote_totals([$entry_id]);
  $stats = isset($totals[$entry_id]) ? $totals[$entry_id] : ['wins' => 0, 'losses' => 0, 'logpos' => 0];
  $wins = intval($stats['wins']);
  $losses = intval($stats['losses']);
  $logpos = intval($stats['logpos']);
  $win_rate = gachasoku_calculate_win_rate($wins, $losses);

  $member_totals = gachasoku_get_member_ranking_vote_totals($member_id, [$entry_id]);
  $member_stats = isset($member_totals[$entry_id]) ? $member_totals[$entry_id] : ['wins' => 0, 'losses' => 0, 'logpos' => 0];
  $member_wins = intval($member_stats['wins']);
  $member_losses = intval($member_stats['losses']);
  $member_logpos = intval($member_stats['logpos']);
  $member_rate = gachasoku_calculate_win_rate($member_wins, $member_losses);

  $ranking_entries = gachasoku_get_sorted_ranking_entries($member_id);
  $ranking_payload = [];

  if (!empty($ranking_entries)) {
    foreach ($ranking_entries as $ranking_entry) {
      if (!isset($ranking_entry['id'])) {
        continue;
      }

      $ranking_entry_id = sanitize_key($ranking_entry['id']);
      if ($ranking_entry_id === '') {
        continue;
      }

      $ranking_payload[$ranking_entry_id] = [
        'rank'  => isset($ranking_entry['current_rank']) ? intval($ranking_entry['current_rank']) : 0,
        'label' => isset($ranking_entry['current_rank_label']) ? $ranking_entry['current_rank_label'] : '',
        'stats' => isset($ranking_entry['vote_stats']) ? [
          'wins'      => intval($ranking_entry['vote_stats']['wins']),
          'losses'    => intval($ranking_entry['vote_stats']['losses']),
          'logpos'    => intval($ranking_entry['vote_stats']['logpos']),
          'win_rate'  => isset($ranking_entry['vote_stats']['win_rate']) ? floatval($ranking_entry['vote_stats']['win_rate']) : gachasoku_calculate_win_rate(intval($ranking_entry['vote_stats']['wins']), intval($ranking_entry['vote_stats']['losses'])),
          'formatted' => isset($ranking_entry['vote_stats']['formatted']) ? $ranking_entry['vote_stats']['formatted'] : number_format_i18n(0, 1) . '%',
        ] : [],
        'member' => isset($ranking_entry['member_vote_stats']) ? [
          'wins'      => intval($ranking_entry['member_vote_stats']['wins']),
          'losses'    => intval($ranking_entry['member_vote_stats']['losses']),
          'logpos'    => intval($ranking_entry['member_vote_stats']['logpos']),
          'win_rate'  => isset($ranking_entry['member_vote_stats']['win_rate']) ? floatval($ranking_entry['member_vote_stats']['win_rate']) : gachasoku_calculate_win_rate(intval($ranking_entry['member_vote_stats']['wins']), intval($ranking_entry['member_vote_stats']['losses'])),
          'formatted' => isset($ranking_entry['member_vote_stats']['formatted']) ? $ranking_entry['member_vote_stats']['formatted'] : number_format_i18n(0, 1) . '%',
        ] : [],
      ];
    }
  }

  $response_stats = [
    'wins'      => $wins,
    'losses'    => $losses,
    'logpos'    => $logpos,
    'win_rate'  => $win_rate,
    'formatted' => number_format_i18n($win_rate, 1) . '%',
  ];

  $response_member = [
    'wins'      => $member_wins,
    'losses'    => $member_losses,
    'logpos'    => $member_logpos,
    'win_rate'  => $member_rate,
    'formatted' => number_format_i18n($member_rate, 1) . '%',
  ];

  if (isset($ranking_payload[$entry_id])) {
    if (!empty($ranking_payload[$entry_id]['stats'])) {
      $response_stats = array_merge($response_stats, $ranking_payload[$entry_id]['stats']);
    }
    if (!empty($ranking_payload[$entry_id]['member'])) {
      $response_member = array_merge($response_member, $ranking_payload[$entry_id]['member']);
    }
  }

  wp_send_json_success([
    'entryId' => $entry_id,
    'stats'   => $response_stats,
    'member'  => $response_member,
    'ranking' => $ranking_payload,
    'cooldown' => HOUR_IN_SECONDS,
    'message'  => '投票ありがとうございました。',
  ]);
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
    $entry_id = isset($entry['id']) ? sanitize_key($entry['id']) : '';
    if ($entry_id === '') {
      $entry_id = gachasoku_generate_ranking_entry_id();
    }
    $name = isset($entry['name']) ? sanitize_text_field($entry['name']) : '';
    $position = isset($entry['position']) ? sanitize_text_field($entry['position']) : '';
    $image_url = isset($entry['image_url']) ? esc_url_raw($entry['image_url']) : '';
    $image_link = isset($entry['image_link']) ? esc_url_raw($entry['image_link']) : '';
    $content = isset($entry['content']) ? wp_kses_post($entry['content']) : '';
    $detail_label = isset($entry['detail_label']) ? sanitize_text_field($entry['detail_label']) : '';
    $detail_url = isset($entry['detail_url']) ? esc_url_raw($entry['detail_url']) : '';
    $official_label = isset($entry['official_label']) ? sanitize_text_field($entry['official_label']) : '';
    $official_url = isset($entry['official_url']) ? esc_url_raw($entry['official_url']) : '';

    if (
      $name === '' &&
      $position === '' &&
      $image_url === '' &&
      $image_link === '' &&
      $content === '' &&
      $detail_label === '' &&
      $detail_url === '' &&
      $official_label === '' &&
      $official_url === ''
    ) {
      continue;
    }

    $entries[] = [
      'id' => $entry_id,
      'name' => $name,
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
  $entry_id = isset($entry['id']) ? sanitize_key($entry['id']) : '';
  if ($entry_id === '' && $index !== '__INDEX__') {
    $entry_id = gachasoku_generate_ranking_entry_id();
  }
  $name = isset($entry['name']) ? $entry['name'] : '';
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
      <input type="hidden" name="gachasoku_ranking_entries[<?php echo esc_attr($index); ?>][id]" value="<?php echo esc_attr($entry_id); ?>" />
      <label>
        名前
        <input type="text" name="gachasoku_ranking_entries[<?php echo esc_attr($index); ?>][name]" value="<?php echo esc_attr($name); ?>" placeholder="例: サイト名" />
      </label>
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

function gachasoku_get_affiliate_mappings() {
  $mappings = get_option('gachasoku_affiliate_mappings', []);
  if (!is_array($mappings)) {
    $mappings = [];
  }

  return $mappings;
}

function gachasoku_sanitize_affiliate_domain($value) {
  $value = trim($value);
  if ($value === '') {
    return '';
  }

  if (!preg_match('#^[a-z][a-z0-9+.-]*://#i', $value)) {
    $value = 'https://' . $value;
  }

  $parts = wp_parse_url($value);
  if ($parts && isset($parts['host'])) {
    $domain = strtolower($parts['host']);
  } else {
    $domain = strtolower(trim($value, '/'));
  }

  return $domain;
}

function gachasoku_save_affiliate_mappings($raw_entries) {
  $mappings = [];

  if (!is_array($raw_entries)) {
    $raw_entries = [];
  }

  foreach ($raw_entries as $entry) {
    $domain = isset($entry['domain']) ? gachasoku_sanitize_affiliate_domain($entry['domain']) : '';
    $url = isset($entry['url']) ? esc_url_raw($entry['url']) : '';

    if ($domain === '' || $url === '') {
      continue;
    }

    $mappings[] = [
      'domain' => $domain,
      'url' => $url,
    ];
  }

  update_option('gachasoku_affiliate_mappings', $mappings);
  return $mappings;
}

function gachasoku_apply_affiliate_url($url, $mappings = null) {
  $url = trim($url);
  if ($url === '') {
    return $url;
  }

  if ($mappings === null) {
    $mappings = gachasoku_get_affiliate_mappings();
  }

  if (empty($mappings)) {
    return $url;
  }

  $parts = wp_parse_url($url);
  if (!$parts || empty($parts['host'])) {
    return $url;
  }

  $host = strtolower($parts['host']);

  foreach ($mappings as $mapping) {
    if (empty($mapping['domain']) || empty($mapping['url'])) {
      continue;
    }

    $domain = strtolower($mapping['domain']);
    $pattern = '/(^|\.)' . preg_quote($domain, '/') . '$/';

    if (preg_match($pattern, $host)) {
      return $mapping['url'];
    }
  }

  return $url;
}

function gachasoku_filter_affiliate_links($content) {
  if (empty($content)) {
    return $content;
  }

  $mappings = gachasoku_get_affiliate_mappings();
  if (empty($mappings)) {
    return $content;
  }

  return preg_replace_callback(
    '/(<a\b[^>]*\bhref=["\'])([^"\']+)(["\'][^>]*>)/i',
    function($matches) use ($mappings) {
      $original = $matches[2];
      $rewritten = gachasoku_apply_affiliate_url($original, $mappings);
      if ($rewritten === $original) {
        return $matches[1] . $original . $matches[3];
      }
      return $matches[1] . esc_url($rewritten) . $matches[3];
    },
    $content
  );
}

add_filter('the_content', 'gachasoku_filter_affiliate_links', 20);
add_filter('the_excerpt', 'gachasoku_filter_affiliate_links', 20);
add_filter('widget_text', 'gachasoku_filter_affiliate_links', 20);
add_filter('widget_text_content', 'gachasoku_filter_affiliate_links', 20);
add_filter('term_description', 'gachasoku_filter_affiliate_links', 20);

function gachasoku_register_link_manager_page() {
  add_menu_page(
    'リンク置換管理',
    'リンク置換管理',
    'manage_options',
    'gachasoku-link-manager',
    'gachasoku_render_link_manager_page',
    'dashicons-admin-links',
    21
  );
}
add_action('admin_menu', 'gachasoku_register_link_manager_page');

function gachasoku_enqueue_link_admin_assets($hook) {
  if ($hook !== 'toplevel_page_gachasoku-link-manager') {
    return;
  }

  wp_enqueue_script(
    'gachasoku-link-admin',
    get_template_directory_uri() . '/js/link-admin.js',
    ['jquery'],
    wp_get_theme()->get('Version'),
    true
  );
  wp_enqueue_style(
    'gachasoku-link-admin',
    get_template_directory_uri() . '/css/link-admin.css',
    [],
    wp_get_theme()->get('Version')
  );
}
add_action('admin_enqueue_scripts', 'gachasoku_enqueue_link_admin_assets');

function gachasoku_render_link_manager_page() {
  if (!current_user_can('manage_options')) {
    return;
  }

  $mappings = gachasoku_get_affiliate_mappings();

  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_admin_referer('gachasoku_links_save', 'gachasoku_links_nonce');

    $raw_entries = isset($_POST['gachasoku_affiliate_mappings']) && is_array($_POST['gachasoku_affiliate_mappings'])
      ? $_POST['gachasoku_affiliate_mappings']
      : [];
    $mappings = gachasoku_save_affiliate_mappings($raw_entries);

    add_settings_error('gachasoku_links', 'gachasoku_links_updated', 'リンク設定を更新しました。', 'updated');
  }

  settings_errors('gachasoku_links');
  ?>
  <div class="wrap">
    <h1>リンク置換管理</h1>
    <form method="post" class="gachasoku-link-admin">
      <?php wp_nonce_field('gachasoku_links_save', 'gachasoku_links_nonce'); ?>
      <p>ここで指定したルートドメインと置換先URLに基づき、サイト内のリンクを自動でアフィリエイトリンクへ差し替えます。</p>
      <div id="gachasoku-link-entries" class="gachasoku-link-entries">
        <?php if (!empty($mappings)) : ?>
          <?php foreach ($mappings as $index => $mapping) : ?>
            <?php gachasoku_render_link_entry_fields($index, $mapping); ?>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
      <button type="button" class="button" id="gachasoku-add-link">設定を追加</button>
      <p class="submit">
        <button type="submit" class="button button-primary">リンク設定を保存</button>
      </p>
    </form>
    <script type="text/template" id="gachasoku-link-entry-template">
      <?php gachasoku_render_link_entry_fields('__INDEX__', []); ?>
    </script>
  </div>
  <?php
}

function gachasoku_render_link_entry_fields($index, $entry) {
  $domain = isset($entry['domain']) ? $entry['domain'] : '';
  $url = isset($entry['url']) ? $entry['url'] : '';
  ?>
  <div class="gachasoku-link-entry" data-index="<?php echo esc_attr($index); ?>">
    <h2>設定 <span class="gachasoku-link-entry__number"></span></h2>
    <div class="gachasoku-link-fields">
      <label>
        置換対象ルートドメイン
        <input type="text" name="gachasoku_affiliate_mappings[<?php echo esc_attr($index); ?>][domain]" value="<?php echo esc_attr($domain); ?>" placeholder="例: oripa.ex-toreca.com" />
      </label>
      <label>
        置換後URL
        <input type="url" name="gachasoku_affiliate_mappings[<?php echo esc_attr($index); ?>][url]" value="<?php echo esc_attr($url); ?>" placeholder="https://example.com/?id=123" />
      </label>
    </div>
    <p class="description">上記ドメインを含むリンクは、表示時にすべて置換後URLへリダイレクトされます。</p>
    <button type="button" class="button-link-delete gachasoku-link-remove">この設定を削除</button>
    <hr />
  </div>
  <?php
}


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

    $normalized_event = $event;
    if (isset($normalized_event['url']) && $normalized_event['url'] !== '') {
      $normalized_event['url'] = gachasoku_apply_affiliate_url($normalized_event['url']);
    }

    if ($type === 'monthly') {
      $day = isset($event['month_day']) ? intval($event['month_day']) : 0;
      if ($day < 1) {
        continue;
      }
      if ($day > $days_in_month) {
        $day = $days_in_month;
      }
      $calendar[$day][] = $normalized_event;
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
          $calendar[$day][] = $normalized_event;
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
      $calendar[$day][] = $normalized_event;
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
  <div
    class="gachasoku-calendar"
    data-calendar
    data-calendar-year="<?php echo esc_attr($year); ?>"
    data-calendar-month="<?php echo esc_attr($month); ?>"
  >
    <header class="gachasoku-calendar__header">
      <h2 class="gachasoku-calendar__title"><?php echo esc_html($month_label); ?></h2>
    </header>
    <div class="gachasoku-calendar__table" data-calendar-table>
      <div class="gachasoku-calendar__row gachasoku-calendar__row--head">
        <?php foreach ($weekday_labels as $label) : ?>
          <div class="gachasoku-calendar__cell gachasoku-calendar__cell--head" aria-hidden="true"><?php echo esc_html($label); ?></div>
        <?php endforeach; ?>
      </div>
      <?php $week_index = 0; ?>
      <?php foreach ($weeks as $week_days) :
        $week_index++;
        ?>
        <div class="gachasoku-calendar__row" data-calendar-week="<?php echo esc_attr($week_index); ?>">
          <?php foreach ($week_days as $weekday_index => $day) :
            $weekday_label = isset($weekday_labels[$weekday_index]) ? $weekday_labels[$weekday_index] : '';
            ?>
            <?php if ($day === null) : ?>
              <div class="gachasoku-calendar__cell gachasoku-calendar__cell--empty" aria-hidden="true"></div>
            <?php else :
              $events = isset($events_by_day[$day]) ? $events_by_day[$day] : [];
              $has_events = !empty($events);
              $weekday_attr = $weekday_label !== '' ? sprintf(' data-weekday="%s"', esc_attr($weekday_label)) : '';
              ?>
              <div class="gachasoku-calendar__cell <?php echo $has_events ? 'gachasoku-calendar__cell--has-events' : ''; ?>"<?php echo $weekday_attr; ?>>
                <div class="gachasoku-calendar__day"><?php echo esc_html($day); ?></div>
                <?php if ($has_events) : ?>
                  <ul class="gachasoku-calendar__events">
                    <?php foreach ($events as $index => $event) :
                      $color = gachasoku_get_calendar_event_color($event, $index);
                      $title = isset($event['title']) ? $event['title'] : '';
                      $url = isset($event['url']) ? $event['url'] : '';
                      $url = gachasoku_apply_affiliate_url($url);
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
      $pickup_url = gachasoku_apply_affiliate_url($pickup_url);
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

require_once get_template_directory() . '/inc/membership.php';

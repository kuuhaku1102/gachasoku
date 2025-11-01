<?php
if (!defined('ABSPATH')) {
  exit;
}

if (!defined('GACHASOKU_MEMBER_STATUS_ACTIVE')) {
  define('GACHASOKU_MEMBER_STATUS_ACTIVE', 'active');
}
if (!defined('GACHASOKU_MEMBER_STATUS_SUSPENDED')) {
  define('GACHASOKU_MEMBER_STATUS_SUSPENDED', 'suspended');
}
if (!defined('GACHASOKU_MEMBER_STATUS_WITHDRAWN')) {
  define('GACHASOKU_MEMBER_STATUS_WITHDRAWN', 'withdrawn');
}

if (!defined('GACHASOKU_MEMBERSHIP_DB_VERSION')) {
  define('GACHASOKU_MEMBERSHIP_DB_VERSION', '2.1.0');
}

if (!defined('GACHASOKU_MEMBERSHIP_PAGES_VERSION')) {
  define('GACHASOKU_MEMBERSHIP_PAGES_VERSION', '1.0.0');
}

if (!defined('GACHASOKU_MEMBER_SESSION_COOKIE')) {
  define('GACHASOKU_MEMBER_SESSION_COOKIE', 'gachasoku_member_session');
}

function gachasoku_get_members_table() {
  global $wpdb;
  return $wpdb->prefix . 'gachasoku_members';
}

function gachasoku_get_member_sessions_table() {
  global $wpdb;
  return $wpdb->prefix . 'gachasoku_member_sessions';
}

function gachasoku_get_member_status_options() {
  return [
    GACHASOKU_MEMBER_STATUS_ACTIVE   => '有効',
    GACHASOKU_MEMBER_STATUS_SUSPENDED => '停止',
    GACHASOKU_MEMBER_STATUS_WITHDRAWN => '退会済',
  ];
}

function gachasoku_activate_membership_features() {
  gachasoku_register_member_role();
  gachasoku_install_membership_tables();
  gachasoku_create_membership_pages();
}
add_action('after_switch_theme', 'gachasoku_activate_membership_features');

add_action('init', 'gachasoku_maybe_create_membership_pages');
function gachasoku_maybe_create_membership_pages() {
  $definitions = gachasoku_get_membership_page_definitions();
  $stored = get_option('gachasoku_membership_pages', []);
  $needs_refresh = get_option('gachasoku_membership_pages_version') !== GACHASOKU_MEMBERSHIP_PAGES_VERSION;

  if (!$needs_refresh) {
    foreach ($definitions as $slug => $data) {
      $page_id = isset($stored[$slug]) ? intval($stored[$slug]) : 0;
      if ($page_id <= 0 || get_post_status($page_id) === false || get_post_status($page_id) === 'trash') {
        $needs_refresh = true;
        break;
      }
    }
  }

  if ($needs_refresh) {
    gachasoku_create_membership_pages();
  }
}

function gachasoku_get_membership_page_definitions() {
  return [
    'member-register' => [
      'title' => '会員登録',
      'content' => "[gachasoku_register_form]\n\n[gachasoku_membership_links context=\"register\"]",
    ],
    'member-login' => [
      'title' => 'ログイン',
      'content' => "[gachasoku_login_form]\n\n[gachasoku_membership_links context=\"login\"]",
    ],
    'member-password-reset' => [
      'title' => 'パスワード再設定',
      'content' => "[gachasoku_password_reset_form]\n\n[gachasoku_membership_links context=\"password\"]",
    ],
    'member-dashboard' => [
      'title' => 'マイページ',
      'content' => '[gachasoku_member_dashboard]',
    ],
    'campaigns' => [
      'title' => 'キャンペーン一覧',
      'content' => '[gachasoku_campaigns status="open"]',
    ],
  ];
}

function gachasoku_create_membership_pages() {
  $definitions = gachasoku_get_membership_page_definitions();
  $stored = get_option('gachasoku_membership_pages', []);
  $updated = false;

  foreach ($definitions as $slug => $data) {
    $page_id = isset($stored[$slug]) ? intval($stored[$slug]) : 0;
    if ($page_id > 0) {
      $page = get_post($page_id);
      if ($page && $page->post_status !== 'trash') {
        continue;
      }
    }

    $existing = get_page_by_path($slug, OBJECT, 'page');
    if ($existing && $existing->post_status !== 'trash') {
      $stored[$slug] = $existing->ID;
      $updated = true;
      continue;
    }

    $page_id = wp_insert_post([
      'post_title' => wp_strip_all_tags($data['title']),
      'post_name' => sanitize_title($slug),
      'post_type' => 'page',
      'post_status' => 'publish',
      'post_content' => wp_slash($data['content']),
      'comment_status' => 'closed',
      'ping_status' => 'closed',
    ]);

    if (!is_wp_error($page_id)) {
      $stored[$slug] = $page_id;
      $updated = true;
    }
  }

  if ($updated) {
    update_option('gachasoku_membership_pages', $stored);
  }

  update_option('gachasoku_membership_pages_version', GACHASOKU_MEMBERSHIP_PAGES_VERSION);
}

function gachasoku_get_membership_page_url($slug) {
  $stored = get_option('gachasoku_membership_pages', []);
  if (isset($stored[$slug])) {
    $permalink = get_permalink($stored[$slug]);
    if ($permalink) {
      return $permalink;
    }
  }

  $page = get_page_by_path($slug, OBJECT, 'page');
  if ($page) {
    return get_permalink($page->ID);
  }

  return home_url('/' . $slug . '/');
}

function gachasoku_get_membership_link_items($context = 'default') {
  $links = [];

  switch ($context) {
    case 'register':
      $links[] = [
        'label' => 'ログインはこちら',
        'url' => gachasoku_get_membership_page_url('member-login'),
      ];
      $links[] = [
        'label' => 'パスワードをお忘れの方',
        'url' => gachasoku_get_membership_page_url('member-password-reset'),
      ];
      break;

    case 'login':
      $links[] = [
        'label' => '会員登録はこちら',
        'url' => gachasoku_get_membership_page_url('member-register'),
      ];
      $links[] = [
        'label' => 'パスワードをお忘れの方',
        'url' => gachasoku_get_membership_page_url('member-password-reset'),
      ];
      break;

    case 'password':
      $links[] = [
        'label' => 'ログイン画面へ戻る',
        'url' => gachasoku_get_membership_page_url('member-login'),
      ];
      $links[] = [
        'label' => '新規会員登録',
        'url' => gachasoku_get_membership_page_url('member-register'),
      ];
      break;

    case 'dashboard':
      $links[] = [
        'label' => '公開中のキャンペーンを見る',
        'url' => gachasoku_get_membership_page_url('campaigns'),
      ];
      break;

    default:
      $links[] = [
        'label' => '会員登録',
        'url' => gachasoku_get_membership_page_url('member-register'),
      ];
      $links[] = [
        'label' => 'ログイン',
        'url' => gachasoku_get_membership_page_url('member-login'),
      ];
      break;
  }

  return apply_filters('gachasoku_membership_link_items', $links, $context);
}

function gachasoku_render_membership_links($context = 'default') {
  $links = array_filter(gachasoku_get_membership_link_items($context), function ($link) {
    return !empty($link['url']) && !empty($link['label']);
  });

  if (empty($links)) {
    return '';
  }

  ob_start();
  echo '<div class="gachasoku-membership-links">';
  foreach ($links as $link) {
    printf(
      '<a class="gachasoku-membership-links__item" href="%1$s">%2$s</a>',
      esc_url($link['url']),
      esc_html($link['label'])
    );
  }
  echo '</div>';
  return ob_get_clean();
}

add_shortcode('gachasoku_membership_links', 'gachasoku_membership_links_shortcode');
function gachasoku_membership_links_shortcode($atts = []) {
  $atts = shortcode_atts([
    'context' => 'default',
  ], $atts, 'gachasoku_membership_links');

  return gachasoku_render_membership_links($atts['context']);
}

function gachasoku_get_member_by_id($member_id) {
  $member_id = intval($member_id);
  if (!$member_id) {
    return null;
  }

  global $wpdb;
  $table = gachasoku_get_members_table();
  $member = $wpdb->get_row(
    $wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $member_id),
    ARRAY_A
  );

  return $member ?: null;
}

function gachasoku_get_member_by_email($email) {
  $email = sanitize_email($email);
  if (!$email) {
    return null;
  }

  global $wpdb;
  $table = gachasoku_get_members_table();
  $member = $wpdb->get_row(
    $wpdb->prepare("SELECT * FROM {$table} WHERE email = %s", $email),
    ARRAY_A
  );

  return $member ?: null;
}

function gachasoku_prepare_member_record($member) {
  if (!$member) {
    return null;
  }

  return wp_parse_args($member, [
    'id' => 0,
    'name' => '',
    'email' => '',
    'status' => GACHASOKU_MEMBER_STATUS_ACTIVE,
    'created_at' => '',
    'updated_at' => '',
    'last_login' => '',
  ]);
}

function gachasoku_insert_member($name, $email, $password) {
  $email = sanitize_email($email);
  $name = wp_strip_all_tags($name);

  if (!$email || !$name) {
    return new WP_Error('invalid_member_data', '会員情報が正しく入力されていません。');
  }

  if (!is_email($email)) {
    return new WP_Error('invalid_member_email', 'メールアドレスの形式が正しくありません。');
  }

  if (gachasoku_get_member_by_email($email)) {
    return new WP_Error('member_exists', 'このメールアドレスは既に登録されています。');
  }

  $hash = wp_hash_password($password);
  $now = current_time('mysql');

  global $wpdb;
  $table = gachasoku_get_members_table();

  $inserted = $wpdb->insert(
    $table,
    [
      'name' => $name,
      'email' => $email,
      'password_hash' => $hash,
      'status' => GACHASOKU_MEMBER_STATUS_ACTIVE,
      'created_at' => $now,
      'updated_at' => $now,
    ],
    ['%s', '%s', '%s', '%s', '%s', '%s']
  );

  if (!$inserted) {
    return new WP_Error('member_insert_failed', '会員情報の保存に失敗しました。時間を置いて再度お試しください。');
  }

  return intval($wpdb->insert_id);
}

function gachasoku_update_member($member_id, $data) {
  $member = gachasoku_get_member_by_id($member_id);
  if (!$member) {
    return new WP_Error('member_not_found', '会員が見つかりません。');
  }

  $allowed = ['name', 'email', 'password_hash', 'status', 'last_login'];
  $update = [];
  $formats = [];

  foreach ($allowed as $key) {
    if (isset($data[$key])) {
      $update[$key] = $data[$key];
      $formats[] = '%s';
    }
  }

  if (empty($update)) {
    return true;
  }

  $update['updated_at'] = current_time('mysql');
  $formats[] = '%s';

  global $wpdb;
  $table = gachasoku_get_members_table();
  $result = $wpdb->update(
    $table,
    $update,
    ['id' => $member['id']],
    $formats,
    ['%d']
  );

  if ($result === false) {
    return new WP_Error('member_update_failed', '会員情報の更新に失敗しました。');
  }

  return true;
}

function gachasoku_update_member_email($member_id, $email) {
  $email = sanitize_email($email);
  if (!$email || !is_email($email)) {
    return new WP_Error('invalid_member_email', 'メールアドレスの形式が正しくありません。');
  }

  $existing = gachasoku_get_member_by_email($email);
  if ($existing && intval($existing['id']) !== intval($member_id)) {
    return new WP_Error('member_exists', 'このメールアドレスは既に使用されています。');
  }

  return gachasoku_update_member($member_id, ['email' => $email]);
}

function gachasoku_update_member_password($member_id, $password) {
  if ($password === '') {
    return new WP_Error('invalid_member_password', 'パスワードが入力されていません。');
  }

  $hash = wp_hash_password($password);
  return gachasoku_update_member($member_id, ['password_hash' => $hash]);
}

function gachasoku_get_member_status($member_id) {
  $member = gachasoku_get_member_by_id($member_id);
  if (!$member || empty($member['status'])) {
    return GACHASOKU_MEMBER_STATUS_ACTIVE;
  }
  return $member['status'];
}

function gachasoku_set_member_status($member_id, $status) {
  $options = gachasoku_get_member_status_options();
  if (!isset($options[$status])) {
    return false;
  }

  $result = gachasoku_update_member($member_id, ['status' => $status]);
  return !is_wp_error($result);
}

function gachasoku_delete_member($member_id) {
  global $wpdb;
  $table = gachasoku_get_members_table();
  $member_id = intval($member_id);
  if (!$member_id) {
    return false;
  }

  $deleted = $wpdb->delete($table, ['id' => $member_id], ['%d']);
  if ($deleted !== false) {
    gachasoku_delete_member_sessions($member_id);
    $entries_table = $wpdb->prefix . 'gachasoku_campaign_entries';
    $wpdb->delete($entries_table, ['user_id' => $member_id], ['%d']);
  }
  return $deleted !== false;
}

function gachasoku_generate_member_token() {
  return wp_generate_password(64, false, false);
}

function gachasoku_delete_member_sessions($member_id) {
  global $wpdb;
  $table = gachasoku_get_member_sessions_table();
  $wpdb->delete($table, ['member_id' => intval($member_id)], ['%d']);
}

function gachasoku_clear_member_cookie() {
  $cookie_name = GACHASOKU_MEMBER_SESSION_COOKIE;
  if (isset($_COOKIE[$cookie_name])) {
    unset($_COOKIE[$cookie_name]);
  }
  $expire = time() - HOUR_IN_SECONDS;
  setcookie($cookie_name, '', $expire, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true);
  if (COOKIEPATH !== SITECOOKIEPATH) {
    setcookie($cookie_name, '', $expire, SITECOOKIEPATH, COOKIE_DOMAIN, is_ssl(), true);
  }
}

function gachasoku_member_login($member, $remember = false) {
  $member = is_array($member) ? $member : gachasoku_get_member_by_id($member);
  if (!$member) {
    return false;
  }

  global $wpdb;
  $table = gachasoku_get_member_sessions_table();

  $token = gachasoku_generate_member_token();
  $lifetime = $remember ? MONTH_IN_SECONDS : WEEK_IN_SECONDS;
  $created = current_time('mysql');
  $expires_timestamp = current_time('timestamp') + $lifetime;
  $expires = wp_date('Y-m-d H:i:s', $expires_timestamp);

  $wpdb->insert(
    $table,
    [
      'member_id' => intval($member['id']),
      'session_token' => $token,
      'created_at' => $created,
      'expires_at' => $expires,
      'remember' => $remember ? 1 : 0,
      'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? substr(wp_unslash($_SERVER['HTTP_USER_AGENT']), 0, 191) : '',
      'ip_address' => isset($_SERVER['REMOTE_ADDR']) ? substr(wp_unslash($_SERVER['REMOTE_ADDR']), 0, 100) : '',
    ],
    ['%d', '%s', '%s', '%s', '%d', '%s', '%s']
  );

  $expire_cookie = $expires_timestamp;
  setcookie(GACHASOKU_MEMBER_SESSION_COOKIE, $token, $expire_cookie, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true);
  if (COOKIEPATH !== SITECOOKIEPATH) {
    setcookie(GACHASOKU_MEMBER_SESSION_COOKIE, $token, $expire_cookie, SITECOOKIEPATH, COOKIE_DOMAIN, is_ssl(), true);
  }

  gachasoku_update_member($member['id'], ['last_login' => $created]);
  gachasoku_get_current_member(true);

  return true;
}

function gachasoku_member_logout() {
  $token = isset($_COOKIE[GACHASOKU_MEMBER_SESSION_COOKIE]) ? sanitize_text_field(wp_unslash($_COOKIE[GACHASOKU_MEMBER_SESSION_COOKIE])) : '';
  if ($token) {
    global $wpdb;
    $table = gachasoku_get_member_sessions_table();
    $wpdb->delete($table, ['session_token' => $token], ['%s']);
  }
  gachasoku_clear_member_cookie();
  gachasoku_get_current_member(true);
}

function gachasoku_get_current_member($force_refresh = false) {
  static $member = null;
  static $checked = false;

  if ($force_refresh) {
    $member = null;
    $checked = false;
  }

  if ($checked) {
    return $member;
  }

  $checked = true;

  $token = isset($_COOKIE[GACHASOKU_MEMBER_SESSION_COOKIE]) ? sanitize_text_field(wp_unslash($_COOKIE[GACHASOKU_MEMBER_SESSION_COOKIE])) : '';
  if (!$token) {
    return null;
  }

  global $wpdb;
  $table = gachasoku_get_member_sessions_table();
  $session = $wpdb->get_row(
    $wpdb->prepare("SELECT * FROM {$table} WHERE session_token = %s", $token),
    ARRAY_A
  );

  if (!$session) {
    gachasoku_clear_member_cookie();
    return null;
  }

  if (strtotime($session['expires_at']) < current_time('timestamp')) {
    $wpdb->delete($table, ['session_token' => $token], ['%s']);
    gachasoku_clear_member_cookie();
    return null;
  }

  $member = gachasoku_get_member_by_id($session['member_id']);
  if (!$member) {
    $wpdb->delete($table, ['session_token' => $token], ['%s']);
    gachasoku_clear_member_cookie();
    return null;
  }

  return gachasoku_prepare_member_record($member);
}

function gachasoku_get_current_member_id() {
  $member = gachasoku_get_current_member();
  return $member ? intval($member['id']) : 0;
}

function gachasoku_is_member_logged_in() {
  return (bool) gachasoku_get_current_member();
}

function gachasoku_get_member_logout_url() {
  return wp_nonce_url(add_query_arg('gachasoku_member_logout', '1'), 'gachasoku_member_logout');
}

function gachasoku_register_member_role() {
  add_role(
    'gachasoku_member',
    '会員',
    [
      'read' => true,
    ]
  );
}

function gachasoku_install_membership_tables() {
  global $wpdb;

  $installed = get_option('gachasoku_membership_db_version');
  if ($installed === GACHASOKU_MEMBERSHIP_DB_VERSION) {
    return;
  }

  require_once ABSPATH . 'wp-admin/includes/upgrade.php';

  $charset_collate = $wpdb->get_charset_collate();
  $members_table = gachasoku_get_members_table();
  $sessions_table = gachasoku_get_member_sessions_table();
  $entries_table = $wpdb->prefix . 'gachasoku_campaign_entries';
  $logs_table = $wpdb->prefix . 'gachasoku_campaign_draw_logs';
  $votes_table = $wpdb->prefix . 'gachasoku_ranking_votes';

  $members_sql = "CREATE TABLE {$members_table} (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    name varchar(191) NOT NULL,
    email varchar(191) NOT NULL,
    password_hash varchar(255) NOT NULL,
    status varchar(20) NOT NULL DEFAULT 'active',
    created_at datetime NOT NULL,
    updated_at datetime NOT NULL,
    last_login datetime DEFAULT NULL,
    PRIMARY KEY  (id),
    UNIQUE KEY email (email)
  ) {$charset_collate};";

  $sessions_sql = "CREATE TABLE {$sessions_table} (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    member_id bigint(20) unsigned NOT NULL,
    session_token varchar(191) NOT NULL,
    remember tinyint(1) NOT NULL DEFAULT 0,
    user_agent varchar(191) DEFAULT NULL,
    ip_address varchar(100) DEFAULT NULL,
    created_at datetime NOT NULL,
    expires_at datetime NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY session_token (session_token),
    KEY member_id (member_id)
  ) {$charset_collate};";

  $entries_sql = "CREATE TABLE {$entries_table} (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    campaign_id bigint(20) unsigned NOT NULL,
    user_id bigint(20) unsigned NOT NULL,
    status varchar(20) NOT NULL,
    applied_at datetime NOT NULL,
    updated_at datetime NOT NULL,
    result_at datetime NULL,
    PRIMARY KEY  (id),
    KEY campaign_id (campaign_id),
    KEY user_id (user_id),
    UNIQUE KEY campaign_user (campaign_id, user_id)
  ) {$charset_collate};";

  $logs_sql = "CREATE TABLE {$logs_table} (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    campaign_id bigint(20) unsigned NOT NULL,
    executed_at datetime NOT NULL,
    executed_by bigint(20) unsigned DEFAULT 0,
    winners longtext NULL,
    notes text NULL,
    PRIMARY KEY  (id),
    KEY campaign_id (campaign_id),
    KEY executed_at (executed_at)
  ) {$charset_collate};";

  $votes_sql = "CREATE TABLE {$votes_table} (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    entry_id varchar(191) NOT NULL,
    member_id bigint(20) unsigned NOT NULL,
    vote_type varchar(20) NOT NULL,
    created_at datetime NOT NULL,
    PRIMARY KEY  (id),
    KEY entry_member (entry_id(64), member_id, created_at),
    KEY member_id (member_id),
    KEY created_at (created_at)
  ) {$charset_collate};";

  dbDelta($members_sql);
  dbDelta($sessions_sql);
  dbDelta($entries_sql);
  dbDelta($logs_sql);
  dbDelta($votes_sql);

  gachasoku_migrate_existing_wp_members();

  update_option('gachasoku_membership_db_version', GACHASOKU_MEMBERSHIP_DB_VERSION);
}

function gachasoku_migrate_existing_wp_members() {
  if (get_option('gachasoku_membership_migrated')) {
    return;
  }

  if (!function_exists('get_users')) {
    return;
  }

  $wp_members = get_users([
    'role' => 'gachasoku_member',
    'number' => -1,
  ]);

  if (empty($wp_members)) {
    update_option('gachasoku_membership_migrated', 1);
    return;
  }

  global $wpdb;
  $members_table = gachasoku_get_members_table();
  $entries_table = $wpdb->prefix . 'gachasoku_campaign_entries';

  foreach ($wp_members as $user) {
    $status = get_user_meta($user->ID, '_gachasoku_member_status', true);
    if (!$status) {
      $status = GACHASOKU_MEMBER_STATUS_ACTIVE;
    }

    $existing = gachasoku_get_member_by_email($user->user_email);
    if ($existing) {
      $member_id = intval($existing['id']);
    } else {
      $member_id = intval($user->ID);
      $wpdb->insert(
        $members_table,
        [
          'id' => $member_id,
          'name' => $user->display_name ?: $user->user_login,
          'email' => $user->user_email,
          'password_hash' => $user->user_pass,
          'status' => $status,
          'created_at' => $user->user_registered,
          'updated_at' => current_time('mysql'),
          'last_login' => $user->user_registered,
        ],
        ['%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s']
      );
    }

    $wpdb->update(
      $entries_table,
      ['user_id' => $member_id],
      ['user_id' => $user->ID],
      ['%d'],
      ['%d']
    );
  }

  update_option('gachasoku_membership_migrated', 1);
}

add_action('init', 'gachasoku_register_campaign_post_type');
function gachasoku_register_campaign_post_type() {
  $labels = [
    'name' => 'キャンペーン',
    'singular_name' => 'キャンペーン',
    'add_new' => '新規追加',
    'add_new_item' => 'キャンペーンを追加',
    'edit_item' => 'キャンペーンを編集',
    'new_item' => '新規キャンペーン',
    'all_items' => 'キャンペーン一覧',
    'search_items' => 'キャンペーンを検索',
    'not_found' => 'キャンペーンが見つかりませんでした。',
    'not_found_in_trash' => 'ゴミ箱にキャンペーンはありません。',
    'menu_name' => 'キャンペーン',
  ];

  register_post_type('gachasoku_campaign', [
    'labels' => $labels,
    'public' => true,
    'has_archive' => false,
    'show_in_rest' => true,
    'menu_icon' => 'dashicons-megaphone',
    'supports' => ['title', 'editor', 'excerpt'],
    'rewrite' => ['slug' => 'campaign'],
  ]);
}

add_action('init', 'gachasoku_maybe_install_membership_tables');
function gachasoku_maybe_install_membership_tables() {
  gachasoku_install_membership_tables();
}

add_action('add_meta_boxes', 'gachasoku_add_campaign_metaboxes');
function gachasoku_add_campaign_metaboxes() {
  add_meta_box(
    'gachasoku-campaign-meta',
    'キャンペーン詳細',
    'gachasoku_render_campaign_meta_box',
    'gachasoku_campaign',
    'normal',
    'high'
  );
}

function gachasoku_render_campaign_meta_box($post) {
  wp_nonce_field('gachasoku_save_campaign', 'gachasoku_campaign_nonce');

  $fields = gachasoku_get_campaign_fields($post->ID);
  $start = $fields['start_datetime'];
  $end = $fields['end_datetime'];
  $link = $fields['link'];
  $image_id = $fields['image_id'];
  $requirements = $fields['requirements'];
  $max_winners = $fields['max_winners'];

  $image_src = $image_id ? wp_get_attachment_image_src($image_id, 'medium') : null;
  ?>
  <table class="form-table gachasoku-campaign-meta">
    <tr>
      <th scope="row"><label for="gachasoku_campaign_link">キャンペーンURL</label></th>
      <td>
        <input type="url" name="gachasoku_campaign_link" id="gachasoku_campaign_link" class="regular-text" value="<?php echo esc_attr($link); ?>" placeholder="https://" />
        <p class="description">保存時にルートドメイン置換ルールが自動適用されます。</p>
      </td>
    </tr>
    <tr>
      <th scope="row">キャンペーン画像</th>
      <td>
        <div class="gachasoku-campaign-image">
          <div class="gachasoku-campaign-image__preview">
            <?php if ($image_src) : ?>
              <img src="<?php echo esc_url($image_src[0]); ?>" alt="" />
            <?php else : ?>
              <span class="gachasoku-campaign-image__placeholder">未選択</span>
            <?php endif; ?>
          </div>
          <div class="gachasoku-campaign-image__actions">
            <input type="hidden" name="gachasoku_campaign_image_id" id="gachasoku_campaign_image_id" value="<?php echo esc_attr($image_id); ?>" />
            <button type="button" class="button gachasoku-campaign-image__select">画像を選択</button>
            <button type="button" class="button-link gachasoku-campaign-image__remove">画像をクリア</button>
          </div>
        </div>
      </td>
    </tr>
    <tr>
      <th scope="row"><label for="gachasoku_campaign_start">応募開始日時</label></th>
      <td>
        <input type="datetime-local" name="gachasoku_campaign_start" id="gachasoku_campaign_start" value="<?php echo esc_attr($start); ?>" />
        <p class="description">開始日時が未入力の場合、即時公開として扱われます。</p>
      </td>
    </tr>
    <tr>
      <th scope="row"><label for="gachasoku_campaign_end">応募終了日時</label></th>
      <td>
        <input type="datetime-local" name="gachasoku_campaign_end" id="gachasoku_campaign_end" value="<?php echo esc_attr($end); ?>" />
        <p class="description">終了日時以降はユーザーが応募できません。未入力の場合は終了なし。</p>
      </td>
    </tr>
    <tr>
      <th scope="row"><label for="gachasoku_campaign_max_winners">当選人数</label></th>
      <td>
        <input type="number" min="1" name="gachasoku_campaign_max_winners" id="gachasoku_campaign_max_winners" value="<?php echo esc_attr($max_winners); ?>" />
        <p class="description">抽選時の初期値として使用します。空欄の場合は抽選実行時に指定してください。</p>
      </td>
    </tr>
    <tr>
      <th scope="row"><label for="gachasoku_campaign_requirements">応募条件</label></th>
      <td>
        <textarea name="gachasoku_campaign_requirements" id="gachasoku_campaign_requirements" rows="5" class="large-text" placeholder="応募条件を入力してください。HTML可。"><?php echo esc_textarea($requirements); ?></textarea>
      </td>
    </tr>
  </table>
  <?php
}

function gachasoku_get_campaign_fields($campaign_id) {
  return [
    'link' => get_post_meta($campaign_id, '_gachasoku_campaign_link', true),
    'image_id' => intval(get_post_meta($campaign_id, '_gachasoku_campaign_image_id', true)),
    'start_datetime' => get_post_meta($campaign_id, '_gachasoku_campaign_start', true),
    'end_datetime' => get_post_meta($campaign_id, '_gachasoku_campaign_end', true),
    'requirements' => get_post_meta($campaign_id, '_gachasoku_campaign_requirements', true),
    'max_winners' => get_post_meta($campaign_id, '_gachasoku_campaign_max_winners', true),
  ];
}

add_action('save_post_gachasoku_campaign', 'gachasoku_save_campaign_meta');
function gachasoku_save_campaign_meta($post_id) {
  if (!isset($_POST['gachasoku_campaign_nonce']) || !wp_verify_nonce($_POST['gachasoku_campaign_nonce'], 'gachasoku_save_campaign')) {
    return;
  }

  if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
    return;
  }

  if (!current_user_can('edit_post', $post_id)) {
    return;
  }

  $link = isset($_POST['gachasoku_campaign_link']) ? esc_url_raw($_POST['gachasoku_campaign_link']) : '';
  $image_id = isset($_POST['gachasoku_campaign_image_id']) ? intval($_POST['gachasoku_campaign_image_id']) : 0;
  $start = isset($_POST['gachasoku_campaign_start']) ? sanitize_text_field($_POST['gachasoku_campaign_start']) : '';
  $end = isset($_POST['gachasoku_campaign_end']) ? sanitize_text_field($_POST['gachasoku_campaign_end']) : '';
  $requirements = isset($_POST['gachasoku_campaign_requirements']) ? wp_kses_post($_POST['gachasoku_campaign_requirements']) : '';
  $max_winners = isset($_POST['gachasoku_campaign_max_winners']) ? intval($_POST['gachasoku_campaign_max_winners']) : '';

  update_post_meta($post_id, '_gachasoku_campaign_link', $link);
  update_post_meta($post_id, '_gachasoku_campaign_image_id', $image_id);
  update_post_meta($post_id, '_gachasoku_campaign_start', $start);
  update_post_meta($post_id, '_gachasoku_campaign_end', $end);
  update_post_meta($post_id, '_gachasoku_campaign_requirements', $requirements);
  if ($max_winners) {
    update_post_meta($post_id, '_gachasoku_campaign_max_winners', $max_winners);
  } else {
    delete_post_meta($post_id, '_gachasoku_campaign_max_winners');
  }
}

add_action('admin_enqueue_scripts', 'gachasoku_enqueue_campaign_admin_assets');
function gachasoku_enqueue_campaign_admin_assets($hook) {
  if ($hook !== 'post.php' && $hook !== 'post-new.php') {
    return;
  }

  $screen = get_current_screen();
  if (!$screen || $screen->post_type !== 'gachasoku_campaign') {
    return;
  }

  wp_enqueue_media();
  wp_enqueue_script(
    'gachasoku-campaign-admin',
    get_template_directory_uri() . '/js/campaign-admin.js',
    ['jquery'],
    wp_get_theme()->get('Version'),
    true
  );
  wp_enqueue_style(
    'gachasoku-campaign-admin',
    get_template_directory_uri() . '/css/campaign-admin.css',
    [],
    wp_get_theme()->get('Version')
  );
}

function gachasoku_get_membership_messages($context) {
  if (!isset($GLOBALS['gachasoku_membership_messages'])) {
    $GLOBALS['gachasoku_membership_messages'] = [];
  }
  if (!isset($GLOBALS['gachasoku_membership_messages'][$context])) {
    $GLOBALS['gachasoku_membership_messages'][$context] = [];
  }
  return $GLOBALS['gachasoku_membership_messages'][$context];
}

function gachasoku_add_membership_message($context, $type, $message) {
  if (!isset($GLOBALS['gachasoku_membership_messages'])) {
    $GLOBALS['gachasoku_membership_messages'] = [];
  }
  if (!isset($GLOBALS['gachasoku_membership_messages'][$context])) {
    $GLOBALS['gachasoku_membership_messages'][$context] = [];
  }
  $GLOBALS['gachasoku_membership_messages'][$context][] = [
    'type' => $type,
    'message' => $message,
  ];
}

function gachasoku_render_membership_messages($context) {
  $messages = gachasoku_get_membership_messages($context);
  if (empty($messages)) {
    return '';
  }

  ob_start();
  echo '<div class="gachasoku-messages">';
  foreach ($messages as $message) {
    $class = $message['type'] === 'success' ? 'gachasoku-message--success' : 'gachasoku-message--error';
    printf('<div class="gachasoku-message %1$s">%2$s</div>', esc_attr($class), esc_html($message['message']));
  }
  echo '</div>';
  return ob_get_clean();
}

add_action('init', 'gachasoku_handle_membership_requests');
function gachasoku_handle_membership_requests() {
  if (isset($_GET['gachasoku_member_logout'])) {
    $nonce = isset($_GET['_wpnonce']) ? $_GET['_wpnonce'] : '';
    if (wp_verify_nonce($nonce, 'gachasoku_member_logout')) {
      gachasoku_member_logout();
      $redirect = remove_query_arg(['gachasoku_member_logout', '_wpnonce']);
      if (!$redirect || $redirect === '') {
        $redirect = home_url('/');
      }
      wp_safe_redirect($redirect);
      exit;
    }
  }

  if (!empty($_POST['gachasoku_register_submit'])) {
    gachasoku_handle_registration();
  }

  if (!empty($_POST['gachasoku_login_submit'])) {
    gachasoku_handle_login();
  }

  if (!empty($_POST['gachasoku_password_reset_submit'])) {
    gachasoku_handle_password_reset();
  }

  if (!empty($_POST['gachasoku_email_update_submit'])) {
    gachasoku_handle_email_update();
  }

  if (!empty($_POST['gachasoku_campaign_apply'])) {
    gachasoku_handle_campaign_application();
  }
}

function gachasoku_handle_registration() {
  $nonce = isset($_POST['gachasoku_register_nonce']) ? $_POST['gachasoku_register_nonce'] : '';
  if (!wp_verify_nonce($nonce, 'gachasoku_register')) {
    gachasoku_add_membership_message('register', 'error', 'フォームの有効期限が切れました。再度お試しください。');
    return;
  }

  $name = isset($_POST['gachasoku_register_name']) ? sanitize_text_field($_POST['gachasoku_register_name']) : '';
  $email = isset($_POST['gachasoku_register_email']) ? sanitize_email($_POST['gachasoku_register_email']) : '';
  $password = isset($_POST['gachasoku_register_password']) ? $_POST['gachasoku_register_password'] : '';
  $confirm = isset($_POST['gachasoku_register_password_confirm']) ? $_POST['gachasoku_register_password_confirm'] : '';

  if ($name === '' || $email === '' || $password === '' || $confirm === '') {
    gachasoku_add_membership_message('register', 'error', '全ての項目を入力してください。');
    return;
  }

  if (!is_email($email)) {
    gachasoku_add_membership_message('register', 'error', 'メールアドレスの形式が正しくありません。');
    return;
  }

  if ($password !== $confirm) {
    gachasoku_add_membership_message('register', 'error', 'パスワードが一致しません。');
    return;
  }

  if (gachasoku_get_member_by_email($email)) {
    gachasoku_add_membership_message('register', 'error', 'このメールアドレスは既に登録されています。');
    return;
  }

  $member_id = gachasoku_insert_member($name, $email, $password);
  if (is_wp_error($member_id)) {
    gachasoku_add_membership_message('register', 'error', $member_id->get_error_message());
    return;
  }

  $member = gachasoku_get_member_by_id($member_id);
  if ($member) {
    gachasoku_member_login($member);
  }

  gachasoku_add_membership_message('register', 'success', '会員登録が完了しました。マイページからキャンペーンに応募できます。');
}

function gachasoku_handle_login() {
  $nonce = isset($_POST['gachasoku_login_nonce']) ? $_POST['gachasoku_login_nonce'] : '';
  if (!wp_verify_nonce($nonce, 'gachasoku_login')) {
    gachasoku_add_membership_message('login', 'error', 'フォームの有効期限が切れました。再度お試しください。');
    return;
  }

  $email = isset($_POST['gachasoku_login_email']) ? sanitize_email($_POST['gachasoku_login_email']) : '';
  $password = isset($_POST['gachasoku_login_password']) ? $_POST['gachasoku_login_password'] : '';

  if ($email === '' || $password === '') {
    gachasoku_add_membership_message('login', 'error', 'メールアドレスとパスワードを入力してください。');
    return;
  }

  $member = gachasoku_get_member_by_email($email);
  if (!$member || empty($member['password_hash'])) {
    gachasoku_add_membership_message('login', 'error', 'メールアドレスまたはパスワードが正しくありません。');
    return;
  }

  if (!wp_check_password($password, $member['password_hash'])) {
    gachasoku_add_membership_message('login', 'error', 'メールアドレスまたはパスワードが正しくありません。');
    return;
  }

  $status = isset($member['status']) ? $member['status'] : GACHASOKU_MEMBER_STATUS_ACTIVE;
  if ($status !== GACHASOKU_MEMBER_STATUS_ACTIVE) {
    $labels = gachasoku_get_member_status_options();
    $label = isset($labels[$status]) ? $labels[$status] : '利用不可';
    gachasoku_add_membership_message('login', 'error', sprintf('アカウントは現在「%s」です。管理者にお問い合わせください。', $label));
    return;
  }

  $remember = !empty($_POST['gachasoku_login_remember']);
  gachasoku_member_login($member, $remember);

  gachasoku_add_membership_message('login', 'success', 'ログインに成功しました。');
}

function gachasoku_handle_password_reset() {
  $nonce = isset($_POST['gachasoku_password_nonce']) ? $_POST['gachasoku_password_nonce'] : '';
  if (!wp_verify_nonce($nonce, 'gachasoku_password_reset')) {
    gachasoku_add_membership_message('password', 'error', 'フォームの有効期限が切れました。再度お試しください。');
    return;
  }

  $email = isset($_POST['gachasoku_password_email']) ? sanitize_email($_POST['gachasoku_password_email']) : '';
  if ($email === '') {
    gachasoku_add_membership_message('password', 'error', 'メールアドレスを入力してください。');
    return;
  }

  $member = gachasoku_get_member_by_email($email);
  if (!$member) {
    gachasoku_add_membership_message('password', 'error', '該当する会員が見つかりません。');
    return;
  }

  $temporary_password = wp_generate_password(12, false);
  $updated = gachasoku_update_member_password($member['id'], $temporary_password);

  if (is_wp_error($updated)) {
    gachasoku_add_membership_message('password', 'error', $updated->get_error_message());
    return;
  }

  $login_url = gachasoku_get_membership_page_url('member-login');
  $blogname = wp_specialchars_decode(get_option('blogname'), ENT_QUOTES);
  $subject = sprintf('【%s】パスワード再設定のお知らせ', $blogname);
  $message = [];
  $message[] = $member['name'] ? sprintf('%s 様', $member['name']) : '会員様';
  $message[] = '';
  $message[] = 'パスワード再設定のご依頼を受け付けました。';
  $message[] = '下記の仮パスワードでログインのうえ、マイページからお好きなパスワードに変更してください。';
  $message[] = '';
  $message[] = '仮パスワード: ' . $temporary_password;
  $message[] = 'ログインページ: ' . $login_url;
  $message[] = '';
  $message[] = '※このメールに心当たりがない場合は破棄してください。';
  $mail_sent = wp_mail($member['email'], $subject, implode("\n", $message));

  if ($mail_sent) {
    gachasoku_add_membership_message('password', 'success', 'パスワード再設定メールを送信しました。メールをご確認ください。');
  } else {
    gachasoku_add_membership_message('password', 'error', 'メール送信に失敗しました。時間を置いて再度お試しください。');
  }
}

function gachasoku_handle_email_update() {
  if (!gachasoku_is_member_logged_in()) {
    gachasoku_add_membership_message('dashboard', 'error', 'ログインが必要です。');
    return;
  }

  $nonce = isset($_POST['gachasoku_email_update_nonce']) ? $_POST['gachasoku_email_update_nonce'] : '';
  if (!wp_verify_nonce($nonce, 'gachasoku_email_update')) {
    gachasoku_add_membership_message('dashboard', 'error', 'フォームの有効期限が切れました。再度お試しください。');
    return;
  }

  $email = isset($_POST['gachasoku_new_email']) ? sanitize_email($_POST['gachasoku_new_email']) : '';
  if ($email === '') {
    gachasoku_add_membership_message('dashboard', 'error', 'メールアドレスを入力してください。');
    return;
  }

  if (!is_email($email)) {
    gachasoku_add_membership_message('dashboard', 'error', 'メールアドレスの形式が正しくありません。');
    return;
  }

  $member = gachasoku_get_current_member();
  if (!$member) {
    gachasoku_add_membership_message('dashboard', 'error', '会員情報を取得できませんでした。');
    return;
  }

  if ($member['email'] === $email) {
    gachasoku_add_membership_message('dashboard', 'success', 'メールアドレスは既に更新済みです。');
    return;
  }

  $result = gachasoku_update_member_email($member['id'], $email);

  if (is_wp_error($result)) {
    gachasoku_add_membership_message('dashboard', 'error', $result->get_error_message());
    return;
  }

  gachasoku_get_current_member(true);
  gachasoku_add_membership_message('dashboard', 'success', 'メールアドレスを更新しました。');
}

function gachasoku_handle_campaign_application() {
  $nonce = isset($_POST['gachasoku_campaign_nonce']) ? $_POST['gachasoku_campaign_nonce'] : '';
  if (!wp_verify_nonce($nonce, 'gachasoku_campaign_apply')) {
    gachasoku_add_membership_message('campaign', 'error', 'フォームの有効期限が切れました。再度お試しください。');
    return;
  }

  if (!gachasoku_is_member_logged_in()) {
    gachasoku_add_membership_message('campaign', 'error', '応募にはログインが必要です。');
    return;
  }

  $user_id = gachasoku_get_current_member_id();
  $status = gachasoku_get_member_status($user_id);
  if ($status !== GACHASOKU_MEMBER_STATUS_ACTIVE) {
    gachasoku_add_membership_message('campaign', 'error', '現在のステータスでは応募できません。');
    return;
  }

  $campaign_id = isset($_POST['gachasoku_campaign_id']) ? intval($_POST['gachasoku_campaign_id']) : 0;
  if (!$campaign_id || get_post_type($campaign_id) !== 'gachasoku_campaign') {
    gachasoku_add_membership_message('campaign', 'error', 'キャンペーンが見つかりませんでした。');
    return;
  }

  if (!gachasoku_is_campaign_open($campaign_id)) {
    gachasoku_add_membership_message('campaign', 'error', 'このキャンペーンの募集は終了しています。');
    return;
  }

  $result = gachasoku_register_campaign_entry($campaign_id, $user_id);
  if (is_wp_error($result)) {
    gachasoku_add_membership_message('campaign', 'error', $result->get_error_message());
    return;
  }

  gachasoku_add_membership_message('campaign', 'success', 'キャンペーンに応募しました。結果発表をお待ちください。');
}

function gachasoku_register_campaign_entry($campaign_id, $user_id) {
  global $wpdb;

  $table = $wpdb->prefix . 'gachasoku_campaign_entries';
  $existing = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE campaign_id = %d AND user_id = %d", $campaign_id, $user_id));
  if ($existing) {
    return new WP_Error('already_applied', '既に応募済みです。');
  }

  $now = current_time('mysql');
  $inserted = $wpdb->insert(
    $table,
    [
      'campaign_id' => $campaign_id,
      'user_id' => $user_id,
      'status' => 'applied',
      'applied_at' => $now,
      'updated_at' => $now,
    ],
    ['%d', '%d', '%s', '%s', '%s']
  );

  if ($inserted === false) {
    return new WP_Error('db_error', '応募処理に失敗しました。時間を置いて再度お試しください。');
  }

  return true;
}

function gachasoku_is_campaign_open($campaign_id) {
  $fields = gachasoku_get_campaign_fields($campaign_id);
  $now = current_time('timestamp');

  $start_ok = true;
  if (!empty($fields['start_datetime'])) {
    $start_ts = strtotime($fields['start_datetime']);
    if ($start_ts && $start_ts > $now) {
      $start_ok = false;
    }
  }

  $end_ok = true;
  if (!empty($fields['end_datetime'])) {
    $end_ts = strtotime($fields['end_datetime']);
    if ($end_ts && $end_ts < $now) {
      $end_ok = false;
    }
  }

  return $start_ok && $end_ok;
}

function gachasoku_is_campaign_finished($campaign_id) {
  $fields = gachasoku_get_campaign_fields($campaign_id);
  if (empty($fields['end_datetime'])) {
    return false;
  }

  $end_ts = strtotime($fields['end_datetime']);
  if (!$end_ts) {
    return false;
  }

  return $end_ts < current_time('timestamp');
}

function gachasoku_get_campaign_card_data($campaign_id) {
  $post = get_post($campaign_id);
  if (!$post || $post->post_type !== 'gachasoku_campaign') {
    return null;
  }

  $fields = gachasoku_get_campaign_fields($campaign_id);
  $image = $fields['image_id'] ? wp_get_attachment_image_src($fields['image_id'], 'large') : null;
  $link = $fields['link'] ? gachasoku_apply_affiliate_url($fields['link']) : '';

  return [
    'id' => $campaign_id,
    'title' => get_the_title($campaign_id),
    'content' => apply_filters('the_content', $post->post_content),
    'excerpt' => get_the_excerpt($campaign_id),
    'link' => $link,
    'image' => $image ? $image[0] : '',
    'start' => $fields['start_datetime'],
    'end' => $fields['end_datetime'],
    'requirements' => $fields['requirements'],
    'max_winners' => $fields['max_winners'],
    'is_open' => gachasoku_is_campaign_open($campaign_id),
    'is_finished' => gachasoku_is_campaign_finished($campaign_id),
  ];
}

function gachasoku_build_campaign_item($campaign_id, $member_id = 0) {
  $card = gachasoku_get_campaign_card_data($campaign_id);
  if (!$card) {
    return null;
  }

  $has_applied = false;
  if ($member_id) {
    $has_applied = gachasoku_user_has_applied($campaign_id, $member_id);
  }

  return [
    'campaign_id' => $campaign_id,
    'card' => $card,
    'has_applied' => $has_applied,
    'nonce' => $member_id ? wp_create_nonce('gachasoku_apply_campaign_' . $campaign_id) : '',
  ];
}

function gachasoku_render_campaign_cards($items, $args = []) {
  $defaults = [
    'container' => true,
    'container_class' => 'gachasoku-campaigns',
    'empty_message' => '',
    'empty_class' => 'gachasoku-campaigns__empty',
    'apply_label' => '応募して公式サイトを開く',
    'applied_label' => '応募済み',
    'visit_label' => '公式サイトへ',
  ];
  $args = wp_parse_args($args, $defaults);

  if (empty($items)) {
    if ($args['empty_message'] === '') {
      return '';
    }
    return '<p class="' . esc_attr($args['empty_class']) . '">' . esc_html($args['empty_message']) . '</p>';
  }

  ob_start();
  if ($args['container']) {
    echo '<div class="' . esc_attr($args['container_class']) . '">';
  }

  foreach ($items as $item) {
    if (!isset($item['card'], $item['campaign_id'])) {
      continue;
    }

    $card = $item['card'];
    $campaign_id = intval($item['campaign_id']);
    $has_applied = !empty($item['has_applied']);
    $nonce = isset($item['nonce']) ? $item['nonce'] : '';
    ?>
    <article class="gachasoku-campaign-card">
      <?php if (!empty($card['image'])) : ?>
        <div class="gachasoku-campaign-card__image">
          <?php if (!empty($card['link'])) : ?>
            <a href="<?php echo esc_url($card['link']); ?>" target="_blank" rel="noopener noreferrer">
              <img src="<?php echo esc_url($card['image']); ?>" alt="" />
            </a>
          <?php else : ?>
            <img src="<?php echo esc_url($card['image']); ?>" alt="" />
          <?php endif; ?>
        </div>
      <?php endif; ?>
      <div class="gachasoku-campaign-card__body">
        <h3 class="gachasoku-campaign-card__title"><?php echo esc_html($card['title']); ?></h3>
        <div class="gachasoku-campaign-card__dates">
          <?php if (!empty($card['start'])) : ?>
            <span>開始：<?php echo esc_html(gachasoku_format_datetime($card['start'])); ?></span>
          <?php endif; ?>
          <?php if (!empty($card['end'])) : ?>
            <span>終了：<?php echo esc_html(gachasoku_format_datetime($card['end'])); ?></span>
          <?php endif; ?>
          <?php if (!empty($card['max_winners'])) : ?>
            <span>当選人数：<?php echo esc_html(number_format_i18n($card['max_winners'])); ?>名</span>
          <?php endif; ?>
        </div>
        <?php if (!empty($card['requirements'])) : ?>
          <div class="gachasoku-campaign-card__requirements">
            <h4>応募条件</h4>
            <?php echo wpautop(wp_kses_post($card['requirements'])); ?>
          </div>
        <?php endif; ?>
        <?php if (!empty($card['content'])) : ?>
          <div class="gachasoku-campaign-card__content">
            <?php echo $card['content']; ?>
          </div>
        <?php endif; ?>
        <div class="gachasoku-campaign-card__actions">
          <?php
          echo gachasoku_render_campaign_action(
            $campaign_id,
            $card,
            $has_applied,
            [
              'nonce' => $nonce,
              'apply_label' => $args['apply_label'],
              'applied_label' => $args['applied_label'],
              'visit_label' => $args['visit_label'],
            ]
          );
          ?>
        </div>
      </div>
    </article>
    <?php
  }

  if ($args['container']) {
    echo '</div>';
  }

  return ob_get_clean();
}

function gachasoku_get_open_campaign_items($member_id) {
  $query = new WP_Query([
    'post_type' => 'gachasoku_campaign',
    'post_status' => 'publish',
    'posts_per_page' => -1,
    'orderby' => 'date',
    'order' => 'DESC',
  ]);

  $items = [];

  if ($query->have_posts()) {
    while ($query->have_posts()) {
      $query->the_post();
      $campaign_id = get_the_ID();
      $card = gachasoku_get_campaign_card_data($campaign_id);
      if (!$card || !$card['is_open']) {
        continue;
      }

      $item = gachasoku_build_campaign_item($campaign_id, $member_id);
      if ($item) {
        $items[] = $item;
      }
    }
  }

  wp_reset_postdata();

  return $items;
}

function gachasoku_get_campaign_entries_for_user($user_id) {
  global $wpdb;

  $table = $wpdb->prefix . 'gachasoku_campaign_entries';
  $results = $wpdb->get_results(
    $wpdb->prepare(
      "SELECT e.*, p.post_title, p.post_status FROM {$table} e LEFT JOIN {$wpdb->posts} p ON p.ID = e.campaign_id WHERE e.user_id = %d ORDER BY e.applied_at DESC",
      $user_id
    ),
    ARRAY_A
  );

  return $results ? $results : [];
}

function gachasoku_get_campaign_entries_grouped($user_id) {
  $entries = gachasoku_get_campaign_entries_for_user($user_id);
  $grouped = [
    'active' => [],
    'finished' => [],
    'result' => [],
  ];

  foreach ($entries as $entry) {
    $campaign_id = intval($entry['campaign_id']);
    $card = gachasoku_get_campaign_card_data($campaign_id);
    if (!$card) {
      continue;
    }

    $entry_data = [
      'campaign' => $card,
      'status' => $entry['status'],
      'applied_at' => $entry['applied_at'],
      'updated_at' => $entry['updated_at'],
      'result_at' => $entry['result_at'],
    ];

    if ($entry['status'] === 'won' || $entry['status'] === 'lost') {
      $grouped['result'][] = $entry_data;
    }

    if ($card['is_finished']) {
      $grouped['finished'][] = $entry_data;
    } else {
      $grouped['active'][] = $entry_data;
    }
  }

  return $grouped;
}

function gachasoku_get_campaign_entry_count($campaign_id, $status = null) {
  global $wpdb;
  $table = $wpdb->prefix . 'gachasoku_campaign_entries';

  if ($status) {
    return (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE campaign_id = %d AND status = %s", $campaign_id, $status));
  }

  return (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE campaign_id = %d", $campaign_id));
}

function gachasoku_get_campaign_winner_logs($campaign_id, $limit = 5) {
  global $wpdb;
  $table = $wpdb->prefix . 'gachasoku_campaign_draw_logs';
  $query = $wpdb->prepare("SELECT * FROM {$table} WHERE campaign_id = %d ORDER BY executed_at DESC LIMIT %d", $campaign_id, $limit);
  $results = $wpdb->get_results($query, ARRAY_A);
  return $results ? $results : [];
}

function gachasoku_mark_campaign_results($campaign_id, $winner_ids) {
  global $wpdb;
  $table = $wpdb->prefix . 'gachasoku_campaign_entries';
  $now = current_time('mysql');

  $wpdb->query($wpdb->prepare("UPDATE {$table} SET status = %s, result_at = %s, updated_at = %s WHERE campaign_id = %d", 'lost', $now, $now, $campaign_id));

  if (!empty($winner_ids)) {
    $placeholders = implode(',', array_fill(0, count($winner_ids), '%d'));
    $query = $wpdb->prepare("UPDATE {$table} SET status = %s, result_at = %s, updated_at = %s WHERE campaign_id = %d AND user_id IN ({$placeholders})", array_merge(['won', $now, $now, $campaign_id], $winner_ids));
    $wpdb->query($query);
  }
}

function gachasoku_log_campaign_draw($campaign_id, $winner_ids, $notes = '') {
  global $wpdb;
  $table = $wpdb->prefix . 'gachasoku_campaign_draw_logs';
  $wpdb->insert(
    $table,
    [
      'campaign_id' => $campaign_id,
      'executed_at' => current_time('mysql'),
      'executed_by' => get_current_user_id(),
      'winners' => wp_json_encode($winner_ids),
      'notes' => $notes,
    ],
    ['%d', '%s', '%d', '%s', '%s']
  );
}

function gachasoku_get_campaign_winner_usernames($winner_ids) {
  $names = [];
  foreach ($winner_ids as $winner_id) {
    $member = gachasoku_get_member_by_id($winner_id);
    if ($member) {
      $names[] = $member['name'] ? $member['name'] : $member['email'];
    }
  }
  return $names;
}

function gachasoku_select_campaign_winners($campaign_id, $max_winners) {
  global $wpdb;
  $table = $wpdb->prefix . 'gachasoku_campaign_entries';
  $ids = $wpdb->get_col($wpdb->prepare("SELECT user_id FROM {$table} WHERE campaign_id = %d AND status = %s", $campaign_id, 'applied'));
  if (empty($ids)) {
    return [];
  }

  if ($max_winners <= 0 || $max_winners >= count($ids)) {
    return $ids;
  }

  $random_keys = array_rand($ids, $max_winners);
  if (!is_array($random_keys)) {
    $random_keys = [$random_keys];
  }

  $winners = [];
  foreach ($random_keys as $key) {
    $winners[] = $ids[$key];
  }

  return $winners;
}

add_shortcode('gachasoku_register_form', 'gachasoku_register_form_shortcode');
function gachasoku_register_form_shortcode() {
  if (gachasoku_is_member_logged_in()) {
    return '<p class="gachasoku-membership__notice">既にログイン済みです。</p>';
  }

  ob_start();
  echo gachasoku_render_membership_messages('register');
  ?>
  <form method="post" class="gachasoku-form">
    <?php wp_nonce_field('gachasoku_register', 'gachasoku_register_nonce'); ?>
    <div class="gachasoku-form__field">
      <label for="gachasoku_register_name">お名前</label>
      <input type="text" name="gachasoku_register_name" id="gachasoku_register_name" required />
    </div>
    <div class="gachasoku-form__field">
      <label for="gachasoku_register_email">メールアドレス</label>
      <input type="email" name="gachasoku_register_email" id="gachasoku_register_email" required />
    </div>
    <div class="gachasoku-form__field">
      <label for="gachasoku_register_password">パスワード</label>
      <input type="password" name="gachasoku_register_password" id="gachasoku_register_password" required />
    </div>
    <div class="gachasoku-form__field">
      <label for="gachasoku_register_password_confirm">パスワード（確認）</label>
      <input type="password" name="gachasoku_register_password_confirm" id="gachasoku_register_password_confirm" required />
    </div>
    <div class="gachasoku-form__actions">
      <button type="submit" class="gachasoku-button">会員登録</button>
    </div>
    <input type="hidden" name="gachasoku_register_submit" value="1" />
  </form>
  <?php echo gachasoku_render_membership_links('register'); ?>
  <?php
  return ob_get_clean();
}

add_shortcode('gachasoku_login_form', 'gachasoku_login_form_shortcode');
function gachasoku_login_form_shortcode() {
  if (gachasoku_is_member_logged_in()) {
    return '<p class="gachasoku-membership__notice">既にログイン済みです。</p>';
  }

  ob_start();
  echo gachasoku_render_membership_messages('login');
  ?>
  <form method="post" class="gachasoku-form">
    <?php wp_nonce_field('gachasoku_login', 'gachasoku_login_nonce'); ?>
    <div class="gachasoku-form__field">
      <label for="gachasoku_login_email">メールアドレス</label>
      <input type="email" name="gachasoku_login_email" id="gachasoku_login_email" required />
    </div>
    <div class="gachasoku-form__field">
      <label for="gachasoku_login_password">パスワード</label>
      <input type="password" name="gachasoku_login_password" id="gachasoku_login_password" required />
    </div>
    <div class="gachasoku-form__field gachasoku-form__field--checkbox">
      <label>
        <input type="checkbox" name="gachasoku_login_remember" value="1" />
        ログイン状態を保持する
      </label>
    </div>
    <div class="gachasoku-form__actions">
      <button type="submit" class="gachasoku-button">ログイン</button>
    </div>
    <input type="hidden" name="gachasoku_login_submit" value="1" />
  </form>
  <?php echo gachasoku_render_membership_links('login'); ?>
  <?php
  return ob_get_clean();
}

add_shortcode('gachasoku_password_reset_form', 'gachasoku_password_reset_form_shortcode');
function gachasoku_password_reset_form_shortcode() {
  ob_start();
  echo gachasoku_render_membership_messages('password');
  ?>
  <form method="post" class="gachasoku-form">
    <?php wp_nonce_field('gachasoku_password_reset', 'gachasoku_password_nonce'); ?>
    <div class="gachasoku-form__field">
      <label for="gachasoku_password_email">登録メールアドレス</label>
      <input type="email" name="gachasoku_password_email" id="gachasoku_password_email" required />
    </div>
    <div class="gachasoku-form__actions">
      <button type="submit" class="gachasoku-button">再設定メールを送信</button>
    </div>
    <input type="hidden" name="gachasoku_password_reset_submit" value="1" />
  </form>
  <?php echo gachasoku_render_membership_links('password'); ?>
  <?php
  return ob_get_clean();
}

add_shortcode('gachasoku_member_dashboard', 'gachasoku_member_dashboard_shortcode');
function gachasoku_member_dashboard_shortcode() {
  if (!gachasoku_is_member_logged_in()) {
    return '<p class="gachasoku-membership__notice">マイページを閲覧するにはログインしてください。</p>';
  }

  $member = gachasoku_get_current_member();
  if (!$member) {
    return '<p class="gachasoku-membership__notice">会員情報を取得できませんでした。</p>';
  }

  $status = gachasoku_get_member_status($member['id']);
  $status_options = gachasoku_get_member_status_options();
  $status_label = isset($status_options[$status]) ? $status_options[$status] : $status;
  $grouped = gachasoku_get_campaign_entries_grouped($member['id']);
  $open_campaigns = gachasoku_get_open_campaign_items($member['id']);

  ob_start();
  echo gachasoku_render_membership_messages('dashboard');
  ?>
  <div class="gachasoku-dashboard">
    <section class="gachasoku-dashboard__section">
      <h2 class="gachasoku-dashboard__title">会員情報</h2>
      <dl class="gachasoku-dashboard__profile">
        <div>
          <dt>お名前</dt>
          <dd><?php echo esc_html($member['name']); ?></dd>
        </div>
        <div>
          <dt>メールアドレス</dt>
          <dd><?php echo esc_html($member['email']); ?></dd>
        </div>
        <div>
          <dt>ステータス</dt>
          <dd><?php echo esc_html($status_label); ?></dd>
        </div>
      </dl>
      <form method="post" class="gachasoku-form gachasoku-dashboard__form">
        <h3>メールアドレスの変更</h3>
        <?php wp_nonce_field('gachasoku_email_update', 'gachasoku_email_update_nonce'); ?>
        <div class="gachasoku-form__field">
          <label for="gachasoku_new_email">新しいメールアドレス</label>
          <input type="email" name="gachasoku_new_email" id="gachasoku_new_email" required />
        </div>
        <div class="gachasoku-form__actions">
          <button type="submit" class="gachasoku-button">更新する</button>
        </div>
        <input type="hidden" name="gachasoku_email_update_submit" value="1" />
      </form>
    </section>

    <section class="gachasoku-dashboard__section">
      <h2 class="gachasoku-dashboard__title">ランキング投票状況</h2>
      <?php echo gachasoku_render_member_ranking_summary($member['id']); ?>
    </section>

    <section class="gachasoku-dashboard__section">
      <h2 class="gachasoku-dashboard__title">開催中のキャンペーン</h2>
      <?php echo gachasoku_render_campaign_cards($open_campaigns, [
        'empty_message' => '現在開催中のキャンペーンはありません。',
        'empty_class' => 'gachasoku-dashboard__empty',
        'container_class' => 'gachasoku-campaigns gachasoku-campaigns--dashboard',
      ]); ?>
    </section>

    <section class="gachasoku-dashboard__section">
      <h2 class="gachasoku-dashboard__title">現在応募中のキャンペーン</h2>
      <?php echo gachasoku_render_dashboard_campaign_list($grouped['active'], '現在応募中のキャンペーンはありません。'); ?>
    </section>

    <section class="gachasoku-dashboard__section">
      <h2 class="gachasoku-dashboard__title">過去に応募したキャンペーン</h2>
      <?php echo gachasoku_render_dashboard_campaign_list($grouped['finished'], '過去に応募したキャンペーンはありません。'); ?>
    </section>

    <section class="gachasoku-dashboard__section">
      <h2 class="gachasoku-dashboard__title">当選結果</h2>
      <?php echo gachasoku_render_dashboard_campaign_list($grouped['result'], '当選結果はまだありません。', true); ?>
    </section>
  </div>
  <?php echo gachasoku_render_membership_links('dashboard'); ?>
  <?php
  return ob_get_clean();
}

function gachasoku_render_dashboard_campaign_list($entries, $empty_message, $show_result = false) {
  if (empty($entries)) {
    return '<p class="gachasoku-dashboard__empty">' . esc_html($empty_message) . '</p>';
  }

  ob_start();
  ?>
  <ul class="gachasoku-dashboard__list">
    <?php foreach ($entries as $entry) :
      $campaign = $entry['campaign'];
      $status = $entry['status'];
      ?>
      <li class="gachasoku-dashboard__item">
        <div class="gachasoku-dashboard__item-head">
          <h3><?php echo esc_html($campaign['title']); ?></h3>
          <?php if ($campaign['link']) : ?>
            <a class="gachasoku-dashboard__link" href="<?php echo esc_url($campaign['link']); ?>" target="_blank" rel="noopener noreferrer">公式サイトを見る</a>
          <?php endif; ?>
        </div>
        <div class="gachasoku-dashboard__meta">
          <?php if ($campaign['start']) : ?>
            <span>開始：<?php echo esc_html(gachasoku_format_datetime($campaign['start'])); ?></span>
          <?php endif; ?>
          <?php if ($campaign['end']) : ?>
            <span>終了：<?php echo esc_html(gachasoku_format_datetime($campaign['end'])); ?></span>
          <?php endif; ?>
          <span>応募日：<?php echo esc_html(gachasoku_format_datetime($entry['applied_at'])); ?></span>
        </div>
        <?php if ($show_result) : ?>
          <div class="gachasoku-dashboard__result <?php echo esc_attr('status-' . $status); ?>">
            <?php echo esc_html(gachasoku_translate_entry_status($status)); ?>
            <?php if ($entry['result_at']) : ?>
              <span class="gachasoku-dashboard__result-date">(<?php echo esc_html(gachasoku_format_datetime($entry['result_at'])); ?>)</span>
            <?php endif; ?>
          </div>
        <?php endif; ?>
      </li>
    <?php endforeach; ?>
  </ul>
  <?php
  return ob_get_clean();
}

function gachasoku_render_member_ranking_summary($member_id) {
  if (!function_exists('gachasoku_get_sorted_ranking_entries')) {
    return '<p class="gachasoku-dashboard__empty">ランキング情報を取得できませんでした。</p>';
  }

  $entries = gachasoku_get_sorted_ranking_entries($member_id);
  if (empty($entries)) {
    return '<p class="gachasoku-dashboard__empty">現在表示できるランキングはありません。</p>';
  }

  ob_start();
  ?>
  <div class="gachasoku-dashboard__ranking">
    <table class="gachasoku-dashboard__ranking-table">
      <thead>
        <tr>
          <th scope="col">順位</th>
          <th scope="col">名前</th>
          <th scope="col">全体勝率</th>
          <th scope="col">あなたの戦績</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($entries as $entry) :
          $rank_label = isset($entry['current_rank_label']) ? $entry['current_rank_label'] : '';
          $label = function_exists('gachasoku_get_ranking_entry_display_name')
            ? gachasoku_get_ranking_entry_display_name($entry)
            : '';
          if ($label === '') {
            $label = '—';
          }
          $stats = isset($entry['vote_stats']) ? $entry['vote_stats'] : ['wins' => 0, 'losses' => 0, 'logpos' => 0, 'formatted' => '0.0%'];
          $member_stats = isset($entry['member_vote_stats']) ? $entry['member_vote_stats'] : ['wins' => 0, 'losses' => 0, 'logpos' => 0, 'formatted' => '0.0%'];
          $member_total_votes = intval($member_stats['wins']) + intval($member_stats['losses']) + intval($member_stats['logpos']);
          ?>
          <tr>
            <td data-label="順位"><?php echo esc_html($rank_label); ?></td>
            <td data-label="名前"><?php echo esc_html($label); ?></td>
            <td data-label="全体勝率">
              <span class="gachasoku-dashboard__ranking-rate"><?php echo esc_html($stats['formatted']); ?></span>
              <small class="gachasoku-dashboard__ranking-counts">(勝ち <?php echo esc_html(number_format_i18n($stats['wins'])); ?> / 負け <?php echo esc_html(number_format_i18n($stats['losses'])); ?> / ログポ <?php echo esc_html(number_format_i18n($stats['logpos'])); ?>)</small>
            </td>
            <td data-label="あなたの戦績">
              <?php if ($member_total_votes > 0) : ?>
                <span class="gachasoku-dashboard__ranking-rate"><?php echo esc_html($member_stats['formatted']); ?></span>
                <small class="gachasoku-dashboard__ranking-counts">(勝ち <?php echo esc_html(number_format_i18n($member_stats['wins'])); ?> / 負け <?php echo esc_html(number_format_i18n($member_stats['losses'])); ?> / ログポ <?php echo esc_html(number_format_i18n($member_stats['logpos'])); ?>)</small>
              <?php else : ?>
                <span class="gachasoku-dashboard__ranking-empty">未投票</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php
  return ob_get_clean();
}

function gachasoku_format_datetime($datetime) {
  if (!$datetime) {
    return '';
  }
  $timestamp = strtotime($datetime);
  if (!$timestamp) {
    return $datetime;
  }
  return date_i18n('Y年n月j日 H:i', $timestamp);
}

function gachasoku_translate_entry_status($status) {
  switch ($status) {
    case 'applied':
      return '抽選待ち';
    case 'won':
      return '当選';
    case 'lost':
      return '落選';
    default:
      return $status;
  }
}

add_shortcode('gachasoku_campaigns', 'gachasoku_campaigns_shortcode');
function gachasoku_campaigns_shortcode($atts = []) {
  $atts = shortcode_atts([
    'status' => 'all',
    'limit' => 0,
  ], $atts, 'gachasoku_campaigns');

  $query_args = [
    'post_type' => 'gachasoku_campaign',
    'posts_per_page' => $atts['limit'] ? intval($atts['limit']) : -1,
    'post_status' => 'publish',
    'orderby' => 'date',
    'order' => 'DESC',
  ];

  $campaigns = new WP_Query($query_args);
  $member_id = gachasoku_get_current_member_id();
  $items = [];

  if ($campaigns->have_posts()) {
    while ($campaigns->have_posts()) {
      $campaigns->the_post();
      $campaign_id = get_the_ID();
      $card = gachasoku_get_campaign_card_data($campaign_id);
      if (!$card) {
        continue;
      }

      if ($atts['status'] === 'open' && !$card['is_open']) {
        continue;
      }

      if ($atts['status'] === 'closed' && $card['is_open']) {
        continue;
      }

      $item = gachasoku_build_campaign_item($campaign_id, $member_id);
      if ($item) {
        $items[] = $item;
      }
    }
  }

  wp_reset_postdata();

  $output = gachasoku_render_campaign_cards($items, [
    'empty_message' => 'キャンペーンは現在ありません。',
    'apply_label' => '応募して公式サイトを開く',
  ]);

  ob_start();
  echo gachasoku_render_membership_messages('campaign');
  echo $output;

  return ob_get_clean();
}

function gachasoku_user_has_applied($campaign_id, $user_id) {
  global $wpdb;
  $table = $wpdb->prefix . 'gachasoku_campaign_entries';
  $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE campaign_id = %d AND user_id = %d", $campaign_id, $user_id));
  return !empty($exists);
}

function gachasoku_render_campaign_action($campaign_id, $card, $has_applied, $args = []) {
  $defaults = [
    'nonce' => '',
    'apply_label' => 'このキャンペーンに応募する',
    'applied_label' => '応募済み',
    'visit_label' => '公式サイトへ',
  ];
  $args = wp_parse_args($args, $defaults);

  if (!gachasoku_is_member_logged_in()) {
    return '<p class="gachasoku-campaign-card__notice">応募するにはログインしてください。</p>';
  }

  if (!$card['is_open']) {
    return '<p class="gachasoku-campaign-card__notice">応募期間外です。</p>';
  }

  if ($has_applied) {
    $status_html = '<span class="gachasoku-campaign-card__status">' . esc_html($args['applied_label']) . '</span>';
    if (!empty($card['link'])) {
      $status_html .= ' <a class="gachasoku-button gachasoku-button--outline" href="' . esc_url($card['link']) . '" target="_blank" rel="noopener noreferrer">' . esc_html($args['visit_label']) . '</a>';
    }
    return $status_html;
  }

  $status = gachasoku_get_member_status(gachasoku_get_current_member_id());
  if ($status !== GACHASOKU_MEMBER_STATUS_ACTIVE) {
    return '<p class="gachasoku-campaign-card__notice">現在のステータスでは応募できません。</p>';
  }

  if (empty($card['link'])) {
    return '<p class="gachasoku-campaign-card__notice">応募リンクが設定されていません。</p>';
  }

  $attributes = [
    'type="button"',
    'class="gachasoku-button gachasoku-button--apply"',
    'data-campaign-apply="1"',
    'data-campaign-id="' . esc_attr($campaign_id) . '"',
    'data-campaign-url="' . esc_url($card['link']) . '"',
    'data-applied-label="' . esc_attr($args['applied_label']) . '"',
    'data-visit-label="' . esc_attr($args['visit_label']) . '"',
  ];

  if (!empty($args['nonce'])) {
    $attributes[] = 'data-campaign-nonce="' . esc_attr($args['nonce']) . '"';
  }

  return '<button ' . implode(' ', $attributes) . '>' . esc_html($args['apply_label']) . '</button>';
}

add_action('wp_ajax_gachasoku_apply_campaign', 'gachasoku_ajax_apply_campaign');
add_action('wp_ajax_nopriv_gachasoku_apply_campaign', 'gachasoku_ajax_apply_campaign');
function gachasoku_ajax_apply_campaign() {
  $campaign_id = isset($_POST['campaign_id']) ? intval($_POST['campaign_id']) : 0;
  $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';

  if (!$campaign_id || $nonce === '') {
    wp_send_json_error(['message' => '不正なリクエストです。'], 400);
  }

  if (!wp_verify_nonce($nonce, 'gachasoku_apply_campaign_' . $campaign_id)) {
    wp_send_json_error(['message' => 'リクエストの有効期限が切れました。再度お試しください。'], 400);
  }

  if (!gachasoku_is_member_logged_in()) {
    wp_send_json_error(['message' => '応募にはログインが必要です。'], 403);
  }

  $user_id = gachasoku_get_current_member_id();
  if (!$user_id) {
    wp_send_json_error(['message' => '会員情報を取得できませんでした。'], 403);
  }

  $status = gachasoku_get_member_status($user_id);
  if ($status !== GACHASOKU_MEMBER_STATUS_ACTIVE) {
    wp_send_json_error(['message' => '現在のステータスでは応募できません。'], 403);
  }

  if (!gachasoku_is_campaign_open($campaign_id)) {
    wp_send_json_error(['message' => 'このキャンペーンの募集は終了しています。'], 400);
  }

  $result = gachasoku_register_campaign_entry($campaign_id, $user_id);
  if (is_wp_error($result)) {
    wp_send_json_error(['message' => $result->get_error_message()], 400);
  }

  $card = gachasoku_get_campaign_card_data($campaign_id);
  $link = ($card && !empty($card['link'])) ? $card['link'] : '';

  wp_send_json_success([
    'message' => 'キャンペーンに応募しました。結果発表をお待ちください。',
    'url' => $link,
  ]);
}

add_action('admin_menu', 'gachasoku_register_membership_admin_pages');
function gachasoku_register_membership_admin_pages() {
  add_menu_page(
    '会員管理',
    '会員管理',
    'manage_options',
    'gachasoku-members',
    'gachasoku_render_member_admin_page',
    'dashicons-groups',
    18
  );

  add_submenu_page(
    'gachasoku-members',
    '抽選管理',
    '抽選管理',
    'manage_options',
    'gachasoku-draws',
    'gachasoku_render_draw_admin_page'
  );
}

add_action('admin_enqueue_scripts', 'gachasoku_enqueue_member_admin_assets');
function gachasoku_enqueue_member_admin_assets($hook) {
  $screens = ['toplevel_page_gachasoku-members', 'gachasoku-members_page_gachasoku-draws'];
  if (!in_array($hook, $screens, true)) {
    return;
  }

  wp_enqueue_style(
    'gachasoku-member-admin',
    get_template_directory_uri() . '/css/member-admin.css',
    [],
    wp_get_theme()->get('Version')
  );
}

function gachasoku_render_member_admin_page() {
  if (!current_user_can('manage_options')) {
    return;
  }

  if (!empty($_POST['gachasoku_member_action'])) {
    gachasoku_handle_member_admin_actions();
  }

  $search = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';
  $status_filter = isset($_GET['status']) ? sanitize_text_field(wp_unslash($_GET['status'])) : '';

  global $wpdb;
  $table = gachasoku_get_members_table();
  $statuses = gachasoku_get_member_status_options();
  $where = [];
  $params = [];

  if ($search) {
    $like = '%' . $wpdb->esc_like($search) . '%';
    $where[] = '(name LIKE %s OR email LIKE %s)';
    $params[] = $like;
    $params[] = $like;
  }

  if ($status_filter && isset($statuses[$status_filter])) {
    $where[] = 'status = %s';
    $params[] = $status_filter;
  }

  $sql = "SELECT * FROM {$table}";
  if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
  }
  $sql .= ' ORDER BY created_at DESC LIMIT 100';

  $query = $params ? $wpdb->prepare($sql, $params) : $sql;
  $users = $wpdb->get_results($query, ARRAY_A);

  ?>
  <div class="wrap gachasoku-member-admin">
    <h1>会員管理</h1>
    <form method="get" class="gachasoku-member-admin__filters">
      <input type="hidden" name="page" value="gachasoku-members" />
      <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="名前・メールアドレスで検索" />
      <select name="status">
        <option value="">ステータスすべて</option>
        <?php foreach ($statuses as $value => $label) : ?>
          <option value="<?php echo esc_attr($value); ?>" <?php selected($status_filter, $value); ?>><?php echo esc_html($label); ?></option>
        <?php endforeach; ?>
      </select>
      <button type="submit" class="button">検索</button>
    </form>

    <?php settings_errors('gachasoku_member_admin'); ?>

    <table class="widefat fixed striped">
      <thead>
        <tr>
          <th>氏名</th>
          <th>メールアドレス</th>
          <th>ステータス</th>
          <th>登録日</th>
          <th>操作</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!empty($users)) : ?>
          <?php foreach ($users as $user) :
            $status = $user['status'];
            ?>
            <tr>
              <td><?php echo esc_html($user['name']); ?></td>
              <td><?php echo esc_html($user['email']); ?></td>
              <td>
                <form method="post" class="gachasoku-member-admin__inline-form">
                  <?php wp_nonce_field('gachasoku_member_update_' . $user['id'], 'gachasoku_member_nonce'); ?>
                  <input type="hidden" name="user_id" value="<?php echo esc_attr($user['id']); ?>" />
                  <select name="member_status">
                    <?php foreach ($statuses as $value => $label) : ?>
                      <option value="<?php echo esc_attr($value); ?>" <?php selected($status, $value); ?>><?php echo esc_html($label); ?></option>
                    <?php endforeach; ?>
                  </select>
                  <button type="submit" name="gachasoku_member_action" value="update_status" class="button">更新</button>
                </form>
              </td>
              <td><?php echo esc_html(gachasoku_format_datetime($user['created_at'])); ?></td>
              <td>
                <form method="post" class="gachasoku-member-admin__inline-form" onsubmit="return confirm('この会員を削除しますか？');">
                  <?php wp_nonce_field('gachasoku_member_delete_' . $user['id'], 'gachasoku_member_nonce'); ?>
                  <input type="hidden" name="user_id" value="<?php echo esc_attr($user['id']); ?>" />
                  <button type="submit" name="gachasoku_member_action" value="delete" class="button button-secondary">削除</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php else : ?>
          <tr>
            <td colspan="5">条件に一致する会員が見つかりませんでした。</td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
  <?php
}

function gachasoku_handle_member_admin_actions() {
  if (!current_user_can('manage_options')) {
    return;
  }

  $action = sanitize_text_field($_POST['gachasoku_member_action']);
  $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;

  if (!$user_id) {
    return;
  }

  $nonce = isset($_POST['gachasoku_member_nonce']) ? $_POST['gachasoku_member_nonce'] : '';

  if ($action === 'update_status') {
    if (!wp_verify_nonce($nonce, 'gachasoku_member_update_' . $user_id)) {
      add_settings_error('gachasoku_member_admin', 'nonce_error', '更新に失敗しました。ページを再読み込みして再度お試しください。', 'error');
      return;
    }

    $status = isset($_POST['member_status']) ? sanitize_text_field($_POST['member_status']) : '';
    if (!gachasoku_set_member_status($user_id, $status)) {
      add_settings_error('gachasoku_member_admin', 'status_error', 'ステータスの更新に失敗しました。', 'error');
    } else {
      add_settings_error('gachasoku_member_admin', 'status_updated', 'ステータスを更新しました。', 'updated');
    }
  } elseif ($action === 'delete') {
    if (!wp_verify_nonce($nonce, 'gachasoku_member_delete_' . $user_id)) {
      add_settings_error('gachasoku_member_admin', 'nonce_error', '削除に失敗しました。ページを再読み込みして再度お試しください。', 'error');
      return;
    }

    if (gachasoku_delete_member($user_id)) {
      add_settings_error('gachasoku_member_admin', 'deleted', '会員を削除しました。', 'updated');
    } else {
      add_settings_error('gachasoku_member_admin', 'delete_failed', '会員の削除に失敗しました。', 'error');
    }
  }
}

function gachasoku_render_draw_admin_page() {
  if (!current_user_can('manage_options')) {
    return;
  }

  if (!empty($_POST['gachasoku_draw_action'])) {
    gachasoku_handle_draw_action();
  }

  $search = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';

  $query_args = [
    'post_type' => 'gachasoku_campaign',
    'posts_per_page' => 20,
    'post_status' => 'publish',
    's' => $search,
  ];

  $campaigns = new WP_Query($query_args);
  ?>
  <div class="wrap gachasoku-draw-admin">
    <h1>抽選管理</h1>
    <form method="get" class="gachasoku-draw-admin__filters">
      <input type="hidden" name="page" value="gachasoku-draws" />
      <input type="search" name="s" placeholder="キャンペーン名で検索" value="<?php echo esc_attr($search); ?>" />
      <button type="submit" class="button">検索</button>
    </form>
    <?php settings_errors('gachasoku_draw_admin'); ?>

    <?php if ($campaigns->have_posts()) : ?>
      <div class="gachasoku-draw-admin__list">
        <?php while ($campaigns->have_posts()) : $campaigns->the_post();
          $campaign_id = get_the_ID();
          $fields = gachasoku_get_campaign_fields($campaign_id);
          $entries_total = gachasoku_get_campaign_entry_count($campaign_id);
          $entries_waiting = gachasoku_get_campaign_entry_count($campaign_id, 'applied');
          $last_logs = gachasoku_get_campaign_winner_logs($campaign_id);
          ?>
          <section class="gachasoku-draw-admin__item">
            <header class="gachasoku-draw-admin__header">
              <h2><?php the_title(); ?></h2>
              <p>応募数：<?php echo esc_html($entries_total); ?> / 抽選待ち：<?php echo esc_html($entries_waiting); ?></p>
            </header>
            <form method="post" class="gachasoku-draw-admin__form">
              <?php wp_nonce_field('gachasoku_draw_' . $campaign_id, 'gachasoku_draw_nonce'); ?>
              <input type="hidden" name="campaign_id" value="<?php echo esc_attr($campaign_id); ?>" />
              <div class="gachasoku-draw-admin__form-grid">
                <label>
                  当選人数
                  <input type="number" name="winner_count" min="1" value="<?php echo esc_attr($fields['max_winners']); ?>" />
                </label>
                <label class="gachasoku-draw-admin__checkbox">
                  <input type="checkbox" name="reset_before_draw" value="1" />
                  前回結果をリセットして再抽選する
                </label>
              </div>
              <div class="gachasoku-draw-admin__actions">
                <button type="submit" name="gachasoku_draw_action" value="run" class="button button-primary" <?php disabled($entries_waiting === 0); ?>>抽選を実行</button>
                <button type="submit" name="gachasoku_draw_action" value="reset" class="button">応募状況をリセット</button>
              </div>
            </form>
            <?php if (!empty($last_logs)) : ?>
              <div class="gachasoku-draw-admin__logs">
                <h3>抽選履歴</h3>
                <ul>
                  <?php foreach ($last_logs as $log) :
                    $winner_ids = $log['winners'] ? json_decode($log['winners'], true) : [];
                    $winner_names = gachasoku_get_campaign_winner_usernames(is_array($winner_ids) ? $winner_ids : []);
                    ?>
                    <li>
                      <strong><?php echo esc_html(gachasoku_format_datetime($log['executed_at'])); ?></strong>
                      <?php if (!empty($winner_names)) : ?>
                        <span>当選者：<?php echo esc_html(implode(' / ', $winner_names)); ?></span>
                      <?php else : ?>
                        <span>当選者：該当なし</span>
                      <?php endif; ?>
                    </li>
                  <?php endforeach; ?>
                </ul>
              </div>
            <?php endif; ?>
          </section>
        <?php endwhile; ?>
      </div>
    <?php else : ?>
      <p>キャンペーンが見つかりませんでした。</p>
    <?php endif; ?>
  </div>
  <?php
  wp_reset_postdata();
}

function gachasoku_handle_draw_action() {
  if (!current_user_can('manage_options')) {
    return;
  }

  $campaign_id = isset($_POST['campaign_id']) ? intval($_POST['campaign_id']) : 0;
  if (!$campaign_id) {
    return;
  }

  $nonce = isset($_POST['gachasoku_draw_nonce']) ? $_POST['gachasoku_draw_nonce'] : '';
  if (!wp_verify_nonce($nonce, 'gachasoku_draw_' . $campaign_id)) {
    add_settings_error('gachasoku_draw_admin', 'nonce_error', '操作に失敗しました。ページを再読み込みして再度お試しください。', 'error');
    return;
  }

  $action = sanitize_text_field($_POST['gachasoku_draw_action']);

  if ($action === 'reset') {
    gachasoku_reset_campaign_entries($campaign_id);
    add_settings_error('gachasoku_draw_admin', 'reset_done', '応募状況をリセットしました。', 'updated');
    return;
  }

  $winner_count = isset($_POST['winner_count']) ? intval($_POST['winner_count']) : 0;
  if ($winner_count < 0) {
    $winner_count = 0;
  }

  if (!empty($_POST['reset_before_draw'])) {
    gachasoku_reset_campaign_entries($campaign_id);
  }

  $winners = gachasoku_select_campaign_winners($campaign_id, $winner_count);
  gachasoku_mark_campaign_results($campaign_id, $winners);
  gachasoku_log_campaign_draw($campaign_id, $winners);

  if (empty($winners)) {
    add_settings_error('gachasoku_draw_admin', 'no_winner', '抽選対象の応募がありませんでした。', 'error');
  } else {
    add_settings_error('gachasoku_draw_admin', 'winner_selected', sprintf('当選者を %d 名選出しました。', count($winners)), 'updated');
  }
}

function gachasoku_reset_campaign_entries($campaign_id) {
  global $wpdb;
  $table = $wpdb->prefix . 'gachasoku_campaign_entries';
  $wpdb->update(
    $table,
    [
      'status' => 'applied',
      'result_at' => null,
      'updated_at' => current_time('mysql'),
    ],
    ['campaign_id' => $campaign_id],
    ['%s', '%s', '%s'],
    ['%d']
  );
}

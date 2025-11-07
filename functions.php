<?php
define('GACHASOKU_FAVORITE_SITES_META_KEY', 'gachasoku_favorite_sites');
define('GACHASOKU_FAVORITE_SITES_UPDATED_META_KEY', 'gachasoku_favorite_sites_updated');
define('GACHASOKU_CAMPAIGN_REQUIRED_SITES_META_KEY', 'gachasoku_campaign_required_sites');

add_action('after_setup_theme', function() {
  add_theme_support('title-tag');
  add_theme_support('post-thumbnails');
  register_nav_menus(['main-menu' => 'メインメニュー']);
});

add_action('wp_enqueue_scripts', function() {
  wp_enqueue_style('yellowsmile-style', get_stylesheet_uri());
});

/**
 * Retrieve ranking site options used for "推しサイト" selections.
 *
 * @return array<int,string> Keyed by post ID with the display label as the value.
 */
function gachasoku_get_ranking_site_options(): array {
  $options = apply_filters('gachasoku_ranking_site_options', null);
  if (is_array($options)) {
    return array_map('wp_strip_all_tags', $options);
  }

  $post_types = ['ranking_site', 'ranking', 'site'];
  $posts = [];

  foreach ($post_types as $post_type) {
    if (post_type_exists($post_type)) {
      $posts = get_posts([
        'post_type'      => $post_type,
        'numberposts'    => -1,
        'orderby'        => 'menu_order title',
        'order'          => 'ASC',
        'post_status'    => 'publish',
        'suppress_filters' => false,
      ]);
      break;
    }
  }

  if (empty($posts)) {
    $posts = get_posts([
      'post_type'      => 'post',
      'numberposts'    => -1,
      'category_name'  => 'ranking',
      'orderby'        => 'menu_order title',
      'order'          => 'ASC',
      'post_status'    => 'publish',
      'suppress_filters' => false,
    ]);
  }

  $options = [];
  foreach ($posts as $post) {
    $options[$post->ID] = get_the_title($post);
  }

  /**
   * Last chance for customisation before returning.
   *
   * @param array<int,string> $options
   */
  $options = apply_filters('gachasoku_ranking_site_options_resolved', $options);

  return array_map('wp_strip_all_tags', $options);
}

/**
 * Fetch a user's stored favourite site IDs.
 *
 * @param int $user_id Optional. Defaults to the current user.
 * @return int[]
 */
function gachasoku_get_user_favorite_sites(int $user_id = 0): array {
  $user_id = $user_id ?: get_current_user_id();
  if (!$user_id) {
    return [];
  }

  $stored = get_user_meta($user_id, GACHASOKU_FAVORITE_SITES_META_KEY, true);
  if (!is_array($stored)) {
    $stored = [];
  }

  return array_values(array_unique(array_map('absint', $stored)));
}

/**
 * Determine whether a user can update their favourite sites and return remaining cooldown.
 *
 * @param int $user_id Optional. Defaults to the current user.
 * @return array{allowed:bool,remaining:int}
 */
function gachasoku_user_can_update_favorite_sites(int $user_id = 0): array {
  $user_id = $user_id ?: get_current_user_id();
  if (!$user_id) {
    return ['allowed' => false, 'remaining' => 0];
  }

  $last_updated = (int) get_user_meta($user_id, GACHASOKU_FAVORITE_SITES_UPDATED_META_KEY, true);
  if (!$last_updated) {
    return ['allowed' => true, 'remaining' => 0];
  }

  $cooldown = 3 * DAY_IN_SECONDS;
  $elapsed = time() - $last_updated;
  if ($elapsed >= $cooldown) {
    return ['allowed' => true, 'remaining' => 0];
  }

  return ['allowed' => false, 'remaining' => max(0, $cooldown - $elapsed)];
}

/**
 * Persist the user's favourite site IDs and update the timestamp.
 *
 * @param int   $user_id
 * @param int[] $site_ids
 * @return void
 */
function gachasoku_set_user_favorite_sites(int $user_id, array $site_ids): void {
  $site_ids = array_values(array_unique(array_map('absint', $site_ids)));
  $site_ids = array_slice($site_ids, 0, 2);

  update_user_meta($user_id, GACHASOKU_FAVORITE_SITES_META_KEY, $site_ids);
  update_user_meta($user_id, GACHASOKU_FAVORITE_SITES_UPDATED_META_KEY, time());
}

/**
 * Store a temporary notice for the favourite site form.
 *
 * @param int    $user_id
 * @param string $type    success|error
 * @param string $message
 * @return void
 */
function gachasoku_set_favorite_sites_notice(int $user_id, string $type, string $message): void {
  set_transient(
    'gachasoku_favorite_sites_notice_' . $user_id,
    [
      'type'    => $type,
      'message' => $message,
    ],
    MINUTE_IN_SECONDS * 10
  );
}

/**
 * Retrieve and clear the stored notice for the favourite site form.
 *
 * @param int $user_id
 * @return array{type:string,message:string}|null
 */
function gachasoku_get_favorite_sites_notice(int $user_id): ?array {
  $notice = get_transient('gachasoku_favorite_sites_notice_' . $user_id);
  if ($notice) {
    delete_transient('gachasoku_favorite_sites_notice_' . $user_id);
  }

  return is_array($notice) ? $notice : null;
}

/**
 * Handle favourite site updates from the My Page form.
 */
function gachasoku_handle_favorite_sites_submission(): void {
  if (empty($_POST['gachasoku_favorite_sites_action'])) {
    return;
  }

  if (!is_user_logged_in()) {
    return;
  }

  $user_id = get_current_user_id();
  $redirect = wp_get_referer() ?: home_url('/');

  if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'gachasoku_update_favorite_sites')) {
    gachasoku_set_favorite_sites_notice($user_id, 'error', '不正なリクエストです。もう一度お試しください。');
    wp_safe_redirect($redirect);
    exit;
  }

  $cooldown = gachasoku_user_can_update_favorite_sites($user_id);
  if (!$cooldown['allowed']) {
    $remaining = human_time_diff(time(), time() + $cooldown['remaining']);
    gachasoku_set_favorite_sites_notice(
      $user_id,
      'error',
      sprintf('推しサイトは%s後に変更できます。', $remaining)
    );
    wp_safe_redirect($redirect);
    exit;
  }

  $available = array_keys(gachasoku_get_ranking_site_options());
  $submitted = isset($_POST['gachasoku_favorite_sites']) ? (array) $_POST['gachasoku_favorite_sites'] : [];
  $submitted = array_map('absint', $submitted);
  $submitted = array_values(array_unique(array_intersect($submitted, $available)));

  if (count($submitted) > 2) {
    $submitted = array_slice($submitted, 0, 2);
  }

  $current = gachasoku_get_user_favorite_sites($user_id);
  $normalized_current = array_values($current);
  $normalized_submitted = array_values($submitted);
  sort($normalized_current);
  sort($normalized_submitted);

  if ($normalized_current === $normalized_submitted) {
    gachasoku_set_favorite_sites_notice($user_id, 'success', '推しサイトの設定に変更はありません。');
    wp_safe_redirect($redirect);
    exit;
  }

  gachasoku_set_user_favorite_sites($user_id, $submitted);

  if (!empty($submitted)) {
    gachasoku_set_favorite_sites_notice($user_id, 'success', '推しサイトを更新しました。');
  } else {
    gachasoku_set_favorite_sites_notice($user_id, 'success', '推しサイトの設定を削除しました。');
  }

  wp_safe_redirect($redirect);
  exit;
}
add_action('init', 'gachasoku_handle_favorite_sites_submission');

/**
 * Determine whether the current request targets the My Page view.
 *
 * @param WP_Post|null $page Optional specific page object.
 * @return bool
 */
function gachasoku_is_mypage($page = null): bool {
  if (!$page) {
    $page = get_queried_object();
  }

  if (!$page || !($page instanceof WP_Post)) {
    return false;
  }

  $page_slug = $page->post_name ?? '';
  $page_title = wp_strip_all_tags($page->post_title ?? '');

  $target_slugs = (array) apply_filters('gachasoku_mypage_slugs', ['mypage', 'my-page']);
  $normalized_slugs = array_filter(array_map('sanitize_title', $target_slugs));

  if ($page_slug && in_array($page_slug, $target_slugs, true)) {
    return true;
  }

  if ($page_slug && in_array(sanitize_title($page_slug), $normalized_slugs, true)) {
    return true;
  }

  $target_titles = (array) apply_filters('gachasoku_mypage_titles', ['マイページ']);
  foreach ($target_titles as $target_title) {
    if ($target_title !== '' && $page_title === wp_strip_all_tags($target_title)) {
      return true;
    }
  }

  return false;
}

/**
 * Render the favourite site selection form for the My Page.
 *
 * @return string
 */
function gachasoku_render_favorite_sites_form(): string {
  if (!is_user_logged_in()) {
    return '<p>推しサイトを設定するにはログインしてください。</p>';
  }

  $user_id = get_current_user_id();
  $options = gachasoku_get_ranking_site_options();

  if (empty($options)) {
    return '<p>ランキングがまだ登録されていません。</p>';
  }

  $current = gachasoku_get_user_favorite_sites($user_id);
  $cooldown = gachasoku_user_can_update_favorite_sites($user_id);
  $notice = gachasoku_get_favorite_sites_notice($user_id);

  $output = '<div class="favorite-sites-form">';

  if ($notice) {
    $class = $notice['type'] === 'success' ? 'notice-success' : 'notice-error';
    $output .= sprintf('<div class="notice %1$s"><p>%2$s</p></div>', esc_attr($class), esc_html($notice['message']));
  }

  if (!$cooldown['allowed'] && $cooldown['remaining'] > 0) {
    $output .= sprintf(
      '<p class="cooldown-message">推しサイトはあと%s後に変更できます。</p>',
      esc_html(human_time_diff(time(), time() + $cooldown['remaining']))
    );
  }

  $output .= '<form method="post">';
  $output .= wp_nonce_field('gachasoku_update_favorite_sites', '_wpnonce', true, false);
  $output .= '<input type="hidden" name="gachasoku_favorite_sites_action" value="1" />';

  for ($i = 0; $i < 2; $i++) {
    $selected = $current[$i] ?? 0;
    $output .= '<div class="favorite-site-select">';
    $output .= sprintf('<label for="gachasoku-favorite-site-%1$d">推しサイト%2$d</label>', $i + 1, $i + 1);
    $output .= sprintf('<select name="gachasoku_favorite_sites[]" id="gachasoku-favorite-site-%1$d" %2$s>', $i + 1, $cooldown['allowed'] ? '' : 'disabled');
    $output .= '<option value="">未選択</option>';
    foreach ($options as $id => $label) {
      $output .= sprintf(
        '<option value="%1$d" %2$s>%3$s</option>',
        (int) $id,
        selected($selected, $id, false),
        esc_html($label)
      );
    }
    $output .= '</select>';
    $output .= '</div>';
  }

  $output .= sprintf(
    '<button type="submit" class="button" %s>推しサイトを保存</button>',
    $cooldown['allowed'] ? '' : 'disabled'
  );
  $output .= '</form>';
  $output .= '</div>';

  return $output;
}
add_shortcode('gachasoku_favorite_sites_form', 'gachasoku_render_favorite_sites_form');

/**
 * Append the favourite site form to the My Page content automatically.
 *
 * @param string $content
 * @return string
 */
function gachasoku_append_mypage_favorite_form(string $content): string {
  if (!is_page()) {
    return $content;
  }

  $page = get_queried_object();
  if (!gachasoku_is_mypage($page)) {
    return $content;
  }

  if (empty($GLOBALS['gachasoku_favorite_sites_form_appended'])) {
    $content .= do_shortcode('[gachasoku_favorite_sites_form]');
    $GLOBALS['gachasoku_favorite_sites_form_appended'] = true;
  }

  return $content;
}
add_filter('the_content', 'gachasoku_append_mypage_favorite_form');

/**
 * Retrieve the required favourite sites for a campaign.
 *
 * @param int $campaign_id
 * @return int[]
 */
function gachasoku_get_campaign_required_sites(int $campaign_id): array {
  $required = get_post_meta($campaign_id, GACHASOKU_CAMPAIGN_REQUIRED_SITES_META_KEY, true);
  if (!is_array($required)) {
    $required = [];
  }

  return array_values(array_unique(array_map('absint', $required)));
}

/**
 * Determine if a user meets the campaign's favourite site requirement.
 *
 * @param int $campaign_id
 * @param int $user_id Optional. Defaults to the current user.
 * @return bool
 */
function gachasoku_campaign_user_meets_favorite_requirement(int $campaign_id, int $user_id = 0): bool {
  $required = gachasoku_get_campaign_required_sites($campaign_id);
  if (empty($required)) {
    return true;
  }

  $favorites = gachasoku_get_user_favorite_sites($user_id);
  if (empty($favorites)) {
    return false;
  }

  return (bool) array_intersect($required, $favorites);
}

/**
 * Filter hook to enforce favourite site restrictions when available.
 *
 * @param bool $can_enter
 * @param int  $campaign_id
 * @param int  $user_id
 * @return bool
 */
function gachasoku_filter_campaign_entry_by_favorites(bool $can_enter, int $campaign_id, int $user_id): bool {
  if (!$can_enter) {
    return false;
  }

  return gachasoku_campaign_user_meets_favorite_requirement($campaign_id, $user_id);
}
add_filter('gachasoku_campaign_user_can_enter', 'gachasoku_filter_campaign_entry_by_favorites', 10, 3);

/**
 * Register campaign meta boxes for favourite site restrictions.
 */
function gachasoku_register_campaign_meta_box(): void {
  if (!post_type_exists('campaign')) {
    return;
  }

  add_meta_box(
    'gachasoku_campaign_favorite_sites',
    '推しサイト制限',
    'gachasoku_render_campaign_meta_box',
    'campaign',
    'side',
    'high'
  );
}
add_action('add_meta_boxes', 'gachasoku_register_campaign_meta_box');

/**
 * Render the campaign favourite site meta box.
 *
 * @param WP_Post $post
 */
function gachasoku_render_campaign_meta_box($post): void {
  $options = gachasoku_get_ranking_site_options();
  wp_nonce_field('gachasoku_campaign_favorite_sites', 'gachasoku_campaign_favorite_sites_nonce');

  if (empty($options)) {
    echo '<p>ランキングが未設定のため、制限を設けられません。</p>';
    return;
  }

  $selected = gachasoku_get_campaign_required_sites($post->ID);

  echo '<p>このキャンペーンに参加できる推しサイトを選択してください。複数選択が可能です。</p>';
  echo '<ul class="campaign-favorite-site-list">';
  foreach ($options as $id => $label) {
    printf(
      '<li><label><input type="checkbox" name="gachasoku_campaign_required_sites[]" value="%1$d" %2$s> %3$s</label></li>',
      (int) $id,
      checked(in_array((int) $id, $selected, true), true, false),
      esc_html($label)
    );
  }
  echo '</ul>';
}

/**
 * Persist the campaign favourite site requirement.
 *
 * @param int $post_id
 */
function gachasoku_save_campaign_meta(int $post_id): void {
  if (!post_type_exists('campaign')) {
    return;
  }

  if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
    return;
  }

  if (!isset($_POST['post_type']) || 'campaign' !== $_POST['post_type']) {
    return;
  }

  if (!isset($_POST['gachasoku_campaign_favorite_sites_nonce']) || !wp_verify_nonce($_POST['gachasoku_campaign_favorite_sites_nonce'], 'gachasoku_campaign_favorite_sites')) {
    return;
  }

  if (!current_user_can('edit_post', $post_id)) {
    return;
  }

  $available = array_keys(gachasoku_get_ranking_site_options());
  $submitted = isset($_POST['gachasoku_campaign_required_sites']) ? (array) $_POST['gachasoku_campaign_required_sites'] : [];
  $submitted = array_map('absint', $submitted);
  $submitted = array_values(array_unique(array_intersect($submitted, $available)));

  update_post_meta($post_id, GACHASOKU_CAMPAIGN_REQUIRED_SITES_META_KEY, $submitted);
}
add_action('save_post', 'gachasoku_save_campaign_meta');

/**
 * Convenience helper for templates/shortcodes to show requirement messages.
 *
 * @param int $campaign_id
 * @param int $user_id Optional. Defaults to current user.
 * @return string
 */
function gachasoku_get_campaign_requirement_message(int $campaign_id, int $user_id = 0): string {
  $required = gachasoku_get_campaign_required_sites($campaign_id);
  if (empty($required)) {
    return '';
  }

  $options = gachasoku_get_ranking_site_options();
  $labels = [];
  foreach ($required as $id) {
    if (isset($options[$id])) {
      $labels[] = $options[$id];
    }
  }

  if (empty($labels)) {
    return '';
  }

  if ($user_id && !gachasoku_campaign_user_meets_favorite_requirement($campaign_id, $user_id)) {
    return sprintf('この抽選に参加するには、推しサイトとして「%s」を設定する必要があります。', esc_html(implode('」「', $labels)));
  }

  return sprintf('この抽選は推しサイト「%s」を推しているユーザーのみ参加できます。', esc_html(implode('」「', $labels)));
}

/**
 * Shortcode to surface campaign requirement notices.
 *
 * Usage: [gachasoku_campaign_requirement id="123"]
 *
 * @param array<string,string> $atts
 * @return string
 */
function gachasoku_campaign_requirement_shortcode(array $atts): string {
  $atts = shortcode_atts([
    'id' => 0,
  ], $atts, 'gachasoku_campaign_requirement');

  $campaign_id = absint($atts['id']);
  if (!$campaign_id) {
    return '';
  }

  $message = gachasoku_get_campaign_requirement_message($campaign_id, get_current_user_id());
  if ('' === $message) {
    return '';
  }

  return sprintf('<div class="campaign-requirement-notice"><p>%s</p></div>', esc_html($message));
}
add_shortcode('gachasoku_campaign_requirement', 'gachasoku_campaign_requirement_shortcode');

/**
 * Append requirement notices to campaign content automatically.
 *
 * @param string $content
 * @return string
 */
function gachasoku_append_campaign_requirement_notice(string $content): string {
  if (!post_type_exists('campaign') || !is_singular('campaign')) {
    return $content;
  }

  $campaign_id = get_queried_object_id();
  if (!$campaign_id) {
    return $content;
  }

  $message = gachasoku_get_campaign_requirement_message($campaign_id, get_current_user_id());
  if ('' === $message) {
    return $content;
  }

  $notice = sprintf('<div class="campaign-requirement-notice"><p>%s</p></div>', esc_html($message));

  if (false === strpos($content, $notice)) {
    $content = $notice . $content;
  }

  return $content;
}
add_filter('the_content', 'gachasoku_append_campaign_requirement_notice', 9);
?>

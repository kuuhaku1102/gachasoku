<?php
/**
 * AIポケカ投資予想機能
 * カスタム投稿タイプと管理画面
 */

// カスタム投稿タイプの登録
function gachasoku_register_ai_pokeca_investment() {
  register_post_type('ai_pokeca_invest', [
    'labels' => [
      'name'               => 'AIポケカ投資',
      'singular_name'      => 'AIポケカ投資',
      'add_new'            => '新規追加',
      'add_new_item'       => '新しい予想を追加',
      'edit_item'          => '予想を編集',
      'new_item'           => '新しい予想',
      'view_item'          => '予想を表示',
      'search_items'       => '予想を検索',
      'not_found'          => '予想が見つかりませんでした',
      'not_found_in_trash' => 'ゴミ箱に予想はありません',
    ],
    'public'              => true,
    'has_archive'         => true,
    'rewrite'             => ['slug' => 'ai-pokeca-investment'],
    'supports'            => ['title', 'editor', 'thumbnail'],
    'menu_icon'           => 'dashicons-chart-line',
    'show_in_rest'        => true,
    'menu_position'       => 5,
  ]);
}
add_action('init', 'gachasoku_register_ai_pokeca_investment');

// カスタムフィールドの追加
function gachasoku_add_ai_pokeca_meta_boxes() {
  add_meta_box(
    'ai_pokeca_cards',
    '予想カード情報（最大6枚）',
    'gachasoku_render_ai_pokeca_cards_meta_box',
    'ai_pokeca_invest',
    'normal',
    'high'
  );
}
add_action('add_meta_boxes', 'gachasoku_add_ai_pokeca_meta_boxes');

// メタボックスの表示
function gachasoku_render_ai_pokeca_cards_meta_box($post) {
  wp_nonce_field('ai_pokeca_cards_nonce', 'ai_pokeca_cards_nonce');
  
  $cards = get_post_meta($post->ID, '_ai_pokeca_cards', true);
  if (!is_array($cards)) {
    $cards = [];
  }
  
  // 最大6枚まで
  for ($i = 0; $i < 6; $i++) {
    $card = isset($cards[$i]) ? $cards[$i] : [
      'image' => '',
      'name' => '',
      'price' => '',
      'reason' => '',
    ];
    ?>
    <div class="ai-pokeca-card-item" style="margin-bottom: 30px; padding: 20px; border: 1px solid #ddd; background: #f9f9f9;">
      <h3 style="margin-top: 0;">カード <?php echo $i + 1; ?></h3>
      
      <p>
        <label><strong>カード画像URL:</strong></label><br>
        <input type="text" name="ai_pokeca_cards[<?php echo $i; ?>][image]" 
               value="<?php echo esc_attr($card['image']); ?>" 
               style="width: 100%;" placeholder="https://example.com/card-image.jpg">
        <small>画像のURLを入力してください</small>
      </p>
      
      <p>
        <label><strong>カード名:</strong></label><br>
        <input type="text" name="ai_pokeca_cards[<?php echo $i; ?>][name]" 
               value="<?php echo esc_attr($card['name']); ?>" 
               style="width: 100%;" placeholder="例: リザードンVMAX SSR">
      </p>
      
      <p>
        <label><strong>現在価格（円）:</strong></label><br>
        <input type="text" name="ai_pokeca_cards[<?php echo $i; ?>][price]" 
               value="<?php echo esc_attr($card['price']); ?>" 
               style="width: 100%;" placeholder="例: 15,000">
      </p>
      
      <p>
        <label><strong>予想根拠:</strong></label><br>
        <textarea name="ai_pokeca_cards[<?php echo $i; ?>][reason]" 
                  rows="4" style="width: 100%;" 
                  placeholder="AIが高騰を予想する理由を記入"><?php echo esc_textarea($card['reason']); ?></textarea>
      </p>
    </div>
    <?php
  }
}

// メタデータの保存
function gachasoku_save_ai_pokeca_cards($post_id) {
  // 自動保存の場合は何もしない
  if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
    return;
  }
  
  // Nonceの検証
  if (!isset($_POST['ai_pokeca_cards_nonce']) || 
      !wp_verify_nonce($_POST['ai_pokeca_cards_nonce'], 'ai_pokeca_cards_nonce')) {
    return;
  }
  
  // 権限チェック
  if (!current_user_can('edit_post', $post_id)) {
    return;
  }
  
  // カードデータの保存
  if (isset($_POST['ai_pokeca_cards'])) {
    $cards = [];
    foreach ($_POST['ai_pokeca_cards'] as $card) {
      // 空のカードはスキップ
      if (empty($card['name']) && empty($card['image'])) {
        continue;
      }
      
      $cards[] = [
        'image'  => sanitize_text_field($card['image']),
        'name'   => sanitize_text_field($card['name']),
        'price'  => sanitize_text_field($card['price']),
        'reason' => sanitize_textarea_field($card['reason']),
      ];
    }
    
    update_post_meta($post_id, '_ai_pokeca_cards', $cards);
  }
}
add_action('save_post_ai_pokeca_invest', 'gachasoku_save_ai_pokeca_cards');

// 管理画面のカラム追加
function gachasoku_ai_pokeca_columns($columns) {
  $new_columns = [];
  foreach ($columns as $key => $value) {
    $new_columns[$key] = $value;
    if ($key === 'title') {
      $new_columns['card_count'] = 'カード数';
      $new_columns['publish_date'] = '公開日';
    }
  }
  return $new_columns;
}
add_filter('manage_ai_pokeca_invest_posts_columns', 'gachasoku_ai_pokeca_columns');

// カラムの内容表示
function gachasoku_ai_pokeca_column_content($column, $post_id) {
  if ($column === 'card_count') {
    $cards = get_post_meta($post_id, '_ai_pokeca_cards', true);
    echo is_array($cards) ? count($cards) : 0;
  }
  if ($column === 'publish_date') {
    echo get_the_date('Y年n月j日', $post_id);
  }
}
add_action('manage_ai_pokeca_invest_posts_custom_column', 'gachasoku_ai_pokeca_column_content', 10, 2);

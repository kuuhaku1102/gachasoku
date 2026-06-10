<?php
/**
 * ガチャ速 オークション機能
 *
 * Phase 1: 基盤
 *  - カスタム投稿タイプ「オークション」(gachasoku_auction)
 *  - 出品メタ情報（開始価格 / 最低入札単位 / 即決価格 / 期間 / 自動延長 / 画像 / DM先Xアカウント）
 *  - 専用DBテーブル2つ（入札履歴 / 落札・トークン管理）
 *
 * 安全方針:
 *  - 既存の会員・キャンペーン用テーブルには一切触れない（読み書き・変更なし）
 *  - 本機能専用のDBバージョン option で管理し、追記専用の dbDelta のみ使用
 *  - 全フォームに nonce、管理操作に権限チェック、出力は esc_*、SQLは $wpdb->prepare
 *
 * このファイルは inc/membership.php の既存ヘルパー
 *  (gachasoku_sanitize_datetime_local / gachasoku_datetime_local_to_mysql など) に依存します。
 *
 * @package Gachasoku
 */

if (!defined('ABSPATH')) {
    exit;
}

/* -------------------------------------------------------------------------
 * 定数
 * ---------------------------------------------------------------------- */

if (!defined('GACHASOKU_AUCTION_DB_VERSION')) {
    // テーブル定義を変更した場合のみインクリメントする。
    define('GACHASOKU_AUCTION_DB_VERSION', '1.0.0');
}

if (!defined('GACHASOKU_AUCTION_POST_TYPE')) {
    define('GACHASOKU_AUCTION_POST_TYPE', 'gachasoku_auction');
}

/* -------------------------------------------------------------------------
 * テーブル名ヘルパー
 * ---------------------------------------------------------------------- */

/**
 * 入札履歴テーブル名を返す。
 *
 * @return string
 */
function gachasoku_get_auction_bids_table() {
    global $wpdb;
    return $wpdb->prefix . 'gachasoku_auction_bids';
}

/**
 * 落札・トークン管理テーブル名を返す。
 *
 * @return string
 */
function gachasoku_get_auction_winners_table() {
    global $wpdb;
    return $wpdb->prefix . 'gachasoku_auction_winners';
}

/* -------------------------------------------------------------------------
 * テーブル作成（追記専用・既存テーブルには触れない）
 * ---------------------------------------------------------------------- */

add_action('init', 'gachasoku_maybe_install_auction_tables', 11);
/**
 * オークション専用テーブルを必要時のみ作成する。
 *
 * dbDelta は「無ければ作る／不足列を足す」追記専用関数であり、
 * 既存の列・行を削除しない。会員用テーブルとは独立したバージョン option で
 * 二重実行を防ぐため、既存の会員データには一切影響しない。
 *
 * @return void
 */
function gachasoku_maybe_install_auction_tables() {
    $installed = get_option('gachasoku_auction_db_version');

    if ($installed === GACHASOKU_AUCTION_DB_VERSION && gachasoku_auction_tables_exist()) {
        return;
    }

    gachasoku_install_auction_tables();
}

/**
 * 専用テーブルが存在するか判定する。
 *
 * @param bool $force_refresh キャッシュを無視するか。
 * @return bool
 */
function gachasoku_auction_tables_exist($force_refresh = false) {
    static $exists = null;

    if ($force_refresh) {
        $exists = null;
    }

    if ($exists !== null) {
        return $exists;
    }

    global $wpdb;

    $bids_table = gachasoku_get_auction_bids_table();
    $winners_table = gachasoku_get_auction_winners_table();

    $found_bids = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $bids_table));
    $found_winners = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $winners_table));

    $exists = ($found_bids === $bids_table) && ($found_winners === $winners_table);

    return $exists;
}

/**
 * 専用テーブルを作成する（追記専用）。
 *
 * @return void
 */
function gachasoku_install_auction_tables() {
    global $wpdb;

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $charset_collate = $wpdb->get_charset_collate();
    $bids_table = gachasoku_get_auction_bids_table();
    $winners_table = gachasoku_get_auction_winners_table();

    // 入札履歴。status: active(現在有効) / outbid(上書きされた) / cancelled。
    $bids_sql = "CREATE TABLE {$bids_table} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        auction_id bigint(20) unsigned NOT NULL,
        member_id bigint(20) unsigned NOT NULL,
        amount bigint(20) unsigned NOT NULL,
        status varchar(20) NOT NULL DEFAULT 'active',
        ip_address varchar(100) DEFAULT NULL,
        user_agent varchar(191) DEFAULT NULL,
        created_at datetime NOT NULL,
        PRIMARY KEY  (id),
        KEY auction_id (auction_id),
        KEY member_id (member_id),
        KEY auction_amount (auction_id, amount)
    ) {$charset_collate};";

    // 落札・トークン管理。
    //  - token は暗号化して保存（平文では保存しない）。表示は落札者本人のみ。
    //  - dm_status: pending(未送信) / submitted(確認待ち) / confirmed(当選確定)。
    $winners_sql = "CREATE TABLE {$winners_table} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        auction_id bigint(20) unsigned NOT NULL,
        member_id bigint(20) unsigned NOT NULL,
        winning_amount bigint(20) unsigned NOT NULL,
        token longtext NOT NULL,
        token_issued_at datetime NOT NULL,
        dm_status varchar(20) NOT NULL DEFAULT 'pending',
        confirmed_at datetime NULL,
        confirmed_by bigint(20) unsigned DEFAULT 0,
        created_at datetime NOT NULL,
        updated_at datetime NOT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY auction_id (auction_id),
        KEY member_id (member_id)
    ) {$charset_collate};";

    dbDelta($bids_sql);
    dbDelta($winners_sql);

    update_option('gachasoku_auction_db_version', GACHASOKU_AUCTION_DB_VERSION);

    // 静的キャッシュをリセットして次回参照時に最新状態を返す。
    gachasoku_auction_tables_exist(true);
}

/* -------------------------------------------------------------------------
 * カスタム投稿タイプ
 * ---------------------------------------------------------------------- */

add_action('init', 'gachasoku_register_auction_post_type');
/**
 * 「オークション」投稿タイプを登録する。
 *
 * @return void
 */
function gachasoku_register_auction_post_type() {
    $labels = [
        'name' => 'オークション',
        'singular_name' => 'オークション',
        'add_new' => '新規追加',
        'add_new_item' => 'オークションを追加',
        'edit_item' => 'オークションを編集',
        'new_item' => '新規オークション',
        'view_item' => 'オークションを表示',
        'all_items' => 'オークション一覧',
        'search_items' => 'オークションを検索',
        'not_found' => 'オークションが見つかりませんでした。',
        'not_found_in_trash' => 'ゴミ箱にオークションはありません。',
        'menu_name' => 'オークション',
    ];

    register_post_type(GACHASOKU_AUCTION_POST_TYPE, [
        'labels' => $labels,
        'public' => true,
        'has_archive' => true,
        'show_in_rest' => true,
        'menu_icon' => 'dashicons-hammer',
        'supports' => ['title', 'editor', 'excerpt'],
        'rewrite' => ['slug' => 'auction'],
    ]);
}

/* -------------------------------------------------------------------------
 * 出品メタ情報
 * ---------------------------------------------------------------------- */

/**
 * オークションのメタ情報を取得する。
 *
 * @param int $auction_id 投稿ID。
 * @return array{
 *   start_datetime:string, end_datetime:string, start_price:int, bid_increment:int,
 *   buy_now_price:int, image_id:int, x_account:string, auto_extend_enabled:bool,
 *   auto_extend_minutes:int, description:string
 * }
 */
function gachasoku_get_auction_fields($auction_id) {
    $auction_id = intval($auction_id);

    $start_price = (int) get_post_meta($auction_id, '_gachasoku_auction_start_price', true);
    $bid_increment = (int) get_post_meta($auction_id, '_gachasoku_auction_bid_increment', true);
    $buy_now_price = (int) get_post_meta($auction_id, '_gachasoku_auction_buy_now_price', true);
    $auto_extend_minutes = (int) get_post_meta($auction_id, '_gachasoku_auction_auto_extend_minutes', true);

    if ($bid_increment < 1) {
        $bid_increment = 1;
    }
    if ($auto_extend_minutes < 1) {
        $auto_extend_minutes = 5;
    }

    return [
        'start_datetime' => (string) get_post_meta($auction_id, '_gachasoku_auction_start', true),
        'end_datetime' => (string) get_post_meta($auction_id, '_gachasoku_auction_end', true),
        'start_price' => max(0, $start_price),
        'bid_increment' => $bid_increment,
        'buy_now_price' => max(0, $buy_now_price),
        'image_id' => (int) get_post_meta($auction_id, '_gachasoku_auction_image_id', true),
        'x_account' => (string) get_post_meta($auction_id, '_gachasoku_auction_x_account', true),
        'auto_extend_enabled' => (bool) get_post_meta($auction_id, '_gachasoku_auction_auto_extend_enabled', true),
        'auto_extend_minutes' => $auto_extend_minutes,
        'condition' => (string) get_post_meta($auction_id, '_gachasoku_auction_condition', true),
        'description' => (string) get_post_meta($auction_id, '_gachasoku_auction_description', true),
    ];
}

/**
 * オークションの種類（コンディション）の選択肢を返す。
 *
 * @return array key => ラベル
 */
function gachasoku_get_auction_condition_options() {
    return [
        'box' => 'BOX',
        'psa10' => 'PSA10',
        'mint' => '美品',
        'minor_scratch' => 'キズ多少あり',
        'damage_large' => 'ダメージ大',
        'damage_small' => 'ダメージ小',
    ];
}

/**
 * 種類キーから表示ラベルを返す（未設定・不正値は空文字）。
 *
 * @param string $key コンディションキー。
 * @return string
 */
function gachasoku_get_auction_condition_label($key) {
    $options = gachasoku_get_auction_condition_options();
    return isset($options[$key]) ? $options[$key] : '';
}

/**
 * Xアカウント名を正規化する（先頭の @ や URL を取り除き、英数字_のみ残す）。
 *
 * @param string $value 入力値。
 * @return string @を除いたハンドル名。
 */
function gachasoku_sanitize_x_account($value) {
    $value = is_string($value) ? trim($value) : '';
    if ($value === '') {
        return '';
    }

    // URL 形式で入力された場合は末尾のハンドルを抽出する。
    if (preg_match('#(?:twitter|x)\.com/@?([A-Za-z0-9_]{1,15})#i', $value, $matches)) {
        return $matches[1];
    }

    $value = ltrim($value, '@');
    $value = preg_replace('/[^A-Za-z0-9_]/', '', $value);

    return substr((string) $value, 0, 15);
}

add_action('add_meta_boxes', 'gachasoku_add_auction_metaboxes');
/**
 * オークション編集画面にメタボックスを追加する。
 *
 * @return void
 */
function gachasoku_add_auction_metaboxes() {
    add_meta_box(
        'gachasoku-auction-meta',
        'オークション設定',
        'gachasoku_render_auction_meta_box',
        GACHASOKU_AUCTION_POST_TYPE,
        'normal',
        'high'
    );
}

/**
 * オークション設定メタボックスを描画する。
 *
 * @param WP_Post $post 投稿オブジェクト。
 * @return void
 */
function gachasoku_render_auction_meta_box($post) {
    wp_nonce_field('gachasoku_save_auction', 'gachasoku_auction_nonce');

    $fields = gachasoku_get_auction_fields($post->ID);
    $start = gachasoku_sanitize_datetime_local($fields['start_datetime']);
    $end = gachasoku_sanitize_datetime_local($fields['end_datetime']);
    $image_src = $fields['image_id'] ? wp_get_attachment_image_src($fields['image_id'], 'medium') : null;
    ?>
    <style>
        .gachasoku-auction-meta th { width: 220px; text-align: left; vertical-align: top; padding-top: 12px; }
        .gachasoku-auction-meta td { padding-top: 8px; }
        .gachasoku-auction-meta .description { color: #666; }
        .gachasoku-auction-image__preview img { max-width: 240px; height: auto; display: block; margin-bottom: 8px; }
    </style>
    <table class="form-table gachasoku-auction-meta">
        <tr>
            <th scope="row"><label for="gachasoku_auction_start">開始日時</label></th>
            <td><input type="datetime-local" name="gachasoku_auction_start" id="gachasoku_auction_start" value="<?php echo esc_attr($start); ?>" /></td>
        </tr>
        <tr>
            <th scope="row"><label for="gachasoku_auction_end">終了日時</label></th>
            <td>
                <input type="datetime-local" name="gachasoku_auction_end" id="gachasoku_auction_end" value="<?php echo esc_attr($end); ?>" />
                <p class="description">この日時を過ぎると自動的に締め切られ、最高額入札者が落札者になります。</p>
            </td>
        </tr>
        <tr>
            <th scope="row"><label for="gachasoku_auction_condition">種類（コンディション）</label></th>
            <td>
                <select name="gachasoku_auction_condition" id="gachasoku_auction_condition">
                    <option value="">未設定</option>
                    <?php foreach (gachasoku_get_auction_condition_options() as $cond_key => $cond_label) : ?>
                        <option value="<?php echo esc_attr($cond_key); ?>" <?php selected($fields['condition'], $cond_key); ?>><?php echo esc_html($cond_label); ?></option>
                    <?php endforeach; ?>
                </select>
                <p class="description">商品の状態を選択すると、一覧・詳細ページにバッジ表示されます。</p>
            </td>
        </tr>
        <tr>
            <th scope="row"><label for="gachasoku_auction_start_price">開始価格（円）</label></th>
            <td><input type="number" min="0" step="1" name="gachasoku_auction_start_price" id="gachasoku_auction_start_price" value="<?php echo esc_attr($fields['start_price']); ?>" /></td>
        </tr>
        <tr>
            <th scope="row"><label for="gachasoku_auction_bid_increment">最低入札単位（円）</label></th>
            <td>
                <input type="number" min="1" step="1" name="gachasoku_auction_bid_increment" id="gachasoku_auction_bid_increment" value="<?php echo esc_attr($fields['bid_increment']); ?>" />
                <p class="description">現在価格にこの額以上を上乗せした入札のみ受け付けます。</p>
            </td>
        </tr>
        <tr>
            <th scope="row"><label for="gachasoku_auction_buy_now_price">即決価格（円・任意）</label></th>
            <td>
                <input type="number" min="0" step="1" name="gachasoku_auction_buy_now_price" id="gachasoku_auction_buy_now_price" value="<?php echo esc_attr($fields['buy_now_price']); ?>" />
                <p class="description">0 のままなら即決なし。この額の入札があった時点で即落札・即終了となります。</p>
            </td>
        </tr>
        <tr>
            <th scope="row">スナイピング対策（自動延長）</th>
            <td>
                <label>
                    <input type="checkbox" name="gachasoku_auction_auto_extend_enabled" value="1" <?php checked($fields['auto_extend_enabled']); ?> />
                    終了間際の入札で終了時刻を自動延長する
                </label>
                <p class="description" style="margin-top:8px;">
                    終了
                    <input type="number" min="1" max="60" step="1" name="gachasoku_auction_auto_extend_minutes" value="<?php echo esc_attr($fields['auto_extend_minutes']); ?>" style="width:64px;" />
                    分以内に入札があった場合、同じ分数だけ終了時刻を延長します。
                </p>
            </td>
        </tr>
        <tr>
            <th scope="row"><label for="gachasoku_auction_x_account">落札後のDM送付先 Xアカウント</label></th>
            <td>
                <input type="text" name="gachasoku_auction_x_account" id="gachasoku_auction_x_account" class="regular-text" value="<?php echo esc_attr($fields['x_account']); ?>" placeholder="例: gachasoku_official" />
                <p class="description">落札者が秘密のパスワードをDMする宛先です。@ は不要（URLを貼り付けても自動で抽出します）。</p>
            </td>
        </tr>
        <tr>
            <th scope="row">商品画像</th>
            <td>
                <div class="gachasoku-auction-image">
                    <div class="gachasoku-auction-image__preview">
                        <?php if ($image_src) : ?>
                            <img src="<?php echo esc_url($image_src[0]); ?>" alt="" />
                        <?php endif; ?>
                    </div>
                    <input type="hidden" name="gachasoku_auction_image_id" id="gachasoku_auction_image_id" value="<?php echo esc_attr($fields['image_id']); ?>" />
                    <button type="button" class="button" id="gachasoku-auction-image-select">画像を選択</button>
                    <button type="button" class="button" id="gachasoku-auction-image-remove">画像を削除</button>
                </div>
            </td>
        </tr>
    </table>
    <script>
    (function ($) {
        $(function () {
            var frame;
            var $preview = $('.gachasoku-auction-image__preview');
            var $input = $('#gachasoku_auction_image_id');

            $('#gachasoku-auction-image-select').on('click', function (e) {
                e.preventDefault();
                if (frame) { frame.open(); return; }
                frame = wp.media({ title: '商品画像を選択', button: { text: 'この画像を使う' }, multiple: false });
                frame.on('select', function () {
                    var attachment = frame.state().get('selection').first().toJSON();
                    $input.val(attachment.id);
                    var url = (attachment.sizes && attachment.sizes.medium) ? attachment.sizes.medium.url : attachment.url;
                    $preview.html('<img src="' + url + '" alt="" />');
                });
                frame.open();
            });

            $('#gachasoku-auction-image-remove').on('click', function (e) {
                e.preventDefault();
                $input.val('');
                $preview.empty();
            });
        });
    })(jQuery);
    </script>
    <?php
}

add_action('admin_enqueue_scripts', 'gachasoku_auction_admin_assets');
/**
 * オークション編集画面でメディアアップローダーを読み込む。
 *
 * @param string $hook 現在の管理画面フック。
 * @return void
 */
function gachasoku_auction_admin_assets($hook) {
    if ($hook !== 'post.php' && $hook !== 'post-new.php') {
        return;
    }

    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if (!$screen || $screen->post_type !== GACHASOKU_AUCTION_POST_TYPE) {
        return;
    }

    wp_enqueue_media();
}

add_action('save_post_' . GACHASOKU_AUCTION_POST_TYPE, 'gachasoku_save_auction_meta');
/**
 * オークションのメタ情報を保存する。
 *
 * @param int $post_id 投稿ID。
 * @return void
 */
function gachasoku_save_auction_meta($post_id) {
    if (!isset($_POST['gachasoku_auction_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['gachasoku_auction_nonce'])), 'gachasoku_save_auction')) {
        return;
    }

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    $start = isset($_POST['gachasoku_auction_start']) ? gachasoku_datetime_local_to_mysql(sanitize_text_field(wp_unslash($_POST['gachasoku_auction_start']))) : '';
    $end = isset($_POST['gachasoku_auction_end']) ? gachasoku_datetime_local_to_mysql(sanitize_text_field(wp_unslash($_POST['gachasoku_auction_end']))) : '';
    $start_price = isset($_POST['gachasoku_auction_start_price']) ? max(0, intval($_POST['gachasoku_auction_start_price'])) : 0;
    $bid_increment = isset($_POST['gachasoku_auction_bid_increment']) ? intval($_POST['gachasoku_auction_bid_increment']) : 1;
    $buy_now_price = isset($_POST['gachasoku_auction_buy_now_price']) ? max(0, intval($_POST['gachasoku_auction_buy_now_price'])) : 0;
    $image_id = isset($_POST['gachasoku_auction_image_id']) ? intval($_POST['gachasoku_auction_image_id']) : 0;
    $x_account = isset($_POST['gachasoku_auction_x_account']) ? gachasoku_sanitize_x_account(wp_unslash($_POST['gachasoku_auction_x_account'])) : '';
    $auto_extend_enabled = isset($_POST['gachasoku_auction_auto_extend_enabled']) ? '1' : '';
    $auto_extend_minutes = isset($_POST['gachasoku_auction_auto_extend_minutes']) ? intval($_POST['gachasoku_auction_auto_extend_minutes']) : 5;

    $condition = isset($_POST['gachasoku_auction_condition']) ? sanitize_key($_POST['gachasoku_auction_condition']) : '';
    if (!array_key_exists($condition, gachasoku_get_auction_condition_options())) {
        $condition = '';
    }

    if ($bid_increment < 1) {
        $bid_increment = 1;
    }
    if ($auto_extend_minutes < 1) {
        $auto_extend_minutes = 1;
    } elseif ($auto_extend_minutes > 60) {
        $auto_extend_minutes = 60;
    }

    update_post_meta($post_id, '_gachasoku_auction_start', $start);
    update_post_meta($post_id, '_gachasoku_auction_end', $end);
    update_post_meta($post_id, '_gachasoku_auction_start_price', $start_price);
    update_post_meta($post_id, '_gachasoku_auction_bid_increment', $bid_increment);
    update_post_meta($post_id, '_gachasoku_auction_buy_now_price', $buy_now_price);
    update_post_meta($post_id, '_gachasoku_auction_image_id', $image_id);
    update_post_meta($post_id, '_gachasoku_auction_auto_extend_minutes', $auto_extend_minutes);

    if ($x_account !== '') {
        update_post_meta($post_id, '_gachasoku_auction_x_account', $x_account);
    } else {
        delete_post_meta($post_id, '_gachasoku_auction_x_account');
    }

    if ($auto_extend_enabled) {
        update_post_meta($post_id, '_gachasoku_auction_auto_extend_enabled', '1');
    } else {
        delete_post_meta($post_id, '_gachasoku_auction_auto_extend_enabled');
    }

    if ($condition !== '') {
        update_post_meta($post_id, '_gachasoku_auction_condition', $condition);
    } else {
        delete_post_meta($post_id, '_gachasoku_auction_condition');
    }
}

/* =========================================================================
 * Phase 2-6 共通: ステータス・価格ヘルパー
 * ====================================================================== */

/**
 * オークションのステータスを返す。
 *
 * @param int $auction_id 投稿ID。
 * @return string 'scheduled'（開始前） | 'open'（開催中） | 'ended'（終了）
 */
function gachasoku_get_auction_status($auction_id) {
    $fields = gachasoku_get_auction_fields($auction_id);
    $now = current_time('timestamp');

    $start = $fields['start_datetime'] ? strtotime($fields['start_datetime']) : 0;
    $end = $fields['end_datetime'] ? strtotime($fields['end_datetime']) : 0;

    if ($start && $now < $start) {
        return 'scheduled';
    }
    if ($end && $now >= $end) {
        return 'ended';
    }
    return 'open';
}

/**
 * 現在の最高入札を1件返す（無ければ null）。
 *
 * @param int $auction_id 投稿ID。
 * @return array|null
 */
function gachasoku_get_auction_highest_bid($auction_id) {
    global $wpdb;
    $table = gachasoku_get_auction_bids_table();

    $row = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM {$table} WHERE auction_id = %d ORDER BY amount DESC, id ASC LIMIT 1",
            intval($auction_id)
        ),
        ARRAY_A
    );

    return $row ?: null;
}

/**
 * 現在価格（最高入札額。入札が無ければ開始価格）を返す。
 *
 * @param int $auction_id 投稿ID。
 * @return int
 */
function gachasoku_get_auction_current_price($auction_id) {
    $highest = gachasoku_get_auction_highest_bid($auction_id);
    if ($highest) {
        return (int) $highest['amount'];
    }
    $fields = gachasoku_get_auction_fields($auction_id);
    return (int) $fields['start_price'];
}

/**
 * 次に入札可能な最低額を返す。
 *
 * @param int $auction_id 投稿ID。
 * @return int
 */
function gachasoku_get_auction_min_next_bid($auction_id) {
    $fields = gachasoku_get_auction_fields($auction_id);
    $highest = gachasoku_get_auction_highest_bid($auction_id);

    if (!$highest) {
        return (int) $fields['start_price'];
    }

    return (int) $highest['amount'] + (int) $fields['bid_increment'];
}

/**
 * 入札件数を返す。
 *
 * @param int $auction_id 投稿ID。
 * @return int
 */
function gachasoku_get_auction_bid_count($auction_id) {
    global $wpdb;
    $table = gachasoku_get_auction_bids_table();
    return (int) $wpdb->get_var(
        $wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE auction_id = %d", intval($auction_id))
    );
}

/**
 * 入札履歴を新しい順に取得する。
 *
 * @param int $auction_id 投稿ID。
 * @param int $limit      取得件数。
 * @return array
 */
function gachasoku_get_auction_bids($auction_id, $limit = 20) {
    global $wpdb;
    $table = gachasoku_get_auction_bids_table();
    $limit = max(1, intval($limit));

    return $wpdb->get_results(
        $wpdb->prepare(
            "SELECT * FROM {$table} WHERE auction_id = %d ORDER BY amount DESC, id ASC LIMIT %d",
            intval($auction_id),
            $limit
        ),
        ARRAY_A
    );
}

/**
 * 入札者名を公開用にマスクする（個人情報保護）。
 *
 * @param string $name 会員名。
 * @return string 例: "山***"
 */
function gachasoku_mask_member_name($name) {
    $name = trim((string) $name);
    if ($name === '') {
        return '入札者';
    }
    $first = mb_substr($name, 0, 1);
    return $first . '***';
}

/* =========================================================================
 * Phase 2: 入札処理（同時入札の競合対策つき）
 * ====================================================================== */

add_action('init', 'gachasoku_handle_auction_bid', 20);
/**
 * 入札フォームの送信を処理する（PRG: 処理後にリダイレクト）。
 *
 * @return void
 */
function gachasoku_handle_auction_bid() {
    if (empty($_POST['gachasoku_auction_bid_submit'])) {
        return;
    }

    $auction_id = isset($_POST['gachasoku_auction_id']) ? intval($_POST['gachasoku_auction_id']) : 0;

    $nonce = isset($_POST['gachasoku_auction_bid_nonce']) ? sanitize_text_field(wp_unslash($_POST['gachasoku_auction_bid_nonce'])) : '';
    if (!$auction_id || !wp_verify_nonce($nonce, 'gachasoku_auction_bid_' . $auction_id)) {
        gachasoku_auction_redirect_with_flash($auction_id, 'error', 'フォームの有効期限が切れました。ページを再読み込みして再度お試しください。');
    }

    if (!gachasoku_is_member_logged_in()) {
        gachasoku_auction_redirect_with_flash($auction_id, 'error', '入札にはログインが必要です。');
    }

    $member_id = gachasoku_get_current_member_id();
    if (gachasoku_get_member_status($member_id) !== GACHASOKU_MEMBER_STATUS_ACTIVE) {
        gachasoku_auction_redirect_with_flash($auction_id, 'error', '現在のステータスでは入札できません。');
    }

    $is_buy_now = !empty($_POST['gachasoku_auction_buy_now']);
    $amount = isset($_POST['gachasoku_auction_amount']) ? intval($_POST['gachasoku_auction_amount']) : 0;

    if ($is_buy_now) {
        $fields = gachasoku_get_auction_fields($auction_id);
        if ($fields['buy_now_price'] > 0) {
            $amount = (int) $fields['buy_now_price'];
        }
    }

    $result = gachasoku_place_auction_bid($auction_id, $member_id, $amount, $is_buy_now);

    if (is_wp_error($result)) {
        gachasoku_auction_redirect_with_flash($auction_id, 'error', $result->get_error_message());
    }

    if (!empty($result['won_buy_now'])) {
        gachasoku_auction_redirect_with_flash($auction_id, 'success', '即決価格で落札しました！マイページで秘密のパスワードをご確認ください。');
    }

    gachasoku_auction_redirect_with_flash($auction_id, 'success', '入札を受け付けました。現在の最高額入札者です。');
}

/**
 * 入札を実行する。GET_LOCK で同一オークションの入札を直列化し、二重落札を防ぐ。
 *
 * @param int  $auction_id 投稿ID。
 * @param int  $member_id  会員ID。
 * @param int  $amount     入札額。
 * @param bool $is_buy_now 即決入札か。
 * @return array|WP_Error 成功時は ['amount'=>int,'won_buy_now'=>bool]。
 */
function gachasoku_place_auction_bid($auction_id, $member_id, $amount, $is_buy_now = false) {
    global $wpdb;

    if (get_post_type($auction_id) !== GACHASOKU_AUCTION_POST_TYPE || get_post_status($auction_id) !== 'publish') {
        return new WP_Error('not_found', 'オークションが見つかりませんでした。');
    }

    $amount = intval($amount);
    if ($amount <= 0) {
        return new WP_Error('invalid_amount', '入札額が正しくありません。');
    }

    $bids_table = gachasoku_get_auction_bids_table();
    $lock_name = 'gachasoku_auction_' . intval($auction_id);

    // オークション単位の排他ロック（最大5秒待機）。
    $locked = (int) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, %d)', $lock_name, 5));
    if ($locked !== 1) {
        return new WP_Error('busy', '只今アクセスが集中しています。少し時間をおいて再度お試しください。');
    }

    try {
        // ロック取得後に最新状態で再検証する（クライアント値は信用しない）。
        if (gachasoku_get_auction_status($auction_id) !== 'open') {
            return new WP_Error('closed', 'このオークションは現在入札を受け付けていません。');
        }

        $fields = gachasoku_get_auction_fields($auction_id);
        $highest = gachasoku_get_auction_highest_bid($auction_id);

        if (!$highest) {
            $min = (int) $fields['start_price'];
            if ($amount < $min) {
                return new WP_Error('too_low', '入札額は開始価格（' . number_format($min) . '円）以上にしてください。');
            }
        } else {
            $min = (int) $highest['amount'] + (int) $fields['bid_increment'];
            if ($amount < $min) {
                return new WP_Error('too_low', '入札額は ' . number_format($min) . '円 以上にしてください。');
            }
            if ((int) $highest['member_id'] === intval($member_id)) {
                return new WP_Error('already_top', 'あなたは既に最高額入札者です。');
            }
        }

        // 即決価格の判定。
        $buy_now = (int) $fields['buy_now_price'];
        $won_buy_now = ($buy_now > 0 && $amount >= $buy_now);

        $now = current_time('mysql');

        // 既存の有効入札を outbid に更新してから新規入札を有効として挿入する。
        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$bids_table} SET status = 'outbid' WHERE auction_id = %d AND status = 'active'",
                intval($auction_id)
            )
        );

        $inserted = $wpdb->insert(
            $bids_table,
            [
                'auction_id' => intval($auction_id),
                'member_id' => intval($member_id),
                'amount' => $amount,
                'status' => 'active',
                'ip_address' => gachasoku_auction_client_ip(),
                'user_agent' => gachasoku_auction_user_agent(),
                'created_at' => $now,
            ],
            ['%d', '%d', '%d', '%s', '%s', '%s', '%s']
        );

        if ($inserted === false) {
            return new WP_Error('db_error', '入札の保存に失敗しました。時間をおいて再度お試しください。');
        }

        if ($won_buy_now) {
            // 即決成立。ロック内で落札確定まで行う。
            gachasoku_close_auction($auction_id, true);
            return ['amount' => $amount, 'won_buy_now' => true];
        }

        // スナイピング対策（自動延長）。
        if ($fields['auto_extend_enabled'] && $fields['end_datetime']) {
            $end_ts = strtotime($fields['end_datetime']);
            $now_ts = current_time('timestamp');
            $window = (int) $fields['auto_extend_minutes'] * MINUTE_IN_SECONDS;
            if ($end_ts && ($end_ts - $now_ts) <= $window) {
                $new_end = date_i18n('Y-m-d H:i:s', $now_ts + $window, false);
                update_post_meta($auction_id, '_gachasoku_auction_end', $new_end);
            }
        }

        return ['amount' => $amount, 'won_buy_now' => false];
    } finally {
        $wpdb->query($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock_name));
    }
}

/**
 * フラッシュメッセージを保存してオークション詳細へリダイレクトする（処理終了）。
 *
 * @param int    $auction_id 投稿ID。
 * @param string $type       'success' | 'error'。
 * @param string $message    表示文。
 * @return void
 */
function gachasoku_auction_redirect_with_flash($auction_id, $type, $message) {
    $member_id = gachasoku_get_current_member_id();
    if ($member_id) {
        set_transient('gachasoku_auction_flash_' . $member_id, ['type' => $type, 'message' => $message], 60);
    }

    $url = $auction_id ? get_permalink($auction_id) : home_url('/');
    if (!$url) {
        $url = home_url('/');
    }

    wp_safe_redirect($url);
    exit;
}

/**
 * 保存済みフラッシュメッセージを取り出して削除する。
 *
 * @return array|null ['type'=>string,'message'=>string]
 */
function gachasoku_auction_pull_flash() {
    $member_id = gachasoku_get_current_member_id();
    if (!$member_id) {
        return null;
    }
    $key = 'gachasoku_auction_flash_' . $member_id;
    $flash = get_transient($key);
    if ($flash) {
        delete_transient($key);
        return $flash;
    }
    return null;
}

/**
 * クライアントIPを取得する（保存用・短く制限）。
 *
 * @return string
 */
function gachasoku_auction_client_ip() {
    $ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '';
    return substr($ip, 0, 100);
}

/**
 * User-Agent を取得する（保存用・短く制限）。
 *
 * @return string
 */
function gachasoku_auction_user_agent() {
    $ua = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : '';
    return substr($ua, 0, 191);
}

/* =========================================================================
 * Phase 3: 落札確定・トークン生成・暗号化・自動締切
 * ====================================================================== */

/**
 * トークン暗号化用のキー（32バイト）を導出する。
 *
 * wp-config の AUTH_KEY / AUTH_SALT を素材にするため、サイトごとに固有。
 *
 * @return string バイナリ32バイト。
 */
function gachasoku_auction_encryption_key() {
    $material = (defined('AUTH_KEY') ? AUTH_KEY : '')
        . '|' . (defined('AUTH_SALT') ? AUTH_SALT : '')
        . '|' . (defined('SECURE_AUTH_KEY') ? SECURE_AUTH_KEY : '')
        . '|gachasoku_auction_v1';
    return hash('sha256', $material, true);
}

/**
 * 平文を暗号化して保存用文字列にする（AES-256-CBC + HMAC）。
 *
 * @param string $plaintext 平文。
 * @return string 'enc:...'（openssl利用時）または 'plain:...'（フォールバック）。
 */
function gachasoku_auction_encrypt($plaintext) {
    if (!function_exists('openssl_encrypt') || !function_exists('random_bytes')) {
        return 'plain:' . base64_encode($plaintext);
    }

    $key = gachasoku_auction_encryption_key();
    $iv = random_bytes(16);
    $cipher = openssl_encrypt($plaintext, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
    if ($cipher === false) {
        return 'plain:' . base64_encode($plaintext);
    }

    $mac = hash_hmac('sha256', $iv . $cipher, $key, true);
    return 'enc:' . base64_encode($iv . $mac . $cipher);
}

/**
 * 保存用文字列を復号する。改ざん検知に失敗した場合は空文字を返す。
 *
 * @param string $stored 保存値。
 * @return string 平文（失敗時は空文字）。
 */
function gachasoku_auction_decrypt($stored) {
    $stored = (string) $stored;

    if (strpos($stored, 'plain:') === 0) {
        return (string) base64_decode(substr($stored, 6));
    }
    if (strpos($stored, 'enc:') !== 0) {
        return '';
    }

    $raw = base64_decode(substr($stored, 4), true);
    if ($raw === false || strlen($raw) < 48) {
        return '';
    }

    $iv = substr($raw, 0, 16);
    $mac = substr($raw, 16, 32);
    $cipher = substr($raw, 48);
    $key = gachasoku_auction_encryption_key();

    $calc = hash_hmac('sha256', $iv . $cipher, $key, true);
    if (!hash_equals($calc, $mac)) {
        return '';
    }

    $plain = openssl_decrypt($cipher, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
    return $plain === false ? '' : $plain;
}

/**
 * 秘密のパスワード（トークン）を生成する。
 *
 * 紛らわしい文字（0/O, 1/I/L など）を除いた英数字を CSPRNG で生成し、
 * 読みやすいように 5 文字ごとに区切る。例: GA-XXXXX-XXXXX-XXXXX-XXXXX
 *
 * @return string
 */
function gachasoku_generate_auction_token() {
    $alphabet = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
    $max = strlen($alphabet) - 1;
    $length = 20;

    $body = '';
    for ($i = 0; $i < $length; $i++) {
        $body .= $alphabet[random_int(0, $max)];
    }

    return 'GA-' . implode('-', str_split($body, 5));
}

/**
 * オークションを締め切り、最高額入札者を落札者として確定する（冪等）。
 *
 * 既に確定済みなら何もしない。入札ゼロの場合は落札者なしで締切記録のみ残す。
 *
 * @param int  $auction_id 投稿ID。
 * @param bool $inside_lock 既に GET_LOCK 取得済みの場合 true。
 * @return bool 処理が完了したか。
 */
function gachasoku_close_auction($auction_id, $inside_lock = false) {
    global $wpdb;

    $auction_id = intval($auction_id);
    if (get_post_type($auction_id) !== GACHASOKU_AUCTION_POST_TYPE) {
        return false;
    }

    $winners_table = gachasoku_get_auction_winners_table();
    $lock_name = 'gachasoku_auction_' . $auction_id;
    $acquired_lock = false;

    if (!$inside_lock) {
        $locked = (int) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, %d)', $lock_name, 5));
        if ($locked !== 1) {
            return false;
        }
        $acquired_lock = true;
    }

    try {
        // 既に落札者レコードがあれば確定済み。
        $existing = $wpdb->get_var(
            $wpdb->prepare("SELECT id FROM {$winners_table} WHERE auction_id = %d", $auction_id)
        );
        if ($existing) {
            return true;
        }

        $highest = gachasoku_get_auction_highest_bid($auction_id);

        if (!$highest) {
            // 入札なしで終了。再処理を避けるため締切時刻を記録する。
            update_post_meta($auction_id, '_gachasoku_auction_closed', current_time('mysql'));
            return true;
        }

        $token = gachasoku_generate_auction_token();
        $now = current_time('mysql');

        $wpdb->insert(
            $winners_table,
            [
                'auction_id' => $auction_id,
                'member_id' => intval($highest['member_id']),
                'winning_amount' => intval($highest['amount']),
                'token' => gachasoku_auction_encrypt($token),
                'token_issued_at' => $now,
                'dm_status' => 'pending',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            ['%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s']
        );

        update_post_meta($auction_id, '_gachasoku_auction_closed', $now);

        return true;
    } finally {
        if ($acquired_lock) {
            $wpdb->query($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock_name));
        }
    }
}

/**
 * 落札者レコードを取得する。
 *
 * @param int $auction_id 投稿ID。
 * @return array|null
 */
function gachasoku_get_auction_winner($auction_id) {
    global $wpdb;
    $table = gachasoku_get_auction_winners_table();
    $row = $wpdb->get_row(
        $wpdb->prepare("SELECT * FROM {$table} WHERE auction_id = %d", intval($auction_id)),
        ARRAY_A
    );
    return $row ?: null;
}

/* ---- 自動締切（WP-Cron + 閲覧時の補助処理） ---- */

add_filter('cron_schedules', 'gachasoku_auction_cron_schedule');
/**
 * 5分間隔のcronスケジュールを追加する。
 *
 * @param array $schedules 既存スケジュール。
 * @return array
 */
function gachasoku_auction_cron_schedule($schedules) {
    if (!isset($schedules['gachasoku_five_minutes'])) {
        $schedules['gachasoku_five_minutes'] = [
            'interval' => 5 * MINUTE_IN_SECONDS,
            'display' => '5分ごと（ガチャ速オークション）',
        ];
    }
    return $schedules;
}

add_action('init', 'gachasoku_auction_schedule_cron', 12);
/**
 * 締切処理のcronイベントを登録する。
 *
 * @return void
 */
function gachasoku_auction_schedule_cron() {
    if (!wp_next_scheduled('gachasoku_auction_close_event')) {
        wp_schedule_event(time() + MINUTE_IN_SECONDS, 'gachasoku_five_minutes', 'gachasoku_auction_close_event');
    }
}

add_action('gachasoku_auction_close_event', 'gachasoku_auction_close_due');
/**
 * 終了時刻を過ぎたのに未確定のオークションをまとめて締め切る。
 *
 * @return void
 */
function gachasoku_auction_close_due() {
    $now = current_time('mysql');

    $query = new WP_Query([
        'post_type' => GACHASOKU_AUCTION_POST_TYPE,
        'post_status' => 'publish',
        'posts_per_page' => 50,
        'no_found_rows' => true,
        'fields' => 'ids',
        'meta_query' => [
            'relation' => 'AND',
            [
                'key' => '_gachasoku_auction_closed',
                'compare' => 'NOT EXISTS',
            ],
            [
                'key' => '_gachasoku_auction_end',
                'value' => $now,
                'compare' => '<=',
                'type' => 'DATETIME',
            ],
        ],
    ]);

    if (empty($query->posts)) {
        return;
    }

    foreach ($query->posts as $auction_id) {
        gachasoku_close_auction($auction_id);
    }
}

/**
 * 単一オークション閲覧時に、終了済みなら即座に締め切る補助処理。
 *
 * cron を待たずに落札者へトークンを表示できるようにする。
 *
 * @param int $auction_id 投稿ID。
 * @return void
 */
function gachasoku_auction_maybe_close_single($auction_id) {
    if (gachasoku_get_auction_status($auction_id) !== 'ended') {
        return;
    }
    if (get_post_meta($auction_id, '_gachasoku_auction_closed', true)) {
        return;
    }
    gachasoku_close_auction($auction_id);
}

/* =========================================================================
 * フロント表示: 単一オークション / 一覧
 * ====================================================================== */

add_filter('the_content', 'gachasoku_auction_single_content', 20);
/**
 * 単一オークションページ本文にオークションUIを追加する。
 *
 * @param string $content 本文。
 * @return string
 */
function gachasoku_auction_single_content($content) {
    if (!is_singular(GACHASOKU_AUCTION_POST_TYPE) || !in_the_loop() || !is_main_query()) {
        return $content;
    }

    $auction_id = get_the_ID();
    gachasoku_auction_maybe_close_single($auction_id);

    return $content . gachasoku_render_auction_detail($auction_id);
}

/**
 * オークション詳細UI（価格・入札フォーム・入札履歴）を描画する。
 *
 * @param int $auction_id 投稿ID。
 * @return string
 */
function gachasoku_render_auction_detail($auction_id) {
    $fields = gachasoku_get_auction_fields($auction_id);
    $status = gachasoku_get_auction_status($auction_id);
    $current_price = gachasoku_get_auction_current_price($auction_id);
    $min_next = gachasoku_get_auction_min_next_bid($auction_id);
    $bid_count = gachasoku_get_auction_bid_count($auction_id);
    $bids = gachasoku_get_auction_bids($auction_id, 10);
    $winner = gachasoku_get_auction_winner($auction_id);
    $flash = gachasoku_auction_pull_flash();

    $status_labels = [
        'scheduled' => '開催前',
        'open' => '開催中',
        'ended' => '終了',
    ];

    ob_start();
    ?>
    <div class="gachasoku-auction">
        <?php if ($flash) : ?>
            <div class="gachasoku-auction__flash gachasoku-auction__flash--<?php echo esc_attr($flash['type']); ?>">
                <?php echo esc_html($flash['message']); ?>
            </div>
        <?php endif; ?>

        <div class="gachasoku-auction__badges">
            <span class="gachasoku-auction__status gachasoku-auction__status--<?php echo esc_attr($status); ?>">
                <?php echo esc_html($status_labels[$status]); ?>
            </span>
            <?php $condition_label = gachasoku_get_auction_condition_label($fields['condition']); ?>
            <?php if ($condition_label !== '') : ?>
                <span class="gachasoku-auction__condition gachasoku-auction__condition--<?php echo esc_attr($fields['condition']); ?>"><?php echo esc_html($condition_label); ?></span>
            <?php endif; ?>
        </div>

        <?php if ($fields['image_id']) :
            $img = wp_get_attachment_image_src($fields['image_id'], 'large');
            if ($img) : ?>
                <div class="gachasoku-auction__image"><img src="<?php echo esc_url($img[0]); ?>" alt="" /></div>
            <?php endif;
        endif; ?>

        <dl class="gachasoku-auction__info">
            <div>
                <dt>現在価格</dt>
                <dd class="gachasoku-auction__price"><?php echo esc_html(number_format($current_price)); ?> 円</dd>
            </div>
            <div>
                <dt>入札件数</dt>
                <dd><?php echo esc_html(number_format($bid_count)); ?> 件</dd>
            </div>
            <?php if ($fields['buy_now_price'] > 0 && $status !== 'ended') : ?>
                <div>
                    <dt>即決価格</dt>
                    <dd><?php echo esc_html(number_format($fields['buy_now_price'])); ?> 円</dd>
                </div>
            <?php endif; ?>
            <?php if ($fields['end_datetime']) : ?>
                <div>
                    <dt>終了日時</dt>
                    <dd><?php echo esc_html(gachasoku_format_datetime($fields['end_datetime'])); ?></dd>
                </div>
            <?php endif; ?>
        </dl>

        <?php if ($status === 'open') : ?>
            <?php echo gachasoku_render_auction_bid_form($auction_id, $min_next, $fields); ?>
        <?php elseif ($status === 'scheduled') : ?>
            <p class="gachasoku-auction__notice">このオークションはまだ開始していません。<?php echo esc_html(gachasoku_format_datetime($fields['start_datetime'])); ?> に開始予定です。</p>
        <?php else : ?>
            <?php if ($winner) : ?>
                <p class="gachasoku-auction__notice gachasoku-auction__notice--ended">このオークションは終了しました。落札価格: <?php echo esc_html(number_format($winner['winning_amount'])); ?> 円</p>
                <?php
                $current_member_id = gachasoku_get_current_member_id();
                if ($current_member_id && intval($winner['member_id']) === $current_member_id) :
                ?>
                    <p class="gachasoku-auction__notice gachasoku-auction__notice--win">🎉 あなたが落札しました！マイページで秘密のパスワードをご確認ください。</p>
                <?php endif; ?>
            <?php else : ?>
                <p class="gachasoku-auction__notice gachasoku-auction__notice--ended">このオークションは終了しました（入札はありませんでした）。</p>
            <?php endif; ?>
        <?php endif; ?>

        <?php if (!empty($bids)) : ?>
            <div class="gachasoku-auction__history">
                <h3>入札履歴（上位<?php echo esc_html(count($bids)); ?>件）</h3>
                <table class="gachasoku-auction__history-table">
                    <thead><tr><th>入札者</th><th>金額</th><th>日時</th></tr></thead>
                    <tbody>
                    <?php foreach ($bids as $bid) :
                        $member = gachasoku_get_member_by_id($bid['member_id']);
                        $name = $member ? gachasoku_mask_member_name($member['name']) : '入札者';
                    ?>
                        <tr>
                            <td><?php echo esc_html($name); ?></td>
                            <td><?php echo esc_html(number_format($bid['amount'])); ?> 円</td>
                            <td><?php echo esc_html(gachasoku_format_datetime($bid['created_at'])); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * 入札フォームを描画する（ログイン＆有効会員のみ）。
 *
 * @param int   $auction_id 投稿ID。
 * @param int   $min_next   最低入札額。
 * @param array $fields     出品メタ。
 * @return string
 */
function gachasoku_render_auction_bid_form($auction_id, $min_next, $fields) {
    if (!gachasoku_is_member_logged_in()) {
        $login_url = function_exists('gachasoku_get_membership_page_url') ? gachasoku_get_membership_page_url('member-login') : wp_login_url();
        return '<p class="gachasoku-auction__notice">入札するには<a href="' . esc_url($login_url) . '">ログイン</a>してください。</p>';
    }

    $member_id = gachasoku_get_current_member_id();
    if (gachasoku_get_member_status($member_id) !== GACHASOKU_MEMBER_STATUS_ACTIVE) {
        return '<p class="gachasoku-auction__notice">現在のステータスでは入札できません。</p>';
    }

    ob_start();
    ?>
    <form method="post" class="gachasoku-auction__bid-form">
        <?php wp_nonce_field('gachasoku_auction_bid_' . $auction_id, 'gachasoku_auction_bid_nonce'); ?>
        <input type="hidden" name="gachasoku_auction_id" value="<?php echo esc_attr($auction_id); ?>" />
        <input type="hidden" name="gachasoku_auction_bid_submit" value="1" />
        <div class="gachasoku-auction__bid-field">
            <label for="gachasoku_auction_amount">入札額（円）</label>
            <input type="number" name="gachasoku_auction_amount" id="gachasoku_auction_amount"
                   min="<?php echo esc_attr($min_next); ?>" step="<?php echo esc_attr(max(1, $fields['bid_increment'])); ?>"
                   value="<?php echo esc_attr($min_next); ?>" required />
            <p class="gachasoku-auction__hint">最低入札額: <?php echo esc_html(number_format($min_next)); ?> 円</p>
        </div>
        <div class="gachasoku-auction__bid-actions">
            <button type="submit" class="gachasoku-button">入札する</button>
        </div>
    </form>
    <?php if ($fields['buy_now_price'] > 0) : ?>
        <form method="post" class="gachasoku-auction__buy-now-form" onsubmit="return confirm('即決価格 <?php echo esc_js(number_format($fields['buy_now_price'])); ?>円で落札します。よろしいですか？');">
            <?php wp_nonce_field('gachasoku_auction_bid_' . $auction_id, 'gachasoku_auction_bid_nonce'); ?>
            <input type="hidden" name="gachasoku_auction_id" value="<?php echo esc_attr($auction_id); ?>" />
            <input type="hidden" name="gachasoku_auction_bid_submit" value="1" />
            <input type="hidden" name="gachasoku_auction_buy_now" value="1" />
            <button type="submit" class="gachasoku-button gachasoku-button--buy-now">即決価格（<?php echo esc_html(number_format($fields['buy_now_price'])); ?>円）で落札</button>
        </form>
    <?php endif; ?>
    <?php
    return ob_get_clean();
}

add_shortcode('gachasoku_auctions', 'gachasoku_auctions_shortcode');
/**
 * オークション一覧ショートコード。
 *
 * @param array $atts 属性。status="open|scheduled|ended"。
 * @return string
 */
function gachasoku_auctions_shortcode($atts = []) {
    $atts = shortcode_atts(['status' => ''], $atts, 'gachasoku_auctions');

    $query = new WP_Query([
        'post_type' => GACHASOKU_AUCTION_POST_TYPE,
        'post_status' => 'publish',
        'posts_per_page' => 30,
        'no_found_rows' => true,
        'meta_key' => '_gachasoku_auction_end',
        'orderby' => 'meta_value',
        'order' => 'ASC',
    ]);

    if (!$query->have_posts()) {
        return '<p class="gachasoku-auction__notice">現在出品中のオークションはありません。</p>';
    }

    $filter = sanitize_key($atts['status']);

    ob_start();
    echo '<div class="gachasoku-auction-list">';
    while ($query->have_posts()) {
        $query->the_post();
        $auction_id = get_the_ID();
        $status = gachasoku_get_auction_status($auction_id);
        if ($filter && $filter !== $status) {
            continue;
        }
        $fields = gachasoku_get_auction_fields($auction_id);
        $price = gachasoku_get_auction_current_price($auction_id);
        $status_labels = ['scheduled' => '開催前', 'open' => '開催中', 'ended' => '終了'];
        // 出品画像 → 無ければアイキャッチ → それも無ければプレースホルダー。
        $img = $fields['image_id'] ? wp_get_attachment_image_src($fields['image_id'], 'medium') : null;
        if (!$img && has_post_thumbnail($auction_id)) {
            $img = wp_get_attachment_image_src(get_post_thumbnail_id($auction_id), 'medium');
        }
        ?>
        <?php $condition_label = gachasoku_get_auction_condition_label($fields['condition']); ?>
        <a class="gachasoku-auction-card gachasoku-auction-card--<?php echo esc_attr($status); ?>" href="<?php the_permalink(); ?>">
            <span class="gachasoku-auction-card__badge"><?php echo esc_html($status_labels[$status]); ?></span>
            <?php if ($condition_label !== '') : ?>
                <span class="gachasoku-auction-card__condition gachasoku-auction__condition--<?php echo esc_attr($fields['condition']); ?>"><?php echo esc_html($condition_label); ?></span>
            <?php endif; ?>
            <?php if ($img) : ?>
                <span class="gachasoku-auction-card__image"><img src="<?php echo esc_url($img[0]); ?>" alt="<?php echo esc_attr(get_the_title()); ?>" loading="lazy" /></span>
            <?php else : ?>
                <span class="gachasoku-auction-card__image gachasoku-auction-card__image--placeholder">🔨</span>
            <?php endif; ?>
            <span class="gachasoku-auction-card__title"><?php echo esc_html(get_the_title()); ?></span>
            <span class="gachasoku-auction-card__price">現在価格 <?php echo esc_html(number_format($price)); ?> 円</span>
            <?php if ($fields['end_datetime']) : ?><span class="gachasoku-auction-card__end">終了 <?php echo esc_html(gachasoku_format_datetime($fields['end_datetime'])); ?></span><?php endif; ?>
        </a>
        <?php
    }
    echo '</div>';
    wp_reset_postdata();

    return ob_get_clean();
}

/* =========================================================================
 * Phase 4: マイページ（落札結果・秘密のパスワード・X DM導線）
 * ====================================================================== */

add_shortcode('gachasoku_auction_dashboard', 'gachasoku_auction_dashboard_shortcode');
/**
 * 落札結果セクション単体のショートコード。
 *
 * @return string
 */
function gachasoku_auction_dashboard_shortcode() {
    if (!gachasoku_is_member_logged_in()) {
        return '<p class="gachasoku-membership__notice">落札結果を見るにはログインしてください。</p>';
    }
    return gachasoku_render_member_auction_section(gachasoku_get_current_member());
}

/**
 * マイページ用「落札したオークション」セクションを描画する。
 *
 * 秘密のパスワード（トークン）は落札者本人のセッションでのみ復号・表示する。
 *
 * @param array $member 会員レコード。
 * @return string
 */
function gachasoku_render_member_auction_section($member) {
    if (empty($member['id'])) {
        return '';
    }

    global $wpdb;
    $winners_table = gachasoku_get_auction_winners_table();
    $member_id = intval($member['id']);

    $rows = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT * FROM {$winners_table} WHERE member_id = %d ORDER BY created_at DESC",
            $member_id
        ),
        ARRAY_A
    );

    ob_start();
    ?>
    <section class="gachasoku-dashboard__section gachasoku-auction-won">
        <h2 class="gachasoku-dashboard__title">オークション</h2>

        <?php
        $archive_url = get_post_type_archive_link(GACHASOKU_AUCTION_POST_TYPE);
        if (!$archive_url) {
            $archive_url = home_url('/auction/');
        }
        ?>
        <a class="gachasoku-auction-cta" href="<?php echo esc_url($archive_url); ?>">
            <span class="gachasoku-auction-cta__icon">🔨</span>
            <span class="gachasoku-auction-cta__text">
                <span class="gachasoku-auction-cta__title">オークションに参加する</span>
                <span class="gachasoku-auction-cta__sub">開催中のオークション一覧を見る</span>
            </span>
            <span class="gachasoku-auction-cta__arrow">→</span>
        </a>

        <h3 class="gachasoku-auction-won__heading">落札したオークション</h3>
        <?php if (empty($rows)) : ?>
            <p class="gachasoku-dashboard__empty">落札したオークションはまだありません。</p>
        <?php else : ?>
            <?php foreach ($rows as $row) :
                // 念のため本人確認を二重化（取得条件で絞っているが復号前に再確認）。
                if (intval($row['member_id']) !== $member_id) {
                    continue;
                }
                $auction_id = intval($row['auction_id']);
                $title = get_the_title($auction_id);
                $fields = gachasoku_get_auction_fields($auction_id);
                $token = gachasoku_auction_decrypt($row['token']);
                $x_account = $fields['x_account'];
                $dm_status = $row['dm_status'];
                $status_text = [
                    'pending' => 'パスワード未送信',
                    'submitted' => '確認待ち',
                    'confirmed' => '当選確定',
                ];
                $label = isset($status_text[$dm_status]) ? $status_text[$dm_status] : $dm_status;
            ?>
                <div class="gachasoku-auction-won__item">
                    <h3 class="gachasoku-auction-won__title"><?php echo esc_html($title); ?></h3>
                    <p class="gachasoku-auction-won__amount">落札価格: <strong><?php echo esc_html(number_format($row['winning_amount'])); ?> 円</strong></p>
                    <p class="gachasoku-auction-won__state gachasoku-auction-won__state--<?php echo esc_attr($dm_status); ?>">状態: <?php echo esc_html($label); ?></p>

                    <?php if ($dm_status === 'confirmed') : ?>
                        <p class="gachasoku-auction-won__done">✅ 当選が確定しました。運営からのご連絡をお待ちください。</p>
                    <?php elseif ($token !== '') : ?>
                        <div class="gachasoku-auction-won__token-box">
                            <p class="gachasoku-auction-won__lead">下記の<strong>秘密のパスワード</strong>を、指定のXアカウントへDMしてください。</p>
                            <div class="gachasoku-auction-won__token">
                                <code id="gachasoku-token-<?php echo esc_attr($row['id']); ?>"><?php echo esc_html($token); ?></code>
                                <button type="button" class="gachasoku-button gachasoku-button--small gachasoku-auction-copy" data-token-target="gachasoku-token-<?php echo esc_attr($row['id']); ?>">コピー</button>
                            </div>
                            <?php if ($x_account !== '') : ?>
                                <p class="gachasoku-auction-won__dm">
                                    DM送付先:
                                    <a href="<?php echo esc_url('https://x.com/' . $x_account); ?>" target="_blank" rel="noopener noreferrer">@<?php echo esc_html($x_account); ?></a>
                                </p>
                            <?php endif; ?>
                            <p class="gachasoku-auction-won__warn">※ このパスワードは絶対に他人に教えないでください。第三者に知られると当選が無効になる場合があります。</p>
                        </div>
                    <?php else : ?>
                        <p class="gachasoku-auction-won__warn">パスワードの表示に問題が発生しました。運営までお問い合わせください。</p>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
            <script>
            (function () {
                document.querySelectorAll('.gachasoku-auction-copy').forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        var el = document.getElementById(btn.getAttribute('data-token-target'));
                        if (!el) { return; }
                        var text = el.textContent;
                        if (navigator.clipboard) {
                            navigator.clipboard.writeText(text).then(function () {
                                var original = btn.textContent;
                                btn.textContent = 'コピーしました';
                                setTimeout(function () { btn.textContent = original; }, 1500);
                            });
                        }
                    });
                });
            })();
            </script>
        <?php endif; ?>
    </section>
    <?php
    return ob_get_clean();
}

/* =========================================================================
 * Phase 5: 管理画面（落札管理・トークン照合・当選確定）
 * ====================================================================== */

add_action('admin_menu', 'gachasoku_register_auction_admin_page');
/**
 * 「落札管理」サブメニューを追加する。
 *
 * @return void
 */
function gachasoku_register_auction_admin_page() {
    add_submenu_page(
        'edit.php?post_type=' . GACHASOKU_AUCTION_POST_TYPE,
        '落札管理',
        '落札管理',
        'manage_options',
        'gachasoku-auction-winners',
        'gachasoku_render_auction_admin_page'
    );
}

/**
 * 落札管理画面のPOST処理（照合・確定）。
 *
 * @return void
 */
function gachasoku_handle_auction_admin_actions() {
    if (!current_user_can('manage_options')) {
        return;
    }
    if (empty($_POST['gachasoku_auction_admin_action'])) {
        return;
    }

    $winner_id = isset($_POST['winner_id']) ? intval($_POST['winner_id']) : 0;
    check_admin_referer('gachasoku_auction_admin_' . $winner_id);

    global $wpdb;
    $winners_table = gachasoku_get_auction_winners_table();
    $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$winners_table} WHERE id = %d", $winner_id), ARRAY_A);
    if (!$row) {
        add_settings_error('gachasoku_auction_admin', 'not_found', '対象の落札データが見つかりませんでした。', 'error');
        return;
    }

    $action = sanitize_text_field(wp_unslash($_POST['gachasoku_auction_admin_action']));
    $now = current_time('mysql');

    if ($action === 'verify') {
        $submitted = isset($_POST['submitted_token']) ? trim(sanitize_text_field(wp_unslash($_POST['submitted_token']))) : '';
        $actual = gachasoku_auction_decrypt($row['token']);

        // タイミング攻撃に強い比較。大文字小文字・前後空白を吸収する。
        $normalized_submitted = strtoupper(preg_replace('/\s+/', '', $submitted));
        $normalized_actual = strtoupper(preg_replace('/\s+/', '', $actual));

        if ($actual !== '' && hash_equals($normalized_actual, $normalized_submitted)) {
            $wpdb->update(
                $winners_table,
                ['dm_status' => 'confirmed', 'confirmed_at' => $now, 'confirmed_by' => get_current_user_id(), 'updated_at' => $now],
                ['id' => $winner_id],
                ['%s', '%s', '%d', '%s'],
                ['%d']
            );
            add_settings_error('gachasoku_auction_admin', 'verified', 'パスワードが一致しました。当選を確定しました。', 'updated');
        } else {
            add_settings_error('gachasoku_auction_admin', 'mismatch', 'パスワードが一致しませんでした。当選は確定していません。', 'error');
        }
        return;
    }

    if ($action === 'confirm') {
        $wpdb->update(
            $winners_table,
            ['dm_status' => 'confirmed', 'confirmed_at' => $now, 'confirmed_by' => get_current_user_id(), 'updated_at' => $now],
            ['id' => $winner_id],
            ['%s', '%s', '%d', '%s'],
            ['%d']
        );
        add_settings_error('gachasoku_auction_admin', 'confirmed', '当選を手動で確定しました。', 'updated');
        return;
    }

    if ($action === 'mark_submitted') {
        $wpdb->update(
            $winners_table,
            ['dm_status' => 'submitted', 'updated_at' => $now],
            ['id' => $winner_id],
            ['%s', '%s'],
            ['%d']
        );
        add_settings_error('gachasoku_auction_admin', 'submitted', '「確認待ち」に変更しました。', 'updated');
        return;
    }
}

/**
 * 落札管理画面を描画する。
 *
 * @return void
 */
function gachasoku_render_auction_admin_page() {
    if (!current_user_can('manage_options')) {
        wp_die('権限がありません。');
    }

    gachasoku_handle_auction_admin_actions();

    global $wpdb;
    $winners_table = gachasoku_get_auction_winners_table();
    $rows = $wpdb->get_results("SELECT * FROM {$winners_table} ORDER BY created_at DESC LIMIT 200", ARRAY_A);

    $dm_labels = ['pending' => '未送信', 'submitted' => '確認待ち', 'confirmed' => '当選確定'];

    echo '<div class="wrap">';
    echo '<h1>オークション 落札管理</h1>';
    echo '<p>落札者がXへDMした秘密のパスワードを照合し、当選を確定します。照合は安全な比較（大文字小文字・空白は無視）で行われます。</p>';
    settings_errors('gachasoku_auction_admin');

    if (empty($rows)) {
        echo '<p>落札データはまだありません。</p></div>';
        return;
    }

    echo '<table class="widefat striped"><thead><tr>';
    echo '<th>オークション</th><th>落札者</th><th>落札額</th><th>正解パスワード</th><th>状態</th><th>照合 / 操作</th>';
    echo '</tr></thead><tbody>';

    foreach ($rows as $row) {
        $auction_id = intval($row['auction_id']);
        $title = get_the_title($auction_id);
        $edit_link = get_edit_post_link($auction_id);
        $member = gachasoku_get_member_by_id($row['member_id']);
        $member_name = $member ? $member['name'] : '不明';
        $member_email = $member ? $member['email'] : '';
        $token = gachasoku_auction_decrypt($row['token']);
        $dm_status = $row['dm_status'];
        $label = isset($dm_labels[$dm_status]) ? $dm_labels[$dm_status] : $dm_status;

        echo '<tr>';
        echo '<td>' . ($edit_link ? '<a href="' . esc_url($edit_link) . '">' . esc_html($title) . '</a>' : esc_html($title)) . '</td>';
        echo '<td>' . esc_html($member_name) . '<br /><small>' . esc_html($member_email) . '</small></td>';
        echo '<td>' . esc_html(number_format($row['winning_amount'])) . ' 円</td>';
        echo '<td><code>' . esc_html($token !== '' ? $token : '（復号失敗）') . '</code></td>';
        echo '<td><strong>' . esc_html($label) . '</strong>';
        if ($dm_status === 'confirmed' && $row['confirmed_at']) {
            echo '<br /><small>' . esc_html(gachasoku_format_datetime($row['confirmed_at'])) . '</small>';
        }
        echo '</td>';

        echo '<td>';
        if ($dm_status !== 'confirmed') {
            // 照合フォーム。
            echo '<form method="post" style="margin-bottom:8px;">';
            wp_nonce_field('gachasoku_auction_admin_' . $row['id']);
            echo '<input type="hidden" name="winner_id" value="' . esc_attr($row['id']) . '" />';
            echo '<input type="hidden" name="gachasoku_auction_admin_action" value="verify" />';
            echo '<input type="text" name="submitted_token" placeholder="DMされたパスワードを貼り付け" style="width:220px;" /> ';
            echo '<button type="submit" class="button button-primary">照合して確定</button>';
            echo '</form>';
            // 手動確定 / 確認待ち。
            echo '<form method="post" style="display:inline;">';
            wp_nonce_field('gachasoku_auction_admin_' . $row['id']);
            echo '<input type="hidden" name="winner_id" value="' . esc_attr($row['id']) . '" />';
            echo '<input type="hidden" name="gachasoku_auction_admin_action" value="confirm" />';
            echo '<button type="submit" class="button" onclick="return confirm(\'照合せずに手動で当選確定します。よろしいですか？\');">手動で確定</button>';
            echo '</form>';
        } else {
            echo '—';
        }
        echo '</td>';
        echo '</tr>';
    }

    echo '</tbody></table></div>';
}

/* =========================================================================
 * フロント用スタイル
 * ====================================================================== */

add_action('wp_enqueue_scripts', 'gachasoku_auction_front_styles', 20);
/**
 * オークション用CSSを既存テーマスタイルにインラインで追加する。
 *
 * @return void
 */
function gachasoku_auction_front_styles() {
    $css = '
    .gachasoku-auction{margin:24px 0;}
    .gachasoku-auction__flash{padding:12px 16px;border-radius:8px;margin-bottom:16px;font-weight:bold;}
    .gachasoku-auction__flash--success{background:#e6f7ec;color:#1a7f37;border:1px solid #b7e4c7;}
    .gachasoku-auction__flash--error{background:#fdeaea;color:#b42318;border:1px solid #f3c0c0;}
    .gachasoku-auction__status{display:inline-block;padding:4px 14px;border-radius:999px;font-weight:bold;font-size:14px;margin-bottom:12px;}
    .gachasoku-auction__status--open{background:#1a7f37;color:#fff;}
    .gachasoku-auction__status--scheduled{background:#946200;color:#fff;}
    .gachasoku-auction__status--ended{background:#666;color:#fff;}
    .gachasoku-auction__badges{display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin-bottom:12px;}
    .gachasoku-auction__badges .gachasoku-auction__status{margin-bottom:0;}
    /* 種類（コンディション）バッジ */
    .gachasoku-auction__condition{display:inline-block;padding:4px 14px;border-radius:999px;font-weight:bold;font-size:14px;background:#eef2ff;color:#3a4ba0;border:1px solid #c7d2fe;}
    .gachasoku-auction__condition--box{background:#fff4e5;color:#9a5b00;border-color:#ffd9a8;}
    .gachasoku-auction__condition--psa10{background:#fde8ef;color:#a01a52;border-color:#f9b8d0;}
    .gachasoku-auction__condition--mint{background:#e6f7ec;color:#1a7f37;border-color:#b7e4c7;}
    .gachasoku-auction__condition--minor_scratch{background:#eef2ff;color:#3a4ba0;border-color:#c7d2fe;}
    .gachasoku-auction__condition--damage_small{background:#fff7e0;color:#8a6d00;border-color:#f3e3a0;}
    .gachasoku-auction__condition--damage_large{background:#fdeaea;color:#b42318;border-color:#f3c0c0;}
    .gachasoku-auction-card__condition{position:absolute;top:8px;right:8px;padding:2px 10px;border-radius:999px;font-size:12px;font-weight:bold;z-index:1;background:#eef2ff;color:#3a4ba0;}
    .gachasoku-auction__image img{max-width:100%;height:auto;border-radius:10px;}
    .gachasoku-auction__info{display:flex;flex-wrap:wrap;gap:16px;margin:16px 0;padding:0;}
    .gachasoku-auction__info>div{background:#faf9f6;border:1px solid #eee;border-radius:8px;padding:10px 16px;min-width:120px;}
    .gachasoku-auction__info dt{font-size:12px;color:#888;margin:0;}
    .gachasoku-auction__info dd{margin:4px 0 0;font-weight:bold;}
    .gachasoku-auction__price{font-size:22px;color:#b42318;}
    .gachasoku-auction__bid-form,.gachasoku-auction__buy-now-form{margin:16px 0;padding:16px;background:#fff;border:1px solid #eee;border-radius:10px;}
    .gachasoku-auction__bid-field input{font-size:18px;padding:8px;width:200px;}
    .gachasoku-auction__hint{color:#888;font-size:13px;margin:6px 0 0;}
    .gachasoku-button{display:inline-block;background:#1a7f37;color:#fff;border:none;border-radius:8px;padding:10px 24px;font-weight:bold;cursor:pointer;font-size:16px;text-decoration:none;}
    .gachasoku-button:hover{opacity:.9;}
    .gachasoku-button--buy-now{background:#b42318;}
    .gachasoku-button--small{font-size:13px;padding:6px 14px;}
    .gachasoku-auction__notice{padding:12px 16px;background:#faf9f6;border-radius:8px;margin:12px 0;}
    .gachasoku-auction__notice--win{background:#e6f7ec;color:#1a7f37;font-weight:bold;}
    .gachasoku-auction__history{margin-top:24px;}
    .gachasoku-auction__history-table{width:100%;border-collapse:collapse;}
    .gachasoku-auction__history-table th,.gachasoku-auction__history-table td{border-bottom:1px solid #eee;padding:8px;text-align:left;font-size:14px;}
    .gachasoku-auction-list{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;}
    .gachasoku-auction-card{display:flex;flex-direction:column;border:1px solid #eee;border-radius:10px;overflow:hidden;text-decoration:none;color:inherit;background:#fff;position:relative;transition:box-shadow .15s ease,transform .15s ease;}
    .gachasoku-auction-card:hover{box-shadow:0 6px 18px rgba(0,0,0,.08);transform:translateY(-2px);}
    .gachasoku-auction-card__badge{position:absolute;top:8px;left:8px;padding:2px 10px;border-radius:999px;font-size:12px;font-weight:bold;color:#fff;background:#1a7f37;z-index:1;}
    .gachasoku-auction-card--ended .gachasoku-auction-card__badge{background:#666;}
    .gachasoku-auction-card--scheduled .gachasoku-auction-card__badge{background:#946200;}
    .gachasoku-auction-card__image{display:block;width:100%;aspect-ratio:4/3;background:#f3f1ec;overflow:hidden;}
    .gachasoku-auction-card__image img{width:100%;height:100%;object-fit:cover;display:block;}
    .gachasoku-auction-card__image--placeholder{display:flex;align-items:center;justify-content:center;font-size:40px;color:#cfc9bd;}
    .gachasoku-auction-card__title{font-weight:bold;padding:10px 12px 0;line-height:1.4;}
    .gachasoku-auction-card__price{padding:6px 12px;color:#b42318;font-weight:bold;}
    .gachasoku-auction-card__end{padding:0 12px 12px;font-size:12px;color:#888;}
    /* マイページ CTA */
    .gachasoku-auction-cta{display:flex;align-items:center;gap:14px;padding:16px 20px;margin:0 0 20px;background:linear-gradient(135deg,#1a7f37,#15923f);border-radius:12px;color:#fff;text-decoration:none;box-shadow:0 4px 14px rgba(26,127,55,.25);}
    .gachasoku-auction-cta:hover{opacity:.95;color:#fff;}
    .gachasoku-auction-cta__icon{font-size:30px;line-height:1;}
    .gachasoku-auction-cta__text{display:flex;flex-direction:column;flex:1;}
    .gachasoku-auction-cta__title{font-size:18px;font-weight:bold;}
    .gachasoku-auction-cta__sub{font-size:13px;opacity:.9;}
    .gachasoku-auction-cta__arrow{font-size:22px;font-weight:bold;}
    .gachasoku-auction-won__heading{font-size:16px;margin:20px 0 12px;padding-top:8px;border-top:1px solid #eee;}
    .gachasoku-auction-won__item{border:1px solid #eee;border-radius:10px;padding:16px;margin-bottom:16px;background:#fff;}
    .gachasoku-auction-won__token-box{margin-top:12px;padding:14px;background:#fffbe6;border:1px solid #ffe58f;border-radius:8px;}
    .gachasoku-auction-won__token{display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin:8px 0;}
    .gachasoku-auction-won__token code{font-size:20px;font-weight:bold;letter-spacing:1px;background:#fff;padding:8px 12px;border:1px dashed #d4b106;border-radius:6px;}
    .gachasoku-auction-won__warn{color:#b42318;font-size:13px;margin-top:8px;}
    .gachasoku-auction-won__done{color:#1a7f37;font-weight:bold;}
    .gachasoku-auction-won__state--confirmed{color:#1a7f37;font-weight:bold;}
    /* レスポンシブ */
    @media (max-width:782px){
      .gachasoku-auction-list{grid-template-columns:repeat(2,1fr);gap:12px;}
      .gachasoku-auction-card__title{font-size:14px;padding:8px 10px 0;}
      .gachasoku-auction-card__price{padding:4px 10px;font-size:14px;}
      .gachasoku-auction-card__end{padding:0 10px 10px;}
      .gachasoku-auction__info{gap:10px;}
      .gachasoku-auction__info>div{min-width:calc(50% - 5px);flex:1 1 calc(50% - 5px);}
      .gachasoku-auction__bid-field input{width:100%;box-sizing:border-box;}
      .gachasoku-auction__bid-form .gachasoku-button,.gachasoku-auction__buy-now-form .gachasoku-button{width:100%;}
      .gachasoku-auction-cta{padding:14px 16px;gap:10px;}
      .gachasoku-auction-cta__title{font-size:16px;}
      .gachasoku-auction-won__token code{font-size:16px;word-break:break-all;}
    }
    @media (max-width:480px){
      .gachasoku-auction-card__badge{font-size:11px;padding:2px 8px;}
    }
    ';
    wp_add_inline_style('yellowsmile-style', $css);
}

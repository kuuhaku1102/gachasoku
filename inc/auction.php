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
        'description' => (string) get_post_meta($auction_id, '_gachasoku_auction_description', true),
    ];
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
}

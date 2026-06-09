<?php
/**
 * オークション一覧（投稿タイプアーカイブ）テンプレート。
 *
 * /auction/ で表示される。商品画像つきカード一覧を
 * [gachasoku_auctions] ショートコードで描画する（画像・ステータス・現在価格・レスポンシブ対応）。
 *
 * @package Gachasoku
 */

get_header(); ?>

<section class="archive-section archive-section--auction">
  <div class="archive-section__header">
    <h2 class="section-title">
      <span class="section-title__icon">🔨</span>
      <span class="section-title__text">オークション一覧</span>
    </h2>
  </div>

  <?php echo do_shortcode('[gachasoku_auctions]'); ?>
</section>

<?php get_footer(); ?>

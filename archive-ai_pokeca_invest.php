<?php
/**
 * Template Name: AIポケカ投資 - 一覧ページ
 * アーカイブページ: 日付別の予想一覧
 */
get_header();
?>

<main class="ai-pokeca-archive">
  <section class="hero ai-pokeca-hero">
    <div class="container">
      <h1>💰 AIポケカ投資予想</h1>
      <p>AIが分析した高騰予想カードを毎日更新！投資の参考にご活用ください。</p>
    </div>
  </section>

  <section class="ai-pokeca-list section">
    <div class="container">
      <?php
      if (have_posts()) :
        ?>
        <div class="ai-pokeca-grid">
          <?php
          while (have_posts()) :
            the_post();
            $cards = get_post_meta(get_the_ID(), '_ai_pokeca_cards', true);
            $card_count = is_array($cards) ? count($cards) : 0;
            ?>
            <article class="ai-pokeca-card">
              <div class="ai-pokeca-card__header">
                <time class="ai-pokeca-card__date" datetime="<?php echo esc_attr(get_the_date('c')); ?>">
                  <?php echo get_the_date('Y年n月j日'); ?>
                </time>
                <span class="ai-pokeca-card__badge"><?php echo $card_count; ?>枚予想</span>
              </div>
              
              <h2 class="ai-pokeca-card__title">
                <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
              </h2>
              
              <?php if (has_excerpt()) : ?>
                <p class="ai-pokeca-card__excerpt"><?php echo esc_html(get_the_excerpt()); ?></p>
              <?php endif; ?>
              
              <?php if ($card_count > 0 && is_array($cards)) : ?>
                <div class="ai-pokeca-card__preview">
                  <?php
                  // 最初の3枚のカードをプレビュー表示
                  $preview_cards = array_slice($cards, 0, 3);
                  foreach ($preview_cards as $card) :
                    if (!empty($card['image'])) :
                      ?>
                      <div class="ai-pokeca-card__preview-item">
                        <img src="<?php echo esc_url($card['image']); ?>" 
                             alt="<?php echo esc_attr($card['name']); ?>"
                             loading="lazy">
                      </div>
                      <?php
                    endif;
                  endforeach;
                  ?>
                </div>
              <?php endif; ?>
              
              <a href="<?php the_permalink(); ?>" class="ai-pokeca-card__link">
                詳細を見る →
              </a>
            </article>
            <?php
          endwhile;
          ?>
        </div>
        
        <div class="pagination">
          <?php
          the_posts_pagination([
            'mid_size'  => 2,
            'prev_text' => '← 前へ',
            'next_text' => '次へ →',
          ]);
          ?>
        </div>
        <?php
      else :
        ?>
        <div class="ai-pokeca-empty">
          <p>現在、予想情報はありません。</p>
        </div>
        <?php
      endif;
      ?>
    </div>
  </section>

  <section class="ai-pokeca-about section">
    <div class="container">
      <h2>AIポケカ投資予想とは？</h2>
      <div class="ai-pokeca-about__content">
        <p>
          当サイトのAIが、市場動向・大会結果・新弾情報などを総合的に分析し、
          今後価格が高騰する可能性の高いポケモンカードを予想します。
        </p>
        <ul>
          <li>📊 <strong>データ分析</strong>：過去の価格推移と市場トレンドを分析</li>
          <li>🏆 <strong>大会結果</strong>：公式大会での使用率と勝率を考慮</li>
          <li>🎴 <strong>新弾情報</strong>：新カードとの相性を評価</li>
          <li>💡 <strong>専門家監修</strong>：トレカ投資の専門家がAIの予想を監修</li>
        </ul>
        <p class="ai-pokeca-about__note">
          ※投資は自己責任でお願いします。本予想は参考情報であり、利益を保証するものではありません。
        </p>
      </div>
    </div>
  </section>
</main>

<?php get_footer(); ?>

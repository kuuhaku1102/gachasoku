<?php
/**
 * Template Name: AIポケカ投資 - 詳細ページ
 * 個別の予想詳細（最大6枚のカード）
 */
get_header();
?>

<main class="ai-pokeca-single">
  <?php
  while (have_posts()) :
    the_post();
    $cards = get_post_meta(get_the_ID(), '_ai_pokeca_cards', true);
    ?>
    
    <article class="ai-pokeca-detail">
      <div class="container">
        <div class="ai-pokeca-detail__header">
          <time class="ai-pokeca-detail__date" datetime="<?php echo esc_attr(get_the_date('c')); ?>">
            📅 <?php echo get_the_date('Y年n月j日'); ?>の予想
          </time>
          <h1 class="ai-pokeca-detail__title"><?php the_title(); ?></h1>
          
          <?php if (has_excerpt()) : ?>
            <div class="ai-pokeca-detail__excerpt">
              <?php the_excerpt(); ?>
            </div>
          <?php endif; ?>
        </div>

        <?php if (is_array($cards) && count($cards) > 0) : ?>
          <div class="ai-pokeca-cards">
            <?php foreach ($cards as $index => $card) : ?>
              <div class="ai-pokeca-card-item">
                <div class="ai-pokeca-card-item__number">
                  Card <?php echo $index + 1; ?>
                </div>
                
                <?php if (!empty($card['image'])) : ?>
                  <div class="ai-pokeca-card-item__image">
                    <img src="<?php echo esc_url($card['image']); ?>" 
                         alt="<?php echo esc_attr($card['name']); ?>"
                         loading="lazy">
                  </div>
                <?php endif; ?>
                
                <div class="ai-pokeca-card-item__content">
                  <?php if (!empty($card['name'])) : ?>
                    <h3 class="ai-pokeca-card-item__name">
                      <?php echo esc_html($card['name']); ?>
                    </h3>
                  <?php endif; ?>
                  
                  <?php if (!empty($card['price'])) : ?>
                    <div class="ai-pokeca-card-item__price">
                      <span class="ai-pokeca-card-item__price-label">現在価格</span>
                      <span class="ai-pokeca-card-item__price-value">
                        ¥<?php echo esc_html($card['price']); ?>
                      </span>
                    </div>
                  <?php endif; ?>
                  
                  <?php if (!empty($card['reason'])) : ?>
                    <div class="ai-pokeca-card-item__reason">
                      <h4 class="ai-pokeca-card-item__reason-title">💡 予想根拠</h4>
                      <p><?php echo nl2br(esc_html($card['reason'])); ?></p>
                    </div>
                  <?php endif; ?>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php else : ?>
          <div class="ai-pokeca-no-cards">
            <p>カード情報が登録されていません。</p>
          </div>
        <?php endif; ?>

        <?php if (get_the_content()) : ?>
          <div class="ai-pokeca-detail__content">
            <h2>📝 総評</h2>
            <?php the_content(); ?>
          </div>
        <?php endif; ?>

        <div class="ai-pokeca-detail__footer">
          <a href="<?php echo get_post_type_archive_link('ai_pokeca_invest'); ?>" class="btn-back">
            ← 一覧に戻る
          </a>
        </div>
      </div>
    </article>

    <section class="ai-pokeca-navigation section">
      <div class="container">
        <div class="ai-pokeca-navigation__links">
          <?php
          $prev_post = get_previous_post();
          $next_post = get_next_post();
          ?>
          
          <?php if ($prev_post) : ?>
            <a href="<?php echo get_permalink($prev_post); ?>" class="ai-pokeca-navigation__link ai-pokeca-navigation__link--prev">
              <span class="ai-pokeca-navigation__label">← 前の予想</span>
              <span class="ai-pokeca-navigation__title"><?php echo get_the_title($prev_post); ?></span>
              <span class="ai-pokeca-navigation__date"><?php echo get_the_date('Y年n月j日', $prev_post); ?></span>
            </a>
          <?php endif; ?>
          
          <?php if ($next_post) : ?>
            <a href="<?php echo get_permalink($next_post); ?>" class="ai-pokeca-navigation__link ai-pokeca-navigation__link--next">
              <span class="ai-pokeca-navigation__label">次の予想 →</span>
              <span class="ai-pokeca-navigation__title"><?php echo get_the_title($next_post); ?></span>
              <span class="ai-pokeca-navigation__date"><?php echo get_the_date('Y年n月j日', $next_post); ?></span>
            </a>
          <?php endif; ?>
        </div>
      </div>
    </section>
    
  <?php endwhile; ?>
</main>

<?php get_footer(); ?>

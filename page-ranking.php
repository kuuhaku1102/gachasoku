<?php
/*
Template Name: ランキングページ
*/

get_header();

if (have_posts()) :
  while (have_posts()) :
    the_post();
    ?>
    <main id="primary" class="site-main ranking-page">
      <div class="ranking-container">
        <header class="ranking-page__header">
          <h1 class="ranking-page__title"><?php the_title(); ?></h1>
          <div class="ranking-page__lead"><?php the_content(); ?></div>
        </header>
    <?php
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
    ?>
    <?php if (!empty($entries)) : ?>
      <ol class="ranking-list">
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
          <li class="ranking-list__item">
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
    <?php else : ?>
      <p class="ranking-page__empty">ランキングが設定されていません。</p>
    <?php endif; ?>
  </div>
    </main>
    <?php
  endwhile;
endif;

get_footer();
?>

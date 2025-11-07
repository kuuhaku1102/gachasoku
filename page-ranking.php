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
    $entries = gachasoku_get_sorted_ranking_entries();
    echo gachasoku_render_ranking_list($entries, [
      'empty_message' => '<p class="ranking-page__empty">ランキングが設定されていません。</p>',
    ]);
    ?>
  </div>
    </main>
    <?php
  endwhile;
endif;

get_footer();
?>

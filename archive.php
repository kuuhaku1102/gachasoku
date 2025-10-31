<?php get_header(); ?>

<section class="archive-section">
  <h2 class="section-title">
    <?php
      if (is_category()) {
        single_cat_title();
      } elseif (is_tag()) {
        single_tag_title();
      } elseif (is_author()) {
        the_post();
        echo '投稿者: ' . get_the_author();
        rewind_posts();
      } else {
        echo '投稿一覧';
      }
    ?>
  </h2>

  <?php if (have_posts()) : ?>
    <div class="archive-grid">
      <?php while (have_posts()) : the_post(); ?>
        <article <?php post_class('archive-card'); ?>>
          <?php if (has_post_thumbnail()) : ?>
            <a class="archive-card__thumbnail" href="<?php the_permalink(); ?>">
              <?php the_post_thumbnail('medium_large'); ?>
            </a>
          <?php endif; ?>
          <div class="archive-card__body">
            <h3 class="archive-card__title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
            <p class="archive-card__excerpt"><?php echo wp_trim_words(get_the_excerpt(), 36, '...'); ?></p>
            <a href="<?php the_permalink(); ?>" class="archive-card__button button">続きを読む</a>
          </div>
        </article>
      <?php endwhile; ?>
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
  <?php else : ?>
    <p class="archive-empty">記事がありません。</p>
  <?php endif; ?>
</section>

<?php get_footer(); ?>

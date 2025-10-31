<?php get_header(); ?>
<h2 class="section-title">
  <?php if (is_category()) { single_cat_title(); }
  elseif (is_tag()) { single_tag_title(); }
  elseif (is_author()) { the_post(); echo '投稿者: ' . get_the_author(); rewind_posts(); }
  else { echo '投稿一覧'; } ?>
</h2>
<?php if (have_posts()) : while (have_posts()) : the_post(); ?>
  <div class="card">
    <?php if (has_post_thumbnail()) : ?>
      <a href="<?php the_permalink(); ?>"><?php the_post_thumbnail('medium', ['style' => 'border-radius:8px;margin-bottom:15px;']); ?></a>
    <?php endif; ?>
    <h3><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
    <p><?php echo wp_trim_words(get_the_excerpt(), 40, '...'); ?></p>
    <a href="<?php the_permalink(); ?>" class="button">続きを読む</a>
  </div>
<?php endwhile; ?>
  <div class="pagination" style="text-align:center;margin-top:30px;">
    <?php the_posts_pagination(['mid_size' => 2,'prev_text' => '← 前へ','next_text' => '次へ →']); ?>
  </div>
<?php else : ?>
  <p>記事がありません。</p>
<?php endif; ?>
<?php get_footer(); ?>

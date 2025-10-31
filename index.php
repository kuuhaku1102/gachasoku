<?php get_header(); ?>
<h2 class="section-title">テスト最新の記事</h2>
<?php if (have_posts()) : while (have_posts()) : the_post(); ?>
  <div class="card">
    <h3><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
    <p><?php the_excerpt(); ?></p>
    <a href="<?php the_permalink(); ?>" class="button">続きを読む</a>
  </div>
<?php endwhile; else: ?>
  <p>記事がありません。</p>
<?php endif; ?>
<?php get_footer(); ?>


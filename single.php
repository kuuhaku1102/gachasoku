<?php get_header(); ?>
<?php if (have_posts()) : while (have_posts()) : the_post(); ?>
  <article class="card">
    <h1 class="section-title"><?php the_title(); ?></h1>
    <p style="color:#888;font-size:0.9rem;margin-bottom:15px;">
      投稿日: <?php the_time('Y年n月j日'); ?>　カテゴリ: <?php the_category(', '); ?>
    </p>
    <?php if (has_post_thumbnail()) : ?>
      <div style="margin-bottom:20px;"><?php the_post_thumbnail('large', ['style'=>'border-radius:10px;']); ?></div>
    <?php endif; ?>
    <div class="post-content"><?php the_content(); ?></div>
  </article>
  <div style="margin-top:40px;text-align:center;">
    <?php previous_post_link('%link', '← 前の記事'); ?> |
    <?php next_post_link('%link', '次の記事 →'); ?>
  </div>
<?php endwhile; endif; ?>
<?php get_footer(); ?>

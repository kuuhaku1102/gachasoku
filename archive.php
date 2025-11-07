<?php get_header(); ?>

<header class="archive-header">
  <h1 class="archive-title"><?php the_archive_title(); ?></h1>
  <?php if (get_the_archive_description()) : ?>
    <div class="archive-description"><?php the_archive_description(); ?></div>
  <?php endif; ?>
</header>

<?php if (have_posts()) : ?>
  <?php while (have_posts()) : the_post(); ?>
    <article <?php post_class('post-card'); ?>>
      <?php if (has_post_thumbnail()) : ?>
        <div class="post-thumbnail">
          <a href="<?php the_permalink(); ?>"><?php the_post_thumbnail('large'); ?></a>
        </div>
      <?php endif; ?>

      <header class="entry-header">
        <h2 class="entry-title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
        <time class="entry-date" datetime="<?php echo esc_attr(get_the_date(DATE_W3C)); ?>">
          <?php echo esc_html(get_the_date()); ?>
        </time>
      </header>

      <div class="entry-summary">
        <?php the_excerpt(); ?>
      </div>
    </article>
  <?php endwhile; ?>

  <?php the_posts_pagination([
    'prev_text' => '前へ',
    'next_text' => '次へ',
  ]); ?>
<?php else : ?>
  <p class="no-posts">記事がありません。</p>
<?php endif; ?>

<?php get_footer(); ?>

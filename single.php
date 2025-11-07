<?php get_header(); ?>

<?php if (have_posts()) : ?>
  <?php while (have_posts()) : the_post(); ?>
    <article <?php post_class('single-post'); ?>>
      <header class="entry-header">
        <h1 class="entry-title"><?php the_title(); ?></h1>
        <div class="entry-meta">
          <time datetime="<?php echo esc_attr(get_the_date(DATE_W3C)); ?>">
            <?php echo esc_html(get_the_date()); ?>
          </time>
          <?php if (has_category()) : ?>
            <span class="entry-categories"><?php the_category(', '); ?></span>
          <?php endif; ?>
        </div>
        <?php if (has_post_thumbnail()) : ?>
          <div class="post-thumbnail"><?php the_post_thumbnail('large'); ?></div>
        <?php endif; ?>
      </header>

      <div class="entry-content">
        <?php the_content(); ?>
        <?php wp_link_pages([ 'before' => '<nav class="page-links">', 'after' => '</nav>' ]); ?>
      </div>

      <?php if (get_the_tags()) : ?>
        <footer class="entry-footer">
          <div class="tag-links"><?php the_tags('', ' '); ?></div>
        </footer>
      <?php endif; ?>
    </article>

    <nav class="post-navigation" aria-label="投稿ナビゲーション">
      <div class="nav-previous"><?php previous_post_link('%link', '« %title'); ?></div>
      <div class="nav-next"><?php next_post_link('%link', '%title »'); ?></div>
    </nav>

    <?php if (comments_open() || get_comments_number()) : ?>
      <div class="comments-area">
        <?php comments_template(); ?>
      </div>
    <?php endif; ?>
  <?php endwhile; ?>
<?php endif; ?>

<?php get_footer(); ?>

<?php get_header(); ?>

<?php if (have_posts()) : ?>
  <?php while (have_posts()) : the_post(); ?>
    <article <?php post_class('page-content'); ?>>
      <header class="entry-header">
        <h1 class="entry-title"><?php the_title(); ?></h1>
      </header>

      <div class="entry-content">
        <?php the_content(); ?>
        <?php wp_link_pages([ 'before' => '<nav class="page-links">', 'after' => '</nav>' ]); ?>
      </div>
    </article>

    <?php if (comments_open() || get_comments_number()) : ?>
      <div class="comments-area">
        <?php comments_template(); ?>
      </div>
    <?php endif; ?>
  <?php endwhile; ?>
<?php endif; ?>

<?php get_footer(); ?>

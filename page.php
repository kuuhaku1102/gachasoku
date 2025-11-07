<?php get_header(); ?>
<h2 class="section-title"><?php the_title(); ?></h2>
<div class="card">
  <?php while (have_posts()) : the_post(); ?>
    <?php the_content(); ?>
  <?php endwhile; ?>
</div>
<?php get_footer(); ?>

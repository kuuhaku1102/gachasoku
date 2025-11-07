<?php get_header(); ?>
<h2 class="section-title"><?php the_title(); ?></h2>
<div class="card">
  <?php while (have_posts()) : the_post(); ?>
    <?php the_content(); ?>
    <?php
      if (function_exists('gachasoku_render_favorite_sites_form') && function_exists('gachasoku_is_mypage') && gachasoku_is_mypage()) {
        if (empty($GLOBALS['gachasoku_favorite_sites_form_appended'])) {
          echo gachasoku_render_favorite_sites_form();
          $GLOBALS['gachasoku_favorite_sites_form_appended'] = true;
        }
      }
    ?>
  <?php endwhile; ?>
</div>
<?php get_footer(); ?>

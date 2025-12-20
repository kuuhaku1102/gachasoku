<?php get_header(); ?>
<?php if (have_posts()) : while (have_posts()) : the_post(); ?>
  <article class="single-post">
    <header class="single-post__header">
      <div class="single-post__meta">
        <time class="single-post__date" datetime="<?php echo get_the_date('c'); ?>">
          📅 <?php the_time('Y年n月j日'); ?>
        </time>
        <?php
          $categories = get_the_category();
          if (!empty($categories)) :
            foreach ($categories as $category) :
        ?>
          <span class="single-post__category"><?php echo esc_html($category->name); ?></span>
        <?php
            endforeach;
          endif;
        ?>
      </div>
      <h1 class="single-post__title"><?php the_title(); ?></h1>
    </header>

    <?php if (has_post_thumbnail()) : ?>
      <div class="single-post__thumbnail">
        <?php the_post_thumbnail('large'); ?>
      </div>
    <?php endif; ?>

    <div class="single-post__content">
      <?php the_content(); ?>
    </div>

    <footer class="single-post__footer">
      <div class="single-post__tags">
        <?php
          $tags = get_the_tags();
          if ($tags) :
        ?>
          <div class="single-post__tags-label">🏷️ タグ:</div>
          <div class="single-post__tags-list">
            <?php foreach ($tags as $tag) : ?>
              <a href="<?php echo get_tag_link($tag->term_id); ?>" class="single-post__tag">
                <?php echo esc_html($tag->name); ?>
              </a>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </footer>
  </article>

  <nav class="post-navigation">
    <div class="post-navigation__prev">
      <?php previous_post_link('%link', '<span class="post-navigation__arrow">←</span><span class="post-navigation__label">前の記事</span>'); ?>
    </div>
    <div class="post-navigation__next">
      <?php next_post_link('%link', '<span class="post-navigation__label">次の記事</span><span class="post-navigation__arrow">→</span>'); ?>
    </div>
  </nav>
<?php endwhile; endif; ?>
<?php get_footer(); ?>

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

  <?php
    $selected_site = isset($_GET['site']) ? sanitize_text_field(wp_unslash($_GET['site'])) : '';
    $selected_sort = isset($_GET['sort']) ? sanitize_key(wp_unslash($_GET['sort'])) : 'latest';
    $site_terms     = gachasoku_get_archive_site_terms();
  ?>

  <form class="archive-filters" method="get">
    <div class="archive-filters__group archive-filters__group--search">
      <label for="archive-filter-search">キーワード</label>
      <input type="search" id="archive-filter-search" name="s" value="<?php echo esc_attr(get_search_query()); ?>" placeholder="検索ワード" />
    </div>
    <div class="archive-filters__group archive-filters__group--site">
      <label for="archive-filter-site">サイト名</label>
      <select id="archive-filter-site" name="site" onchange="this.form.submit()">
        <option value="">すべて</option>
        <?php foreach ($site_terms as $term) : ?>
          <option value="<?php echo esc_attr($term->slug); ?>" <?php selected($selected_site, $term->slug); ?>><?php echo esc_html($term->name); ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="archive-filters__group archive-filters__group--sort">
      <label for="archive-filter-sort">並び替え</label>
      <select id="archive-filter-sort" name="sort" onchange="this.form.submit()">
        <option value="latest" <?php selected($selected_sort, 'latest'); ?>>最新順</option>
        <option value="oldest" <?php selected($selected_sort, 'oldest'); ?>>古い順</option>
        <option value="title" <?php selected($selected_sort, 'title'); ?>>タイトル順</option>
      </select>
    </div>
    <noscript>
      <button type="submit" class="button">絞り込む</button>
    </noscript>
  </form>

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

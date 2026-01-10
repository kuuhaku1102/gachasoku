<?php
/**
 * Template Name: 記事一覧
 */

get_header();
?>

<section class="archive-section">
  <div class="archive-section__header">
    <h2 class="section-title">
      <span class="section-title__icon">📝</span>
      <span class="section-title__text">すべての記事</span>
    </h2>
    <?php
    // 全投稿数を取得
    $all_posts_count = wp_count_posts('post');
    $published_count = $all_posts_count->publish;
    ?>
    <p class="archive-section__count">全 <?php echo $published_count; ?> 件</p>
  </div>

  <?php
    $selected_site = isset($_GET['site']) ? sanitize_text_field(wp_unslash($_GET['site'])) : '';
    $site_terms     = gachasoku_get_archive_site_terms();
    $paged = get_query_var('paged') ? get_query_var('paged') : 1;
    
    // 全投稿を取得するクエリ
    $args = [
      'post_type'      => 'post',
      'post_status'    => 'publish',
      'posts_per_page' => 12,
      'paged'          => $paged,
    ];
    
    // 検索クエリがある場合
    if (get_search_query()) {
      $args['s'] = get_search_query();
    }
    
    // サイト名での絞り込み
    if ($selected_site && function_exists('gachasoku_get_archive_site_terms')) {
      $args['tax_query'] = [
        [
          'taxonomy' => 'site',
          'field'    => 'slug',
          'terms'    => $selected_site,
        ],
      ];
    }
    
    $blog_query = new WP_Query($args);
  ?>

  <form class="archive-filters" method="get">
    <div class="archive-filters__group archive-filters__group--search">
      <label for="archive-filter-search">
        <span class="archive-filters__icon">🔍</span>
        キーワード
      </label>
      <input type="search" id="archive-filter-search" name="s" value="<?php echo esc_attr(get_search_query()); ?>" placeholder="検索ワード" />
    </div>
    <div class="archive-filters__group archive-filters__group--site">
      <label for="archive-filter-site">
        <span class="archive-filters__icon">🏷️</span>
        サイト名
      </label>
      <select id="archive-filter-site" name="site" onchange="this.form.submit()">
        <option value="">すべて</option>
        <?php foreach ($site_terms as $term) : ?>
          <option value="<?php echo esc_attr($term->slug); ?>" <?php selected($selected_site, $term->slug); ?>><?php echo esc_html($term->name); ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <noscript>
      <button type="submit" class="button">絞り込む</button>
    </noscript>
  </form>

  <?php if ($blog_query->have_posts()) : ?>
    <div class="archive-grid">
      <?php while ($blog_query->have_posts()) : $blog_query->the_post(); ?>
        <article <?php post_class('archive-card'); ?>>
          <?php if (has_post_thumbnail()) : ?>
            <a class="archive-card__thumbnail" href="<?php the_permalink(); ?>">
              <?php the_post_thumbnail('medium_large'); ?>
              <div class="archive-card__thumbnail-overlay"></div>
            </a>
          <?php endif; ?>
          <div class="archive-card__body">
            <div class="archive-card__meta">
              <time class="archive-card__date" datetime="<?php echo get_the_date('c'); ?>">
                📅 <?php echo get_the_date('Y.m.d'); ?>
              </time>
              <?php
                $categories = get_the_category();
                if (!empty($categories)) :
              ?>
                <span class="archive-card__category">
                  <?php echo esc_html($categories[0]->name); ?>
                </span>
              <?php endif; ?>
            </div>
            <h3 class="archive-card__title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
            <p class="archive-card__excerpt"><?php echo wp_trim_words(get_the_excerpt(), 36, '...'); ?></p>
            <a href="<?php the_permalink(); ?>" class="archive-card__button button">続きを読む →</a>
          </div>
        </article>
      <?php endwhile; ?>
    </div>

    <div class="pagination">
      <?php
        echo paginate_links([
          'total'     => $blog_query->max_num_pages,
          'current'   => $paged,
          'mid_size'  => 2,
          'prev_text' => '← 前へ',
          'next_text' => '次へ →',
        ]);
      ?>
    </div>
  <?php else : ?>
    <p class="archive-empty">記事がありません。</p>
  <?php endif; ?>
  
  <?php wp_reset_postdata(); ?>
</section>

<?php get_footer(); ?>

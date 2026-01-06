<?php get_header(); ?>

<section class="category-hub">
  <?php
    $category = get_queried_object();
    $category_description = category_description();
  ?>
  
  <header class="category-hub__header">
    <h1 class="category-hub__title">
      <span class="category-hub__icon">📂</span>
      <?php single_cat_title(); ?>
    </h1>
    <?php if (have_posts()) : ?>
      <p class="category-hub__count">
        <?php
          global $wp_query;
          echo '全 ' . $wp_query->found_posts . ' 件の記事';
        ?>
      </p>
    <?php endif; ?>
  </header>

  <?php if (!empty($category_description)) : ?>
    <div class="category-hub__description">
      <?php echo $category_description; ?>
    </div>
  <?php else : ?>
    <div class="category-hub__description">
      <p>このカテゴリでは、<?php single_cat_title(); ?>に関する情報をまとめています。初心者向けの基礎知識から、詳しい比較・選び方まで、役立つ記事を揃えています。</p>
    </div>
  <?php endif; ?>

  <?php if (have_posts()) : ?>
    <div class="category-hub__articles">
      <h2 class="category-hub__section-title">
        <span class="category-hub__section-icon">📝</span>
        記事一覧
      </h2>
      
      <div class="archive-grid">
        <?php while (have_posts()) : the_post(); ?>
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
          the_posts_pagination([
            'mid_size'  => 2,
            'prev_text' => '← 前へ',
            'next_text' => '次へ →',
          ]);
        ?>
      </div>
    </div>
  <?php else : ?>
    <p class="archive-empty">記事がありません。</p>
  <?php endif; ?>

  <nav class="category-hub__nav">
    <a href="<?php echo esc_url(home_url('/')); ?>" class="category-hub__nav-link">
      ← トップページに戻る
    </a>
  </nav>
</section>

<?php get_footer(); ?>

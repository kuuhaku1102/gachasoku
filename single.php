<?php get_header(); ?>
<?php if (have_posts()) : while (have_posts()) : the_post(); ?>

<?php
// 構造化データ：Article
$schema_article = [
  '@context' => 'https://schema.org',
  '@type' => 'Article',
  'headline' => get_the_title(),
  'datePublished' => get_the_date('c'),
  'dateModified' => get_the_modified_date('c'),
  'author' => [
    '@type' => 'Organization',
    'name' => get_bloginfo('name'),
  ],
  'publisher' => [
    '@type' => 'Organization',
    'name' => get_bloginfo('name'),
    'logo' => [
      '@type' => 'ImageObject',
      'url' => get_site_icon_url(),
    ],
  ],
];

if (has_post_thumbnail()) {
  $schema_article['image'] = get_the_post_thumbnail_url(get_the_ID(), 'large');
}

// 構造化データ：Breadcrumb
$categories = get_the_category();
$breadcrumb_items = [
  [
    '@type' => 'ListItem',
    'position' => 1,
    'name' => 'ホーム',
    'item' => home_url('/'),
  ],
];

if (!empty($categories)) {
  $breadcrumb_items[] = [
    '@type' => 'ListItem',
    'position' => 2,
    'name' => $categories[0]->name,
    'item' => get_category_link($categories[0]->term_id),
  ];
  $breadcrumb_items[] = [
    '@type' => 'ListItem',
    'position' => 3,
    'name' => get_the_title(),
    'item' => get_permalink(),
  ];
} else {
  $breadcrumb_items[] = [
    '@type' => 'ListItem',
    'position' => 2,
    'name' => get_the_title(),
    'item' => get_permalink(),
  ];
}

$schema_breadcrumb = [
  '@context' => 'https://schema.org',
  '@type' => 'BreadcrumbList',
  'itemListElement' => $breadcrumb_items,
];
?>

<script type="application/ld+json">
<?php echo wp_json_encode($schema_article, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT); ?>
</script>

<script type="application/ld+json">
<?php echo wp_json_encode($schema_breadcrumb, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT); ?>
</script>

<nav class="breadcrumb" aria-label="パンくずリスト">
  <ol class="breadcrumb__list">
    <li class="breadcrumb__item">
      <a href="<?php echo esc_url(home_url('/')); ?>" class="breadcrumb__link">ホーム</a>
    </li>
    <?php if (!empty($categories)) : ?>
      <li class="breadcrumb__item">
        <a href="<?php echo esc_url(get_category_link($categories[0]->term_id)); ?>" class="breadcrumb__link">
          <?php echo esc_html($categories[0]->name); ?>
        </a>
      </li>
    <?php endif; ?>
    <li class="breadcrumb__item breadcrumb__item--current" aria-current="page">
      <?php the_title(); ?>
    </li>
  </ol>
</nav>
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

  <?php
    // 同じカテゴリーの記事への内部リンク
    $categories = get_the_category();
    if (!empty($categories)) :
      $category = $categories[0];
  ?>
    <aside class="single-post__related">
      <h2 class="single-post__related-title">
        <span class="single-post__related-icon">📚</span>
        <?php echo esc_html($category->name); ?>の他の記事
      </h2>
      <p class="single-post__related-intro">
        <a href="<?php echo esc_url(get_category_link($category->term_id)); ?>" class="single-post__related-category-link">
          <?php echo esc_html($category->name); ?>カテゴリーを見る →
        </a>
      </p>
    </aside>
  <?php endif; ?>

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

<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
  <meta charset="<?php bloginfo('charset'); ?>">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<div id="page" class="site">
  <header class="site-header">
    <div class="container">
      <div class="site-branding">
        <?php if (has_custom_logo()) : ?>
          <div class="site-logo"><?php the_custom_logo(); ?></div>
        <?php endif; ?>

        <?php if (is_front_page() && is_home()) : ?>
          <h1 class="site-title"><a href="<?php echo esc_url(home_url('/')); ?>"><?php bloginfo('name'); ?></a></h1>
        <?php else : ?>
          <p class="site-title"><a href="<?php echo esc_url(home_url('/')); ?>"><?php bloginfo('name'); ?></a></p>
        <?php endif; ?>

        <?php $description = get_bloginfo('description', 'display');
        if ($description || is_customize_preview()) : ?>
          <p class="site-description"><?php echo esc_html($description); ?></p>
        <?php endif; ?>
      </div>

      <?php if (has_nav_menu('main-menu')) : ?>
        <nav class="site-navigation" aria-label="メインメニュー">
          <?php wp_nav_menu([
            'theme_location' => 'main-menu',
            'menu_class'     => 'menu',
            'container'      => false,
            'depth'          => 1,
          ]); ?>
        </nav>
      <?php endif; ?>
    </div>
  </header>

  <main class="site-main">
    <div class="container">

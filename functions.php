<?php
add_action('after_setup_theme', function() {
  add_theme_support('title-tag');
  add_theme_support('post-thumbnails');
  add_theme_support('custom-logo', [
    'height'      => 80,
    'width'       => 80,
    'flex-height' => true,
    'flex-width'  => true,
  ]);

  register_nav_menus([
    'main-menu' => 'メインメニュー',
  ]);
});

add_action('wp_enqueue_scripts', function() {
  wp_enqueue_style('yellowsmile-style', get_stylesheet_uri(), [], '1.0');
});

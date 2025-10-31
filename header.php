<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo('charset'); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<header class="site-header">
  <div class="container site-header__inner">
    <div class="site-header__branding">
      <a class="site-header__logo" href="<?php echo esc_url(home_url('/')); ?>">
        <span class="site-header__title"><?php echo esc_html(get_bloginfo('name')); ?></span>
      </a>
      <?php
      $gachasoku_description = get_bloginfo('description', 'display');
      if ($gachasoku_description || is_customize_preview()) :
        ?>
        <p class="site-header__description"><?php echo esc_html($gachasoku_description); ?></p>
        <?php
      endif;
      ?>
    </div>
    <button class="site-header__toggle" type="button" aria-controls="primary-navigation" aria-expanded="false">
      <span class="site-header__toggle-bars" aria-hidden="true">
        <span></span>
        <span></span>
        <span></span>
      </span>
      <span class="screen-reader-text">メニューを開閉</span>
    </button>
    <nav class="site-header__nav" id="primary-navigation" aria-label="メインメニュー">
      <?php
      wp_nav_menu([
        'theme_location' => 'main-menu',
        'menu_class'     => 'site-header__menu',
        'container'      => false,
      ]);
      ?>
    </nav>
    <div class="site-header__membership">
      <?php if (function_exists('gachasoku_is_member_logged_in') && gachasoku_is_member_logged_in()) : ?>
        <a class="site-header__membership-link" href="<?php echo esc_url(gachasoku_get_membership_page_url('member-dashboard')); ?>">マイページ</a>
        <a class="site-header__membership-link site-header__membership-link--logout" href="<?php echo esc_url(gachasoku_get_member_logout_url()); ?>">ログアウト</a>
      <?php else : ?>
        <a class="site-header__membership-link" href="<?php echo esc_url(gachasoku_get_membership_page_url('member-register')); ?>">会員登録</a>
        <a class="site-header__membership-link" href="<?php echo esc_url(gachasoku_get_membership_page_url('member-login')); ?>">ログイン</a>
      <?php endif; ?>
    </div>
  </div>
</header>
<main class="container">



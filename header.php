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
    <?php
    $is_member_logged_in = function_exists('gachasoku_is_member_logged_in') && gachasoku_is_member_logged_in();
    $membership_links     = [];

    if ($is_member_logged_in) {
      $membership_links[] = [
        'url'   => gachasoku_get_membership_page_url('member-dashboard'),
        'label' => 'マイページ',
      ];

      $membership_links[] = [
        'url'       => gachasoku_get_member_logout_url(),
        'label'     => 'ログアウト',
        'is_logout' => true,
      ];
    } else {
      $membership_links[] = [
        'url'   => gachasoku_get_membership_page_url('member-register'),
        'label' => '会員登録',
      ];

      $membership_links[] = [
        'url'   => gachasoku_get_membership_page_url('member-login'),
        'label' => 'ログイン',
      ];
    }
    ?>
    <nav class="site-header__nav" id="primary-navigation" aria-label="メインメニュー">
      <?php
      wp_nav_menu([
        'theme_location' => 'main-menu',
        'menu_class'     => 'site-header__menu',
        'container'      => false,
      ]);
      ?>
      <div class="site-header__membership site-header__membership--mobile">
        <?php foreach ($membership_links as $link) : ?>
          <a class="site-header__membership-link<?php echo !empty($link['is_logout']) ? ' site-header__membership-link--logout' : ''; ?>" href="<?php echo esc_url($link['url']); ?>"><?php echo esc_html($link['label']); ?></a>
        <?php endforeach; ?>
      </div>
    </nav>
    <div class="site-header__membership site-header__membership--desktop">
      <?php foreach ($membership_links as $link) : ?>
        <a class="site-header__membership-link<?php echo !empty($link['is_logout']) ? ' site-header__membership-link--logout' : ''; ?>" href="<?php echo esc_url($link['url']); ?>"><?php echo esc_html($link['label']); ?></a>
      <?php endforeach; ?>
    </div>
  </div>
</header>
<main class="container">



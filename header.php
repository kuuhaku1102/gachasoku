<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo('charset'); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<header>
  <div class="container">
    <h1><a href="<?php echo esc_url(home_url('/')); ?>"> <?php bloginfo('name'); ?></a></h1>
    <p class="site-description"><?php bloginfo('description'); ?></p>
    <nav><?php wp_nav_menu(['theme_location' => 'main-menu']); ?></nav>
  </div>
</header>
<main class="container">

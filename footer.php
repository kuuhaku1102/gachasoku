</main>
<footer class="site-footer">
  <div class="container site-footer__inner">
    <?php if (has_nav_menu('footer-menu')) : ?>
      <nav class="site-footer__nav" aria-label="フッターメニュー">
        <?php
        wp_nav_menu([
          'theme_location' => 'footer-menu',
          'menu_class'     => 'site-footer__menu',
          'container'      => false,
          'depth'          => 1,
        ]);
        ?>
      </nav>
    <?php endif; ?>
    <div class="site-footer__meta">
      <p>&copy; <?php echo date('Y'); ?> <?php bloginfo('name'); ?> All Rights Reserved.</p>
      <p>Made with <span class="site-footer__heart" aria-hidden="true">💛</span> by <strong>InfinityDesign</strong></p>
    </div>
  </div>
</footer>
<?php wp_footer(); ?>
</body>
</html>

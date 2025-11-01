<?php
/* Template Name: オンラインオリパまとめ - Index */
get_header();
?>

<main class="oripa-index">
  <section class="hero">
    <div class="container">
      <h1>【2025年最新版】オンラインオリパ・ガチャサイトまとめ</h1>
      <p>
        ポケカ・遊戯王・ワンピースなど人気トレカの最新オンラインオリパ情報を毎日更新！
        当サイトでは信頼できるオリパ販売サイトを徹底比較し、初心者でも安心して楽しめるガチャを紹介しています。
      </p>
      <a href="#latest" class="btn-primary">最新ガチャを見る</a>
    </div>
  </section>

  <section id="ranking" class="ranking-highlight section">
    <div class="container">
      <h2>人気ランキングガチャ</h2>
      <p>人気と評価の高いオンラインオリパをランキング形式でチェック！迷ったときの参考にどうぞ。</p>
      <?php echo do_shortcode('[gachasoku_ranking]'); ?>
    </div>
  </section>

  <section id="campaigns" class="campaigns section">
    <div class="container">
      <h2>現在開催中のキャンペーン</h2>
      <p>今すぐ参加できるお得なキャンペーン情報をチェックし、気になる企画に応募しましょう。</p>
      <?php echo do_shortcode('[gachasoku_campaigns status="open"]'); ?>
    </div>
  </section>

  <section id="latest" class="latest-oripa section">
    <div class="container">
      <h2>オリパ最新ガチャ情報</h2>
      <p>最新のオンラインオリパガチャ一覧を毎日更新！人気・限定イベント・高還元オリパを見逃すな！</p>
      <?php echo do_shortcode('[oripa_list]'); ?>
    </div>
  </section>

  <section id="calendar" class="calendar section">
    <div class="container">
      <h2>オリパイベントカレンダー</h2>
      <p>各サイトで開催されるキャンペーン・特別ガチャ・限定BOX情報をカレンダー形式でチェック！</p>
      <?php echo do_shortcode('[gachasoku_calendar]'); ?>
    </div>
  </section>

  <section class="latest-posts section">
    <div class="container">
      <h2>最新記事一覧</h2>
      <p>話題のオンラインオリパ情報や攻略記事をピックアップ。最新の投稿をチェックしよう！</p>
      <?php
      $gachasoku_latest_posts = new WP_Query([
        'post_type'      => 'post',
        'posts_per_page' => 4,
        'post_status'    => 'publish',
      ]);

      if ($gachasoku_latest_posts->have_posts()) :
        ?>
        <div class="latest-posts__grid">
          <?php
          while ($gachasoku_latest_posts->have_posts()) :
            $gachasoku_latest_posts->the_post();
            $thumbnail_url = get_the_post_thumbnail_url(get_the_ID(), 'medium');
            ?>
            <article class="latest-posts__card">
              <a class="latest-posts__thumb" href="<?php the_permalink(); ?>">
                <?php if ($thumbnail_url) : ?>
                  <img src="<?php echo esc_url($thumbnail_url); ?>" alt="<?php the_title_attribute(); ?>">
                <?php else : ?>
                  <span class="latest-posts__thumb--placeholder">No Image</span>
                <?php endif; ?>
              </a>
              <div class="latest-posts__body">
                <time class="latest-posts__date" datetime="<?php echo esc_attr(get_the_date('c')); ?>"><?php echo esc_html(get_the_date()); ?></time>
                <h3 class="latest-posts__title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
                <p class="latest-posts__excerpt"><?php echo esc_html(wp_trim_words(get_the_excerpt(), 22, '…')); ?></p>
                <a class="latest-posts__more" href="<?php the_permalink(); ?>">記事を読む</a>
              </div>
            </article>
            <?php
          endwhile;
          wp_reset_postdata();
          ?>
        </div>
        <?php
      else :
        ?>
        <p class="latest-posts__empty">現在表示できる記事がありません。</p>
        <?php
      endif;
      ?>
    </div>
  </section>

  <section class="about section">
    <div class="container">
      <h2>オンラインオリパとは？</h2>
      <p>
        「オンラインオリパ」は、実店舗で販売されていた「オリジナルパック」をネット上で楽しめるサービスです。
        トレカファンが気軽にレアカードを狙える仕組みとして急速に普及しています。
      </p>
      <h3>オンラインオリパが人気の理由</h3>
      <ul>
        <li>抽選結果がすぐに分かるリアルタイム感</li>
        <li>限定イベント・コラボなど多彩なガチャ企画</li>
        <li>在庫・排出率が明確で安心</li>
      </ul>
    </div>
  </section>

  <section class="faq section">
    <div class="container">
      <h2>よくある質問（FAQ）</h2>
      <dl>
        <dt>Q. オンラインオリパは違法じゃないの？</dt>
        <dd>各サイトは古物商許可を取得し、景品表示法に準拠して運営されています。</dd>
        <dt>Q. 高額カードは本当に当たりますか？</dt>
        <dd>在庫・当選履歴が公開されており、透明性が高いサイトを選ぶのがポイントです。</dd>
        <dt>Q. 支払い方法は？</dt>
        <dd>クレジットカード・PayPay・コンビニ払いなど各種対応。詳細は各公式サイトで確認。</dd>
      </dl>
    </div>
  </section>

  <section class="comparison section">
    <div class="container">
      <h2>オンラインオリパ比較表</h2>
      <table class="comparison-table">
        <thead>
          <tr><th>サイト名</th><th>特徴</th><th>還元率</th><th>取扱シリーズ</th></tr>
        </thead>
        <tbody>
          <tr>
            <td data-label="サイト名">オリパワン</td>
            <td data-label="特徴">初心者に人気</td>
            <td data-label="還元率">約85%</td>
            <td data-label="取扱シリーズ">ポケカ・ワンピース</td>
          </tr>
          <tr>
            <td data-label="サイト名">オリパダッシュ</td>
            <td data-label="特徴">更新頻度が高い</td>
            <td data-label="還元率">約90%</td>
            <td data-label="取扱シリーズ">遊戯王・ポケカ</td>
          </tr>
          <tr>
            <td data-label="サイト名">おりくじ</td>
            <td data-label="特徴">低価格帯中心</td>
            <td data-label="還元率">約80%</td>
            <td data-label="取扱シリーズ">ポケカ・MTG</td>
          </tr>
          <tr>
            <td data-label="サイト名">DOPA</td>
            <td data-label="特徴">イベント特化型</td>
            <td data-label="還元率">約88%</td>
            <td data-label="取扱シリーズ">ポケカ</td>
          </tr>
        </tbody>
      </table>
    </div>
  </section>
</main>

<?php get_footer(); ?>

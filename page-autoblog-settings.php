<?php
/**
 * Template Name: 自動ブログ設定
 * Description: 自動ブログ生成システムの設定と管理画面
 */

// 管理者以外はアクセス不可
if (!current_user_can('manage_options')) {
    wp_die('このページにアクセスする権限がありません。');
}

get_header();
?>

<style>
.autoblog-settings {
    max-width: 1200px;
    margin: 40px auto;
    padding: 20px;
}

.autoblog-card {
    background: white;
    border-radius: 12px;
    padding: 30px;
    margin-bottom: 30px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.autoblog-card h2 {
    color: #333;
    margin-top: 0;
    padding-bottom: 15px;
    border-bottom: 3px solid #ffde59;
}

.status-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.status-item {
    background: linear-gradient(135deg, #ffde59 0%, #ffd633 100%);
    padding: 20px;
    border-radius: 8px;
    text-align: center;
}

.status-item h3 {
    margin: 0 0 10px 0;
    font-size: 14px;
    color: #666;
    font-weight: normal;
}

.status-item .value {
    font-size: 32px;
    font-weight: bold;
    color: #333;
}

.category-list {
    list-style: none;
    padding: 0;
}

.category-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px;
    border-bottom: 1px solid #eee;
}

.category-item:last-child {
    border-bottom: none;
}

.category-name {
    font-weight: bold;
    color: #333;
}

.category-progress {
    color: #666;
    font-size: 14px;
}

.progress-bar {
    width: 200px;
    height: 8px;
    background: #f0f0f0;
    border-radius: 4px;
    overflow: hidden;
    margin-left: 15px;
}

.progress-fill {
    height: 100%;
    background: linear-gradient(90deg, #ffde59 0%, #ffd633 100%);
    transition: width 0.3s;
}

.btn-primary {
    background: linear-gradient(135deg, #ffde59 0%, #ffd633 100%);
    color: #333;
    border: none;
    padding: 12px 24px;
    border-radius: 6px;
    font-weight: bold;
    cursor: pointer;
    text-decoration: none;
    display: inline-block;
    transition: transform 0.2s, box-shadow 0.2s;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(255, 222, 89, 0.4);
}

.info-box {
    background: #f8f9fa;
    border-left: 4px solid #ffde59;
    padding: 15px;
    margin: 20px 0;
    border-radius: 4px;
}

.info-box h4 {
    margin-top: 0;
    color: #333;
}

.info-box ul {
    margin-bottom: 0;
}
</style>

<div class="autoblog-settings">
    <div class="autoblog-card">
        <h2>📊 自動ブログ生成システム - ダッシュボード</h2>
        
        <?php
        // GitHubリポジトリのデータを読み込み（実際の環境では適切なパスに変更）
        $history_file = get_template_directory() . '/data/article_history.json';
        $design_file = get_template_directory() . '/data/seo_category_design.json';
        
        $history = file_exists($history_file) ? json_decode(file_get_contents($history_file), true) : null;
        $design = file_exists($design_file) ? json_decode(file_get_contents($design_file), true) : null;
        
        if ($history && $design):
            $total_articles = 0;
            $category_progress = $history['category_progress'] ?? [];
            
            foreach ($category_progress as $cat) {
                $total_articles += $cat['total_articles'] ?? 0;
            }
            
            $last_date = $history['last_article_date'] ?? '未実行';
            $last_category = $history['last_category'] ?? '未設定';
        ?>
        
        <div class="status-grid">
            <div class="status-item">
                <h3>総記事数</h3>
                <div class="value"><?php echo $total_articles; ?></div>
            </div>
            <div class="status-item">
                <h3>カテゴリー数</h3>
                <div class="value"><?php echo count($design['categories']); ?></div>
            </div>
            <div class="status-item">
                <h3>最終実行日</h3>
                <div class="value" style="font-size: 18px;"><?php echo $last_date; ?></div>
            </div>
            <div class="status-item">
                <h3>次回実行</h3>
                <div class="value" style="font-size: 18px;">毎日10:00</div>
            </div>
        </div>
        
        <?php else: ?>
        <div class="info-box">
            <h4>⚠️ データファイルが見つかりません</h4>
            <p>自動ブログ生成システムがまだセットアップされていないか、データファイルのパスが正しくありません。</p>
        </div>
        <?php endif; ?>
    </div>
    
    <?php if ($history && $design): ?>
    <div class="autoblog-card">
        <h2>📁 カテゴリー別進捗状況</h2>
        
        <ul class="category-list">
            <?php foreach ($design['categories'] as $category): 
                $slug = $category['slug'];
                $progress = $category_progress[$slug] ?? ['total_articles' => 0];
                $current = $progress['total_articles'];
                $target = $category['target_articles'];
                $percentage = $target > 0 ? ($current / $target) * 100 : 0;
            ?>
            <li class="category-item">
                <div>
                    <div class="category-name"><?php echo esc_html($category['name']); ?></div>
                    <div class="category-progress"><?php echo $current; ?> / <?php echo $target; ?> 記事</div>
                </div>
                <div class="progress-bar">
                    <div class="progress-fill" style="width: <?php echo min($percentage, 100); ?>%"></div>
                </div>
            </li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>
    
    <div class="autoblog-card">
        <h2>⚙️ システム設定</h2>
        
        <div class="info-box">
            <h4>📝 設定ファイルの編集</h4>
            <p>カテゴリーや記事の役割を変更するには、GitHubリポジトリの以下のファイルを編集してください：</p>
            <ul>
                <li><code>data/seo_category_design.json</code> - カテゴリーと記事役割の設計</li>
                <li><code>.github/workflows/auto-blog-post.yml</code> - 実行スケジュールの変更</li>
            </ul>
        </div>
        
        <div class="info-box">
            <h4>🔑 GitHub Secrets設定</h4>
            <p>以下の環境変数がGitHub Secretsに設定されている必要があります：</p>
            <ul>
                <li><code>OPENAI_API_KEY</code> - OpenAI APIキー</li>
                <li><code>WP_SITE_URL</code> - WordPressサイトURL</li>
                <li><code>WP_USER</code> - WordPressユーザー名</li>
                <li><code>WP_APP_PASSWORD</code> - アプリケーションパスワード</li>
            </ul>
        </div>
        
        <div style="margin-top: 30px;">
            <a href="https://github.com/kuuhaku1102/gachasoku/actions" target="_blank" class="btn-primary">
                GitHub Actions で手動実行
            </a>
        </div>
    </div>
    
    <div class="autoblog-card">
        <h2>📖 使い方ガイド</h2>
        
        <h3>自動実行について</h3>
        <p>このシステムは、GitHub Actionsで毎日午前10時（JST）に自動実行されます。記事の生成、投稿、内部リンクの挿入まで全て自動で行われます。</p>
        
        <h3>カテゴリーローテーション</h3>
        <p>記事は設定されたカテゴリーを順番にローテーションしながら生成されます。各カテゴリー内では、優先度の高い役割から順に記事が作成されます。</p>
        
        <h3>品質管理</h3>
        <p>生成された記事は6ステップの品質管理プロセスを経て、60点満点中50点以上の記事のみが投稿されます。</p>
        
        <h3>内部リンク</h3>
        <p>投稿済みの記事データベースを元に、関連記事への内部リンクが自動で挿入されます。記事が増えるほど、内部リンクの精度が向上します。</p>
    </div>
</div>

<?php get_footer(); ?>

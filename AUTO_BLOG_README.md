# 自動ブログ生成システム - セットアップガイド

## 📋 概要

このシステムは、OpenAI APIを使用してSEO最適化された記事を自動生成し、WordPress REST APIで投稿する完全自動化システムです。

### 主な機能

- ✅ **6ステップ品質管理**: 検索意図定義 → 見出し設計 → 本文生成 → 統合 → 品質ゲート → SEO最適化
- ✅ **カテゴリーローテーション**: 設定されたカテゴリーを順番に記事生成
- ✅ **内部リンク自動挿入**: 投稿済み記事への関連リンクを自動挿入
- ✅ **毎日自動実行**: GitHub Actionsで毎日午前10時（JST）に自動実行
- ✅ **2026年SEO準拠**: noindex設定、カテゴリハブ連携に対応

---

## 🚀 セットアップ手順

### 1. GitHub Secretsの設定

GitHubリポジトリの `Settings` > `Secrets and variables` > `Actions` に以下を登録：

| シークレット名 | 内容 | 取得方法 |
|---|---|---|
| `OPENAI_API_KEY` | OpenAI APIキー | [OpenAI Platform](https://platform.openai.com/api-keys)で生成 |
| `WP_SITE_URL` | WordPressサイトURL | 例: `https://gachasoku.example.com` |
| `WP_USER` | WordPressユーザー名 | 管理者または編集者権限のユーザー |
| `WP_APP_PASSWORD` | アプリケーションパスワード | WordPress管理画面で生成（下記参照） |

### 2. WordPressアプリケーションパスワードの生成

1. WordPress管理画面にログイン
2. `ユーザー` > `プロフィール` に移動
3. 「アプリケーションパスワード」セクションまでスクロール
4. 「新しいアプリケーションパスワード名」に `GitHub_Actions` と入力
5. 「新しいアプリケーションパスワードを追加」をクリック
6. 表示されたパスワード（例: `abcd efgh ijkl mnop qrst uvwx`）をコピー
7. GitHubの`WP_APP_PASSWORD`シークレットに登録

⚠️ **注意**: このパスワードは一度しか表示されないため、必ず控えてください。

### 3. WordPressカテゴリーの作成

WordPress管理画面で以下のカテゴリーを作成してください（スラッグも正確に設定）：

| カテゴリー名 | スラッグ | 説明 |
|---|---|---|
| ガチャ速報 | `gacha-news` | 最新のガチャ情報をいち早くお届け |
| 攻略情報 | `strategy` | ゲームの攻略方法や効率的なプレイ方法を解説 |
| キャンペーン情報 | `campaign` | 開催中のイベントやキャンペーン情報をまとめて紹介 |
| お役立ち情報 | `tips` | ゲームをより楽しむためのTipsや小ネタを紹介 |

### 4. 管理画面ページの作成（オプション）

1. WordPress管理画面で `固定ページ` > `新規追加`
2. タイトル: `自動ブログ設定`
3. テンプレート: `自動ブログ設定` を選択
4. 公開設定: `非公開` または `パスワード保護`
5. 公開

---

## 🎯 使い方

### 自動実行

システムは毎日午前10時（JST）に自動実行されます。特別な操作は不要です。

### 手動実行

1. GitHubリポジトリの `Actions` タブに移動
2. `Auto Blog Post - Daily SEO Articles` ワークフローを選択
3. `Run workflow` をクリック
4. `Run workflow` ボタンを再度クリック

### 実行ログの確認

1. `Actions` タブで実行中または完了したワークフローをクリック
2. `generate-and-post` ジョブをクリック
3. 各ステップのログを確認

---

## 📁 ファイル構成

```
gachasoku/
├── .github/
│   └── workflows/
│       └── auto-blog-post.yml          # GitHub Actionsワークフロー
├── scripts/
│   ├── auto_blog.py                    # メインオーケストレーター
│   ├── generate_article.py             # 記事生成エンジン
│   ├── post_to_wordpress.py            # WordPress投稿
│   └── internal_link_manager.py        # 内部リンク管理
├── data/
│   ├── seo_category_design.json        # カテゴリーと記事役割の設計
│   ├── article_history.json            # 記事生成履歴
│   ├── internal_links_db.json          # 内部リンクデータベース
│   └── articles/                       # 生成された記事（JSON）
└── page-autoblog-settings.php          # WordPress管理画面
```

---

## ⚙️ カスタマイズ

### カテゴリーと記事役割の変更

`data/seo_category_design.json` を編集してください。

**重要なフィールド:**
- `slug`: WordPressのカテゴリースラッグと一致させる
- `target_articles`: 目標記事数
- `article_roles`: 記事の役割と優先度
- `differentiation`: 差別化ポイント（必須）

### 実行スケジュールの変更

`.github/workflows/auto-blog-post.yml` の `cron` を編集してください。

```yaml
schedule:
  # 毎日午前10時（JST）= UTC 1:00
  - cron: '0 1 * * *'
```

**例:**
- 毎日午前9時: `'0 0 * * *'`
- 週3回（火・木・土）: `'0 1 * * 2,4,6'`
- 毎週月曜日: `'0 1 * * 1'`

### 使用するAIモデルの変更

`scripts/generate_article.py` の `MODEL` 変数を変更してください。

```python
# 利用可能なモデル
MODEL = "gpt-4.1-mini"      # デフォルト（コスパ重視）
MODEL = "gpt-4.1-nano"      # 最速・最安
MODEL = "gemini-2.5-flash"  # Gemini（OpenAI互換API経由）
```

### 品質基準の変更

`scripts/generate_article.py` の `PASS_SCORE` を変更してください。

```python
PASS_SCORE = 50  # デフォルト（60点満点中50点以上で合格）
```

---

## 🔍 トラブルシューティング

### エラー: `401 Unauthorized`

**原因**: WordPressのアプリケーションパスワードが無効

**解決方法**:
1. WordPressでアプリケーションパスワードを再生成
2. GitHub Secretsの`WP_APP_PASSWORD`を更新

### エラー: `KeyError: 'differentiation'`

**原因**: `seo_category_design.json`に`differentiation`フィールドがない

**解決方法**:
すべての`article_roles`に`differentiation`フィールドを追加

### エラー: `品質基準を満たしていません`

**原因**: 生成された記事の品質スコアが50点未満

**解決方法**:
- `PASS_SCORE`を下げる（推奨しない）
- プロンプトを改善する
- 再実行する（AIの生成結果は毎回異なります）

### 記事が投稿されない

**確認項目**:
1. GitHub Actionsのログを確認
2. WordPressカテゴリーのスラッグが正しいか確認
3. WordPress REST APIが有効か確認
4. アプリケーションパスワードが正しいか確認

---

## 📊 品質管理プロセス

### Step 1: 検索意図の定義
- 対象読者の特定
- 検索意図の明確化
- 記事のゴール設定

### Step 2: 見出し構造の設計
- H2見出し3〜5個
- 各H2にH3見出し2〜4個
- 論理的な構成

### Step 3: セクション単位での本文生成
- 各セクションを個別に生成
- 具体例や数値を含める
- トンマナの統一

### Step 4: 全文の統合
- 導入文とまとめの生成
- 全体の流れを整える

### Step 5: 品質ゲート
- 網羅性（0-15点）
- 独自性（0-15点）
- 具体性（0-15点）
- 読みやすさ（0-15点）
- **合計50点以上で合格**

### Step 6: SEO最適化
- タイトル案5つ
- メタディスクリプション
- FAQ生成

---

## 📈 運用のベストプラクティス

### 1. 定期的なモニタリング
- 週1回、GitHub Actionsのログを確認
- 投稿された記事の品質をチェック
- Search Consoleでインデックス状況を確認

### 2. カテゴリー設計の見直し
- 1ヶ月ごとに`seo_category_design.json`を見直し
- パフォーマンスの良いカテゴリーを優先
- 低パフォーマンスのカテゴリーは統合または削除

### 3. 内部リンクの最適化
- 記事が20〜30本蓄積されたら内部リンクの効果を確認
- 関連性の低いリンクがあれば、キーワード設定を見直し

### 4. コスト管理
- OpenAI APIの使用量を定期的に確認
- 1記事あたりの生成コストを把握
- 必要に応じてモデルを変更（gpt-4.1-mini → gpt-4.1-nano）

---

## 🎓 2026年SEO準拠について

このシステムは、2026年版SEO指示書に完全準拠しています：

- ✅ **評価の集中**: カテゴリーローテーションで評価を分散させない
- ✅ **低品質の排除**: 品質ゲートで50点未満の記事は投稿しない
- ✅ **明確な役割**: 1記事=1明確な役割、重複・詰め込み禁止
- ✅ **内部リンク構造**: 記事 → カテゴリ → 記事 の循環構造
- ✅ **差別化の明確化**: 各記事役割に差別化ポイントを設定

---

## 📞 サポート

問題が解決しない場合は、以下の情報を添えてお問い合わせください：

1. GitHub Actionsのログ（エラーメッセージ）
2. `data/seo_category_design.json`の内容
3. WordPressのバージョン
4. 実行環境（GitHub Actions / ローカル）

---

**作成日**: 2026年1月10日  
**バージョン**: 1.0  
**対象サイト**: ガチャ速（gachasoku）

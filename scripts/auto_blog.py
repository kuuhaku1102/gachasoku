#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
自動ブログ生成システム - メインオーケストレーター
記事生成、WordPress投稿、データ更新の全体フローを制御
"""

import json
import os
from datetime import datetime
from generate_article import ArticleGenerator
from post_to_wordpress import WordPressPublisher


class AutoBlogSystem:
    """自動ブログシステムクラス"""
    
    def __init__(self):
        # GitHub Actionsではscripts/から実行されるため、親ディレクトリを参照
        import os
        base_dir = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
        self.design_path = os.path.join(base_dir, 'data/seo_category_design.json')
        self.history_path = os.path.join(base_dir, 'data/article_history.json')
        
        self.design = self._load_json(self.design_path)
        self.history = self._load_json(self.history_path)
    
    def _load_json(self, path):
        """JSONファイルを読み込み"""
        try:
            with open(path, 'r', encoding='utf-8') as f:
                return json.load(f)
        except FileNotFoundError:
            if 'history' in path:
                return {
                    "last_category": None,
                    "last_article_date": None,
                    "category_progress": {}
                }
            raise
    
    def _save_json(self, path, data):
        """JSONファイルを保存"""
        with open(path, 'w', encoding='utf-8') as f:
            json.dump(data, f, ensure_ascii=False, indent=2)
    
    def run(self):
        """メイン処理"""
        print(f"\n{'='*60}")
        print(f"自動ブログ生成システム起動")
        print(f"実行日時: {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}")
        print(f"{'='*60}\n")
        
        # 次に生成する記事を決定
        category, role = self._determine_next_article()
        
        print(f"次の記事:")
        print(f"  カテゴリー: {category['name']}")
        print(f"  役割: {role['role']}")
        print(f"  優先度: {role['priority']}")
        
        # 記事を生成
        generator = ArticleGenerator(
            category,
            role,
            self.design['site_info']
        )
        article = generator.generate_article()
        
        # 記事を保存
        self._save_article(article)
        
        # WordPressに投稿
        publisher = WordPressPublisher()
        post = publisher.publish_article(article)
        
        # 履歴を更新
        self._update_history(category['slug'], role['priority'])
        
        print(f"\n{'='*60}")
        print("処理完了")
        print(f"{'='*60}")
        print(f"投稿URL: {post['link']}")
        print(f"品質スコア: {article['quality_score']}/60")
    
    def _determine_next_article(self):
        """次に生成する記事を決定"""
        last_category = self.history.get('last_category')
        category_progress = self.history.get('category_progress', {})
        
        # カテゴリーをローテーション
        categories = self.design['categories']
        
        if not last_category:
            # 初回は最初のカテゴリー
            selected_category = categories[0]
        else:
            # 前回のカテゴリーの次を選択
            current_index = next(
                (i for i, c in enumerate(categories) if c['slug'] == last_category),
                -1
            )
            next_index = (current_index + 1) % len(categories)
            selected_category = categories[next_index]
        
        # そのカテゴリー内で次の役割を決定
        progress = category_progress.get(selected_category['slug'], {})
        last_priority = progress.get('last_priority', 0)
        
        # 優先度順に役割を取得
        roles = sorted(
            selected_category['article_roles'],
            key=lambda r: r['priority']
        )
        
        # 次の優先度の役割を選択
        selected_role = None
        for role in roles:
            if role['priority'] > last_priority:
                selected_role = role
                break
        
        # 全ての役割を使い切った場合は最初に戻る
        if not selected_role:
            selected_role = roles[0]
        
        return selected_category, selected_role
    
    def _save_article(self, article):
        """生成された記事を保存"""
        import os
        base_dir = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
        timestamp = datetime.now().strftime('%Y%m%d_%H%M%S')
        filename = os.path.join(base_dir, f"data/articles/article_{timestamp}.json")
        
        os.makedirs(os.path.join(base_dir, 'data/articles'), exist_ok=True)
        
        with open(filename, 'w', encoding='utf-8') as f:
            json.dump(article, f, ensure_ascii=False, indent=2)
        
        print(f"\n[記事保存] {filename}")
    
    def _update_history(self, category_slug, priority):
        """履歴を更新"""
        self.history['last_category'] = category_slug
        self.history['last_article_date'] = datetime.now().strftime('%Y-%m-%d')
        
        if category_slug not in self.history['category_progress']:
            self.history['category_progress'][category_slug] = {
                'total_articles': 0,
                'last_priority': 0
            }
        
        self.history['category_progress'][category_slug]['total_articles'] += 1
        self.history['category_progress'][category_slug]['last_priority'] = priority
        
        self._save_json(self.history_path, self.history)
        
        print(f"\n[履歴更新] カテゴリー: {category_slug}, 優先度: {priority}")


def main():
    """メイン関数"""
    try:
        system = AutoBlogSystem()
        system.run()
    except Exception as e:
        print(f"\n❌ エラーが発生しました: {e}")
        import traceback
        traceback.print_exc()
        exit(1)


if __name__ == "__main__":
    main()

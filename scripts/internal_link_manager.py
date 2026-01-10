#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
内部リンク自動化マネージャー
投稿済み記事データベースを管理し、関連記事へのリンクを自動挿入
"""

import json
import re
from datetime import datetime


class InternalLinkManager:
    """内部リンク管理クラス"""
    
    def __init__(self, db_path=None):
        if db_path is None:
            import os
            base_dir = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
            db_path = os.path.join(base_dir, 'data/internal_links_db.json')
        self.db_path = db_path
        self.db = self._load_db()
    
    def _load_db(self):
        """データベースを読み込み"""
        try:
            with open(self.db_path, 'r', encoding='utf-8') as f:
                return json.load(f)
        except FileNotFoundError:
            return {"articles": []}
    
    def _save_db(self):
        """データベースを保存"""
        with open(self.db_path, 'w', encoding='utf-8') as f:
            json.dump(self.db, f, ensure_ascii=False, indent=2)
    
    def add_article(self, article_data, url):
        """新しい記事をデータベースに追加"""
        article_entry = {
            "id": f"article_{datetime.now().strftime('%Y%m%d_%H%M%S')}",
            "title": article_data['seo']['title_options'][0],
            "url": url,
            "category": article_data['category'],
            "keywords": article_data['seo'].get('keywords', []),
            "published_at": datetime.now().isoformat()
        }
        
        self.db['articles'].append(article_entry)
        self._save_db()
        
        print(f"[内部リンクDB] 記事を追加しました: {article_entry['title']}")
    
    def insert_internal_links(self, content_html, current_article, max_links=5):
        """
        記事本文に内部リンクを挿入
        
        Args:
            content_html: HTML形式の記事本文
            current_article: 現在の記事データ
            max_links: 最大リンク数
        
        Returns:
            内部リンクが挿入されたHTML
        """
        if len(self.db['articles']) == 0:
            print("[内部リンク自動挿入] データベースが空です")
            return content_html
        
        # 関連記事を検索
        related_articles = self._find_related_articles(current_article)
        
        if not related_articles:
            print("[内部リンク自動挿入] 関連記事が見つかりませんでした")
            return content_html
        
        # リンク挿入
        inserted_count = 0
        modified_content = content_html
        
        for article in related_articles[:max_links]:
            # キーワードでリンクを挿入
            for keyword in article['keywords']:
                if inserted_count >= max_links:
                    break
                
                # 単語境界でマッチング
                pattern = re.compile(r'\b(' + re.escape(keyword) + r')\b', re.IGNORECASE)
                
                # 最初のマッチを探す
                match = pattern.search(modified_content)
                
                if match:
                    start_pos = match.start()
                    
                    # リンク内かどうかをチェック
                    before_text = modified_content[:start_pos]
                    last_a_open = before_text.rfind('<a ')
                    last_a_close = before_text.rfind('</a>')
                    
                    # <a>の後に</a>がない場合はリンク内なのでスキップ
                    if last_a_open > last_a_close:
                        continue
                    
                    # リンクを挿入
                    link_html = f'<a href="{article["url"]}" class="internal-link">{match.group(1)}</a>'
                    modified_content = modified_content[:start_pos] + link_html + modified_content[match.end():]
                    
                    inserted_count += 1
                    print(f"[内部リンク自動挿入] リンクを挿入: {keyword} -> {article['title']}")
                    
                    break
        
        if inserted_count > 0:
            print(f"[内部リンク自動挿入] ✓ {inserted_count}個のリンクを挿入しました")
        else:
            print("[内部リンク自動挿入] ⚠ 内部リンクを挿入できませんでした")
        
        return modified_content
    
    def _find_related_articles(self, current_article):
        """関連記事を検索してスコアリング"""
        scored_articles = []
        
        for article in self.db['articles']:
            score = 0
            
            # カテゴリー一致
            if article['category'] == current_article['category']:
                score += 10
            
            # キーワード一致
            current_keywords = set(current_article.get('keywords', []))
            article_keywords = set(article['keywords'])
            common_keywords = current_keywords & article_keywords
            score += len(common_keywords) * 5
            
            if score > 0:
                scored_articles.append({
                    **article,
                    'score': score
                })
        
        # スコアが高い順にソート
        scored_articles.sort(key=lambda x: x['score'], reverse=True)
        
        return scored_articles


def main():
    """テスト用メイン関数"""
    manager = InternalLinkManager()
    
    # テスト用の記事データ
    test_article = {
        "category": "gacha-news",
        "keywords": ["ガチャ", "新キャラ", "確率"],
        "seo": {
            "title_options": ["テスト記事タイトル"]
        }
    }
    
    # データベースに追加
    manager.add_article(test_article, "https://example.com/test-article/")
    
    print(f"\nデータベース内の記事数: {len(manager.db['articles'])}")


if __name__ == "__main__":
    main()

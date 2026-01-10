#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
WordPress投稿スクリプト
生成された記事をWordPress REST APIで投稿し、内部リンクを挿入
"""

import os
import requests
import base64
import markdown
from internal_link_manager import InternalLinkManager


class WordPressPublisher:
    """WordPress投稿クラス"""
    
    def __init__(self):
        self.site_url = os.environ.get("WP_SITE_URL")
        self.username = os.environ.get("WP_USER")
        self.app_password = os.environ.get("WP_APP_PASSWORD")
        
        if not all([self.site_url, self.username, self.app_password]):
            raise ValueError("環境変数が設定されていません: WP_SITE_URL, WP_USER, WP_APP_PASSWORD")
        
        # Basic認証ヘッダー
        credentials = f"{self.username}:{self.app_password}"
        token = base64.b64encode(credentials.encode()).decode()
        self.headers = {
            "Authorization": f"Basic {token}",
            "Content-Type": "application/json"
        }
        
        # 内部リンクマネージャー
        self.link_manager = InternalLinkManager()
    
    def publish_article(self, article_data):
        """記事をWordPressに投稿"""
        print(f"\n{'='*60}")
        print("WordPress投稿開始")
        print(f"{'='*60}\n")
        
        # カテゴリーIDを取得
        category_id = self._get_category_id(article_data['category'])
        
        if not category_id:
            raise Exception(f"カテゴリーが見つかりません: {article_data['category']}")
        
        # MarkdownをHTMLに変換
        content_html = markdown.markdown(
            article_data['content'],
            extensions=['extra', 'nl2br']
        )
        
        # 内部リンクを挿入
        content_with_links = self.link_manager.insert_internal_links(
            content_html,
            article_data,
            max_links=5
        )
        
        # 投稿データを作成
        post_data = {
            "title": article_data['seo']['title_options'][0],
            "content": content_with_links,
            "status": "publish",
            "categories": [category_id],
            "meta": {
                "description": article_data['seo']['meta_description']
            }
        }
        
        # WordPress REST APIで投稿
        response = requests.post(
            f"{self.site_url}/wp-json/wp/v2/posts",
            headers=self.headers,
            json=post_data
        )
        
        if response.status_code in [200, 201]:
            post = response.json()
            print(f"✓ 記事を投稿しました")
            print(f"  タイトル: {post['title']['rendered']}")
            print(f"  URL: {post['link']}")
            
            # 内部リンクDBに追加
            self.link_manager.add_article(article_data, post['link'])
            
            return post
        else:
            raise Exception(f"WordPress投稿失敗: {response.status_code} - {response.text}")
    
    def _get_category_id(self, category_slug):
        """カテゴリースラッグからIDを取得"""
        response = requests.get(
            f"{self.site_url}/wp-json/wp/v2/categories",
            params={"slug": category_slug}
        )
        
        if response.status_code == 200:
            categories = response.json()
            if categories:
                return categories[0]['id']
        
        return None


def main():
    """テスト用メイン関数"""
    # テスト用の記事データ
    test_article = {
        "content": """# テスト記事

これはテスト記事です。

## セクション1

テスト本文です。

### サブセクション1-1

詳細な説明です。
""",
        "seo": {
            "title_options": ["テスト記事タイトル"],
            "meta_description": "これはテスト記事のメタディスクリプションです。"
        },
        "category": "gacha-news",
        "quality_score": 55
    }
    
    publisher = WordPressPublisher()
    result = publisher.publish_article(test_article)
    
    print(f"\n投稿ID: {result['id']}")


if __name__ == "__main__":
    main()

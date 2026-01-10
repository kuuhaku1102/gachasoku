#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
記事生成エンジン (OpenAI API使用)
6ステップの品質管理プロセスで高品質なSEO記事を生成
"""

import os
import json
import re
from datetime import datetime
from openai import OpenAI

# OpenAI APIクライアント初期化
client = OpenAI(api_key=os.environ.get("OPENAI_API_KEY"))

# 使用するモデル
MODEL = "gpt-4.1-mini"

# 品質ゲートの合格点
PASS_SCORE = 50


class ArticleGenerator:
    """記事生成クラス"""
    
    def __init__(self, category_data, role_data, site_info):
        self.category = category_data
        self.role = role_data
        self.site_info = site_info
        
    def generate_article(self):
        """6ステップで記事を生成"""
        print(f"\n{'='*60}")
        print(f"記事生成開始: {self.category['name']} - {self.role['role']}")
        print(f"{'='*60}\n")
        
        # Step 1: 検索意図の定義
        intent_data = self.step1_define_intent()
        
        # Step 2: 見出し構造の設計
        outline = self.step2_design_outline(intent_data)
        
        # Step 3: セクション単位での本文生成
        sections = self.step3_generate_sections(outline)
        
        # Step 4: 全文の統合
        full_article = self.step4_integrate_article(sections, outline)
        
        # Step 5: 品質ゲート
        quality_score = self.step5_quality_gate(full_article)
        
        if quality_score < PASS_SCORE:
            raise Exception(f"品質基準を満たしていません (スコア: {quality_score}/{PASS_SCORE})")
        
        # Step 6: SEO最適化パッケージ
        seo_package = self.step6_seo_optimization(full_article, intent_data)
        
        return {
            "content": full_article,
            "seo": seo_package,
            "quality_score": quality_score,
            "category": self.category['slug'],
            "role": self.role['role']
        }
    
    def step1_define_intent(self):
        """Step 1: 検索意図の定義"""
        print("[Step 1] 検索意図の定義...")
        
        prompt = f"""
あなたは{self.site_info['name']}のSEO編集長です。

以下の記事を企画します:
- カテゴリー: {self.category['name']}
- 記事の役割: {self.role['role']}
- 目的: {self.role['purpose']}
- 差別化ポイント: {self.role['differentiation']}

この記事で満たすべき「検索意図」を定義してください。

以下の形式でJSON形式で出力してください:
{{
  "target_audience": "誰に向けた記事か（例: ゲーム初心者、ガチャを引くか迷っている人）",
  "search_intent": "読者が何を知りたいのか（例: 新ガチャの性能、引くべきかの判断基準）",
  "goal": "この記事を読んだ後の読者の状態（例: 引くべきかどうか判断できる）",
  "keywords": ["主要キーワード1", "主要キーワード2", "主要キーワード3"]
}}
"""
        
        response = client.chat.completions.create(
            model=MODEL,
            messages=[{"role": "user", "content": prompt}],
            temperature=0.7
        )
        
        intent_text = response.choices[0].message.content.strip()
        # JSONを抽出
        intent_data = self._extract_json(intent_text)
        
        print(f"✓ 検索意図を定義しました")
        print(f"  対象読者: {intent_data['target_audience']}")
        print(f"  検索意図: {intent_data['search_intent']}")
        
        return intent_data
    
    def step2_design_outline(self, intent_data):
        """Step 2: 見出し構造の設計"""
        print("\n[Step 2] 見出し構造の設計...")
        
        prompt = f"""
以下の検索意図に基づいて、記事の見出し構造を設計してください。

検索意図:
{json.dumps(intent_data, ensure_ascii=False, indent=2)}

記事の役割: {self.role['role']}
差別化ポイント: {self.role['differentiation']}

要件:
- H2見出しを3〜5個作成
- 各H2の下にH3見出しを2〜4個作成
- 見出しは具体的で、読者の疑問に答える形にする
- {self.site_info['tone']}

以下の形式でJSON形式で出力してください:
{{
  "title": "記事タイトル（30〜40文字）",
  "sections": [
    {{
      "h2": "H2見出し",
      "h3_list": ["H3見出し1", "H3見出し2"]
    }}
  ]
}}
"""
        
        response = client.chat.completions.create(
            model=MODEL,
            messages=[{"role": "user", "content": prompt}],
            temperature=0.7
        )
        
        outline_text = response.choices[0].message.content.strip()
        outline = self._extract_json(outline_text)
        
        print(f"✓ 見出し構造を設計しました")
        print(f"  タイトル: {outline['title']}")
        print(f"  H2見出し数: {len(outline['sections'])}")
        
        return outline
    
    def step3_generate_sections(self, outline):
        """Step 3: セクション単位での本文生成"""
        print("\n[Step 3] セクション単位での本文生成...")
        
        sections = []
        
        for i, section in enumerate(outline['sections'], 1):
            print(f"  セクション {i}/{len(outline['sections'])}: {section['h2']}")
            
            prompt = f"""
以下の見出しに基づいて、本文を生成してください。

H2見出し: {section['h2']}
H3見出し: {', '.join(section['h3_list'])}

要件:
- H2見出しの後に導入文（2〜3文）を書く
- 各H3見出しの下に本文を書く（200〜400文字）
- 具体例や数値を含める
- {self.site_info['tone']}
- Markdown形式で出力

出力形式:
## {section['h2']}

導入文をここに書く。

### H3見出し1

本文をここに書く。

### H3見出し2

本文をここに書く。
"""
            
            response = client.chat.completions.create(
                model=MODEL,
                messages=[{"role": "user", "content": prompt}],
                temperature=0.7
            )
            
            section_content = response.choices[0].message.content.strip()
            sections.append(section_content)
        
        print(f"✓ {len(sections)}個のセクションを生成しました")
        
        return sections
    
    def step4_integrate_article(self, sections, outline):
        """Step 4: 全文の統合"""
        print("\n[Step 4] 全文の統合...")
        
        # 導入文を生成
        intro_prompt = f"""
以下のタイトルの記事の導入文を書いてください。

タイトル: {outline['title']}

要件:
- 3〜5文程度
- 読者の疑問や悩みを明示
- この記事で何が分かるかを明確に
- {self.site_info['tone']}
"""
        
        response = client.chat.completions.create(
            model=MODEL,
            messages=[{"role": "user", "content": intro_prompt}],
            temperature=0.7
        )
        
        intro = response.choices[0].message.content.strip()
        
        # まとめを生成
        summary_prompt = f"""
以下のタイトルの記事のまとめを書いてください。

タイトル: {outline['title']}

要件:
- 3〜5文程度
- 記事の要点を簡潔にまとめる
- 読者への行動喚起を含める
- {self.site_info['tone']}
"""
        
        response = client.chat.completions.create(
            model=MODEL,
            messages=[{"role": "user", "content": summary_prompt}],
            temperature=0.7
        )
        
        summary = response.choices[0].message.content.strip()
        
        # 全文を統合
        full_article = f"""# {outline['title']}

{intro}

{chr(10).join(sections)}

## まとめ

{summary}
"""
        
        print(f"✓ 全文を統合しました（文字数: {len(full_article)}）")
        
        return full_article
    
    def step5_quality_gate(self, article):
        """Step 5: 品質ゲート（自動審査）"""
        print("\n[Step 5] 品質ゲート...")
        
        prompt = f"""
以下の記事を60点満点で採点してください。

記事:
{article}

採点基準:
1. 網羅性（0-15点）: 検索意図を満たしているか
2. 独自性（0-15点）: 他の記事との差別化ができているか
3. 具体性（0-15点）: 具体例や数値が含まれているか
4. 読みやすさ（0-15点）: 構成が分かりやすく、文章が読みやすいか

以下の形式でJSON形式で出力してください:
{{
  "scores": {{
    "coverage": 点数,
    "uniqueness": 点数,
    "specificity": 点数,
    "readability": 点数
  }},
  "total_score": 合計点,
  "feedback": "改善点や良い点"
}}
"""
        
        response = client.chat.completions.create(
            model=MODEL,
            messages=[{"role": "user", "content": prompt}],
            temperature=0.3
        )
        
        quality_text = response.choices[0].message.content.strip()
        quality_data = self._extract_json(quality_text)
        
        total_score = quality_data['total_score']
        
        print(f"✓ 品質スコア: {total_score}/60")
        print(f"  網羅性: {quality_data['scores']['coverage']}/15")
        print(f"  独自性: {quality_data['scores']['uniqueness']}/15")
        print(f"  具体性: {quality_data['scores']['specificity']}/15")
        print(f"  読みやすさ: {quality_data['scores']['readability']}/15")
        
        return total_score
    
    def step6_seo_optimization(self, article, intent_data):
        """Step 6: SEO最適化パッケージ"""
        print("\n[Step 6] SEO最適化パッケージ...")
        
        prompt = f"""
以下の記事に対して、SEO最適化パッケージを作成してください。

記事:
{article[:1000]}...

検索意図:
{json.dumps(intent_data, ensure_ascii=False, indent=2)}

以下を生成してください:
1. タイトル案5つ（30〜40文字、キーワードを含む）
2. メタディスクリプション（120文字以内）
3. FAQ（3〜5個）

以下の形式でJSON形式で出力してください:
{{
  "title_options": ["タイトル案1", "タイトル案2", "タイトル案3", "タイトル案4", "タイトル案5"],
  "meta_description": "メタディスクリプション",
  "faq": [
    {{
      "question": "質問",
      "answer": "回答"
    }}
  ]
}}
"""
        
        response = client.chat.completions.create(
            model=MODEL,
            messages=[{"role": "user", "content": prompt}],
            temperature=0.7
        )
        
        seo_text = response.choices[0].message.content.strip()
        seo_package = self._extract_json(seo_text)
        
        print(f"✓ SEO最適化パッケージを作成しました")
        print(f"  タイトル案: {len(seo_package['title_options'])}個")
        print(f"  FAQ: {len(seo_package['faq'])}個")
        
        return seo_package
    
    def _extract_json(self, text):
        """テキストからJSONを抽出"""
        # コードブロックを除去
        text = re.sub(r'```json\s*', '', text)
        text = re.sub(r'```\s*', '', text)
        
        try:
            return json.loads(text)
        except json.JSONDecodeError as e:
            print(f"JSON解析エラー: {e}")
            print(f"テキスト: {text}")
            raise


def main():
    """テスト用メイン関数"""
    # テスト用のデータを読み込み
    with open('../data/seo_category_design.json', 'r', encoding='utf-8') as f:
        design = json.load(f)
    
    site_info = design['site_info']
    category = design['categories'][0]
    role = category['article_roles'][0]
    
    generator = ArticleGenerator(category, role, site_info)
    result = generator.generate_article()
    
    print(f"\n{'='*60}")
    print("生成完了")
    print(f"{'='*60}")
    print(f"品質スコア: {result['quality_score']}/60")
    print(f"タイトル: {result['seo']['title_options'][0]}")


if __name__ == "__main__":
    main()

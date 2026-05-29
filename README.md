# Rapls AI Chatbot

WordPressサイトに AI チャットボットを導入できるプラグインです。RAG（Retrieval-Augmented Generation）で自サイトの記事内容を踏まえた回答ができます。

📖 **詳しい解説記事**: [Rapls AI Chatbot開発者が解説｜なぜ作ったか・RAGの設計判断・つまずきポイントまで](https://raplsworks.com/rapls-ai-chatbot-guide/)

## Features

- **OpenAI / Claude / Gemini / OpenRouter（100+モデル）** マルチプロバイダー対応
- **RAG ベースの応答** — サイト内記事を学習させたハイブリッド検索（ベクトル埋め込み＋キーワード）
- **ナレッジベース** — Q&A・自由記述・PDF/DOCXアップロード
- **Web検索** — 各プロバイダーのビルトイン検索を自動利用
- **MCP Server** — 7ツール内蔵（Claude Desktop / Cursor / VS Code 対応）
- **WordPress 7.0 Connectors API 対応** — サイト全体で AI プロバイダを一元管理
- **Gutenberg ブロック対応** — ビジュアルエディタで配置
- **ノーコード設定** — 管理画面で運用が完結
- **多言語対応** — i18n 準備完了
- **Free版と Pro版** — 基本機能は無料で利用可能

## Installation

### WordPress.org から（推奨）

1. WordPress管理画面 → **プラグイン** → **新規追加**
2. 「Rapls AI Chatbot」で検索 → **インストール**
3. 有効化後、管理画面の **Rapls AI Chatbot** メニューから設定

### GitHub から

1. [Releases](../../releases) から最新版の ZIP をダウンロード
2. WordPress管理画面 → **プラグイン** → **新規追加** → **プラグインのアップロード**
3. 有効化

## セットアップ

### 前提条件

- **WordPress 6.3以上**（WordPress 7.0+ で Connectors API 統合推奨）
- **PHP 7.4以上**
- **API キー** — OpenAI / Anthropic Claude / Google Gemini / OpenRouter のいずれか

### クイックスタート

プラグイン設定画面で AI プロバイダを選択。WordPress 7.0 以上なら Connectors API を通じてキーを登録（推奨）。

詳しくは [実装ガイド](https://raplsworks.com/rapls-ai-chatbot-guide/) を参照。

## よくある質問 / トラブルシューティング

### Q: チャット履歴が保存されない・突然消える

**A:** WP AI Client を使用している場合、メッセージのマーシャリング処理で履歴が無言で失われることがあります。

👉 詳細な診断方法: [WP AI Clientへ移行したら、エラーも出さずに会話の履歴が消えた](https://raplsworks.com/wp-ai-client-history-marshalling/)

### Q: フロントエンドでチャットボックスが表示されない

**A:** Gutenberg ブロックの挿入後、ページを**パーマリンク構造で再保存**してください。REST API キャッシュが更新されます。

### Q: 複数の AI プロバイダを使い分けたい

**A:** WordPress 7.0 以上なら、Connectors API で複数プロバイダを登録し、このプラグインから選択できます。

👉 [WordPress 7.0 の WP AI Client を実装目線で読み解いた話 - Connectors UI、wp_ai_client_prompt() API、Rapls AI Chatbot での対応方針](https://raplsworks.com/wp-ai-client-wordpress-7-0/)

### Q: PDF・DOCX をナレッジベースに追加したい

**A:** Pro版で対応しています。詳しくは [Pro版の機能一覧](https://raplsworks.com/rapls-ai-chatbot-guide/) を参照。

---

## WordPress 7.0 での変更点

**WordPress 7.0 Armstrong（2026年5月リリース）** 以降、このプラグインは WP AI Client と Connectors API に対応しました。

### 何が変わったか

- **API キー管理の一元化** — Connectors UI で複数 AI プロバイダを一箇所で管理
- **プロバイダ切り替えが簡単** — このプラグイン側で設定不要、サイト全体の設定から反映
- **後方互換性** — WordPress 6.3〜6.5 でも従来の API キー設定方式で動作

👉 [WordPress 7.0 対応の詳細](https://raplsworks.com/wp-ai-client-wordpress-7-0/)

---

## Documentation

- [実装ガイド｜開発判断・RAG設計・つまずきポイント](https://raplsworks.com/rapls-ai-chatbot-guide/)
- [WordPress.org プラグインページ](https://wordpress.org/plugins/rapls-ai-chatbot/)

## Pro版

有料の Pro版では、以下のような高度な機能が利用できます。

- **アナリティクス** — 利用状況・満足度・FAQ ランキング・チャーン分析
- **リードキャプチャ・Webhook 連携** — 顧客データの自動抽出
- **会話シナリオ** — マルチステップの誘導フロー
- **WooCommerce 連携** — 商品データ自動クロール・商品カード表示
- **LINE Messaging API 連携** — LINE での利用が可能
- **音声入出力** — STT / TTS 対応
- **ホワイトラベル** — カスタムフォント・季節テーマ
- **Slack 通知・Google Sheets エクスポート**
- **データ暗号化** — AES-256-GCM・PII マスキング
- **マルチサイト対応**
- ほか 80 以上の機能を搭載しています。

👉 [Pro版の詳細](https://raplsworks.com/rapls-ai-chatbot-guide/)

---

## Development

### Requirements

- WordPress 6.3以上
- PHP 7.4以上
- OpenAI / Anthropic / Google Gemini / OpenRouter いずれかの API キー

### Contributing

バグ報告・機能要望は [Issues](../../issues) までお願いします。Pull Request も歓迎です。

### Development Environment

- Local by Flywheel または DDEV での WordPress 開発環境
- GitHub Actions で自動テスト実行（PHP 7.4 / 8.0 / 8.3 対応確認）

詳しくは [WordPress プラグイン開発ガイド](https://raplsworks.com/wordpress-plugin-development-guide/) を参照。

## Changelog

詳細は [readme.txt](./readme.txt) をご覧ください。

## Author

**Rapls（ラプルス）**  
フリーランス Web 開発者 / WordPress Polyglots PTE（日本語翻訳責任者）

- 🌐 [Rapls Works](https://raplsworks.com/)
- 📋 [WordPress.org プロフィール](https://profiles.wordpress.org/rapls/)
- 🐙 [GitHub](https://github.com/raplsworks)

## License

GPL v2 or later

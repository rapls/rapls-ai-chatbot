# Rapls AI Chatbot

WordPressサイトに AI チャットボットを導入できるプラグインです。RAG（Retrieval-Augmented Generation）で自サイトの記事内容を踏まえた回答ができます。

📖 **詳しい解説記事**: [Rapls AI Chatbot開発者が解説｜なぜ作ったか・RAGの設計判断・つまずきポイントまで](https://raplsworks.com/rapls-ai-chatbot-guide/)

## Features

- OpenAI / Claude / Gemini / OpenRouter（100+モデル）マルチプロバイダー対応
- サイト内記事を学習させた RAG ベースの応答（ベクトル埋め込み＋キーワードのハイブリッド検索）
- ナレッジベース（Q&A・自由記述・PDF/DOCXアップロード）
- Web検索（各プロバイダーのビルトイン検索を自動利用）
- MCP Server（7ツール内蔵、Claude Desktop / Cursor / VS Code 対応）
- Gutenberg ブロック対応
- 設定画面で運用が完結（ノーコード）
- 多言語対応（i18n）
- Free版と Pro版あり

## Installation

### WordPress.org から（推奨）

WordPress管理画面 → プラグイン → 新規追加 → 「Rapls AI Chatbot」で検索

### GitHub から

Releases から最新版の ZIP をダウンロード → プラグイン → 新規追加 → プラグインのアップロード

## Documentation

- [プラグインの使い方と設定方法](https://raplsworks.com/rapls-ai-chatbot-guide/)
- [WordPress.org プラグインページ](https://wordpress.org/plugins/rapls-ai-chatbot/)

## Pro版

有料の Pro版では、以下のような高度な機能が利用できます。

- アナリティクス（利用状況・満足度・FAQ ランキング・チャーン分析）
- リードキャプチャ・Webhook 連携
- 会話シナリオ（マルチステップの誘導フロー）
- WooCommerce 連携（商品データ自動クロール・商品カード表示）
- LINE Messaging API 連携
- 音声入出力（STT / TTS）
- ホワイトラベル・カスタムフォント・季節テーマ
- Slack 通知・Google Sheets エクスポート
- データ暗号化（AES-256-GCM）・PII マスキング
- マルチサイト対応

ほか 80 以上の機能を搭載しています。

👉 [Pro版の詳細はこちら](https://raplsworks.com/rapls-ai-chatbot-guide/)

## Development

### Requirements

- WordPress 6.3以上
- PHP 7.4以上
- OpenAI / Anthropic / Google Gemini / OpenRouter いずれかの API キー

### Contributing

バグ報告・機能要望は [Issues](../../issues) までお願いします。Pull Request も歓迎です。

## Changelog

詳細は [readme.txt](./readme.txt) をご覧ください。

## Author

**Rapls（ラプルス）**
フリーランスWeb開発者 / WordPress Polyglots PTE

- ブログ: [Rapls Works](https://raplsworks.com/)
- WordPress.org: [プロフィール](https://profiles.wordpress.org/rapls/)

## License

GPL v2 or later

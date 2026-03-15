# 審査アドバイスへの対応報告

ご指摘いただいた全項目について対応いたしました。以下に変更内容をご報告します。

---

## 1. Free版の月500回制限 / KB 20件制限 — 撤廃済み

**ご指摘**: Free版の `FREE_MESSAGE_LIMIT = 500` と `FREE_FAQ_LIMIT = 20` がtrialwareに該当するリスク。

**対応**: 両制限を `PHP_INT_MAX`（実質無制限）に変更しました。ユーザーは自身のAPIキーとAPI料金を負担する設計のため、プラグイン側で人工的な回数制限を設ける合理性がないという判断です。

**変更ファイル**:
- `includes/class-pro-features.php` — 定数を `PHP_INT_MAX` に変更
- `includes/admin/class-admin.php` — `message_limit_notice()` を空実装に
- `templates/admin/dashboard.php` — 制限到達警告と月間AI応答カードを削除、Pro通知を1件の控えめなdismissible noticeに集約
- `templates/admin/knowledge.php` — FAQ制限表示を削除
- `readme.txt` — 「500/月」「20件」等の制限記述を全て削除

**Proとの差別化**: Free版は全AI機能が無制限。Proはアナリティクス、リード獲得、シナリオ、オペレーターモード、WooCommerce連携、LINE連携等の「業務運用機能」で差別化します。

---

## 2. Powered by リンク — デフォルト非表示に変更済み

**ご指摘**: フロント公開サイト上の "Powered by Rapls Works" リンクがデフォルト表示で、非表示がPro課金導線になっている。

**対応**:
- **デフォルトでフッターを完全非表示**にしました
- Free版の「Hide footer」チェックボックスを**廃止**しました
- Pro版のホワイトラベル機能で**カスタムフッターを追加（opt-in）**する設計に変更しました

**変更ファイル**:
- `templates/frontend/chatbot-widget.php` — "Powered by" 出力を完全削除、Proホワイトラベルフッターのみ残存（opt-in）
- `templates/admin/settings.php` — 「Hide footer」チェックボックスを削除
- `includes/admin/class-admin.php` — `hide_powered_by` のサニタイズ処理を削除

---

## 3. Pro訴求量 — 大幅削減済み

**ご指摘**: ダッシュボードの notice が多すぎる、readme.txt の Pro 列挙が長すぎる。

**対応**:
- ダッシュボードのnoticeを**1件のdismissible info notice**に集約（従来: エラー警告 + info notice の2本立て）
- テキストを控えめに変更: `Unlock analytics, lead capture, scenarios, and more with Pro.`
- readme.txt の Free vs Pro セクションを**約80行から20行に圧縮**
- Pro専用REST APIエンドポイント一覧を削除（「See the Pro documentation for details」に置換）
- Pro専用DBテーブル（user_context, audit_log）の記載を削除

---

## 4. readme.txt の記述整理 — 対応済み

**ご指摘**: 将来モデル名の断定、Pro機能の列挙量、外部通信まわりの説明。

**対応**:
- Free vs Pro 比較を「Free でできること」「Pro で追加されること」の2ブロックに簡潔化
- AIモデル一覧は現行モデルのみ記載（変更なし — ユーザーの選択判断材料として必要）
- External Services / Privacy セクションは WordPress.org 必須のため維持
- FAQ の回数制限関連の記述を更新（「人工的な制限はない」旨に変更）

---

## 5. Chart.js ライセンス — 同梱済み

**ご指摘**: `assets/vendor/chart.js/chart.umd.min.js` のソース・ライセンス案内が不足。

**対応**:
- `assets/vendor/chart.js/LICENSE.md` を新規追加（MIT License、ソースURL記載）
- `readme.txt` の Development セクションに Credits を追加: `Chart.js (MIT License)`

---

## 6. messages.mo — 削除済み

**ご指摘**: ルートに `messages.mo` がある。

**対応**: Pro版の翻訳ファイルがFreeプラグインのルートに紛れ込んでいたものでした。WordPressはこの場所からは読み込まないため、削除しました。正規の翻訳ファイルは `languages/rapls-ai-chatbot-ja.mo` に存在します。

---

## 対応後の状態

| 項目 | 対応前 | 対応後 |
|------|--------|--------|
| AI応答制限 | 500回/月 | 無制限 |
| KB制限 | 20件 | 無制限 |
| Powered by | デフォルト表示 | デフォルト非表示 |
| Hide footer設定 | Free版にあり（実質Pro課金導線） | 廃止 |
| ダッシュボードnotice | 2件（error + info） | 1件（dismissible info） |
| readme Pro列挙 | 約80行 | 約20行 |
| Chart.js LICENSE | なし | 同梱 |
| messages.mo | ルートに存在 | 削除 |

## Pro差別化ポイント（制限撤廃後）

Free版を無制限にした上で、Proは以下の「業務運用機能」で差別化しています：

- **アナリティクス**: 会話分析、満足度、FAQ ランキング、PDF レポート
- **リード獲得**: フォーム、Webhook、CSV/JSON エクスポート、Google Sheets
- **自動化**: シナリオ、アクション、営業時間、祝日カレンダー
- **運用**: オペレーターモード、ハンドオフ、複数ボット、キュー管理
- **連携**: WooCommerce、LINE、Slack、予約システム
- **セキュリティ**: 暗号化、PII マスキング、監査ログ、脆弱性スキャン
- **テーマ**: 10 追加テーマ、ダークモード、カスタムフォント

これはWordPress.orgガイドラインの「機能制限ではなく機能追加で差別化する」方針に沿っています。

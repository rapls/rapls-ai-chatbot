# WordPress.org レビュー指摘 修正計画

**作成日**: 2026-03-25
**レビューID**: AUTOPREREVIEW rapls-ai-chatbot
**対象**: rapls-ai-chatbot (Free版) v1.5.1

---

## 指摘事項の概要と優先度

| # | 指摘 | 重要度 | 影響範囲 | 工数 |
|---|------|--------|----------|------|
| 1 | トライアルウェア / ロック機能の禁止 | 🔴 必須 | テンプレート・CSS・メニュー | 中 |
| 2 | インライン JS/CSS を wp_enqueue 化 | 🔴 必須 | 6ファイル・19箇所 | 大 |
| 3 | プレフィックス `wpaic_` → 独自プレフィックスへ変更 | 🔴 必須 | 全60ファイル・~1,900箇所 | 特大 |
| 4 | Chart.js のバージョン更新 | 🔴 必須 | 1ファイル | 小 |
| 5 | html2canvas CDN → ローカル化 | 🔴 必須 | chatbot.js 1箇所 | 小 |
| 6 | REST API permission_callback を専用メソッド化 | 🔴 必須 | REST controller | 中 |
| 7 | editor.asset.php に ABSPATH チェック追加 | 🔴 必須 | 1ファイル・1行 | 極小 |
| 8 | GPL 準拠・外部サービスの readme.txt 記載 | 🟡 推奨 | readme.txt | 小 |

> **社内レビュアー所見**: これは拒否ではなく修正依頼つきの保留。指摘は具体的で進めやすい部類。
> 反論より修正優先。一部だけ直して返すのが一番損。全部直してから短く返す。

### 新プレフィックスの決定

社内レビュアー推奨に基づき、以下に決定:

| 用途 | 旧 | 新 |
|------|-----|-----|
| 定数・クラス | `WPAIC_` | `RAPLSAICH_` |
| 関数・オプション・フック | `wpaic_` | `raplsaich_` |
| CSS クラス・HTML ID | `wpaic-` | `raplsaich-` |
| JS グローバル（admin） | `wpaicAdmin` | `raplsaichAdmin` |
| JS グローバル（frontend） | `wpAiChatbotConfig` | `raplsaichConfig` |
| REST namespace | `wp-ai-chatbot/v1` | `rapls-ai-chatbot/v1` |
| DB テーブル | `{prefix}aichat_` | `{prefix}raplsaich_` |
| ショートコード | `wpaic_chatbot` | `raplsaich_chatbot` |

---

## Phase 1: 即時修正（小規模・独立）

### 1-1. ABSPATH チェック追加

**ファイル**: `includes/block/editor.asset.php`（指摘あり）
**作業**: ファイル先頭に以下を追加:
```php
<?php
if ( ! defined( 'ABSPATH' ) ) exit;
```

**横展開**: 同種ファイルを全チェック（`*.asset.php`, `views/*.php`, `partials/*.php` 等）
```bash
grep -rL "defined.*ABSPATH" includes/ templates/ --include="*.php" | head -20
```
**工数**: 15分

### 1-2. html2canvas をローカルに含める

**ファイル**: `assets/js/chatbot.js` (line 2356)
**作業**:
1. html2canvas v1.4.1 を `assets/vendor/html2canvas/` にダウンロードして配置
2. `chatbot.js` の CDN URL をローカルパスに変更
3. または `wp_enqueue_script` で事前登録し、必要時にロード

**工数**: 30分

### 1-3. Chart.js を最新安定版に更新

**ファイル**: `assets/vendor/chart.js/chart.umd.min.js`
**作業**: Chart.js v4.4.8（最新安定版）をダウンロードして差し替え
**工数**: 15分

### 1-4. readme.txt に外部サービス説明を追加

**ファイル**: `readme.txt`
**作業**: 以下の外部サービス接続を明記:
- OpenAI API（AI応答生成）
- Anthropic Claude API（AI応答生成）
- Google Gemini API（AI応答生成）
- OpenRouter API（AIルーティング）
- Google reCAPTCHA v3（スパム防止）

各サービスの利用規約・プライバシーポリシーURLを記載

**工数**: 30分

---

## Phase 2: トライアルウェア / ロック機能の除去

### 2-1. 方針

WordPress.org ガイドライン5により、Free版にロックされたPro機能のUI（disabled チェックボックス、
ロックアイコン付きフォーム）を含めることは禁止。

**採用する方式: HTMLモックアップ方式（案3）**

既存の upsell プレビューHTML（~1,200行）を活かし、**フォームコントロール要素だけを除去**する。
テーブル、カード、統計表示等のレイアウトHTMLはそのまま残す。

**メリット**:
- 画像ファイルの管理が不要（撮影・圧縮・多言語版が不要）
- 多言語は `__()` 翻訳関数で自動対応（英語/日本語の切替が自然）
- バージョンアップ時もHTMLを更新するだけ
- 既存コードの大部分を活用できる（工数最小）

**ルール**: 以下のフォーム要素は一切含めない
- `<input>`, `<select>`, `<textarea>`, `<button type="submit">`
- `disabled` 属性
- `pointer-events: none` でグレーアウトした実フォーム
- `dashicons-lock` ロックアイコン

**許可される要素**:
- `<div>`, `<table>`, `<span>`, `<p>`, `<h2>` 等のレイアウト要素
- サンプルデータを含む読み取り専用のテーブル（td にテキスト表示のみ）
- 統計カード（数値表示のみ）
- チャートのプレースホルダー（div + 背景色のみ）
- PRO バッジ（案内用、ロック表現ではない）
- Upgrade バナー + リンク

### 2-2. 修正対象一覧

#### A. 設定画面の個別 Pro 機能

| 場所 | ファイル:行 | 変更前 | 変更後 |
|------|-----------|--------|--------|
| Pro テーマ | settings.php:963-982 | disabled ラジオボタン10個 + ロックアイコン | テーマ名テキスト一覧 + Upgrade リンク |
| Badge Icon | settings.php:1057-1101 | ロックアイコン + Upgrade テキスト | 機能説明テキスト + Upgrade リンク |
| Dark Mode | settings.php:1115-1131 | disabled チェックボックス + ロックアイコン | 機能説明テキスト + Upgrade リンク |
| Enhanced Extraction | crawler.php:277-295 | disabled チェックボックス + ロックアイコン | 対応要素テーブル（読取専用）+ Upgrade リンク |
| Export | conversations.php:134-145 | disabled 入力欄3個 + disabled ボタン | 機能説明テキスト + Upgrade リンク |

**変更後のHTMLパターン（各箇所共通）**:

```php
<?php if (!$is_pro_active): ?>
<div class="raplaich-pro-preview" style="background:#f8f9ff;border:1px solid #e0e5f6;border-radius:6px;padding:16px;margin-top:8px;">
    <p style="margin:0 0 8px;">
        <span class="dashicons dashicons-star-filled" style="color:#667eea;vertical-align:text-bottom;"></span>
        <?php printf(
            /* translators: %s: URL to Pro page */
            __('This feature is available in <a href="%s" target="_blank">Rapls AI Chatbot Pro</a>.', 'rapls-ai-chatbot'),
            'https://raplsworks.com/rapls-ai-chatbot-pro/'
        ); ?>
    </p>
    <!-- ここに読取専用のモックアップHTML（テーブル、カード等）を必要に応じて配置 -->
</div>
<?php endif; ?>
```

#### B. メニュー項目とプレビューページ（class-admin.php）

**現状**: 6個のメニュー項目が PRO バッジ付き → `render_pro_upsell_page()` を表示（disabled フォーム含む）

**変更方針**: 既存のプレビューHTMLから **フォーム要素のみ除去**。レイアウトは維持。

| メニュー項目 | 変更内容 |
|-------------|---------|
| Pro Settings | Upgrade バナー + 機能一覧カード（フォームなし） |
| Analytics | Upgrade バナー + 統計カード + チャートプレースホルダー（フォームなし） |
| Leads | Upgrade バナー + 統計カード + サンプルテーブル（フォームなし） |
| Audit Log | Upgrade バナー + サンプルテーブル（フォームなし） |
| Site Learning | Free版で実動作。Enhanced Extraction のみテキスト案内 |
| Conversations | Free版で実動作。Export のみテキスト案内 |

**具体的な除去ルール（既存HTMLに対する変換）**:

```
除去するもの:
  <input ...>           → 完全除去
  <select ...>...</select> → 完全除去
  <textarea ...>...</textarea> → 完全除去
  <button type="submit">  → 完全除去
  disabled="disabled"     → 属性自体を除去（残った要素があれば）
  disabled               → 属性自体を除去
  class="wpaic-pro-locked" → クラスを除去
  dashicons-lock         → dashicons-star-filled に変更
  pointer-events: none   → 除去

残すもの:
  <div>, <table>, <tr>, <td>, <th>  → そのまま
  <span class="badge-*">           → そのまま（装飾用）
  <h2>, <h3>, <p>                   → そのまま
  統計カードの数値表示               → そのまま（テキスト）
  サンプルデータ行                   → そのまま（テキスト）
  Upgrade バナー                     → そのまま
```

**例: Analytics プレビュー（変更前 → 変更後）**:

```
変更前: <input type="text" disabled value="2026-01-01"> ← 除去
変更後: （なし）

変更前: <button disabled>Download PDF</button> ← 除去
変更後: （なし）

変更前: <div class="stat-card"><div class="num">1,234</div><div class="label">Conversations</div></div>
変更後: <div class="stat-card"><div class="num">1,234</div><div class="label">Conversations</div></div> ← そのまま
```

#### C. CSS クリーンアップ

| ファイル | 除去対象 |
|---------|---------|
| `assets/css/admin.css` | `.wpaic-pro-locked`, `.wpaic-pro-overlay` 関連スタイル |
| `assets/css/admin-menu.css` | 非アクティブ PRO メニューの opacity 制御（lines 16-22） |

維持:
- `.wpaic-pro-badge-small` — 案内バッジとして使用
- `.wpaic-pro-menu-badge` — メニューバッジとして使用
- `.wpaic-pro-preview-wrapper` — リネームして装飾用に使用（opacity/grayscale は除去）

### 2-3. 作業手順

1. **設定画面の個別ロック除去**（settings.php, crawler.php, conversations.php）
   - disabled フォーム要素を除去し、テキスト案内 + Upgrade リンクに置換
   - 5箇所、各箇所10-30行程度の変更

2. **upsell ページのフォーム要素除去**（class-admin.php）
   - 既存の ~1,200行のプレビューHTMLを走査
   - `<input>`, `<select>`, `<textarea>`, `<button>` タグを除去
   - `disabled` 属性を除去
   - ロックアイコンを星アイコンに変更
   - Upgrade バナーは維持

3. **CSS クリーンアップ**
   - `.wpaic-pro-locked` 関連スタイルを除去
   - `.wpaic-pro-overlay` 関連スタイルを除去
   - メニューの opacity 制御を除去

4. **テスト**
   - 全管理画面を確認
   - `grep -rn 'disabled' templates/ includes/admin/` で残存チェック
   - Pro 版有効時に実際のUIに正しく差し替わること

### 2-4. 検証チェックリスト

- [ ] Free版に disabled な `<input>`, `<checkbox>`, `<select>`, `<button>` が一切ないこと
- [ ] Free版に `pointer-events: none` でグレーアウトした実フォームがないこと
- [ ] Free版に `dashicons-lock` ロックアイコンがないこと（Pro案内以外）
- [ ] Upgrade リンクが正しい URL に飛ぶこと
- [ ] Pro 版インストール時にプレビューが実際のUIに差し替わること
- [ ] 多言語（日本語/英語）でプレビューテキストが正しく表示されること
- [ ] `grep -rn '<input.*disabled\|<select.*disabled\|<button.*disabled' templates/ includes/admin/` で Pro 関連の disabled が残っていないこと

**工数**: 2-3時間

---

## Phase 3: REST API permission_callback の専用メソッド化

### 3-1. 方針

社内レビュアー: 「反論より設計を明確化。防御ロジックを callback に持ったままでも、
permission_callback 側に入口チェックを寄せるのがよい。」

`__return_true` のままではレビューアーが納得しない。
公開エンドポイントでも**専用メソッドで意図を表明**する。

### 3-2. 対象と変更

| エンドポイント | メソッド | 現状 | 変更後 |
|---------------|---------|------|--------|
| `GET /session` | `get_session` | `__return_true` | `[$this, 'allow_public_chat']` — bot有効状態を確認 |
| `GET /lead-config` | `get_lead_config` | `__return_true` | `[$this, 'allow_public_chat']` — bot有効状態を確認 |
| `GET /message-limit` | `get_message_limit_status` | `__return_true` | **削除検討** — Free制限を消したなら不要 |
| `POST /offline-message` | `submit_offline_message` | `__return_true` | `[$this, 'allow_offline_message']` — 同一オリジン + honeypot + dwell time + reCAPTCHA有効時トークン存在確認 |

### 3-3. 実装例

```php
/**
 * Permission: public chat endpoints (session, lead-config).
 * Chatbot must be enabled for the current page context.
 */
public function allow_public_chat(): bool {
    // Bot must be enabled (respects wpaic_chatbot_enabled filter)
    return (bool) apply_filters('raplsaich_chatbot_enabled', true);
}

/**
 * Permission: offline message submission.
 * Requires same-origin + basic anti-spam checks.
 */
public function allow_offline_message(WP_REST_Request $request): bool {
    // Same-origin header must be present
    if (!$this->has_origin_headers()) {
        return false;
    }
    return true;
    // Note: detailed checks (rate limit, honeypot, timing, reCAPTCHA) in callback
}
```

### 3-4. `/message-limit` の削除検討

Free版の人工的メッセージ制限（500/月）を既に削除済みの場合、このエンドポイントは不要。
- 削除する場合: ルート登録を除去、JS 側の呼び出しも除去
- 残す場合: `allow_public_chat` を permission_callback に設定

**工数**: 1-2時間

---

## Phase 4: インライン JS/CSS の wp_enqueue 化

> **社内レビュアー**: 「レビューが具体的にファイルと行番号まで出していて、19件あると言っている。
> これは『そのまま見つかった』ということ。個別反論するより一気に寄せた方が速い。」

### 4-0. 現状（Step 7 完了後）

確認済みのインラインタグは **16件**。方式は2種類:

### 4-1. 別ファイルに分離（PHP なし — 8件）

| ファイル:行 | 種類 | 行数 | 内容 | 分離先 |
|------------|------|------|------|--------|
| settings.php:1042-1055 | STYLE | 13 | Badge position grid | `assets/css/admin-badge-position.css` |
| conversations.php:13-53 | STYLE | 40 | Handoff badge + pulse animation | `assets/css/admin-conversations.css` |
| conversations.php:357-427 | STYLE | 70 | Modal + operator reply | 同上に統合 |
| knowledge.php:441-565 | STYLE | 125 | Knowledge grid + toggle switch | `assets/css/admin-knowledge.css` |
| class-admin.php:3045-3125 | STYLE | 81 | Pro preview banner | `assets/css/admin-pro-preview.css` |
| class-admin.php:6512-6693 | STYLE | 182 | Pro settings tab groups | `assets/css/admin-pro-tabs.css` |
| crawler.php:201-212 | SCRIPT | 11 | Post type checkbox sync | `assets/js/admin-crawler.js` |
| class-admin.php:6695-6732 | SCRIPT | 38 | Tab group switching | `assets/js/admin-tab-groups.js` |

**作業手順**:
1. テンプレートから `<style>...</style>` / `<script>...</script>` ブロックを抽出
2. 対応する `assets/css/*.css` / `assets/js/*.js` ファイルに配置
3. テンプレートのブロックを除去
4. `enqueue_scripts()` / `enqueue_styles()` でページ条件付きロード:
   ```php
   if (strpos($hook, 'wpaic-conversations') !== false) {
       wp_enqueue_style('wpaic-conversations', WPAIC_PLUGIN_URL . 'assets/css/admin-conversations.css', ['wpaic-admin'], WPAIC_VERSION);
   }
   ```

### 4-2. `wp_add_inline_script()` に変換（PHP 埋め込みあり — 8件）

| ファイル:行 | 行数 | 内容 | PHP変数 |
|------------|------|------|---------|
| settings.php:417-499 | 83 | MCP key generation | wp_json_encode, esc_js, nonce |
| settings.php:1737-1779 | 42 | Copy support info | esc_js (翻訳文字列) |
| settings.php:1936-2083 | 147 | Export/import/reset | esc_js (翻訳文字列), AJAX |
| crawler.php:431-524 | 94 | Index delete/exclude | esc_js (確認文字列) |
| conversations.php:429-515 | 87 | Message rendering | esc_js (ロールラベル) |
| knowledge.php:568-659 | 92 | Add/import forms | esc_js (ステータスメッセージ) |
| dashboard.php:214-308 | 95 | Chart.js chart data | wp_json_encode (チャートデータ) |
| class-admin.php:1859-1871 | 13 | Security notice dismiss | esc_url, nonce |

**作業手順**:
1. テンプレートから `<script>...</script>` ブロックの中身を抽出
2. レンダー関数内で `wp_add_inline_script('wpaic-admin', $js_code)` を呼び出す
3. PHP変数は事前に `sprintf()` で埋め込み、または `wp_localize_script()` でデータを渡す
4. テンプレートのブロックを除去

### 4-3. 実装の優先順

```
A. CSS 6件を別ファイル化（最も単純、コピー＆削除）
B. JS 2件を別ファイル化（PHP なし、コピー＆削除）
C. JS 8件を wp_add_inline_script 化（PHP 変数の処理が必要）
```

**工数**: 4-6時間

---

## Phase 5: プレフィックス変更（最大規模）

> **社内レビュアー**: 「理屈としては wpaic はそこまで短くないが、審査は理屈勝負より
> レビューアーの納得が重要。押し返すのは得策ではない。」
> 「一括リネームは『だいたい置換できた気がする』が一番危険。
> 必ず旧prefixの grep がゼロになるまで見る。」

### 5-1. 変更対象の総数

| カテゴリ | 旧 | 新 | 件数 |
|---------|-----|-----|------|
| クラス名 | `WPAIC_` | `RAPLSAICH_` | ~479 |
| 関数・変数・フック | `wpaic_` | `raplsaich_` | ~787 |
| 定数 | `WPAIC_VERSION` 等 | `RAPLSAICH_VERSION` 等 | ~30 |
| JS グローバル | `wpaicAdmin`, `wpAiChatbotConfig` | `raplsaichAdmin`, `raplsaichConfig` | ~250 |
| CSS クラス | `.wpaic-` | `.raplsaich-` | ~437 |
| オプション名 | `wpaic_settings` 等 | `raplsaich_settings` 等 | ~198 |
| Transient | `wpaic_*` | `raplsaich_*` | ~50 |
| REST namespace | `wp-ai-chatbot/v1` | `rapls-ai-chatbot/v1` | ~17 |
| DB テーブル | `{prefix}aichat_*` | `{prefix}raplsaich_*` | ~129 |
| ショートコード | `wpaic_chatbot` | `raplsaich_chatbot` | ~3 |
| Cron フック | `wpaic_crawl_site` 等 | `raplsaich_crawl_site` 等 | ~12 |
| **合計** | | | **~1,900** |

### 5-2. 実装手順

#### Step 1: 一括置換（順序が重要）

```
# 1. PHP 定数・クラス名（大文字）
WPAIC_  → RAPLSAICH_

# 2. PHP 関数・オプション・フック・transient（小文字）
wpaic_  → raplsaich_

# 3. CSS クラス・HTML ID・スクリプトハンドル（ハイフン区切り）
wpaic-  → raplsaich-

# 4. JS グローバル変数
wpaicAdmin → raplsaichAdmin
wpAiChatbotConfig → raplsaichConfig

# 5. REST namespace
wp-ai-chatbot/v1 → rapls-ai-chatbot/v1

# 6. DB テーブル名（SQL文字列内）
aichat_ → raplsaich_

# 7. 個別定数
WPAIC_CRAWLING → RAPLSAICH_CRAWLING
```

#### Step 2: データマイグレーション（既存インストール対応）

`class-activator.php` の `activate()` に旧→新のマイグレーションを追加:

```php
// オプションのマイグレーション
$old_settings = get_option('wpaic_settings');
if ($old_settings !== false && get_option('raplsaich_settings') === false) {
    update_option('raplsaich_settings', $old_settings);
    // 旧オプションは残す（ロールバック用）
}

// DB テーブルのリネーム
// ALTER TABLE {prefix}aichat_conversations RENAME TO {prefix}raplsaich_conversations;
// 等

// Cron フックの再登録
// 旧フックを解除し、新フックで再スケジュール

// ショートコードの後方互換
// 旧ショートコード wpaic_chatbot も新ハンドラーに転送
```

#### Step 3: 後方互換性

- **ショートコード**: `wpaic_chatbot` を `raplsaich_chatbot` のエイリアスとして維持
- **フィルター/アクション**: 旧フック名を新フック名に転送（deprecated 通知付き）
- **REST API**: 旧 namespace `wp-ai-chatbot/v1` も登録（deprecated）
- **オプション**: 旧オプション名からの自動マイグレーション

#### Step 4: Pro プラグインとの同期

Pro プラグインも同じプレフィックス変更が必要:
- `WPAIC_Pro_*` → `RAPLSAICH_Pro_*`
- `wpaic_pro_*` → `raplsaich_pro_*`
- Free の新クラス名を参照するように全面更新

#### Step 5: 完了確認（必須）

旧プレフィックスが完全にゼロになるまで確認:
```bash
grep -RniE "\bWPAIC_|wpaic_|'wpaic|\"wpaic|wp-ai-chatbot/v1|wpAiChatbotConfig|wpaicAdmin" \
  --include="*.php" --include="*.js" --include="*.css" .
```
**この grep の出力がゼロになるまで完了ではない。**

**工数**: 2-3日（テスト含む）

---

## Phase 6: テスト・検証

### 6-1. 新規インストールテスト
- [ ] プラグインを有効化してエラーがないこと
- [ ] 全管理画面が正常に表示されること
- [ ] チャットボットが正常に動作すること
- [ ] サイト学習（クローラー）が正常動作すること
- [ ] REST API が全エンドポイントで応答すること

### 6-2. アップグレードテスト（既存→新バージョン）
- [ ] 旧バージョンから更新後、設定が引き継がれること
- [ ] DB テーブルが正しくリネームされること
- [ ] ショートコード `wpaic_chatbot` が後方互換で動作すること
- [ ] Cron ジョブが正しく再登録されること

### 6-3. トライアルウェア残存チェック（必須）

```bash
# 人工制限・ロック機能の痕跡がないことを確認
grep -RniE "limit reached|monthly AI response limit|Upgrade to Pro for unlimited|20 entries|500 AI responses|license key|unlock" \
  --include="*.php" --include="*.js" .

# disabled フォーム要素が残っていないことを確認
grep -rn '<input.*disabled\|<select.*disabled\|<button.*disabled\|pointer-events.*none' \
  templates/ includes/admin/ --include="*.php"

# ロックアイコンが残っていないことを確認
grep -rn 'dashicons-lock' templates/ includes/admin/ --include="*.php"
```

### 6-4. 旧プレフィックス残存チェック（必須）

```bash
grep -RniE "\bWPAIC_|wpaic_|'wpaic|\"wpaic|wp-ai-chatbot/v1|wpAiChatbotConfig|wpaicAdmin" \
  --include="*.php" --include="*.js" --include="*.css" .
```
**出力がゼロでなければ完了ではない。**

### 6-5. インラインタグ残存チェック

```bash
grep -rn '<script>\|<style>' templates/ includes/admin/ --include="*.php"
```

### 6-6. CDN残存チェック

```bash
grep -rn 'cdnjs.cloudflare.com\|cdn.jsdelivr.net' --include="*.js" --include="*.php" .
```

### 6-7. Plugin Check 実行

```bash
wp plugin check rapls-ai-chatbot --require=./wp-content/plugins/plugin-check/cli.php
```

### 6-8. ZIP 作成・検証

```bash
git archive --format=zip HEAD -o /tmp/check.zip
unzip -l /tmp/check.zip | grep -E '(CLAUDE\.md|\.DS_Store|node_modules/)' && echo "FAIL" || echo "OK"
rm /tmp/check.zip
```

---

## Phase 7: レビュー返信

> **社内レビュアー**: 「返信は短くていい。レビュー側も『変更点の長文列挙は不要、簡潔に』と言っている。」

### 返信テンプレート

```
Thank you for the review.

I reviewed and addressed the reported issues, including removal of remote asset
loading, enqueueing inline JS/CSS properly, improving REST permission callbacks,
adding direct access guards, updating third-party libraries, and revising
prefixes where needed.

I also re-tested the plugin after applying the changes and uploaded the
corrected version.

Please let me know if anything else needs attention.
```

> **重要**: 変更点の長文リストは付けない。レビューアーは全プラグインを再レビューする。

---

## 実行順序（社内レビュアー推奨順）

> **方針**: 簡単なものを先に全部潰す → prefix を一括でやる → 最後に動作テスト

```
Step 1: html2canvas をローカル化 or Free版から除去        [Phase 1]  15分
Step 2: editor.asset.php + 同種ファイルに ABSPATH ガード   [Phase 1]  15分
Step 3: /message-limit の存在意義を再確認して削除検討       [Phase 3]  30分
Step 4: 公開 REST 4本の permission_callback を専用関数化    [Phase 3]  1-2時間
Step 5: Chart.js を最新安定版へ更新                         [Phase 1]  15分
Step 6: readme.txt に外部サービス説明を追加                 [Phase 1]  30分
Step 7: トライアルウェア / ロック表示の除去                  [Phase 2]  2-3時間
Step 8: インライン JS/CSS を enqueue 化（19件）             [Phase 4]  4-6時間
Step 9: プレフィックス一括変更（~1,900箇所）                [Phase 5]  2-3日
Step 10: grep と動作テスト                                  [Phase 6]  1日
Step 11: ZIP 作り直し → 短く返信                            [Phase 7]  30分
```

---

## Pro プラグインへの影響

Free のプレフィックス変更に伴い、Pro プラグインも全面的な更新が必要:
- Free のクラス名・関数名・フック名の参照をすべて更新
- Pro 独自のプレフィックスも統一（`wpaic_pro_*` → `raplsaich_pro_*`）
- Free/Pro 間の共有テーブル名の統一
- **Pro は WordPress.org に公開しないため、プレフィックス変更は Free との整合性のみが目的**

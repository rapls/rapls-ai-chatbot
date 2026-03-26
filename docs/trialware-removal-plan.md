# Trialware 完全除去計画

**作成日**: 2026-03-26
**目的**: WordPress.org レビューアーの Trialware 指摘を完全解消する
**方針**: Free 版に Pro 機能ゲートを一切残さない。Pro 機能は Pro プラグイン側のフックで追加する。

---

## 設計原則

1. **Free 版のコードは全て制限なく動作する**
2. **Pro チェックで機能を拒否するコードは Free から除去**
3. **Pro 機能は Pro プラグイン側で `add_filter`/`add_action` により注入**
4. **Free のスタブクラス (`RAPLSAICH_Pro_Features`) は常に「制限なし」を返す**

---

## 対応パターン

### パターン A: ブロッキングゲート → 除去

Free 版で機能をブロック（403/429エラーを返す）しているコードを除去。
Pro 側が必要ならフィルタフックで注入。

**例（変更前）:**
```php
// Free REST controller
if (!$pro_features->check_ip_whitelist()) {
    return new WP_REST_Response(['error' => '...', 'error_code' => 'ip_not_whitelisted'], 403);
}
```

**例（変更後）:**
```php
// Free REST controller — ブロック除去、フィルタポイントのみ残す
$pre_check = apply_filters('raplsaich_pre_chat_check', null, $request);
if ($pre_check instanceof WP_REST_Response) {
    return $pre_check;
}
```

```php
// Pro プラグイン側（class-pro-main.php）
add_filter('raplsaich_pre_chat_check', [$this, 'run_pro_checks'], 10, 2);

public function run_pro_checks($result, $request) {
    if ($result !== null) return $result;
    // IP whitelist, banned words, spam, budget checks here
    ...
}
```

### パターン B: 機能ゲート → 除去またはフィルタ化

Free で機能を無効化しているコード（`is_pro()` チェック）を除去。

**例（変更前）:**
```php
$cache_enabled = !empty($pro_settings['response_cache_enabled']) && $pro_features->is_pro();
```

**例（変更後）:**
```php
// Free では常に false（設定画面がないので設定値が存在しない）
$cache_enabled = !empty($pro_settings['response_cache_enabled']);
// Pro プラグインが設定値を管理するので、Pro インストール時のみ true になる
```

### パターン C: UI ゲート → 単純表示に変更

メニューや設定画面での `$is_pro` 分岐を簡素化。

---

## 対象一覧（41箇所）

### 優先度1: REST API のブロッキングゲート（最重要、レビュー指摘対象）

| # | ファイル:行 | チェック | アクション | 対応 |
|---|-----------|---------|----------|------|
| 1 | rest-controller.php:938 | `is_ip_blocked()` | 403返却 | フィルタ `raplsaich_pre_chat_check` に移行 |
| 2 | rest-controller.php:947 | `check_ip_whitelist()` | 403返却 | 同上 |
| 3 | rest-controller.php:979 | `check_budget_limit()` | 429返却 | 同上 |
| 4 | rest-controller.php:988 | `contains_banned_words()` | 400返却 | 同上 |
| 5 | rest-controller.php:998 | `is_spam()` | 400返却 | 同上 |
| 6 | rest-controller.php:1179 | `is_pro()` + cache check | キャッシュ無効 | `is_pro()` 条件を除去 |
| 7 | rest-controller.php:737 | `is_pro()` + multimodal | アップロード拒否 | フィルタに移行 |
| 8 | rest-controller.php:1338 | sentiment analysis | プロンプト調整スキップ | フィルタに移行 |
| 9 | rest-controller.php:1379 | context memory | コンテキストスキップ | フィルタに移行 |
| 10 | rest-controller.php:2502 | enhanced rate limit | レート制限スキップ | フィルタに移行 |
| 11 | rest-controller.php:3655 | `check_message_limit()` | エラー返却 | 除去（FREE_MESSAGE_LIMIT=PHP_INT_MAX） |
| 12 | rest-controller.php:3664 | `is_ip_blocked()` | 403返却 | フィルタに移行 |
| 13 | rest-controller.php:3673 | `check_ip_whitelist()` | 403返却 | フィルタに移行 |
| 14 | rest-controller.php:3692 | `check_budget_limit()` | 429返却 | フィルタに移行 |
| 15 | rest-controller.php:3735 | multi-bot | ボット選択スキップ | フィルタに移行 |
| 16 | rest-controller.php:4037 | `check_budget_limit()` | 429返却 | Pro ルート内（is_pro ブロック） → 対応不要 |
| 17 | rest-controller.php:4122 | suggestions empty for Free | 空配列返却 | Pro ルート内 → 対応不要 |
| 18 | rest-controller.php:4130 | `check_budget_limit()` | 429返却 | Pro ルート内 → 対応不要 |

### 優先度2: MCP ツールのブロッキングゲート

| # | ファイル:行 | チェック | アクション | 対応 |
|---|-----------|---------|----------|------|
| 19 | tool-send-message.php:73 | `contains_banned_words()` | エラー返却 | フィルタに移行 |
| 20 | tool-send-message.php:78 | `check_message_limit()` | エラー返却 | 除去（常にパス） |
| 21 | tool-send-message.php:84 | `check_budget_limit()` | エラー返却 | フィルタに移行 |

### 優先度3: フロントエンド機能ゲート

| # | ファイル:行 | チェック | 対応 |
|---|-----------|---------|------|
| 22 | chatbot-widget.php:59 | テーマ強制デフォルト | `is_pro()` 条件除去、Free テーマ外は設定UI がないので自然に default |
| 23 | chatbot-widget.php:72 | ダークモード除去 | 同上 |
| 24 | chatbot-widget.php:101 | ショートコード theme override | `is_pro()` 条件除去 |
| 25 | chatbot-widget.php:223 | カスタム CSS 無効 | `is_pro()` 条件除去 |
| 26 | chatbot-widget.php:501 | ページ別テーマ | `is_pro()` 条件除去 |
| 27 | chatbot-widget.php:574 | 埋め込みテーマ | `is_pro()` 条件除去 |
| 28 | chatbot-widget.php:153 | ホワイトラベル footer | `is_pro()` 条件除去 |

### 優先度4: 管理画面 UI ゲート

| # | ファイル:行 | チェック | 対応 |
|---|-----------|---------|------|
| 29 | class-admin.php:92-166 | メニュー表示制御 | upsell ページは維持（テキストのみ）、機能制限なし |
| 30 | class-admin.php:2256 | FAQ 上限チェック | `can_add_faq()` は常に true（FREE_FAQ_LIMIT=PHP_INT_MAX） |
| 31 | class-main.php:293 | クローラー初期化 | `is_pro()` 条件除去 → Free でもクローラー動作 |
| 32 | content-extractor.php:36 | 拡張抽出 | `is_pro()` 条件除去 |

### 優先度5: テンプレート UI ゲート

| # | ファイル:行 | 対応 |
|---|-----------|------|
| 33 | settings.php:21 | Pro バナー → 維持（案内テキストのみ、機能制限なし） |
| 34 | settings.php:903 | Pro テーマ表示 → テキスト案内のみ（実装済み） |
| 35 | settings.php:1046 | ダークモード → テキスト案内のみ（実装済み） |
| 36 | crawler.php:266 | 拡張抽出 → テキスト案内のみ（実装済み） |
| 37 | conversations.php:94 | エクスポート → テキスト案内のみ（実装済み） |
| 38 | knowledge.php:86,210,220,382,416 | Pro UI 表示 → 条件分岐維持（UI要素の表示/非表示のみ） |

### 情報取得のみ（対応不要）

| # | ファイル:行 | 理由 |
|---|-----------|------|
| 39 | rest-controller.php:976 | `get_unavailable_message()` — 情報取得、ブロックなし |
| 40 | rest-controller.php:1241,1583,3409 | `get_remaining_messages()` — 常に PHP_INT_MAX |
| 41 | tool-get-site-info.php:71 | `is_pro()` — 情報報告のみ |

---

## 実装手順

### Step 1: フィルタフックの設計（30分）

Free 版の REST controller に以下のフィルタを追加:

```php
/**
 * Filter: Pre-chat validation (Pro uses for IP block, banned words, spam, budget).
 * Return WP_REST_Response to reject, null to continue.
 */
$pre_check = apply_filters('raplsaich_pre_chat_check', null, $message, $request);
if ($pre_check instanceof WP_REST_Response) {
    return $pre_check;
}

/**
 * Filter: Pre-send AI modifications (Pro uses for sentiment, context memory).
 * Modify $messages array before sending to AI.
 */
$messages = apply_filters('raplsaich_pre_ai_messages', $messages, $session_id, $settings);

/**
 * Filter: Post-response processing (Pro uses for caching, context save).
 */
$response = apply_filters('raplsaich_post_ai_response', $response, $message, $session_id);
```

### Step 2: REST controller からブロッキングゲートを除去（2-3時間）

- IP block/whitelist チェック（4箇所）を除去
- Banned words/spam チェック（2箇所）を除去
- Budget limit チェック（3箇所）を除去
- Message limit チェック（1箇所）を除去
- Multimodal `is_pro()` ゲート（1箇所）を除去
- Response cache `is_pro()` 条件（1箇所）を除去
- Sentiment/context memory ゲート（2箇所）を除去
- Enhanced rate limit `is_pro()` ゲート（1箇所）を除去
- Multi-bot `is_pro()` ゲート（1箇所）を除去
- フィルタフックに置換

### Step 3: MCP ツールからブロッキングゲートを除去（30分）

- Banned words/message limit/budget limit チェック（3箇所）を除去
- フィルタフックに置換

### Step 4: フロントエンドから `is_pro()` ゲートを除去（1時間）

- テーマ強制デフォルト（6箇所）を除去
- カスタム CSS ゲートを除去
- ホワイトラベル footer ゲートを除去
- 注意: Pro 設定値は Pro プラグインが管理するので、Free では設定値が空 → 自然にデフォルト動作

### Step 5: class-main.php のクローラー初期化ゲートを除去（15分）

- `is_pro()` チェックを除去 → Free でもクローラーが動作

### Step 6: Pro プラグイン側にフックを追加（2-3時間）

- `raplsaich_pre_chat_check` フィルタで IP/banned words/spam/budget チェックを実行
- `raplsaich_pre_ai_messages` フィルタで sentiment/context memory を適用
- `raplsaich_post_ai_response` フィルタでキャッシュ保存を実行

### Step 7: テスト（1-2時間）

- Free 版のみで全機能が制限なく動作すること
- Pro 版追加時に Pro 機能が正しく動作すること
- `grep -rn 'is_pro()\|is_feature_available\|check_ip_whitelist\|check_budget_limit\|contains_banned_words\|is_spam\|check_message_limit' includes/ templates/` で残存確認

---

## 工数見積もり

| Step | 作業 | 工数 |
|------|------|------|
| 1 | フィルタフック設計 | 30分 |
| 2 | REST controller 修正 | 2-3時間 |
| 3 | MCP ツール修正 | 30分 |
| 4 | フロントエンド修正 | 1時間 |
| 5 | class-main.php 修正 | 15分 |
| 6 | Pro プラグイン側フック追加 | 2-3時間 |
| 7 | テスト | 1-2時間 |
| **合計** | | **7-10時間** |

---

## レビュー返信テンプレート（修正後）

```
Thank you for the specific feedback. I've removed all Pro feature gates from the
Free plugin — IP blocking, banned words, spam detection, budget limits, and other
Pro checks are no longer in the Free codebase. The Free plugin is now fully
functional with no restrictions. Pro features are delivered entirely through a
separate plugin using WordPress filter hooks.

The corrected version has been uploaded.
```

# 監査修正項目 検証ガイド

対象: Free v1.5.0 / Pro v1.3.0
作成日: 2026-03-10
3回の包括的監査で発見・修正された全46件のデバッグ・検証方法をまとめています。

---

## 目次

- [第1回監査 (21件)](#第1回監査-21件)
- [第2回監査 (17件)](#第2回監査-17件)
- [第3回監査 (8件)](#第3回監査-8件)
- [SQL クエリ集](#sql-クエリ集)
- [ブラウザ Console テスト集](#ブラウザ-console-テスト集)

---

## 第1回監査 (21件)

### 1. マルチボット `enabled` → `is_active` フィールド名修正

**重要度**: Critical
**ファイル**: Pro `includes/class-pro-main.php` — `coordinate_multi_bot()`
**修正内容**: ボット有効判定が `$b['enabled']` → `$b['is_active']` に修正

**検証方法**:
1. Pro Settings > Chatbots タブでボットを2つ以上作成
2. 1つを有効、1つを無効に設定
3. Pro Settings > Security > Multi-bot Coordination を有効化
4. フロントでチャット送信 → 有効なボットのみが応答に使われることを確認

```sql
-- ボット設定確認
SELECT option_value FROM wp_options WHERE option_name = 'wpaic_settings';
-- pro_features.bots 配列の各ボットに is_active フィールドがあることを確認
```

---

### 2. 二重暗号化防止ガード

**重要度**: Medium
**ファイル**: Pro `includes/class-pro-main.php` — `encrypt_message_content()`
**修正内容**: `encg:` / `enc:` プレフィックスがある場合はスキップ

**検証方法**:
1. Pro Settings > Encryption を有効化
2. フロントでメッセージを送信
3. DB で暗号化を確認 → `encg:` プレフィックスが1回だけ付いていること

```sql
SELECT id, LEFT(content, 40) as content_preview
FROM wp_aichat_messages ORDER BY id DESC LIMIT 5;
-- encg:encg: のような二重プレフィックスがないこと
```

---

### 3. `get_by_id()` に復号フィルター適用

**重要度**: Medium
**ファイル**: Free `includes/models/class-message.php` — `get_by_id()`
**修正内容**: `wpaic_message_content_load` フィルターを適用

**検証方法**:
1. 暗号化を有効にした状態でメッセージを送信
2. 管理画面 > 会話履歴 > 個別メッセージ表示
3. メッセージ内容が復号されて表示されること（暗号文字列でないこと）

---

### 4. `decrypt_value()` 失敗時の戻り値修正

**重要度**: Medium
**ファイル**: Pro `includes/class-pro-features.php` — `decrypt_value()`
**修正内容**: 失敗時に空文字列ではなく元の暗号化文字列を返すように変更

**検証方法**:
1. `wp-config.php` の `AUTH_SALT` を一時変更
2. 既存の暗号化メッセージを表示 → 暗号化文字列がそのまま表示される（空にならない）
3. `wp-content/debug.log` に復号失敗のログが記録される
4. **必ず `AUTH_SALT` を元に戻すこと**

---

### 5. SSRF 防止 (`reject_unsafe_urls`)

**重要度**: Medium
**ファイル**: Pro `includes/class-pro-main.php` — `crawl_external_urls()`
**修正内容**: `wp_remote_get()` に `reject_unsafe_urls => true` を追加

**検証方法**:
1. Pro Settings > External Learning で内部 IP の URL を追加 (例: `http://127.0.0.1/admin`)
2. クロール実行 → 内部 URL がクロールされないこと
3. `debug.log` にブロックログがないか確認

---

### 6. レスポンスサイズ制限 (1MB)

**重要度**: Medium
**ファイル**: Pro `includes/class-pro-main.php` — `crawl_external_urls()`
**修正内容**: `limit_response_size => 1048576` を追加

**検証方法**: 巨大ページ (1MB超) の URL を External Learning に追加 → メモリ溢れなくクロール処理される

---

### 7. `mb_str_split` フォールバック

**重要度**: Medium
**ファイル**: Pro `includes/class-pro-main.php` — `extract_routing_words()`
**修正内容**: `mb_str_split` がない環境で `preg_split` にフォールバック

**検証方法**:
```php
// PHP コンソールで確認
var_dump(function_exists('mb_str_split'));
// false の場合でもマルチボットのルーティングが動作すること
```

---

### 8. 暗号化マイグレーション排他ロック

**重要度**: Medium
**ファイル**: Pro `includes/class-pro-admin.php` — `ajax_encryption_migrate()`
**修正内容**: 5分間のトランジェントロックを追加

**検証方法**:
1. Pro Settings > Encryption > Migration ボタンをクリック
2. 処理中に再度ボタンをクリック → 「Migration is already in progress」エラーが返る

```sql
-- ロック確認
SELECT option_value FROM wp_options
WHERE option_name = '_transient_wpaic_encryption_migration_lock';
```

---

### 9. スケジュールクロール実装

**重要度**: Medium
**ファイル**: Pro `includes/class-pro-main.php` — `maybe_schedule_pro_crawl()`, `run_scheduled_crawl()`
**修正内容**: `wpaic_scheduled_crawl_pro` cron イベントを追加

**検証方法**:
1. Pro Settings > Crawler > Scheduled Crawl を有効化
2. 頻度と時刻を設定して保存

```php
// WP-CLI で cron 確認
wp cron event list | grep wpaic_scheduled_crawl
```

---

### 10. Webhook イベントに `handoff_requested` 追加

**重要度**: Medium
**ファイル**: Free `includes/admin/class-admin.php` — `sanitize_pro_features_settings()`
**修正内容**: webhook_events の許可リストに `handoff_requested` を追加

**検証方法**:
1. Pro Settings > Webhook > Events で `handoff_requested` チェックボックスが表示される
2. チェックして保存 → リロード後も保持される

---

### 11. 暗号化/復号メソッドの型ヒント除去

**重要度**: Medium
**ファイル**: Pro `includes/class-pro-main.php` — `encrypt_message_content()`, `decrypt_message_content()`
**修正内容**: `string` 型ヒントを除去し、`is_string()` ガードを追加

**検証方法**: WordPress フィルターから `null` や `WP_Error` が渡されてもクラッシュしないこと（通常運用で確認可）

---

### 12. `ip_in_cidr` IPv6 IP vs IPv4 CIDR チェック

**重要度**: Low
**ファイル**: Pro `includes/class-pro-features.php` — `ip_in_cidr()`
**修正内容**: IPv6 アドレスを IPv4 CIDR と比較する場合は `false` を返す

**検証方法**: IPv6 アドレス (例: `::1`) からアクセスし、IPv4 CIDR ブロックリスト (例: `192.168.1.0/24`) に影響されないこと

---

### 13. 脆弱性スキャナーの暗号化プレフィックス修正

**重要度**: Low
**ファイル**: Pro `includes/class-pro-features.php` — `run_vulnerability_scan()`
**修正内容**: `gcm:` → `encg:` に修正

**検証方法**:
1. API キーを暗号化した状態でセキュリティスキャンを実行
2. 「暗号化されていない API キー」の警告が出ないこと

---

### 14. ナレッジ削除時のバージョン履歴クリーンアップ

**重要度**: Low
**ファイル**: Free `includes/models/class-knowledge.php` — `delete()`、Pro `includes/class-pro-main.php` — `cleanup_knowledge_versions()`
**修正内容**: `wpaic_knowledge_deleted` アクションを発火し、Pro 側でバージョンテーブルを削除

**検証方法**:
1. Knowledge Versioning を有効化
2. ナレッジエントリを追加して数回編集（バージョン作成）
3. エントリを削除

```sql
-- 削除後にバージョンが残っていないこと
SELECT * FROM wp_aichat_knowledge_versions WHERE knowledge_id = [削除したID];
-- 0件であること
```

---

### 15. `free_message_limit` デッドコード削除

**重要度**: Low
**ファイル**: Free `includes/admin/class-admin.php`
**修正内容**: サニタイザーから未使用の `free_message_limit` 行を削除

**検証方法**: 設定保存が正常に動作し、メッセージ制限が Pro の `monthly_message_limit` で制御されること

---

### 16. TTS 言語設定 UI 追加

**重要度**: Low
**ファイル**: Pro `templates/admin/pro-settings.php`、Pro `includes/class-pro-admin.php`
**修正内容**: TTS Language フィールドを追加、サニタイザーに `tts_lang` を追加

**検証方法**:
1. Pro Settings > AI Enhancement > TTS Language に `ja` を入力して保存
2. リロード後に値が保持されている
3. フロントで TTS ボタンをクリック → 日本語で読み上げられる

---

### 17. ブックマーク localStorage キーのサイト固有化

**重要度**: Low
**ファイル**: Free `assets/js/chatbot.js` — `init()`、`toggleBookmark()`
**修正内容**: `wpaic_bookmarks` → `wpaic_bookmarks_{site_key}` に変更

**検証方法**:
1. フロントでメッセージをブックマーク
2. ブラウザ Console:

```javascript
// サイト固有キーが使われていること
Object.keys(localStorage).filter(k => k.startsWith('wpaic_bookmarks_'));
// wpaic_bookmarks_{hash} の形式で1件あること
```

---

### 18. ip-api.com HTTP 使用の説明コメント追加

**重要度**: Low
**ファイル**: Pro `includes/class-pro-main.php` — `detect_visitor_country()`
**修正内容**: HTTP 使用理由のコメントを追加（Free tier は HTTPS 非対応）

**検証方法**: コードレビューのみ（機能変更なし）

---

### 19. キャッシュ正規化の改善

**重要度**: Low
**ファイル**: Pro `includes/class-pro-main.php` — `normalize_cache_message()`
**修正内容**: `+`, `#`, `@` などの意味のある文字を保持するように正規表現を変更

**検証方法**:
1. Response Cache を有効化
2. `C++` や `C#` を含む質問を送信
3. 同じ質問を再送信 → キャッシュヒットすること

```sql
SELECT content, cache_hash, cache_hit FROM wp_aichat_messages
WHERE cache_hit = 1 ORDER BY id DESC LIMIT 5;
```

---

### 20-21. (その他の Low 修正)

コードコメント改善やマイナーな整理のため、特別な検証は不要。

---

## 第2回監査 (17件)

### 22. `pre_ai_handoff_check` の未定義変数 `$conversation` 修正

**重要度**: Critical
**ファイル**: Pro `includes/class-pro-main.php` — `pre_ai_handoff_check()`
**修正内容**: 未定義の `$conversation` を `['id' => $conv_id, 'session_id' => $session_id]` に修正

**検証方法**:
1. Pro Settings > Handoff を有効化、Slack 通知を設定
2. ハンドオフキーワードを入力して送信
3. Slack にハンドオフ通知が届くこと
4. `debug.log` に「Undefined variable: conversation」がないこと

---

### 23. Holidays データ形状の不一致修正

**重要度**: Critical
**ファイル**: Pro `includes/class-pro-features.php` — `is_holiday()`
**修正内容**: 文字列配列（`['2026-01-01']`）とオブジェクト配列（`[['date' => '2026-01-01']]`）の両方に対応

**検証方法**:
1. Pro Settings > Business Hours > Holidays を有効化
2. 今日の日付を休日として追加
3. 保存してフロントでチャットにアクセス → 休日メッセージが表示される

---

### 24. JS `bookmarkKey` の `rest_url` → `restUrl` 修正

**重要度**: Medium
**ファイル**: Free `assets/js/chatbot.js` — `init()`
**修正内容**: `this.config.rest_url` → `this.config.restUrl`

**検証方法**:
```javascript
// ブラウザ Console で確認
// WPAIChatbot オブジェクトの bookmarkKey が restUrl ベースであること
document.querySelector('.wp-ai-chatbot').__wpaic.bookmarkKey
// 'wpaic_bookmarks_http___example_com_wp_json_...' のような形式
```

---

### 25. レート制限 IP の `X-Forwarded-For` 検証追加

**重要度**: Medium
**ファイル**: Pro `includes/class-pro-features.php` — `get_client_ip_for_rate_limit()`
**修正内容**: `REMOTE_ADDR` がプライベート IP の場合のみプロキシヘッダーを信頼

**検証方法**:
1. Enhanced Rate Limit を有効化（例: 3回/分）
2. curl で `X-Forwarded-For` ヘッダーを偽装して連続リクエスト

```bash
# レート制限が回避されないこと（直接アクセスの場合）
for i in {1..5}; do
  curl -s -H "X-Forwarded-For: 1.2.3.$i" \
    -X POST "https://example.com/wp-json/wp-ai-chatbot/v1/chat" \
    -d '{"message":"test","session_id":"xxx"}' | jq .error_code
done
# REMOTE_ADDR が公開 IP の場合、X-Forwarded-For は無視されて rate_limited になること
```

---

### 26. デフォルトモデル統一

**重要度**: Medium
**ファイル**: Free `includes/admin/class-admin.php` — `get_all_defaults()`, `sanitize_settings()`, `ajax_reset_settings()`
**修正内容**: 全箇所を `gpt-4o-mini` / `claude-haiku-4-5-20251001` に統一

**検証方法**:
1. 管理画面でモデルを `gpt-4o` に変更して保存
2. Settings の「Reset to Defaults」を実行
3. モデルが `gpt-4o-mini` にリセットされること

---

### 27. スケジュールクロールの頻度変更時リスケジュール

**重要度**: Medium
**ファイル**: Pro `includes/class-pro-main.php` — `maybe_schedule_pro_crawl()`
**修正内容**: 設定ハッシュをトランジェントに保存し、変更時に再スケジュール

**検証方法**:
1. Scheduled Crawl を有効化（daily, 03:00）
2. 頻度を weekly に変更して保存
3. cron イベントが weekly に更新されること

```php
wp cron event list | grep wpaic_scheduled_crawl
// recurrence が weekly になっていること
```

---

### 28. AI Form nonce フォールバック削除

**重要度**: Medium
**ファイル**: Pro `includes/class-pro-rest.php` — `ai_form_submit()`
**修正内容**: 弱い Referer ベースのフォールバックを削除、nonce 必須化

**検証方法**:
1. AI Form をフロントに設置
2. フォーム送信が正常に動作すること（nonce 付き）
3. nonce なしの直接 POST → 403 エラーが返ること

```bash
curl -X POST "https://example.com/wp-json/wp-ai-chatbot/v1/ai-form-submit" \
  -d '{"form_id":"test","fields":{}}' \
  -H "Content-Type: application/json"
# 403 Forbidden が返ること
```

---

### 29-30. `ip_in_cidr` 逆方向チェック + マスク範囲検証

**重要度**: Low
**ファイル**: Pro `includes/class-pro-features.php` — `ip_in_cidr()`
**修正内容**: IPv4 IP vs IPv6 CIDR チェック追加、マスク 0-32 範囲検証追加

**検証方法**: IP ブロックリストに不正な CIDR (例: `0.0.0.0/99`) を追加してもエラーにならないこと

---

### 31. テーブルヘルパー結果の統一使用 (5箇所)

**重要度**: Low
**ファイル**: Pro `class-pro-features.php`, `class-pro-rest.php`
**修正内容**: `$wpdb->prefix` 直接構築を `wpaic_require_table()` の戻り値使用に統一

**検証方法**: ハンドオフ機能、オペレーター返信が正常動作すること

---

### 32. Dead ternary 除去

**重要度**: Low
**ファイル**: Pro `includes/class-pro-main.php` — `add_related_knowledge_links()`, `classify_intent()`
**修正内容**: `!is_array()` 内の不要な `is_array()` チェックを `return []` に簡略化

**検証方法**: コードレビューのみ（動作変更なし）

---

### 33. JS 翻訳不可文字列の追加 (5件)

**重要度**: Low
**ファイル**: Free `includes/frontend/class-chatbot-widget.php`
**修正内容**: `web_sources_title`, `listening`, `dedup_truncated`, `dedup_stale`, `dedup_truncated_no_history` を `wp_localize_script` に追加

**検証方法**:
1. サイト言語を日本語に設定
2. フロントのチャットでウェブ検索結果が返る質問を送信
3. 「Web sources:」が翻訳されて表示されること（.po に翻訳がある場合）

```javascript
// ブラウザ Console で翻訳文字列が渡されていること確認
wpaic_config.strings.web_sources_title  // undefined でないこと
wpaic_config.strings.listening          // undefined でないこと
```

---

### 34. シーズナルテーマの空 CSS 除去

**重要度**: Low
**ファイル**: Pro `includes/class-pro-main.php` — `enqueue_seasonal_theme()`
**修正内容**: 空の CSS ルール出力とそのインラインスタイル追加を削除

**検証方法**: Pro Settings > UI > Seasonal Theme を設定 → フロントでテーマクラスが JS で適用されること

---

### 35. `lead_fields` type プロパティ保持

**重要度**: Low
**ファイル**: Free `includes/admin/class-admin.php` — `sanitize_pro_features_settings()`
**修正内容**: リードフィールドの `type` プロパティをサニタイズして保存

**検証方法**:
```sql
SELECT option_value FROM wp_options WHERE option_name = 'wpaic_settings';
-- pro_features.lead_fields の各フィールドに type キーが存在すること
```

---

### 36. Holidays UI 追加

**重要度**: Low
**ファイル**: Pro `templates/admin/pro-settings.php`, Pro `includes/class-pro-admin.php`
**修正内容**: Business Hours タブに holidays チェックボックス、日付テキストエリア、メッセージフィールドを追加

**検証方法**:
1. Pro Settings > Business Hours タブを開く
2. 「Enable holiday schedule」チェックボックスが表示される
3. チェック → Holiday Dates テキストエリアが表示される
4. 日付（1行1日付、YYYY-MM-DD 形式）を入力して保存
5. リロード後に値が保持される

---

### 37. `allowed_extra_origins` UI 追加

**重要度**: Low
**ファイル**: Pro `templates/admin/pro-settings.php`, Pro `includes/class-pro-admin.php`
**修正内容**: Security タブに Allowed Extra Origins テキストエリアを追加

**検証方法**:
1. Pro Settings > Security タブを開く
2. 「Allowed Extra Origins」テキストエリアが表示される
3. `https://example.com` を入力して保存
4. リロード後に値が保持される

---

### 38. CF_CONNECTING_IP 検証

**重要度**: Low — Fix 25 (レート制限 IP 検証) と同時に対応済み

---

## 第3回監査 (8件)

### 39. `wpaic_require_table()` バッククォート二重化修正 (7箇所)

**重要度**: High
**ファイル**: Pro `class-pro-rest.php`, `class-pro-features.php`, `class-pro-main.php`, `class-pro-admin.php`
**修正内容**: `trim($table, '`')` でバッククォートを除去してから `$wpdb->insert/update/delete` に渡す

**検証方法**:
1. ハンドオフ機能をテスト: チャットでハンドオフキーワード送信 → ステータスが更新される
2. オペレーター返信をテスト: 管理画面から会話に返信 → メッセージが保存される
3. 暗号化マイグレーション: Encryption タブで Migrate ボタンをクリック → 処理完了
4. ナレッジ削除: エントリ削除 → バージョン履歴もクリーンアップ

```php
// debug.log に SQL エラーがないこと確認
// 特に "Table '``wp_aichat_...``' doesn't exist" のようなエラーがないこと
```

---

### 40. `allowed_extra_origins` 型不一致修正

**重要度**: High
**ファイル**: Pro `includes/class-pro-main.php` — `add_extra_allowed_origins()`
**修正内容**: 配列と文字列の両方を処理するように修正

**検証方法**:
1. Pro Settings > Security > Allowed Extra Origins に URL を追加して保存
2. その URL からのクロスオリジンリクエストが許可されること
3. `debug.log` に TypeError がないこと

```bash
# クロスオリジンリクエストテスト
curl -s -H "Origin: https://example.com" \
  "https://your-site.com/wp-json/wp-ai-chatbot/v1/session" \
  -D - -o /dev/null | grep "Access-Control"
# Access-Control-Allow-Origin に設定した URL が含まれること
```

---

### 41. `ajax_reset_settings()` のデフォルト統一

**重要度**: Medium
**ファイル**: Free `includes/admin/class-admin.php` — `ajax_reset_settings()`
**修正内容**: ハードコードされた不完全なデフォルトを `self::get_all_defaults()` に置換

**検証方法**:
1. Settings で各種設定を変更して保存
2. 「Reset to Defaults」を実行
3. 全設定がデフォルト値にリセットされること（特に以下を確認）:
   - `response_language`: 空文字列（デフォルト）
   - `show_feedback_buttons`: true（デフォルト）
   - `web_search_enabled`: false（デフォルト）
   - `crawler_enabled`: true（デフォルト）
   - `pro_features`: デフォルトの Pro 設定

---

### 42. `wpaic_current_bot_config` フィルター接続

**重要度**: Medium
**ファイル**: Pro `includes/class-pro-main.php` — `coordinate_multi_bot()`, `get_current_bot_config()`, `apply_prompt_template()`
**修正内容**: ボット解決結果をインスタンスプロパティに保存し、フィルターで返すように接続

**検証方法**:
1. マルチボット設定で、ボットにカスタムシステムプロンプトを設定
2. Prompt Templates も有効化
3. そのボットのページでチャット → ボットのシステムプロンプトが使われ、テンプレートで上書きされないこと

---

### 43. ブックマーク localStorage の consent ラッパー使用

**重要度**: Low
**ファイル**: Free `assets/js/chatbot.js`
**修正内容**: `localStorage.getItem/setItem` → `wpaicLsGet/wpaicLsSet` に変更

**検証方法**:
1. Settings > Advanced > Consent Strict Mode を有効化
2. フロントでチャットを開く（同意前）
3. メッセージのブックマークボタンをクリック → localStorage に書き込まれないこと
4. 同意後 → ブックマークが正常に保存されること

```javascript
// ブラウザ Console で確認
localStorage.getItem('wpaic_consent') // 同意前は null
// ブックマーク操作 → localStorage にキーが追加されないこと
```

---

### 44. `white_label_footer_target` サニタイズ追加

**重要度**: Low
**ファイル**: Free `includes/admin/class-admin.php` — `sanitize_pro_features_settings()`
**修正内容**: `_blank` と `_self` のみ許可するホワイトリストバリデーション追加

**検証方法**:
1. Pro Settings > White Label > Footer Target を設定
2. `_blank` または `_self` を選択 → 正常に保存
3. 不正な値（手動で POST）→ `_blank` にフォールバック

---

### 45. IPv6 CIDR マッチング実装

**重要度**: Low
**ファイル**: Pro `includes/class-pro-features.php` — `ip_in_cidr()`
**修正内容**: `inet_pton()` を使用した IPv6 CIDR 完全サポート

**検証方法**:
1. IP ブロックリストに IPv6 CIDR を追加（例: `2001:db8::/32`）
2. 該当範囲の IPv6 アドレスからのアクセスがブロックされること

---

### 46. `save_knowledge_version()` テーブルヘルパー統一

**重要度**: Low
**ファイル**: Pro `includes/class-pro-main.php` — `save_knowledge_version()`
**修正内容**: `$wpdb->prefix` 直接構築を `trim(wpaic_require_table(...), '`')` に変更

**検証方法**: Knowledge Versioning 有効時にナレッジ編集 → バージョンが保存されること

```sql
SELECT * FROM wp_aichat_knowledge_versions ORDER BY id DESC LIMIT 5;
```

---

## 第4回監査 (7件)

### 47. `get_all_defaults()` に設定キー19件追加

**重要度**: Medium
**ファイル**: Free `includes/admin/class-admin.php` — `get_all_defaults()`
**修正内容**: `badge_position`, `show_feedback_buttons`, `sources_display_mode`, `excluded_pages`, `response_language`, `delete_data_on_uninstall`, `recaptcha_*` (6件), `trust_cloudflare_ip`, `trust_proxy_ip`, `mcp_enabled`, `mcp_api_key_hash`, `web_search_enabled`, `embedding_enabled`, `embedding_provider` を追加

**検証方法**:
1. 管理画面で各種設定を変更して保存
2. 「Reset to Defaults」を実行
3. 以下がデフォルトにリセットされること:
   - `badge_position`: `bottom-right`
   - `show_feedback_buttons`: `true`
   - `recaptcha_enabled`: `false`
   - `web_search_enabled`: `false`
4. Settings Export → Import でこれらのキーが保持されること

---

### 48. 音声入力 `autoResize()` 未定義メソッド修正

**重要度**: Medium
**ファイル**: Free `assets/js/chatbot.js`
**修正内容**: 存在しない `self.autoResize()` をインラインのリサイズロジックに置換

**検証方法**:
1. Pro Settings > AI Enhancement > Voice Input を有効化
2. フロントでマイクボタンをクリックして音声入力
3. 音声認識結果がテキストエリアに入力されること
4. テキストエリアが内容に応じてリサイズされること
5. Console に `TypeError: self.autoResize is not a function` がないこと

---

### 49. 会話共有/ブックマークの DOM セレクター修正

**重要度**: Medium
**ファイル**: Free `assets/js/chatbot.js`
**修正内容**: `.chatbot-message__text` (存在しないクラス) → `.chatbot-message__content` に変更

**検証方法**:
1. Chat Bookmarks を有効化
2. フロントでメッセージをブックマーク → 保存されたブックマーク内容が空でないこと
3. Conversation Sharing を有効化
4. 共有ボタンをクリック → クリップボードにコピーされた内容にメッセージテキストが含まれること

```javascript
// ブラウザ Console でブックマーク内容を確認
Object.keys(localStorage)
  .filter(k => k.startsWith('wpaic_bookmarks_'))
  .forEach(k => {
    var bookmarks = JSON.parse(localStorage.getItem(k));
    bookmarks.forEach(b => console.log('Content:', b.text));
    // text が空文字列でないこと
  });
```

---

### 50. `openrouter_api_key` エクスポート/インポート除外リスト追加

**重要度**: Medium
**ファイル**: Pro `includes/class-pro-admin.php` — `ajax_export_settings()`, `ajax_import_settings()`
**修正内容**: `$api_key_fields` 配列に `'openrouter_api_key'` を追加

**検証方法**:
1. OpenRouter API キーを設定した状態で Settings Export を実行
2. エクスポート JSON を確認 → `openrouter_api_key` が空になっていること
3. 別の API キーを持つ環境で Import → 既存の OpenRouter キーが上書きされないこと

```bash
# エクスポート JSON の確認
cat exported-settings.json | python3 -c "import sys,json; d=json.load(sys.stdin); print('openrouter_api_key:', d.get('openrouter_api_key', 'NOT FOUND'))"
# 'NOT FOUND' または空文字列であること
```

---

### 51. オフラインフォーム reCAPTCHA キー名修正

**重要度**: Low
**ファイル**: Free `assets/js/chatbot.js`
**修正内容**: `config.recaptchaEnabled` → `config.recaptcha_enabled` (PHP のキー名と一致)

**検証方法**:
1. reCAPTCHA を有効化
2. 営業時間外にオフラインフォームを表示
3. フォーム送信時に reCAPTCHA トークンが付与されること
4. reCAPTCHA スクリプト未読み込み時に「Security verification loading」メッセージが表示されること

---

### 52. オフラインフォームの翻訳対応

**重要度**: Low
**ファイル**: Free `assets/js/chatbot.js`
**修正内容**: ハードコード英語プレースホルダーを `config.strings.offline_*` に置換

**検証方法**:
1. サイト言語を日本語に設定
2. 営業時間外にオフラインフォームを表示
3. フォームのラベルが日本語で表示されること（「名前」「メール」「メッセージ」「送信」）

---

### 53. `clearSession()` sessionStorage try/catch 追加

**重要度**: Low
**ファイル**: Free `assets/js/chatbot.js` — `clearSession()`
**修正内容**: `Object.keys(sessionStorage)` を try/catch で囲む

**検証方法**:
1. Safari プライベートモードでチャットページを開く
2. セッションリセット操作を実行
3. Console に例外エラーが表示されないこと

---

## 第5回監査 (6件)

### 54. `initOfflineForm()` の `_s` 未定義 ReferenceError 修正

**重要度**: Critical
**ファイル**: Free `assets/js/chatbot.js` — `initOfflineForm()`
**修正内容**: `var _s = self.config.strings || {};` を関数冒頭に追加

**検証方法**:
1. Business Hours + Offline Message を有効化
2. 営業時間外にフロントでチャットを開く
3. オフラインフォームが正常に表示されること
4. Console に `ReferenceError: _s is not defined` がないこと

---

### 55. ブックマーク DOM セレクター修正 (contentEl 自身を渡す)

**重要度**: Medium
**ファイル**: Free `assets/js/chatbot.js`
**修正内容**: `contentEl.querySelector('.chatbot-message__content')` → `contentEl` (自身がそのクラス)

**検証方法**:
1. Chat Bookmarks を有効化
2. メッセージをブックマーク → localStorage の内容を確認

```javascript
Object.keys(localStorage)
  .filter(k => k.startsWith('wpaic_bookmarks_'))
  .forEach(k => {
    JSON.parse(localStorage.getItem(k)).forEach(b => console.log('text:', b.text));
    // text が空でなくメッセージ内容が含まれること
  });
```

---

### 56. 脆弱性スキャンに `openrouter_api_key` 追加

**重要度**: Medium
**ファイル**: Pro `includes/class-pro-features.php` — `run_vulnerability_scan()`
**修正内容**: `$api_key_fields` 配列に `'openrouter_api_key'` を追加

**検証方法**:
1. OpenRouter API キーを暗号化せずに設定
2. Security Scan を実行
3. 「Unencrypted API key: openrouter_api_key」の警告が表示されること

---

### 57. 音声入力後プレースホルダーの `placeholder` 文字列キー追加

**重要度**: Low
**ファイル**: Free `includes/frontend/class-chatbot-widget.php`, Free `assets/js/chatbot.js`
**修正内容**: PHP strings 配列に `placeholder` キー追加、JS フォールバックを英語に変更

**検証方法**:
1. 音声入力を有効化
2. マイクボタンで録音→停止
3. テキストエリアのプレースホルダーがサイト言語に応じた翻訳で表示されること

---

### 58. ファイルサイズエラーの翻訳対応

**重要度**: Low
**ファイル**: Free `assets/js/chatbot.js` — `handleImageSelect()`
**修正内容**: ハードコード英語 alert を `config.strings.image_too_large` に置換

**検証方法**:
1. Multimodal を有効化、最大サイズを小さく設定 (例: 100KB)
2. 大きな画像をアップロード → エラーメッセージがサイト言語で表示されること

---

### 59. `maybe_create_versions_table()` テーブルヘルパー使用

**重要度**: Low
**ファイル**: Pro `includes/class-pro-main.php` — `maybe_create_versions_table()`
**修正内容**: `$wpdb->prefix` 直接構築を `trim(wpaic_validated_table(...), '`')` に変更

**検証方法**: Knowledge Versioning 有効時にテーブルが正常に作成されること

---

## SQL クエリ集

### 暗号化状態の確認

```sql
-- メッセージの暗号化状態
SELECT id,
  CASE WHEN content LIKE 'encg:%' THEN 'encrypted (GCM)'
       WHEN content LIKE 'enc:%' THEN 'encrypted (CBC legacy)'
       ELSE 'plaintext' END as status,
  LEFT(content, 30) as preview
FROM wp_aichat_messages ORDER BY id DESC LIMIT 10;
```

### キャッシュヒット率

```sql
SELECT
  COUNT(*) as total,
  SUM(CASE WHEN cache_hit = 1 THEN 1 ELSE 0 END) as hits,
  ROUND(SUM(CASE WHEN cache_hit = 1 THEN 1 ELSE 0 END) * 100.0 / COUNT(*), 1) as hit_rate
FROM wp_aichat_messages WHERE role = 'assistant';
```

### ハンドオフ状態

```sql
SELECT id, session_id, handoff_status, updated_at
FROM wp_aichat_conversations
WHERE handoff_status IS NOT NULL
ORDER BY updated_at DESC LIMIT 10;
```

### ナレッジバージョン履歴

```sql
SELECT kv.*, k.question
FROM wp_aichat_knowledge_versions kv
JOIN wp_aichat_knowledge k ON kv.knowledge_id = k.id
ORDER BY kv.created_at DESC LIMIT 10;
```

### Cron ジョブ一覧

```sql
-- WordPress の cron イベント（シリアライズされたデータ）
SELECT option_value FROM wp_options WHERE option_name = 'cron';
-- WP-CLI 推奨: wp cron event list
```

### トランジェント確認

```sql
-- 各種ロック・キャッシュ
SELECT option_name, option_value FROM wp_options
WHERE option_name LIKE '%transient%wpaic%'
ORDER BY option_name;
```

---

## ブラウザ Console テスト集

### 設定値の確認

```javascript
// フロントに渡されている全設定値
console.log(wpaic_config);

// 翻訳文字列
console.log(wpaic_config.strings);

// ブックマークキー
console.log(document.querySelector('.wp-ai-chatbot')?.__wpaic?.bookmarkKey);
```

### localStorage 確認

```javascript
// プラグイン関連の localStorage キー一覧
Object.keys(localStorage).filter(k => k.startsWith('wpaic'));

// 同意状態
localStorage.getItem('wpaic_consent');

// ブックマーク内容
Object.keys(localStorage)
  .filter(k => k.startsWith('wpaic_bookmarks_'))
  .forEach(k => console.log(k, JSON.parse(localStorage.getItem(k))));
```

### REST API テスト

```javascript
// セッション取得
fetch(wpaic_config.restUrl + 'session').then(r => r.json()).then(console.log);

// メッセージ制限
fetch(wpaic_config.restUrl + 'message-limit').then(r => r.json()).then(console.log);

// レスポンスヘッダー確認（セキュリティヘッダー）
fetch(wpaic_config.restUrl + 'session').then(r => {
  console.log('X-Content-Type-Options:', r.headers.get('X-Content-Type-Options'));
  console.log('X-Frame-Options:', r.headers.get('X-Frame-Options'));
});
```

### キャッシュテスト

```javascript
// 同じメッセージを2回送信してキャッシュヒット確認
async function testCache(msg) {
  const send = () => fetch(wpaic_config.restUrl + 'chat', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-WPAIC-Session': document.cookie.match(/wpaic_session_id=([^;]+)/)?.[1]
    },
    body: JSON.stringify({ message: msg })
  }).then(r => r.json());

  console.log('1st:', await send());
  console.log('2nd (should be cached):', await send());
}
testCache('テストメッセージ');
```

---

## 補足: debug.log の確認方法

```bash
# 最新のエラーを確認
tail -50 /path/to/wordpress/wp-content/debug.log

# プラグイン関連のエラーのみ
grep -i "wpaic\|rapls\|ai.chatbot" /path/to/wordpress/wp-content/debug.log | tail -30

# 暗号化関連
grep "decrypt\|encrypt" /path/to/wordpress/wp-content/debug.log | tail -10

# SQL エラー
grep -i "SQL\|query\|table.*not.*exist" /path/to/wordpress/wp-content/debug.log | tail -10
```

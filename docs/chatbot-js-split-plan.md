# chatbot.js Pro 機能分離計画

**作成日**: 2026-03-26
**目的**: Free 配布物の chatbot.js から Pro 機能の実装コードを完全分離
**コミット起点**: d3bb2f3

---

## 現状

- `assets/js/chatbot.js`: 4,300行
- Pro 機能コード: 約 1,800行（推定）
- Free コア: 約 2,500行（推定）
- Pro API 呼び出し: `proApiRequest` bridge 経由（9箇所）
- `is_pro: false` ガード: 全 Pro 機能に適用済み

---

## 分離対象の Pro 機能ブロック（17個）

### A. 独立メソッド（完全に切り出し可能）

| # | メソッド名 | 開始行 | 機能 | 依存 |
|---|-----------|--------|------|------|
| 1 | `initHandoffPolling` | ~1000 | ハンドオフ状態ポーリング | apiRequest |
| 2 | `stopHandoffPolling` | 1032 | ポーリング停止 | なし |
| 3 | `pollHandoffStatus` | 1042 | ステータス取得 | proApiRequest |
| 4 | `initOfflineForm` | 3246 | オフラインフォーム構築 | proApiRequest |
| 5 | `initBookmarkNav` | 4076 | ブックマークナビゲーション | localStorage |
| 6 | `toggleBookmark` | 3869 | ブックマーク切替 | localStorage |
| 7 | `initFullscreenMode` | 3182 | フルスクリーン | DOM |
| 8 | `initWelcomeScreen` | 3081 | ウェルカム画面 | DOM |
| 9 | `initResponseDelay` | 3208 | 応答遅延 | config |
| 10 | `initNotificationSound` | 3216 | 通知音 | Audio API |
| 11 | `initConversionTracking` | 3754 | コンバージョン | proApiRequest |
| 12 | `trackConversion` | 3852 | コンバージョン送信 | proApiRequest |
| 13 | `initSearchPanel` | ~3897 | メッセージ検索 | DOM |
| 14 | `initShareButton` | ~4222 | 会話共有 | clipboard |

### B. 埋め込みコード（addMessage 内等、分離が複雑）

| # | 箇所 | 行範囲 | 内容 | 分離方式 |
|---|------|--------|------|---------|
| 15 | `addMessage` 内 regenerate ボタン | 1644-1680 | 再生成UI追加 | フック化 |
| 16 | `addMessage` 内 bookmark ボタン | 1659-1675 | ブックマークUI追加 | フック化 |
| 17 | `addMessage` 内 sentiment dot | 1292-1310 | 感情分析表示 | フック化 |
| 18 | `addMessage` 内 scenario UI | 1550-1610 | シナリオUI | フック化 |
| 19 | `addMessage` 内 TTS 自動再生 | 1686-1700 | 音声読み上げ | フック化 |
| 20 | `sendMessage` 内 response delay | 974-985 | 遅延処理 | フック化 |
| 21 | voice/TTS setup | 2945-3075 | 音声入力+読み上げ | メソッド分離 |

---

## 分離アーキテクチャ

### Free chatbot.js の構造（変更後）

```javascript
const RaplsaichChatbot = {
    // === Free Core ===
    init(), cacheElements(), bindEvents(),
    open(), close(), toggle(),
    sendMessage(), apiRequest(), loadHistory(),
    addMessage(),  // Pro 拡張はフック経由
    getSession(), setLoading(),
    // ... Free のみの機能

    // === Pro 拡張ポイント ===
    proApiRequest(),           // bridge（既存）
    _proMessageHooks: [],      // addMessage 後のフック配列
    _proInitHooks: [],         // init 後のフック配列

    // Pro プラグインが登録するフックを実行
    runProHooks(hookName, ...args) {
        var hooks = this['_pro' + hookName + 'Hooks'] || [];
        hooks.forEach(function(fn) { fn.apply(this, args); }.bind(this));
    }
};
```

### Pro chatbot-pro.js（新規作成、Pro プラグインに配置）

```javascript
// Pro が Free の chatbot にフックを登録
(function() {
    var bot = window.RaplsaichChatbot;
    if (!bot || !bot.config || !bot.config.is_pro) return;

    // Init hooks
    bot._proInitHooks.push(function() {
        this.initHandoffPolling();
        this.initOfflineForm();
        this.initBookmarkNav();
        this.initFullscreenMode();
        this.initWelcomeScreen();
        this.initResponseDelay();
        this.initNotificationSound();
        this.initConversionTracking();
        this.initSearchPanel();
        this.initShareButton();
        this.initVoiceAndTTS();
    });

    // Message hooks (called after each addMessage)
    bot._proMessageHooks.push(function(role, messageEl, data) {
        this.addRegenerateButton(role, messageEl, data);
        this.addBookmarkButton(role, messageEl, data);
        this.addSentimentDot(role, messageEl, data);
        this.addScenarioUI(role, messageEl, data);
        this.autoTTS(role, messageEl, data);
    });

    // Pro method implementations
    Object.assign(bot, {
        initHandoffPolling: function() { ... },
        pollHandoffStatus: function() { ... },
        // ... 全 Pro メソッド
    });
})();
```

---

## 実装手順

### Step 1: フックシステムをFreeに追加（30分）
- `_proInitHooks`, `_proMessageHooks` 配列プロパティ
- `runProHooks(name, ...args)` メソッド
- `init()` 末尾で `runProHooks('Init')` 呼び出し
- `addMessage()` 末尾で `runProHooks('Message', role, messageEl, data)` 呼び出し

### Step 2: Pro メソッドを chatbot-pro.js に抽出（2-3時間）
- 独立メソッド 14個をそのまま移動
- `addMessage` 内の埋め込みコード 5箇所を個別メソッドに抽出してから移動
- `sendMessage` 内の response delay をフック化

### Step 3: Free chatbot.js からPro コードを除去（1時間）
- Step 2 で移動したメソッドを削除
- `addMessage` 内の Pro コードをフック呼び出しに置換
- Pro state 変数（handoffStatus, ttsEnabled 等）を初期化コードから除去

### Step 4: Pro プラグインに chatbot-pro.js を配置（30分）
- `rapls-ai-chatbot-pro/assets/js/chatbot-pro.js` に配置
- Pro の `enqueue_frontend_scripts` で `wp_enqueue_script` 追加
- Free の chatbot.js を依存として指定

### Step 5: テスト（1時間）
- Free 単体: session/chat/history/feedback が正常動作
- Free 単体: Pro UI 要素（regenerate, bookmark, search 等）が表示されない
- Pro 有効: 全 Pro 機能が正常動作
- Pro 有効→無効: Pro UI が消え、Free のみで動作

---

## ext_settings 参照の whitelist

Free が読む `pro_features`（`ext_settings`）キー:

### chatbot-widget.php（フロント）
- `badge_icon_type`, `badge_icon_preset`, `badge_icon_image`, `badge_icon_emoji` — バッジアイコン表示
- `show_regenerate_button` — 再生成ボタン表示（Free 機能）
- `offline_message_enabled`, `offline_form_title`, `offline_form_description` — オフライン設定読み取り

### class-rest-controller.php（REST API）
- `lead_capture_enabled`, `lead_capture_required`, `lead_fields`, `lead_custom_fields` — lead-config 返却
- `lead_form_title`, `lead_form_description` — lead-config 返却
- `response_cache_enabled`, `cache_ttl_days` — レスポンスキャッシュ
- `file_upload_enabled`, `file_upload_max_size`, `file_upload_types` — ファイルアップロード設定

### 方針
- 上記キーの読み取りは維持（設定値が空ならデフォルト動作）
- `lead_capture_enabled` は `raplsaich_is_pro_active()` と AND 条件で保護済み
- それ以外の `ext_settings` 参照は Pro フィルタ経由に移行

---

## 工数見積もり

| Step | 作業 | 工数 |
|------|------|------|
| 1 | フックシステム追加 | 30分 |
| 2 | Pro メソッド抽出 | 2-3時間 |
| 3 | Free からPro コード除去 | 1時間 |
| 4 | Pro プラグイン配置 | 30分 |
| 5 | テスト | 1時間 |
| **合計** | | **5-6時間** |

---

## 完了条件

```bash
# Free chatbot.js に Pro API パスがゼロ
grep -c '/regenerate\|/suggestions\|/autocomplete\|/lead\b\|/offline-message\|/conversion\|/handoff' assets/js/chatbot.js
# → 0（proApiRequest 内のコメント除く）

# Free chatbot.js に Pro 初期化メソッドがゼロ
grep -c 'initHandoff\|initOffline\|initBookmark\|initFullscreen\|initWelcome\|initResponseDelay\|initNotification\|initConversion\|initSearch\|initShare' assets/js/chatbot.js
# → 0

# Free 単体で基本機能が動作
# session → chat → history → feedback の E2E テスト

# Pro 有効時に全 Pro 機能が動作
# regenerate, handoff, offline, bookmark, search, share, voice, fullscreen 等
```

# Pro アップセル用スクリーンショット撮影ガイド

**目的**: Free 版のアップセルページに表示する Pro 機能のスクリーンショットを撮影する
**配置先**: `assets/images/pro-preview/`
**形式**: PNG（tinypng.com で圧縮後配置）

---

## 撮影環境の準備

### 1. ローカル環境セットアップ
- [ ] Local by Flywheel で hash.local を起動
- [ ] Free + Pro 両方のプラグインを有効化
- [ ] Pro ライセンスを有効化（ライセンスキーを入力）

### 2. ダミーデータの準備
- [ ] **会話データ**: 最低5件の会話（active 3件、closed 2件）
  - 各会話に3-5メッセージ
  - 1件にハンドオフステータスを設定
- [ ] **リードデータ**: 最低3件（名前、メール、電話、会社名入り）
- [ ] **ナレッジベース**: 最低5件のQ&A（カテゴリ分類付き）
- [ ] **サイト学習**: 最低3ページをインデックス済み
- [ ] **API キー**: OpenAI or Claude のテスト用キーを設定

### 3. ブラウザ設定
- [ ] Google Chrome を使用
- [ ] ウィンドウ幅: **1280px**（DevTools → Toggle Device → Responsive → 1280）
- [ ] ズーム: **100%**
- [ ] 言語: 撮影対象に合わせて **英語** または **日本語**
- [ ] ダークモード: **オフ**（標準の白背景）

---

## 撮影対象と手順（6枚）

### Screenshot 1: `pro-settings.png`
**表示元**: Pro 設定 → 全タブ概要

**手順**:
1. 管理画面 → Rapls AI Chatbot → Pro Settings を開く
2. 「Customer」タブグループが選択された状態
3. 左側にタブグループ（Customer / AI / Operations / Integrations / Management / System）が見える
4. 右側にサブタブ（Lead Capture / Offline / Conversion 等）が見える
5. Lead Capture の設定が表示されている状態

**撮影範囲**: ページ全体（Upgrade バナーは不要、設定エリアのみ）
**ファイル名**: `pro-settings.png`
**サイズ目安**: 1200 x 800px

---

### Screenshot 2: `analytics.png`
**表示元**: Analytics ダッシュボード

**手順**:
1. 管理画面 → Rapls AI Chatbot → Analytics を開く
2. 期間セレクタで「過去30日」を選択
3. 統計カード（Conversations, Messages, Avg Messages, Satisfaction Rate 等）が表示
4. チャートエリア（会話数グラフ、時間帯分布）が表示
5. FAQ ランキングテーブルが見える

**撮影範囲**: 統計カード + チャート（スクロール上部）
**ファイル名**: `analytics.png`
**サイズ目安**: 1200 x 900px

---

### Screenshot 3: `leads.png`
**表示元**: Leads 管理画面

**手順**:
1. 管理画面 → Rapls AI Chatbot → Leads を開く
2. 統計カード（Total Leads, Today, This Week, This Month）が表示
3. リードテーブルに3件以上のデータが表示
4. 各行に名前、メール、電話、会社名、日付が見える
5. エクスポートボタン（CSV/JSON）が見える

**撮影範囲**: 統計カード + テーブル
**ファイル名**: `leads.png`
**サイズ目安**: 1200 x 700px

---

### Screenshot 4: `conversations.png`
**表示元**: Conversations 管理画面（Pro 有効時）

**手順**:
1. 管理画面 → Rapls AI Chatbot → Conversations を開く
2. 会話テーブルに5件以上表示
3. 1件の会話をクリックして詳細パネルを開く
4. メッセージ一覧が表示（ユーザー + AI の吹き出し）
5. エクスポートボタンとフィルタが見える

**撮影範囲**: テーブル + 詳細パネル（モーダル）
**ファイル名**: `conversations.png`
**サイズ目安**: 1200 x 800px

---

### Screenshot 5: `site-learning.png`
**表示元**: Site Learning 管理画面（Pro 有効時）

**手順**:
1. 管理画面 → Rapls AI Chatbot → Site Learning を開く
2. 学習ステータスカード（Indexed Pages, Last Crawl 等）が表示
3. ベクトル埋め込みセクション（Embedding status, Progress bar）が表示
4. インデックス済みページのテーブルが表示
5. 「Enhanced Content Extraction」が有効になっている

**撮影範囲**: ステータスカード + インデックステーブル
**ファイル名**: `site-learning.png`
**サイズ目安**: 1200 x 900px

---

### Screenshot 6: `audit-log.png`
**表示元**: Audit Log 管理画面

**手順**:
1. 管理画面 → Rapls AI Chatbot → Audit Log を開く
2. 監査ログテーブルに5件以上のエントリ
3. タイムスタンプ、イベント種別、ユーザー、説明が表示
4. フィルタ/検索バーが見える
5. エクスポートボタンが見える

**撮影範囲**: テーブル全体
**ファイル名**: `audit-log.png`
**サイズ目安**: 1200 x 600px

---

## 撮影テクニック

### macOS でのスクリーンショット
```bash
# ウィンドウ全体
Cmd + Shift + 4 → Space → ウィンドウをクリック

# 範囲選択
Cmd + Shift + 4 → ドラッグで範囲選択
```

### Chrome DevTools でのフル画面キャプチャ
1. F12 → DevTools を開く
2. Cmd + Shift + P → 「screenshot」と入力
3. 「Capture full size screenshot」を選択
4. 自動でPNGダウンロード

### 推奨方法（最も綺麗）
1. Chrome DevTools でウィンドウ幅を 1280px に固定
2. 該当ページを開く
3. DevTools の「Capture screenshot」（表示範囲のみ）を使用
4. 不要な部分は macOS Preview でトリミング

---

## 撮影後の処理

### 1. トリミング
- [ ] 各画像をブラウザのアドレスバーが含まれないようトリミング
- [ ] WordPress 管理メニュー（左サイドバー）は含めても含めなくてもOK
- [ ] 余白を最小限に

### 2. 圧縮
- [ ] [tinypng.com](https://tinypng.com/) にアップロード
- [ ] 圧縮後のファイルをダウンロード
- [ ] 1枚あたり 50-150KB 目安

### 3. 配置
```bash
mkdir -p assets/images/pro-preview/
# 圧縮済みファイルを配置
cp ~/Downloads/pro-settings.png assets/images/pro-preview/
cp ~/Downloads/analytics.png assets/images/pro-preview/
cp ~/Downloads/leads.png assets/images/pro-preview/
cp ~/Downloads/conversations.png assets/images/pro-preview/
cp ~/Downloads/site-learning.png assets/images/pro-preview/
cp ~/Downloads/audit-log.png assets/images/pro-preview/
```

### 4. 多言語対応（オプション）
英語版と日本語版を撮影する場合:
```
assets/images/pro-preview/
  pro-settings-en.png
  pro-settings-ja.png
  analytics-en.png
  analytics-ja.png
  ...
```

PHP で言語に応じて切替:
```php
$lang = (strpos(get_locale(), 'ja') === 0) ? 'ja' : 'en';
$image = "pro-settings-{$lang}.png";
```

---

## アップセルページへの実装

### 基本パターン（各プレビューページ共通）
```php
private function render_analytics_preview(): void {
    $this->render_pro_upgrade_banner(
        __('Analytics', 'rapls-ai-chatbot'),
        __('Track chatbot performance with detailed analytics.', 'rapls-ai-chatbot'),
        [/* feature list */]
    );
    // スクリーンショット表示
    $img_url = RAPLSAICH_PLUGIN_URL . 'assets/images/pro-preview/analytics.png';
    echo '<div style="margin-top:20px;text-align:center;">';
    echo '<img src="' . esc_url($img_url) . '" alt="' . esc_attr__('Analytics preview', 'rapls-ai-chatbot') . '" ';
    echo 'style="max-width:100%;border:1px solid #ddd;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,.1);">';
    echo '</div>';
}
```

---

## チェックリスト

- [ ] 6枚のスクリーンショットを撮影
- [ ] 全て tinypng.com で圧縮
- [ ] `assets/images/pro-preview/` に配置
- [ ] `.gitattributes` に画像パスが export-ignore されていないことを確認
- [ ] 各アップセルページに `<img>` タグを追加
- [ ] Free 単体で画像が正しく表示されることを確認
- [ ] `git archive` で ZIP を作成し、画像が含まれることを確認

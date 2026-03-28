# WordPress.org レビュー修正計画（第3ラウンド）

**レビューID**: R rapls-ai-chatbot/rapls/25Mar26/T3 27Mar26/3.9A7
**対象**: rapls-ai-chatbot (Free) v1.5.3

---

## 指摘4件と対応方針

### Issue 1: カスタム CSS の任意挿入（HIGH）

**指摘**: `custom_css` 設定でユーザーが任意 CSS を入力し、フロントに挿入されている。
WordPress のカスタマイザー CSS エディタが既にあるため、プラグインでの任意コード挿入は不許可。

**該当コード**: `includes/frontend/class-chatbot-widget.php` Lines 219-225

```php
if (!empty($pro_settings['custom_css'])) {
    $safe_css = $pro_settings['custom_css'];
    // ... 不十分な手動サニタイズ ...
    $custom_css .= "\n" . $safe_css;
}
```

**対応方針**: **custom_css 機能を Free から完全除去**。
- Free のコードから `custom_css` の読み取り・挿入を削除
- Pro 側で管理（Pro のフロントエンド config フィルタ経由で注入する場合も、
  WordPress のカスタマイザーを推奨する方向に変更）
- レビュー返信で「custom_css 機能を除去し、WordPress のカスタマイザー CSS エディタの使用を推奨する」と説明

**工数**: 15分

---

### Issue 2: 外部サービスの利用規約/プライバシーリンク不足（HIGH）

**指摘**: OpenAI の ToS/Privacy リンクが readme に記載されていないとの指摘。
また `embed-loader.js` の外部サイトスクリプト読み込みも言及。

**実態確認**: readme.txt Lines 269-295 に全5サービスの ToS/Privacy リンクは**記載済み**。
レビューアーが見落とした可能性があるが、より目立つ形式に修正してリスクを排除する。

**対応方針**:
- 各サービスの ToS/Privacy リンクをクリック可能な形式で再フォーマット
- `embed-loader.js` の説明を追加（外部サイトからの iframe 読み込みであり、
  プラグイン自身が外部 CDN を読み込むわけではないことを明記）
- セクションの冒頭に総括文を追加

**工数**: 30分

---

### Issue 3: ブロックレンダーの出力エスケープ（MEDIUM）

**指摘**: `render.php` で `do_shortcode()` の出力を `echo` しているが、エスケープされていない。

**該当コード**: `includes/block/render.php` Line 27
```php
echo do_shortcode('[rapls_chatbot' . $raplsaich_shortcode_atts . ']');
```

**実態**: ショートコード属性は `esc_attr()` でエスケープ済み。`do_shortcode()` の出力は
ウィジェット HTML（信頼済み）だが、レビューアーの指摘に従い `wp_kses_post()` でラップする。

**対応方針**:
```php
echo wp_kses_post(do_shortcode('[rapls_chatbot' . $raplsaich_shortcode_atts . ']'));
```

ただし `wp_kses_post` はウィジェットの SVG アイコンを除去する可能性がある。
その場合は `wp_kses` にカスタム許可タグリストを使用。

**工数**: 15分（テスト込み30分）

---

### Issue 4: CSS 変数のエスケープ不足（HIGH）

**指摘**: `wp_add_inline_style` に渡す `$primary_color` と CSS 変数がエスケープされていない。

**該当コード**: `includes/frontend/class-chatbot-widget.php` Lines 204-228

```php
$custom_css = "
    :root {
        --raplsaich-primary: {$primary_color};           // 未エスケープ
        --raplsaich-primary-dark: " . $this->darken_color($primary_color, 20) . ";  // 未エスケープ
    }
";
```

**対応方針**: 全 CSS 変数値を `esc_attr()` でエスケープ
```php
$custom_css = ":root{--raplsaich-primary:" . esc_attr($primary_color)
    . ";--raplsaich-primary-dark:" . esc_attr($this->darken_color($primary_color, 20)) . ";}";
```

`$position_css` の margin 値も `absint()` で数値保証。

**工数**: 30分

---

## 実行順序

| Step | 作業 | 工数 | 優先度 |
|------|------|------|--------|
| 1 | custom_css 機能を Free から除去 | 15分 | 高 |
| 2 | CSS 変数のエスケープ修正 | 30分 | 高 |
| 3 | ブロックレンダーの wp_kses_post 追加 | 30分 | 中 |
| 4 | readme.txt 外部サービスセクション強化 | 30分 | 高 |
| 5 | テスト・Plugin Check 実行 | 30分 | |
| **合計** | | **2時間15分** | |

---

## レビュー返信テンプレート（修正後）

```
Thank you for the detailed review and I apologize for the issues found.

1. Custom CSS: Removed the arbitrary custom CSS injection feature entirely.
   Users should use the WordPress Customizer CSS editor instead.

2. External Services: Reformatted the External Services section with
   clearer links. All ToS and Privacy Policy URLs verified.

3. Block Render: Added wp_kses_post() escaping to block render output.

4. CSS Escaping: All CSS variable values now escaped with esc_attr().
   Position values use absint() for numeric safety.

The corrected version has been uploaded.
```

/**
 * WP AI Chatbot - 管理画面スクリプト
 */

(function($) {
    'use strict';

    /**
     * Destructive AJAX helper — handles two-step confirmation tokens.
     * First call returns confirm_required + token; second call sends token back.
     */
    window.wpaicDestructiveAjax = function(opts) {
        var data = $.extend({}, opts.data);
        $.post(opts.url || ajaxurl, data, function(response) {
            if (response.success && response.data && response.data.confirm_required) {
                // Server issued a token — confirm with user and retry
                if (confirm(response.data.message)) {
                    data.confirm_token = response.data.confirm_token;
                    $.post(opts.url || ajaxurl, data, function(r2) {
                        if (opts.success) opts.success(r2);
                    }).fail(function() {
                        if (opts.fail) opts.fail();
                    });
                } else {
                    if (opts.cancel) opts.cancel();
                }
            } else {
                if (opts.success) opts.success(response);
            }
        }).fail(function() {
            if (opts.fail) opts.fail();
        });
    };

    $(document).ready(function() {

        // タブ切り替え
        $('.wpaic-settings-tabs .nav-tab').on('click', function(e) {
            e.preventDefault();

            var targetId = $(this).attr('href');

            // タブの状態を更新
            $('.wpaic-settings-tabs .nav-tab').removeClass('nav-tab-active');
            $(this).addClass('nav-tab-active');

            // コンテンツの表示を切り替え
            $('.wpaic-settings-tabs .tab-content').removeClass('active');
            $(targetId).addClass('active');

            // URLハッシュを更新
            history.replaceState(null, null, targetId);

            // タブをlocalStorageに保存（設定保存後も維持するため）
            localStorage.setItem('wpaic_active_tab', targetId);

            // Pro機能タブのときはグローバル保存ボタンを非表示
            if (targetId === '#tab-pro') {
                $('#wpaic-global-submit').hide();
            } else {
                $('#wpaic-global-submit').show();
            }
        });

        // 設定保存後のみタブを復元（URLにsettings-updatedがある場合）
        var urlParams = new URLSearchParams(window.location.search);
        var settingsUpdated = urlParams.get('settings-updated');

        if (window.location.hash) {
            // URLハッシュがある場合はそのタブを表示
            var $tab = $('.wpaic-settings-tabs .nav-tab[href="' + window.location.hash + '"]');
            if ($tab.length) {
                $tab.trigger('click');
            }
        } else if (settingsUpdated) {
            // 設定保存後は保存されたタブを復元
            var savedTab = localStorage.getItem('wpaic_active_tab');
            if (savedTab) {
                var $tab = $('.wpaic-settings-tabs .nav-tab[href="' + savedTab + '"]');
                if ($tab.length) {
                    $tab.trigger('click');
                }
            }
        } else {
            // 通常のページ遷移時はlocalStorageをクリア（最初のタブを表示）
            localStorage.removeItem('wpaic_active_tab');
        }

        // AIプロバイダー切り替え時の表示制御
        $('#ai_provider').on('change', function() {
            var provider = $(this).val();

            // 全てのプロバイダー設定を非表示
            $('.provider-settings').hide();

            // 選択されたプロバイダーの設定を表示
            $('#' + provider + '-settings').show();
        }).trigger('change');

        // APIキー削除 — sets hidden delete flag; key removed on save
        $('.wpaic-clear-api-key').on('click', function() {
            var targetId = $(this).data('target');
            var $input = $('#' + targetId);
            var $deleteFlag = $('#delete_' + targetId);
            var $wrapper = $(this).closest('.wpaic-api-key-wrapper');

            if (confirm('APIキーを削除しますか？\n削除後、設定を保存してください。')) {
                $input.val('').attr('placeholder', '');
                $deleteFlag.val('1');
                $(this).hide();
                $wrapper.find('.wpaic-key-status')
                    .removeClass('wpaic-key-set')
                    .addClass('wpaic-key-empty')
                    .text('未設定（保存で削除）');
            }
        });

        // API接続テスト — uses entered key, or saved key via 'use_saved' flag
        $('.wpaic-test-api').on('click', function() {
            var $button = $(this);
            var provider = $button.data('provider');
            var $input = $button.siblings('input[type="password"]');
            var apiKey = $input.val();
            var useSaved = !apiKey && $input.attr('placeholder');

            if (!apiKey && !useSaved) {
                alert('APIキーを入力してください。');
                return;
            }

            $button.prop('disabled', true).text('テスト中...');

            $.ajax({
                url: wpaicAdmin.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'wpaic_test_api',
                    nonce: wpaicAdmin.nonce,
                    provider: provider,
                    api_key: apiKey,
                    use_saved: useSaved ? '1' : ''
                },
                success: function(response) {
                    if (response.success) {
                        alert('✓ ' + response.data);
                        // Fetch models after successful connection test
                        fetchModels(provider, apiKey, false, false);
                    } else {
                        alert('✗ ' + response.data);
                    }
                },
                error: function() {
                    alert('エラーが発生しました。');
                },
                complete: function() {
                    $button.prop('disabled', false).text('接続テスト');
                }
            });
        });

        // ============================================
        // 動的モデル取得
        // ============================================

        /**
         * Fetch models from API and update dropdown
         */
        function fetchModels(provider, apiKey, useSaved, forceRefresh) {
            var selectId = '#wpaic-' + provider + '-model';
            var $select = $(selectId);
            if (!$select.length) return;

            var currentValue = $select.val();
            var i18n = wpaicAdmin.i18n || {};

            // Disable dropdown and show loading
            $select.prop('disabled', true);
            var $loading = $('<option>').val('').text(i18n.loadingModels || 'Loading models...');
            var existingOptions = $select.html();
            $select.html($loading);

            var data = {
                action: 'wpaic_fetch_models',
                nonce: wpaicAdmin.nonce,
                provider: provider,
                use_saved: useSaved ? 1 : 0,
                force_refresh: forceRefresh ? 1 : 0
            };
            if (apiKey) {
                data.api_key = apiKey;
            }

            $.ajax({
                url: wpaicAdmin.ajaxUrl,
                method: 'POST',
                data: data,
                success: function(response) {
                    if (!response.success || !response.data || !response.data.models) {
                        // Restore existing options on failure
                        $select.html(existingOptions);
                        return;
                    }

                    var models = response.data.models;
                    var visionModels = response.data.vision_models || [];

                    // Check if any models returned
                    if ($.isEmptyObject(models)) {
                        $select.html(existingOptions);
                        return;
                    }

                    // Rebuild options
                    $select.empty();
                    var savedValue = $select.data('initial-value') || currentValue;
                    var savedExists = false;

                    $.each(models, function(id, label) {
                        var isVision = ($.inArray(id, visionModels) !== -1) ? '1' : '0';
                        var $option = $('<option>')
                            .val(id)
                            .text(label)
                            .attr('data-vision', isVision);
                        $select.append($option);

                        if (id === savedValue) {
                            savedExists = true;
                        }
                    });

                    // Restore selection
                    if (savedExists) {
                        $select.val(savedValue);
                    } else if (savedValue) {
                        // Saved model not in new list - add it with (saved) label
                        var $savedOption = $('<option>')
                            .val(savedValue)
                            .text(savedValue + ' ' + (i18n.modelSaved || '(saved)'));
                        $select.prepend($savedOption);
                        $select.val(savedValue);
                    }
                },
                error: function() {
                    // Restore existing options on error
                    $select.html(existingOptions);
                },
                complete: function() {
                    $select.prop('disabled', false);
                }
            });
        }

        // Trigger 1: Page load - fetch models for providers with saved keys
        (function() {
            var providers = ['openai', 'claude', 'gemini'];
            $.each(providers, function(_, provider) {
                var $select = $('#wpaic-' + provider + '-model');
                var $keyInput = $('#' + provider + '_api_key');
                // Only fetch if API key is configured (has placeholder indicating it's set)
                if ($select.length && $keyInput.length) {
                    var hasKey = $keyInput.attr('placeholder') && $keyInput.attr('placeholder').indexOf('••') !== -1;
                    if (hasKey) {
                        fetchModels(provider, null, true, false);
                    }
                }
            });
        })();

        // Trigger 2: API key input change
        $('#openai_api_key, #claude_api_key, #gemini_api_key').on('change', function() {
            var $input = $(this);
            var apiKey = $input.val();
            if (apiKey.length < 10) return;

            var provider = 'openai';
            if ($input.attr('id') === 'claude_api_key') provider = 'claude';
            if ($input.attr('id') === 'gemini_api_key') provider = 'gemini';

            fetchModels(provider, apiKey, false, false);
        });

        // Trigger 3: Refresh button click
        $('.wpaic-refresh-models').on('click', function() {
            var provider = $(this).data('provider');
            var $keyInput = $('#' + provider + '_api_key');
            var apiKey = $keyInput.val();
            var useSaved = !apiKey;

            fetchModels(provider, apiKey || null, useSaved, true);
        });

        // 手動クロール
        $('#wpaic-manual-crawl').on('click', function() {
            var $button = $(this);
            var $status = $('#crawl-status');

            if (!confirm('サイト全体の学習を実行しますか？\nページ数によっては時間がかかる場合があります。')) {
                return;
            }

            $button.prop('disabled', true);
            $status.text('学習中...');

            $.ajax({
                url: wpaicAdmin.ajaxUrl,
                method: 'POST',
                timeout: 300000, // 5 minutes — manual crawl processes all content
                data: {
                    action: 'wpaic_manual_crawl',
                    nonce: wpaicAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $status.text('✓ ' + response.data.message);
                        // 3秒後にページをリロード
                        setTimeout(function() {
                            location.reload();
                        }, 2000);
                    } else {
                        $status.text('✗ ' + response.data);
                    }
                },
                error: function() {
                    $status.text('✗ エラーが発生しました。');
                },
                complete: function() {
                    $button.prop('disabled', false);
                }
            });
        });

        // 会話履歴の詳細表示（モーダル用AJAX）
        // conversations.phpに直接記述しているため、ここでは不要
        // 必要に応じて共通化可能

        // Webhookテスト
        $('#wpaic-test-webhook').on('click', function() {
            var $button = $(this);
            var $result = $('#wpaic-webhook-test-result');

            $button.prop('disabled', true);
            $result.text('テスト中...').css('color', '#666');

            $.ajax({
                url: wpaicAdmin.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'wpaic_test_webhook',
                    nonce: wpaicAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $result.text('✓ ' + response.data).css('color', '#155724');
                    } else {
                        $result.text('✗ ' + response.data).css('color', '#721c24');
                    }
                },
                error: function() {
                    $result.text('✗ エラーが発生しました。').css('color', '#721c24');
                },
                complete: function() {
                    $button.prop('disabled', false);
                }
            });
        });

        // 会話エクスポート
        $('#wpaic-export-conversations').on('click', function() {
            var $button = $(this);
            var format = $('#wpaic-export-format').val() || 'csv';
            var dateFrom = $('#wpaic-export-date-from').val() || '';
            var dateTo = $('#wpaic-export-date-to').val() || '';

            $button.prop('disabled', true).text('エクスポート中...');

            $.ajax({
                url: wpaicAdmin.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'wpaic_export_conversations',
                    nonce: wpaicAdmin.nonce,
                    format: format,
                    date_from: dateFrom,
                    date_to: dateTo
                },
                success: function(response) {
                    if (response.success) {
                        downloadExport(response.data);
                    } else {
                        alert('エクスポートに失敗しました: ' + response.data);
                    }
                },
                error: function() {
                    alert('エラーが発生しました。');
                },
                complete: function() {
                    $button.prop('disabled', false).text('エクスポート');
                }
            });
        });

        // リードエクスポート
        $('#wpaic-export-leads').on('click', function() {
            var $button = $(this);
            var format = $('#wpaic-leads-export-format').val() || 'csv';
            var dateFrom = $('#wpaic-leads-export-date-from').val() || '';
            var dateTo = $('#wpaic-leads-export-date-to').val() || '';

            $button.prop('disabled', true).text('エクスポート中...');

            $.ajax({
                url: wpaicAdmin.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'wpaic_export_leads',
                    nonce: wpaicAdmin.nonce,
                    format: format,
                    date_from: dateFrom,
                    date_to: dateTo
                },
                success: function(response) {
                    if (response.success) {
                        downloadExport(response.data);
                    } else {
                        alert('エクスポートに失敗しました: ' + response.data);
                    }
                },
                error: function() {
                    alert('エラーが発生しました。');
                },
                complete: function() {
                    $button.prop('disabled', false).text('エクスポート');
                }
            });
        });

        // クイックリプライ動的管理
        var $quickRepliesContainer = $('#wpaic-quick-replies');
        var quickReplyIndex = $quickRepliesContainer.find('.wpaic-quick-reply-item').length;

        $('#wpaic-add-quick-reply').on('click', function() {
            var html = '<div class="wpaic-quick-reply-item" style="margin-bottom: 8px; display: flex; gap: 8px;">' +
                '<input type="text" name="wpaic_settings[pro_features][quick_replies][' + quickReplyIndex + '][text]" ' +
                'class="regular-text" placeholder="例: 営業時間を教えてください">' +
                '<button type="button" class="button wpaic-remove-quick-reply">×</button>' +
                '</div>';
            $quickRepliesContainer.append(html);
            quickReplyIndex++;
        });

        $quickRepliesContainer.on('click', '.wpaic-remove-quick-reply', function() {
            $(this).closest('.wpaic-quick-reply-item').remove();
        });

        // 休日動的管理
        var $holidaysContainer = $('#wpaic-holidays');
        var holidayIndex = $holidaysContainer.find('.wpaic-holiday-item').length;

        $('#wpaic-add-holiday').on('click', function() {
            var html = '<div class="wpaic-holiday-item" style="margin-bottom: 8px; display: flex; gap: 8px;">' +
                '<input type="date" name="wpaic_settings[pro_features][holidays][' + holidayIndex + '][date]">' +
                '<input type="text" name="wpaic_settings[pro_features][holidays][' + holidayIndex + '][name]" ' +
                'class="regular-text" placeholder="休日名（任意）">' +
                '<button type="button" class="button wpaic-remove-holiday">×</button>' +
                '</div>';
            $holidaysContainer.append(html);
            holidayIndex++;
        });

        $holidaysContainer.on('click', '.wpaic-remove-holiday', function() {
            $(this).closest('.wpaic-holiday-item').remove();
        });

        // プロンプトテンプレート動的管理
        var $templatesContainer = $('#wpaic-prompt-templates');
        var templateIndex = $templatesContainer.find('.wpaic-prompt-template-item').length;

        $('#wpaic-add-template').on('click', function() {
            var html = '<div class="wpaic-prompt-template-item" style="margin-bottom: 15px; padding: 15px; border: 1px solid #ddd; border-radius: 4px;">' +
                '<div style="margin-bottom: 10px;">' +
                '<input type="text" name="wpaic_settings[pro_features][prompt_templates][' + templateIndex + '][name]" ' +
                'placeholder="テンプレート名" class="regular-text">' +
                '<button type="button" class="button wpaic-remove-template">×</button>' +
                '</div>' +
                '<textarea name="wpaic_settings[pro_features][prompt_templates][' + templateIndex + '][prompt]" ' +
                'rows="3" class="large-text" placeholder="このテンプレートのシステムプロンプト..."></textarea>' +
                '</div>';
            $templatesContainer.append(html);
            templateIndex++;
        });

        $templatesContainer.on('click', '.wpaic-remove-template', function() {
            $(this).closest('.wpaic-prompt-template-item').remove();
        });

        // Pro機能セクションの有効/無効切り替え
        $('.wpaic-pro-toggle').on('change', function() {
            var $section = $(this).closest('.wpaic-pro-section');
            var $content = $section.find('.wpaic-pro-section-content');
            if ($(this).is(':checked')) {
                $content.slideDown();
            } else {
                $content.slideUp();
            }
        });

        // 初期状態で無効なセクションを閉じる
        $('.wpaic-pro-toggle:not(:checked)').each(function() {
            $(this).closest('.wpaic-pro-section').find('.wpaic-pro-section-content').hide();
        });

        // エクスポートダウンロード
        function downloadExport(data) {
            var content, type;

            if (data.format === 'json') {
                content = JSON.stringify(data.data, null, 2);
                type = 'application/json';
            } else {
                // CSV with BOM for Excel
                var rows = data.data.map(function(row) {
                    return row.map(function(cell) {
                        // Escape quotes and wrap in quotes if contains comma or newline
                        var str = String(cell || '');
                        if (str.indexOf(',') !== -1 || str.indexOf('\n') !== -1 || str.indexOf('"') !== -1) {
                            str = '"' + str.replace(/"/g, '""') + '"';
                        }
                        return str;
                    }).join(',');
                }).join('\n');
                content = '\uFEFF' + rows; // BOM for Excel
                type = 'text/csv;charset=utf-8';
            }

            var blob = new Blob([content], { type: type });
            var url = URL.createObjectURL(blob);
            var a = document.createElement('a');
            a.href = url;
            a.download = data.filename;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
        }

        // テーマ別のプライマリカラー設定
        var themeColors = {
            'default': '#007bff',
            'simple': '#6c757d',
            'classic': '#2c3e50',
            'light': '#5c9eff',
            'minimal': '#333333',
            'flat': '#3498db',
            'modern': '#00d4ff',
            'gradient': '#667eea',
            'dark': '#4a9eff',
            'glass': '#667eea',
            'rounded': '#ff6b6b',
            'ocean': '#00bcd4',
            'sunset': '#ff6b35',
            'forest': '#2d6a4f',
            'neon': '#00ffff',
            'elegant': '#c9a961'
        };

        // テーマセレクター
        $('.wpaic-theme-option').on('click', function(e) {
            var $option = $(this);

            // disabled状態の場合は何もしない
            if ($option.hasClass('disabled')) {
                e.preventDefault();
                return false;
            }

            // ラジオボタンをチェック
            var $radio = $option.find('input[type="radio"]');
            if ($radio.prop('disabled')) {
                e.preventDefault();
                return false;
            }

            $radio.prop('checked', true);

            // 全てのテーマオプションからselectedを削除
            $('.wpaic-theme-option').removeClass('selected');

            // クリックしたオプションにselectedを追加
            $option.addClass('selected');

            // テーマに対応するプライマリカラーを設定
            var themeValue = $radio.val();
            if (themeColors[themeValue]) {
                var $colorField = $('#wpaic_primary_color');
                if ($colorField.length && $.fn.wpColorPicker) {
                    $colorField.wpColorPicker('color', themeColors[themeValue]);
                } else {
                    $colorField.val(themeColors[themeValue]);
                }
            }

            // ダーク系テーマの場合はダークモードをオンに、それ以外はオフに
            var darkThemes = ['dark', 'modern', 'neon'];
            if (darkThemes.indexOf(themeValue) !== -1) {
                $('input[name="wpaic_settings[dark_mode]"]').prop('checked', true);
            } else {
                $('input[name="wpaic_settings[dark_mode]"]').prop('checked', false);
            }
        });

        // ============================================
        // タブリセットボタン
        // ============================================
        $('.wpaic-reset-tab-btn').on('click', function() {
            var tabId = $(this).data('tab');
            var i18n = wpaicAdmin.i18n || {};
            var defaults = wpaicAdmin.defaults || {};

            if (!confirm(i18n.confirmResetTab || 'Reset all settings in this tab to defaults?')) {
                return;
            }

            resetTabToDefaults(tabId, defaults);
        });

        // タブをデフォルトにリセットする関数
        function resetTabToDefaults(tabId, defaults) {
            var $tab = $('#' + tabId);

            // タブ内のフィールドをリセット
            $tab.find('input, select, textarea').each(function() {
                var $field = $(this);
                var name = $field.attr('name');
                if (!name) return;

                // wpaic_settings[field_name] 形式から field_name を抽出
                var match = name.match(/wpaic_settings\[([^\]]+)\]/);
                if (!match) return;

                var fieldName = match[1];
                var defaultValue = defaults[fieldName];

                // pro_features の場合
                var proMatch = name.match(/wpaic_settings\[pro_features\]\[([^\]]+)\]/);
                if (proMatch && defaults.pro_features) {
                    fieldName = proMatch[1];
                    defaultValue = defaults.pro_features[fieldName];
                }

                if (defaultValue === undefined) return;

                // フィールドタイプに応じてリセット
                if ($field.is(':checkbox')) {
                    $field.prop('checked', !!defaultValue);
                } else if ($field.is(':radio')) {
                    if ($field.val() === String(defaultValue)) {
                        $field.prop('checked', true);
                    }
                } else if ($field.is('select')) {
                    $field.val(defaultValue);
                } else {
                    $field.val(defaultValue);
                }
            });

            // テーマセレクター特別処理
            if (tabId === 'tab-display') {
                var defaultTheme = defaults.widget_theme || 'default';
                $('.wpaic-theme-option').removeClass('selected');
                $('input[name="wpaic_settings[widget_theme]"][value="' + defaultTheme + '"]')
                    .prop('checked', true)
                    .closest('.wpaic-theme-option').addClass('selected');
            }
        }

        // ============================================
        // フィールドリセットボタン（新形式）
        // ============================================
        $('.wpaic-field-reset').on('click', function() {
            var $btn = $(this);
            var fieldName = $btn.data('field');
            var i18n = wpaicAdmin.i18n || {};
            var defaults = wpaicAdmin.defaults || {};

            if (!confirm(i18n.confirmResetField || 'Reset this field to default?')) {
                return;
            }

            var defaultValue;

            // pro_features かどうかをチェック
            if (fieldName.indexOf('pro_features.') === 0) {
                var proFieldName = fieldName.replace('pro_features.', '');
                defaultValue = defaults.pro_features ? defaults.pro_features[proFieldName] : undefined;
            } else {
                defaultValue = defaults[fieldName];
            }

            if (defaultValue === undefined) return;

            // 対応するフィールドを探してリセット
            var $field = $btn.siblings('input, textarea, select').first();
            if (!$field.length) {
                $field = $btn.prev('input, textarea, select');
            }

            if ($field.length) {
                if ($field.is(':checkbox')) {
                    $field.prop('checked', !!defaultValue);
                } else {
                    $field.val(defaultValue);
                }
            }
        });

        // ============================================
        // フィールドリセットボタン（既存形式）
        // ============================================
        $('.wpaic-reset-field').on('click', function() {
            var $btn = $(this);
            var targetId = $btn.data('target');
            var defaultValue = $btn.data('default');

            if (targetId && defaultValue !== undefined) {
                var $field = $('#' + targetId);
                if ($field.hasClass('wpaic-color-field') && $.fn.wpColorPicker) {
                    $field.wpColorPicker('color', defaultValue);
                } else {
                    $field.val(defaultValue).trigger('change');
                }
            }
        });

        // Initialize WordPress color picker
        if ($.fn.wpColorPicker) {
            $('.wpaic-color-field').wpColorPicker();
        }

    });

    // Advanced section toggle (checkbox-gated, state persisted in localStorage)
    $('.wpaic-advanced-toggle').each(function () {
        var key = 'wpaic_adv_' + this.id;
        if (localStorage.getItem(key) === '1') {
            this.checked = true;
            $('#' + $(this).data('target')).removeClass('wpaic-advanced-disabled');
        }
    }).on('change', function () {
        var targetId = $(this).data('target');
        var $section = $('#' + targetId);
        var key = 'wpaic_adv_' + this.id;
        if (this.checked) {
            $section.removeClass('wpaic-advanced-disabled');
            localStorage.setItem(key, '1');
        } else {
            $section.addClass('wpaic-advanced-disabled');
            localStorage.removeItem(key);
        }
    });

    // Per-language welcome messages: show/hide based on response_language
    $('#wpaic_response_language').on('change', function() {
        $('#wpaic-per-language-welcome').toggle($(this).val() === 'auto');
    });

    // Clear individual per-language welcome message
    $(document).on('click', '.wpaic-reset-welcome-lang', function() {
        var targetId = $(this).data('target');
        $('#' + targetId).val('');
    });

    // Clear all per-language welcome messages
    $('#wpaic-reset-all-welcome-langs').on('click', function() {
        $('#wpaic-per-language-welcome textarea').val('');
    });

    // Badge position: update active state and margin labels
    $('input[name="wpaic_settings[badge_position]"]').on('change', function() {
        var pos = $(this).val();
        var isLeft = (pos === 'bottom-left' || pos === 'top-left');
        var isTop = (pos === 'top-right' || pos === 'top-left');
        var i18n = (typeof wpaicAdmin !== 'undefined' && wpaicAdmin.i18n) ? wpaicAdmin.i18n : {};
        // Update active class on grid
        $('.wpaic-badge-pos-option').removeClass('active');
        $(this).closest('.wpaic-badge-pos-option').addClass('active');
        // Update margin labels
        $('#wpaic_margin_h_label').text(isLeft ? (i18n.leftLabel || 'Left:') : (i18n.rightLabel || 'Right:'));
        $('#wpaic_margin_v_label').text(isTop ? (i18n.topLabel || 'Top:') : (i18n.bottomLabel || 'Bottom:'));
    });

})(jQuery);

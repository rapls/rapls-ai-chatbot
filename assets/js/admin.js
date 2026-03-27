/**
 * WP AI Chatbot - 管理画面スクリプト
 */

(function($) {
    'use strict';

    /**
     * Destructive AJAX helper — handles two-step confirmation tokens.
     * First call returns confirm_required + token; second call sends token back.
     */
    window.raplsaichDestructiveAjax = function(opts) {
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
        $('.raplsaich-settings-tabs .nav-tab').on('click', function(e) {
            e.preventDefault();

            var targetId = $(this).attr('href');

            // タブの状態を更新
            $('.raplsaich-settings-tabs .nav-tab').removeClass('nav-tab-active');
            $(this).addClass('nav-tab-active');

            // コンテンツの表示を切り替え
            $('.raplsaich-settings-tabs .tab-content').removeClass('active');
            $(targetId).addClass('active');

            // URLハッシュを更新
            history.replaceState(null, null, targetId);

            // タブをlocalStorageに保存（設定保存後も維持するため）
            try { localStorage.setItem('raplsaich_active_tab', targetId); } catch (e) { /* storage unavailable */ }

            // Pro機能タブのときはグローバル保存ボタンを非表示
            if (targetId === '#tab-pro') {
                $('#raplsaich-global-submit').hide();
            } else {
                $('#raplsaich-global-submit').show();
            }
        });

        // 設定保存後のみタブを復元（URLにsettings-updatedがある場合）
        var urlParams = new URLSearchParams(window.location.search);
        var settingsUpdated = urlParams.get('settings-updated');

        if (window.location.hash) {
            // URLハッシュがある場合はそのタブを表示
            var $tab = $('.raplsaich-settings-tabs .nav-tab').filter(function() {
                return $(this).attr('href') === window.location.hash;
            });
            if ($tab.length) {
                $tab.trigger('click');
            }
        } else if (settingsUpdated) {
            // 設定保存後は保存されたタブを復元
            var savedTab = null; try { savedTab = localStorage.getItem('raplsaich_active_tab'); } catch (e) { /* storage unavailable */ }
            if (savedTab) {
                var $tab = $('.raplsaich-settings-tabs .nav-tab').filter(function() {
                    return $(this).attr('href') === savedTab;
                });
                if ($tab.length) {
                    $tab.trigger('click');
                }
            }
        } else {
            // 通常のページ遷移時はlocalStorageをクリア（最初のタブを表示）
            try { localStorage.removeItem('raplsaich_active_tab'); } catch (e) { /* storage unavailable */ }
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
        $('.raplsaich-clear-api-key').on('click', function() {
            var targetId = $(this).data('target');
            var $input = $('#' + targetId);
            var $deleteFlag = $('#delete_' + targetId);
            var $wrapper = $(this).closest('.raplsaich-api-key-wrapper');

            if (confirm(raplsaichAdmin.i18n.confirmDeleteApiKey || 'Delete this API key?\nPlease save settings after deletion.')) {
                $input.val('').attr('placeholder', '');
                $deleteFlag.val('1');
                $(this).hide();
                $wrapper.find('.raplsaich-key-status')
                    .removeClass('raplsaich-key-set')
                    .addClass('raplsaich-key-empty')
                    .text(raplsaichAdmin.i18n.keyUnset || 'Not set (will be deleted on save)');
            }
        });

        // API接続テスト — uses entered key, or saved key via 'use_saved' flag
        $('.raplsaich-test-api').on('click', function() {
            var $button = $(this);
            var provider = $button.data('provider');
            var $input = $button.siblings('input[type="password"]');
            var apiKey = $input.val();
            var useSaved = !apiKey && $input.attr('placeholder');

            if (!apiKey && !useSaved) {
                alert(raplsaichAdmin.i18n.enterApiKey || 'Please enter an API key.');
                return;
            }

            $button.prop('disabled', true).text(raplsaichAdmin.i18n.testing || 'Testing...');

            $.ajax({
                url: raplsaichAdmin.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'raplsaich_test_api',
                    nonce: raplsaichAdmin.nonce,
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
                    alert(raplsaichAdmin.i18n.error || 'Error');
                },
                complete: function() {
                    $button.prop('disabled', false).text(raplsaichAdmin.i18n.connectionTest || 'Connection test');
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
            var selectId = '#raplsaich-' + provider + '-model';
            var $select = $(selectId);
            if (!$select.length) return;

            var currentValue = $select.val();
            var i18n = raplsaichAdmin.i18n || {};

            // Disable dropdown and show loading
            $select.prop('disabled', true);
            var $loading = $('<option>').val('').text(i18n.loadingModels || 'Loading models...');
            var existingOptions = $select.html();
            $select.html($loading);

            var data = {
                action: 'raplsaich_fetch_models',
                nonce: raplsaichAdmin.nonce,
                provider: provider,
                use_saved: useSaved ? 1 : 0,
                force_refresh: forceRefresh ? 1 : 0
            };
            if (apiKey) {
                data.api_key = apiKey;
            }

            $.ajax({
                url: raplsaichAdmin.ajaxUrl,
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
                var $select = $('#raplsaich-' + provider + '-model');
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
        $('.raplsaich-refresh-models').on('click', function() {
            var provider = $(this).data('provider');
            var $keyInput = $('#' + provider + '_api_key');
            var apiKey = $keyInput.val();
            var useSaved = !apiKey;

            fetchModels(provider, apiKey || null, useSaved, true);
        });

        // 手動クロール
        $('#raplsaich-manual-crawl').on('click', function() {
            var $button = $(this);
            var $status = $('#crawl-status');

            if (!confirm(raplsaichAdmin.i18n.confirmCrawl || 'Run site-wide learning?\nThis may take a while depending on the number of pages.')) {
                return;
            }

            $button.prop('disabled', true);
            $status.text(raplsaichAdmin.i18n.crawling || 'Learning...');

            $.ajax({
                url: raplsaichAdmin.ajaxUrl,
                method: 'POST',
                timeout: 300000, // 5 minutes — manual crawl processes all content
                data: {
                    action: 'raplsaich_manual_crawl',
                    nonce: raplsaichAdmin.nonce
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
                    $status.text('✗ ' + (raplsaichAdmin.i18n.error || 'Error'));
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
        $('#raplsaich-test-webhook').on('click', function() {
            var $button = $(this);
            var $result = $('#raplsaich-webhook-test-result');

            $button.prop('disabled', true);
            $result.text((raplsaichAdmin.i18n && raplsaichAdmin.i18n.testing) || 'Testing...').css('color', '#666');

            $.ajax({
                url: raplsaichAdmin.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'raplsaich_test_webhook',
                    nonce: raplsaichAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $result.text('✓ ' + response.data).css('color', '#155724');
                    } else {
                        $result.text('✗ ' + response.data).css('color', '#721c24');
                    }
                },
                error: function() {
                    $result.text('✗ ' + ((raplsaichAdmin.i18n && raplsaichAdmin.i18n.error) || 'An error occurred.')).css('color', '#721c24');
                },
                complete: function() {
                    $button.prop('disabled', false);
                }
            });
        });

        // 会話エクスポート
        $('#raplsaich-export-conversations').on('click', function() {
            var $button = $(this);
            var format = $('#raplsaich-export-format').val() || 'csv';
            var dateFrom = $('#raplsaich-export-date-from').val() || '';
            var dateTo = $('#raplsaich-export-date-to').val() || '';

            $button.prop('disabled', true).text(raplsaichAdmin.i18n.exporting || 'Exporting...');

            $.ajax({
                url: raplsaichAdmin.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'raplsaich_export_conversations',
                    nonce: raplsaichAdmin.nonce,
                    format: format,
                    date_from: dateFrom,
                    date_to: dateTo
                },
                success: function(response) {
                    if (response.success) {
                        downloadExport(response.data);
                    } else {
                        alert((raplsaichAdmin.i18n.exportFailed || 'Export failed.') + ' ' + response.data);
                    }
                },
                error: function() {
                    alert(raplsaichAdmin.i18n.error || 'Error');
                },
                complete: function() {
                    $button.prop('disabled', false).text(raplsaichAdmin.i18n.exportConversations || 'Export');
                }
            });
        });

        // リードエクスポート
        $('#raplsaich-export-leads').on('click', function() {
            var $button = $(this);
            var format = $('#raplsaich-leads-export-format').val() || 'csv';
            var dateFrom = $('#raplsaich-leads-export-date-from').val() || '';
            var dateTo = $('#raplsaich-leads-export-date-to').val() || '';

            $button.prop('disabled', true).text(raplsaichAdmin.i18n.exporting || 'Exporting...');

            $.ajax({
                url: raplsaichAdmin.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'raplsaich_export_leads',
                    nonce: raplsaichAdmin.nonce,
                    format: format,
                    date_from: dateFrom,
                    date_to: dateTo
                },
                success: function(response) {
                    if (response.success) {
                        downloadExport(response.data);
                    } else {
                        alert((raplsaichAdmin.i18n.exportFailed || 'Export failed.') + ' ' + response.data);
                    }
                },
                error: function() {
                    alert(raplsaichAdmin.i18n.error || 'Error');
                },
                complete: function() {
                    $button.prop('disabled', false).text(raplsaichAdmin.i18n.exportConversations || 'Export');
                }
            });
        });

        // クイックリプライ動的管理
        var $quickRepliesContainer = $('#raplsaich-quick-replies');
        var quickReplyIndex = $quickRepliesContainer.find('.raplsaich-quick-reply-item').length;

        $('#raplsaich-add-quick-reply').on('click', function() {
            var html = '<div class="raplsaich-quick-reply-item" style="margin-bottom: 8px; display: flex; gap: 8px;">' +
                '<input type="text" name="raplsaich_settings[extensions][quick_replies][' + quickReplyIndex + '][text]" ' +
                'class="regular-text" placeholder="' + (raplsaichAdmin.i18n.quickReplyPlaceholder || 'e.g., What are your business hours?') + '">' +
                '<button type="button" class="button raplsaich-remove-quick-reply">×</button>' +
                '</div>';
            $quickRepliesContainer.append(html);
            quickReplyIndex++;
        });

        $quickRepliesContainer.on('click', '.raplsaich-remove-quick-reply', function() {
            $(this).closest('.raplsaich-quick-reply-item').remove();
        });

        // 休日動的管理
        var $holidaysContainer = $('#raplsaich-holidays');
        var holidayIndex = $holidaysContainer.find('.raplsaich-holiday-item').length;

        $('#raplsaich-add-holiday').on('click', function() {
            var html = '<div class="raplsaich-holiday-item" style="margin-bottom: 8px; display: flex; gap: 8px;">' +
                '<input type="date" name="raplsaich_settings[extensions][holidays][' + holidayIndex + '][date]">' +
                '<input type="text" name="raplsaich_settings[extensions][holidays][' + holidayIndex + '][name]" ' +
                'class="regular-text" placeholder="' + (raplsaichAdmin.i18n.holidayNamePlaceholder || 'Holiday name (optional)') + '">' +
                '<button type="button" class="button raplsaich-remove-holiday">×</button>' +
                '</div>';
            $holidaysContainer.append(html);
            holidayIndex++;
        });

        $holidaysContainer.on('click', '.raplsaich-remove-holiday', function() {
            $(this).closest('.raplsaich-holiday-item').remove();
        });

        // プロンプトテンプレート動的管理
        var $templatesContainer = $('#raplsaich-prompt-templates');
        var templateIndex = $templatesContainer.find('.raplsaich-prompt-template-item').length;

        $('#raplsaich-add-template').on('click', function() {
            var html = '<div class="raplsaich-prompt-template-item" style="margin-bottom: 15px; padding: 15px; border: 1px solid #ddd; border-radius: 4px;">' +
                '<div style="margin-bottom: 10px;">' +
                '<input type="text" name="raplsaich_settings[extensions][prompt_templates][' + templateIndex + '][name]" ' +
                'placeholder="' + (raplsaichAdmin.i18n.templateNamePlaceholder || 'Template name') + '" class="regular-text">' +
                '<button type="button" class="button raplsaich-remove-template">×</button>' +
                '</div>' +
                '<textarea name="raplsaich_settings[extensions][prompt_templates][' + templateIndex + '][prompt]" ' +
                'rows="3" class="large-text" placeholder="' + (raplsaichAdmin.i18n.templatePromptPlaceholder || 'System prompt for this template...') + '"></textarea>' +
                '</div>';
            $templatesContainer.append(html);
            templateIndex++;
        });

        $templatesContainer.on('click', '.raplsaich-remove-template', function() {
            $(this).closest('.raplsaich-prompt-template-item').remove();
        });

        // Pro機能セクションの有効/無効切り替え
        $('.raplsaich-pro-toggle').on('change', function() {
            var $section = $(this).closest('.raplsaich-pro-section');
            var $content = $section.find('.raplsaich-pro-section-content');
            if ($(this).is(':checked')) {
                $content.slideDown();
            } else {
                $content.slideUp();
            }
        });

        // 初期状態で無効なセクションを閉じる
        $('.raplsaich-pro-toggle:not(:checked)').each(function() {
            $(this).closest('.raplsaich-pro-section').find('.raplsaich-pro-section-content').hide();
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
        $('.raplsaich-theme-option').on('click', function(e) {
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
            $('.raplsaich-theme-option').removeClass('selected');

            // クリックしたオプションにselectedを追加
            $option.addClass('selected');

            // テーマに対応するプライマリカラーを設定
            var themeValue = $radio.val();
            if (themeColors[themeValue]) {
                var $colorField = $('#raplsaich_primary_color');
                if ($colorField.length && $.fn.wpColorPicker) {
                    $colorField.wpColorPicker('color', themeColors[themeValue]);
                } else {
                    $colorField.val(themeColors[themeValue]);
                }
            }

            // ダーク系テーマの場合はダークモードをオンに、それ以外はオフに
            var darkThemes = ['dark', 'modern', 'neon'];
            if (darkThemes.indexOf(themeValue) !== -1) {
                $('input[name="raplsaich_settings[dark_mode]"]').prop('checked', true);
            } else {
                $('input[name="raplsaich_settings[dark_mode]"]').prop('checked', false);
            }
        });

        // ============================================
        // タブリセットボタン
        // ============================================
        $('.raplsaich-reset-tab-btn').on('click', function() {
            var tabId = $(this).data('tab');
            var i18n = raplsaichAdmin.i18n || {};
            var defaults = raplsaichAdmin.defaults || {};

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

                // raplsaich_settings[field_name] 形式から field_name を抽出
                var match = name.match(/raplsaich_settings\[([^\]]+)\]/);
                if (!match) return;

                var fieldName = match[1];
                var defaultValue = defaults[fieldName];

                // extensions の場合
                var proMatch = name.match(/raplsaich_settings\[extensions\]\[([^\]]+)\]/);
                if (proMatch && defaults.extensions) {
                    fieldName = proMatch[1];
                    defaultValue = defaults.extensions[fieldName];
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
                $('.raplsaich-theme-option').removeClass('selected');
                $('input[name="raplsaich_settings[widget_theme]"][value="' + defaultTheme + '"]')
                    .prop('checked', true)
                    .closest('.raplsaich-theme-option').addClass('selected');
            }
        }

        // ============================================
        // フィールドリセットボタン（新形式）
        // ============================================
        $('.raplsaich-field-reset').on('click', function() {
            var $btn = $(this);
            var fieldName = $btn.data('field');
            var i18n = raplsaichAdmin.i18n || {};
            var defaults = raplsaichAdmin.defaults || {};

            if (!confirm(i18n.confirmResetField || 'Reset this field to default?')) {
                return;
            }

            var defaultValue;

            // extensions かどうかをチェック
            if (fieldName.indexOf('extensions.') === 0) {
                var proFieldName = fieldName.replace('extensions.', '');
                defaultValue = defaults.extensions ? defaults.extensions[proFieldName] : undefined;
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
        $('.raplsaich-reset-field').on('click', function() {
            var $btn = $(this);
            var targetId = $btn.data('target');
            var defaultValue = $btn.data('default');

            if (targetId && defaultValue !== undefined) {
                var $field = $('#' + targetId);
                if ($field.hasClass('raplsaich-color-field') && $.fn.wpColorPicker) {
                    $field.wpColorPicker('color', defaultValue);
                } else {
                    $field.val(defaultValue).trigger('change');
                }
            }
        });

        // Initialize WordPress color picker
        if ($.fn.wpColorPicker) {
            $('.raplsaich-color-field').wpColorPicker();
        }

    });

    // Advanced section toggle (checkbox-gated, state persisted in localStorage)
    $('.raplsaich-advanced-toggle').each(function () {
        var key = 'raplsaich_adv_' + this.id;
        if ((function() { try { return localStorage.getItem(key); } catch (e) { return null; } })() === '1') {
            this.checked = true;
            $('#' + $(this).data('target')).removeClass('raplsaich-advanced-disabled');
        }
    }).on('change', function () {
        var targetId = $(this).data('target');
        var $section = $('#' + targetId);
        var key = 'raplsaich_adv_' + this.id;
        if (this.checked) {
            $section.removeClass('raplsaich-advanced-disabled');
            try { localStorage.setItem(key, '1'); } catch (e) { /* storage unavailable */ }
        } else {
            $section.addClass('raplsaich-advanced-disabled');
            try { localStorage.removeItem(key); } catch (e) { /* storage unavailable */ }
        }
    });

    // Per-language welcome messages: show/hide based on response_language
    $('#raplsaich_response_language').on('change', function() {
        $('#raplsaich-per-language-welcome').toggle($(this).val() === 'auto');
    });

    // Clear individual per-language welcome message
    $(document).on('click', '.raplsaich-reset-welcome-lang', function() {
        var targetId = $(this).data('target');
        $('#' + targetId).val('');
    });

    // Clear all per-language welcome messages
    $('#raplsaich-reset-all-welcome-langs').on('click', function() {
        $('#raplsaich-per-language-welcome textarea').val('');
    });

    // Badge position: update active state and margin labels
    $('input[name="raplsaich_settings[badge_position]"]').on('change', function() {
        var pos = $(this).val();
        var isLeft = (pos === 'bottom-left' || pos === 'top-left');
        var isTop = (pos === 'top-right' || pos === 'top-left');
        var i18n = (typeof raplsaichAdmin !== 'undefined' && raplsaichAdmin.i18n) ? raplsaichAdmin.i18n : {};
        // Update active class on grid
        $('.raplsaich-badge-pos-option').removeClass('active');
        $(this).closest('.raplsaich-badge-pos-option').addClass('active');
        // Update margin labels
        $('#raplsaich_margin_h_label').text(isLeft ? (i18n.leftLabel || 'Left:') : (i18n.rightLabel || 'Right:'));
        $('#raplsaich_margin_v_label').text(isTop ? (i18n.topLabel || 'Top:') : (i18n.bottomLabel || 'Bottom:'));
    });

})(jQuery);

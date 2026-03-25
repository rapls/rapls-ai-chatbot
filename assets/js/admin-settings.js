jQuery(document).ready(function($) {
    var i18n = raplsaichAdmin.i18n || {};
    // Export
    $('#raplsaich-export-settings').on('click', function() {
        var $button = $(this);
        var includeKnowledge = $('#raplsaich-export-include-knowledge').is(':checked');
        $button.prop('disabled', true).text(i18n.exporting || 'Exporting...');
        $.ajax({
            url: raplsaichAdmin.ajaxUrl,
            method: 'POST',
            data: {
                action: 'raplsaich_export_settings',
                nonce: raplsaichAdmin.nonce,
                include_knowledge: includeKnowledge ? 1 : 0
            },
            success: function(response) {
                if (response.success) {
                    // Download JSON file
                    var blob = new Blob([JSON.stringify(response.data, null, 2)], {type: 'application/json'});
                    var url = URL.createObjectURL(blob);
                    var a = document.createElement('a');
                    a.href = url;
                    a.download = 'raplsaich-settings-' + new Date().toISOString().slice(0,10) + '.json';
                    document.body.appendChild(a);
                    a.click();
                    document.body.removeChild(a);
                    URL.revokeObjectURL(url);
                } else {
                    alert((i18n.exportFailed || 'Export failed') + ': ' + response.data);
                }
            },
            error: function() {
                alert(i18n.exportFailed || 'Export failed.');
            },
            complete: function() {
                $button.prop('disabled', false).text(raplsaichAdmin.i18n.exportSettings);
            }
        });
    });
    // Import
    $('#raplsaich-import-settings').on('click', function() {
        var $button = $(this);
        var $status = $('#raplsaich-import-status');
        var fileInput = $('#raplsaich-import-file')[0];
        if (!fileInput.files.length) {
            $status.html('<span style="color: red;"></span>').find('span').text(i18n.selectFile || 'Please select a file.');
            return;
        }
        var file = fileInput.files[0];
        if (!file.name.endsWith('.json')) {
            $status.html('<span style="color: red;"></span>').find('span').text(i18n.invalidJson || 'Please select a JSON file.');
            return;
        }
        if (!confirm(i18n.confirmOverwrite || 'Current settings will be overwritten. Continue?')) {
            return;
        }
        $button.prop('disabled', true).text(i18n.importing || 'Importing...');
        $status.text('');
        var reader = new FileReader();
        reader.onload = function(e) {
            try {
                var importData = JSON.parse(e.target.result);
                $.ajax({
                    url: raplsaichAdmin.ajaxUrl,
                    method: 'POST',
                    data: {
                        action: 'raplsaich_import_settings',
                        nonce: raplsaichAdmin.nonce,
                        import_data: JSON.stringify(importData)
                    },
                    success: function(response) {
                        if (response.success) {
                            $status.html('<span style="color: green;"></span>').find('span').text(response.data);
                            setTimeout(function() {
                                location.reload();
                            }, 1500);
                        } else {
                            $status.html('<span style="color: red;"></span>').find('span').text(response.data);
                        }
                    },
                    error: function() {
                        $status.html('<span style="color: red;"></span>').find('span').text(i18n.importFailed || 'Import failed.');
                    },
                    complete: function() {
                        $button.prop('disabled', false).text(raplsaichAdmin.i18n.importSettings);
                    }
                });
            } catch (err) {
                $status.html('<span style="color: red;"></span>').find('span').text(i18n.invalidJson || 'Invalid JSON file.');
                $button.prop('disabled', false).text(raplsaichAdmin.i18n.importSettings);
            }
        };
        reader.readAsText(file);
    });
    // Reset settings
    $('#raplsaich-reset-settings').on('click', function() {
        var $button = $(this);
        var $status = $('#raplsaich-reset-status');
        var input = prompt(i18n.resetConfirm || 'All settings will be reset. API keys will also be deleted.\n\nThis action cannot be undone.\n\nTo reset, type "reset":');
        if (input !== 'reset') {
            if (input !== null) {
                $status.html('<span style="color: red;"></span>').find('span').text(i18n.resetTypeError || 'Please type "reset".');
            }
            return;
        }
        $button.prop('disabled', true).text(i18n.resetting || 'Resetting...');
        $status.text('');
        raplsaichDestructiveAjax({
            data: {
                action: 'raplsaich_reset_settings',
                nonce: raplsaichAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    $status.html('<span style="color: green;"></span>').find('span').text(response.data);
                    setTimeout(function() {
                        location.reload();
                    }, 1500);
                } else {
                    $status.html('<span style="color: red;"></span>').find('span').text(response.data);
                }
                $button.prop('disabled', false).text(raplsaichAdmin.i18n.resetSettings);
            },
            fail: function() {
                $status.html('<span style="color: red;"></span>').find('span').text(i18n.resetFailed || 'Reset failed.');
                $button.prop('disabled', false).text(raplsaichAdmin.i18n.resetSettings);
            },
            cancel: function() {
                $button.prop('disabled', false).text(raplsaichAdmin.i18n.resetSettings);
            }
        });
    });
    // Page exclusion - Add page
    $('#raplsaich-add-excluded-page').on('click', function() {
        var $select = $('#raplsaich-page-selector');
        var pageId = $select.val();
        var pageTitle = $select.find('option:selected').text();
        if (!pageId) {
            return;
        }
        // Create tag item (use DOM construction to prevent XSS from page titles)
        var $item = $('<div class="raplsaich-excluded-page-item" style="display: inline-flex; align-items: center; background: #f0f0f1; border-radius: 4px; padding: 5px 10px; margin: 3px 5px 3px 0;"></div>')
            .attr('data-page-id', pageId);
        $item.append($('<span></span>').text(pageTitle));
        $item.append($('<input type="hidden" name="raplsaich_settings[excluded_pages][]">').val(pageId));
        $item.append('<button type="button" class="raplsaich-remove-excluded-page" style="background: none; border: none; cursor: pointer; color: #a00; margin-left: 8px; font-size: 16px;">&times;</button>');
        $('#raplsaich-excluded-pages-list').append($item);
        // Remove from select
        $select.find('option[value="' + pageId + '"]').remove();
        $select.val('');
    });
    // Page exclusion - Remove page
    $(document).on('click', '.raplsaich-remove-excluded-page', function() {
        var $item = $(this).closest('.raplsaich-excluded-page-item');
        var pageId = $item.data('page-id');
        var pageTitle = $item.find('span').text();
        // Add back to select (use DOM construction to prevent XSS)
        $('#raplsaich-page-selector').append($('<option></option>').val(pageId).text(pageTitle));
        // Remove item
        $item.remove();
    });
    // Reset field to default
    $(document).on('click', '.raplsaich-reset-field', function() {
        var targetId = $(this).data('target');
        var defaultValue = $(this).data('default');
        var $target = $('#' + targetId);
        if ($target.length) {
            $target.val(defaultValue);
            // Flash effect to indicate change
            $target.css('background-color', '#fff9c4');
            setTimeout(function() {
                $target.css('background-color', '');
            }, 500);
        }
    });
    // Avatar image uploader
    var avatarFrame;
    $('#raplsaich-upload-avatar').on('click', function(e) {
        e.preventDefault();
        if (avatarFrame) {
            avatarFrame.open();
            return;
        }
        avatarFrame = wp.media({
            title: raplsaichAdmin.i18n.selectAvatar+'',
            button: {
                text: raplsaichAdmin.i18n.useAsAvatar+''
            },
            multiple: false,
            library: {
                type: 'image'
            }
        });
        avatarFrame.on('select', function() {
            var attachment = avatarFrame.state().get('selection').first().toJSON();
            var imageUrl = attachment.sizes && attachment.sizes.thumbnail ? attachment.sizes.thumbnail.url : attachment.url;
            $('#raplsaich_bot_avatar').val(imageUrl);
            updateAvatarPreview(imageUrl);
        });
        avatarFrame.open();
    });
    // Reset avatar to emoji
    $('#raplsaich-reset-avatar').on('click', function() {
        $('#raplsaich_bot_avatar').val('🤖');
        updateAvatarPreview('🤖');
    });
    // Update avatar preview
    function updateAvatarPreview(value) {
        var $preview = $('.raplsaich-avatar-preview');
        var isImage = /^(https?:\/\/|\/)/i.test(value) || /\.(jpg|jpeg|png|gif|svg|webp)$/i.test(value);
        if (isImage) {
            var $img = $('<img alt="Avatar" style="width: 48px; height: 48px; border-radius: 50%; object-fit: cover; border: 2px solid #ddd;">').attr('src', value);
            $preview.empty().append($img);
        } else {
            $preview.empty().append($('<span style="font-size: 48px; line-height: 1;"></span>').text(value));
        }
    }
    // Update preview on input change
    $('#raplsaich_bot_avatar').on('input', function() {
        updateAvatarPreview($(this).val());
    });
    // Multimodal vision model filter
    var multimodalEnabled = raplsaichSettingsData.multimodalEnabled || false;
    function checkMultimodalModels() {
        if (!multimodalEnabled) {
            // Reset all options when multimodal is disabled
            $('#raplsaich-openai-model, #raplsaich-claude-model, #raplsaich-gemini-model').each(function() {
                $(this).find('option').each(function() {
                    var $opt = $(this);
                    $opt.prop('disabled', false);
                    if ($opt.data('original-text')) {
                        $opt.text($opt.data('original-text'));
                    }
                });
                $(this).css('border-color', '');
                $(this).siblings('.raplsaich-vision-warning').hide();
            });
            return;
        }
        var provider = $('[name="raplsaich_settings[ai_provider]"]').val();
        var modelSelect = null;
        switch (provider) {
            case 'openai':
                modelSelect = $('#raplsaich-openai-model');
                break;
            case 'claude':
                modelSelect = $('#raplsaich-claude-model');
                break;
            case 'gemini':
                modelSelect = $('#raplsaich-gemini-model');
                break;
        }
        if (!modelSelect || !modelSelect.length) {
            return;
        }
        // First, disable all non-vision models
        var firstVisionModel = null;
        modelSelect.find('option').each(function() {
            var $opt = $(this);
            var vision = $opt.data('vision');
            var isVision = (vision === 1 || vision === '1' || vision === true);
            if (!isVision) {
                $opt.prop('disabled', true);
                if (!$opt.data('original-text')) {
                    $opt.data('original-text', $opt.text());
                }
                $opt.text($opt.data('original-text') + ' ('+raplsaichAdmin.i18n.noVisionSupport+')');
            } else {
                $opt.prop('disabled', false);
                if ($opt.data('original-text')) {
                    $opt.text($opt.data('original-text'));
                }
                if (!firstVisionModel) {
                    firstVisionModel = $opt.val();
                }
            }
        });
        // Check if currently selected model is vision-capable
        var selectedOption = modelSelect.find('option:selected');
        var selectedVision = selectedOption.data('vision');
        var isVisionModel = (selectedVision === 1 || selectedVision === '1' || selectedVision === true);
        var $warning = modelSelect.siblings('.raplsaich-vision-warning');
        if (!isVisionModel) {
            // Auto-select first vision model
            if (firstVisionModel) {
                modelSelect.val(firstVisionModel);
            }
            $warning.show();
            modelSelect.css('border-color', '#d63638');
        } else {
            $warning.hide();
            modelSelect.css('border-color', '');
        }
    }
    // Check on page load
    checkMultimodalModels();
    // Check when provider changes
    $('[name="raplsaich_settings[ai_provider]"]').on('change', function() {
        setTimeout(checkMultimodalModels, 100);
    });
    // Check when model changes
    $('#raplsaich-openai-model, #raplsaich-claude-model, #raplsaich-gemini-model').on('change', checkMultimodalModels);
    // Save active tab on form submit so it persists after the settings-updated redirect.
    // Append the tab hash to _wp_http_referer so WordPress redirects back with the hash.
    $('form').on('submit', function(e) {
        var $activeTab = $('.raplsaich-settings-tabs .nav-tab-active');
        if ($activeTab.length) {
            var tabHash = $activeTab.attr('href');
            localStorage.setItem('raplsaich_active_tab', tabHash);
            // Update _wp_http_referer to include the tab hash
            var $referer = $(this).find('input[name="_wp_http_referer"]');
            if ($referer.length) {
                var refUrl = $referer.val().replace(/#.*$/, '') + tabHash;
                $referer.val(refUrl);
            }
        }
        // Prevent form submission with non-vision model when multimodal is enabled
        if (!multimodalEnabled) return true;
        var provider = $('[name="raplsaich_settings[ai_provider]"]').val();
        var modelSelect = $('#raplsaich-' + provider + '-model');
        if (modelSelect.length) {
            var selectedOption = modelSelect.find('option:selected');
            var vision = selectedOption.data('vision');
            // Skip check for providers without vision metadata (e.g., OpenRouter)
            if (typeof vision === 'undefined') return true;
            var isVision = (vision === 1 || vision === '1' || vision === true);
            if (!isVision) {
                alert(raplsaichAdmin.i18n.multimodalVision);
                e.preventDefault();
                return false;
            }
        }
        return true;
    });
    // Embed code copy buttons
    $('.raplsaich-copy-embed').on('click', function() {
        var $btn = $(this);
        var target = document.getElementById($btn.data('target'));
        if (!target) return;
        var text = target.value;
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(function() {
                var orig = $btn.text();
                $btn.text(raplsaichAdmin.i18n.copied);
                setTimeout(function() { $btn.text(orig); }, 2000);
            });
        } else {
            target.select();
            document.execCommand('copy');
            var orig = $btn.text();
            $btn.text(raplsaichAdmin.i18n.copied);
            setTimeout(function() { $btn.text(orig); }, 2000);
        }
    });
});

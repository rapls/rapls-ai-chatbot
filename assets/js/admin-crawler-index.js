jQuery(document).ready(function($) {
    // Delete single index
    $(document).on('click', '.raplsaich-delete-index', function() {
        var $btn = $(this);
        var postId = $btn.data('post-id');
        var indexId = $btn.data('index-id');
        var $row = $btn.closest('tr');

        if (!confirm(raplsaichCrawler.confirmDelete || 'Delete?')) {
            return;
        }

        $btn.prop('disabled', true);

        var postData = {
            action: 'raplsaich_delete_index',
            nonce: raplsaichAdmin.nonce,
            post_id: postId
        };
        if (!postId && indexId) {
            postData.index_id = indexId;
        }
        $.post(ajaxurl, postData, function(response) {
            if (response.success) {
                $row.fadeOut(300, function() {
                    $(this).remove();
                    // Update count
                    var $tbody = $('#indexed-list-body');
                    if ($tbody.find('tr').length === 0) {
                        location.reload();
                    }
                });
            } else {
                alert(response.data || (raplsaichCrawler.deleteFailed||'Failed'));
                $btn.prop('disabled', false);
            }
        }).fail(function() {
            alert((raplsaichCrawler.errorOccurred||'Error'));
            $btn.prop('disabled', false);
        });
    });

    // Exclude post from indexed list
    $(document).on('click', '.raplsaich-exclude-post', function() {
        var $btn = $(this);
        var postId = $btn.data('post-id');
        var title = $btn.data('title');
        var $row = $btn.closest('tr');

        $btn.prop('disabled', true);

        $.post(ajaxurl, {
            action: 'raplsaich_crawler_exclude_post',
            nonce: raplsaichAdmin.nonce,
            post_id: postId
        }, function(response) {
            if (response.success) {
                // Add tag to exclusion list
                var tag = '<span class="raplsaich-exclude-tag" data-post-id="' + postId + '" style="display: inline-flex; align-items: center; gap: 4px; background: #f0f0f1; border: 1px solid #c3c4c7; border-radius: 3px; padding: 2px 8px; font-size: 13px;">' +
                    $('<span>').text(title).html() + ' <small>(ID:' + postId + ')</small>' +
                    ' <button type="button" class="raplsaich-include-post" data-post-id="' + postId + '" style="background: none; border: none; cursor: pointer; color: #b32d2e; font-size: 14px; padding: 0 2px;" title="'+(raplsaichCrawler.removeExcl||'Remove')+'">&times;</button>' +
                    '<input type="hidden" name="raplsaich_settings[crawler_exclude_ids][]" value="' + postId + '">' +
                    '</span>';
                $('#raplsaich-exclude-tags').append(tag);
                $row.fadeOut(300, function() {
                    $(this).remove();
                    if ($('#indexed-list-body tr').length === 0) {
                        location.reload();
                    }
                });
            } else {
                alert(response.data || (raplsaichCrawler.errorOccurred||'Error'));
                $btn.prop('disabled', false);
            }
        }).fail(function() {
            alert((raplsaichCrawler.errorOccurred||'Error'));
            $btn.prop('disabled', false);
        });
    });

    // Remove exclusion (include post)
    $(document).on('click', '.raplsaich-include-post', function() {
        var $btn = $(this);
        var postId = $btn.data('post-id');
        var $tag = $btn.closest('.raplsaich-exclude-tag');

        $btn.prop('disabled', true);

        $.post(ajaxurl, {
            action: 'raplsaich_crawler_include_post',
            nonce: raplsaichAdmin.nonce,
            post_id: postId
        }, function(response) {
            if (response.success) {
                $tag.fadeOut(200, function() { $(this).remove(); });
            } else {
                alert(response.data || (raplsaichCrawler.errorOccurred||'Error'));
                $btn.prop('disabled', false);
            }
        }).fail(function() {
            alert((raplsaichCrawler.errorOccurred||'Error'));
            $btn.prop('disabled', false);
        });
    });

    // Add exclusion by ID
    $('#raplsaich-add-exclude').on('click', function() {
        var $input = $('#raplsaich-exclude-id-input');
        var postId = parseInt($input.val(), 10);
        if (!postId || postId < 1) return;

        // Check if already excluded
        if ($('#raplsaich-exclude-tags .raplsaich-exclude-tag[data-post-id="' + postId + '"]').length) {
            $input.val('');
            return;
        }

        var $btn = $(this);
        $btn.prop('disabled', true);

        $.post(ajaxurl, {
            action: 'raplsaich_crawler_exclude_post',
            nonce: raplsaichAdmin.nonce,
            post_id: postId
        }, function(response) {
            if (response.success) {
                var tag = '<span class="raplsaich-exclude-tag" data-post-id="' + postId + '" style="display: inline-flex; align-items: center; gap: 4px; background: #f0f0f1; border: 1px solid #c3c4c7; border-radius: 3px; padding: 2px 8px; font-size: 13px;">' +
                    '#' + postId + ' <small>(ID:' + postId + ')</small>' +
                    ' <button type="button" class="raplsaich-include-post" data-post-id="' + postId + '" style="background: none; border: none; cursor: pointer; color: #b32d2e; font-size: 14px; padding: 0 2px;" title="'+(raplsaichCrawler.removeExcl||'Remove')+'">&times;</button>' +
                    '<input type="hidden" name="raplsaich_settings[crawler_exclude_ids][]" value="' + postId + '">' +
                    '</span>';
                $('#raplsaich-exclude-tags').append(tag);
                $input.val('');
                // Remove from indexed list if present
                $('#indexed-list-body tr[data-post-id="' + postId + '"]').fadeOut(300, function() { $(this).remove(); });
            } else {
                alert(response.data || (raplsaichCrawler.errorOccurred||'Error'));
            }
            $btn.prop('disabled', false);
        }).fail(function() {
            alert((raplsaichCrawler.errorOccurred||'Error'));
            $btn.prop('disabled', false);
        });
    });

    // Generate Embeddings (batch processing with loop)
    $('#raplsaich-generate-embeddings').on('click', function() {
        var $btn = $(this);
        var $status = $('#embedding-status');
        $btn.prop('disabled', true);
        $status.text((raplsaichCrawler.processing||'Processing...'));

        function processBatch(source) {
            $.post(ajaxurl, {
                action: 'raplsaich_generate_embeddings',
                nonce: raplsaichAdmin.nonce,
                source: source
            }, function(response) {
                if (response.success) {
                    $status.text(response.data.message);
                    if (response.data.remaining > 0) {
                        processBatch(source);
                    } else if (source === 'index') {
                        // After index, process knowledge
                        processBatch('knowledge');
                    } else {
                        $btn.prop('disabled', false);
                        setTimeout(function() { location.reload(); }, 1500);
                    }
                } else {
                    $status.text((raplsaichCrawler.errorLabel||'Error:')+' ' + (response.data || ''));
                    $btn.prop('disabled', false);
                }
            }).fail(function() {
                $status.text((raplsaichCrawler.errorOccurred||'Error'));
                $btn.prop('disabled', false);
            });
        }

        processBatch('index');
    });

    // Clear All Embeddings
    $('#raplsaich-clear-embeddings').on('click', function() {
        if (!confirm(raplsaichCrawler.confirmClearEmbed || 'Clear embeddings?')) {
            return;
        }
        var $btn = $(this);
        $btn.prop('disabled', true);
        $.post(ajaxurl, {
            action: 'raplsaich_clear_embeddings',
            nonce: raplsaichAdmin.nonce
        }, function(response) {
            if (response.success) {
                location.reload();
            } else {
                alert(response.data || (raplsaichCrawler.errorOccurred||'Error'));
                $btn.prop('disabled', false);
            }
        }).fail(function() {
            alert((raplsaichCrawler.errorOccurred||'Error'));
            $btn.prop('disabled', false);
        });
    });

    // Enhanced extraction toggle (saves to pro_features via AJAX)
    $('#raplsaich_enhanced_extraction').on('change', function() {
        var enabled = $(this).is(':checked') ? 1 : 0;
        $.post(ajaxurl, {
            action: 'raplsaich_save_pro_setting',
            nonce: raplsaichAdmin.nonce,
            key: 'enhanced_content_extraction',
            value: enabled
        });
    });

    // Delete all index
    $('#raplsaich-delete-all-index').on('click', function() {
        var $btn = $(this);

        $btn.prop('disabled', true).text((raplsaichCrawler.deleting||'Deleting...'));

        raplsaichDestructiveAjax({
            data: { action: 'raplsaich_delete_all_index', nonce: raplsaichAdmin.nonce },
            success: function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert(response.data || (raplsaichCrawler.deleteFailed||'Failed'));
                    $btn.prop('disabled', false).html('🗑️ '+(raplsaichCrawler.deleteAll||'Delete All'));
                }
            },
            cancel: function() {
                $btn.prop('disabled', false).html('🗑️ '+(raplsaichCrawler.deleteAll||'Delete All'));
            },
            fail: function() {
                alert((raplsaichCrawler.errorOccurred||'Error'));
                $btn.prop('disabled', false).html('🗑️ '+(raplsaichCrawler.deleteAll||'Delete All'));
            }
        });
    });
});

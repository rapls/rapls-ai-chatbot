jQuery(document).ready(function($) {
    var i18n = raplsaichAdmin.i18n || {};

    // Add text
    $('#raplsaich-add-knowledge-form').on('submit', function(e) {
        e.preventDefault();
        var $form = $(this);
        var $button = $form.find('button[type="submit"]');
        var $status = $('#add-knowledge-status');

        $button.prop('disabled', true).text(i18n.processing || raplsaichKB.adding);
        $status.text('');

        $.ajax({
            url: raplsaichAdmin.ajaxUrl,
            method: 'POST',
            data: {
                action: 'raplsaich_add_knowledge',
                nonce: raplsaichAdmin.nonce,
                title: $('#knowledge-title').val(),
                content: $('#knowledge-content').val(),
                category: $('#knowledge-category').val(),
                priority: $('#knowledge-priority').val() || 0,
                type: $('#knowledge-type').val() || 'qa'
            },
            success: function(response) {
                if (response.success) {
                    $status.html('<span style="color: green;">'+raplsaichKB.addedOk+'</span>');
                    $form[0].reset();
                    setTimeout(function() {
                        location.reload();
                    }, 1000);
                } else {
                    $status.html('<span style="color: red;"></span>').find('span').text(response.data);
                }
            },
            error: function() {
                $status.html('<span style="color: red;"></span>').find('span').text(i18n.error || raplsaichKB.errorOccurred);
            },
            complete: function() {
                $button.prop('disabled', false).text(raplsaichKB.addBtn);
            }
        });
    });

    // File import
    $('#raplsaich-import-knowledge-form').on('submit', function(e) {
        e.preventDefault();
        var $form = $(this);
        var $button = $form.find('button[type="submit"]');
        var $status = $('#import-knowledge-status');

        var fileInput = $('#knowledge-file')[0];
        if (!fileInput.files.length) {
            $status.html('<span style="color: red;">'+raplsaichKB.selectFile+'</span>');
            return;
        }

        var formData = new FormData();
        formData.append('action', 'raplsaich_import_knowledge');
        formData.append('nonce', raplsaichAdmin.nonce);
        formData.append('file', fileInput.files[0]);
        formData.append('category', $('#import-category').val());

        $button.prop('disabled', true).text(i18n.importing || raplsaichKB.importing);
        $status.text('');

        $.ajax({
            url: raplsaichAdmin.ajaxUrl,
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            timeout: 60000,
            success: function(response) {
                if (response.success) {
                    $status.html('<span style="color: green;"></span>').find('span').text(response.data.message);
                    $form[0].reset();
                    setTimeout(function() {
                        location.reload();
                    }, 1000);
                } else {
                    $status.html('<span style="color: red;"></span>').find('span').text(response.data || raplsaichKB.importFailed);
                }
            },
            error: function() {
                $status.html('<span style="color: red;"></span>').find('span').text(i18n.error || raplsaichKB.errorOccurred);
            },
            complete: function() {
                $button.prop('disabled', false).text(raplsaichKB.importBtn);
            }
        });
    });

    // Category filter (preserve sort params)
    $('#raplsaich-category-filter').on('change', function() {
        var category = $(this).val();
        var url = new URL(raplsaichKB.pageUrl+'', window.location.origin);
        if (category) {
            url.searchParams.set('category', category);
        }
        var currentParams = new URLSearchParams(window.location.search);
        if (currentParams.has('orderby')) {
            url.searchParams.set('orderby', currentParams.get('orderby'));
        }
        if (currentParams.has('order')) {
            url.searchParams.set('order', currentParams.get('order'));
        }
        window.location.href = url.toString();
    });

    // Toggle active/inactive
    $('.raplsaich-toggle-active').on('change', function() {
        var id = $(this).data('id');
        var isActive = $(this).prop('checked') ? 1 : 0;

        $.ajax({
            url: raplsaichAdmin.ajaxUrl,
            method: 'POST',
            data: {
                action: 'raplsaich_toggle_knowledge',
                nonce: raplsaichAdmin.nonce,
                id: id,
                is_active: isActive
            }
        });
    });

    // Change priority in list
    $('.raplsaich-priority-select').on('change', function() {
        var $select = $(this);
        var id = $select.data('id');
        var priority = $select.val();

        $select.prop('disabled', true);

        $.ajax({
            url: raplsaichAdmin.ajaxUrl,
            method: 'POST',
            data: {
                action: 'raplsaich_update_priority',
                nonce: raplsaichAdmin.nonce,
                id: id,
                priority: priority
            },
            success: function(response) {
                if (!response.success) {
                    alert(response.data || raplsaichKB.priorityFail);
                }
            },
            error: function() {
                alert(raplsaichKB.errorOccurred);
            },
            complete: function() {
                $select.prop('disabled', false);
            }
        });
    });

    // Open edit modal
    $('.raplsaich-edit-knowledge').on('click', function() {
        var id = $(this).data('id');

        $.ajax({
            url: raplsaichAdmin.ajaxUrl,
            method: 'POST',
            data: {
                action: 'raplsaich_get_knowledge',
                nonce: raplsaichAdmin.nonce,
                id: id
            },
            success: function(response) {
                if (response.success) {
                    var data = response.data;
                    $('#edit-knowledge-id').val(data.id);
                    $('#edit-knowledge-title').val(data.title);
                    $('#edit-knowledge-content').val(data.content);
                    $('#edit-knowledge-type').val(data.type || 'qa');
                    $('#edit-knowledge-category').val(data.category || '');
                    $('#edit-knowledge-priority').val(data.priority || 0);
                    $('#raplsaich-edit-modal').show();
                } else {
                    alert(response.data);
                }
            }
        });
    });

    // Edit form submit
    $('#raplsaich-edit-knowledge-form').on('submit', function(e) {
        e.preventDefault();
        var $form = $(this);
        var $button = $form.find('button[type="submit"]');

        $button.prop('disabled', true).text(i18n.processing || raplsaichKB.updating);

        $.ajax({
            url: raplsaichAdmin.ajaxUrl,
            method: 'POST',
            data: {
                action: 'raplsaich_update_knowledge',
                nonce: raplsaichAdmin.nonce,
                id: $('#edit-knowledge-id').val(),
                title: $('#edit-knowledge-title').val(),
                content: $('#edit-knowledge-content').val(),
                type: $('#edit-knowledge-type').val(),
                category: $('#edit-knowledge-category').val(),
                priority: $('#edit-knowledge-priority').val() || 0
            },
            success: function(response) {
                if (response.success) {
                    alert(response.data);
                    location.reload();
                } else {
                    alert(response.data);
                }
            },
            error: function() {
                alert(i18n.error || raplsaichKB.errorOccurred);
            },
            complete: function() {
                $button.prop('disabled', false).text(raplsaichKB.updateBtn);
            }
        });
    });

    // Delete
    $('.raplsaich-delete-knowledge').on('click', function() {
        if (!confirm(i18n.confirmDelete || raplsaichKB.confirmDelete)) {
            return;
        }

        var id = $(this).data('id');
        var $row = $(this).closest('tr');

        $row.css('opacity', '0.5');

        $.ajax({
            url: raplsaichAdmin.ajaxUrl,
            method: 'POST',
            data: {
                action: 'raplsaich_delete_knowledge',
                nonce: raplsaichAdmin.nonce,
                id: id
            },
            success: function(response) {
                if (response.success) {
                    $row.fadeOut(300, function() {
                        $(this).remove();
                    });
                } else {
                    alert(response.data);
                    $row.css('opacity', '1');
                }
            },
            error: function() {
                alert(i18n.error || raplsaichKB.errorOccurred);
                $row.css('opacity', '1');
            }
        });
    });

    // Close modal
    $('.raplsaich-modal-close, .raplsaich-modal').on('click', function(e) {
        if (e.target === this) {
            $('#raplsaich-edit-modal').hide();
        }
    });

    // Close modal with ESC key
    $(document).on('keydown', function(e) {
        if (e.key === 'Escape') {
            $('#raplsaich-edit-modal').hide();
        }
    });

    // Generate FAQ from gaps (Pro)
    $('#raplsaich-generate-faq').on('click', function() {
        var $btn = $(this);
        var $status = $('#raplsaich-generate-faq-status');

        if (!confirm(raplsaichKB.confirmGenerate)) {
            return;
        }

        $btn.prop('disabled', true);
        $status.text(raplsaichKB.generating);

        $.post(raplsaichAdmin.ajaxUrl, {
            action: 'raplsaich_generate_faq',
            nonce: raplsaichAdmin.nonce
        }, function(response) {
            if (response.success) {
                $status.html('<span style="color:green;"></span>').find('span').text(response.data.message);
                setTimeout(function() {
                    window.location.href = raplsaichKB.draftUrl;
                }, 1500);
            } else {
                $status.html('<span style="color:red;"></span>').find('span').text(response.data);
            }
        }).fail(function() {
            $status.html('<span style="color:red;">'+raplsaichKB.errorOccurred+'</span>');
        }).always(function() {
            $btn.prop('disabled', false);
        });
    });

    // Approve draft (Pro)
    $(document).on('click', '.raplsaich-approve-draft', function() {
        var id = $(this).data('id');
        var $row = $(this).closest('tr');

        $.post(raplsaichAdmin.ajaxUrl, {
            action: 'raplsaich_approve_faq_draft',
            nonce: raplsaichAdmin.nonce,
            id: id
        }, function(response) {
            if (response.success) {
                $row.find('.raplsaich-draft-badge').remove();
                $row.removeClass('raplsaich-draft-row');
                $row.find('.raplsaich-approve-draft, .raplsaich-reject-draft').remove();
                $row.find('td:last').prepend('<button type="button" class="button button-small button-link-delete raplsaich-delete-knowledge" data-id="' + id + '">'+(raplsaichKB.deleteBtn||'Delete')+'</button> ');
            } else {
                alert(response.data || (raplsaichKB.error||'Error'));
            }
        }).fail(function(xhr) {
            console.error('raplsaich_approve_faq_draft failed:', xhr.status, xhr.responseText);
            alert('AJAX error: ' + xhr.status);
        });
    });

    // Reject draft (Pro)
    $(document).on('click', '.raplsaich-reject-draft', function() {
        if (!confirm((raplsaichKB.confirmReject||'Reject?')) {
            return;
        }

        var id = $(this).data('id');
        var $row = $(this).closest('tr');

        $row.css('opacity', '0.5');

        $.post(raplsaichAdmin.ajaxUrl, {
            action: 'raplsaich_reject_faq_draft',
            nonce: raplsaichAdmin.nonce,
            id: id
        }, function(response) {
            if (response.success) {
                $row.fadeOut(300, function() { $(this).remove(); });
            } else {
                alert(response.data || (raplsaichKB.error||'Error'));
                $row.css('opacity', '1');
            }
        }).fail(function(xhr) {
            console.error('raplsaich_reject_faq_draft failed:', xhr.status, xhr.responseText);
            alert('AJAX error: ' + xhr.status);
            $row.css('opacity', '1');
        });
    });

    // Knowledge export (Pro) — streaming file download (avoids JSON memory issues)
    $('.raplsaich-export-knowledge').on('click', function(e) {
        e.preventDefault();
        var format = $(this).data('format');
        var $btn = $(this);
        var $status = $('.raplsaich-export-knowledge-status');
        var category = $('#raplsaich-category-filter').val() || '';

        $btn.prop('disabled', true);
        $status.text((raplsaichKB.exporting||'Exporting...'));

        // Direct download via GET — server streams the file (no JSON payload in memory)
        var url = raplsaichAdmin.ajaxUrl
            + '?action=raplsaich_download_knowledge'
            + '&nonce=' + encodeURIComponent(raplsaichAdmin.nonce)
            + '&format=' + encodeURIComponent(format)
            + '&category=' + encodeURIComponent(category);
        window.location.href = url;

        // Re-enable after delay (browser handles the download)
        setTimeout(function() {
            $btn.prop('disabled', false);
            $status.html('<span style="color:green;">✓</span>');
            setTimeout(function() { $status.text(''); }, 3000);
        }, 1500);
    });

    // ── Version History (Pro) ──────────────────────────────────────────
    if (raplsaichKB.isPro) {

    // Line-based diff (WordPress revision style: red=removed, green=added)
    function raplsaichLineDiff(oldText, newText) {
        if (oldText === newText) {
            return '<span style="color:#50575e;">'+(raplsaichKB.noChanges||'No changes.')+'</span>';
        }
        var esc = function(s) { return $('<span>').text(s).html(); };
        var oldLines = oldText.split('\n');
        var newLines = newText.split('\n');

        // LCS (Longest Common Subsequence) to find matching lines
        var m = oldLines.length, n = newLines.length;
        var dp = [];
        for (var i = 0; i <= m; i++) {
            dp[i] = [];
            for (var j = 0; j <= n; j++) {
                if (i === 0 || j === 0) { dp[i][j] = 0; }
                else if (oldLines[i - 1] === newLines[j - 1]) { dp[i][j] = dp[i - 1][j - 1] + 1; }
                else { dp[i][j] = Math.max(dp[i - 1][j], dp[i][j - 1]); }
            }
        }

        // Backtrack to build diff operations
        var ops = [];
        i = m; var j = n;
        while (i > 0 || j > 0) {
            if (i > 0 && j > 0 && oldLines[i - 1] === newLines[j - 1]) {
                ops.push({ type: 'equal', line: oldLines[i - 1] });
                i--; j--;
            } else if (j > 0 && (i === 0 || dp[i][j - 1] >= dp[i - 1][j])) {
                ops.push({ type: 'add', line: newLines[j - 1] });
                j--;
            } else {
                ops.push({ type: 'del', line: oldLines[i - 1] });
                i--;
            }
        }
        ops.reverse();

        // Context diff: show only changed lines with up to 3 lines of surrounding context
        var ctx = 3;
        var show = [];
        for (i = 0; i < ops.length; i++) { show[i] = (ops[i].type !== 'equal'); }
        for (i = 0; i < ops.length; i++) {
            if (ops[i].type !== 'equal') { continue; }
            for (var k = Math.max(0, i - ctx); k <= Math.min(ops.length - 1, i + ctx); k++) {
                if (ops[k].type !== 'equal') { show[i] = true; break; }
            }
        }

        var html = '';
        var inGap = false;
        for (i = 0; i < ops.length; i++) {
            if (!show[i]) {
                if (!inGap) {
                    html += '<div style="padding:1px 6px;color:#999;text-align:center;font-size:12px;">⋯</div>';
                    inGap = true;
                }
                continue;
            }
            inGap = false;
            var o = ops[i];
            if (o.type === 'equal') {
                html += '<div style="padding:1px 6px;color:#50575e;">&nbsp; ' + esc(o.line) + '</div>';
            } else if (o.type === 'del') {
                html += '<div style="padding:1px 6px;background:#fcdddd;"><del style="text-decoration:none;">− ' + esc(o.line) + '</del></div>';
            } else {
                html += '<div style="padding:1px 6px;background:#d4fcd5;"><ins style="text-decoration:none;">+ ' + esc(o.line) + '</ins></div>';
            }
        }
        return html;
    }

    var _versionCache = [];

    $('#raplsaich-show-versions').on('click', function() {
        var $panel = $('#raplsaich-versions-panel');
        var $list = $('#raplsaich-versions-list');
        var knowledgeId = $('#edit-knowledge-id').val();

        if ($panel.is(':visible')) {
            $panel.slideUp(200);
            return;
        }

        $list.html('<p><span class="spinner is-active" style="float:none; margin:0 8px 0 0;"></span>'+(raplsaichKB.loading||'Loading...')+'</p>');
        $('#raplsaich-diff-panel').hide();
        $panel.slideDown(200);

        $.post(raplsaichAdmin.ajaxUrl, {
            action: 'raplsaich_get_knowledge_versions',
            nonce: raplsaichAdmin.nonce,
            knowledge_id: knowledgeId
        }, function(r) {
            if (!r.success || !r.data.versions || r.data.versions.length === 0) {
                $list.html('<p class="description">'+(raplsaichKB.noHistory||'No history.')+'</p>');
                return;
            }
            _versionCache = r.data.versions;
            var html = '<table class="widefat striped" style="max-width: 100%;"><thead><tr>'
                + '<th style="width:50px;">#</th>'
                + '<th style="width:120px;">'+(raplsaichKB.author||'Author')+'</th>'
                + '<th style="width:150px;">'+(raplsaichKB.date||'Date')+'</th>'
                + '<th style="width:180px;">'+(raplsaichKB.actions||'Actions')+'</th>'
                + '</tr></thead><tbody>';

            _versionCache.forEach(function(v, idx) {
                html += '<tr>'
                    + '<td>v' + v.version_number + '</td>'
                    + '<td>' + $('<span>').text(v.created_by_name).html() + '</td>'
                    + '<td>' + $('<span>').text(v.created_at).html() + '</td>'
                    + '<td>'
                    + '<button type="button" class="button button-small raplsaich-preview-version" data-idx="' + idx + '">'+(raplsaichKB.diff||'Diff')+'</button> '
                    + '<button type="button" class="button button-small raplsaich-restore-version" data-id="' + v.id + '" data-version="' + v.version_number + '">↩ '+(raplsaichKB.restore||'Restore')+'</button>'
                    + '</td></tr>';
            });
            html += '</tbody></table>';
            $list.html(html);
        }).fail(function() {
            $list.html('<p class="description" style="color:#d63638;">'+(raplsaichKB.historyFail||'Failed.')+'</p>');
        });
    });

    // Diff preview — compare version content with current form content
    $(document).on('click', '.raplsaich-preview-version', function() {
        var idx = $(this).data('idx');
        var v = _versionCache[idx];
        if (!v) { return; }

        var currentContent = $('#edit-knowledge-content').val();
        var oldContent = v.content;

        $('#raplsaich-diff-title').text('v' + v.version_number + ' → '+(raplsaichKB.current||'Current'));

        var diffHtml = '';
        // Title diff
        var currentTitle = $('#edit-knowledge-title').val();
        if (v.title !== currentTitle) {
            diffHtml += '<div style="margin-bottom:8px;"><strong>'+(raplsaichKB.title||'Title')+':</strong><br>' + raplsaichLineDiff(v.title, currentTitle) + '</div>';
        }
        // Content diff
        diffHtml += '<div><strong>'+(raplsaichKB.content||'Content')+':</strong><br>' + raplsaichLineDiff(oldContent, currentContent) + '</div>';
        // Category diff
        var currentCat = $('#edit-knowledge-category').val();
        if (v.category !== currentCat) {
            diffHtml += '<div style="margin-top:8px;"><strong>'+(raplsaichKB.category||'Category')+':</strong> ' + raplsaichLineDiff(v.category || '(none)', currentCat || '(none)') + '</div>';
        }

        $('#raplsaich-diff-content').html(diffHtml);
        $('#raplsaich-diff-panel').show();
        // Auto-scroll modal body to show diff panel
        var $modalBody = $('#raplsaich-diff-panel').closest('.raplsaich-modal-body');
        if ($modalBody.length) {
            $modalBody.animate({ scrollTop: $modalBody[0].scrollHeight }, 300);
        }
    });

    $('#raplsaich-diff-close').on('click', function() {
        $('#raplsaich-diff-panel').slideUp(200);
    });

    // Restore version — save to DB
    $(document).on('click', '.raplsaich-restore-version', function() {
        var $btn = $(this);
        var versionNum = $btn.data('version');
        if (!confirm((raplsaichKB.restoreVersion||'Restore to version')+' v' + versionNum + '?')) {
            return;
        }
        $btn.prop('disabled', true);
        $.post(raplsaichAdmin.ajaxUrl, {
            action: 'raplsaich_restore_knowledge_version',
            nonce: raplsaichAdmin.nonce,
            version_id: $btn.data('id')
        }, function(r) {
            if (r.success) {
                // Reload the edit form with restored data
                var knowledgeId = $('#edit-knowledge-id').val();
                $.post(raplsaichAdmin.ajaxUrl, {
                    action: 'raplsaich_get_knowledge',
                    nonce: raplsaichAdmin.nonce,
                    id: knowledgeId
                }, function(kr) {
                    if (kr.success) {
                        $('#edit-knowledge-title').val(kr.data.title);
                        $('#edit-knowledge-content').val(kr.data.content);
                        $('#edit-knowledge-category').val(kr.data.category || '');
                        $('#edit-knowledge-priority').val(kr.data.priority || 0);
                    }
                });
                $('#raplsaich-versions-panel').slideUp(200);
                alert((raplsaichKB.restored||'Restored.'));
                // Reload page to reflect changes in the table
                location.reload();
            } else {
                alert(r.data || (raplsaichKB.restoreFail||'Failed.'));
                $btn.prop('disabled', false);
            }
        }).fail(function() {
            alert((raplsaichKB.ajaxError||'AJAX error.'));
            $btn.prop('disabled', false);
        });
    });

    // Hide versions panel when modal closes
    $('.raplsaich-modal-close').on('click', function() {
        $('#raplsaich-versions-panel').hide();
    });
    }

});

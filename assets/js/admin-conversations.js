<script>
jQuery(document).ready(function($) {
    var i18n = raplsaichAdmin.i18n || {};
    var _operatorPollTimer = null;
    var _currentConvId = null;
    var _lastMessageId = 0;
    var _isPro = !!(raplsaichAdmin.isPro);
    var _apiBase = (raplsaichAdmin.restUrl || '/wp-json/') + 'rapls-ai-chatbot/v1';

    // Build a single message DOM element
    function buildMessageEl(msg, allMessages) {
        var roleLabel;
        var roleClass;
        if (msg.role === 'user') {
            roleLabel = (raplsaichConv.user||'User');
            roleClass = 'user';
        } else if (msg.role === 'operator') {
            roleLabel = (raplsaichConv.operator||'Operator');
            roleClass = 'operator';
        } else {
            roleLabel = (raplsaichConv.ai||'AI');
            roleClass = 'assistant';
        }

        var wrap = document.createElement('div');
        wrap.className = 'raplsaich-message message-' + roleClass;
        if (msg.id) wrap.dataset.messageId = msg.id;

        var header = document.createElement('div');
        var strong = document.createElement('strong');
        strong.textContent = roleLabel;
        header.appendChild(strong);

        if (msg.role === 'operator') {
            var roleBadge = document.createElement('span');
            roleBadge.className = 'raplsaich-operator-role-badge';
            roleBadge.textContent = (raplsaichConv.operator||'Operator');
            header.appendChild(roleBadge);
        }

        header.appendChild(document.createTextNode(' '));
        var small = document.createElement('small');
        small.textContent = '(' + msg.created_at + ')';
        header.appendChild(small);

        // Feedback badge
        if (msg.role === 'assistant' && typeof msg.feedback !== 'undefined') {
            var badge = document.createElement('span');
            if (msg.feedback === 1) {
                badge.className = 'raplsaich-feedback-badge raplsaich-feedback-positive';
                badge.title = (raplsaichConv.positiveFeedback||'Positive feedback');
                badge.textContent = '\uD83D\uDC4D';
                header.appendChild(document.createTextNode(' '));
                header.appendChild(badge);
            } else if (msg.feedback === -1) {
                badge.className = 'raplsaich-feedback-badge raplsaich-feedback-negative';
                badge.title = (raplsaichConv.negativeFeedback||'Negative feedback');
                badge.textContent = '\uD83D\uDC4E';
                header.appendChild(document.createTextNode(' '));
                header.appendChild(badge);
            }
        }

        // Extract [image:URL] markers
        var textContent = msg.content || '';
        var imageMatch = textContent.match(/\[image:(https?:\/\/[^\]]+)\]/);
        if (imageMatch) {
            textContent = textContent.replace(/\n?\[image:[^\]]+\]/, '').trim();
        }

        var p = document.createElement('p');
        p.style.whiteSpace = 'pre-wrap';
        p.textContent = textContent;

        wrap.appendChild(header);
        if (imageMatch) {
            var imgWrap = document.createElement('div');
            imgWrap.style.cssText = 'margin: 6px 0;';
            var img = document.createElement('img');
            img.src = imageMatch[1];
            img.alt = (raplsaichConv.screenshot||'Screenshot');
            img.style.cssText = 'max-width: 300px; max-height: 200px; border-radius: 6px; border: 1px solid #ddd; cursor: pointer;';
            img.addEventListener('click', function() { window.open(this.src, '_blank'); });
            imgWrap.appendChild(img);
            wrap.appendChild(imgWrap);
        }
        wrap.appendChild(p);

        // Suggest Improvement (Pro)
        if (msg.role === 'assistant' && _isPro && allMessages) {
            var suggestBtn = document.createElement('button');
            suggestBtn.type = 'button';
            suggestBtn.className = 'button button-small raplsaich-suggest-edit';
            suggestBtn.textContent = (raplsaichConv.suggestImprovement||'Suggest Improvement');
            suggestBtn.style.cssText = 'margin-top: 6px; font-size: 11px;';
            suggestBtn.dataset.content = msg.content;
            var idx = allMessages.indexOf(msg);
            suggestBtn.dataset.userMsg = (idx > 0 ? allMessages[idx - 1].content : '') || '';
            wrap.appendChild(suggestBtn);
        }

        // AI metadata badges
        if (msg.role === 'assistant' && (msg.ai_model || msg.tokens || msg.cache_hit)) {
            var metaDiv = document.createElement('div');
            metaDiv.className = 'raplsaich-msg-meta';
            if (msg.ai_model) {
                var modelBadge = document.createElement('span');
                modelBadge.className = 'raplsaich-msg-meta-badge';
                modelBadge.textContent = msg.ai_model;
                metaDiv.appendChild(modelBadge);
            }
            if (msg.tokens) {
                var tokenBadge = document.createElement('span');
                tokenBadge.className = 'raplsaich-msg-meta-badge';
                tokenBadge.textContent = msg.tokens.toLocaleString() + ' '+(raplsaichConv.tokens||'tokens');
                metaDiv.appendChild(tokenBadge);
            }
            if (msg.cache_hit) {
                var cacheBadge = document.createElement('span');
                cacheBadge.className = 'raplsaich-msg-meta-badge cached';
                cacheBadge.textContent = '\u26A1 '+(raplsaichConv.cached||'cached');
                metaDiv.appendChild(cacheBadge);
            }
            wrap.appendChild(metaDiv);
        }

        return wrap;
    }

    // Update operator UI state based on handoff status
    function updateOperatorUI(handoffStatus) {
        var $start = $('#raplsaich-operator-start');
        var $end = $('#raplsaich-operator-end');
        var $form = $('#raplsaich-operator-reply-form');
        var $label = $('#raplsaich-handoff-status-label');

        if (!_isPro) {
            $start.hide(); $end.hide(); $form.hide(); $label.hide();
            return;
        }

        if (handoffStatus === 'pending' || handoffStatus === 'active') {
            $start.hide();
            $end.show();
            $form.show();
            $label.show().text(handoffStatus === 'pending'
                ? (raplsaichConv.pending||'Pending')
                : (raplsaichConv.operatorActive||'Operator Active')
            ).attr('class', 'raplsaich-handoff-badge ' + (handoffStatus === 'pending' ? 'raplsaich-handoff-badge--pending' : 'raplsaich-handoff-badge--active'));
            startOperatorPolling();
        } else {
            $start.show();
            $end.hide();
            $form.hide();
            $label.hide();
            stopOperatorPolling();
        }
    }

    // Polling for new messages
    function startOperatorPolling() {
        stopOperatorPolling();
        _operatorPollTimer = setInterval(function() {
            if (!_currentConvId) return;
            $.ajax({
                url: raplsaichAdmin.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'raplsaich_get_conversation_messages',
                    nonce: raplsaichAdmin.nonce,
                    conversation_id: _currentConvId
                },
                success: function(response) {
                    if (!response.success) return;
                    var data = response.data;
                    var messages = data.messages || data;
                    if (!messages || !messages.length) return;

                    // Find new messages
                    var container = document.getElementById('raplsaich-conversation-messages');
                    var lastRendered = _lastMessageId;
                    messages.forEach(function(msg) {
                        if (msg.id && msg.id > lastRendered) {
                            container.appendChild(buildMessageEl(msg, null));
                            _lastMessageId = msg.id;
                        }
                    });

                    // Update handoff status
                    if (data.handoff_status !== undefined) {
                        updateOperatorUI(data.handoff_status);
                    }

                    // Auto-scroll
                    var modalBody = container.closest('.raplsaich-modal-body');
                    if (modalBody) modalBody.scrollTop = modalBody.scrollHeight;
                }
            });
        }, 5000);
    }

    function stopOperatorPolling() {
        if (_operatorPollTimer) {
            clearInterval(_operatorPollTimer);
            _operatorPollTimer = null;
        }
    }

    // View conversation details
    $('.raplsaich-view-conversation').on('click', function() {
        var conversationId = $(this).data('id');
        _currentConvId = conversationId;
        _lastMessageId = 0;
        var modal = $('#raplsaich-conversation-modal');
        var messagesContainer = $('#raplsaich-conversation-messages');

        messagesContainer.empty().append($('<p></p>').text(i18n.processing || 'Loading...'));
        $('#raplsaich-operator-reply-form').hide();
        $('#raplsaich-operator-start').hide();
        $('#raplsaich-operator-end').hide();
        $('#raplsaich-handoff-status-label').hide();
        modal.show();

        $.ajax({
            url: raplsaichAdmin.ajaxUrl,
            method: 'POST',
            data: {
                action: 'raplsaich_get_conversation_messages',
                nonce: raplsaichAdmin.nonce,
                conversation_id: conversationId
            },
            success: function(response) {
                if (!response.success) {
                    messagesContainer.empty().append($('<p></p>').text(i18n.error || 'Error'));
                    return;
                }

                var data = response.data;
                // Support both old format (array) and new format ({messages, handoff_status})
                var messages = Array.isArray(data) ? data : (data.messages || []);
                var handoffStatus = data.handoff_status || null;

                if (messages.length > 0) {
                    var container = document.createElement('div');
                    messages.forEach(function(msg) {
                        container.appendChild(buildMessageEl(msg, messages));
                        if (msg.id && msg.id > _lastMessageId) _lastMessageId = msg.id;
                    });
                    messagesContainer.empty().append(container);

                    // Auto-scroll to bottom
                    var modalBody = messagesContainer.closest('.raplsaich-modal-body')[0];
                    if (modalBody) modalBody.scrollTop = modalBody.scrollHeight;
                } else {
                    messagesContainer.html('<p>'+(raplsaichConv.noMessages||'No messages.')+'</p>');
                }

                updateOperatorUI(handoffStatus);
            }
        });
    });

    // Response edit suggestion (Pro)
    $(document).on('click', '.raplsaich-suggest-edit', function() {
        var $btn = $(this);
        var content = $btn.data('content');
        var userMsg = $btn.data('userMsg') || '';
        $btn.prop('disabled', true).text((raplsaichConv.generating||'Generating...'));

        // Remove any existing suggestion
        $btn.siblings('.raplsaich-edit-suggestion').remove();

        $.ajax({
            url: raplsaichAdmin.ajaxUrl,
            method: 'POST',
            data: {
                action: 'raplsaich_suggest_response_edit',
                nonce: raplsaichAdmin.nonce,
                content: content,
                user_message: userMsg
            },
            success: function(response) {
                if (response.success && response.data.suggestions) {
                    var sugDiv = document.createElement('div');
                    sugDiv.className = 'raplsaich-edit-suggestion';
                    sugDiv.style.cssText = 'margin-top: 8px; padding: 10px; background: #fef9e7; border: 1px solid #f0c36d; border-radius: 4px; font-size: 13px; white-space: pre-wrap;';
                    sugDiv.textContent = response.data.suggestions;
                    $btn.after(sugDiv);
                } else {
                    alert(response.data || (raplsaichConv.failedToGenerateSuggestions||'Failed to generate suggestions.'));
                }
            },
            error: function() {
                alert((raplsaichConv.requestFailed||'Request failed.'));
            },
            complete: function() {
                $btn.prop('disabled', false).text((raplsaichConv.suggestImprovement||'Suggest Improvement'));
            }
        });
    });

    // Close modal
    $('.raplsaich-modal-close, .raplsaich-modal').on('click', function(e) {
        if (e.target === this) {
            $('#raplsaich-conversation-modal').hide();
            stopOperatorPolling();
            _currentConvId = null;
        }
    });

    // Operator: Start chat (set handoff to pending)
    $('#raplsaich-operator-start').on('click', function() {
        if (!_currentConvId || !_isPro) return;
        var $btn = $(this);
        $btn.prop('disabled', true);
        $.ajax({
            url: _apiBase + '/handoff-action',
            method: 'POST',
            contentType: 'application/json',
            headers: { 'X-WP-Nonce': raplsaichAdmin.restNonce },
            data: JSON.stringify({ conversation_id: _currentConvId, action: 'accept' }),
            success: function(response) {
                if (response.success) {
                    updateOperatorUI('active');
                } else {
                    alert(response.error || i18n.error || 'Error');
                }
            },
            error: function() { alert((raplsaichConv.anErrorOccurred||'An error occurred.')); },
            complete: function() { $btn.prop('disabled', false); }
        });
    });

    // Operator: End chat (resolve handoff)
    $('#raplsaich-operator-end').on('click', function() {
        if (!_currentConvId || !_isPro) return;
        if (!confirm((raplsaichConv.endOperatorChatAnd||'End operator chat and return to AI mode?'))) return;
        var $btn = $(this);
        $btn.prop('disabled', true);
        $.ajax({
            url: _apiBase + '/handoff-action',
            method: 'POST',
            contentType: 'application/json',
            headers: { 'X-WP-Nonce': raplsaichAdmin.restNonce },
            data: JSON.stringify({ conversation_id: _currentConvId, action: 'resolve' }),
            success: function(response) {
                if (response.success) {
                    updateOperatorUI(null);
                } else {
                    alert(response.error || i18n.error || 'Error');
                }
            },
            error: function() { alert((raplsaichConv.anErrorOccurred||'An error occurred.')); },
            complete: function() { $btn.prop('disabled', false); }
        });
    });

    // Operator: Send reply
    $('#raplsaich-operator-send').on('click', function() { sendOperatorReply(); });
    $('#raplsaich-operator-message').on('keydown', function(e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            sendOperatorReply();
        }
    });

    function sendOperatorReply() {
        var $textarea = $('#raplsaich-operator-message');
        var message = $textarea.val().trim();
        if (!message || !_currentConvId || !_isPro) return;

        var $btn = $('#raplsaich-operator-send');
        $btn.prop('disabled', true);

        $.ajax({
            url: _apiBase + '/operator-reply',
            method: 'POST',
            contentType: 'application/json',
            headers: { 'X-WP-Nonce': raplsaichAdmin.restNonce },
            data: JSON.stringify({ conversation_id: _currentConvId, message: message }),
            success: function(response) {
                if (response.success) {
                    $textarea.val('');
                    // Add message to UI immediately
                    var container = document.getElementById('raplsaich-conversation-messages');
                    var now = new Date();
                    var msgEl = buildMessageEl({
                        id: response.data.message_id,
                        role: 'operator',
                        content: message,
                        created_at: now.getFullYear() + '/' + String(now.getMonth()+1).padStart(2,'0') + '/' + String(now.getDate()).padStart(2,'0') + ' ' + String(now.getHours()).padStart(2,'0') + ':' + String(now.getMinutes()).padStart(2,'0') + ':' + String(now.getSeconds()).padStart(2,'0')
                    }, null);
                    container.appendChild(msgEl);
                    if (response.data.message_id > _lastMessageId) {
                        _lastMessageId = response.data.message_id;
                    }
                    // Auto-scroll
                    var modalBody = container.closest('.raplsaich-modal-body');
                    if (modalBody) modalBody.scrollTop = modalBody.scrollHeight;
                    // Update status
                    updateOperatorUI('active');
                } else {
                    alert(response.error || (raplsaichConv.failedToSendMessage||'Failed to send message.'));
                }
            },
            error: function() { alert((raplsaichConv.anErrorOccurred||'An error occurred.')); },
            complete: function() { $btn.prop('disabled', false); $textarea.focus(); }
        });
    }

    // Select all checkbox
    $('#raplsaich-select-all').on('change', function() {
        $('.raplsaich-conv-checkbox').prop('checked', $(this).prop('checked'));
        updateDeleteSelectedButton();
    });

    // Individual checkbox
    $('.raplsaich-conv-checkbox').on('change', function() {
        updateDeleteSelectedButton();
        var allChecked = $('.raplsaich-conv-checkbox:not(:checked)').length === 0;
        $('#raplsaich-select-all').prop('checked', allChecked);
    });

    // Update delete selected button state
    function updateDeleteSelectedButton() {
        var checkedCount = $('.raplsaich-conv-checkbox:checked').length;
        $('#raplsaich-delete-selected').prop('disabled', checkedCount === 0);
    }

    // Restore from archive (delegated for dynamically replaced buttons)
    $(document).on('click', '.raplsaich-unarchive-conversation', function() {
        var id = $(this).data('id');
        var $btn = $(this);
        var $row = $btn.closest('tr');
        $btn.prop('disabled', true);

        $.ajax({
            url: raplsaichAdmin.ajaxUrl,
            method: 'POST',
            data: {
                action: 'raplsaich_unarchive_conversation',
                nonce: raplsaichAdmin.nonce,
                conversation_id: id
            },
            success: function(response) {
                if (response.success) {
                    $row.find('.status-badge').attr('class', 'status-badge status-closed').text((raplsaichConv.closed||'Closed'));
                    $btn.replaceWith(
                        '<button type="button" class="button button-small raplsaich-archive-conversation" data-id="' + id + '">'+(raplsaichConv.archive||'Archive')+'</button>'
                    );
                } else {
                    alert(response.data || (raplsaichConv.failedToRestore||'Failed to restore.'));
                    $btn.prop('disabled', false);
                }
            },
            error: function() {
                $btn.prop('disabled', false);
            }
        });
    });

    // Archive single (delegated for dynamically replaced buttons)
    $(document).on('click', '.raplsaich-archive-conversation', function() {
        var id = $(this).data('id');
        var $btn = $(this);
        var $row = $btn.closest('tr');
        $btn.prop('disabled', true);

        $.ajax({
            url: raplsaichAdmin.ajaxUrl,
            method: 'POST',
            data: {
                action: 'raplsaich_archive_conversation',
                nonce: raplsaichAdmin.nonce,
                conversation_id: id
            },
            success: function(response) {
                if (response.success) {
                    $row.find('.status-badge').attr('class', 'status-badge status-archived').text((raplsaichConv.archived||'Archived'));
                    $btn.replaceWith(
                        '<button type="button" class="button button-small raplsaich-unarchive-conversation" data-id="' + id + '">'+(raplsaichConv.restore||'Restore')+'</button>'
                    );
                } else {
                    alert(response.data || (raplsaichConv.failedToArchive||'Failed to archive.'));
                    $btn.prop('disabled', false);
                }
            },
            error: function() {
                $btn.prop('disabled', false);
            }
        });
    });

    // Download conversation as Markdown
    $('.raplsaich-download-conversation').on('click', function() {
        var $btn = $(this);
        var id = $btn.data('id');
        var sessionId = $btn.data('session') || id;
        $btn.prop('disabled', true);

        $.ajax({
            url: raplsaichAdmin.ajaxUrl,
            method: 'POST',
            data: {
                action: 'raplsaich_get_conversation_messages',
                nonce: raplsaichAdmin.nonce,
                conversation_id: id
            },
            success: function(response) {
                $btn.prop('disabled', false);
                if (!response.success) {
                    alert(i18n.error || 'Error');
                    return;
                }

                var data = response.data;
                var messages = Array.isArray(data) ? data : (data.messages || []);

                // Build Markdown
                var md = '# '+(raplsaichConv.conversation||'Conversation')+' #' + id + '\n\n';
                md += '**Session:** ' + sessionId + '\n\n';
                md += '---\n\n';

                messages.forEach(function(msg) {
                    var role = msg.role === 'user' ? (raplsaichConv.user||'User')
                             : msg.role === 'operator' ? (raplsaichConv.operator||'Operator')
                             : (raplsaichConv.assistant||'Assistant');
                    md += '### ' + role;
                    if (msg.created_at) {
                        md += ' (' + msg.created_at + ')';
                    }
                    md += '\n\n' + (msg.content || '') + '\n\n';
                });

                // Download as file
                var blob = new Blob([md], { type: 'text/markdown;charset=utf-8' });
                var url = URL.createObjectURL(blob);
                var a = document.createElement('a');
                a.href = url;
                a.download = 'conversation-' + id + '.md';
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                URL.revokeObjectURL(url);
            },
            error: function() {
                $btn.prop('disabled', false);
            }
        });
    });

    // Delete single
    $('.raplsaich-delete-conversation').on('click', function() {
        var id = $(this).data('id');
        if (!confirm(i18n.confirmDelete || (raplsaichConv.areYouSureYou2||'Are you sure you want to delete this conversation?'))) {
            return;
        }

        var $row = $(this).closest('tr');
        $row.css('opacity', '0.5');

        $.ajax({
            url: raplsaichAdmin.ajaxUrl,
            method: 'POST',
            data: {
                action: 'raplsaich_delete_conversation',
                nonce: raplsaichAdmin.nonce,
                conversation_id: id
            },
            success: function(response) {
                if (response.success) {
                    $row.fadeOut(300, function() {
                        $(this).remove();
                        if ($('.raplsaich-conv-checkbox').length === 0) {
                            location.reload();
                        }
                    });
                } else {
                    alert(response.data || (raplsaichConv.failedToDelete||'Failed to delete.'));
                    $row.css('opacity', '1');
                }
            },
            error: function() {
                alert(i18n.error || (raplsaichConv.anErrorOccurred||'An error occurred.'));
                $row.css('opacity', '1');
            }
        });
    });

    // Delete selected conversations
    $('#raplsaich-delete-selected').on('click', function() {
        var ids = [];
        $('.raplsaich-conv-checkbox:checked').each(function() {
            ids.push($(this).val());
        });

        if (ids.length === 0) {
            alert((raplsaichConv.pleaseSelectConversationsTo||'Please select conversations to delete.'));
            return;
        }

        if (!confirm((raplsaichConv.areYouSureYou2||'Are you sure you want to delete the selected conversations?').replace('%d', ids.length))) {
            return;
        }

        var $button = $(this);
        $button.prop('disabled', true).text(i18n.processing || (raplsaichConv.deleting||'Deleting...'));

        $.ajax({
            url: raplsaichAdmin.ajaxUrl,
            method: 'POST',
            data: {
                action: 'raplsaich_delete_conversations_bulk',
                nonce: raplsaichAdmin.nonce,
                conversation_ids: ids
            },
            success: function(response) {
                if (response.success) {
                    alert(response.data);
                    location.reload();
                } else {
                    alert(response.data || (raplsaichConv.failedToDelete||'Failed to delete.'));
                    $button.prop('disabled', false).text((raplsaichConv.deleteSelected||'Delete Selected'));
                }
            },
            error: function() {
                alert(i18n.error || (raplsaichConv.anErrorOccurred||'An error occurred.'));
                $button.prop('disabled', false).text((raplsaichConv.deleteSelected||'Delete Selected'));
            }
        });
    });

    // Delete all conversations
    $('#raplsaich-delete-all').on('click', function() {
        if (!confirm((raplsaichConv.areYouSureYou||'Are you sure you want to delete all conversation history?\nThis action cannot be undone.'))) {
            return;
        }

        var $button = $(this);
        $button.prop('disabled', true).text(i18n.processing || (raplsaichConv.deleting||'Deleting...'));

        raplsaichDestructiveAjax({
            data: { action: 'raplsaich_delete_all_conversations', nonce: raplsaichAdmin.nonce },
            success: function(response) {
                if (response.success) {
                    alert(response.data);
                    location.reload();
                } else {
                    alert(response.data || (raplsaichConv.failedToDelete||'Failed to delete.'));
                    $button.prop('disabled', false).text((raplsaichConv.deleteAll||'Delete All'));
                }
            },
            cancel: function() {
                $button.prop('disabled', false).text((raplsaichConv.deleteAll||'Delete All'));
            },
            fail: function() {
                alert(i18n.error || (raplsaichConv.anErrorOccurred||'An error occurred.'));
                $button.prop('disabled', false).text((raplsaichConv.deleteAll||'Delete All'));
            }
        });
    });

    // Reset all user sessions
    $('#raplsaich-reset-sessions').on('click', function() {
        if (!confirm((raplsaichConv.areYouSureYou2||'Are you sure you want to reset all user sessions?') + '\n\n' + (raplsaichConv.thisWillForceAll||'This will force all users to start new conversations on their next visit.') + '\n' + (raplsaichConv.existingConversationHistoryWill||'Existing conversation history will remain in the database.'))) {
            return;
        }

        var $button = $(this);
        $button.prop('disabled', true).text(i18n.processing || (raplsaichConv.processing||'Processing...'));

        $.ajax({
            url: raplsaichAdmin.ajaxUrl,
            method: 'POST',
            data: {
                action: 'raplsaich_reset_sessions',
                nonce: raplsaichAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    alert(response.data);
                    $button.prop('disabled', false).text((raplsaichConv.resetAllUserSessions||'Reset All User Sessions'));
                } else {
                    alert(response.data || (raplsaichConv.failedToResetSessions||'Failed to reset sessions.'));
                    $button.prop('disabled', false).text((raplsaichConv.resetAllUserSessions||'Reset All User Sessions'));
                }
            },
            error: function() {
                alert(i18n.error || (raplsaichConv.anErrorOccurred||'An error occurred.'));
                $button.prop('disabled', false).text((raplsaichConv.resetAllUserSessions||'Reset All User Sessions'));
            }
        });
    });

    // Handoff reset
    $(document).on('click', '.raplsaich-reset-handoff', function() {
        var $btn = $(this);
        var convId = $btn.data('id');
        if (!confirm((raplsaichConv.resetHandoffStatusFor||'Reset handoff status for this conversation?'))) {
            return;
        }
        $btn.prop('disabled', true).text('...');
        $.ajax({
            url: ajaxurl,
            method: 'POST',
            data: {
                action: 'raplsaich_reset_handoff',
                conversation_id: convId,
                nonce: raplsaichAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    var $td = $btn.closest('td');
                    $td.html('<em style="color:#999;">—</em>');
                } else {
                    alert(response.data || (raplsaichConv.failedToResetHandoff||'Failed to reset handoff.'));
                    $btn.prop('disabled', false).text((raplsaichConv.reset||'Reset'));
                }
            },
            error: function() {
                alert((raplsaichConv.anErrorOccurred||'An error occurred.'));
                $btn.prop('disabled', false).text((raplsaichConv.reset||'Reset'));
            }
        });
    });
    // Auto-open conversation modal if conversation_id is in URL (e.g., from handoff email)
    var urlParams = new URLSearchParams(window.location.search);
    var autoOpenId = parseInt(urlParams.get('conversation_id'), 10) || 0;
    if (autoOpenId) {
        var $target = $('.raplsaich-view-conversation[data-id="' + autoOpenId + '"]');
        if ($target.length) {
            $target.trigger('click');
        } else {
            // Conversation may not be on current page — open modal directly
            var modal = $('#raplsaich-conversation-modal');
            var messagesContainer = $('#raplsaich-conversation-messages');
            messagesContainer.empty().append($('<p></p>').text(i18n.processing || 'Loading...'));
            modal.show();
            $.ajax({
                url: raplsaichAdmin.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'raplsaich_get_conversation_messages',
                    nonce: raplsaichAdmin.nonce,
                    conversation_id: autoOpenId
                },
                success: function(response) {
                    if (response.success && response.data && response.data.messages) {
                        messagesContainer.empty();
                        response.data.messages.forEach(function(msg) {
                            messagesContainer.append(buildMessageEl(msg, response.data.messages));
                        });
                    } else {
                        messagesContainer.empty().append($('<p></p>').text(response.data || 'Error'));
                    }
                },
                error: function() {
                    messagesContainer.empty().append($('<p></p>').text(raplsaichAdmin.i18n.error || 'Error'));
                }
            });
        }
    }

    // Session ID: click to copy
    $(document).on('click', '.raplsaich-copy-session', function() {
        var fullId = $(this).attr('title');
        if (!fullId) return;
        var el = this;
        var copyPromise;
        if (navigator.clipboard && navigator.clipboard.writeText) {
            copyPromise = navigator.clipboard.writeText(fullId);
        } else {
            // Fallback for non-HTTPS or older browsers
            var ta = document.createElement('textarea');
            ta.value = fullId;
            ta.style.cssText = 'position:fixed;left:-9999px';
            document.body.appendChild(ta);
            ta.select();
            document.execCommand('copy');
            document.body.removeChild(ta);
            copyPromise = Promise.resolve();
        }
        copyPromise.then(function() {
            var orig = el.textContent;
            el.textContent = (raplsaichConv.copied||'Copied!');
            setTimeout(function() { el.textContent = orig; }, 1000);
        });
    });
});

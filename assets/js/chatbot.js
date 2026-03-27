/**
 * WP AI Chatbot - フロントエンドスクリプト
 */

(function() {
    'use strict';

    /**
     * WP Consent API integration.
     * Returns true if consent is granted for the given category, or if no
     * Consent API / CMP is active (backwards-compatible default).
     * When raplsaichConfig.consent_strict_mode is true, returns false
     * when no Consent API is detected (GDPR-strict sites).
     *
     * @param {string} category Consent category (e.g. 'functional', 'statistics', 'marketing').
     * @returns {boolean}
     */
    function raplsaichHasConsent(category) {
        if (typeof window.wp_has_consent === 'function') {
            return window.wp_has_consent(category);
        }
        if (typeof window.wp_get_consent === 'function') {
            return window.wp_get_consent(category) === 'allow';
        }
        // No Consent API present — respect strict mode setting.
        var config = window.raplsaichConfig || {};
        return !config.consent_strict_mode;
    }

    /**
     * Check if persistent storage (localStorage) is allowed by consent.
     * Requires functional or preferences consent.
     */
    function raplsaichStorageAllowed() {
        return raplsaichHasConsent('functional') || raplsaichHasConsent('preferences');
    }

    // localStorage wrappers — gate reads/writes on consent, allow removal always.
    // try/catch guards against Safari private mode and other environments that throw.
    function raplsaichLsGet(k) {
        if (!raplsaichStorageAllowed()) return null;
        try { return localStorage.getItem(k); } catch (e) { return null; }
    }
    function raplsaichLsSet(k, v) {
        if (!raplsaichStorageAllowed()) return;
        try { localStorage.setItem(k, v); } catch (e) { /* quota exceeded or private mode */ }
    }
    function raplsaichLsRemove(k) {
        try { localStorage.removeItem(k); } catch (e) { /* noop */ }
    }

    // sessionStorage wrappers — session data also gated on functional consent
    function raplsaichSsGet(k) {
        if (!raplsaichStorageAllowed()) return null;
        try { return sessionStorage.getItem(k); } catch (e) { return null; }
    }
    function raplsaichSsSet(k, v) {
        if (!raplsaichStorageAllowed()) return;
        try { sessionStorage.setItem(k, v); } catch (e) { /* quota exceeded or private mode */ }
    }
    function raplsaichSsRemove(k) {
        try { sessionStorage.removeItem(k); } catch (e) { /* noop */ }
    }

    const RaplsaichChatbot = {

        // DOM要素
        container: null,
        badge: null,
        window: null,
        messagesEl: null,
        inputForm: null,
        inputTextarea: null,
        typingIndicator: null,
        resizeHandle: null,
        leadFormEl: null,
        leadForm: null,

        // 状態
        sessionId: null,
        isOpen: false,
        isLoading: false,
        isInitialized: false,
        isResizing: false,
        leadConfig: null,
        leadSubmitted: false,
        historyLoaded: false,
        sessionLoading: false,
        selectedImage: null,
        selectedImageData: null,

        // 設定
        config: window.raplsaichConfig || {},

        // Pro extension hooks — registered by chatbot-pro.js
        _proHooks: {},

        /**
         * 初期化
         */
        init: function() {
            // bookmarkKey — initialized by Pro
            this.cacheElements();
            if (!this.container) return;
            this.applyBrowserLanguagePlaceholders();

            // Inline mode: shortcode-embedded chatbot
            if (this.config.inlineMode) {
                this.initInlineMode();
            }

            this.createResizeHandle();
            this.bindEvents();
            this.loadSession();
            this.loadWindowSize();
            this.listenForConsentChange();
            this.isInitialized = true;

            // Pro features initialize here (registered by chatbot-pro.js)
            this.runProHook('init');
        },

        /**
         * Apply browser-language-based placeholders for input field.
         * Overrides the server-side placeholder with a browser-locale-aware translation.
         */
        applyBrowserLanguagePlaceholders: function() {
            if (!this.inputTextarea) return;
            var lang = (navigator.language || navigator.userLanguage || 'en').toLowerCase().split('-')[0];
            var placeholders = {
                ja: 'メッセージを入力...',
                en: 'Type a message...',
                zh: '输入消息...',
                ko: '메시지를 입력하세요...',
                es: 'Escribe un mensaje...',
                fr: 'Tapez un message...',
                de: 'Nachricht eingeben...',
                pt: 'Digite uma mensagem...',
                it: 'Scrivi un messaggio...',
                ru: 'Введите сообщение...',
                ar: '...اكتب رسالة',
                th: 'พิมพ์ข้อความ...',
                vi: 'Nhập tin nhắn...'
            };
            var placeholder = placeholders[lang];
            if (placeholder) {
                this.inputTextarea.placeholder = placeholder;
                // Update config.strings so voice input reset also uses the correct placeholder
                if (this.config.strings) {
                    this.config.strings.placeholder = placeholder;
                }
            }
        },

        /**
         * インラインモード初期化
         * ショートコード埋め込み時: バッジ非表示、ウィンドウ即表示、閉じるボタン非表示
         */
        initInlineMode: function() {
            // Open immediately
            this.isOpen = true;
            this.container.dataset.state = 'open';

            if (this.badge) {
                this.badge.style.display = 'none';
            }
            if (this.window) {
                this.window.style.display = 'flex';
                this.window.setAttribute('aria-hidden', 'false');
                this.window.inert = false;
            }

            // Hide close button in inline mode, but keep it in embed mode
            if (!this.config.embedMode) {
                var closeBtn = this.container.querySelector('.chatbot-close');
                if (closeBtn) {
                    closeBtn.style.display = 'none';
                }
            }
        },

        /**
         * DOM要素をキャッシュ
         */
        cacheElements: function() {
            this.container = document.getElementById('wp-ai-chatbot');
            if (!this.container) return;

            this.badge = this.container.querySelector('.chatbot-badge');
            this.window = this.container.querySelector('.chatbot-window');
            this.messagesEl = this.container.querySelector('.chatbot-messages');
            this.inputForm = this.container.querySelector('.chatbot-input');
            this.inputTextarea = this.inputForm ? this.inputForm.querySelector('textarea') : null;
            this.typingIndicator = this.container.querySelector('.chatbot-typing');
            this.leadFormEl = this.container.querySelector('.chatbot-lead-form');
            this.leadForm = this.container.querySelector('.lead-form');

            // 画像アップロード関連
            this.imageInput = this.container.querySelector('.chatbot-image-input');
            this.imageBtn = this.container.querySelector('.chatbot-image-btn');
            this.imagePreview = this.container.querySelector('.chatbot-image-preview');
            this.imagePreviewImg = this.imagePreview ? this.imagePreview.querySelector('img') : null;
            this.imagePreviewRemove = this.container.querySelector('.image-preview-remove');

            // スクリーンショット関連
            this.screenshotBtn = this.container.querySelector('.chatbot-screenshot-btn');

        },

        /**
         * リサイズハンドルを作成
         */
        createResizeHandle: function() {
            // No resize handle in inline mode (container sized by CSS)
            if (this.config.inlineMode) {
                this.resizeHandle = null;
                return;
            }
            this.resizeHandle = document.createElement('div');
            this.resizeHandle.className = 'chatbot-resize-handle';
            this.resizeHandle.setAttribute('aria-label', (this.config.strings && this.config.strings.resize_window) || 'Resize window');
            this.window.appendChild(this.resizeHandle);
        },

        /**
         * 保存されたウィンドウサイズを読み込み
         */
        loadWindowSize: function() {
            // Inline mode: size controlled by container CSS
            if (this.config.inlineMode) return;
            var savedSize = raplsaichLsGet('raplsaich_window_size');
            if (savedSize) {
                try {
                    var size = JSON.parse(savedSize);
                    if (size.width && size.height) {
                        this.window.style.width = size.width + 'px';
                        this.window.style.height = size.height + 'px';
                    }
                } catch (e) {
                    if (this.config.debug) { console.error('Failed to load window size:', e); }
                }
            }
        },

        /**
         * ウィンドウサイズを保存
         */
        saveWindowSize: function() {
            var size = {
                width: this.window.offsetWidth,
                height: this.window.offsetHeight
            };
            raplsaichLsSet('raplsaich_window_size', JSON.stringify(size));
        },

        /**
         * 入力欄を確実にクリア
         */
        clearInput: function() {
            // 常に新しい参照を取得
            var textarea = this.container.querySelector('.chatbot-input textarea');
            var form = this.container.querySelector('.chatbot-input');

            if (textarea) {
                // 値をクリア
                textarea.value = '';
                textarea.defaultValue = '';

                // 高さをリセット
                textarea.style.height = 'auto';

                // フォームをリセット
                if (form && form.reset) {
                    form.reset();
                }

                // 再度値をクリア（リセット後に念のため）
                textarea.value = '';
            }
        },

        /**
         * イベントをバインド
         */
        bindEvents: function() {
            var self = this;

            // バッジクリック → 開く（インラインモードではバッジ非表示のためスキップ）
            if (this.badge && !this.config.inlineMode) {
                this.badge.addEventListener('click', function() {
                    self.open();
                });
            }

            // 閉じるボタン（インラインモードでは非表示のためスキップ、ただし埋め込みモードでは表示）
            var closeBtn = this.container.querySelector('.chatbot-close');
            if (closeBtn && (!this.config.inlineMode || this.config.embedMode)) {
                closeBtn.addEventListener('click', function() {
                    self.close();
                });
            }

            // フォーム送信
            if (!this.inputForm || !this.inputTextarea) return;
            this.inputForm.addEventListener('submit', function(e) {
                e.preventDefault();
                e.stopPropagation();
                self.handleSubmit();
                return false;
            });

            // Enter送信（Shift+Enterで改行、IME変換中は無視）
            this.inputTextarea.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' && !e.shiftKey && !e.isComposing) {
                    e.preventDefault();
                    e.stopPropagation();
                    self.handleSubmit();
                    return false;
                }
            });

            // テキストエリア自動リサイズ
            this.inputTextarea.addEventListener('input', function() {
                this.style.height = 'auto';
                this.style.height = Math.min(this.scrollHeight, 100) + 'px';
            });

            // ESCキーで閉じる（インラインモードでは無効）
            if (!this.config.inlineMode) {
                document.addEventListener('keydown', function(e) {
                    if (e.key === 'Escape' && self.isOpen) {
                        self.close();
                    }
                });
            }

            // ページ表示時（bfcache対策）
            window.addEventListener('pageshow', function(e) {
                if (e.persisted) {
                    self.clearInput();
                }
            });

            // リサイズハンドルのイベント
            if (this.resizeHandle) {
                this.resizeHandle.addEventListener('mousedown', function(e) {
                    e.preventDefault();
                    self.startResize(e.clientX, e.clientY);
                });

                this.resizeHandle.addEventListener('touchstart', function(e) {
                    e.preventDefault();
                    var touch = e.touches[0];
                    self.startResize(touch.clientX, touch.clientY);
                }, { passive: false });
            }
        },

        /**
         * リサイズ開始
         */
        startResize: function(startX, startY) {
            var self = this;
            this.isResizing = true;
            this.window.classList.add('resizing');

            var startWidth = this.window.offsetWidth;
            var startHeight = this.window.offsetHeight;
            var position = this.config.badge_position || 'bottom-right';

            var onMouseMove = function(e) {
                if (!self.isResizing) return;
                e.preventDefault();

                var clientX = e.clientX || (e.touches && e.touches[0].clientX);
                var clientY = e.clientY || (e.touches && e.touches[0].clientY);

                // Determine delta direction based on widget position
                var deltaX, deltaY;
                if (position.indexOf('left') !== -1) {
                    deltaX = clientX - startX; // drag right to expand
                } else {
                    deltaX = startX - clientX; // drag left to expand
                }
                if (position.indexOf('top') !== -1) {
                    deltaY = clientY - startY; // drag down to expand
                } else {
                    deltaY = startY - clientY; // drag up to expand
                }

                var newWidth = Math.max(300, Math.min(startWidth + deltaX, window.innerWidth * 0.9));
                var newHeight = Math.max(400, Math.min(startHeight + deltaY, window.innerHeight * 0.9));

                self.window.style.width = newWidth + 'px';
                self.window.style.height = newHeight + 'px';
            };

            var onMouseUp = function() {
                self.isResizing = false;
                self.window.classList.remove('resizing');
                self.saveWindowSize();

                document.removeEventListener('mousemove', onMouseMove);
                document.removeEventListener('mouseup', onMouseUp);
                document.removeEventListener('touchmove', onMouseMove);
                document.removeEventListener('touchend', onMouseUp);
            };

            document.addEventListener('mousemove', onMouseMove);
            document.addEventListener('mouseup', onMouseUp);
            document.addEventListener('touchmove', onMouseMove, { passive: false });
            document.addEventListener('touchend', onMouseUp);
        },

        /**
         * ウィンドウを開く
         */
        open: function() {
            this.isOpen = true;
            this.container.dataset.state = 'open';
            this.window.setAttribute('aria-hidden', 'false');
            this.window.inert = false;

            // セッション読み込み中は待機（loadSession完了後にcheckAndShowLeadFormが呼ばれる）
            if (this.sessionLoading) {
                return;
            }

            // セッション読み込み完了後の処理
            this.checkAndShowLeadForm();
        },

        /**
         * ウィンドウを閉じる
         */
        close: function() {
            // Embed mode: notify parent frame only, don't alter window state
            if (this.config.embedMode) {
                this.runProHook("stopHandoffPolling");
                if (window.parent !== window) {
                    var targetOrigin = this.config.embed_origin || window.location.origin;
                    window.parent.postMessage({type: 'raplsaich:close'}, targetOrigin);
                }
                return;
            }

            this.isOpen = false;
            this.container.dataset.state = 'closed';
            this.runProHook("stopHandoffPolling");
            // Blur any focused element inside before hiding to avoid aria-hidden warning
            if (document.activeElement && this.window.contains(document.activeElement)) {
                document.activeElement.blur();
            }
            this.window.setAttribute('aria-hidden', 'true');
            this.window.inert = true;

            // リサイズ状態をリセット
            raplsaichLsRemove('raplsaich_window_size');
            this.window.style.width = '';
            this.window.style.height = '';
        },

        /**
         * セッションを読み込み/作成
         */
        loadSession: function() {
            var self = this;

            // 会話履歴保存がオフの場合、毎回新規セッションを作成（会話を引き継がない）
            if (!this.config.save_history) {
                this.clearSession();
                this.sessionId = null;
            }

            // セッションバージョンチェック（sessionStorageを使用）
            var storedVersion = raplsaichSsGet('raplsaich_session_version');
            var storedSessionId = raplsaichSsGet('raplsaich_session');
            var currentVersion = parseInt(this.config.session_version, 10) || 1;

            // セッションをクリアすべき条件:
            // 1. バージョンが異なる場合
            // 2. セッションIDはあるがバージョンがない場合（不整合）
            var shouldClear = false;
            if (storedVersion && parseInt(storedVersion, 10) !== currentVersion) {
                shouldClear = true;
            } else if (storedSessionId && !storedVersion) {
                shouldClear = true;
            }

            if (shouldClear) {
                this.clearSession();
            }

            // セッションストレージからセッションID取得（sessionStorage優先、localStorageフォールバック）
            this.sessionId = raplsaichSsGet('raplsaich_session')
                || raplsaichLsGet('raplsaich_session');

            var finishLoading = function() {
                self.sessionLoading = false;
                // セッション読み込み完了後、チャットが開いていればリードフォームをチェック
                if (self.isOpen) {
                    self.checkAndShowLeadForm();
                }
                // セッション確定後にコンバージョンゴールを再チェック
                // （initConversionTracking時点ではsessionIdが未設定の場合があるため）
                // conversion tracking handled by Pro
            };

            if (!this.sessionId) {
                // 新規セッション作成
                this.sessionLoading = true;
                this.apiRequest('GET', '/session')
                    .then(function(response) {
                        self.sessionId = response.session_id;
                        raplsaichSsSet('raplsaich_session', self.sessionId);
                        raplsaichSsSet('raplsaich_session_version', String(currentVersion));
                        // 会話履歴保存がオンの場合のみ localStorage に永続化
                        if (self.config.save_history) {
                            raplsaichLsSet('raplsaich_session', self.sessionId);
                        }
                        // HMAC トークンを保存（IP 変動時のフォールバック認証用）
                        if (response.session_token) {
                            raplsaichLsSet('raplsaich_session_token', response.session_token);
                        }
                        // セッション作成後にリード設定を読み込み
                        return self.loadLeadConfig();
                    })
                    .then(function() {
                        finishLoading();
                    })
                    .catch(function(error) {
                        if (self.config.debug) {
                            console.error('Session creation failed:', error);
                        }
                        finishLoading();
                    });
            } else {
                // 既存セッションの場合
                this.sessionLoading = true;

                // 会話履歴保存がオンの場合のみ履歴を読み込む
                var loadHistoryPromise = this.config.save_history
                    ? this.loadHistory()
                    : Promise.resolve();

                loadHistoryPromise
                    .then(function() {
                        return self.loadLeadConfig();
                    })
                    .then(function() {
                        finishLoading();
                    })
                    .catch(function(error) {
                        if (self.config.debug) {
                            console.error('Failed to load session data:', error);
                        }
                        finishLoading();
                    });
            }
        },

        /**
         * セッションをクリア
         */
        clearSession: function() {
            // 古いセッションのリード送信フラグを取得してクリア
            var oldSessionId = raplsaichSsGet('raplsaich_session');
            if (oldSessionId) {
                raplsaichSsRemove('raplsaich_lead_submitted_' + oldSessionId);
            }

            raplsaichSsRemove('raplsaich_session');
            raplsaichSsRemove('raplsaich_session_version');
            raplsaichLsRemove('raplsaich_session');
            raplsaichLsRemove('raplsaich_session_token');

            // すべてのリード送信済みフラグをクリア（クリーンアップは常に許可）
            try {
                var ssKeys = Object.keys(sessionStorage);
                for (var si = 0; si < ssKeys.length; si++) {
                    if (ssKeys[si].indexOf('raplsaich_lead_submitted_') === 0) {
                        raplsaichSsRemove(ssKeys[si]);
                    }
                }
            } catch (e) {
                // sessionStorage unavailable (e.g., Safari private mode)
            }

            this.sessionId = null;
            this.leadSubmitted = false;
            this.historyLoaded = false;
            this.leadConfig = null;
            if (this.messagesEl && !this.isOfflineMode) {
                this.messagesEl.innerHTML = '';
            }
        },

        /**
         * リードフォームをチェックして表示
         */
        checkAndShowLeadForm: function() {
            var self = this;
            // Skip in offline mode (form is already displayed)
            if (this.isOfflineMode) return;
            if (this.shouldShowLeadForm()) {
                this.runProHook('showLeadForm');
            } else {
                // リードフォームを表示しない場合は入力フィールドにフォーカス
                if (this.inputTextarea) {
                    this.inputTextarea.focus();
                }
                // 履歴がなく、メッセージもない場合のみウェルカムメッセージを表示
                // ウェルカムスクリーンが表示中の場合はスキップ（重複防止）
                if (!this.historyLoaded && this.messagesEl.children.length === 0 && !this._welcomeScreenActive) {
                    this.showWelcomeMessage();
                }
                // 一番下までスクロール
                setTimeout(function() {
                    self.scrollToBottom();
                }, 100);
            }
        },

        /**
         * 会話履歴を読み込み
         */
        loadHistory: function() {
            var self = this;

            // Skip history loading in offline mode (form is already displayed)
            if (this.isOfflineMode) {
                return Promise.resolve();
            }

            if (!this.sessionId) {
                return Promise.resolve();
            }

            return this.apiRequest('GET', '/history/' + this.sessionId)
                .then(function(response) {
                    // Re-check offline mode (may have been set while fetch was in flight)
                    if (self.isOfflineMode) return;
                    if (response.success && response.messages && response.messages.length > 0) {
                        // 既存のメッセージをクリア
                        self.messagesEl.innerHTML = '';

                        // ウェルカムメッセージを先頭に表示
                        self.showWelcomeMessage();

                        // 履歴からメッセージを復元
                        response.messages.forEach(function(msg) {
                            var role = msg.role === 'assistant' ? 'bot' : msg.role;
                            self.addMessage(role, msg.content, null, msg.id);
                        });

                        self.historyLoaded = true;
                    }
                })
                .catch(function(error) {
                    if (self.config.debug) {
                        console.error('Failed to load history:', error);
                    }
                });
        },

        /**
         * Show welcome message
         */
        showWelcomeMessage: function() {
            var welcomeMsg = this.config.welcome_message || 'Hello! How can I help you today?';

            // Auto-detect: use per-language welcome message for browser language
            if (this.config.response_language === 'auto') {
                var browserLang = (navigator.language || navigator.userLanguage || 'en').substring(0, 2).toLowerCase();
                var defaultTranslations = {
                    en: 'Hello! How can I help you today?',
                    ja: 'こんにちは！何かお手伝いできることはありますか？',
                    zh: '您好！有什么可以帮助您的吗？',
                    ko: '안녕하세요! 무엇을 도와드릴까요?',
                    es: '¡Hola! ¿En qué puedo ayudarte hoy?',
                    fr: 'Bonjour ! Comment puis-je vous aider aujourd\'hui ?',
                    de: 'Hallo! Wie kann ich Ihnen heute helfen?',
                    pt: 'Olá! Como posso ajudá-lo hoje?',
                    it: 'Ciao! Come posso aiutarti oggi?',
                    ru: 'Здравствуйте! Чем могу помочь?',
                    ar: 'مرحبا! كيف يمكنني مساعدتك اليوم؟',
                    th: 'สวัสดีครับ! มีอะไรให้ช่วยไหมครับ?',
                    vi: 'Xin chào! Tôi có thể giúp gì cho bạn?',
                };
                var adminMessages = this.config.welcome_messages || {};
                // Priority: 1) per-language admin message, 2) main welcome_message (kept as-is),
                // 3) default translation (only when main message is unchanged from default)
                if (adminMessages[browserLang]) {
                    welcomeMsg = adminMessages[browserLang];
                } else if (welcomeMsg === 'Hello! How can I help you today?' && defaultTranslations[browserLang]) {
                    welcomeMsg = defaultTranslations[browserLang];
                }
            }

            this.addMessage('bot', welcomeMsg);
            this.showQuickReplies();
        },

        /**
         * Show quick reply buttons after welcome message
         */
        showQuickReplies: function() {
            var replies = this.config.quick_replies;
            if (!replies || !replies.length || !this.messagesEl) return;

            var self = this;
            var container = document.createElement('div');
            container.className = 'chatbot-quick-replies';

            for (var i = 0; i < replies.length; i++) {
                (function(text) {
                    var btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'chatbot-quick-reply-btn';
                    btn.textContent = text;
                    btn.onclick = function() {
                        // Remove quick replies after click
                        if (container.parentNode) container.parentNode.removeChild(container);
                        // Set input and submit
                        if (self.inputTextarea) self.inputTextarea.value = text;
                        self.handleSubmit();
                    };
                    container.appendChild(btn);
                })(replies[i].text || replies[i]);
            }

            this.messagesEl.appendChild(container);
            this.scrollToBottom();
        },

        /**
         * 送信処理（入力欄を即座にクリア）
         */
        handleSubmit: function() {
            var self = this;

            // 常にDOMから最新の参照を取得
            var textarea = this.container.querySelector('.chatbot-input textarea');
            if (!textarea) return;

            var message = textarea.value.trim();

            // 空メッセージまたはロード中は何もしない
            if (!message || this.isLoading) return;

            // Dismiss welcome screen if active
            if (this._welcomeScreenActive) {
                this._dismissWelcomeScreen();
            }

            // 即座にクリア（値取得直後）
            this.clearInput();

            // 非同期でも再度クリア（ブラウザの挙動対策）
            var clearAgain = function() {
                var ta = self.container.querySelector('.chatbot-input textarea');
                if (ta) {
                    ta.value = '';
                    ta.style.height = 'auto';
                }
            };

            // 複数タイミングでクリアを試行
            setTimeout(clearAgain, 0);
            setTimeout(clearAgain, 10);
            setTimeout(clearAgain, 50);
            requestAnimationFrame(clearAgain);

            // メッセージを送信
            this.sendMessage(message);
        },

        /**
         * メッセージを送信
         */
        sendMessage: function(message) {
            if (!message || this.isLoading) return;

            var self = this;

            // ユーザーメッセージを表示
            this.addMessage('user', message);

            // ローディング開始
            this.setLoading(true);

            // セッションIDがない場合は先に取得
            var sendPromise;
            if (!this.sessionId) {
                sendPromise = this.apiRequest('GET', '/session')
                    .then(function(response) {
                        self.sessionId = response.session_id;
                        raplsaichSsSet('raplsaich_session', self.sessionId);
                        if (response.session_token) {
                            raplsaichLsSet('raplsaich_session_token', response.session_token);
                        }
                        return self.doSendMessage(message);
                    });
            } else {
                sendPromise = this.doSendMessage(message);
            }

            sendPromise
                .catch(function(error) {
                    // Skip if already handled (e.g. recaptcha_not_ready)
                    if (error === 'recaptcha_not_ready') return;
                    if (self.config.debug) console.error('Chat error:', error);

                    // Error code → message map (populated from PHP i18n), then HTTP status fallback
                    var _s = self.config.strings || {};
                    var ecMap = _s.error_code_messages || {};
                    var ec = error.errorCode || '';
                    // For rate_limited or __use_server_message__, prefer server message
                    var ecMapValue = ecMap[ec];
                    var errorMessage;
                    if ((ec === 'rate_limited' || ecMapValue === '__use_server_message__') && error.message) {
                        errorMessage = error.message;
                    } else {
                        errorMessage = ecMapValue;
                    } // raplsaich-i18n-ok
                    // Dev aid: warn when server sends error_code not in the PHP map.
                    // Uses is_plugin_admin (no WP_DEBUG requirement) so production admins also see it.
                    if (ec && !errorMessage && self.config.is_plugin_admin) {
                        console.warn('[RAPLSAICH] Unmapped error_code: "' + ec + '". Add to error_code_messages in class-chatbot-widget.php.'); // raplsaich-i18n-ok
                    }
                    if (!errorMessage) {
                        // Fallback to HTTP status categories
                        if (error.status === 429) {
                            errorMessage = _s.error_rate_limit || 'Too many requests. Please try again in a moment.'; // raplsaich-i18n-ok
                        } else if (error.status === 403) {
                            errorMessage = _s.error_unavailable || 'This feature is currently unavailable.'; // raplsaich-i18n-ok
                        } else if (error.status >= 500) {
                            errorMessage = _s.error_server || 'A temporary error occurred. Please try again later.'; // raplsaich-i18n-ok
                        } else {
                            errorMessage = error.message || (_s.error_occurred || 'An error occurred.'); // raplsaich-i18n-ok
                        }
                    }
                    self.addMessage('bot', errorMessage);

                    // 429/503 rate limit: disable input for retry_after seconds
                    if (error.response && error.response.retry_after > 0) {
                        self.startRetryCountdown(error.response.retry_after);
                    }
                })
                .finally(function() {
                    // When response delay is active, setLoading(false) is called after the delay
                    if (!self._responseDelayPending) {
                        self.setLoading(false);
                    }
                    if (self.inputTextarea) self.inputTextarea.focus();
                });
        },

        /**
         * 実際のメッセージ送信処理
         */
        doSendMessage: function(message) {
            var self = this;

            // 画像データを取得してクリア
            var imageData = this.selectedImageData;
            this.clearSelectedImage();

            // reCAPTCHAトークンを取得してから送信
            return this.getRecaptchaToken('chat')
                .then(function(token) {
                    // reCAPTCHA enabled but token empty (script not loaded yet)
                    if (!token && self.config.recaptcha_enabled) {
                        self.addMessage('bot', (self.config.strings && self.config.strings.recaptcha_loading) || 'Security verification loading. Please try again in a moment.');
                        return Promise.reject('recaptcha_not_ready');
                    }

                    var requestData = {
                        session_id: self.sessionId,
                        message: message,
                        page_url: window.location.href,
                        recaptcha_token: token,
                        client_request_id: self.generateRequestId(),
                        bot_id: self.config.bot_id || 'default'
                    };

                    // 画像/ファイルがある場合は追加
                    if (imageData) {
                        if (imageData.indexOf('data:image/') === 0) {
                            requestData.image = imageData;
                        } else {
                            requestData.file = imageData;
                            requestData.file_name = self._selectedFileName || '';
                        }
                    }

                    return self.apiRequest('POST', '/chat', requestData);
                })
                .then(function(response) {
                    var showResponse = function() {
                        if (response.success) {
                            var dedupAge = (response._server_now && response.data && response.data._saved_at)
                                ? (response._server_now - response.data._saved_at)
                                : 999;
                            var isDedupFresh = response.dedup_hit && response.data && response.data._truncated
                                && dedupAge >= 0 && dedupAge < 90;
                            if (isDedupFresh) {
                                var truncMsg;
                                if (response.data._history_saved) {
                                    truncMsg = self.config.strings.dedup_truncated || 'Your message was received and processed. Please reload the page to see the response.';
                                } else {
                                    truncMsg = self.config.strings.dedup_truncated_no_history || 'Your response was processed successfully. To see saved responses, consider enabling chat history in the plugin settings.';
                                }
                                if (response.data.client_request_id) {
                                    truncMsg += ' (ref: ' + response.data.client_request_id.substring(0, 8) + ')';
                                }
                                self.addMessage('bot', truncMsg);
                            } else if (response.dedup_hit && response.data && response.data._truncated) {
                                var staleRef = (response.data.client_request_id)
                                    ? ' (ref: ' + response.data.client_request_id.substring(0, 8) + ')'
                                    : '';
                                self.addMessage('bot', (self.config.strings.dedup_stale || 'A cache inconsistency was detected. Please reload the page. If this persists, the site administrator should check the object cache configuration.') + staleRef);
                            } else {
                                self.addMessage('bot', response.data.content, response.data.sources, response.data.message_id, response.data.sentiment, response.data.product_cards, response.data.web_sources, response.data.action, response.data.content_cards, response.data.scenario, response.data.related_knowledge);
                                self.runProHook("fetchSuggestions");
                                self.saveContext();
                                // conversion tracking handled by Pro
                            }

                            if (response.data && response.data.handoff_triggered) {
                                self.handleHandoffTriggered(response.data);
                            } else if (response.data && response.data.handoff_status) {
                                self.showHandoffIndicator(response.data.handoff_status);
                                // Start polling if operator is active (e.g. operator started from admin)
                                if (!self.handoffPollTimer && (response.data.handoff_status === 'active' || response.data.handoff_status === 'pending')) {
                                    self.handoffStatus = response.data.handoff_status;
                                    self.runProHook("startHandoffPolling");
                                }
                            }
                        } else {
                            self.addMessage('bot', response.error || (self.config.strings && self.config.strings.error_occurred) || 'An error occurred.');
                        }
                    };

                    // Response delay: keep loading indicator visible for a natural typing feel (Pro)
                    var delay = self._responseDelayMs || 0;
                    if (delay > 0) {
                        self._responseDelayPending = true;
                        setTimeout(function() {
                            self._responseDelayPending = false;
                            self.setLoading(false);
                            showResponse();
                        }, delay);
                    } else {
                        showResponse();
                    }
                });
        },

        /**
         * コンテキストを保存（Pro: コンテキスト記憶用）
         */
        saveContext: function() {
            var self = this;

            // session_idが必要（context key はサーバ側で session_id から導出）
            if (!this.sessionId) {
                return;
            }

            // Pro版のエンドポイントを呼び出す（Free版では404になるが無視）
            this.apiRequest('POST', '/save-context', {
                session_id: this.sessionId
            }).catch(function() {
                // エラーは無視（コンテキスト保存は重要ではない）
            });
        },

        /**
         * Handle handoff triggered by server response
         */
        handleHandoffTriggered: function(data) {
            var s = this.config.strings || {};
            this.handoffStatus = 'pending';
            this.addSystemMessage(data.handoff_message || s.handoff_pending || 'A support representative has been notified. Please wait...');
            this.showHandoffIndicator('pending');
            this.runProHook("startHandoffPolling");
        },

        /**
         * Add system message (centered notification)
         */
        addSystemMessage: function(text) {
            var messageEl = document.createElement('div');
            messageEl.className = 'chatbot-message chatbot-message--system';
            var contentEl = document.createElement('div');
            contentEl.className = 'chatbot-message__content';
            contentEl.textContent = text;
            messageEl.appendChild(contentEl);
            this.messagesEl.appendChild(messageEl);
            this.scrollToBottom();
        },

        /**
         * Show/update/remove handoff status indicator bar
         */
        showHandoffIndicator: function(status) {
            var self = this;
            var indicator = this.window ? this.window.querySelector('.chatbot-handoff-indicator') : null;

            if (!status || status === 'resolved') {
                if (indicator) indicator.remove();
                return;
            }

            if (!indicator) {
                indicator = document.createElement('div');
                indicator.className = 'chatbot-handoff-indicator';
                // Insert after header
                var header = this.window ? this.window.querySelector('.chatbot-header') : null;
                if (header && header.nextSibling) {
                    header.parentNode.insertBefore(indicator, header.nextSibling);
                }
            }

            var self = this;
            var s = this.config.strings || {};
            indicator.className = 'chatbot-handoff-indicator chatbot-handoff-indicator--' + status;
            indicator.innerHTML = '';

            // Status icon + text
            var statusText = document.createElement('span');
            statusText.className = 'chatbot-handoff-text';
            if (status === 'pending') {
                var dotP = document.createElement('span');
                dotP.className = 'chatbot-handoff-dot';
                statusText.appendChild(dotP);
                statusText.appendChild(document.createTextNode(s.handoff_waiting || 'Waiting for support representative...'));
            } else if (status === 'active') {
                var dotA = document.createElement('span');
                dotA.className = 'chatbot-handoff-dot chatbot-handoff-dot--active';
                statusText.appendChild(dotA);
                statusText.appendChild(document.createTextNode(s.handoff_active || 'Connected with support'));
            }
            indicator.appendChild(statusText);

            // Cancel / back-to-AI button
            if (status === 'pending') {
                var cancelBtn = document.createElement('button');
                cancelBtn.className = 'chatbot-handoff-cancel';
                cancelBtn.type = 'button';
                cancelBtn.textContent = s.handoff_cancel || 'Back to AI';
                cancelBtn.addEventListener('click', function() {
                    self.runProHook("cancelHandoff");
                });
                indicator.appendChild(cancelBtn);
            }
        },

        /**
         * reCAPTCHAトークンを取得
         * @param {string} action - reCAPTCHA action name (must match PHP expected_action)
         */
        getRecaptchaToken: function(action) {
            var self = this;

            // reCAPTCHAが無効の場合は空のトークンを返す
            if (!this.config.recaptcha_enabled || !this.config.recaptcha_site_key) {
                return Promise.resolve('');
            }

            // grecaptchaが利用可能かチェック
            if (typeof grecaptcha === 'undefined' || !grecaptcha.execute) {
                if (self.config.debug) console.warn('reCAPTCHA is not loaded');
                return Promise.resolve('');
            }

            return new Promise(function(resolve, reject) {
                try {
                    grecaptcha.ready(function() {
                        grecaptcha.execute(self.config.recaptcha_site_key, { action: action || 'chat' })
                            .then(function(token) {
                                resolve(token);
                            })
                            .catch(function(error) {
                                if (self.config.debug) console.error('reCAPTCHA error:', error);
                                resolve(''); // エラーでも送信は続行
                            });
                    });
                } catch (e) {
                    if (self.config.debug) console.error('reCAPTCHA exception:', e);
                    resolve('');
                }
            });
        },

        /**
         * Reload reCAPTCHA script to get a fresh instance (fixes stale token issue on long-lived pages)
         */
        _reloadRecaptcha: function() {
            var self = this;
            return new Promise(function(resolve) {
                // Remove existing reCAPTCHA script tags
                var scripts = document.querySelectorAll('script[src*="recaptcha"]');
                scripts.forEach(function(s) { s.parentNode.removeChild(s); });

                // Reset grecaptcha global
                if (typeof grecaptcha !== 'undefined') {
                    try { delete window.grecaptcha; } catch(e) { window.grecaptcha = undefined; }
                }

                // Load fresh script
                var script = document.createElement('script');
                script.src = 'https://www.google.com/recaptcha/api.js?render=' + encodeURIComponent(self.config.recaptcha_site_key);
                script.onload = function() {
                    // Wait for grecaptcha.ready
                    var checkReady = function(attempts) {
                        if (typeof grecaptcha !== 'undefined' && grecaptcha.ready) {
                            grecaptcha.ready(function() { resolve(); });
                        } else if (attempts < 20) {
                            setTimeout(function() { checkReady(attempts + 1); }, 100);
                        } else {
                            resolve(); // Give up waiting, let retry proceed
                        }
                    };
                    checkReady(0);
                };
                script.onerror = function() { resolve(); };
                document.head.appendChild(script);
            });
        },

        /**
         * Add message to UI
         */
        addMessage: function(role, content, sources, messageId, sentiment, productCards, webSources, actionData, contentCards, scenarioData, relatedKnowledge) {
            var self = this;
            var messageEl = document.createElement('div');
            messageEl.className = 'chatbot-message chatbot-message--' + role;
            if (messageId) {
                messageEl.setAttribute('data-message-id', messageId);
            }

            // Add avatar for bot/operator messages
            if (role === 'bot') {
                var avatarEl = document.createElement('span');
                avatarEl.className = 'chatbot-message__avatar';
                if (this.config.bot_avatar_is_image) {
                    var avatarImg = document.createElement('img');
                    avatarImg.src = this.config.bot_avatar;
                    avatarImg.alt = this.config.bot_name;
                    avatarImg.className = 'chatbot-message__avatar-img';
                    avatarEl.appendChild(avatarImg);
                } else {
                    avatarEl.textContent = this.config.bot_avatar;
                }
                messageEl.appendChild(avatarEl);
            } else if (role === 'operator') {
                var opAvatarEl = document.createElement('span');
                opAvatarEl.className = 'chatbot-message__avatar chatbot-message__avatar--operator';
                opAvatarEl.textContent = '\uD83D\uDC64';
                messageEl.appendChild(opAvatarEl);
            }

            var contentEl = document.createElement('div');
            contentEl.className = 'chatbot-message__content';

            // Add sentiment indicator (Pro feature) - small colored dot
            // Sentiment indicator — added by Pro via message hook

            // Bot/operator messages: safe HTML formatting (line breaks + auto-links, or markdown)
            // User messages: plain text only (no formatting needed)
            if (role === 'bot' || role === 'operator') {
                var formatted = this.formatBotMessage(content);
                var textSpan = document.createElement('span');
                if (this.config.markdown_enabled && role === 'bot') {
                    textSpan.className = 'raplsaich-markdown';
                }
                textSpan.appendChild(formatted);
                contentEl.appendChild(textSpan);
            } else {
                contentEl.textContent = content;
            }

            messageEl.appendChild(contentEl);

            // 参照元があれば追加
            if (sources && sources.length > 0) {
                var sourcesEl = document.createElement('div');
                sourcesEl.className = 'chatbot-message__sources';

                var titleEl = document.createElement('div');
                titleEl.className = 'chatbot-message__sources-title';
                titleEl.textContent = '📄 ' + ((self.config.strings && self.config.strings.sources_title) || 'Reference pages:');
                sourcesEl.appendChild(titleEl);

                sources.forEach(function(url) {
                    try {
                        var parsed = new URL(url);
                        if (parsed.protocol !== 'http:' && parsed.protocol !== 'https:') return;
                    } catch (e) { return; }
                    var linkEl = document.createElement('a');
                    linkEl.href = url;
                    linkEl.target = '_blank';
                    linkEl.rel = 'noopener noreferrer';
                    linkEl.textContent = url;
                    sourcesEl.appendChild(linkEl);
                });

                contentEl.appendChild(sourcesEl);
            }

            // Web search sources
            if (webSources && webSources.length > 0) {
                var webSourcesEl = document.createElement('div');
                webSourcesEl.className = 'chatbot-message__sources chatbot-message__web-sources';

                var webTitleEl = document.createElement('div');
                webTitleEl.className = 'chatbot-message__sources-title';
                webTitleEl.textContent = '\uD83C\uDF10 ' + ((self.config.strings && self.config.strings.web_sources_title) || 'Web sources:');
                webSourcesEl.appendChild(webTitleEl);

                webSources.forEach(function(src) {
                    var url = src.url || '';
                    var title = src.title || '';
                    try {
                        var parsed = new URL(url);
                        if (parsed.protocol !== 'http:' && parsed.protocol !== 'https:') return;
                    } catch (e) { return; }
                    var linkEl = document.createElement('a');
                    linkEl.href = url;
                    linkEl.target = '_blank';
                    linkEl.rel = 'noopener noreferrer';
                    linkEl.textContent = title || url;
                    webSourcesEl.appendChild(linkEl);
                });

                contentEl.appendChild(webSourcesEl);
            }

            // Content cards (RAG source links)
            if (contentCards && contentCards.length > 0) {
                var ccContainer = document.createElement('div');
                ccContainer.className = 'chatbot-content-cards';

                contentCards.forEach(function(cc) {
                    var ccUrl = cc.url || '';
                    try {
                        var parsed = new URL(ccUrl);
                        if (parsed.protocol !== 'http:' && parsed.protocol !== 'https:') return;
                    } catch (e) { return; }

                    var ccLink = document.createElement('a');
                    ccLink.className = 'chatbot-content-card';
                    ccLink.href = ccUrl;
                    ccLink.target = '_blank';
                    ccLink.rel = 'noopener noreferrer';

                    var ccType = document.createElement('div');
                    ccType.className = 'chatbot-content-card__type';
                    ccType.textContent = cc.type || 'page';
                    ccLink.appendChild(ccType);

                    var ccTitle = document.createElement('div');
                    ccTitle.className = 'chatbot-content-card__title';
                    ccTitle.textContent = cc.title || ccUrl;
                    ccLink.appendChild(ccTitle);

                    if (cc.excerpt) {
                        var ccExcerpt = document.createElement('div');
                        ccExcerpt.className = 'chatbot-content-card__excerpt';
                        ccExcerpt.textContent = cc.excerpt;
                        ccLink.appendChild(ccExcerpt);
                    }

                    ccContainer.appendChild(ccLink);
                });

                contentEl.appendChild(ccContainer);
            }

            // Product cards (Pro WooCommerce feature)
            if (productCards && productCards.length > 0) {
                var cardsContainer = document.createElement('div');
                cardsContainer.className = 'chatbot-product-cards';

                productCards.forEach(function(card) {
                    // Validate URL protocol to prevent javascript: XSS
                    try {
                        var cardParsed = new URL(card.url);
                        if (cardParsed.protocol !== 'http:' && cardParsed.protocol !== 'https:') return;
                    } catch (e) { return; }

                    var cardLink = document.createElement('a');
                    cardLink.className = 'chatbot-product-card';
                    cardLink.href = card.url;
                    cardLink.target = '_blank';
                    cardLink.rel = 'noopener noreferrer';

                    if (card.image) {
                        var imgEl = document.createElement('img');
                        imgEl.className = 'chatbot-product-card__image';
                        imgEl.src = card.image;
                        imgEl.alt = card.name;
                        imgEl.loading = 'lazy';
                        cardLink.appendChild(imgEl);
                    }

                    var infoEl = document.createElement('div');
                    infoEl.className = 'chatbot-product-card__info';

                    var nameEl = document.createElement('div');
                    nameEl.className = 'chatbot-product-card__name';
                    nameEl.textContent = card.name;
                    infoEl.appendChild(nameEl);

                    if (card.price_html) {
                        var priceEl = document.createElement('div');
                        priceEl.className = 'chatbot-product-card__price';
                        priceEl.textContent = card.price_html;
                        infoEl.appendChild(priceEl);
                    }

                    if (!card.in_stock) {
                        var stockEl = document.createElement('span');
                        stockEl.className = 'chatbot-product-card__out-of-stock';
                        stockEl.textContent = (self.config.strings && self.config.strings.out_of_stock) || 'Out of stock';
                        infoEl.appendChild(stockEl);
                    }

                    cardLink.appendChild(infoEl);
                    cardsContainer.appendChild(cardLink);
                });

                contentEl.appendChild(cardsContainer);
            }

            // Related knowledge links (Pro feature)
            if (relatedKnowledge && relatedKnowledge.length > 0) {
                var rkContainer = document.createElement('div');
                rkContainer.className = 'chatbot-related-knowledge';

                var rkTitle = document.createElement('div');
                rkTitle.className = 'chatbot-related-knowledge__title';
                rkTitle.textContent = (self.config.strings && self.config.strings.related_knowledge) || 'Related';
                rkContainer.appendChild(rkTitle);

                relatedKnowledge.forEach(function(rk) {
                    var rkBtn = document.createElement('button');
                    rkBtn.type = 'button';
                    rkBtn.className = 'chatbot-related-knowledge__item';
                    rkBtn.textContent = rk.title;
                    rkBtn.addEventListener('click', function() {
                        if (!self.inputTextarea) return;
                        self.inputTextarea.value = rk.title;
                        self.inputTextarea.style.height = 'auto';
                        self.inputTextarea.style.height = Math.min(self.inputTextarea.scrollHeight, 100) + 'px';
                        self.handleSubmit();
                    });
                    rkContainer.appendChild(rkBtn);
                });

                contentEl.appendChild(rkContainer);
            }

            // Action buttons (Pro intent recognition)
            if (actionData) {
                var actionEl = document.createElement('div');
                actionEl.className = 'chatbot-action-buttons';

                if (actionData.type === 'redirect' && actionData.url) {
                    // Validate URL protocol
                    try {
                        var actionParsed = new URL(actionData.url);
                        if (actionParsed.protocol !== 'http:' && actionParsed.protocol !== 'https:') {
                            actionData = null;
                        }
                    } catch (e) { actionData = null; }
                }
                if (actionData && actionData.type === 'redirect' && actionData.url) {
                    var actionBtn = document.createElement('a');
                    actionBtn.href = actionData.url;
                    actionBtn.target = '_blank';
                    actionBtn.rel = 'noopener noreferrer';
                    actionBtn.className = 'chatbot-action-btn';
                    actionBtn.textContent = actionData.label || ((self.config.strings && self.config.strings.open) || 'Open');
                    actionEl.appendChild(actionBtn);
                } else if (actionData && actionData.type === 'link_buttons' && actionData.links) {
                    actionData.links.forEach(function(link) {
                        // Validate URL protocol
                        try {
                            var linkParsed = new URL(link.url);
                            if (linkParsed.protocol !== 'http:' && linkParsed.protocol !== 'https:') return;
                        } catch (e) { return; }
                        var linkBtn = document.createElement('a');
                        linkBtn.href = link.url;
                        linkBtn.target = '_blank';
                        linkBtn.rel = 'noopener noreferrer';
                        linkBtn.className = 'chatbot-action-btn';
                        linkBtn.textContent = link.label;
                        actionEl.appendChild(linkBtn);
                    });
                } else if (actionData && actionData.type === 'notify_email' && actionData.message) {
                    var noticeEl = document.createElement('div');
                    noticeEl.className = 'chatbot-action-notice';
                    noticeEl.textContent = actionData.message;
                    actionEl.appendChild(noticeEl);
                }

                if (actionEl.hasChildNodes()) contentEl.appendChild(actionEl);
            }

            // Scenario UI (Pro conversation scenarios)
            // Scenario UI — added by Pro via message hook

            // Add feedback buttons for bot messages
            if (role === 'bot' && messageId) {
                var actionsEl = document.createElement('div');
                actionsEl.className = 'chatbot-message__actions';

                // Feedback buttons (if enabled)
                if (this.config.show_feedback) {
                    var feedbackEl = document.createElement('div');
                    feedbackEl.className = 'chatbot-message__feedback';

                    var thumbsUp = document.createElement('button');
                    thumbsUp.type = 'button';
                    thumbsUp.className = 'chatbot-feedback-btn chatbot-feedback-btn--up';
                    thumbsUp.innerHTML = '👍';
                    thumbsUp.title = (self.config.strings && self.config.strings.good_response) || 'Good response';
                    thumbsUp.onclick = function() { self.sendFeedback(messageId, 1, this); };

                    var thumbsDown = document.createElement('button');
                    thumbsDown.type = 'button';
                    thumbsDown.className = 'chatbot-feedback-btn chatbot-feedback-btn--down';
                    thumbsDown.innerHTML = '👎';
                    thumbsDown.title = (self.config.strings && self.config.strings.bad_response) || 'Bad response';
                    thumbsDown.onclick = function() { self.sendFeedback(messageId, -1, this); };

                    feedbackEl.appendChild(thumbsUp);
                    feedbackEl.appendChild(thumbsDown);
                    actionsEl.appendChild(feedbackEl);
                }

            // Regenerate UI — added by Pro via message hook

            // Bookmark UI — added by Pro via message hook

                contentEl.appendChild(actionsEl);
            }

            this.messagesEl.appendChild(messageEl);

            // TTS: speak bot responses
            // TTS auto-play — handled by Pro via message hook

            // Pro extensions: regenerate, bookmark, sentiment, scenario, TTS, notification sound
            this.runProHook('message', role, messageEl, {
                messageId: messageId, content: content, sources: sources,
                sentiment: sentiment, scenarioData: scenarioData
            });

            // スクロール
            this.scrollToBottom();

            return messageEl;
        },

        /**
         * Send feedback for a message
         */
        sendFeedback: function(messageId, feedback, btnEl) {
            var self = this;
            var feedbackContainer = btnEl.parentElement;
            var allBtns = feedbackContainer.querySelectorAll('.chatbot-feedback-btn');

            // Remove previous selection
            allBtns.forEach(function(btn) {
                btn.classList.remove('chatbot-feedback-btn--selected');
            });

            // Add selection to clicked button
            btnEl.classList.add('chatbot-feedback-btn--selected');

            this.apiRequest('POST', '/feedback', {
                message_id: messageId,
                feedback: feedback,
                session_id: this.sessionId,
            })
            .then(function(data) {
                if (data && !data.success && self.config.debug) {
                    console.error('Feedback error:', data.error);
                }
            })
            .catch(function(error) {
                if (self.config.debug) console.error('Feedback error:', error);
            });
        },

        /**
         * 選択した画像をクリア
         */
        clearSelectedImage: function() {
            this.selectedImage = null;
            this.selectedImageData = null;
            this._selectedFileName = null;
            if (this.imageInput) {
                this.imageInput.value = '';
            }
            if (this.imagePreview) {
                this.imagePreview.hidden = true;
                var nameEl = this.imagePreview.querySelector('.file-preview-name');
                if (nameEl) nameEl.style.display = 'none';
            }
            if (this.imagePreviewImg) {
                this.imagePreviewImg.src = '';
                this.imagePreviewImg.style.display = '';
            }
        },

        /**
         * ローディング状態を切り替え
         */
        setLoading: function(loading) {
            this.isLoading = loading;
            if (this.typingIndicator) this.typingIndicator.hidden = !loading;
            var submitBtn = this.inputForm ? this.inputForm.querySelector('button[type="submit"]') : null;
            if (submitBtn) submitBtn.disabled = loading;

            if (loading) {
                this.scrollToBottom();
            }
        },

        /**
         * Disable send button for N seconds with countdown (rate limit cooldown).
         * Called when server returns retry_after on 429/503.
         * Stores timer ID to prevent concurrent countdowns from stacking.
         */
        startRetryCountdown: function(seconds) {
            var self = this;
            // Clear any existing countdown to prevent timer stacking
            if (this._retryTimerId) {
                clearTimeout(this._retryTimerId);
                this._retryTimerId = null;
            }
            var btn = this.inputForm.querySelector('button[type="submit"]');
            if (!btn) return;
            var remaining = Math.min(Math.max(1, Math.ceil(seconds)), 120);
            // Save current text to data attribute (survives DOM replacement by
            // page builders) and always update on each countdown start to handle
            // dynamic text changes from translations or theme overrides.
            btn.setAttribute('data-raplsaich-retry-original-text', btn.textContent);
            btn.disabled = true;
            self.isLoading = true;

            var tick = function() {
                // Re-query button in case DOM was replaced (page builders / SPA)
                var currentBtn = self.inputForm.querySelector('button[type="submit"]');
                if (!currentBtn) {
                    if (self.config && self.config.debug) {
                        console.debug('RAPLSAICH: retry countdown cancelled — submit button removed from DOM');
                    }
                    self._retryTimerId = null;
                    self.isLoading = false;
                    return;
                }
                if (remaining <= 0) {
                    currentBtn.textContent = currentBtn.getAttribute('data-raplsaich-retry-original-text') || '';
                    currentBtn.removeAttribute('data-raplsaich-retry-original-text');
                    currentBtn.disabled = false;
                    self.isLoading = false;
                    self._retryTimerId = null;
                    return;
                }
                currentBtn.textContent = remaining + 's';
                remaining--;
                self._retryTimerId = setTimeout(tick, 1000);
            };
            tick();
        },

        /**
         * 一番下までスクロール
         */
        scrollToBottom: function() {
            var self = this;
            setTimeout(function() {
                self.messagesEl.scrollTop = self.messagesEl.scrollHeight;
            }, 10);
        },

        /**
         * APIリクエスト
         */
        apiRequest: function(method, endpoint, data, _retryCount) {
            var self = this;
            var url = this.config.api_base + endpoint;
            _retryCount = _retryCount || 0;

            var headers = {
                'Content-Type': 'application/json',
                'X-WP-Nonce': this.config.nonce
            };

            if (this.sessionId) {
                headers['X-RAPLSAICH-Session'] = this.sessionId;
            }

            // HMAC トークンを送信（IP 変動時のフォールバック認証用）
            var sessionToken = raplsaichLsGet('raplsaich_session_token');
            if (sessionToken) {
                headers['X-RAPLSAICH-Session-Token'] = sessionToken;
            }

            var options = {
                method: method,
                headers: headers
            };

            if (data) {
                options.body = JSON.stringify(data);
            }

            return fetch(url, options)
                .then(function(response) {
                    // レスポンスがJSONかチェック
                    var contentType = response.headers.get('content-type');
                    if (!contentType || !contentType.includes('application/json')) {
                        throw new Error('Server returned non-JSON response');
                    }

                    return response.json().then(function(json) {
                        // 409 Conflict: client-side jittered retry (once only)
                        if (response.status === 409 && json.retryable && _retryCount < 1) {
                            var delay = 300 + Math.floor(Math.random() * 300); // 300-600ms
                            return new Promise(function(resolve) {
                                setTimeout(resolve, delay);
                            }).then(function() {
                                return self.apiRequest(method, endpoint, data, _retryCount + 1);
                            });
                        }

                        if (!response.ok) {
                            // WP_Error format: {code, message, data} vs plugin format: {success, error, error_code}
                            var errorCode = json.error_code || (json.data && json.data.error_code) || json.code || '';
                            var errorMsg = json.error || json.message || (self.config.strings.api_error || 'API error') + ': ' + response.status;

                            // Auto-retry: 429 rate limited → wait and retry with exponential backoff (up to 2 retries)
                            // Skip retry if error_code is rate_limited (intentional block with custom message)
                            if (response.status === 429 && _retryCount < 2 && errorCode !== 'rate_limited') {
                                var retryDelay = (Math.pow(2, _retryCount) * 2000) + Math.floor(Math.random() * 1000);
                                return new Promise(function(resolve) {
                                    setTimeout(resolve, retryDelay);
                                }).then(function() {
                                    return self.apiRequest(method, endpoint, data, _retryCount + 1);
                                });
                            }

                            // Auto-retry: recaptcha_failed → reload reCAPTCHA script and retry (once)
                            if (errorCode === 'recaptcha_failed' && _retryCount < 1) {
                                return self._reloadRecaptcha().then(function() {
                                    // Re-acquire token with fresh reCAPTCHA instance
                                    return self.getRecaptchaToken(data && data.recaptcha_action || 'chat');
                                }).then(function(newToken) {
                                    if (data) {
                                        data.recaptcha_token = newToken;
                                    }
                                    return self.apiRequest(method, endpoint, data, _retryCount + 1);
                                });
                            }

                            // Auto-retry: session_expired → clear session, re-acquire, retry original request (once)
                            if (errorCode === 'session_expired' && _retryCount < 1) {
                                self.clearSession();
                                return self.apiRequest('GET', '/session').then(function(sessResp) {
                                    self.sessionId = sessResp.session_id;
                                    raplsaichSsSet('raplsaich_session', self.sessionId);
                                    if (sessResp.session_token) {
                                        raplsaichLsSet('raplsaich_session_token', sessResp.session_token);
                                    }
                                    if (data && data.session_id) {
                                        data.session_id = self.sessionId;
                                    }
                                    return self.apiRequest(method, endpoint, data, _retryCount + 1);
                                });
                            }

                            var error = new Error(errorMsg);
                            error.response = json;
                            error.errorCode = errorCode;
                            error.status = response.status;
                            throw error;
                        }
                        return json;
                    });
                });
        },

        /**
         * リード設定を読み込み
         */
        loadLeadConfig: function() {
            var self = this;

            // セッションIDがない場合はスキップ
            if (!this.sessionId) {
                return Promise.resolve();
            }

            // 既にリードを送信済みかチェック
            var leadSubmittedKey = 'raplsaich_lead_submitted_' + this.sessionId;
            var leadSubmitted = raplsaichSsGet(leadSubmittedKey);
            if (leadSubmitted) {
                this.leadSubmitted = true;
                return Promise.resolve();
            }

            // リードフォームが存在しない場合はスキップ
            if (!this.leadFormEl) {
                return Promise.resolve();
            }

            return this.apiRequest('GET', '/lead-config')
            .then(function(data) {
                if (data && data.success && data.data && data.data.enabled) {
                    self.leadConfig = data.data;
                }
            })
            .catch(function() {
                // エラーは静かに処理（リードフォームなしで続行）
            });
        },

        /**
         * リードフォームを表示すべきかチェック
         */
        shouldShowLeadForm: function() {
            return this.leadConfig &&
                   this.leadConfig.enabled &&
                   !this.leadSubmitted &&
                   this.leadFormEl;
        },

        /**
         * リードフォームを非表示
         */
        hideLeadForm: function() {
            if (this.leadFormEl) {
                this.leadFormEl.hidden = true;
            }
        },

        /**
         * チャットを表示
         */
        showChat: function() {
            var self = this;
            this.messagesEl.hidden = false;
            this.inputForm.hidden = false;
            this.inputTextarea.focus();

            // ウェルカムメッセージを表示
            if (this.messagesEl.children.length === 0) {
                this.showWelcomeMessage();
            }

            // 少し遅延させてスクロール（DOMの更新を待つ）
            setTimeout(function() {
                self.scrollToBottom();
            }, 50);
        },

        /**
         * Dismiss the welcome screen and show the messages area (Pro)
         */
        _dismissWelcomeScreen: function() {
            this._welcomeScreenActive = false;
            if (this._welcomeScreenEl) {
                this._welcomeScreenEl.remove();
                this._welcomeScreenEl = null;
            }
            if (this.messagesEl) {
                this.messagesEl.style.display = '';
            }
        },

        /**
         * Submit offline message form
         */
        /** Timer ID for _dropped retry hint — cleared on re-submit to prevent stale overwrite. */
        _droppedTimer: null,

        /**
         * Generate a unique request ID for deduplication.
         */
        generateRequestId: function() {
            return Date.now().toString(36) + '_' + Math.random().toString(36).substring(2, 11);
        },

        /**
         * Format bot message content safely using DOM API.
         * Returns a DocumentFragment (not an HTML string) to avoid innerHTML-based XSS vectors.
         * When markdown is enabled, delegates to formatBotMessageMarkdown().
         */
        formatBotMessage: function(text) {
            if (this.config.markdown_enabled) {
                return this.formatBotMessageMarkdown(text);
            }
            return this.formatBotMessagePlain(text);
        },

        /**
         * Plain text formatter: newline→<br> and URL auto-linking only.
         */
        formatBotMessagePlain: function(text) {
            var fragment = document.createDocumentFragment();
            // Split on URL pattern, preserving the matched URLs as separate tokens
            var urlPattern = /https?:\/\/[^\s<>"')\]]+/g;
            var parts = text.split(urlPattern);
            var urls = text.match(urlPattern) || [];

            for (var i = 0; i < parts.length; i++) {
                // Add text segment (with newline → <br> conversion)
                var lines = parts[i].split('\n');
                for (var j = 0; j < lines.length; j++) {
                    if (j > 0) {
                        fragment.appendChild(document.createElement('br'));
                    }
                    if (lines[j]) {
                        fragment.appendChild(document.createTextNode(lines[j]));
                    }
                }
                // Add URL as <a> element (DOM API — href is set via property, not string concat)
                if (i < urls.length) {
                    var a = document.createElement('a');
                    a.href = urls[i];
                    a.target = '_blank';
                    a.rel = 'noopener noreferrer';
                    a.textContent = urls[i];
                    fragment.appendChild(a);
                }
            }
            return fragment;
        },

        /**
         * Markdown formatter: renders headings, lists, blockquotes, code blocks,
         * bold, italic, inline code, and URL auto-links via DOM API.
         * Returns a DocumentFragment.
         */
        formatBotMessageMarkdown: function(text) {
            var self = this;
            var fragment = document.createDocumentFragment();

            // Step 1: Extract fenced code blocks (``` ... ```) and protect them
            var codeBlocks = [];
            var codeBlockPattern = /```(\w*)\n?([\s\S]*?)```/g;
            var processed = text.replace(codeBlockPattern, function(match, lang, code) {
                var index = codeBlocks.length;
                codeBlocks.push({ lang: lang, code: code.replace(/\n$/, '') });
                return '\x00CODEBLOCK' + index + '\x00';
            });

            // Step 2: Split into lines and process block-level elements
            var lines = processed.split('\n');
            var i = 0;

            while (i < lines.length) {
                var line = lines[i];

                // Code block placeholder
                var cbMatch = line.match(/^\x00CODEBLOCK(\d+)\x00$/);
                if (cbMatch) {
                    var cb = codeBlocks[parseInt(cbMatch[1], 10)];
                    var pre = document.createElement('pre');
                    var code = document.createElement('code');
                    if (cb.lang) {
                        code.className = 'language-' + cb.lang;
                    }
                    code.textContent = cb.code;
                    pre.appendChild(code);
                    fragment.appendChild(pre);
                    i++;
                    continue;
                }

                // Headings (# to ###)
                var headingMatch = line.match(/^(#{1,3})\s+(.+)$/);
                if (headingMatch) {
                    var level = headingMatch[1].length;
                    var h = document.createElement('h' + (level + 1)); // h2-h4
                    self._appendInlineMarkdown(h, headingMatch[2]);
                    fragment.appendChild(h);
                    i++;
                    continue;
                }

                // Blockquote (> ...)
                if (/^>\s?/.test(line)) {
                    var bq = document.createElement('blockquote');
                    var bqLines = [];
                    while (i < lines.length && /^>\s?/.test(lines[i])) {
                        bqLines.push(lines[i].replace(/^>\s?/, ''));
                        i++;
                    }
                    var bqP = document.createElement('p');
                    self._appendInlineMarkdown(bqP, bqLines.join('\n'));
                    bq.appendChild(bqP);
                    fragment.appendChild(bq);
                    continue;
                }

                // Unordered list (- or * )
                if (/^[\-\*]\s+/.test(line)) {
                    var ul = document.createElement('ul');
                    while (i < lines.length && /^[\-\*]\s+/.test(lines[i])) {
                        var li = document.createElement('li');
                        self._appendInlineMarkdown(li, lines[i].replace(/^[\-\*]\s+/, ''));
                        ul.appendChild(li);
                        i++;
                    }
                    fragment.appendChild(ul);
                    continue;
                }

                // Ordered list (1. 2. etc.)
                if (/^\d+\.\s+/.test(line)) {
                    var ol = document.createElement('ol');
                    while (i < lines.length && /^\d+\.\s+/.test(lines[i])) {
                        var oli = document.createElement('li');
                        self._appendInlineMarkdown(oli, lines[i].replace(/^\d+\.\s+/, ''));
                        ol.appendChild(oli);
                        i++;
                    }
                    fragment.appendChild(ol);
                    continue;
                }

                // Table (| col | col | with separator |---|---|)
                if (/^\|(.+\|)+\s*$/.test(line) && i + 1 < lines.length && /^\|[\s\-:]+(\|[\s\-:]+)+\s*$/.test(lines[i + 1])) {
                    var table = document.createElement('table');
                    var thead = document.createElement('thead');
                    var tbody = document.createElement('tbody');

                    // Parse alignment from separator row
                    var sepCells = lines[i + 1].split('|').filter(function(c) { return c.trim() !== ''; });
                    var aligns = sepCells.map(function(c) {
                        var t = c.trim();
                        if (t.charAt(0) === ':' && t.charAt(t.length - 1) === ':') return 'center';
                        if (t.charAt(t.length - 1) === ':') return 'right';
                        return '';
                    });

                    // Header row
                    var headerCells = line.split('|').filter(function(c) { return c.trim() !== ''; });
                    var tr = document.createElement('tr');
                    headerCells.forEach(function(cell, ci) {
                        var th = document.createElement('th');
                        self._appendInlineMarkdown(th, cell.trim());
                        if (aligns[ci]) th.style.textAlign = aligns[ci];
                        tr.appendChild(th);
                    });
                    thead.appendChild(tr);
                    table.appendChild(thead);

                    // Skip header and separator
                    i += 2;

                    // Body rows
                    while (i < lines.length && /^\|(.+\|)+\s*$/.test(lines[i])) {
                        var bodyCells = lines[i].split('|').filter(function(c) { return c.trim() !== ''; });
                        var bodyTr = document.createElement('tr');
                        bodyCells.forEach(function(cell, ci) {
                            var td = document.createElement('td');
                            self._appendInlineMarkdown(td, cell.trim());
                            if (aligns[ci]) td.style.textAlign = aligns[ci];
                            bodyTr.appendChild(td);
                        });
                        tbody.appendChild(bodyTr);
                        i++;
                    }
                    table.appendChild(tbody);
                    fragment.appendChild(table);
                    continue;
                }

                // Empty line → skip (paragraph break)
                if (line.trim() === '') {
                    i++;
                    continue;
                }

                // Regular paragraph: collect consecutive non-empty, non-block lines
                var paraLines = [];
                while (i < lines.length && lines[i].trim() !== '' &&
                    !/^#{1,3}\s/.test(lines[i]) &&
                    !/^>\s?/.test(lines[i]) &&
                    !/^[\-\*]\s+/.test(lines[i]) &&
                    !/^\d+\.\s+/.test(lines[i]) &&
                    !/^\|(.+\|)+\s*$/.test(lines[i]) &&
                    !/^\x00CODEBLOCK/.test(lines[i])) {
                    paraLines.push(lines[i]);
                    i++;
                }
                var p = document.createElement('p');
                self._appendInlineMarkdown(p, paraLines.join('\n'));
                fragment.appendChild(p);
            }

            return fragment;
        },

        /**
         * Append inline markdown (bold, italic, inline code, URLs) to a DOM element.
         * All content is created via DOM API — no innerHTML.
         */
        _appendInlineMarkdown: function(el, text) {
            // Tokenize: inline code, bold, italic, markdown links, URLs, line breaks, plain text
            // Order matters: code first, then bold, italic, markdown links [text](url), raw URLs
            var pattern = /(`[^`]+`|\*\*[^*]+\*\*|\*[^*]+\*|_[^_]+_|\[[^\]]+\]\(https?:\/\/[^\s\)]+\)|https?:\/\/[^\s<>"'\)\]]+|\n)/g;
            var lastIndex = 0;
            var match;

            while ((match = pattern.exec(text)) !== null) {
                // Plain text before match
                if (match.index > lastIndex) {
                    el.appendChild(document.createTextNode(text.substring(lastIndex, match.index)));
                }
                var token = match[0];

                if (token === '\n') {
                    el.appendChild(document.createElement('br'));
                } else if (token.charAt(0) === '`') {
                    // Inline code
                    var codeEl = document.createElement('code');
                    codeEl.textContent = token.substring(1, token.length - 1);
                    el.appendChild(codeEl);
                } else if (token.substring(0, 2) === '**') {
                    // Bold
                    var strong = document.createElement('strong');
                    strong.textContent = token.substring(2, token.length - 2);
                    el.appendChild(strong);
                } else if (token.charAt(0) === '*' || token.charAt(0) === '_') {
                    // Italic
                    var em = document.createElement('em');
                    em.textContent = token.substring(1, token.length - 1);
                    el.appendChild(em);
                } else if (token.charAt(0) === '[') {
                    // Markdown link: [text](url)
                    var linkMatch = token.match(/^\[([^\]]+)\]\((https?:\/\/[^\s\)]+)\)$/);
                    if (linkMatch) {
                        var a = document.createElement('a');
                        a.href = linkMatch[2];
                        a.target = '_blank';
                        a.rel = 'noopener noreferrer';
                        a.textContent = linkMatch[1];
                        el.appendChild(a);
                    } else {
                        el.appendChild(document.createTextNode(token));
                    }
                } else if (/^https?:\/\//.test(token)) {
                    // Raw URL
                    var a = document.createElement('a');
                    a.href = token;
                    a.target = '_blank';
                    a.rel = 'noopener noreferrer';
                    a.textContent = token;
                    el.appendChild(a);
                }
                lastIndex = pattern.lastIndex;
            }

            // Remaining plain text
            if (lastIndex < text.length) {
                el.appendChild(document.createTextNode(text.substring(lastIndex)));
            }
        },

        /**
         * Listen for WP Consent API consent changes.
         * Re-initialize consent-gated features when user updates preferences.
         */
        listenForConsentChange: function() {
            var self = this;
            document.addEventListener('wp_listen_for_consent_change', function() {
                if (!raplsaichStorageAllowed()) {
                    // Consent revoked — remove ALL raplsaich_ keys from both storages.
                    // Walk all keys to catch conversion markers from old sessions, etc.
                    try {
                        var i;
                        var lsKeys = Object.keys(localStorage);
                        for (i = 0; i < lsKeys.length; i++) {
                            if (lsKeys[i].indexOf('raplsaich_') === 0) {
                                localStorage.removeItem(lsKeys[i]);
                            }
                        }
                        var ssKeys = Object.keys(sessionStorage);
                        for (i = 0; i < ssKeys.length; i++) {
                            if (ssKeys[i].indexOf('raplsaich_') === 0) {
                                sessionStorage.removeItem(ssKeys[i]);
                            }
                        }
                    } catch (e) { /* private mode or storage unavailable */ }
                }
                // Re-evaluate conversion tracking
                self.runProHook("initConversionTracking");
            });
        },

        /**
         * Initialize conversion tracking
         */
        conversionTrackingInitialized: false,


        /**
         * Pro API request — delegates to apiRequest only when Pro is active.
         * Free version never calls Pro endpoints directly.
         */
        proApiRequest: function(method, endpoint, data) {
            if (!this.config.is_pro) {
                return Promise.resolve({ success: false, error: 'Pro required' });
            }
            return this.apiRequest(method, endpoint, data);
        },

        /**
         * Register a Pro extension hook.
         * @param {string}   name  Hook name ('init', 'message', 'open', 'close').
         * @param {function} fn    Callback, invoked with this = RaplsaichChatbot.
         */
        registerProHook: function(name, fn) {
            if (!this._proHooks[name]) this._proHooks[name] = [];
            this._proHooks[name].push(fn);
        },

        /**
         * Execute all registered Pro hooks for a given name.
         * Returns false if no hooks registered (safe no-op for Free-only).
         */
        runProHook: function(name) {
            var hooks = this._proHooks[name];
            if (!hooks || !hooks.length) return false;
            var args = Array.prototype.slice.call(arguments, 1);
            var self = this;
            hooks.forEach(function(fn) { fn.apply(self, args); });
            return true;
        },

        isValidEmail: function(email) {
            var re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return re.test(email);
        }
    };

    // Expose globally so Pro plugin can register hooks
    window.RaplsaichChatbot = RaplsaichChatbot;

    // DOM準備完了後に初期化
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            RaplsaichChatbot.init();
        });
    } else {
        RaplsaichChatbot.init();
    }

})();

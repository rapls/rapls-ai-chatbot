/**
 * WP AI Chatbot - フロントエンドスクリプト
 */

(function() {
    'use strict';

    /**
     * WP Consent API integration.
     * Returns true if consent is granted for the given category, or if no
     * Consent API / CMP is active (backwards-compatible default).
     * When wpAiChatbotConfig.consent_strict_mode is true, returns false
     * when no Consent API is detected (GDPR-strict sites).
     *
     * @param {string} category Consent category (e.g. 'functional', 'statistics', 'marketing').
     * @returns {boolean}
     */
    function wpaicHasConsent(category) {
        if (typeof window.wp_has_consent === 'function') {
            return window.wp_has_consent(category);
        }
        if (typeof window.wp_get_consent === 'function') {
            return window.wp_get_consent(category) === 'allow';
        }
        // No Consent API present — respect strict mode setting.
        var config = window.wpAiChatbotConfig || {};
        return !config.consent_strict_mode;
    }

    /**
     * Check if persistent storage (localStorage) is allowed by consent.
     * Requires functional or preferences consent.
     */
    function wpaicStorageAllowed() {
        return wpaicHasConsent('functional') || wpaicHasConsent('preferences');
    }

    // localStorage wrappers — gate reads/writes on consent, allow removal always.
    // try/catch guards against Safari private mode and other environments that throw.
    function wpaicLsGet(k) {
        if (!wpaicStorageAllowed()) return null;
        try { return localStorage.getItem(k); } catch (e) { return null; }
    }
    function wpaicLsSet(k, v) {
        if (!wpaicStorageAllowed()) return;
        try { localStorage.setItem(k, v); } catch (e) { /* quota exceeded or private mode */ }
    }
    function wpaicLsRemove(k) {
        try { localStorage.removeItem(k); } catch (e) { /* noop */ }
    }

    // sessionStorage wrappers — session data also gated on functional consent
    function wpaicSsGet(k) {
        if (!wpaicStorageAllowed()) return null;
        try { return sessionStorage.getItem(k); } catch (e) { return null; }
    }
    function wpaicSsSet(k, v) {
        if (!wpaicStorageAllowed()) return;
        try { sessionStorage.setItem(k, v); } catch (e) { /* quota exceeded or private mode */ }
    }
    function wpaicSsRemove(k) {
        try { sessionStorage.removeItem(k); } catch (e) { /* noop */ }
    }

    const WPAIChatbot = {

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

        // ハンドオフ状態
        handoffStatus: null,
        handoffPollTimer: null,
        lastOperatorMessageId: 0,

        // 設定
        config: window.wpAiChatbotConfig || {},

        /**
         * 初期化
         */
        init: function() {
            this.cacheElements();
            if (!this.container) return;

            // Inline mode: shortcode-embedded chatbot
            if (this.config.inlineMode) {
                this.initInlineMode();
            }

            this.createResizeHandle();
            this.bindEvents();
            this.loadSession();  // loadLeadConfigはloadSession内で呼ばれる
            this.loadWindowSize();
            this.setupAutocomplete();
            this.setupImageUpload();
            this.setupVoiceInput();
            this.initWelcomeScreen();
            this.initFullscreenMode();
            this.initResponseDelay();
            this.initNotificationSound();
            this.bindLeadFormEvents();
            this.initOfflineForm();
            this.initConversionTracking();
            this.listenForConsentChange();
            this.isInitialized = true;
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
            }

            var closeBtn = this.container.querySelector('.chatbot-close');
            if (closeBtn) {
                closeBtn.style.display = 'none';
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
            this.inputTextarea = this.inputForm.querySelector('textarea');
            this.typingIndicator = this.container.querySelector('.chatbot-typing');
            this.leadFormEl = this.container.querySelector('.chatbot-lead-form');
            this.leadForm = this.container.querySelector('.lead-form');

            // 画像アップロード関連
            this.imageInput = this.container.querySelector('.chatbot-image-input');
            this.imageBtn = this.container.querySelector('.chatbot-image-btn');
            this.imagePreview = this.container.querySelector('.chatbot-image-preview');
            this.imagePreviewImg = this.imagePreview ? this.imagePreview.querySelector('img') : null;
            this.imagePreviewRemove = this.container.querySelector('.image-preview-remove');

        },

        /**
         * リサイズハンドルを作成
         */
        createResizeHandle: function() {
            // No resize handle in inline mode (container sized by CSS)
            if (this.config.inlineMode) {
                this.resizeHandle = document.createElement('div');
                return;
            }
            this.resizeHandle = document.createElement('div');
            this.resizeHandle.className = 'chatbot-resize-handle';
            this.resizeHandle.setAttribute('aria-label', 'ウィンドウをリサイズ');
            this.window.appendChild(this.resizeHandle);
        },

        /**
         * 保存されたウィンドウサイズを読み込み
         */
        loadWindowSize: function() {
            // Inline mode: size controlled by container CSS
            if (this.config.inlineMode) return;
            var savedSize = wpaicLsGet('wpaic_window_size');
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
            wpaicLsSet('wpaic_window_size', JSON.stringify(size));
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

            // 閉じるボタン（インラインモードでは非表示のためスキップ）
            var closeBtn = this.container.querySelector('.chatbot-close');
            if (closeBtn && !this.config.inlineMode) {
                closeBtn.addEventListener('click', function() {
                    self.close();
                });
            }

            // フォーム送信
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
            this.resizeHandle.addEventListener('mousedown', function(e) {
                e.preventDefault();
                self.startResize(e.clientX, e.clientY);
            });

            this.resizeHandle.addEventListener('touchstart', function(e) {
                e.preventDefault();
                var touch = e.touches[0];
                self.startResize(touch.clientX, touch.clientY);
            }, { passive: false });
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

            var onMouseMove = function(e) {
                if (!self.isResizing) return;
                e.preventDefault();

                var clientX = e.clientX || (e.touches && e.touches[0].clientX);
                var clientY = e.clientY || (e.touches && e.touches[0].clientY);

                // 左上からリサイズするので、差分を反転
                var deltaX = startX - clientX;
                var deltaY = startY - clientY;

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
            this.isOpen = false;
            this.container.dataset.state = 'closed';
            this.window.setAttribute('aria-hidden', 'true');

            // リサイズ状態をリセット
            wpaicLsRemove('wpaic_window_size');
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
            var storedVersion = wpaicSsGet('wpaic_session_version');
            var storedSessionId = wpaicSsGet('wpaic_session');
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
            this.sessionId = wpaicSsGet('wpaic_session')
                || wpaicLsGet('wpaic_session');

            var finishLoading = function() {
                self.sessionLoading = false;
                // セッション読み込み完了後、チャットが開いていればリードフォームをチェック
                if (self.isOpen) {
                    self.checkAndShowLeadForm();
                }
            };

            if (!this.sessionId) {
                // 新規セッション作成
                this.sessionLoading = true;
                this.apiRequest('GET', '/session')
                    .then(function(response) {
                        self.sessionId = response.session_id;
                        wpaicSsSet('wpaic_session', self.sessionId);
                        wpaicSsSet('wpaic_session_version', String(currentVersion));
                        // 会話履歴保存がオンの場合のみ localStorage に永続化
                        if (self.config.save_history) {
                            wpaicLsSet('wpaic_session', self.sessionId);
                        }
                        // HMAC トークンを保存（IP 変動時のフォールバック認証用）
                        if (response.session_token) {
                            wpaicLsSet('wpaic_session_token', response.session_token);
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
            var oldSessionId = wpaicSsGet('wpaic_session');
            if (oldSessionId) {
                wpaicSsRemove('wpaic_lead_submitted_' + oldSessionId);
            }

            wpaicSsRemove('wpaic_session');
            wpaicSsRemove('wpaic_session_version');
            wpaicLsRemove('wpaic_session');
            wpaicLsRemove('wpaic_session_token');

            // すべてのリード送信済みフラグをクリア
            var keys = Object.keys(sessionStorage);
            keys.forEach(function(key) {
                if (key.startsWith('wpaic_lead_submitted_')) {
                    wpaicSsRemove(key);
                }
            });

            this.sessionId = null;
            this.leadSubmitted = false;
            this.historyLoaded = false;
            this.leadConfig = null;
            if (this.messagesEl) {
                this.messagesEl.innerHTML = '';
            }
        },

        /**
         * リードフォームをチェックして表示
         */
        checkAndShowLeadForm: function() {
            var self = this;
            if (this.shouldShowLeadForm()) {
                this.showLeadForm();
            } else {
                // リードフォームを表示しない場合は入力フィールドにフォーカス
                if (this.inputTextarea) {
                    this.inputTextarea.focus();
                }
                // 履歴がなく、メッセージもない場合のみウェルカムメッセージを表示
                if (!this.historyLoaded && this.messagesEl.children.length === 0) {
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

            if (!this.sessionId) {
                return Promise.resolve();
            }

            return this.apiRequest('GET', '/history/' + this.sessionId)
                .then(function(response) {
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
                        wpaicSsSet('wpaic_session', self.sessionId);
                        if (response.session_token) {
                            wpaicLsSet('wpaic_session_token', response.session_token);
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
                    console.error('Chat error:', error);

                    // Error code → message map (populated from PHP i18n), then HTTP status fallback
                    var _s = self.config.strings || {};
                    var ecMap = _s.error_code_messages || {};
                    var ec = error.errorCode || '';
                    var errorMessage = ecMap[ec]; // wpaic-i18n-ok
                    // Dev aid: warn when server sends error_code not in the PHP map.
                    // Uses is_plugin_admin (no WP_DEBUG requirement) so production admins also see it.
                    if (ec && !errorMessage && self.config.is_plugin_admin) {
                        console.warn('[WPAIC] Unmapped error_code: "' + ec + '". Add to error_code_messages in class-chatbot-widget.php.'); // wpaic-i18n-ok
                    }
                    if (!errorMessage) {
                        // Fallback to HTTP status categories
                        if (error.status === 429) {
                            errorMessage = _s.error_rate_limit || 'Too many requests. Please try again in a moment.'; // wpaic-i18n-ok
                        } else if (error.status === 403) {
                            errorMessage = _s.error_unavailable || 'This feature is currently unavailable.'; // wpaic-i18n-ok
                        } else if (error.status >= 500) {
                            errorMessage = _s.error_server || 'A temporary error occurred. Please try again later.'; // wpaic-i18n-ok
                        } else {
                            errorMessage = error.message || (_s.error_occurred || 'An error occurred.'); // wpaic-i18n-ok
                        }
                    }
                    self.addMessage('bot', errorMessage);

                    // 429/503 rate limit: disable input for retry_after seconds
                    if (error.response && error.response.retry_after > 0) {
                        self.startRetryCountdown(error.response.retry_after);
                    }
                })
                .finally(function() {
                    self.setLoading(false);
                    self.inputTextarea.focus();
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

                    // 画像がある場合は追加
                    if (imageData) {
                        requestData.image = imageData;
                    }

                    return self.apiRequest('POST', '/chat', requestData);
                })
                .then(function(response) {
                    if (response.success) {
                        // Dedup done-marker: server processed the request but the
                        // cached payload was too large to store. Show a gentle
                        // notice instead of an empty bubble to avoid "blank reply" UX.
                        // Dedup freshness check: only trust _truncated if saved
                        // within 90s (transient TTL=60s + margin). Uses server
                        // timestamps to avoid client clock skew issues.
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
                            // Stale dedup hit (>90s or missing timestamps) — show
                            // reload notice instead of empty content bubble.
                            var staleRef = (response.data.client_request_id)
                                ? ' (ref: ' + response.data.client_request_id.substring(0, 8) + ')'
                                : '';
                            self.addMessage('bot', (self.config.strings.dedup_stale || 'A cache inconsistency was detected. Please reload the page. If this persists, the site administrator should check the object cache configuration.') + staleRef);
                        } else {
                            self.addMessage('bot', response.data.content, response.data.sources, response.data.message_id, response.data.sentiment, response.data.product_cards, response.data.web_sources, response.data.action, response.data.content_cards, response.data.scenario);
                            // Fetch related question suggestions (Pro)
                            self.fetchSuggestions();
                            // Save context for memory (Pro) - async, don't wait
                            self.saveContext();
                        }

                        // Handoff detection (Pro: live agent escalation)
                        if (response.data && response.data.handoff_triggered) {
                            self.handleHandoffTriggered(response.data);
                        } else if (response.data && response.data.handoff_status) {
                            self.showHandoffIndicator(response.data.handoff_status);
                        }
                    } else {
                        self.addMessage('bot', response.error || (self.config.strings && self.config.strings.error_occurred) || 'An error occurred.');
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

            // Pro版のエンドポイントを直接呼び出す（Free版では404になるが無視）
            var url = this.config.api_base + '/save-context';

            fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': this.config.nonce
                },
                body: JSON.stringify({
                    session_id: this.sessionId
                })
            })
            .then(function(response) {
                // レスポンスがJSONでない場合は無視（Pro版未インストール等）
                var contentType = response.headers.get('content-type');
                if (!contentType || !contentType.includes('application/json')) {
                    return null;
                }
                return response.json();
            })
            .catch(function() {
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
            this.startHandoffPolling();
        },

        /**
         * Start polling for handoff status and operator messages
         */
        startHandoffPolling: function() {
            var self = this;
            this.stopHandoffPolling();
            this.handoffPollTimer = setInterval(function() {
                self.pollHandoffStatus();
            }, 5000);
        },

        /**
         * Stop handoff polling
         */
        stopHandoffPolling: function() {
            if (this.handoffPollTimer) {
                clearInterval(this.handoffPollTimer);
                this.handoffPollTimer = null;
            }
        },

        /**
         * Poll handoff status endpoint
         */
        pollHandoffStatus: function() {
            var self = this;
            if (!this.sessionId) return;

            var url = this.config.api_base + '/handoff-status/' + encodeURIComponent(this.sessionId);
            if (this.lastOperatorMessageId) {
                url += '?last_message_id=' + this.lastOperatorMessageId;
            }

            fetch(url, {
                method: 'GET',
                headers: { 'X-WP-Nonce': this.config.nonce }
            })
            .then(function(response) {
                if (!response.ok) return null;
                return response.json();
            })
            .then(function(result) {
                if (!result || !result.success) return;
                var data = result.data || {};

                // Status change detection
                if (data.handoff_status !== self.handoffStatus) {
                    var prevStatus = self.handoffStatus;
                    self.handoffStatus = data.handoff_status;
                    var s = self.config.strings || {};

                    if (data.handoff_status === 'active' && prevStatus === 'pending') {
                        self.addSystemMessage(s.handoff_active || 'Connected with support');
                    } else if (data.handoff_status === 'resolved' || data.handoff_status === null) {
                        self.addSystemMessage(s.handoff_resolved || 'Support session ended. You are now chatting with AI again.');
                        self.stopHandoffPolling();
                        self.handoffStatus = null;
                    }
                    self.showHandoffIndicator(data.handoff_status);
                }

                // Render new operator messages
                if (data.messages && data.messages.length > 0) {
                    data.messages.forEach(function(msg) {
                        if (msg.role === 'operator') {
                            self.addMessage('operator', msg.content, null, msg.id);
                            if (msg.id > self.lastOperatorMessageId) {
                                self.lastOperatorMessageId = msg.id;
                            }
                        }
                    });
                }
            })
            .catch(function() {
                // Polling errors are non-critical
            });
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

            var s = this.config.strings || {};
            indicator.className = 'chatbot-handoff-indicator chatbot-handoff-indicator--' + status;
            if (status === 'pending') {
                indicator.textContent = s.handoff_waiting || 'Waiting for support representative...';
            } else if (status === 'active') {
                indicator.textContent = s.handoff_active || 'Connected with support';
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
                console.warn('reCAPTCHA is not loaded');
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
                                console.error('reCAPTCHA error:', error);
                                resolve(''); // エラーでも送信は続行
                            });
                    });
                } catch (e) {
                    console.error('reCAPTCHA exception:', e);
                    resolve('');
                }
            });
        },

        /**
         * Add message to UI
         */
        addMessage: function(role, content, sources, messageId, sentiment, productCards, webSources, actionData, contentCards, scenarioData) {
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
            if (role === 'bot' && sentiment && sentiment !== 'neutral') {
                var sentimentEl = document.createElement('span');
                sentimentEl.className = 'chatbot-sentiment chatbot-sentiment--' + sentiment;
                var s = this.config.strings || {};
                var sentimentTitles = {
                    'frustrated': s.sentiment_frustrated || 'Frustrated',
                    'confused': s.sentiment_confused || 'Confused',
                    'urgent': s.sentiment_urgent || 'Urgent',
                    'positive': s.sentiment_positive || 'Positive',
                    'negative': s.sentiment_negative || 'Negative'
                };
                sentimentEl.title = sentimentTitles[sentiment] || sentiment;
                contentEl.appendChild(sentimentEl);
            }

            // Bot/operator messages: safe HTML formatting (line breaks + auto-links, or markdown)
            // User messages: plain text only (no formatting needed)
            if (role === 'bot' || role === 'operator') {
                var formatted = this.formatBotMessage(content);
                var textSpan = document.createElement('span');
                if (this.config.markdown_enabled && role === 'bot') {
                    textSpan.className = 'wpaic-markdown';
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

            // Action buttons (Pro intent recognition)
            if (actionData) {
                var actionEl = document.createElement('div');
                actionEl.className = 'chatbot-action-buttons';

                if (actionData.type === 'redirect' && actionData.url) {
                    var actionBtn = document.createElement('a');
                    actionBtn.href = actionData.url;
                    actionBtn.target = '_blank';
                    actionBtn.rel = 'noopener noreferrer';
                    actionBtn.className = 'chatbot-action-btn';
                    actionBtn.textContent = actionData.label || 'Open';
                    actionEl.appendChild(actionBtn);
                } else if (actionData.type === 'link_buttons' && actionData.links) {
                    actionData.links.forEach(function(link) {
                        var linkBtn = document.createElement('a');
                        linkBtn.href = link.url;
                        linkBtn.target = '_blank';
                        linkBtn.rel = 'noopener noreferrer';
                        linkBtn.className = 'chatbot-action-btn';
                        linkBtn.textContent = link.label;
                        actionEl.appendChild(linkBtn);
                    });
                } else if (actionData.type === 'notify_email' && actionData.message) {
                    var noticeEl = document.createElement('div');
                    noticeEl.className = 'chatbot-action-notice';
                    noticeEl.textContent = actionData.message;
                    actionEl.appendChild(noticeEl);
                }

                contentEl.appendChild(actionEl);
            }

            // Scenario UI (Pro conversation scenarios)
            if (scenarioData) {
                var scenarioEl = document.createElement('div');
                scenarioEl.className = 'chatbot-scenario-ui';

                // Progress bar
                if (typeof scenarioData.progress === 'number') {
                    var progressEl = document.createElement('div');
                    progressEl.className = 'chatbot-scenario-progress';

                    var labelEl = document.createElement('div');
                    labelEl.className = 'chatbot-scenario-progress__label';
                    var nameSpan = document.createElement('span');
                    nameSpan.textContent = scenarioData.name || '';
                    labelEl.appendChild(nameSpan);
                    var pctSpan = document.createElement('span');
                    pctSpan.textContent = scenarioData.progress + '%';
                    labelEl.appendChild(pctSpan);
                    progressEl.appendChild(labelEl);

                    var barEl = document.createElement('div');
                    barEl.className = 'chatbot-scenario-progress__bar';
                    var fillEl = document.createElement('div');
                    fillEl.className = 'chatbot-scenario-progress__fill';
                    fillEl.style.width = scenarioData.progress + '%';
                    barEl.appendChild(fillEl);
                    progressEl.appendChild(barEl);

                    scenarioEl.appendChild(progressEl);
                }

                // Select options as clickable buttons (for input steps with select type)
                if (scenarioData.input && scenarioData.input.options && scenarioData.input.options.length > 0) {
                    var optionsEl = document.createElement('div');
                    optionsEl.className = 'chatbot-scenario-options';
                    scenarioData.input.options.forEach(function(opt) {
                        var optBtn = document.createElement('button');
                        optBtn.type = 'button';
                        optBtn.className = 'chatbot-scenario-option-btn';
                        optBtn.textContent = opt;
                        optBtn.onclick = function() {
                            optionsEl.remove();
                            self.inputTextarea.value = opt;
                            self.handleSubmit();
                        };
                        optionsEl.appendChild(optBtn);
                    });
                    scenarioEl.appendChild(optionsEl);
                }

                // Completion indicator
                if (scenarioData.status === 'completed') {
                    var completeEl = document.createElement('div');
                    completeEl.className = 'chatbot-scenario-complete';
                    completeEl.textContent = '\u2713 ' + (scenarioData.name || 'Scenario') + ' completed';
                    scenarioEl.appendChild(completeEl);
                }

                if (scenarioEl.childNodes.length > 0) {
                    contentEl.appendChild(scenarioEl);
                }
            }

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

                // Regenerate button (Pro only, can be disabled in settings)
                if (this.config.is_pro && this.config.show_regenerate) {
                    var regenerateBtn = document.createElement('button');
                    regenerateBtn.type = 'button';
                    regenerateBtn.className = 'chatbot-regenerate-btn';
                    regenerateBtn.innerHTML = '🔄';
                    regenerateBtn.title = (self.config.strings && self.config.strings.regenerate) || 'Regenerate response';
                    // Read current message_id from DOM each time (may change after regeneration)
                    regenerateBtn.onclick = function() {
                        var currentMessageId = parseInt(messageEl.getAttribute('data-message-id'), 10);
                        self.regenerateResponse(currentMessageId, messageEl);
                    };
                    actionsEl.appendChild(regenerateBtn);
                }

                contentEl.appendChild(actionsEl);
            }

            this.messagesEl.appendChild(messageEl);

            // TTS: speak bot responses
            if (role === 'bot' && content && this.ttsEnabled && this.ttsActive) {
                this.speakText(content);
                this.playNotificationSound();
            }

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

            fetch(this.config.restUrl + 'feedback', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    message_id: messageId,
                    feedback: feedback,
                    session_id: this.sessionId,
                }),
            })
            .then(function(response) { return response.json(); })
            .then(function(data) {
                if (!data.success) {
                    console.error('Feedback error:', data.error);
                }
            })
            .catch(function(error) {
                console.error('Feedback error:', error);
            });
        },

        /**
         * Regenerate AI response (Pro feature)
         */
        regenerateResponse: function(messageId, messageEl) {
            var self = this;

            if (this.isLoading) return;

            // Show loading
            this.setLoading(true);

            // Add loading class to message
            messageEl.classList.add('chatbot-message--regenerating');

            fetch(this.config.restUrl + 'regenerate', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    message_id: messageId,
                    session_id: this.sessionId,
                }),
            })
            .then(function(response) { return response.json(); })
            .then(function(data) {
                self.setLoading(false);
                messageEl.classList.remove('chatbot-message--regenerating');

                if (data.success) {
                    // Update message content
                    var contentEl = messageEl.querySelector('.chatbot-message__content');
                    var actionsEl = contentEl.querySelector('.chatbot-message__actions');

                    var formatted = self.formatBotMessage(data.data.content);
                    contentEl.innerHTML = '';
                    var textSpan = document.createElement('span');
                    if (self.config.markdown_enabled) {
                        textSpan.className = 'wpaic-markdown';
                    }
                    textSpan.appendChild(formatted);
                    contentEl.appendChild(textSpan);

                    // Re-add sources if any
                    if (data.data.sources && data.data.sources.length > 0) {
                        var sourcesEl = document.createElement('div');
                        sourcesEl.className = 'chatbot-message__sources';

                        var titleEl = document.createElement('div');
                        titleEl.className = 'chatbot-message__sources-title';
                        titleEl.textContent = '📄 ' + ((self.config.strings && self.config.strings.sources_title) || 'Reference pages:');
                        sourcesEl.appendChild(titleEl);

                        data.data.sources.forEach(function(url) {
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

                    // Re-add web sources if any
                    if (data.data.web_sources && data.data.web_sources.length > 0) {
                        var webSourcesEl = document.createElement('div');
                        webSourcesEl.className = 'chatbot-message__sources chatbot-message__web-sources';
                        var webTitleEl = document.createElement('div');
                        webTitleEl.className = 'chatbot-message__sources-title';
                        webTitleEl.textContent = '\uD83C\uDF10 ' + ((self.config.strings && self.config.strings.web_sources_title) || 'Web sources:');
                        webSourcesEl.appendChild(webTitleEl);
                        data.data.web_sources.forEach(function(src) {
                            var wUrl = src.url || '';
                            var wTitle = src.title || '';
                            try {
                                var parsed = new URL(wUrl);
                                if (parsed.protocol !== 'http:' && parsed.protocol !== 'https:') return;
                            } catch (e) { return; }
                            var linkEl = document.createElement('a');
                            linkEl.href = wUrl;
                            linkEl.target = '_blank';
                            linkEl.rel = 'noopener noreferrer';
                            linkEl.textContent = wTitle || wUrl;
                            webSourcesEl.appendChild(linkEl);
                        });
                        contentEl.appendChild(webSourcesEl);
                    }

                    // Re-add content cards if any (RAG source links)
                    if (data.data.content_cards && data.data.content_cards.length > 0) {
                        var regenCcContainer = document.createElement('div');
                        regenCcContainer.className = 'chatbot-content-cards';
                        data.data.content_cards.forEach(function(cc) {
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
                            regenCcContainer.appendChild(ccLink);
                        });
                        contentEl.appendChild(regenCcContainer);
                    }

                    // Re-add product cards if any (Pro WooCommerce)
                    if (data.data.product_cards && data.data.product_cards.length > 0) {
                        var cardsContainer = document.createElement('div');
                        cardsContainer.className = 'chatbot-product-cards';
                        data.data.product_cards.forEach(function(card) {
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

                    // Re-add action buttons if any (Pro intent recognition)
                    if (data.data.action) {
                        var regenAction = data.data.action;
                        var regenActionEl = document.createElement('div');
                        regenActionEl.className = 'chatbot-action-buttons';
                        if (regenAction.type === 'redirect' && regenAction.url) {
                            var rBtn = document.createElement('a');
                            rBtn.href = regenAction.url;
                            rBtn.target = '_blank';
                            rBtn.rel = 'noopener noreferrer';
                            rBtn.className = 'chatbot-action-btn';
                            rBtn.textContent = regenAction.label || 'Open';
                            regenActionEl.appendChild(rBtn);
                        } else if (regenAction.type === 'link_buttons' && regenAction.links) {
                            regenAction.links.forEach(function(link) {
                                var lBtn = document.createElement('a');
                                lBtn.href = link.url;
                                lBtn.target = '_blank';
                                lBtn.rel = 'noopener noreferrer';
                                lBtn.className = 'chatbot-action-btn';
                                lBtn.textContent = link.label;
                                regenActionEl.appendChild(lBtn);
                            });
                        } else if (regenAction.type === 'notify_email' && regenAction.message) {
                            var rNotice = document.createElement('div');
                            rNotice.className = 'chatbot-action-notice';
                            rNotice.textContent = regenAction.message;
                            regenActionEl.appendChild(rNotice);
                        }
                        contentEl.appendChild(regenActionEl);
                    }

                    // Re-add actions
                    if (actionsEl) {
                        contentEl.appendChild(actionsEl);
                    }

                    // Update message ID
                    messageEl.setAttribute('data-message-id', data.data.message_id);
                } else {
                    console.error('Regenerate error:', data.error);
                }
            })
            .catch(function(error) {
                self.setLoading(false);
                messageEl.classList.remove('chatbot-message--regenerating');
                console.error('Regenerate error:', error);
            });
        },

        /**
         * Fetch and display related question suggestions (Pro feature)
         */
        fetchSuggestions: function() {
            var self = this;

            if (!this.config.is_pro || !this.config.related_suggestions || !this.sessionId) {
                return;
            }

            fetch(this.config.restUrl + 'suggestions', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ session_id: this.sessionId }),
            })
            .then(function(response) {
                var contentType = response.headers.get('content-type');
                if (!contentType || !contentType.includes('application/json')) {
                    return null;
                }
                return response.json();
            })
            .then(function(data) {
                if (data && data.success && data.data && data.data.suggestions && data.data.suggestions.length > 0) {
                    self.showSuggestions(data.data.suggestions);
                }
            })
            .catch(function(error) {
                // Silently ignore errors
            });
        },

        /**
         * Display suggestion buttons
         */
        showSuggestions: function(suggestions) {
            var self = this;

            // Remove existing suggestions
            var existing = this.messagesEl.querySelector('.chatbot-suggestions');
            if (existing) existing.remove();

            var suggestionsEl = document.createElement('div');
            suggestionsEl.className = 'chatbot-suggestions';

            var titleEl = document.createElement('div');
            titleEl.className = 'chatbot-suggestions__title';
            titleEl.textContent = (this.config.strings && this.config.strings.suggestions_title) || 'You might also ask:';
            suggestionsEl.appendChild(titleEl);

            var buttonsEl = document.createElement('div');
            buttonsEl.className = 'chatbot-suggestions__buttons';

            suggestions.forEach(function(suggestion) {
                var btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'chatbot-suggestion-btn';
                btn.textContent = suggestion;
                btn.onclick = function() {
                    suggestionsEl.remove();
                    self.inputTextarea.value = suggestion;
                    self.handleSubmit();
                };
                buttonsEl.appendChild(btn);
            });

            suggestionsEl.appendChild(buttonsEl);
            this.messagesEl.appendChild(suggestionsEl);
            this.scrollToBottom();
        },

        /**
         * Setup autocomplete for input (Pro feature)
         */
        setupAutocomplete: function() {
            var self = this;

            if (!this.config.is_pro || !this.config.autocomplete) return;

            // Create autocomplete dropdown
            this.autocompleteEl = document.createElement('div');
            this.autocompleteEl.className = 'chatbot-autocomplete';
            this.autocompleteEl.hidden = true;
            this.inputForm.appendChild(this.autocompleteEl);

            var debounceTimer;
            this.inputTextarea.addEventListener('input', function() {
                clearTimeout(debounceTimer);
                var query = this.value.trim();

                if (query.length < 3) {
                    self.autocompleteEl.hidden = true;
                    return;
                }

                debounceTimer = setTimeout(function() {
                    self.fetchAutocomplete(query);
                }, 300);
            });

            // Hide on blur
            this.inputTextarea.addEventListener('blur', function() {
                setTimeout(function() {
                    self.autocompleteEl.hidden = true;
                }, 200);
            });
        },

        /**
         * Fetch autocomplete suggestions
         */
        fetchAutocomplete: function(query) {
            var self = this;

            fetch(this.config.restUrl + 'autocomplete', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ query: query, session_id: self.sessionId }),
            })
            .then(function(response) {
                var contentType = response.headers.get('content-type');
                if (!contentType || !contentType.includes('application/json')) {
                    return null;
                }
                return response.json();
            })
            .then(function(data) {
                if (data && data.success && data.data && data.data.suggestions && data.data.suggestions.length > 0) {
                    self.showAutocomplete(data.data.suggestions);
                } else {
                    self.autocompleteEl.hidden = true;
                }
            })
            .catch(function(error) {
                self.autocompleteEl.hidden = true;
            });
        },

        /**
         * Show autocomplete dropdown
         */
        showAutocomplete: function(suggestions) {
            var self = this;

            this.autocompleteEl.innerHTML = '';

            suggestions.forEach(function(suggestion) {
                var item = document.createElement('div');
                item.className = 'chatbot-autocomplete__item';
                item.textContent = suggestion;
                item.onclick = function() {
                    self.inputTextarea.value = suggestion;
                    self.autocompleteEl.hidden = true;
                    self.inputTextarea.focus();
                };
                self.autocompleteEl.appendChild(item);
            });

            this.autocompleteEl.hidden = false;
        },

        /**
         * 画像アップロード機能をセットアップ（Pro: マルチモーダル）
         */
        setupImageUpload: function() {
            var self = this;

            // マルチモーダルが無効の場合は何もしない
            if (!this.config.multimodal_enabled) {
                return;
            }

            // 画像ボタンを表示
            if (this.imageBtn) {
                this.imageBtn.hidden = false;

                // 画像ボタンクリックでファイル選択を開く
                this.imageBtn.addEventListener('click', function() {
                    self.imageInput.click();
                });
            }

            // ファイル選択時の処理
            if (this.imageInput) {
                this.imageInput.addEventListener('change', function(e) {
                    var file = e.target.files[0];
                    if (file) {
                        self.handleImageSelect(file);
                    }
                });
            }

            // プレビュー削除ボタン
            if (this.imagePreviewRemove) {
                this.imagePreviewRemove.addEventListener('click', function() {
                    self.clearSelectedImage();
                });
            }
        },

        /**
         * 画像選択時の処理
         */
        handleImageSelect: function(file) {
            var self = this;
            var maxSize = (this.config.multimodal_max_size || 2048) * 1024; // KB to bytes

            // ファイルサイズチェック
            if (file.size > maxSize) {
                var imgMsg = (this.config.strings && this.config.strings.image_too_large) || 'Image is too large. Please select an image under %sKB.';
                alert(imgMsg.replace('%s', (maxSize / 1024)));
                this.imageInput.value = '';
                return;
            }

            // 画像タイプチェック
            var allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            if (allowedTypes.indexOf(file.type) === -1) {
                alert((this.config.strings && this.config.strings.image_invalid_format) || 'Unsupported image format. Please select JPEG, PNG, GIF, or WebP.');
                this.imageInput.value = '';
                return;
            }

            // 画像をBase64に変換
            var reader = new FileReader();
            reader.onload = function(e) {
                self.selectedImage = file;
                self.selectedImageData = e.target.result;
                self.showImagePreview(e.target.result);
            };
            reader.readAsDataURL(file);
        },

        /**
         * 画像プレビューを表示
         */
        showImagePreview: function(dataUrl) {
            if (this.imagePreview && this.imagePreviewImg) {
                this.imagePreviewImg.src = dataUrl;
                this.imagePreview.hidden = false;
            }
        },

        /**
         * 選択した画像をクリア
         */
        clearSelectedImage: function() {
            this.selectedImage = null;
            this.selectedImageData = null;
            if (this.imageInput) {
                this.imageInput.value = '';
            }
            if (this.imagePreview) {
                this.imagePreview.hidden = true;
            }
            if (this.imagePreviewImg) {
                this.imagePreviewImg.src = '';
            }
        },

        /**
         * ローディング状態を切り替え
         */
        setLoading: function(loading) {
            this.isLoading = loading;
            this.typingIndicator.hidden = !loading;
            this.inputForm.querySelector('button[type="submit"]').disabled = loading;

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
            btn.setAttribute('data-wpaic-retry-original-text', btn.textContent);
            btn.disabled = true;
            self.isLoading = true;

            var tick = function() {
                // Re-query button in case DOM was replaced (page builders / SPA)
                var currentBtn = self.inputForm.querySelector('button[type="submit"]');
                if (!currentBtn) {
                    if (self.config && self.config.debug) {
                        console.debug('WPAIC: retry countdown cancelled — submit button removed from DOM');
                    }
                    self._retryTimerId = null;
                    self.isLoading = false;
                    return;
                }
                if (remaining <= 0) {
                    currentBtn.textContent = currentBtn.getAttribute('data-wpaic-retry-original-text') || '';
                    currentBtn.removeAttribute('data-wpaic-retry-original-text');
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
                headers['X-WPAIC-Session'] = this.sessionId;
            }

            // HMAC トークンを送信（IP 変動時のフォールバック認証用）
            var sessionToken = wpaicLsGet('wpaic_session_token');
            if (sessionToken) {
                headers['X-WPAIC-Session-Token'] = sessionToken;
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
                            var errorMsg = json.error || json.message || 'API error: ' + response.status;

                            // Auto-retry: 429 rate limited → wait and retry with exponential backoff (up to 2 retries)
                            if (response.status === 429 && _retryCount < 2) {
                                var retryDelay = (Math.pow(2, _retryCount) * 2000) + Math.floor(Math.random() * 1000);
                                return new Promise(function(resolve) {
                                    setTimeout(resolve, retryDelay);
                                }).then(function() {
                                    return self.apiRequest(method, endpoint, data, _retryCount + 1);
                                });
                            }

                            // Auto-retry: session_expired → clear session, re-acquire, retry original request (once)
                            if (errorCode === 'session_expired' && _retryCount < 1) {
                                self.clearSession();
                                return self.apiRequest('GET', '/session').then(function(sessResp) {
                                    self.sessionId = sessResp.session_id;
                                    wpaicSsSet('wpaic_session', self.sessionId);
                                    if (sessResp.session_token) {
                                        wpaicLsSet('wpaic_session_token', sessResp.session_token);
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
            var leadSubmittedKey = 'wpaic_lead_submitted_' + this.sessionId;
            var leadSubmitted = wpaicSsGet(leadSubmittedKey);
            if (leadSubmitted) {
                this.leadSubmitted = true;
                return Promise.resolve();
            }

            // リードフォームが存在しない場合はスキップ
            if (!this.leadFormEl) {
                return Promise.resolve();
            }

            var url = this.config.api_base + '/lead-config';

            return fetch(url, {
                method: 'GET',
                headers: {
                    'X-WP-Nonce': this.config.nonce
                }
            })
            .then(function(response) {
                // レスポンスがJSONかチェック
                var contentType = response.headers.get('content-type');
                if (!contentType || !contentType.includes('application/json')) {
                    return null;
                }
                return response.json();
            })
            .then(function(data) {
                if (data && data.success && data.data && data.data.enabled) {
                    self.leadConfig = data.data;
                }
            })
            .catch(function(error) {
                // エラーは静かに処理（リードフォームなしで続行）
            });
        },

        /**
         * リードフォームのイベントをバインド
         */
        bindLeadFormEvents: function() {
            var self = this;

            if (!this.leadForm) return;

            // フォーム送信
            this.leadForm.addEventListener('submit', function(e) {
                e.preventDefault();
                self.submitLeadForm();
            });

            // スキップボタン
            var skipBtn = this.leadForm.querySelector('.lead-skip-btn');
            if (skipBtn) {
                skipBtn.addEventListener('click', function() {
                    self.hideLeadForm();
                    self.showChat();
                });
            }
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
         * リードフォームを表示
         */
        showLeadForm: function() {
            if (!this.leadConfig || !this.leadFormEl) return;

            var config = this.leadConfig;

            // タイトルと説明を設定
            var titleEl = this.leadFormEl.querySelector('.lead-form-title');
            var descEl = this.leadFormEl.querySelector('.lead-form-description');
            if (titleEl) titleEl.textContent = config.title || '';
            if (descEl) descEl.textContent = config.description || '';

            // フィールドを設定
            var fields = config.fields || {};
            var formEl = this.leadForm;
            var buttonsEl = formEl ? formEl.querySelector('.lead-form-buttons') : null;

            // Remove previously injected custom fields (re-show safety)
            var oldCustom = formEl ? formEl.querySelectorAll('.lead-field-custom') : [];
            for (var oc = 0; oc < oldCustom.length; oc++) {
                oldCustom[oc].parentNode.removeChild(oldCustom[oc]);
            }

            for (var fieldName in fields) {
                var fieldConfig = fields[fieldName];
                var fieldEl = this.leadFormEl.querySelector('.lead-field-' + fieldName);

                if (fieldEl) {
                    // Built-in field (name, email, phone, company)
                    fieldEl.hidden = false;
                    var label = fieldEl.querySelector('label');
                    var input = fieldEl.querySelector('input');

                    if (label && fieldConfig.label) {
                        label.textContent = fieldConfig.label;
                        if (fieldConfig.required) {
                            var reqSpan = document.createElement('span');
                            reqSpan.className = 'required';
                            reqSpan.textContent = '*';
                            label.appendChild(document.createTextNode(' '));
                            label.appendChild(reqSpan);
                        }
                    }
                    if (input) {
                        input.required = fieldConfig.required;
                    }
                } else if (fieldConfig.custom && formEl && buttonsEl) {
                    // Dynamic custom field
                    var cfDiv = document.createElement('div');
                    cfDiv.className = 'lead-field lead-field-custom';

                    var cfLabel = document.createElement('label');
                    cfLabel.setAttribute('for', 'lead-cf-' + fieldName);
                    cfLabel.textContent = fieldConfig.label || fieldName;
                    if (fieldConfig.required) {
                        var cfReq = document.createElement('span');
                        cfReq.className = 'required';
                        cfReq.textContent = '*';
                        cfLabel.appendChild(document.createTextNode(' '));
                        cfLabel.appendChild(cfReq);
                    }
                    cfDiv.appendChild(cfLabel);

                    var cfInput;
                    if (fieldConfig.type === 'textarea') {
                        cfInput = document.createElement('textarea');
                        cfInput.rows = 3;
                    } else if (fieldConfig.type === 'select') {
                        cfInput = document.createElement('select');
                        var defaultOpt = document.createElement('option');
                        defaultOpt.value = '';
                        defaultOpt.textContent = '— ' + (fieldConfig.label || fieldName) + ' —';
                        cfInput.appendChild(defaultOpt);
                        if (fieldConfig.options && fieldConfig.options.length) {
                            fieldConfig.options.forEach(function(opt) {
                                var optEl = document.createElement('option');
                                optEl.value = opt;
                                optEl.textContent = opt;
                                cfInput.appendChild(optEl);
                            });
                        }
                    } else {
                        cfInput = document.createElement('input');
                        cfInput.type = fieldConfig.type || 'text';
                    }
                    cfInput.id = 'lead-cf-' + fieldName;
                    cfInput.name = fieldName;
                    if (fieldConfig.required) {
                        cfInput.required = true;
                    }
                    cfDiv.appendChild(cfInput);

                    formEl.insertBefore(cfDiv, buttonsEl);
                }
            }

            // スキップボタンの表示/非表示
            var skipBtn = this.leadFormEl.querySelector('.lead-skip-btn');
            if (skipBtn) {
                skipBtn.hidden = config.required;
            }

            // フォームを表示、チャットを非表示
            this.leadFormEl.hidden = false;
            this.messagesEl.hidden = true;
            this.inputForm.hidden = true;
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
         * リードフォームを送信
         */
        submitLeadForm: function() {
            var self = this;

            if (!this.leadForm) return;

            var submitBtn = this.leadForm.querySelector('.lead-submit-btn');
            var errorEl = this.leadForm.querySelector('.lead-form-error');

            // エラーをクリア
            if (errorEl) {
                errorEl.hidden = true;
                errorEl.textContent = '';
            }

            // フォームデータを収集
            var formData = {
                session_id: this.sessionId,
                page_url: window.location.href,
                name: '',
                email: '',
                phone: '',
                company: ''
            };

            var nameInput = this.leadForm.querySelector('#lead-name');
            var emailInput = this.leadForm.querySelector('#lead-email');
            var phoneInput = this.leadForm.querySelector('#lead-phone');
            var companyInput = this.leadForm.querySelector('#lead-company');

            if (nameInput) formData.name = nameInput.value.trim();
            if (emailInput) formData.email = emailInput.value.trim();
            if (phoneInput) formData.phone = phoneInput.value.trim();
            if (companyInput) formData.company = companyInput.value.trim();

            // Collect custom field values
            var customFields = {};
            var cfEls = this.leadForm.querySelectorAll('.lead-field-custom');
            for (var ci = 0; ci < cfEls.length; ci++) {
                var cfInputEl = cfEls[ci].querySelector('input, textarea, select');
                if (cfInputEl && cfInputEl.name) {
                    customFields[cfInputEl.name] = (cfInputEl.value || '').trim();
                }
            }
            if (Object.keys(customFields).length > 0) {
                formData.custom_fields = customFields;
            }

            // バリデーション
            var hasError = false;
            this.leadForm.querySelectorAll('input[required], textarea[required], select[required]').forEach(function(input) {
                if (!input.value.trim()) {
                    input.classList.add('error');
                    hasError = true;
                } else {
                    input.classList.remove('error');
                }
            });

            // メールバリデーション
            if (emailInput && emailInput.value && !self.isValidEmail(emailInput.value)) {
                emailInput.classList.add('error');
                hasError = true;
            }

            if (hasError) {
                if (errorEl) {
                    errorEl.textContent = (self.config.strings && self.config.strings.required_fields) || 'Please fill in all required fields.';
                    errorEl.hidden = false;
                }
                return;
            }

            // 送信中状態
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.textContent = (self.config.strings && self.config.strings.sending) || 'Sending...';
            }

            var url = this.config.api_base + '/lead';

            // reCAPTCHAトークンを取得してから送信
            this.getRecaptchaToken('lead').then(function(token) {
                if (token) {
                    formData.recaptcha_token = token;
                } else if (self.config.recaptcha_enabled) {
                    // reCAPTCHA is configured but token is empty (script not loaded yet)
                    if (errorEl) {
                        errorEl.textContent = (self.config.strings && self.config.strings.recaptcha_loading) || 'Security verification loading. Please try again in a moment.';
                        errorEl.hidden = false;
                    }
                    if (submitBtn) {
                        submitBtn.disabled = false;
                        submitBtn.textContent = (self.config.strings && self.config.strings.start_chat) || 'Start chat';
                    }
                    return Promise.reject('recaptcha_not_ready');
                }

                return fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': self.config.nonce
                    },
                    body: JSON.stringify(formData)
                });
            })
            .then(function(response) {
                var contentType = response.headers.get('content-type');
                if (!contentType || !contentType.includes('application/json')) {
                    throw new Error((self.config.strings && self.config.strings.server_error) || 'A server error occurred.');
                }
                return response.json();
            })
            .then(function(data) {
                if (data.success) {
                    self.leadSubmitted = true;
                    wpaicSsSet('wpaic_lead_submitted_' + self.sessionId, 'true');
                    self.hideLeadForm();
                    self.showChat();
                } else {
                    var errMsg = data.error || (self.config.strings && self.config.strings.send_failed) || 'Failed to send.';
                    if (data.debug) {
                        console.error('Server error debug:', data.debug);
                    }
                    throw new Error(errMsg);
                }
            })
            .catch(function(error) {
                // Skip if already handled (e.g. recaptcha_not_ready)
                if (error === 'recaptcha_not_ready') return;
                console.error('Lead submit error:', error);
                if (errorEl) {
                    errorEl.textContent = error.message || (self.config.strings && self.config.strings.send_failed) || 'Failed to send.';
                    errorEl.hidden = false;
                }
            })
            .finally(function() {
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.textContent = (self.config.strings && self.config.strings.start_chat) || 'Start chat';
                }
            });
        },

        /**
         * メールバリデーション
         */
        /**
         * Setup voice input (STT) and text-to-speech (TTS)
         */
        setupVoiceInput: function() {
            var self = this;
            var SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;

            // Voice input (STT)
            this.micBtn = this.container.querySelector('.chatbot-mic-btn');
            this.recognition = null;
            this.isRecording = false;

            if (SpeechRecognition && this.micBtn && this.config.voice_input_enabled) {
                this.micBtn.hidden = false;

                this.recognition = new SpeechRecognition();
                this.recognition.continuous = false;
                this.recognition.interimResults = true;
                this.recognition.lang = this.config.tts_lang || document.documentElement.lang || 'ja';

                this.recognition.onresult = function(event) {
                    var transcript = '';
                    for (var i = event.resultIndex; i < event.results.length; i++) {
                        transcript += event.results[i][0].transcript;
                    }
                    if (self.inputTextarea) {
                        self.inputTextarea.value = transcript;
                        self.autoResize();
                    }
                };

                this.recognition.onend = function() {
                    self.stopRecording();
                    // Auto-send if we got text
                    if (self.inputTextarea && self.inputTextarea.value.trim()) {
                        self.inputForm.dispatchEvent(new Event('submit', { cancelable: true }));
                    }
                };

                this.recognition.onerror = function(event) {
                    self.stopRecording();
                    if (event.error !== 'aborted' && event.error !== 'no-speech') {
                        if (self.config.debug) {
                            console.warn('WPAIC Voice: SpeechRecognition error:', event.error);
                        }
                    }
                };

                this.micBtn.addEventListener('click', function() {
                    if (self.isRecording) {
                        self.recognition.stop();
                    } else {
                        self.startRecording();
                    }
                });
            }

            // TTS toggle
            this.ttsEnabled = this.config.tts_enabled && ('speechSynthesis' in window);
            this.ttsActive = false;
            this.ttsToggle = this.container.querySelector('.chatbot-tts-toggle');
            if (this.ttsEnabled && this.ttsToggle) {
                this.ttsToggle.hidden = false;
                this.ttsToggle.addEventListener('click', function() {
                    self.ttsActive = !self.ttsActive;
                    self.ttsToggle.classList.toggle('active', self.ttsActive);
                    if (!self.ttsActive) {
                        window.speechSynthesis.cancel();
                    }
                });
            }
        },

        startRecording: function() {
            if (!this.recognition) return;
            try {
                this.isRecording = true;
                this.micBtn.classList.add('recording');
                this.micBtn.querySelector('.chatbot-mic-icon').style.display = 'none';
                this.micBtn.querySelector('.chatbot-mic-stop-icon').style.display = '';
                if (this.inputTextarea) {
                    this.inputTextarea.value = '';
                    this.inputTextarea.placeholder = (this.config.strings && this.config.strings.listening) || 'Listening...';
                }
                this.recognition.start();
            } catch (e) {
                this.stopRecording();
            }
        },

        stopRecording: function() {
            this.isRecording = false;
            if (this.micBtn) {
                this.micBtn.classList.remove('recording');
                var micIcon = this.micBtn.querySelector('.chatbot-mic-icon');
                var stopIcon = this.micBtn.querySelector('.chatbot-mic-stop-icon');
                if (micIcon) micIcon.style.display = '';
                if (stopIcon) stopIcon.style.display = 'none';
            }
            if (this.inputTextarea) {
                this.inputTextarea.placeholder = (this.config.strings && this.config.strings.placeholder) || 'メッセージを入力...';
            }
        },

        /**
         * Speak text using browser TTS
         */
        speakText: function(text) {
            if (!this.ttsEnabled || !this.ttsActive) return;
            // Strip markdown/HTML for cleaner speech
            var clean = text.replace(/[#*_`~\[\]()>|\\-]/g, '').replace(/<[^>]*>/g, '').trim();
            if (!clean) return;
            // Limit to first 500 chars to avoid long speeches
            if (clean.length > 500) {
                clean = clean.substring(0, 500);
            }
            var utterance = new SpeechSynthesisUtterance(clean);
            utterance.lang = this.config.tts_lang || document.documentElement.lang || 'ja';
            window.speechSynthesis.cancel();
            window.speechSynthesis.speak(utterance);
        },

        /**
         * Initialize offline message form if outside business hours
         */
        /**
         * Initialize welcome screen (Pro)
         */
        initWelcomeScreen: function() {
            if (!this.config.is_pro || !this.config.welcome_screen_enabled) return;
            var self = this;
            var title = this.config.welcome_screen_title || '';
            var message = this.config.welcome_screen_message || '';
            var buttons = this.config.welcome_screen_buttons || [];
            if (!title && !message) return;

            this._welcomeShown = false;
            var origToggle = this.toggleChat.bind(this);
            this.toggleChat = function() {
                origToggle();
                if (self.isOpen && !self._welcomeShown) {
                    self._welcomeShown = true;
                    var welcomeEl = document.createElement('div');
                    welcomeEl.className = 'chatbot-welcome-screen';
                    if (title) {
                        var titleEl = document.createElement('div');
                        titleEl.className = 'chatbot-welcome-title';
                        titleEl.textContent = title;
                        welcomeEl.appendChild(titleEl);
                    }
                    if (message) {
                        var msgEl = document.createElement('div');
                        msgEl.className = 'chatbot-welcome-message';
                        msgEl.textContent = message;
                        welcomeEl.appendChild(msgEl);
                    }
                    if (buttons.length > 0) {
                        var btnsEl = document.createElement('div');
                        btnsEl.className = 'chatbot-welcome-buttons';
                        buttons.forEach(function(btn) {
                            var btnEl = document.createElement('button');
                            btnEl.type = 'button';
                            btnEl.className = 'chatbot-welcome-btn';
                            btnEl.textContent = btn;
                            btnEl.onclick = function() {
                                welcomeEl.remove();
                                self.inputTextarea.value = btn;
                                self.handleSubmit();
                            };
                            btnsEl.appendChild(btnEl);
                        });
                        welcomeEl.appendChild(btnsEl);
                    }
                    if (self.messagesEl && self.messagesEl.children.length === 0) {
                        self.messagesEl.appendChild(welcomeEl);
                    }
                }
            };
        },

        /**
         * Initialize fullscreen mode (Pro)
         */
        initFullscreenMode: function() {
            if (!this.config.is_pro || !this.config.fullscreen_mode) return;
            if (!this.window) return;

            var self = this;
            var fsBtn = document.createElement('button');
            fsBtn.type = 'button';
            fsBtn.className = 'chatbot-fullscreen-btn';
            fsBtn.innerHTML = '&#x26F6;';
            fsBtn.title = 'Fullscreen';
            fsBtn.onclick = function() {
                self.window.classList.toggle('chatbot-window--fullscreen');
                fsBtn.innerHTML = self.window.classList.contains('chatbot-window--fullscreen') ? '&#x2716;' : '&#x26F6;';
            };

            var header = this.window.querySelector('.chatbot-header');
            if (header) {
                header.style.position = 'relative';
                fsBtn.style.cssText = 'position: absolute; right: 40px; top: 50%; transform: translateY(-50%); background: none; border: none; color: inherit; font-size: 16px; cursor: pointer; opacity: 0.7;';
                header.appendChild(fsBtn);
            }
        },

        /**
         * Initialize response delay typing effect (Pro)
         */
        initResponseDelay: function() {
            if (!this.config.is_pro || !this.config.response_delay_enabled) return;
            this._responseDelayMs = this.config.response_delay_ms || 500;
        },

        /**
         * Initialize notification sound (Pro)
         */
        initNotificationSound: function() {
            if (!this.config.is_pro || !this.config.notification_sound_enabled) return;
            this._notifSoundEnabled = true;
        },

        /**
         * Play notification sound when bot message arrives
         */
        playNotificationSound: function() {
            if (!this._notifSoundEnabled) return;
            try {
                var ctx = new (window.AudioContext || window.webkitAudioContext)();
                var osc = ctx.createOscillator();
                var gain = ctx.createGain();
                osc.connect(gain);
                gain.connect(ctx.destination);
                osc.frequency.value = 880;
                osc.type = 'sine';
                gain.gain.value = 0.1;
                osc.start();
                gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.3);
                osc.stop(ctx.currentTime + 0.3);
            } catch (e) {
                // AudioContext not available
            }
        },

        initOfflineForm: function() {
            var config = window.wpAiChatbotConfig || {};
            var offline = config.offline_message;

            if (!offline || !offline.enabled) return;

            this.isOfflineMode = true;

            // Replace chat area with offline form (DOM API — no innerHTML)
            var messagesArea = this.container.querySelector('.wpaic-messages');
            if (!messagesArea) return;

            var self = this;

            // Helper: create element with attributes
            var el = function(tag, attrs, children) {
                var node = document.createElement(tag);
                if (attrs) {
                    for (var k in attrs) {
                        if (attrs.hasOwnProperty(k)) { node.setAttribute(k, attrs[k]); }
                    }
                }
                if (children) {
                    for (var i = 0; i < children.length; i++) {
                        if (typeof children[i] === 'string') {
                            node.appendChild(document.createTextNode(children[i]));
                        } else if (children[i]) {
                            node.appendChild(children[i]);
                        }
                    }
                }
                return node;
            };

            var form = el('form', { id: 'wpaic-offline-form' }, [
                el('div', { 'class': 'wpaic-offline-field' }, [
                    el('input', { type: 'text', name: 'name', placeholder: 'Name', 'class': 'wpaic-offline-input' })
                ]),
                el('div', { 'class': 'wpaic-offline-field' }, [
                    el('input', { type: 'email', name: 'email', placeholder: 'Email *', required: '', 'class': 'wpaic-offline-input' })
                ]),
                el('div', { 'class': 'wpaic-offline-field' }, [
                    el('textarea', { name: 'message', placeholder: 'Message *', required: '', rows: '4', 'class': 'wpaic-offline-input' })
                ]),
                // Honeypot field: hidden from real users, bots auto-fill it
                el('div', { style: 'position:absolute;left:-9999px;top:-9999px;', 'aria-hidden': 'true', tabindex: '-1' }, [
                    el('input', { type: 'text', name: 'wpaic_hp', autocomplete: 'off', tabindex: '-1' })
                ]),
                // Timing field: records when form was rendered
                el('input', { type: 'hidden', name: '_ts', value: String(Math.floor(Date.now() / 1000)) }),
                el('button', { type: 'submit', 'class': 'wpaic-offline-submit' }, ['Send Message']),
                el('div', { 'class': 'wpaic-offline-status' })
            ]);

            var wrapper = el('div', { 'class': 'wpaic-offline-form' }, [
                el('div', { 'class': 'wpaic-offline-header' }, [
                    el('h3', null, [offline.title || '']),
                    el('p', null, [offline.description || ''])
                ]),
                form
            ]);

            messagesArea.innerHTML = '';
            messagesArea.appendChild(wrapper);

            // Hide input area
            var inputArea = this.container.querySelector('.wpaic-input-area');
            if (inputArea) inputArea.style.display = 'none';

            // Bind form submit
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                self.submitOfflineForm(form);
            });
        },

        /**
         * Submit offline message form
         */
        /** Timer ID for _dropped retry hint — cleared on re-submit to prevent stale overwrite. */
        _droppedTimer: null,

        submitOfflineForm: function(form) {
            var self = this;
            var config = window.wpAiChatbotConfig || {};
            var statusEl = form.querySelector('.wpaic-offline-status');
            var submitBtn = form.querySelector('.wpaic-offline-submit');

            // Cancel any pending _dropped retry timer from a previous submission.
            if (self._droppedTimer) {
                clearTimeout(self._droppedTimer);
                self._droppedTimer = null;
            }

            var name = form.querySelector('[name="name"]').value;
            var email = form.querySelector('[name="email"]').value;
            var message = form.querySelector('[name="message"]').value;

            if (!email || !message) return;

            // Prevent duplicate submission of identical content within 30s.
            // Uses sessionStorage so the guard survives page reloads within the same tab.
            var dedupKey = email + '|' + message;
            try {
                var lastDedup = JSON.parse(sessionStorage.getItem('wpaic_offline_dedup') || '{}');
                if (lastDedup.k === dedupKey && lastDedup.t && (Date.now() - lastDedup.t) < 30000) {
                    return;
                }
            } catch (e) { /* sessionStorage unavailable — skip dedup */ }

            submitBtn.disabled = true;
            var _s = self.config.strings || {};
            statusEl.textContent = _s.sending || 'Sending...';
            statusEl.className = 'wpaic-offline-status';

            // Honeypot (wpaic_hp) and timing (_ts) for bot detection
            var honeypot = form.querySelector('[name="wpaic_hp"]');
            var tsField = form.querySelector('[name="_ts"]');

            // Ensure _ts is set even if form was rendered late (JS optimization/deferred load)
            var tsValue = tsField ? parseInt(tsField.value, 10) : 0;
            if (!tsValue) {
                tsValue = Math.floor(Date.now() / 1000);
            }

            var requestBody = {
                name: name,
                email: email,
                message: message,
                page_url: window.location.href,
                wpaic_hp: honeypot ? honeypot.value : '',
                _ts: tsValue
            };

            // reCAPTCHAトークンを取得してから送信
            this.getRecaptchaToken('offline').then(function(token) {
                if (token) {
                    requestBody.recaptcha_token = token;
                } else if (config.recaptchaEnabled) {
                    // reCAPTCHA is configured but token is empty (script not loaded yet)
                    statusEl.textContent = _s.recaptcha_loading || 'Security verification loading. Please try again in a moment.';
                    statusEl.className = 'wpaic-offline-status wpaic-offline-error';
                    submitBtn.disabled = false;
                    return Promise.reject('recaptcha_not_ready');
                }

                return fetch(config.restUrl + 'offline-message', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': config.nonce
                    },
                    body: JSON.stringify(requestBody)
                });
            })
            .then(function(response) { return response.json(); })
            .then(function(data) {
                if (data.success && data._dropped) {
                    // Request was silently dropped by server-side validation.
                    // Show a generic processing message, then a retry hint after delay.
                    // No specific reason is revealed to avoid giving attackers clues.
                    statusEl.textContent = _s.processing || 'Processing...';
                    statusEl.className = 'wpaic-offline-status';
                    self._droppedTimer = setTimeout(function() {
                        self._droppedTimer = null;
                        statusEl.textContent = _s.offline_reload_request || 'Could not complete the request. Please reload the page and try again.';
                        statusEl.className = 'wpaic-offline-status wpaic-offline-error';
                    }, 3000);
                } else if (data.success) {
                    try { sessionStorage.setItem('wpaic_offline_dedup', JSON.stringify({ k: dedupKey, t: Date.now() })); } catch (e) { /* ignore */ }
                    statusEl.textContent = data.data.message || _s.message_sent || 'Message sent!';
                    statusEl.className = 'wpaic-offline-status wpaic-offline-success';
                    form.reset();
                } else {
                    statusEl.textContent = data.error || _s.send_failed || 'Failed to send.';
                    statusEl.className = 'wpaic-offline-status wpaic-offline-error';
                }
            })
            .catch(function(err) {
                // Skip if already handled (e.g. recaptcha_not_ready)
                if (err === 'recaptcha_not_ready') return;
                statusEl.textContent = _s.send_failed_retry || 'Failed to send. Please try again.';
                statusEl.className = 'wpaic-offline-status wpaic-offline-error';
            })
            .finally(function() {
                submitBtn.disabled = false;
            });
        },

        /**
         * Generate a unique request ID for deduplication.
         */
        generateRequestId: function() {
            return Date.now().toString(36) + '_' + Math.random().toString(36).substr(2, 9);
        },

        /**
         * Escape HTML entities
         */
        escapeHtml: function(text) {
            var div = document.createElement('div');
            div.appendChild(document.createTextNode(text));
            return div.innerHTML;
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
                if (!wpaicStorageAllowed()) {
                    // Consent revoked — remove ALL wpaic_ keys from both storages.
                    // Walk all keys to catch conversion markers from old sessions, etc.
                    try {
                        var i;
                        var lsKeys = Object.keys(localStorage);
                        for (i = 0; i < lsKeys.length; i++) {
                            if (lsKeys[i].indexOf('wpaic_') === 0) {
                                localStorage.removeItem(lsKeys[i]);
                            }
                        }
                        var ssKeys = Object.keys(sessionStorage);
                        for (i = 0; i < ssKeys.length; i++) {
                            if (ssKeys[i].indexOf('wpaic_') === 0) {
                                sessionStorage.removeItem(ssKeys[i]);
                            }
                        }
                    } catch (e) { /* private mode or storage unavailable */ }
                }
                // Re-evaluate conversion tracking
                self.initConversionTracking();
            });
        },

        /**
         * Initialize conversion tracking
         */
        conversionTrackingInitialized: false,

        initConversionTracking: function() {
            var config = window.wpAiChatbotConfig || {};
            if (!config.conversion_tracking || !config.conversion_goals || !config.conversion_goals.length) {
                return;
            }
            // WP Consent API: conversion tracking requires statistics or marketing consent
            if (!wpaicHasConsent('statistics') && !wpaicHasConsent('marketing')) {
                return;
            }

            var self = this;
            var goals = config.conversion_goals;

            // Check current URL against goals on page load (for SPA or redirect-back scenarios)
            this.checkConversionGoals(goals);

            // Only install History API hooks once to prevent accumulation on consent changes
            if (this.conversionTrackingInitialized) {
                return;
            }
            this.conversionTrackingInitialized = true;

            // Monitor navigation via History API
            var originalPushState = history.pushState;
            history.pushState = function() {
                originalPushState.apply(this, arguments);
                // Re-check consent at call time (may have been revoked since hook was installed)
                if (wpaicHasConsent('statistics') || wpaicHasConsent('marketing')) {
                    self.checkConversionGoals(goals);
                }
            };

            var originalReplaceState = history.replaceState;
            history.replaceState = function() {
                originalReplaceState.apply(this, arguments);
                if (wpaicHasConsent('statistics') || wpaicHasConsent('marketing')) {
                    self.checkConversionGoals(goals);
                }
            };

            window.addEventListener('popstate', function() {
                if (wpaicHasConsent('statistics') || wpaicHasConsent('marketing')) {
                    self.checkConversionGoals(goals);
                }
            });
        },

        /**
         * Check current URL against conversion goals
         */
        checkConversionGoals: function(goals) {
            var currentUrl = window.location.href;
            var sessionId = this.sessionId;

            if (!sessionId) return;

            // Check if already tracked this session
            var trackingKey = 'wpaic_converted_' + sessionId;
            if (wpaicSsGet(trackingKey)) return;

            for (var i = 0; i < goals.length; i++) {
                var goal = goals[i];
                if (!goal.url_pattern) continue;

                try {
                    var regex = new RegExp(goal.url_pattern);
                    if (regex.test(currentUrl)) {
                        this.trackConversion(sessionId, goal.name || '');
                        wpaicSsSet(trackingKey, '1');
                        break;
                    }
                } catch (e) {
                    // Invalid regex pattern - try simple contains match
                    if (currentUrl.indexOf(goal.url_pattern) !== -1) {
                        this.trackConversion(sessionId, goal.name || '');
                        wpaicSsSet(trackingKey, '1');
                        break;
                    }
                }
            }
        },

        /**
         * Send conversion tracking API call
         */
        trackConversion: function(sessionId, goalName) {
            var config = window.wpAiChatbotConfig || {};
            fetch(config.restUrl + 'conversion', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': config.nonce
                },
                body: JSON.stringify({
                    session_id: sessionId,
                    goal: goalName
                })
            }).catch(function() {
                // Silently fail
            });
        },

        isValidEmail: function(email) {
            var re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return re.test(email);
        }
    };

    // DOM準備完了後に初期化
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            WPAIChatbot.init();
        });
    } else {
        WPAIChatbot.init();
    }

})();

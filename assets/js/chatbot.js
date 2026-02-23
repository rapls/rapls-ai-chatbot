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
        userId: null,
        isOpen: false,
        isLoading: false,
        isInitialized: false,
        isResizing: false,
        leadConfig: null,
        leadSubmitted: false,
        historyLoaded: false,
        sessionLoading: false,
        navType: 'navigate',
        selectedImage: null,
        selectedImageData: null,

        // 設定
        config: window.wpAiChatbotConfig || {},

        /**
         * 初期化
         */
        init: function() {
            this.cacheElements();
            if (!this.container) return;

            this.createResizeHandle();
            this.bindEvents();
            this.initUserId();  // コンテキスト記憶用のユーザーID
            this.loadSession();  // loadLeadConfigはloadSession内で呼ばれる
            this.loadWindowSize();
            this.setupAutocomplete();
            this.setupImageUpload();
            this.bindLeadFormEvents();
            this.initOfflineForm();
            this.initConversionTracking();
            this.listenForConsentChange();
            this.isInitialized = true;
        },

        /**
         * ユーザーIDを初期化（コンテキスト記憶用）
         * localStorageに保存してブラウザセッション間で永続化
         * WP Consent API: functional/preferences 同意がない場合はセッション内のみのIDを使用
         */
        initUserId: function() {
            var storedUserId = wpaicLsGet('wpaic_user_id');
            if (storedUserId) {
                this.userId = storedUserId;
            } else {
                // 新しいユーザーIDを生成
                this.userId = 'user_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
                wpaicLsSet('wpaic_user_id', this.userId);
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
            this.resizeHandle = document.createElement('div');
            this.resizeHandle.className = 'chatbot-resize-handle';
            this.resizeHandle.setAttribute('aria-label', 'ウィンドウをリサイズ');
            this.window.appendChild(this.resizeHandle);
        },

        /**
         * 保存されたウィンドウサイズを読み込み
         */
        loadWindowSize: function() {
            var savedSize = wpaicLsGet('wpaic_window_size');
            if (savedSize) {
                try {
                    var size = JSON.parse(savedSize);
                    if (size.width && size.height) {
                        this.window.style.width = size.width + 'px';
                        this.window.style.height = size.height + 'px';
                    }
                } catch (e) {
                    console.error('Failed to load window size:', e);
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

            // バッジクリック → 開く
            this.badge.addEventListener('click', function() {
                self.open();
            });

            // 閉じるボタン
            this.container.querySelector('.chatbot-close').addEventListener('click', function() {
                self.close();
            });

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

            // ESCキーで閉じる
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && self.isOpen) {
                    self.close();
                }
            });

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

            // ナビゲーションタイプをチェック
            // 'reload' = ページリロード（履歴を表示）
            // 'navigate' = 新規ナビゲーション（ウェルカムメッセージを表示）
            this.navType = 'navigate';
            try {
                var navEntries = performance.getEntriesByType('navigation');
                if (navEntries && navEntries.length > 0) {
                    this.navType = navEntries[0].type;
                }
            } catch (e) {
                // Navigation API not supported
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
                        wpaicLsSet('wpaic_session', self.sessionId);
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
                        console.error('Session creation failed:', error);
                        finishLoading();
                    });
            } else {
                // 既存セッションの場合
                this.sessionLoading = true;

                // リロード時のみ履歴を読み込む（新規ナビゲーション時はウェルカムメッセージから開始）
                var loadHistoryPromise = (this.navType === 'reload')
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
                        console.error('Failed to load session data:', error);
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

                        // 履歴からメッセージを復元
                        response.messages.forEach(function(msg) {
                            var role = msg.role === 'assistant' ? 'bot' : msg.role;
                            self.addMessage(role, msg.content, null, msg.id);
                        });

                        self.historyLoaded = true;
                    }
                })
                .catch(function(error) {
                    console.error('Failed to load history:', error);
                });
        },

        /**
         * Show welcome message
         */
        showWelcomeMessage: function() {
            var welcomeMsg = this.config.welcome_message || 'Hello! How can I help you today?';
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
                    var errorMessage = error.message || '申し訳ありません。エラーが発生しました。もう一度お試しください。';
                    self.addMessage('bot', errorMessage);
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
                        self.addMessage('bot', 'Security verification loading. Please try again in a moment.');
                        return Promise.reject('recaptcha_not_ready');
                    }

                    var requestData = {
                        session_id: self.sessionId,
                        user_id: self.userId,
                        message: message,
                        page_url: window.location.href,
                        recaptcha_token: token,
                        client_request_id: self.generateRequestId()
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
                        if (response.data && response.data._truncated) {
                            self.addMessage('bot', self.config.strings.dedup_truncated || 'Your message was received and processed. Please reload the page to see the response.');
                        } else {
                            self.addMessage('bot', response.data.content, response.data.sources, response.data.message_id, response.data.sentiment);
                            // Fetch related question suggestions (Pro)
                            self.fetchSuggestions();
                            // Save context for memory (Pro) - async, don't wait
                            self.saveContext();
                        }
                    } else {
                        self.addMessage('bot', response.error || 'エラーが発生しました。');
                    }
                });
        },

        /**
         * コンテキストを保存（Pro: コンテキスト記憶用）
         */
        saveContext: function() {
            var self = this;

            // user_idとsession_idが必要
            if (!this.userId || !this.sessionId) {
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
                    user_id: this.userId,
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
        addMessage: function(role, content, sources, messageId, sentiment) {
            var self = this;
            var messageEl = document.createElement('div');
            messageEl.className = 'chatbot-message chatbot-message--' + role;
            if (messageId) {
                messageEl.setAttribute('data-message-id', messageId);
            }

            // Add avatar for bot messages
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

            }

            var contentEl = document.createElement('div');
            contentEl.className = 'chatbot-message__content';

            // Add sentiment indicator (Pro feature) - small colored dot
            if (role === 'bot' && sentiment && sentiment !== 'neutral') {
                var sentimentEl = document.createElement('span');
                sentimentEl.className = 'chatbot-sentiment chatbot-sentiment--' + sentiment;
                var sentimentTitles = {
                    'frustrated': 'Frustrated',
                    'confused': 'Confused',
                    'urgent': 'Urgent',
                    'positive': 'Positive',
                    'negative': 'Negative'
                };
                sentimentEl.title = sentimentTitles[sentiment] || sentiment;
                contentEl.appendChild(sentimentEl);
                // Add text after sentiment indicator
                var textNode = document.createTextNode(content);
                contentEl.appendChild(textNode);
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
                titleEl.textContent = '📄 参考ページ:';
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
                    thumbsUp.title = 'Good response';
                    thumbsUp.onclick = function() { self.sendFeedback(messageId, 1, this); };

                    var thumbsDown = document.createElement('button');
                    thumbsDown.type = 'button';
                    thumbsDown.className = 'chatbot-feedback-btn chatbot-feedback-btn--down';
                    thumbsDown.innerHTML = '👎';
                    thumbsDown.title = 'Bad response';
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
                    regenerateBtn.title = 'Regenerate response';
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

                    // Create new content
                    contentEl.textContent = data.data.content;

                    // Re-add sources if any
                    if (data.data.sources && data.data.sources.length > 0) {
                        var sourcesEl = document.createElement('div');
                        sourcesEl.className = 'chatbot-message__sources';

                        var titleEl = document.createElement('div');
                        titleEl.className = 'chatbot-message__sources-title';
                        titleEl.textContent = '📄 参考ページ:';
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
            titleEl.textContent = 'こちらも聞いてみませんか？';
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
                alert('画像サイズが大きすぎます。' + (maxSize / 1024) + 'KB以下の画像を選択してください。');
                this.imageInput.value = '';
                return;
            }

            // 画像タイプチェック
            var allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            if (allowedTypes.indexOf(file.type) === -1) {
                alert('対応していない画像形式です。JPEG、PNG、GIF、WebPのいずれかを選択してください。');
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
                            // サーバーからのエラーメッセージがあればそれを使う
                            var error = new Error(json.error || 'API error: ' + response.status);
                            error.response = json;
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
            for (var fieldName in fields) {
                var fieldConfig = fields[fieldName];
                var fieldEl = this.leadFormEl.querySelector('.lead-field-' + fieldName);
                if (fieldEl) {
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

            // バリデーション
            var hasError = false;
            this.leadForm.querySelectorAll('input[required]').forEach(function(input) {
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
                    errorEl.textContent = '必須項目を入力してください。';
                    errorEl.hidden = false;
                }
                return;
            }

            // 送信中状態
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.textContent = '送信中...';
            }

            var url = this.config.api_base + '/lead';

            // reCAPTCHAトークンを取得してから送信
            this.getRecaptchaToken('lead').then(function(token) {
                if (token) {
                    formData.recaptcha_token = token;
                } else if (self.config.recaptcha_enabled) {
                    // reCAPTCHA is configured but token is empty (script not loaded yet)
                    if (errorEl) {
                        errorEl.textContent = 'Security verification loading. Please try again in a moment.';
                        errorEl.hidden = false;
                    }
                    if (submitBtn) {
                        submitBtn.disabled = false;
                        submitBtn.textContent = 'チャットを開始';
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
                    throw new Error('サーバーエラーが発生しました。');
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
                    var errMsg = data.error || '送信に失敗しました。';
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
                    errorEl.textContent = error.message || '送信に失敗しました。';
                    errorEl.hidden = false;
                }
            })
            .finally(function() {
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'チャットを開始';
                }
            });
        },

        /**
         * メールバリデーション
         */
        /**
         * Initialize offline message form if outside business hours
         */
        initOfflineForm: function() {
            var config = window.wpAiChatbotConfig || {};
            var offline = config.offline_message;

            if (!offline || !offline.enabled) return;

            this.isOfflineMode = true;

            // Replace chat area with offline form
            var messagesArea = this.container.querySelector('.wpaic-messages');
            if (!messagesArea) return;

            var formHtml = '<div class="wpaic-offline-form">' +
                '<div class="wpaic-offline-header">' +
                '<h3>' + this.escapeHtml(offline.title) + '</h3>' +
                '<p>' + this.escapeHtml(offline.description) + '</p>' +
                '</div>' +
                '<form id="wpaic-offline-form">' +
                '<div class="wpaic-offline-field">' +
                '<input type="text" name="name" placeholder="' + this.escapeHtml('Name') + '" class="wpaic-offline-input">' +
                '</div>' +
                '<div class="wpaic-offline-field">' +
                '<input type="email" name="email" placeholder="' + this.escapeHtml('Email *') + '" required class="wpaic-offline-input">' +
                '</div>' +
                '<div class="wpaic-offline-field">' +
                '<textarea name="message" placeholder="' + this.escapeHtml('Message *') + '" required rows="4" class="wpaic-offline-input"></textarea>' +
                '</div>' +
                // Honeypot field: hidden from real users, bots auto-fill it
                '<div style="position:absolute;left:-9999px;top:-9999px;" aria-hidden="true" tabindex="-1">' +
                '<input type="text" name="wpaic_hp" autocomplete="off" tabindex="-1">' +
                '</div>' +
                // Timing field: records when form was rendered
                '<input type="hidden" name="_ts" value="' + Math.floor(Date.now() / 1000) + '">' +
                '<button type="submit" class="wpaic-offline-submit">' + this.escapeHtml('Send Message') + '</button>' +
                '<div class="wpaic-offline-status"></div>' +
                '</form>' +
                '</div>';

            messagesArea.innerHTML = formHtml;

            // Hide input area
            var inputArea = this.container.querySelector('.wpaic-input-area');
            if (inputArea) inputArea.style.display = 'none';

            // Bind form submit
            var self = this;
            var form = document.getElementById('wpaic-offline-form');
            if (form) {
                form.addEventListener('submit', function(e) {
                    e.preventDefault();
                    self.submitOfflineForm(form);
                });
            }
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

            submitBtn.disabled = true;
            statusEl.textContent = 'Sending...';
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
                    statusEl.textContent = 'Security verification loading. Please try again in a moment.';
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
                    statusEl.textContent = 'Processing...';
                    statusEl.className = 'wpaic-offline-status';
                    self._droppedTimer = setTimeout(function() {
                        self._droppedTimer = null;
                        statusEl.textContent = 'Could not complete the request. Please reload the page and try again.';
                        statusEl.className = 'wpaic-offline-status wpaic-offline-error';
                    }, 3000);
                } else if (data.success) {
                    statusEl.textContent = data.data.message || 'Message sent!';
                    statusEl.className = 'wpaic-offline-status wpaic-offline-success';
                    form.reset();
                } else {
                    statusEl.textContent = data.error || 'Failed to send.';
                    statusEl.className = 'wpaic-offline-status wpaic-offline-error';
                }
            })
            .catch(function(err) {
                // Skip if already handled (e.g. recaptcha_not_ready)
                if (err === 'recaptcha_not_ready') return;
                statusEl.textContent = 'Failed to send. Please try again.';
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
         * Listen for WP Consent API consent changes.
         * Re-initialize consent-gated features when user updates preferences.
         */
        listenForConsentChange: function() {
            var self = this;
            document.addEventListener('wp_listen_for_consent_change', function() {
                if (wpaicStorageAllowed()) {
                    // Consent granted — persist ephemeral userId if we have one
                    if (self.userId && !wpaicLsGet('wpaic_user_id')) {
                        wpaicLsSet('wpaic_user_id', self.userId);
                    }
                } else {
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

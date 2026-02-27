/**
 * Rapls AI Chatbot — Cross-Site Embed Loader
 *
 * Usage:
 * <script src="https://yoursite.com/wp-content/plugins/rapls-ai-chatbot/assets/js/embed-loader.js"
 *         data-site="https://yoursite.com"
 *         data-color="#007bff"
 *         data-position="right"
 *         data-label="Chat"
 *         async></script>
 */
(function () {
    'use strict';

    // Find our own script tag to read data attributes
    var scripts = document.getElementsByTagName('script');
    var script = scripts[scripts.length - 1];
    // Fallback: try to find by src if currentScript not reliable
    if (document.currentScript) {
        script = document.currentScript;
    }

    var siteUrl = (script.getAttribute('data-site') || '').replace(/\/+$/, '');
    if (!siteUrl) {
        // Try to infer from script src
        var src = script.getAttribute('src') || '';
        var match = src.match(/^(https?:\/\/[^/]+)/);
        if (match) {
            siteUrl = match[1];
        }
    }
    if (!siteUrl) return;

    var color = script.getAttribute('data-color') || '#007bff';
    var position = script.getAttribute('data-position') || 'right';
    var label = script.getAttribute('data-label') || '';
    var size = script.getAttribute('data-size') || '60';

    var isOpen = false;
    var iframe = null;
    var iframeReady = false;

    // Prefix for all our elements
    var PREFIX = 'wpaic-embed-';

    // --- Badge Button ---
    var badge = document.createElement('button');
    badge.id = PREFIX + 'badge';
    badge.setAttribute('aria-label', label || 'Chat');

    var badgeSize = parseInt(size, 10) || 60;
    var positionCSS = position === 'left' ? 'left:20px;right:auto;' : 'right:20px;left:auto;';

    badge.style.cssText = 'position:fixed;bottom:20px;' + positionCSS +
        'width:' + badgeSize + 'px;height:' + badgeSize + 'px;border-radius:50%;' +
        'background:' + color + ';color:#fff;border:none;cursor:pointer;' +
        'box-shadow:0 4px 12px rgba(0,0,0,.15);z-index:999998;' +
        'display:flex;align-items:center;justify-content:center;' +
        'transition:transform .2s,box-shadow .2s;padding:0;';

    // Chat icon SVG
    badge.innerHTML = '<svg width="28" height="28" viewBox="0 0 24 24" fill="currentColor" style="flex-shrink:0">' +
        '<path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm0 14H6l-2 2V4h16v12z"/>' +
        '<circle cx="8" cy="10" r="1.5"/><circle cx="12" cy="10" r="1.5"/><circle cx="16" cy="10" r="1.5"/></svg>';

    if (label) {
        badge.style.borderRadius = '28px';
        badge.style.width = 'auto';
        badge.style.padding = '0 20px';
        badge.innerHTML += '<span style="margin-left:8px;font-size:14px;font-weight:600;white-space:nowrap">' +
            label.replace(/</g, '&lt;') + '</span>';
    }

    badge.addEventListener('mouseenter', function () {
        badge.style.transform = 'scale(1.05)';
        badge.style.boxShadow = '0 6px 20px rgba(0,0,0,.2)';
    });
    badge.addEventListener('mouseleave', function () {
        badge.style.transform = 'scale(1)';
        badge.style.boxShadow = '0 4px 12px rgba(0,0,0,.15)';
    });

    // --- Iframe Container ---
    var container = document.createElement('div');
    container.id = PREFIX + 'container';
    container.style.cssText = 'position:fixed;bottom:90px;' + positionCSS +
        'width:400px;height:600px;max-width:calc(100vw - 20px);max-height:calc(100vh - 110px);' +
        'z-index:999999;border-radius:12px;overflow:hidden;' +
        'box-shadow:0 8px 32px rgba(0,0,0,.15);display:none;' +
        'transition:opacity .2s,transform .2s;opacity:0;transform:translateY(10px);' +
        'background:#fff;';

    // --- Functions ---
    function createIframe() {
        if (iframe) return;
        iframe = document.createElement('iframe');
        iframe.src = siteUrl + '/?wpaic_embed=1';
        iframe.style.cssText = 'width:100%;height:100%;border:none;';
        iframe.setAttribute('allow', 'clipboard-write');
        iframe.setAttribute('title', label || 'Chat');
        container.appendChild(iframe);
    }

    function openChat() {
        if (isOpen) return;
        isOpen = true;
        createIframe();
        container.style.display = 'block';
        // Force reflow for transition
        container.offsetHeight; // eslint-disable-line no-unused-expressions
        container.style.opacity = '1';
        container.style.transform = 'translateY(0)';
        badge.style.display = 'none';
    }

    function closeChat() {
        if (!isOpen) return;
        isOpen = false;
        container.style.opacity = '0';
        container.style.transform = 'translateY(10px)';
        setTimeout(function () {
            container.style.display = 'none';
        }, 200);
        badge.style.display = 'flex';
    }

    function toggleChat() {
        if (isOpen) {
            closeChat();
        } else {
            openChat();
        }
    }

    // --- Event Listeners ---
    badge.addEventListener('click', toggleChat);

    // ESC key to close
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && isOpen) {
            closeChat();
        }
    });

    // postMessage from iframe
    window.addEventListener('message', function (e) {
        if (!e.data || typeof e.data !== 'object') return;

        // Verify origin matches our site
        var expectedOrigin = siteUrl.replace(/\/+$/, '');
        if (e.origin !== expectedOrigin) return;

        if (e.data.type === 'wpaic:close') {
            closeChat();
        }
        if (e.data.type === 'wpaic:ready') {
            iframeReady = true;
        }
    });

    // --- Mobile: Full-screen overlay ---
    function applyMobileStyles() {
        var isMobile = window.innerWidth <= 768;
        if (isMobile) {
            container.style.cssText = 'position:fixed;top:0;left:0;right:0;bottom:0;' +
                'width:100%;height:100%;max-width:none;max-height:none;' +
                'z-index:999999;border-radius:0;overflow:hidden;' +
                'box-shadow:none;display:none;opacity:0;transform:translateY(10px);' +
                'transition:opacity .2s,transform .2s;background:#fff;';
        } else {
            container.style.cssText = 'position:fixed;bottom:90px;' + positionCSS +
                'width:400px;height:600px;max-width:calc(100vw - 20px);max-height:calc(100vh - 110px);' +
                'z-index:999999;border-radius:12px;overflow:hidden;' +
                'box-shadow:0 8px 32px rgba(0,0,0,.15);display:none;' +
                'transition:opacity .2s,transform .2s;opacity:0;transform:translateY(10px);' +
                'background:#fff;';
        }
        // Re-apply open state if chat is open
        if (isOpen) {
            container.style.display = 'block';
            container.style.opacity = '1';
            container.style.transform = 'translateY(0)';
        }
    }

    var resizeTimer;
    window.addEventListener('resize', function () {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(applyMobileStyles, 150);
    });

    // --- Insert into page ---
    function init() {
        document.body.appendChild(badge);
        document.body.appendChild(container);
        applyMobileStyles();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();

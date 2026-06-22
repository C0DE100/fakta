/* ============================================================
   CSRF — attach the session token to every state-changing request.
   Loaded on every page (nav.php + admin_header.php) before other
   scripts. Reads the token from window.FAKTA_CSRF.
   ============================================================ */
(function () {
    'use strict';
    function token() { return window.FAKTA_CSRF || ''; }

    // 1) Patch window.fetch — covers the document/template/client APIs.
    if (window.fetch) {
        var _fetch = window.fetch;
        window.fetch = function (input, init) {
            init = init || {};
            var method = (init.method ||
                (input && typeof input === 'object' ? input.method : '') || 'GET').toUpperCase();
            if (method !== 'GET' && method !== 'HEAD') {
                var h = new Headers(init.headers ||
                    (input && typeof input === 'object' ? input.headers : undefined) || {});
                if (!h.has('X-CSRF-Token')) h.set('X-CSRF-Token', token());
                init.headers = h;
            }
            return _fetch.call(this, input, init);
        };
    }

    // 2) Patch jQuery ajax — admin pages + client profile use $.post / $.ajax.
    function setupJq() {
        if (!window.jQuery) return false;
        window.jQuery.ajaxSetup({
            beforeSend: function (xhr, settings) {
                var m = (settings.type || settings.method || 'GET').toUpperCase();
                if (m !== 'GET' && m !== 'HEAD') xhr.setRequestHeader('X-CSRF-Token', token());
            }
        });
        return true;
    }
    if (!setupJq()) {
        // jQuery may load after this script — wire it up once the DOM is ready.
        if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', setupJq);
        else setupJq();
    }
}());

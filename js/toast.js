/* ============================================================
   Global toast + confirm dialog
   ------------------------------------------------------------
   window.toast(message, type, opts)
       type: 'success' | 'error' | 'info' (default 'info')
       opts: { duration,
               link:   { text, href },          // trailing navigation link
               action: { text, onClick } }      // trailing button that runs a callback (e.g. Врати)
   window.confirmDialog({ title, message, confirmText, cancelText,
                          danger, onConfirm, onCancel })
   Loaded on every page (via includes/nav.php), before draft-workspace.js.
   ============================================================ */
(function () {
    'use strict';

    var ICONS = {
        success: '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>',
        error:   '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="m15 9-6 6"/><path d="m9 9 6 6"/></svg>',
        info:    '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg>'
    };

    function ensureHost() {
        var h = document.getElementById('fakta-toasts');
        if (!h) {
            h = document.createElement('div');
            h.id = 'fakta-toasts';
            h.className = 'toast-host';
            document.body.appendChild(h);
        }
        return h;
    }

    function toast(message, type, opts) {
        opts = opts || {};
        type = type || 'info';
        var host = ensureHost();
        var el = document.createElement('div');
        el.className = 'toast toast--' + type;
        el.innerHTML = '<span class="toast-ico">' + (ICONS[type] || ICONS.info) + '</span><span class="toast-msg"></span>';
        var msgEl = el.querySelector('.toast-msg');
        msgEl.textContent = message;
        // Optional trailing clickable link: opts.link = { text, href }
        if (opts.link && opts.link.href) {
            msgEl.appendChild(document.createTextNode(' '));
            var a = document.createElement('a');
            a.className = 'toast-link';
            a.href = opts.link.href;
            a.textContent = opts.link.text || 'отвори';
            a.addEventListener('click', function (e) { e.stopPropagation(); }); // don't dismiss before navigating
            msgEl.appendChild(a);
        }
        // Optional trailing action button that runs a callback (e.g. undo).
        if (opts.action && typeof opts.action.onClick === 'function') {
            msgEl.appendChild(document.createTextNode(' '));
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'toast-link toast-action';
            btn.textContent = opts.action.text || 'Врати';
            btn.addEventListener('click', function (e) {
                e.stopPropagation();
                opts.action.onClick();
                remove();
            });
            msgEl.appendChild(btn);
        }
        host.appendChild(el);
        requestAnimationFrame(function () { el.classList.add('toast--in'); });

        var ttl = opts.duration || ((opts.link || opts.action) ? 7000 : (type === 'error' ? 5000 : 3000));
        var timer = setTimeout(remove, ttl);
        function remove() {
            clearTimeout(timer);
            el.classList.remove('toast--in');
            setTimeout(function () { if (el.parentNode) el.remove(); }, 250);
        }
        el.addEventListener('click', remove);
        return el;
    }

    function confirmDialog(opts) {
        opts = opts || {};
        var ov = document.createElement('div');
        ov.className = 'confirm-overlay';
        ov.innerHTML =
            '<div class="confirm-box" role="dialog" aria-modal="true">' +
                '<div class="confirm-title"></div>' +
                '<div class="confirm-msg"></div>' +
                '<div class="confirm-actions">' +
                    '<button type="button" class="btn-secondary confirm-cancel"></button>' +
                    '<button type="button" class="btn-new-client confirm-ok"></button>' +
                '</div>' +
            '</div>';
        ov.querySelector('.confirm-title').textContent = opts.title || 'Потврди';
        ov.querySelector('.confirm-msg').textContent   = opts.message || '';
        var okBtn = ov.querySelector('.confirm-ok');
        var noBtn = ov.querySelector('.confirm-cancel');
        okBtn.textContent = opts.confirmText || 'Потврди';
        noBtn.textContent = opts.cancelText || 'Откажи';
        if (opts.danger) okBtn.style.background = '#dc2626';
        document.body.appendChild(ov);
        requestAnimationFrame(function () { ov.classList.add('open'); });

        function close() {
            ov.classList.remove('open');
            document.removeEventListener('keydown', onKey, true);
            setTimeout(function () { if (ov.parentNode) ov.remove(); }, 200);
        }
        function onKey(e) { if (e.key === 'Escape') { close(); if (opts.onCancel) opts.onCancel(); } }

        okBtn.addEventListener('click', function () { close(); if (opts.onConfirm) opts.onConfirm(); });
        noBtn.addEventListener('click', function () { close(); if (opts.onCancel) opts.onCancel(); });
        ov.addEventListener('click', function (e) { if (e.target === ov) { close(); if (opts.onCancel) opts.onCancel(); } });
        document.addEventListener('keydown', onKey, true);
        setTimeout(function () { okBtn.focus(); }, 50);
    }

    window.toast = toast;
    window.confirmDialog = confirmDialog;
}());

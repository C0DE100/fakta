/* ============================================================
   Notifications — top-nav bell (Facebook-style).
   Loaded on every page via nav.php. Polls the unread count and,
   on open, renders the recent feed. Clicking an item marks it read
   and deep-links into the case's Задачи tab.
   ============================================================ */
(function () {
    'use strict';

    var API = 'api/notification_api.php';
    var POLL_MS = 45000;

    var wrap   = document.getElementById('navNotif');
    if (!wrap) return;
    var btn    = document.getElementById('navBellBtn');
    var badge  = document.getElementById('navBellBadge');
    var menu   = document.getElementById('navNotifMenu');
    var list   = document.getElementById('navNotifList');
    var readAll = document.getElementById('navNotifReadAll');

    /* ---- helpers ---- */

    function setBadge(n) {
        n = parseInt(n, 10) || 0;
        if (n > 0) {
            badge.textContent = n > 99 ? '99+' : String(n);
            badge.hidden = false;
        } else {
            badge.hidden = true;
        }
    }

    function getJSON(action) {
        return fetch(API + '?action=' + encodeURIComponent(action), { credentials: 'same-origin' })
            .then(function (r) { return r.json(); });
    }

    function postForm(action, extra) {
        var body = new URLSearchParams();
        body.set('action', action);
        if (extra) Object.keys(extra).forEach(function (k) { body.set(k, extra[k]); });
        return fetch(API, { method: 'POST', credentials: 'same-origin', body: body })
            .then(function (r) { return r.json(); });
    }

    function timeAgo(sqlDate) {
        if (!sqlDate) return '';
        var then = new Date(sqlDate.replace(' ', 'T'));
        var s = Math.floor((Date.now() - then.getTime()) / 1000);
        if (isNaN(s)) return '';
        if (s < 60)    return 'пред момент';
        var m = Math.floor(s / 60);
        if (m < 60)    return 'пред ' + m + ' мин';
        var h = Math.floor(m / 60);
        if (h < 24)    return 'пред ' + h + ' ' + (h === 1 ? 'час' : 'часа');
        var d = Math.floor(h / 24);
        if (d < 7)     return 'пред ' + d + ' ' + (d === 1 ? 'ден' : 'дена');
        return then.toLocaleDateString('mk-MK');
    }

    function messageFor(n) {
        var actor = n.actor_name || 'Некој';
        if (n.type === 'todo.assigned') return actor + ' ти додели задача';
        return actor + ' те спомена';
    }

    function caseLabel(n) {
        if (!n.case_number) return '';
        var label = 'Предмет ' + n.case_number;
        if (n.case_basis) label += ' · ' + n.case_basis;
        return label;
    }

    function linkFor(n) {
        if (!n.case_id) return null;
        return 'predmet.php?id=' + encodeURIComponent(n.case_id) + '&tab=todos';
    }

    /* ---- rendering ---- */

    function renderList(items) {
        list.innerHTML = '';
        if (!items || !items.length) {
            var empty = document.createElement('div');
            empty.className = 'nav-notif-empty';
            empty.textContent = 'Немате известувања.';
            list.appendChild(empty);
            return;
        }
        items.forEach(function (n) {
            var row = document.createElement('a');
            row.className = 'nav-notif-item' + (Number(n.is_read) ? '' : ' is-unread');
            var href = linkFor(n);
            row.href = href || '#';

            var dot = document.createElement('span');
            dot.className = 'nav-notif-dot';
            row.appendChild(dot);

            var body = document.createElement('span');
            body.className = 'nav-notif-body';

            var msg = document.createElement('span');
            msg.className = 'nav-notif-msg';
            msg.textContent = messageFor(n);
            body.appendChild(msg);

            if (n.title) {
                var sub = document.createElement('span');
                sub.className = 'nav-notif-sub';
                sub.textContent = '„' + n.title + '“';
                body.appendChild(sub);
            }

            var meta = document.createElement('span');
            meta.className = 'nav-notif-meta';
            var cl = caseLabel(n);
            meta.textContent = cl ? cl + ' · ' + timeAgo(n.created_at) : timeAgo(n.created_at);
            body.appendChild(meta);

            row.appendChild(body);

            row.addEventListener('click', function (e) {
                // Mark read regardless; let the link navigate normally.
                if (!Number(n.is_read)) {
                    postForm('mark_read', { id: n.id }).then(function (res) {
                        if (res && typeof res.unread !== 'undefined') setBadge(res.unread);
                    });
                }
                if (!href) e.preventDefault();
            });

            list.appendChild(row);
        });
    }

    function loadList() {
        list.innerHTML = '<div class="nav-notif-empty">Се вчитува…</div>';
        getJSON('list').then(function (res) {
            if (!res || !res.success) { list.innerHTML = '<div class="nav-notif-empty">Грешка при вчитување.</div>'; return; }
            setBadge(res.unread);
            renderList(res.data);
        }).catch(function () {
            list.innerHTML = '<div class="nav-notif-empty">Грешка при вчитување.</div>';
        });
    }

    function pollCount() {
        getJSON('unread_count').then(function (res) {
            if (res && res.success) setBadge(res.unread);
        }).catch(function () {});
    }

    /* ---- open/close ---- */

    function close() {
        wrap.classList.remove('open');
        btn.setAttribute('aria-expanded', 'false');
        menu.setAttribute('aria-hidden', 'true');
    }
    function toggle() {
        var open = wrap.classList.toggle('open');
        btn.setAttribute('aria-expanded', open ? 'true' : 'false');
        menu.setAttribute('aria-hidden', open ? 'false' : 'true');
        if (open) loadList();
    }

    btn.addEventListener('click', function (e) { e.stopPropagation(); toggle(); });
    document.addEventListener('click', function (e) { if (!wrap.contains(e.target)) close(); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') close(); });

    readAll.addEventListener('click', function (e) {
        e.stopPropagation();
        postForm('mark_all_read').then(function () {
            setBadge(0);
            loadList();
        });
    });

    /* ---- boot ---- */
    pollCount();
    setInterval(pollCount, POLL_MS);
}());

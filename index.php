<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

// Super-admin manages tenants only — keep them out of the company app.
if (current_role() === 'super_admin') {
    header('Location: ' . fakta_url('admin/index.php'));
    exit;
}

$userName = current_user()['name'] ?? '';
?>
<!DOCTYPE html>
<html lang="mk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Факта</title>
    <script>if(localStorage.getItem('darkMode')==='1')document.documentElement.setAttribute('data-theme','dark');</script>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.css">
    <link rel="stylesheet" href="css/style.css">
</head>

<body class="min-h-screen">

    <?php include 'includes/nav.php'; ?>

    <div class="app-layout">

    <?php $currentPage = 'home'; include 'includes/sidebar.php'; ?>

    <div class="main-content">
    <div class="max-w-6xl mx-auto px-4 pb-16" id="dashboard">

        <!-- Greeting -->
        <div class="pt-10 pb-5">
            <h1 class="text-lg font-semibold text-slate-800">
                Добредојде<?= $userName !== '' ? ', ' . htmlspecialchars($userName) : '' ?>
            </h1>
            <p class="text-sm text-slate-400 mt-1">Следни настани и задачи</p>
        </div>

        <!-- Stat cards (work-focused) -->
        <div class="dash-stats">
            <a href="kalendar.php?view=day" class="dash-card dash-card--link">
                <span class="dash-card-label">Настани денес</span>
                <span class="dash-card-value" id="statTodayEv">—</span>
            </a>
            <button type="button" class="dash-card dash-card--link" id="statOverdueCard">
                <span class="dash-card-label">Задачи во доцнење</span>
                <span class="dash-card-value" id="statOverdue">—</span>
            </button>
            <button type="button" class="dash-card dash-card--link" id="statTodosCard">
                <span class="dash-card-label">Активни задачи</span>
                <span class="dash-card-value" id="statTodos">—</span>
            </button>
        </div>

        <!-- Main: what's next (calendar) + what to do (todos) -->
        <div class="dash-grid">

            <section class="dash-panel">
                <div class="dash-panel-head">
                    <h2 class="dash-panel-title">
                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="4" rx="2"/><path d="M3 10h18M8 2v4M16 2v4"/></svg>
                    Настани
                    </h2>
                    <a href="kalendar.php" class="dash-panel-link">Календар →</a>
                </div>
                <div id="dashEvents" class="dash-panel-body">
                    <p class="dash-loading">Се вчитува…</p>
                </div>
            </section>

            <section class="dash-panel">
                <div class="dash-panel-head">
                    <h2 class="dash-panel-title">
                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="8" height="4" x="8" y="2" rx="1"/><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><path d="m8 10 1.2 1.2L11.5 8.8"/><path d="M14 10h3"/><path d="m8 14.5 1.2 1.2L11.5 13.3"/><path d="M14 14.5h3"/><path d="m8 19 1.2 1.2L11.5 17.8"/><path d="M14 19h3"/></svg>
                        Задачи
                    </h2>
                    <button type="button" id="dashTodoAdd" class="dash-add-btn">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12h14"/></svg>
                        Нова задача
                    </button>
                </div>
                <div id="dashTodos" class="dash-panel-body">
                    <p class="dash-loading">Се вчитува…</p>
                </div>
            </section>

        </div>

    </div>
    </div> <!-- /.main-content -->
    </div> <!-- /.app-layout -->

    <!-- Modal: нова задача (од почетна) -->
    <div class="modal-overlay" id="dashTodoModal">
        <div class="modal-box todo-modal-box" role="dialog" aria-modal="true" aria-labelledby="dashTodoTitle">
            <div class="modal-header">
                <div class="modal-title" id="dashTodoTitle">Нова задача</div>
                <button type="button" class="modal-close" id="dashTodoClose" aria-label="Затвори">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
                </button>
            </div>
            <div class="todo-modal-compose">
                <input type="text" id="dtTitle" class="field todo-title-input" placeholder="Нова задача — пр. Јави се на клиент, Подготви документи…">
                <label class="dt-link-toggle">
                    <input type="checkbox" id="dtLink">
                    <span>Поврзи со предмет</span>
                </label>
                <div id="dtCaseWrap" class="dt-case-wrap" hidden>
                    <div class="combo" id="dtCaseCombo">
                        <input type="text" id="dtCaseInput" class="field combo-input" placeholder="Пребарај предмет…" autocomplete="off">
                        <div class="combo-menu" id="dtCaseMenu" hidden></div>
                    </div>
                    <div class="combo" id="dtAsgCombo">
                        <input type="text" id="dtAsgInput" class="field combo-input" placeholder="Прво избери предмет…" autocomplete="off" disabled>
                        <div class="combo-menu" id="dtAsgMenu" hidden></div>
                    </div>
                </div>
                <div class="todo-modal-row">
                    <input type="text" id="dtDue" class="field todo-due-input" placeholder="Рок (опц.)" title="Рок (опц.)">
                </div>
                <textarea id="dtNote" class="field todo-note-input" rows="2" placeholder="Забелешка (опц.)"></textarea>
                <div class="hearing-compose-foot">
                    <button type="button" id="dtCancel" class="btn-modal-cancel">Откажи</button>
                    <button type="button" id="dtAdd" class="btn-modal-save">Додај</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="js/app.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/l10n/mk.js"></script>
    <script>
    /* ---- Home dashboard: my upcoming events + my open to-dos (by case) ---- */
    $(function () {
        var API = 'api/case_api.php';
        var MK_MON_SH = ['јан','фев','мар','апр','мај','јун','јул','авг','сеп','окт','ное','дек'];
        var MK_WD_SH  = ['Пон','Вто','Сре','Чет','Пет','Саб','Нед'];
        var HKIND = { hearing: 'Рочиште', trial: 'Судење', meeting: 'Состанок', other: 'Друго', private: 'Друго' };
        var CASE_COLORS = { slate:'#475569', red:'#dc2626', orange:'#ea580c', amber:'#d97706', green:'#16a34a', blue:'#2563eb', purple:'#7c3aed', pink:'#db2777' };
        // Idle checkbox-ring colour by to-do status (matches the .tdot palette).
        var STATUS_COLOR = { open:'#a8a29e', in_progress:'#2563eb', waiting:'#d4a017' };
        var CHECK_ICO = '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>';
        var CARET_ICO = '<svg class="todo-status-caret" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>';
        var TODO_STATUSES = [
            { key:'open',        label:'Отворена' },
            { key:'in_progress', label:'Во тек' },
            { key:'waiting',     label:'Чека' },
            { key:'done',        label:'Завршена' },
            { key:'declined',    label:'Одбиена' }
        ];
        function findTodo(id) { return DASH.todos.filter(function (t) { return String(t.id) === String(id); })[0]; }

        function esc(s) { return $('<div>').text(s == null ? '' : String(s)).html(); }
        function pad(n) { return (n < 10 ? '0' : '') + n; }
        function ymd(d) { return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()); }
        function mondayIdx(d) { return (d.getDay() + 6) % 7; }
        function hTime(s) { return String(s).slice(11, 16); }
        function startOf(e) { return e.start || e.hearing_at || e.starts_at; }
        function hRange(e) {
            var s = hTime(startOf(e)), end = e.ends_at ? hTime(e.ends_at) : null;
            return (end && end !== s) ? s + '–' + end : s;
        }
        function kindOf(e) { return HKIND[e.kind] ? e.kind : 'hearing'; }

        var TODAY = new Date(); TODAY.setHours(0, 0, 0, 0);
        function dayHead(key) {
            var d = new Date(key + 'T00:00:00');
            var diff = Math.round((d - TODAY) / 86400000);
            if (diff === 0) return { label: 'Денес', today: true };
            if (diff === 1) return { label: 'Утре', today: false };
            return { label: MK_WD_SH[mondayIdx(d)] + ' · ' + d.getDate() + ' ' + MK_MON_SH[d.getMonth()], today: false };
        }
        function shortDate(d) { var p = String(d).slice(0, 10).split('-'); return p.length < 3 ? '' : p[2] + '.' + p[1]; }
        // Compact, colour-coded due info: {label, cls, overdue} or null.
        function dueInfo(d) {
            if (!d) return null;
            var dd = new Date(String(d).slice(0, 10) + 'T00:00:00');
            var diff = Math.round((dd - TODAY) / 86400000);
            if (diff < 0)  return { label: shortDate(d), cls: 'is-overdue', overdue: true };
            if (diff === 0) return { label: 'денес', cls: 'is-today' };
            if (diff === 1) return { label: 'утре', cls: 'is-soon' };
            if (diff <= 6) return { label: MK_WD_SH[mondayIdx(dd)], cls: 'is-soon' };
            return { label: shortDate(d), cls: '' };
        }

        var DASH = { events: [], todos: [] };

        /* ---- Events: "what's next", grouped by day ---- */
        function eventRow(e) {
            var k = kindOf(e);
            var caseLine = e.source === 'case'
                ? '<span class="dash-ev-case"><b>Предмет ' + esc(e.case_number) + '</b>'
                    + (e.client_name ? ' · ' + esc(e.client_name) : '') + '</span>'
                : '';
            var href = e.source === 'case'
                ? 'predmet.php?id=' + encodeURIComponent(e.case_id)
                : 'kalendar.php?date=' + String(startOf(e)).slice(0, 10) + '&view=day';
            return '<a class="dash-ev hkind--' + k + '" href="' + href + '">'
                + '<span class="dash-ev-time">' + hRange(e) + '</span>'
                + '<span class="dash-ev-body">'
                +   '<span class="dash-ev-head"><span class="dash-ev-title">' + esc(e.title) + '</span>'
                +     '<span class="hkind-badge hkind--' + k + '">' + HKIND[k] + '</span></span>'
                +   caseLine
                + '</span></a>';
        }
        function renderEvents() {
            var evs = DASH.events;
            if (!evs.length) { $('#dashEvents').html('<p class="dash-empty">Нема закажани настани.</p>'); return; }
            var byDay = {};
            evs.forEach(function (e) { var k = String(startOf(e)).slice(0, 10); (byDay[k] = byDay[k] || []).push(e); });
            var html = Object.keys(byDay).sort().map(function (key) {
                var hd = dayHead(key);
                return '<div class="dash-day">'
                    + '<div class="dash-day-head' + (hd.today ? ' is-today' : '') + '">' + esc(hd.label) + '</div>'
                    + byDay[key].map(eventRow).join('')
                    + '</div>';
            }).join('');
            $('#dashEvents').html(html);
        }

        /* ---- To-dos: grouped under the case they belong to ---- */
        function todoRow(t) {
            var status = t.status || 'open';
            var di = dueInfo(t.due_date);
            var due = di ? ('<span class="dash-due ' + di.cls + '">' + esc(di.label) + '</span>') : '';
            var ring = STATUS_COLOR[status] || STATUS_COLOR.open;
            var menu = TODO_STATUSES.map(function (s) {
                return '<button type="button" class="todo-status-opt' + (s.key === status ? ' is-current' : '') + '" data-status="' + s.key + '">'
                    + '<span class="tdot tdot--' + s.key + '"></span>' + s.label + '</button>';
            }).join('');
            return '<div class="dash-td" data-id="' + t.id + '">'
                + '<button type="button" class="dash-td-check" data-id="' + t.id + '" title="Означи како завршена" aria-label="Заврши задача" style="--ring:' + ring + '">' + CHECK_ICO + '</button>'
                + '<span class="dash-td-title">' + esc(t.title) + '</span>'
                + due
                + '<div class="todo-status-wrap dash-td-status">'
                +   '<button type="button" class="todo-status tstat--' + status + '" title="Промени статус"><span class="tdot tdot--' + status + '"></span>' + CARET_ICO + '</button>'
                +   '<div class="todo-status-menu" hidden>' + menu + '</div>'
                + '</div>'
                + '</div>';
        }
        function renderTodos() {
            var list = DASH.todos;
            if (!list.length) { $('#dashTodos').html('<p class="dash-empty">Нема активни задачи.</p>'); return; }
            // Group by case, ordering cases by their most urgent (earliest / overdue) due date.
            var groups = {}, order = [];
            list.forEach(function (t) {
                var g = groups[t.case_id];
                if (!g) { g = groups[t.case_id] = { id: t.case_id, number: t.case_number, client: t.client_name, color: t.case_color, items: [], urg: Infinity }; order.push(g); }
                g.items.push(t);
                var u = t.due_date ? new Date(String(t.due_date).slice(0, 10) + 'T00:00:00').getTime() : Infinity;
                if (u < g.urg) g.urg = u;
            });
            // Personal (no-case) tasks group first, then cases by urgency.
            order.sort(function (a, b) {
                if (!a.id !== !b.id) return !a.id ? -1 : 1;
                return a.urg - b.urg;
            });
            var html = order.map(function (g) {
                if (!g.id) {
                    // Standalone personal to-dos — no case to link to.
                    return '<div class="dash-cg">'
                        + '<div class="dash-cg-head dash-cg-head--personal">'
                        +   '<span class="dash-cg-dot" style="background:' + CASE_COLORS.slate + '"></span>'
                        +   '<span class="dash-cg-num">Лични задачи</span>'
                        +   '<span class="dash-cg-count">' + g.items.length + '</span>'
                        + '</div>'
                        + g.items.map(todoRow).join('')
                        + '</div>';
                }
                var dot = CASE_COLORS[g.color] || CASE_COLORS.slate;
                return '<div class="dash-cg">'
                    + '<a class="dash-cg-head" href="predmet.php?id=' + encodeURIComponent(g.id) + '">'
                    +   '<span class="dash-cg-dot" style="background:' + dot + '"></span>'
                    +   '<span class="dash-cg-num">Предмет ' + esc(g.number) + '</span>'
                    +   (g.client ? '<span class="dash-cg-client">' + esc(g.client) + '</span>' : '')
                    +   '<span class="dash-cg-count">' + g.items.length + '</span>'
                    + '</a>'
                    + g.items.map(todoRow).join('')
                    + '</div>';
            }).join('');
            $('#dashTodos').html(html);
        }

        function renderStats() {
            var todayKey = ymd(TODAY);
            $('#statTodayEv').text(DASH.events.filter(function (e) { return String(startOf(e)).slice(0, 10) === todayKey; }).length);
            var overdue = DASH.todos.filter(function (t) { var di = dueInfo(t.due_date); return di && di.overdue; }).length;
            $('#statTodos').text(DASH.todos.length);
            $('#statOverdue').text(overdue).toggleClass('is-alert', overdue > 0);
        }

        $('#statTodosCard, #statOverdueCard').on('click', function () {
            document.getElementById('dashTodos').scrollIntoView({ behavior: 'smooth', block: 'center' });
        });

        /* ---- Change a to-do's status from the dashboard (CSRF auto-attached by
           csrf.js). 'done'/'declined' drop it from the list (with undo); the
           other statuses just update it in place. ---- */
        function applyStatus(id, newStatus, opts) {
            opts = opts || {};
            var t = findTodo(id); if (!t || newStatus === t.status) return;
            var prev = t.status;
            var terminal = (newStatus === 'done' || newStatus === 'declined');
            var $row = $('#dashTodos .dash-td[data-id="' + id + '"]');
            if (opts.$btn) opts.$btn.addClass('is-busy is-checked');
            if (terminal) $row.addClass('is-completing');
            $.post(API, { action: 'set_todo_status', todo_id: id, status: newStatus }, null, 'json')
                .done(function (r) {
                    if (!r || !r.success) {
                        if (opts.$btn) opts.$btn.removeClass('is-busy is-checked');
                        $row.removeClass('is-completing');
                        if (window.toast) window.toast((r && r.message) || 'Грешка.', 'error');
                        return;
                    }
                    if (terminal) {
                        setTimeout(function () {
                            DASH.todos = DASH.todos.filter(function (x) { return String(x.id) !== String(id); });
                            renderTodos(); renderStats();
                        }, 200);
                        if (window.toast) {
                            window.toast('Задачата е ' + (newStatus === 'done' ? 'завршена' : 'одбиена') + '.', 'success',
                                { action: { text: 'Врати', onClick: function () { restoreStatus(t, prev); } } });
                        }
                    } else {
                        t.status = newStatus;
                        renderTodos(); renderStats();
                    }
                })
                .fail(function () {
                    if (opts.$btn) opts.$btn.removeClass('is-busy is-checked');
                    $row.removeClass('is-completing');
                    if (window.toast) window.toast('Грешка при зачувување.', 'error');
                });
        }
        // Re-open a just-completed/declined task (the undo action).
        function restoreStatus(t, prev) {
            $.post(API, { action: 'set_todo_status', todo_id: t.id, status: prev }, null, 'json')
                .done(function (r) {
                    if (r && r.success) {
                        t.status = prev;
                        if (!findTodo(t.id)) DASH.todos.push(t);
                        renderTodos(); renderStats();
                    } else if (window.toast) window.toast((r && r.message) || 'Грешка.', 'error');
                })
                .fail(function () { if (window.toast) window.toast('Грешка при враќање.', 'error'); });
        }

        // Quick-complete via the checkbox.
        $('#dashTodos').on('click', '.dash-td-check', function () {
            var $btn = $(this);
            if ($btn.hasClass('is-busy')) return;
            applyStatus($btn.data('id'), 'done', { $btn: $btn });
        });
        function closeStatusMenus() { $('#dashTodos .todo-status-menu').prop('hidden', true); }
        // Status pill → open the menu, positioned (fixed) next to the pill so the
        // scrollable panel never clips it; flips above the pill if low on screen.
        $('#dashTodos').on('click', '.todo-status', function (e) {
            e.stopPropagation();
            var $menu = $(this).siblings('.todo-status-menu');
            var willOpen = $menu.prop('hidden');
            closeStatusMenus();
            if (!willOpen) return;
            $menu.prop('hidden', false);
            var pr = this.getBoundingClientRect(), mw = $menu.outerWidth(), mh = $menu.outerHeight();
            var left = Math.max(8, pr.right - mw);
            var top = (pr.bottom + 4 + mh > window.innerHeight - 8) ? (pr.top - 4 - mh) : (pr.bottom + 4);
            $menu.css({ left: left + 'px', top: Math.max(8, top) + 'px' });
        });
        // Pick a status from the menu.
        $('#dashTodos').on('click', '.todo-status-opt', function (e) {
            e.stopPropagation();
            var $opt = $(this);
            closeStatusMenus();
            applyStatus($opt.closest('.dash-td').data('id'), $opt.data('status'));
        });
        // Close on outside click, panel scroll, or window resize (fixed menu won't follow).
        $(document).on('click', closeStatusMenus);
        $('#dashTodos').on('scroll', closeStatusMenus);
        $(window).on('resize', closeStatusMenus);

        function load() {
            $.ajax({ url: API, data: { action: 'dashboard' }, dataType: 'json' })
                .done(function (res) {
                    if (!res || !res.success) {
                        $('#dashEvents, #dashTodos').html('<p class="dash-empty">Грешка при вчитување.</p>');
                        return;
                    }
                    DASH.events = res.events || [];
                    DASH.todos  = res.todos || [];
                    renderEvents();
                    renderTodos();
                    renderStats();
                })
                .fail(function () {
                    $('#dashEvents, #dashTodos').html('<p class="dash-empty">Грешка при вчитување.</p>');
                });
        }

        /* ---- "Нова задача" modal: create a to-do straight from the home page.
           Unlinked → a personal task assigned to me (наслов / рок / забелешка).
           Linked → pick a предмет (admins: any; others: only theirs) and assign
           it to someone доделен on that case. ---- */

        /* Reusable searchable combobox. opts:
             input/menu  — jQuery els
             source(q, done) — async; calls done([{value,label,sub}])
             onSelect(item|null)
           Built for scale: server-backed sources can debounce + cap; stale
           responses are dropped via a sequence guard. Nothing is materialised
           until the user opens it, so 1000s of cases / users never load at once. */
        function makeCombo(opts) {
            var items = [], active = -1, timer = null, seq = 0, sel = null;
            function paint() {
                opts.menu.find('.combo-opt').removeClass('is-active')
                    .filter('[data-i="' + active + '"]').addClass('is-active');
            }
            function close() { opts.menu.prop('hidden', true); active = -1; }
            function render(list) {
                items = list || []; active = -1;
                if (!items.length) {
                    opts.menu.html('<div class="combo-empty">Нема резултати</div>').prop('hidden', false);
                    return;
                }
                opts.menu.html(items.map(function (it, i) {
                    return '<div class="combo-opt" data-i="' + i + '">'
                        + '<span class="combo-opt-label">' + esc(it.label) + '</span>'
                        + (it.sub ? '<span class="combo-sub">' + esc(it.sub) + '</span>' : '')
                        + '</div>';
                }).join('')).prop('hidden', false);
            }
            function run() {
                var mine = ++seq;
                opts.source(opts.input.val().trim(), function (list) {
                    if (mine === seq) render(list);
                });
            }
            function choose(i) {
                var it = items[i]; if (!it) return;
                sel = it; opts.input.val(it.label); close();
                if (opts.onSelect) opts.onSelect(it);
            }
            opts.input.on('input', function () {
                if (sel) { sel = null; if (opts.onSelect) opts.onSelect(null); }
                clearTimeout(timer); timer = setTimeout(run, 200);
            });
            opts.input.on('focus', function () { if (!this.disabled) run(); });
            opts.input.on('keydown', function (e) {
                if (opts.menu.prop('hidden') || !items.length) return;
                if (e.key === 'ArrowDown') { e.preventDefault(); active = (active + 1) % items.length; paint(); }
                else if (e.key === 'ArrowUp') { e.preventDefault(); active = (active - 1 + items.length) % items.length; paint(); }
                else if (e.key === 'Enter') { if (active >= 0) { e.preventDefault(); choose(active); } }
                else if (e.key === 'Escape') { close(); }
            });
            opts.menu.on('mousedown', '.combo-opt', function (e) { e.preventDefault(); choose(+$(this).data('i')); });
            opts.input.on('blur', function () { setTimeout(close, 120); });
            return {
                value: function () { return sel; },
                clear: function () { sel = null; opts.input.val(''); close(); },
                enable: function (on) { opts.input.prop('disabled', !on); },
                placeholder: function (p) { opts.input.attr('placeholder', p); }
            };
        }

        // Рок picker (flatpickr, MK locale) — calendar inline (static) so it stays
        // inside the modal's stacking context. The hidden input keeps the Y-m-d value.
        var FP_LOCALE = (window.flatpickr && flatpickr.l10ns && flatpickr.l10ns.mk) ? flatpickr.l10ns.mk : 'default';
        var fpDtDue = (function () {
            var el = document.getElementById('dtDue');
            if (!el || !window.flatpickr) return null;
            return flatpickr(el, {
                locale: FP_LOCALE, dateFormat: 'Y-m-d', altInput: true, altFormat: 'd.m.Y',
                altInputClass: el.className, allowInput: true, disableMobile: true, static: true
            });
        })();

        // Assignees of the currently-picked case (filtered client-side in its combo).
        var dtAsgList = [];
        var asgCombo = makeCombo({
            input: $('#dtAsgInput'), menu: $('#dtAsgMenu'),
            source: function (q, done) {
                var ql = q.toLowerCase();
                done(dtAsgList.filter(function (m) { return !ql || m.name.toLowerCase().indexOf(ql) !== -1; })
                    .map(function (m) { return { value: m.id, label: m.name }; }));
            }
        });
        var caseCombo = makeCombo({
            input: $('#dtCaseInput'), menu: $('#dtCaseMenu'),
            source: function (q, done) {
                $.ajax({ url: API, data: { action: 'assignable_cases', q: q, limit: 25 }, dataType: 'json' })
                    .done(function (r) {
                        done(((r && r.data) || []).map(function (c) {
                            return { value: c.id, label: 'Предмет ' + c.case_number, sub: c.client_name || '' };
                        }));
                    }).fail(function () { done([]); });
            },
            onSelect: function (it) {
                // Reset the assignee combo whenever the case changes.
                asgCombo.clear(); dtAsgList = [];
                if (!it) { asgCombo.enable(false); asgCombo.placeholder('Прво избери предмет…'); return; }
                asgCombo.enable(false); asgCombo.placeholder('Се вчитува…');
                $.ajax({ url: API, data: { action: 'case_assignees', id: it.value }, dataType: 'json' }).done(function (r) {
                    var list = (r && r.data) || [];
                    if (window.FAKTA_ROLE === 'praktikant') {
                        list = list.filter(function (m) { return String(m.id) === String(window.FAKTA_UID); });
                    }
                    dtAsgList = list.map(function (m) { return { id: m.id, name: m.name }; });
                    if (!dtAsgList.length) { asgCombo.enable(false); asgCombo.placeholder('Нема доделени лица на предметот'); }
                    else { asgCombo.enable(true); asgCombo.placeholder('Додели на…'); }
                }).fail(function () { asgCombo.enable(false); asgCombo.placeholder('Грешка при вчитување'); });
            }
        });

        function dtReset() {
            $('#dtTitle').val(''); $('#dtNote').val('');
            if (fpDtDue) fpDtDue.clear(); else $('#dtDue').val('');
            $('#dtLink').prop('checked', false);
            $('#dtCaseWrap').prop('hidden', true);
            caseCombo.clear(); asgCombo.clear(); dtAsgList = [];
            asgCombo.enable(false); asgCombo.placeholder('Прво избери предмет…');
        }
        function dtClose() {
            $('#dashTodoModal').removeClass('open');
            $('body').removeClass('modal-open');
            document.removeEventListener('keydown', dtKey, true);
        }
        function dtKey(e) { if (e.key === 'Escape') dtClose(); }
        $('#dashTodoAdd').on('click', function () {
            dtReset();
            $('#dashTodoModal').addClass('open');
            $('body').addClass('modal-open');
            document.addEventListener('keydown', dtKey, true);
            setTimeout(function () { $('#dtTitle').focus(); }, 60);
        });
        $('#dashTodoClose, #dtCancel').on('click', dtClose);
        $('#dashTodoModal').on('click', function (e) { if (e.target === this) dtClose(); });
        $('#dtLink').on('change', function () {
            var on = this.checked;
            $('#dtCaseWrap').prop('hidden', !on);
            if (on) setTimeout(function () { $('#dtCaseInput').focus(); }, 40);
        });
        $('#dtTitle').on('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); $('#dtAdd').click(); } });
        $('#dtAdd').on('click', function () {
            var title = $('#dtTitle').val().trim();
            if (!title) { if (window.toast) window.toast('Внеси задача.', 'error'); return; }
            var data = { action: 'add_todo', title: title, due_date: $('#dtDue').val(), note: $('#dtNote').val() };
            if ($('#dtLink').is(':checked')) {
                var c = caseCombo.value(), a = asgCombo.value();
                if (!c) { if (window.toast) window.toast('Избери предмет.', 'error'); return; }
                if (!a) { if (window.toast) window.toast('Додели ја задачата на лице од предметот.', 'error'); return; }
                data.id = c.value;
                data.assigned_to = a.value;
            }
            var $b = $(this).prop('disabled', true);
            $.post(API, data, null, 'json')
                .done(function (r) {
                    if (r && r.success) {
                        dtClose();
                        if (window.toast) window.toast('Задачата е додадена.', 'success');
                        load();
                    } else if (window.toast) window.toast((r && r.message) || 'Грешка.', 'error');
                })
                .fail(function () { if (window.toast) window.toast('Грешка при зачувување.', 'error'); })
                .always(function () { $b.prop('disabled', false); });
        });

        load();
    });
    </script>
</body>
</html>

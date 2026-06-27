<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

// Super-admins live in the admin area, not the tenant app.
if (current_role() === 'super_admin') {
    header('Location: ' . fakta_url('admin/index.php'));
    exit;
}

$currentPage = 'kalendar';
$companyId   = current_company_id();

// Employees for the "whose calendar" filter (доделено на / owner).
$mStmt = $GLOBALS['fakta_db']->prepare(
    "SELECT id, name FROM users
     WHERE company_id = :cid AND role IN ('admin','employee','praktikant')
     ORDER BY name"
);
$mStmt->execute([':cid' => $companyId]);
$members = $mStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="mk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Календар – Факта</title>
    <script>if(localStorage.getItem('darkMode')==='1')document.documentElement.setAttribute('data-theme','dark');</script>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.css">
    <link rel="stylesheet" href="css/style.css">
</head>

<body class="min-h-screen">

    <?php include 'includes/nav.php'; ?>

    <div class="app-layout">

    <?php include 'includes/sidebar.php'; ?>

    <div class="main-content">
    <div class="max-w-6xl mx-auto px-4 pb-16">

        <div class="pt-10 pb-6 flex items-start justify-between gap-4 flex-wrap">
            <div>
                <h1 class="text-lg font-semibold text-slate-800">Календар</h1>
                <p class="text-sm text-slate-400 mt-1">Рочишта, состаноци и лични настани</p>
            </div>
            <div class="cal-legend">
                <span class="cal-legend-item"><span class="cal-dot hkind--hearing"></span>Рочиште</span>
                <span class="cal-legend-item"><span class="cal-dot hkind--meeting"></span>Состанок</span>
                <span class="cal-legend-item"><span class="cal-dot hkind--other"></span>Друго</span>
            </div>
        </div>

        <div class="cal-toolbar">
            <div class="cal-nav">
                <button type="button" id="calPrev" class="cal-nav-btn" aria-label="Претходно">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
                </button>
                <button type="button" id="calToday" class="cal-today-btn">Денес</button>
                <button type="button" id="calNext" class="cal-nav-btn" aria-label="Следно">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
                </button>
            </div>
            <div class="cal-titlewrap">
                <button type="button" id="calTitleBtn" class="cal-title">
                    <span id="calTitle"></span>
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>
                </button>
                <div id="calJump" class="cal-jump" style="display:none">
                    <div class="cal-jump-head">
                        <button type="button" id="calJumpPrevY" class="cal-nav-btn" aria-label="Претходна година"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg></button>
                        <span id="calJumpYear" class="cal-jump-year"></span>
                        <button type="button" id="calJumpNextY" class="cal-nav-btn" aria-label="Следна година"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg></button>
                    </div>
                    <div class="cal-jump-grid" id="calJumpMonths"></div>
                </div>
            </div>
            <div class="cal-toolbar-right">
                <button type="button" id="calAddBtn" class="btn-modal-save cal-add-btn">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12h14"/></svg>
                    Нов настан
                </button>
                <div class="cal-viewswrap">
                    <span class="cal-tb-label">Преглед:</span>
                    <div class="cal-views" id="calViews">
                        <button type="button" class="cal-view-btn is-active" data-view="month">Месечен</button>
                        <button type="button" class="cal-view-btn" data-view="week">Неделен</button>
                        <button type="button" class="cal-view-btn" data-view="day">Дневен</button>
                    </div>
                </div>
                <div class="cal-filter">
                    <span class="cal-tb-label">Вработен:</span>
                    <select id="calAssignee" class="field cal-filter-select" title="Филтер по вработен">
                        <option value="0">Сите вработени</option>
                        <?php foreach ($members as $m): ?>
                            <option value="<?= (int) $m['id'] ?>"><?= htmlspecialchars($m['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>

        <div class="cal-wrap bg-white border border-slate-200 rounded-xl shadow-sm">
            <div id="calBody"></div>
        </div>

    </div>
    </div>
    </div>

    <!-- Day events list -->
    <div id="calDayModal" class="cal-pop" style="display:none">
        <div class="cal-pop-backdrop" data-close></div>
        <div class="cal-pop-card">
            <div class="cal-modal-head">
                <h3 id="calDayTitle"></h3>
                <button type="button" class="cal-modal-close" data-close aria-label="Затвори">&times;</button>
            </div>
            <div id="calDayList" class="cal-modal-list"></div>
            <div class="cal-modal-foot">
                <button type="button" id="calDayAdd" class="btn-modal-save">+ Додај настан</button>
            </div>
        </div>
    </div>

    <!-- Single event detail -->
    <div id="calEventModal" class="cal-pop" style="display:none">
        <div class="cal-pop-backdrop" data-close></div>
        <div class="cal-pop-card">
            <div class="cal-modal-head">
                <span id="calEvBadge"></span>
                <button type="button" class="cal-modal-close" data-close aria-label="Затвори">&times;</button>
            </div>
            <div class="cal-modal-list">
                <div id="calEvBody"></div>
            </div>
        </div>
    </div>

    <!-- Add / edit personal event -->
    <div id="calFormModal" class="cal-pop" style="display:none">
        <div class="cal-pop-backdrop" data-close></div>
        <div class="cal-pop-card">
            <div class="cal-modal-head">
                <h3 id="calFormTitle">Нов настан</h3>
                <button type="button" class="cal-modal-close" data-close aria-label="Затвори">&times;</button>
            </div>
            <div class="cal-modal-list">
                <div class="hkind-chips" id="calFormKind">
                    <button type="button" class="hkind-chip hkind--meeting is-active" data-kind="meeting">Состанок</button>
                    <button type="button" class="hkind-chip hkind--other" data-kind="other">Друго</button>
                </div>
                <input type="text" id="calFormTitleInput" class="field" placeholder="Наслов на настанот">
                <div class="cal-form-row">
                    <input type="text" id="calFormDate" class="field cal-form-date" placeholder="Датум">
                    <select id="calFormTime" class="field cal-form-time"></select>
                </div>
                <input type="text" id="calFormLoc" class="field" placeholder="Локација (опц.)">
                <textarea id="calFormNote" class="field" rows="2" placeholder="Белешка (опц.)"></textarea>
                <div class="cal-ev-foot">
                    <button type="button" class="btn-modal-cancel" data-close>Откажи</button>
                    <button type="button" id="calFormSave" class="btn-modal-save">Зачувај</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="js/app.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/l10n/mk.js"></script>
    <script>
    $(function () {
        var API = 'api/case_api.php';
        var MK_MONTHS = ['Јануари','Февруари','Март','Април','Мај','Јуни','Јули','Август','Септември','Октомври','Ноември','Декември'];
        var MK_MON_SH = ['јан','фев','мар','апр','мај','јун','јул','авг','сеп','окт','ное','дек'];
        var MK_WD = ['Пон','Вто','Сре','Чет','Пет','Саб','Нед'];
        var MK_WD_FULL = ['Понеделник','Вторник','Среда','Четврток','Петок','Сабота','Недела'];
        var HKIND = { hearing: 'Рочиште', trial: 'Судење', meeting: 'Состанок', other: 'Друго' };
        var DEFAULT_TIME = '09:00';

        var mode = 'month';
        try { var savedView = localStorage.getItem('calView'); if (['month', 'week', 'day'].indexOf(savedView) >= 0) mode = savedView; } catch (e) {}
        // Reflect the chosen view in the switcher + remember it across refreshes.
        function setMode(v) {
            mode = v;
            try { localStorage.setItem('calView', v); } catch (e) {}
            $('#calViews .cal-view-btn').removeClass('is-active').filter('[data-view="' + v + '"]').addClass('is-active');
        }
        var cursor = new Date(); cursor.setHours(0, 0, 0, 0);
        // Deep-link support: predmet.php hearing dates link here as ?date=YYYY-MM-DD&view=day.
        (function () {
            var qs = new URLSearchParams(location.search);
            var qDate = qs.get('date'), qView = qs.get('view');
            if (qDate && /^\d{4}-\d{2}-\d{2}$/.test(qDate)) {
                var p = qDate.split('-');
                cursor = new Date(+p[0], +p[1] - 1, +p[2]); cursor.setHours(0, 0, 0, 0);
            }
            if (qView && ['month', 'week', 'day'].indexOf(qView) >= 0) mode = qView;
            if (qDate || qView) history.replaceState(null, '', 'kalendar.php');
        }());
        var eventsByDay = {};   // 'YYYY-MM-DD' -> [event,…]
        var eventsById  = {};   // 'source:id'  -> event

        function esc(s) { return $('<div>').text(s == null ? '' : String(s)).html(); }
        function pad(n) { return (n < 10 ? '0' : '') + n; }
        function ymd(d) { return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()); }
        function kindOf(e) { return HKIND[e.kind] ? e.kind : 'hearing'; }
        function hTime(s) { return String(s).slice(11, 16); }
        function startOf(e) { return e.start || e.hearing_at || e.starts_at; }
        function uidOf(e) { return e.source + ':' + e.id; }
        function mondayIdx(d) { return (d.getDay() + 6) % 7; }
        function addDays(d, n) { var x = new Date(d); x.setDate(x.getDate() + n); return x; }
        function weekStart(d) { return addDays(d, -mondayIdx(d)); }
        function longDate(s) {
            var p = String(s).slice(0, 10).split('-');
            return parseInt(p[2], 10) + ' ' + MK_MONTHS[parseInt(p[1], 10) - 1] + ' ' + p[0];
        }

        /* ---- flatpickr date + time-select (matches предмет forms) ---- */
        var FP_LOCALE = (window.flatpickr && flatpickr.l10ns && flatpickr.l10ns.mk) ? flatpickr.l10ns.mk : 'default';
        var fpForm = null;
        if (window.flatpickr) {
            fpForm = flatpickr('#calFormDate', {
                locale: FP_LOCALE, dateFormat: 'Y-m-d', altInput: true, altFormat: 'd.m.Y',
                altInputClass: 'field cal-form-date', allowInput: true, disableMobile: true
            });
        }
        (function fillTimes() {
            var sel = document.getElementById('calFormTime'), html = '';
            for (var m = 0; m < 24 * 60; m += 15) html += '<option value="' + pad(Math.floor(m / 60)) + ':' + pad(m % 60) + '">' + pad(Math.floor(m / 60)) + ':' + pad(m % 60) + '</option>';
            sel.innerHTML = html; sel.value = DEFAULT_TIME;
        }());

        /* ---- Data ---- */
        function rangeFor() {
            if (mode === 'day')  return { from: cursor, to: cursor };
            if (mode === 'week') { var s = weekStart(cursor); return { from: s, to: addDays(s, 6) }; }
            var first = new Date(cursor.getFullYear(), cursor.getMonth(), 1);
            var s2 = addDays(first, -mondayIdx(first));
            return { from: s2, to: addDays(s2, 41) };
        }

        function load() {
            var r = rangeFor();
            render();  // skeleton/title immediately
            $.ajax({ url: API, data: { action: 'calendar_feed', from: ymd(r.from), to: ymd(r.to), assignee_id: $('#calAssignee').val() || 0 }, dataType: 'json' })
                .done(function (res) {
                    eventsByDay = {}; eventsById = {};
                    ((res && res.data) || []).forEach(function (e) {
                        eventsById[uidOf(e)] = e;
                        var key = String(startOf(e)).slice(0, 10);
                        (eventsByDay[key] = eventsByDay[key] || []).push(e);
                    });
                    render();
                })
                .fail(function () { render(); });
        }

        function dayEvents(key) {
            return (eventsByDay[key] || []).slice().sort(function (a, b) { return String(startOf(a)).localeCompare(String(startOf(b))); });
        }

        /* ---- Rendering ---- */
        function render() {
            if (mode === 'week') { $('#calTitle').text(weekTitle()); renderWeek(); }
            else if (mode === 'day') { $('#calTitle').text(MK_WD_FULL[mondayIdx(cursor)] + ', ' + longDate(ymd(cursor))); renderDay(); }
            else { $('#calTitle').text(MK_MONTHS[cursor.getMonth()] + ' ' + cursor.getFullYear()); renderMonth(); }
        }

        function weekTitle() {
            var s = weekStart(cursor), e = addDays(s, 6);
            var ds = s.getDate(), de = e.getDate();
            if (s.getMonth() === e.getMonth()) return ds + ' – ' + de + ' ' + MK_MONTHS[s.getMonth()] + ' ' + s.getFullYear();
            var left = ds + ' ' + MK_MON_SH[s.getMonth()], right = de + ' ' + MK_MON_SH[e.getMonth()];
            return left + ' – ' + right + ' ' + e.getFullYear();
        }

        function eventChip(e) {
            var k = kindOf(e);
            return '<button type="button" class="cal-ev hkind--' + k + '" data-uid="' + uidOf(e) + '" title="' + esc(HKIND[k] + ' · ' + esc(e.title)) + '">'
                + '<span class="cal-ev-time">' + hTime(startOf(e)) + '</span>'
                + '<span class="cal-ev-title">' + esc(e.title) + '</span></button>';
        }

        function renderMonth() {
            var first = new Date(cursor.getFullYear(), cursor.getMonth(), 1);
            var start = addDays(first, -mondayIdx(first));
            var todayKey = ymd(new Date()), curMonth = cursor.getMonth();
            var html = '<div class="cal-weekdays">' + MK_WD.map(function (w) { return '<div class="cal-wd">' + w + '</div>'; }).join('') + '</div>';
            html += '<div class="cal-grid">';
            for (var i = 0; i < 42; i++) {
                var d = addDays(start, i), key = ymd(d), evs = dayEvents(key);
                var cls = 'cal-cell';
                if (d.getMonth() !== curMonth) cls += ' is-other';
                if (key === todayKey) cls += ' is-today';
                if (mondayIdx(d) >= 5) cls += ' is-weekend';
                var inner = '<div class="cal-cell-head"><button type="button" class="cal-cell-add" data-day="' + key + '" title="Додај настан">+</button><span class="cal-cell-day">' + d.getDate() + '</span></div>';
                if (evs.length) {
                    // Cap to a fixed number of lines so every tile is the same height:
                    // up to MAX_LINES events, or (MAX_LINES-1) + a "+N повеќе" row.
                    var MAX_LINES = 3;
                    var visible = evs.length > MAX_LINES ? MAX_LINES - 1 : evs.length;
                    var shown = evs.slice(0, visible).map(eventChip).join('');
                    var more = evs.length > visible ? '<button type="button" class="cal-more" data-day="' + key + '">+' + (evs.length - visible) + ' повеќе</button>' : '';
                    inner += '<div class="cal-cell-evs">' + shown + more + '</div>';
                }
                html += '<div class="' + cls + '" data-day="' + key + '">' + inner + '</div>';
            }
            $('#calBody').html(html + '</div>');
        }

        function renderWeek() {
            var s = weekStart(cursor), todayKey = ymd(new Date());
            var html = '<div class="cal-week">';
            for (var i = 0; i < 7; i++) {
                var d = addDays(s, i), key = ymd(d), evs = dayEvents(key);
                var headCls = 'cal-week-colhead' + (key === todayKey ? ' is-today' : '');
                var body = evs.length ? evs.map(eventChip).join('') : '<div class="cal-week-empty">—</div>';
                html += '<div class="cal-week-col">'
                    + '<button type="button" class="' + headCls + '" data-nav-day="' + key + '">'
                    +   '<span class="cal-week-wd">' + MK_WD[i] + '</span><span class="cal-week-date">' + d.getDate() + '</span></button>'
                    + '<div class="cal-week-colbody" data-day="' + key + '">' + body + '</div>'
                    + '</div>';
            }
            $('#calBody').html(html + '</div>');
        }

        function dayEventRow(e) {
            var k = kindOf(e);
            return '<button type="button" class="cal-day-ev hkind-border--' + k + '" data-uid="' + uidOf(e) + '">'
                + '<div class="cal-day-ev-top"><span class="hkind-badge hkind--' + k + '">' + HKIND[k] + '</span>'
                + '<span class="cal-day-ev-time">' + hTime(startOf(e)) + '</span></div>'
                + '<div class="cal-day-ev-title">' + esc(e.title) + '</div>'
                + '<div class="cal-day-ev-meta">' + (e.source === 'case' ? esc(e.case_number) + (e.client_name ? ' · ' + esc(e.client_name) : '') : 'Личен настан · ' + esc(e.owner_name || '')) + (e.location ? ' · 📍 ' + esc(e.location) : '') + '</div>'
                + '</button>';
        }

        var DAY_HOUR_H = 48;       // px per hour in the day view
        var DAY_EV_MIN = 45;       // nominal block length (events have no duration)

        // Pack a day's events into side-by-side columns when they overlap.
        function layoutDayEvents(evs) {
            var items = evs.map(function (e) {
                var t = startOf(e), s = (+String(t).slice(11, 13)) * 60 + (+String(t).slice(14, 16));
                return { e: e, start: s, end: s + DAY_EV_MIN, col: 0, ncol: 1 };
            }).sort(function (a, b) { return a.start - b.start; });
            var i = 0;
            while (i < items.length) {
                var cluster = [items[i]], end = items[i].end, j = i + 1;
                while (j < items.length && items[j].start < end) { cluster.push(items[j]); end = Math.max(end, items[j].end); j++; }
                var colEnds = [];
                cluster.forEach(function (it) {
                    var placed = false;
                    for (var c = 0; c < colEnds.length; c++) { if (colEnds[c] <= it.start) { it.col = c; colEnds[c] = it.end; placed = true; break; } }
                    if (!placed) { it.col = colEnds.length; colEnds.push(it.end); }
                });
                cluster.forEach(function (it) { it.ncol = colEnds.length; });
                i = j;
            }
            return items;
        }

        function renderDay() {
            var key = ymd(cursor), evs = dayEvents(key);
            var hoursCol = '', lines = '';
            for (var h = 0; h < 24; h++) {
                hoursCol += '<div class="cal-dayv-hour" style="height:' + DAY_HOUR_H + 'px"><span>' + pad(h) + ':00</span></div>';
                lines += '<div class="cal-dayv-line" style="top:' + (h * DAY_HOUR_H) + 'px"></div>';
            }
            var evHtml = layoutDayEvents(evs).map(function (it) {
                var e = it.e, k = kindOf(e);
                var top = it.start / 60 * DAY_HOUR_H, hgt = Math.max(22, DAY_EV_MIN / 60 * DAY_HOUR_H);
                return '<button type="button" class="cal-dayv-ev hkind--' + k + '" data-uid="' + uidOf(e) + '"'
                    + ' style="top:' + top + 'px;height:' + hgt + 'px;left:calc(' + (it.col / it.ncol * 100) + '% + 3px);width:calc(' + (100 / it.ncol) + '% - 6px)">'
                    + '<span class="cal-dayv-ev-time">' + hTime(startOf(e)) + '</span> '
                    + '<span class="cal-dayv-ev-title">' + esc(e.title) + '</span></button>';
            }).join('');
            var nowLine = '';
            var now = new Date();
            if (ymd(now) === key) {
                nowLine = '<div class="cal-dayv-now" style="top:' + ((now.getHours() * 60 + now.getMinutes()) / 60 * DAY_HOUR_H) + 'px"></div>';
            }
            var banner = '<div class="cal-dayv-bar">' + (evs.length
                ? '<strong>' + evs.length + '</strong> ' + (evs.length === 1 ? 'настан' : 'настани') + ' за овој ден'
                : 'Нема закажани настани · кликни на час за да додадеш') + '</div>';
            var html = banner + '<div class="cal-dayv-grid" id="calDayGrid">'
                + '<div class="cal-dayv-hours">' + hoursCol + '</div>'
                + '<div class="cal-dayv-track" id="calDayTrack" style="height:' + (24 * DAY_HOUR_H) + 'px">' + lines + nowLine + evHtml + '</div>'
                + '</div>';
            $('#calBody').html(html);
            // Scroll to the first event (or ~8:00 on an empty day).
            var grid = document.getElementById('calDayGrid');
            if (grid) {
                var firstTop = evs.length ? Math.min.apply(null, layoutDayEvents(evs).map(function (it) { return it.start / 60 * DAY_HOUR_H; })) : 8 * DAY_HOUR_H;
                grid.scrollTop = Math.max(0, firstTop - DAY_HOUR_H);
            }
        }

        /* ---- Popovers (anchored to the clicked tile, Google-Calendar style) ---- */
        function closeModals() { $('#calDayModal, #calEventModal, #calFormModal').hide(); }
        function rectOf(a) { return (a && a.getBoundingClientRect) ? a.getBoundingClientRect() : a; }
        function showPop($pop, anchor) {
            closeModals();
            $pop.css('display', 'block');
            var card = $pop.find('.cal-pop-card')[0];
            if (!card) return;
            var vw = window.innerWidth, vh = window.innerHeight, gap = 8, pad = 10;
            var pw = card.offsetWidth, ph = card.offsetHeight, left, top;
            var r = rectOf(anchor);
            if (r && r.right != null) {
                left = r.right + gap;                                          // prefer the right of the tile
                if (left + pw > vw - pad) left = r.left - gap - pw;            // …flip to the left if no room
                if (left < pad) left = Math.min(Math.max(pad, r.left || pad), vw - pw - pad);
                top = (r.top != null) ? r.top : pad;
                if (vw < 560) left = Math.max(pad, (vw - pw) / 2);            // phones: center horizontally
            } else { left = (vw - pw) / 2; top = (vh - ph) / 2; }
            if (top + ph > vh - pad) top = vh - pad - ph;
            if (top < pad) top = pad;
            card.style.left = Math.round(left) + 'px';
            card.style.top = Math.round(top) + 'px';
        }

        function openDay(key, anchor) {
            var evs = dayEvents(key);
            $('#calDayTitle').text(longDate(key));
            $('#calDayModal').data('day', key);
            $('#calDayList').html(evs.length ? evs.map(dayEventRow).join('') : '<p class="cal-modal-empty">Нема настани за овој ден.</p>');
            showPop($('#calDayModal'), anchor);
        }

        function metaRow(icon, label, value) {
            return '<div class="cal-ev-row"><span class="cal-ev-row-ico">' + icon + '</span>'
                + '<span class="cal-ev-row-lbl">' + label + '</span>'
                + '<span class="cal-ev-row-val">' + value + '</span></div>';
        }

        function openEvent(uid, anchor) {
            var e = eventsById[uid];
            if (!e) return;
            var k = kindOf(e);
            $('#calEvBadge').html('<span class="hkind-badge hkind--' + k + '">' + HKIND[k] + '</span>'
                + (e.source === 'personal' ? ' <span class="cal-ev-tag">Личен настан</span>' : ''));
            var rows = '';
            rows += metaRow('🗓️', 'Кога', esc(longDate(startOf(e))) + ' · ' + hTime(startOf(e)));
            if (e.source === 'case') {
                rows += metaRow('📁', 'Предмет', esc(e.case_number) + (e.case_basis ? ' · ' + esc(e.case_basis) : ''));
                if (e.client_name) rows += metaRow('👤', 'Клиент', esc(e.client_name));
            }
            if (e.location)     rows += metaRow('📍', 'Локација', esc(e.location));
            if (e.assignees)    rows += metaRow('👥', 'Доделено на', esc(e.assignees));
            rows += metaRow('✍️', e.source === 'personal' ? 'Сопственик' : 'Креирано од', esc(e.creator_name || e.owner_name || '—'));

            var foot;
            if (e.source === 'case') {
                foot = '<a class="btn-modal-save" href="predmet.php?id=' + e.case_id + '">Отвори предмет →</a>';
            } else if (e.can_edit) {
                foot = '<button type="button" class="btn-modal-cancel cal-ev-del" data-uid="' + uid + '">Избриши</button>'
                     + '<button type="button" class="btn-modal-save cal-ev-edit" data-uid="' + uid + '">Уреди</button>';
            } else {
                foot = '';
            }
            var body = '<div class="cal-ev-detail-title">' + esc(e.title) + '</div>'
                + '<div class="cal-ev-rows">' + rows + '</div>'
                + (e.note ? '<div class="cal-ev-note">' + esc(e.note).replace(/\n/g, '<br>') + '</div>' : '')
                + (foot ? '<div class="cal-ev-foot">' + foot + '</div>' : '');
            $('#calEvBody').html(body);
            showPop($('#calEventModal'), anchor);
        }

        // openForm({ date:'YYYY-MM-DD' }) to add, or ({ event: e }) to edit.
        var formEditId = null, formKind = 'meeting';
        function setFormKind(k) {
            formKind = (k === 'other') ? 'other' : 'meeting';
            $('#calFormKind .hkind-chip').removeClass('is-active').filter('[data-kind="' + formKind + '"]').addClass('is-active');
        }
        function openForm(opts, anchor) {
            opts = opts || {};
            if (opts.event) {
                var e = opts.event;
                formEditId = e.id;
                $('#calFormTitle').text('Уреди настан');
                $('#calFormTitleInput').val(e.title);
                if (fpForm) fpForm.setDate(String(startOf(e)).slice(0, 10), true); else $('#calFormDate').val(String(startOf(e)).slice(0, 10));
                $('#calFormTime').val(hTime(startOf(e)));
                $('#calFormLoc').val(e.location || '');
                $('#calFormNote').val(e.note || '');
                setFormKind(e.kind);
            } else {
                formEditId = null;
                $('#calFormTitle').text('Нов настан');
                $('#calFormTitleInput').val('');
                if (fpForm) fpForm.setDate(opts.date || ymd(cursor), true); else $('#calFormDate').val(opts.date || ymd(cursor));
                $('#calFormTime').val(opts.time || DEFAULT_TIME);
                $('#calFormLoc').val(''); $('#calFormNote').val('');
                setFormKind('meeting');
            }
            showPop($('#calFormModal'), anchor);
            setTimeout(function () { $('#calFormTitleInput').focus(); }, 30);
        }

        /* ---- Events ---- */
        function step(dir) {
            if (mode === 'day') cursor = addDays(cursor, dir);
            else if (mode === 'week') cursor = addDays(cursor, dir * 7);
            else cursor = new Date(cursor.getFullYear(), cursor.getMonth() + dir, 1);
            load();
        }
        $('#calPrev').on('click', function () { step(-1); });
        $('#calNext').on('click', function () { step(1); });
        $('#calToday').on('click', function () { cursor = new Date(); cursor.setHours(0, 0, 0, 0); load(); });
        $('#calAssignee').on('change', load);
        $('#calAddBtn').on('click', function () { openForm({ date: mode === 'month' ? ymd(new Date()) : ymd(cursor) }, setAnchor(this)); });

        $('#calViews').on('click', '.cal-view-btn', function () {
            var v = $(this).data('view');
            if (v === mode) return;
            setMode(v); load();
        });

        // Popovers anchor to the tile that opened the stack; chained opens (add /
        // edit / an event clicked inside the day popover) reuse that same anchor so
        // they replace the previous popover in place instead of cascading sideways.
        var anchorRect = null;
        function setAnchor(el) { anchorRect = el.getBoundingClientRect(); return anchorRect; }

        $('#calBody').on('click', '.cal-ev', function (e) { e.stopPropagation(); openEvent($(this).data('uid'), setAnchor(this)); });
        $('#calBody').on('click', '.cal-dayv-ev', function (e) { e.stopPropagation(); openEvent($(this).data('uid'), setAnchor(this)); });
        // Day view: click an empty time slot to create an event at that hour.
        $('#calBody').on('click', '#calDayTrack', function (e) {
            if ($(e.target).closest('.cal-dayv-ev').length) return;
            var rect = this.getBoundingClientRect();
            var mins = Math.round(((e.clientY - rect.top) / DAY_HOUR_H * 60) / 15) * 15;
            mins = Math.max(0, Math.min(23 * 60 + 45, mins));
            anchorRect = { left: e.clientX, right: e.clientX, top: e.clientY, bottom: e.clientY };
            openForm({ date: ymd(cursor), time: pad(Math.floor(mins / 60)) + ':' + pad(mins % 60) }, anchorRect);
        });
        $('#calBody').on('click', '.cal-more', function (e) { e.stopPropagation(); openDay($(this).data('day'), setAnchor($(this).closest('.cal-cell')[0] || this)); });
        $('#calBody').on('click', '.cal-cell-add', function (e) { e.stopPropagation(); openForm({ date: $(this).data('day') }, setAnchor($(this).closest('.cal-cell')[0] || this)); });
        $('#calBody').on('click', '.cal-cell', function (e) {
            if ($(e.target).closest('.cal-ev, .cal-more, .cal-cell-add').length) return;
            openDay($(this).data('day'), setAnchor(this));
        });
        $('#calBody').on('click', '.cal-week-colhead', function () {
            cursor = new Date($(this).data('nav-day') + 'T00:00:00'); cursor.setHours(0, 0, 0, 0);
            setMode('day'); load();
        });
        $('#calBody').on('click', '.cal-week-colbody', function (e) {
            if ($(e.target).closest('.cal-ev').length) return;
            openForm({ date: $(this).data('day') }, setAnchor(this));
        });

        // Inside the day popover: event row → detail, "add" → form. Both reuse the
        // day popover's own anchor so they open exactly where the day popover is.
        $('#calDayList').on('click', '.cal-day-ev', function () { openEvent($(this).data('uid'), anchorRect); });
        $('#calDayAdd').on('click', function () { openForm({ date: $('#calDayModal').data('day') || ymd(cursor) }, anchorRect); });

        // Event popover "edit" — form opens where the event popover is.
        $('#calEventModal').on('click', '.cal-ev-edit', function () {
            var e = eventsById[$(this).data('uid')]; if (e) openForm({ event: e }, anchorRect);
        });
        $('#calEventModal').on('click', '.cal-ev-del', function () {
            var e = eventsById[$(this).data('uid')]; if (!e) return;
            confirmDialog({
                title: 'Бришење настан', danger: true, message: 'Избриши го настанот „' + e.title + '"? Ова не може да се врати.',
                confirmText: 'Избриши', cancelText: 'Откажи',
                onConfirm: function () {
                    $.post(API, { action: 'delete_event', event_id: e.id }, null, 'json')
                        .done(function (r) { if (r.success) { closeModals(); load(); } else toast(r.message || 'Грешка.', 'error'); });
                }
            });
        });

        // Form: kind chips + save.
        $('#calFormKind').on('click', '.hkind-chip', function () { setFormKind($(this).data('kind')); });
        $('#calFormSave').on('click', function () {
            var title = $('#calFormTitleInput').val().trim();
            var date = $('#calFormDate').val();
            var time = $('#calFormTime').val();
            if (!title || !date || !time) { toast('Внеси наслов, датум и време.', 'error'); return; }
            var data = {
                title: title, starts_at: date + ' ' + time, kind: formKind,
                location: $('#calFormLoc').val().trim(), note: $('#calFormNote').val().trim()
            };
            var $b = $(this).prop('disabled', true);
            if (formEditId) { data.action = 'update_event'; data.event_id = formEditId; }
            else { data.action = 'add_event'; }
            $.post(API, data, null, 'json')
                .done(function (r) {
                    if (r.success) { closeModals(); load(); }
                    else toast(r.message || 'Грешка.', 'error');
                }).always(function () { $b.prop('disabled', false); });
        });

        /* ---- Quick month/year jump (click the title) ---- */
        var jumpYear;
        function renderJump() {
            $('#calJumpYear').text(jumpYear);
            var curY = cursor.getFullYear(), curM = cursor.getMonth(), nowY = new Date().getFullYear(), nowM = new Date().getMonth();
            var html = '';
            for (var i = 0; i < 12; i++) {
                var cls = 'cal-jump-month';
                if (jumpYear === curY && i === curM) cls += ' is-active';
                else if (jumpYear === nowY && i === nowM) cls += ' is-now';
                html += '<button type="button" class="' + cls + '" data-m="' + i + '">' + MK_MON_SH[i] + '</button>';
            }
            $('#calJumpMonths').html(html);
        }
        function openJump() { jumpYear = cursor.getFullYear(); renderJump(); $('#calJump').css('display', 'block'); }
        function closeJump() { $('#calJump').hide(); }

        $('#calTitleBtn').on('click', function (e) { e.stopPropagation(); $('#calJump').is(':visible') ? closeJump() : openJump(); });
        $('#calJumpPrevY').on('click', function () { jumpYear--; renderJump(); });
        $('#calJumpNextY').on('click', function () { jumpYear++; renderJump(); });
        $('#calJumpMonths').on('click', '.cal-jump-month', function () {
            cursor = new Date(jumpYear, $(this).data('m'), 1); cursor.setHours(0, 0, 0, 0);
            closeJump(); load();
        });
        $(document).on('click', function (e) { if (!$(e.target).closest('.cal-titlewrap').length) closeJump(); });

        $('.cal-pop').on('click', '[data-close]', closeModals);
        $(document).on('keydown', function (e) { if (e.key === 'Escape') { closeModals(); closeJump(); } });

        setMode(mode);   // highlight the restored view in the switcher
        load();
    });
    </script>
</body>
</html>

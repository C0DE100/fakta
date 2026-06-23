/* ============================================================
   Предмети (cases) — listing, filters, grid/list views and the
   create/edit modal (parties, assignees, основ autocomplete,
   inline client creation). Depends on jQuery + global toast/
   confirmDialog (loaded via nav.php).
   ============================================================ */
$(function () {
    'use strict';

    var API = 'api/case_api.php';
    var CAN_MANAGE = window.FAKTA_CAN_MANAGE === true;

    var state = {
        status: 'active',
        view: localStorage.getItem('casesView') === 'list' ? 'list' : 'grid',
        search: '',
        assignee: '',
        sort: 'newest',
        page: 1
    };

    var clients = [];     // [{id, type, company_name, full_name}]
    var members = [];     // [{id, name, role}]
    var selectedAssignees = {}; // id -> true
    var editingId = null;
    var activeClientRow = null;  // the party row that opened the quick-client modal
    var searchTimer = null, basisTimer = null;

    /* ---------------- helpers ---------------- */

    function esc(s) { var d = document.createElement('div'); d.appendChild(document.createTextNode(s == null ? '' : s)); return d.innerHTML; }

    function clientName(c) { return (c.type === 'company' ? c.company_name : c.full_name) || 'Без име'; }

    var PALETTE = [
        { bg: '#eff6ff', fg: '#1d4ed8' }, { bg: '#fff7ed', fg: '#c2410c' },
        { bg: '#f0fdf4', fg: '#15803d' }, { bg: '#fdf4ff', fg: '#a21caf' },
        { bg: '#fef2f2', fg: '#b91c1c' }, { bg: '#f0f9ff', fg: '#0369a1' },
        { bg: '#fefce8', fg: '#a16207' }, { bg: '#f5f3ff', fg: '#6d28d9' }
    ];
    function color(name) {
        var s = (name || '').trim(), h = 0;
        for (var i = 0; i < s.length; i++) h = (h + s.charCodeAt(i)) % PALETTE.length;
        return PALETTE[h] || PALETTE[0];
    }
    function initials(name) {
        var p = (name || '').trim().split(/\s+/).filter(Boolean);
        if (!p.length) return '?';
        if (p.length === 1) return p[0].slice(0, 2).toUpperCase();
        return (p[0][0] + p[p.length - 1][0]).toUpperCase();
    }
    function fmtMoney(amount, currency) {
        if (amount == null || amount === '') return '';
        var n = parseFloat(amount);
        if (isNaN(n)) return '';
        var s = n.toLocaleString('de-DE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        return s + ' ' + (currency || 'ден');
    }
    function fmtDate(s) {
        if (!s) return '';
        var d = new Date(String(s).replace(' ', 'T'));
        if (isNaN(d.getTime())) return '';
        return ('0' + d.getDate()).slice(-2) + '.' + ('0' + (d.getMonth() + 1)).slice(-2) + '.' + d.getFullYear();
    }

    function post(action, data) {
        data = data || {}; data.action = action;
        return $.ajax({ url: API, type: 'POST', data: data, dataType: 'json' });
    }

    /* ---------------- list loading + rendering ---------------- */

    function loadList() {
        $('#casesList').html('<p class="list-msg" style="padding:1rem 0">Се вчитува...</p>');
        $('#casesPager').empty();
        $.ajax({
            url: API, dataType: 'json',
            data: {
                action: 'get_list', status: state.status, search: state.search,
                assignee_id: state.assignee, sort: state.sort, page: state.page
            },
            success: function (res) {
                if (!res.success) { $('#casesList').html('<p class="list-msg err" style="padding:1rem 0">' + esc(res.message) + '</p>'); return; }
                render(res.data, res.page, res.pages);
            },
            error: function () { $('#casesList').html('<p class="list-msg err" style="padding:1rem 0">Грешка при вчитување.</p>'); }
        });
    }

    function render(rows, page, pages) {
        if (!rows.length) {
            $('#casesList').html('<p class="list-msg" style="padding:1.5rem 0;text-align:center">'
                + (state.search ? 'Нема резултати за пребарувањето.' : (state.status === 'archived' ? 'Нема архивирани предмети.' : 'Сè уште нема предмети.')) + '</p>');
            $('#casesPager').empty();
            return;
        }
        var html = state.view === 'list'
            ? '<div class="case-list">' + rows.map(listRow).join('') + '</div>'
            : '<div class="case-grid">' + rows.map(card).join('') + '</div>';
        $('#casesList').html(html);

        if (pages <= 1) { $('#casesPager').empty(); return; }
        var p = '';
        for (var i = 1; i <= pages; i++) p += '<button class="page-btn' + (i === page ? ' active' : '') + '" data-page="' + i + '">' + i + '</button>';
        $('#casesPager').html(p);
    }

    function partyBits(role, name, fallback) {
        return '<span class="case-role">' + esc(role || fallback) + '</span>'
             + '<span class="case-pname">' + esc(name || '—') + '</span>';
    }

    function actionsHtml(r) {
        if (!CAN_MANAGE) return '';
        var archAction = r.archived_at
            ? '<button class="card-action" data-unarchive="' + r.id + '" title="Врати од архива"><svg class="card-action-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 7v6h6"/><path d="M21 17a9 9 0 0 0-9-9 9 9 0 0 0-6 2.3L3 13"/></svg></button>'
            : '<button class="card-action" data-archive="' + r.id + '" title="Архивирај"><svg class="card-action-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="5" x="2" y="3" rx="1"/><path d="M4 8v11a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8"/><path d="m9 13 3 3 3-3"/><path d="M12 16V8"/></svg></button>';
        return '<div class="client-card-actions">'
            + '<button class="card-action" data-edit="' + r.id + '" title="Уреди"><svg class="card-action-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg></button>'
            + archAction
            + '<button class="card-action card-action--danger" data-delete="' + r.id + '" title="Избриши"><svg class="card-action-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg></button>'
            + '</div>';
    }

    function assigneeAvatars(list) {
        if (!list || !list.length) return '';
        return list.slice(0, 4).map(function (a) {
            var c = color(a.name);
            return '<span class="case-assignee-av" style="background:' + c.bg + ';color:' + c.fg + '" title="' + esc(a.name) + '">' + esc(initials(a.name)) + '</span>';
        }).join('') + (list.length > 4 ? '<span class="case-assignee-av case-assignee-more">+' + (list.length - 4) + '</span>' : '');
    }

    function card(r) {
        var value = fmtMoney(r.value_amount, r.value_currency);
        return '<div class="case-card" data-id="' + r.id + '">'
            + actionsHtml(r)
            + '<div class="case-card-head">'
            +   '<span class="case-num">' + esc(r.case_number) + '</span>'
            +   (r.archived_at ? '<span class="case-badge case-badge--archived">Архивиран</span>' : '')
            + '</div>'
            + '<div class="case-parties">'
            +   '<div class="case-party">' + partyBits(r.client_role, r.client_name, 'Странка') + '</div>'
            +   '<div class="case-vs">против</div>'
            +   '<div class="case-party">' + partyBits(r.opponent_role, r.opponent_name, 'Спротивна странка') + '</div>'
            + '</div>'
            + '<div class="case-basis">' + esc(r.basis || '—') + '</div>'
            + '<div class="case-meta">'
            +   (value ? '<span class="case-chip case-chip--value">' + esc(value) + '</span>' : '')
            +   (r.admin_number ? '<span class="case-chip">№ ' + esc(r.admin_number) + '</span>' : '')
            + '</div>'
            + '<div class="case-card-footer">'
            +   '<div class="case-assignees">' + (assigneeAvatars(r.assignees) || '<span class="case-noassign">Незададен</span>') + '</div>'
            +   (r.created_by_name ? '<span class="case-creator" title="Креирано од ' + esc(r.created_by_name) + '">' + esc(r.created_by_name) + '</span>' : '')
            + '</div>'
            + '</div>';
    }

    function listRow(r) {
        var value = fmtMoney(r.value_amount, r.value_currency);
        return '<div class="case-row" data-id="' + r.id + '">'
            + '<div class="case-row-num">' + esc(r.case_number) + (r.archived_at ? '<span class="case-badge case-badge--archived">арх.</span>' : '') + '</div>'
            + '<div class="case-row-body">'
            +   '<div class="case-row-parties"><strong>' + esc(r.client_name || '—') + '</strong>'
            +     '<span class="case-row-vs">против</span><strong>' + esc(r.opponent_name || '—') + '</strong></div>'
            +   '<div class="case-row-basis">' + esc(r.basis || '—') + '</div>'
            + '</div>'
            + '<div class="case-row-value">' + (value ? esc(value) : '') + '</div>'
            + '<div class="case-row-admin">' + (r.admin_number ? '№ ' + esc(r.admin_number) : '') + '</div>'
            + '<div class="case-row-assignees">' + (assigneeAvatars(r.assignees) || '<span class="case-noassign">—</span>') + '</div>'
            + '<div class="case-row-actions">' + (CAN_MANAGE ? actionsHtml(r).replace('client-card-actions', 'case-row-actions-inner') : '') + '</div>'
            + '</div>';
    }

    /* ---------------- filters / tabs / view ---------------- */

    $('#caseSearch').on('input', function () {
        state.search = $(this).val().trim();
        clearTimeout(searchTimer);
        searchTimer = setTimeout(function () { state.page = 1; loadList(); }, 300);
    });
    $('#caseAssignee').on('change', function () { state.assignee = $(this).val(); state.page = 1; loadList(); });
    $('#caseSort').on('change', function () { state.sort = $(this).val(); state.page = 1; loadList(); });

    $('#caseTabs').on('click', '.case-tab', function () {
        $('.case-tab').removeClass('is-active'); $(this).addClass('is-active');
        state.status = $(this).data('status'); state.page = 1; loadList();
    });

    $('#viewToggle').on('click', '.view-toggle-btn', function () {
        $('.view-toggle-btn').removeClass('is-active'); $(this).addClass('is-active');
        state.view = $(this).data('view');
        localStorage.setItem('casesView', state.view);
        loadList();
    });
    // restore saved view
    $('.view-toggle-btn').removeClass('is-active').filter('[data-view="' + state.view + '"]').addClass('is-active');

    $('#casesPager').on('click', '[data-page]', function () { state.page = +$(this).data('page'); loadList(); });

    // open detail / handle row actions
    $('#casesList').on('click', '.case-card, .case-row', function (e) {
        if ($(e.target).closest('button').length) return;
        window.location.href = 'predmet.php?id=' + $(this).data('id');
    });
    $('#casesList').on('click', '[data-edit]', function (e) { e.stopPropagation(); openEdit($(this).data('edit')); });
    $('#casesList').on('click', '[data-archive]', function (e) { e.stopPropagation(); doArchive($(this).data('archive')); });
    $('#casesList').on('click', '[data-unarchive]', function (e) { e.stopPropagation(); doUnarchive($(this).data('unarchive')); });
    $('#casesList').on('click', '[data-delete]', function (e) { e.stopPropagation(); doDelete($(this).data('delete')); });

    function doArchive(id) {
        confirmDialog({
            title: 'Архивирање', message: 'Предметот ќе се премести во Архива и ќе добие архивски број. Продолжи?',
            confirmText: 'Архивирај', cancelText: 'Откажи',
            onConfirm: function () {
                post('archive', { id: id }).done(function (r) {
                    if (r.success) { toast(r.message, 'success'); loadList(); } else toast(r.message || 'Грешка.', 'error');
                });
            }
        });
    }
    function doUnarchive(id) {
        post('unarchive', { id: id }).done(function (r) {
            if (r.success) { toast(r.message, 'success'); loadList(); } else toast(r.message || 'Грешка.', 'error');
        });
    }
    function doDelete(id) {
        confirmDialog({
            title: 'Бришење', danger: true, message: 'Предметот ќе се премести во корпа. Продолжи?',
            confirmText: 'Избриши', cancelText: 'Откажи',
            onConfirm: function () {
                post('delete', { id: id }).done(function (r) {
                    if (r.success) { toast(r.message, 'success'); loadList(); } else toast(r.message || 'Грешка.', 'error');
                });
            }
        });
    }

    /* ---------------- reference data ---------------- */

    function loadClients(cb) {
        return $.ajax({ url: 'api/client_api.php', data: { action: 'get_all' }, dataType: 'json' })
            .done(function (res) { if (res.success) clients = res.data || []; if (cb) cb(); });
    }
    function loadMembers() {
        return $.ajax({ url: API, data: { action: 'members' }, dataType: 'json' })
            .done(function (res) {
                if (!res.success) return;
                members = res.data || [];
                var opts = '<option value="">Сите вработени</option>';
                members.forEach(function (m) { opts += '<option value="' + m.id + '">' + esc(m.name) + '</option>'; });
                $('#caseAssignee').html(opts);
            });
    }

    /* ---------------- create / edit modal ---------------- */

    function openModal() { $('#caseModal').addClass('open').removeAttr('aria-hidden'); $('body').addClass('modal-open'); }
    function closeModal() { $('#caseModal').removeClass('open').attr('aria-hidden', 'true'); $('body').removeClass('modal-open'); }

    function clientOptions(selectedId) {
        var o = '<option value="">— избери клиент —</option>';
        clients.forEach(function (c) {
            o += '<option value="' + c.id + '"' + (String(c.id) === String(selectedId) ? ' selected' : '') + '>' + esc(clientName(c)) + '</option>';
        });
        return o;
    }

    function clientPartyRow(party) {
        party = party || {};
        return '<div class="case-party-row" data-side="client">'
            + '<select class="field party-client">' + clientOptions(party.client_id) + '</select>'
            + '<button type="button" class="party-new-client" title="Нов клиент"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" x2="12" y1="5" y2="19"/><line x1="5" x2="19" y1="12" y2="12"/></svg></button>'
            + '<input type="text" class="field party-role" placeholder="Својство (пр. Тужител)" value="' + esc(party.role || '') + '">'
            + '<button type="button" class="party-remove" title="Отстрани">&times;</button>'
            + '</div>';
    }

    function opponentPartyRow(party) {
        party = party || {};
        var et = party.entity_type === 'company' ? 'company' : 'individual';
        return '<div class="case-party-row case-party-row--opp" data-side="opponent">'
            + '<input type="text" class="field party-name" placeholder="Име на странка" value="' + esc(party.name || '') + '">'
            + '<select class="field party-etype">'
            +   '<option value="individual"' + (et === 'individual' ? ' selected' : '') + '>Физичко</option>'
            +   '<option value="company"' + (et === 'company' ? ' selected' : '') + '>Правно</option>'
            + '</select>'
            + '<input type="text" class="field party-lawyer" placeholder="Адвокат (опц.)" value="' + esc(party.opposing_lawyer || '') + '">'
            + '<input type="text" class="field party-role" placeholder="Својство (пр. Тужен)" value="' + esc(party.role || '') + '">'
            + '<button type="button" class="party-remove" title="Отстрани">&times;</button>'
            + '</div>';
    }

    function roleLabel(role) {
        return role === 'admin' ? 'Адвокат' : (role === 'praktikant' ? 'Практикант' : 'Вработен');
    }
    function memberName(id) {
        var m = members.find(function (x) { return String(x.id) === String(id); });
        return m ? m.name : '';
    }

    // Selected assignees shown as removable tags.
    function renderSelectedAssignees() {
        var ids = Object.keys(selectedAssignees);
        $('#assigneeSelected').html(ids.length ? ids.map(function (id) {
            var name = memberName(id), c = color(name);
            return '<span class="assignee-tag">'
                + '<span class="assignee-tag-av" style="background:' + c.bg + ';color:' + c.fg + '">' + esc(initials(name)) + '</span>'
                + esc(name)
                + '<button type="button" class="assignee-tag-x" data-uid="' + id + '" aria-label="Отстрани">&times;</button></span>';
        }).join('') : '<span class="assignee-empty">Сè уште никој не е зададен</span>');
    }

    // Filterable dropdown of employees not yet selected (scales to many).
    function renderAssigneeDropdown(q) {
        q = (q || '').toLowerCase().trim();
        var list = members.filter(function (m) {
            return !selectedAssignees[m.id] && (!q || m.name.toLowerCase().indexOf(q) !== -1);
        });
        if (!members.length) { $('#assigneeDropdown').html('<div class="assignee-drop-empty">Нема вработени во канцеларијата</div>').show(); return; }
        if (!list.length) { $('#assigneeDropdown').html('<div class="assignee-drop-empty">' + (q ? 'Нема резултати.' : 'Сите се додадени.') + '</div>').show(); return; }
        $('#assigneeDropdown').html(list.slice(0, 50).map(function (m) {
            var c = color(m.name);
            return '<div class="assignee-drop-item" data-uid="' + m.id + '">'
                + '<span class="assignee-tag-av" style="background:' + c.bg + ';color:' + c.fg + '">' + esc(initials(m.name)) + '</span>'
                + '<span class="assignee-drop-name">' + esc(m.name) + '</span>'
                + '<span class="assignee-drop-role">' + roleLabel(m.role) + '</span></div>';
        }).join('')).show();
    }

    $('#assigneeSearch').on('focus input', function () { renderAssigneeDropdown($(this).val()); });
    $('#assigneeSearch').on('blur', function () { setTimeout(function () { $('#assigneeDropdown').hide(); }, 150); });
    $('#assigneeDropdown').on('mousedown', '.assignee-drop-item', function (e) {
        e.preventDefault();
        selectedAssignees[$(this).data('uid')] = true;
        $('#assigneeSearch').val('').focus();
        renderSelectedAssignees();
        renderAssigneeDropdown('');
    });
    $('#assigneeSelected').on('click', '.assignee-tag-x', function () {
        delete selectedAssignees[$(this).data('uid')];
        renderSelectedAssignees();
    });

    $('[data-add-party]').on('click', function () {
        var side = $(this).data('add-party');
        $(side === 'client' ? '#clientPartyList' : '#opponentPartyList')
            .append(side === 'client' ? clientPartyRow() : opponentPartyRow());
    });

    $('#caseForm').on('click', '.party-remove', function () {
        var $list = $(this).closest('.case-party-list');
        $(this).closest('.case-party-row').remove();
        // keep at least one client row present
        if ($list.attr('id') === 'clientPartyList' && !$list.children().length) $list.append(clientPartyRow());
    });

    // inline new-client
    $('#caseForm').on('click', '.party-new-client', function () {
        activeClientRow = $(this).closest('.case-party-row');
        openQuickClient();
    });

    function resetModal() {
        editingId = null;
        $('#caseId').val('');
        $('#caseForm')[0].reset();
        $('#caseCurrency').val('ден');
        $('#caseAlert').hide();
        $('#adminNumberRow').show();
        selectedAssignees = {};
        $('#clientPartyList').html(clientPartyRow());
        $('#opponentPartyList').html(opponentPartyRow());
        $('#assigneeSearch').val('');
        renderSelectedAssignees();
        $('#caseModalTitle').text('Нов предмет');
        $('#caseModalSub').text('Внеси ги основните податоци и странките');
        $('#basisSuggest').hide();
    }

    $('#caseNewBtn').on('click', function () { resetModal(); openModal(); });
    $('[data-case-close]').on('click', closeModal);
    $('#caseModal').on('click', function (e) { if (e.target === this) closeModal(); });

    function openEdit(id) {
        $.ajax({ url: API, data: { action: 'get_one', id: id }, dataType: 'json' }).done(function (res) {
            if (!res.success) { toast(res.message || 'Грешка.', 'error'); return; }
            var c = res.data;
            resetModal();
            editingId = c.id;
            $('#caseId').val(c.id);
            $('#caseBasis').val(c.basis || '');
            $('#caseValue').val(c.value_amount != null ? String(c.value_amount).replace('.', ',') : '');
            $('#caseCurrency').val(c.value_currency || 'ден');
            $('#adminNumberRow').hide(); // admin numbers managed on the detail page when editing

            var cParties = (c.parties || []).filter(function (p) { return p.side === 'client'; });
            var oParties = (c.parties || []).filter(function (p) { return p.side === 'opponent'; });
            $('#clientPartyList').html((cParties.length ? cParties : [{}]).map(clientPartyRow).join(''));
            $('#opponentPartyList').html(oParties.map(opponentPartyRow).join(''));

            selectedAssignees = {};
            (c.assignees || []).forEach(function (a) { selectedAssignees[a.id] = true; });
            $('#assigneeSearch').val('');
            renderSelectedAssignees();

            $('#caseModalTitle').text('Уреди предмет ' + (c.case_number || ''));
            $('#caseModalSub').text('Ажурирај податоци, странки и задолжени');
            openModal();
        });
    }

    function collectParties() {
        var out = [];
        $('#clientPartyList .case-party-row').each(function () {
            var cid = $(this).find('.party-client').val();
            if (!cid) return;
            out.push({ side: 'client', client_id: cid, role: $(this).find('.party-role').val().trim() });
        });
        $('#opponentPartyList .case-party-row').each(function () {
            var name = $(this).find('.party-name').val().trim();
            if (!name) return;
            out.push({
                side: 'opponent', name: name,
                entity_type: $(this).find('.party-etype').val(),
                opposing_lawyer: $(this).find('.party-lawyer').val().trim(),
                role: $(this).find('.party-role').val().trim()
            });
        });
        return out;
    }

    $('#caseForm').on('submit', function (e) {
        e.preventDefault();
        var parties = collectParties();
        if (!parties.some(function (p) { return p.side === 'client'; })) {
            showAlert('Додај барем една странка-клиент (избери клиент).'); return;
        }
        if (!$('#caseBasis').val().trim()) { showAlert('Основот е задолжителен.'); return; }

        var data = {
            basis: $('#caseBasis').val().trim(),
            value_amount: $('#caseValue').val().trim(),
            value_currency: $('#caseCurrency').val(),
            parties: JSON.stringify(parties),
            assignees: JSON.stringify(Object.keys(selectedAssignees))
        };
        var action;
        if (editingId) { action = 'update'; data.id = editingId; }
        else { action = 'create'; data.admin_number = $('#caseAdminNumber').val().trim(); }

        var $btn = $('#caseSaveBtn').prop('disabled', true).text('Се зачувува...');
        post(action, data).done(function (r) {
            if (r.success) { closeModal(); toast(r.message, 'success'); state.page = 1; loadList(); }
            else showAlert(r.message || 'Грешка.');
        }).fail(function () { showAlert('Грешка при комуникација.'); })
          .always(function () { $btn.prop('disabled', false).text('Зачувај'); });
    });

    function showAlert(msg) {
        $('#caseAlert').removeClass('alert-ok').addClass('alert-err').text(msg).show();
    }

    /* ---------------- основ autocomplete ---------------- */

    $('#caseBasis').on('input', function () {
        var q = $(this).val().trim();
        clearTimeout(basisTimer);
        if (q.length < 2) { $('#basisSuggest').hide(); return; }
        basisTimer = setTimeout(function () {
            $.ajax({ url: API, data: { action: 'suggest_basis', q: q }, dataType: 'json' }).done(function (res) {
                var items = (res.data || []);
                if (!items.length) { $('#basisSuggest').hide(); return; }
                $('#basisSuggest').html(items.map(function (it) {
                    return '<div class="basis-suggest-item" data-val="' + esc(it.basis) + '">'
                        + '<span>' + esc(it.basis) + '</span><span class="basis-suggest-count">' + it.cnt + '×</span></div>';
                }).join('')).show();
            });
        }, 200);
    });
    $('#basisSuggest').on('mousedown', '.basis-suggest-item', function (e) {
        e.preventDefault();
        $('#caseBasis').val($(this).data('val'));
        $('#basisSuggest').hide();
    });
    $('#caseBasis').on('blur', function () { setTimeout(function () { $('#basisSuggest').hide(); }, 150); });

    /* ---------------- quick client modal ---------------- */

    function openQuickClient() {
        $('#qcFormIndividual')[0].reset(); $('#qcFormCompany')[0].reset();
        $('#quickClientAlert').hide();
        $('.qc-type').removeClass('is-active').filter('[data-qc-type="individual"]').addClass('is-active');
        $('#qcFormIndividual').show(); $('#qcFormCompany').hide();
        $('#quickClientModal').addClass('open').removeAttr('aria-hidden');
    }
    function closeQuickClient() { $('#quickClientModal').removeClass('open').attr('aria-hidden', 'true'); }

    $('.qc-type').on('click', function () {
        $('.qc-type').removeClass('is-active'); $(this).addClass('is-active');
        var t = $(this).data('qc-type');
        $('#qcFormIndividual').toggle(t === 'individual');
        $('#qcFormCompany').toggle(t === 'company');
    });
    $('#quickClientClose, #quickClientCancel, #quickClientCancel2').on('click', closeQuickClient);

    $('#qcFormIndividual, #qcFormCompany').on('submit', function (e) {
        e.preventDefault();
        var isCo = this.id === 'qcFormCompany';
        var $btn = $(this).find('button[type="submit"]').prop('disabled', true).text('Се зачувува...');
        var payload = $(this).serialize() + '&action=' + (isCo ? 'create_company' : 'create_individual');
        $.ajax({ url: 'api/client_api.php', type: 'POST', data: payload, dataType: 'json' })
            .done(function (res) {
                if (!res.success) { $('#quickClientAlert').removeClass('alert-ok').addClass('alert-err').text(res.message).show(); return; }
                // refresh client list, then select the new one in the originating row
                loadClients(function () {
                    refreshClientSelects();
                    if (activeClientRow) activeClientRow.find('.party-client').val(res.id);
                    toast('Клиентот е креиран и поврзан.', 'success');
                    closeQuickClient();
                });
            })
            .fail(function () { $('#quickClientAlert').removeClass('alert-ok').addClass('alert-err').text('Грешка при комуникација.').show(); })
            .always(function () { $btn.prop('disabled', false).text('Зачувај клиент'); });
    });

    // Re-render every client <select> preserving current selection.
    function refreshClientSelects() {
        $('#clientPartyList .party-client').each(function () {
            var cur = $(this).val();
            $(this).html(clientOptions(cur));
        });
    }

    /* ---------------- trash ---------------- */

    if (CAN_MANAGE) {
        var $tm = $('#caseTrashModal'), didRestore = false;
        $('#caseTrashBtn').on('click', function () { didRestore = false; $tm.addClass('open').removeAttr('aria-hidden'); $('body').addClass('modal-open'); loadTrash(); });
        function closeTrash() { $tm.removeClass('open').attr('aria-hidden', 'true'); $('body').removeClass('modal-open'); if (didRestore) loadList(); }
        $('#caseTrashClose').on('click', closeTrash);
        $tm.on('click', function (e) { if (e.target === this) closeTrash(); });

        function loadTrash() {
            $('#caseTrashList').html('<p class="trash-empty">Се вчитува…</p>');
            $.ajax({ url: API, data: { action: 'list_deleted' }, dataType: 'json' }).done(function (res) {
                var rows = res.data || [];
                if (!rows.length) { $('#caseTrashList').html('<p class="trash-empty">Корпата е празна.</p>'); return; }
                $('#caseTrashList').html(rows.map(function (c) {
                    return '<div class="trash-row"><div class="trash-row-info">'
                        + '<div class="trash-row-name">' + esc(c.case_number) + ' · ' + esc(c.basis || '—') + '</div>'
                        + '<div class="trash-row-meta">избришан ' + fmtDate(c.deleted_at) + '</div></div>'
                        + '<div class="trash-row-actions">'
                        + '<button class="btn-secondary trash-restore" data-id="' + c.id + '">Врати</button>'
                        + '<button class="btn-secondary btn-secondary--danger trash-purge" data-id="' + c.id + '">Избриши трајно</button>'
                        + '</div></div>';
                }).join(''));
            });
        }
        $('#caseTrashList').on('click', '.trash-restore', function () {
            post('restore', { id: $(this).data('id') }).done(function (r) {
                if (r.success) { didRestore = true; toast('Предметот е вратен.', 'success'); loadTrash(); } else toast(r.message, 'error');
            });
        });
        $('#caseTrashList').on('click', '.trash-purge', function () {
            var id = $(this).data('id');
            confirmDialog({
                title: 'Трајно бришење', danger: true, message: 'Предметот ќе биде трајно избришан и не може да се врати. Продолжи?',
                confirmText: 'Избриши трајно', cancelText: 'Откажи',
                onConfirm: function () { post('force_delete', { id: id }).done(function (r) { if (r.success) { toast('Трајно избришан.', 'success'); loadTrash(); } else toast(r.message, 'error'); }); }
            });
        });
    }

    $(document).on('keydown', function (e) {
        if (e.key !== 'Escape') return;
        if ($('#quickClientModal').hasClass('open')) closeQuickClient();
        else if ($('#caseModal').hasClass('open')) closeModal();
    });

    /* ---------------- init ---------------- */
    $.when(loadMembers(), loadClients()).always(function () {
        // Deep-link: predmet.php "Уреди" sends ?edit=ID — open the edit modal.
        var editId = new URLSearchParams(location.search).get('edit');
        if (editId && CAN_MANAGE) {
            openEdit(editId);
            history.replaceState(null, '', 'predmeti.php');
        }
    });
    loadList();
});

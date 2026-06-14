$(document).ready(function () {

    // ---- Frosted nav on scroll ----
    const $nav = $('.site-nav');
    const mainContent = document.querySelector('.main-content');
    (mainContent || window).addEventListener('scroll', function () {
        const scrollTop = mainContent ? mainContent.scrollTop : window.scrollY;
        $nav.toggleClass('nav-scrolled', scrollTop > 48);
    }, { passive: true });

    // ---- Dark mode ----
    function updateDarkModeLabel() {
        const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
        $('#darkModeToggle .sidebar-btn-label').text(isDark ? 'Светла тема' : 'Темна тема');
    }
    updateDarkModeLabel(); // set correct label on page load

    $('#darkModeToggle').on('click', function () {
        const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
        if (isDark) {
            document.documentElement.removeAttribute('data-theme');
            localStorage.setItem('darkMode', '0');
        } else {
            document.documentElement.setAttribute('data-theme', 'dark');
            localStorage.setItem('darkMode', '1');
        }
        updateDarkModeLabel();
    });

    $('#sidebarToggle').on('click', function () {
        const $sidebar = $('#sidebar');
        $sidebar.toggleClass('collapsed');
        const isNowCollapsed = $sidebar.hasClass('collapsed');
        localStorage.setItem('sidebarCollapsed', isNowCollapsed ? '1' : '0');
        if (isNowCollapsed) {
            $('.sidebar-submenu').removeClass('open');
            $('.sidebar-btn--parent').removeClass('sidebar-btn--open');
            hideFlyout();
        }
    });

    $('.sidebar-btn[data-scroll]').on('click', function () {
        const target = document.getElementById($(this).data('scroll'));
        if (target) target.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });

    $('#btnInvoicesToggle').on('click', function () {
        $(this).toggleClass('sidebar-btn--open');
        $('#submenuInvoices').toggleClass('open');
    });

    // ---- Collapsed flyout ----

    let $flyout    = null;
    let flyoutTimer = null;

    function showFlyout($group) {
        clearTimeout(flyoutTimer);
        if ($flyout) { $flyout.remove(); $flyout = null; }

        const rect = $group.find('.sidebar-btn--parent')[0].getBoundingClientRect();
        $flyout = $group.find('.sidebar-submenu')
            .clone()
            .removeClass('open')
            .addClass('sidebar-flyout')
            .css('top', rect.top + 'px')
            .appendTo('body');

        $flyout.on('mouseenter', function () {
            clearTimeout(flyoutTimer);
        }).on('mouseleave', function () {
            flyoutTimer = setTimeout(hideFlyout, 80);
        });
    }

    function hideFlyout() {
        if ($flyout) { $flyout.remove(); $flyout = null; }
    }

    $('.sidebar-group').on('mouseenter', function () {
        if (!$('#sidebar').hasClass('collapsed')) return;
        showFlyout($(this));
    }).on('mouseleave', function () {
        if (!$('#sidebar').hasClass('collapsed')) return;
        flyoutTimer = setTimeout(hideFlyout, 80);
    });

    let allClients = [];
    let editingClientId = null; // null = create mode; otherwise the id being edited
    const PAGE_SIZE = 15;

    const MK_MONTHS = ['Јануари','Февруари','Март','Април','Мај','Јуни','Јули','Август','Септември','Октомври','Ноември','Декември'];

    // ---- Modal ----

    function openModal(panelId) {
        $('.modal-panel').removeClass('active');
        if (panelId) $('#' + panelId).addClass('active');
        $('#clientModal').addClass('open').removeAttr('aria-hidden');
        $('body').addClass('modal-open');
    }

    function closeModal() {
        $('#clientModal').removeClass('open').attr('aria-hidden', 'true');
        $('body').removeClass('modal-open');
        editingClientId = null;
        $('#panelFormCompany .profile-hero-title').text('Ново правно лице');
        $('#panelFormIndividual .profile-hero-title').text('Ново физичко лице');
        // Reset to default step after CSS transition
        setTimeout(function () {
            $('.modal-panel').removeClass('active');
            $('#panelSelectType').addClass('active');
        }, 200);
    }

    function switchModalPanel(panelId) {
        $('.modal-panel').removeClass('active');
        if (panelId) $('#' + panelId).addClass('active');
    }

    // Open modal from action cards
    $('[data-modal-open]').on('click', function () {
        const panelId = $(this).data('modal-open');
        openModal(panelId);
    });

    // Close: button or backdrop click
    $(document).on('click', '[data-modal-close]', closeModal);
    $('#clientModal').on('click', function (e) {
        if (e.target === this) closeModal();
    });
    $(document).on('keydown', function (e) {
        if (e.key === 'Escape' && $('#clientModal').hasClass('open')) closeModal();
    });

    // Internal navigation within modal (back arrows)
    $(document).on('click', '[data-go-modal]', function () {
        switchModalPanel($(this).data('go-modal'));
    });

    // ---- Clients ----

    $('#btnCompany').on('click', function () {
        editingClientId = null;
        $('#panelFormCompany .profile-hero-title').text('Ново правно лице');
        $('#formCompany')[0].reset();
        $('#alertCompany').hide();
        resetModalAvatar('#avatarCompany');
        switchModalPanel('panelFormCompany');
    });

    $('#btnIndividual').on('click', function () {
        editingClientId = null;
        $('#panelFormIndividual .profile-hero-title').text('Ново физичко лице');
        $('#formIndividual')[0].reset();
        $('#alertIndividual').hide();
        resetModalAvatar('#avatarIndividual');
        switchModalPanel('panelFormIndividual');
    });

    // Open the create modal directly on a pre-filled form, in edit mode.
    function openEditClient(client) {
        editingClientId = client.id;
        if (client.type === 'company') {
            $('#pravno_naziv').val(client.company_name || '');
            $('#pravno_sediste').val(client.headquarters || '');
            $('#pravno_embs').val(client.embs || '');
            $('#pravno_edb').val(client.edb || '');
            $('#pravno_upravitel').val(client.manager || '');
            $('#pravno_email').val(client.email || '');
            $('#pravno_phone').val(client.phone || '');
            $('#alertCompany').hide();
            updateModalAvatar('#pravno_naziv', '#avatarCompany');
            $('#panelFormCompany .profile-hero-title').text('Уреди правно лице');
            openModal('panelFormCompany');
        } else {
            $('#fizicko_ime').val(client.full_name || '');
            $('#fizicko_adresa').val(client.address || '');
            $('#fizicko_embg').val(client.embg || '');
            $('#fizicko_licna').val(client.id_card_number || '');
            $('#fizicko_email').val(client.email || '');
            $('#fizicko_phone').val(client.phone || '');
            $('#alertIndividual').hide();
            updateModalAvatar('#fizicko_ime', '#avatarIndividual');
            $('#panelFormIndividual .profile-hero-title').text('Уреди физичко лице');
            openModal('panelFormIndividual');
        }
    }

    $('#formCompany, #formIndividual').on('submit', function (e) {
        e.preventDefault();
        const $form   = $(this);
        const $btn    = $form.find('button[type="submit"]');
        const alertId = '#' + $form.data('alert');

        const isEdit  = editingClientId !== null;
        const isCo    = $form.attr('id') === 'formCompany';
        const action  = isEdit ? (isCo ? 'update_company' : 'update_individual') : $form.data('action');
        let   payload = $form.serialize() + '&action=' + action;
        if (isEdit) payload += '&id=' + editingClientId;

        $btn.prop('disabled', true).text('Се зачувува...');

        $.ajax({
            url: 'api/client_api.php',
            type: 'POST',
            data: payload,
            dataType: 'json',
            success: function (res) {
                if (res.success) {
                    if (isEdit) {
                        closeModal();
                    } else {
                        showAlert(alertId, 'ok', res.message);
                        $form[0].reset();
                        resetModalAvatar(isCo ? '#avatarCompany' : '#avatarIndividual');
                    }
                    loadClients();
                    loadClientsFilter();
                } else {
                    showAlert(alertId, 'err', res.message);
                }
            },
            error: function () {
                showAlert(alertId, 'err', 'Грешка при комуникација со серверот.');
            },
            complete: function () {
                $btn.prop('disabled', false).text('Зачувај');
            }
        });
    });

    function loadClients() {
        $('#clientsList').html('<p class="list-msg" style="padding:0.75rem 0">Се вчитува...</p>');
        $('#clientsPager').empty();

        $.ajax({
            url: 'api/client_api.php',
            data: { action: 'get_all' },
            dataType: 'json',
            success: function (res) {
                if (!res.success) { $('#clientsList').html('<p class="list-msg err" style="padding:0.75rem 0">' + res.message + '</p>'); return; }
                allClients = res.data;
                renderClients(getFilteredClients(), 1);
            },
            error: function () { $('#clientsList').html('<p class="list-msg err" style="padding:0.75rem 0">Грешка при вчитување.</p>'); }
        });
    }

    function getFilteredClients() {
        const q = $('#searchClients').val().trim().toLowerCase();
        if (!q) return allClients;
        return allClients.filter(function (c) {
            const name  = (c.type === 'company' ? c.company_name : c.full_name) || '';
            const email = c.email || '';
            const phone = c.phone || '';
            return (name + ' ' + email + ' ' + phone).toLowerCase().indexOf(q) !== -1;
        });
    }

    // SVG snippets reused across client cards
    const ICON_COMPANY    = '<path d="M6 22V4a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v18Z"/><path d="M6 12H4a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h2"/><path d="M18 9h2a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2h-2"/><path d="M10 6h4"/><path d="M10 10h4"/><path d="M10 14h4"/><path d="M10 18h4"/>';
    const ICON_INDIVIDUAL = '<path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>';
    const ICON_MAIL       = '<rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/>';
    const ICON_PHONE      = '<path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>';
    const ICON_EDIT       = '<path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4Z"/>';
    const ICON_TRASH      = '<path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>';

    function svgIcon(cls, paths) {
        return '<svg xmlns="http://www.w3.org/2000/svg" class="' + cls + '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' + paths + '</svg>';
    }

    function renderClients(clients, page) {
        const total = clients.length;
        const pages = Math.ceil(total / PAGE_SIZE) || 1;
        page = Math.min(Math.max(page, 1), pages);

        if (!total) { $('#clientsList').html('<p class="list-msg">Нема резултати.</p>'); $('#clientsPager').empty(); return; }

        const slice = clients.slice((page - 1) * PAGE_SIZE, page * PAGE_SIZE);
        let cards = '';
        $.each(slice, function (_, c) {
            const name    = (c.type === 'company' ? c.company_name : c.full_name) || '';
            const isCo    = c.type === 'company';
            const typeIco = svgIcon('client-type-icon', isCo ? ICON_COMPANY : ICON_INDIVIDUAL);
            const typeTxt = isCo ? 'Правно лице' : 'Физичко лице';

            const email = c.email
                ? '<div class="client-info-row">' + svgIcon('client-info-icon', ICON_MAIL) + '<span>' + escapeHtml(c.email) + '</span></div>'
                : '<div class="client-info-row client-info-empty">' + svgIcon('client-info-icon', ICON_MAIL) + '<span>Нема е-пошта</span></div>';
            const phone = c.phone
                ? '<div class="client-info-row">' + svgIcon('client-info-icon', ICON_PHONE) + '<span>' + escapeHtml(c.phone) + '</span></div>'
                : '<div class="client-info-row client-info-empty">' + svgIcon('client-info-icon', ICON_PHONE) + '<span>Нема телефон</span></div>';

            let footer;
            if (c.created_by_name) {
                const cc = avatarColor(c.created_by_name);
                footer = '<div class="client-creator-avatar" style="background:' + cc.bg + ';color:' + cc.fg + '" title="Креирано од ' + escapeHtml(c.created_by_name) + '">' + initials(c.created_by_name) + '</div>';
            } else {
                footer = '<div class="client-creator-avatar client-creator-avatar--unknown" title="Креатор непознат">?</div>';
            }

            // Praktikant may view/create clients but not edit or delete them.
            var actionsHtml = (window.FAKTA_ROLE === 'praktikant') ? '' :
                    '<div class="client-card-actions">'
                +     '<button type="button" class="card-action" data-edit="' + c.id + '" title="Уреди" aria-label="Уреди">' + svgIcon('card-action-icon', ICON_EDIT) + '</button>'
                +     '<button type="button" class="card-action card-action--danger" data-delete="' + c.id + '" title="Избриши" aria-label="Избриши">' + svgIcon('card-action-icon', ICON_TRASH) + '</button>'
                +   '</div>';

            cards += '<div class="client-card" data-id="' + c.id + '">'
                +   actionsHtml
                +   '<div class="client-card-top">'
                +     '<div class="client-avatar">' + initials(name) + '</div>'
                +     '<div class="client-card-id">'
                +       '<span class="client-card-name">' + escapeHtml(name) + '</span>'
                +       '<span class="client-type-badge" title="' + typeTxt + '">' + typeIco + typeTxt + '</span>'
                +     '</div>'
                +   '</div>'
                +   '<div class="client-card-info">' + email + phone + '</div>'
                +   '<div class="client-card-footer">' + footer + '</div>'
                + '</div>';
        });
        $('#clientsList').html('<div class="client-grid">' + cards + '</div>');

        if (pages <= 1) { $('#clientsPager').empty(); return; }
        let pHtml = '';
        for (let i = 1; i <= pages; i++) {
            pHtml += '<button class="page-btn' + (i === page ? ' active' : '') + '" data-page="' + i + '">' + i + '</button>';
        }
        $('#clientsPager').html(pHtml);
    }

    $('#searchClients').on('input', function () { renderClients(getFilteredClients(), 1); });
    $('#clientsPager').on('click', '[data-page]', function () { renderClients(getFilteredClients(), +$(this).data('page')); });

    // Open the client profile — but let inner links / action buttons handle themselves.
    $('#clientsList').on('click', '.client-card', function (e) {
        if ($(e.target).closest('a, .client-card-actions').length) return;
        window.location.href = 'klient.php?id=' + $(this).data('id');
    });

    // Edit / delete straight from a card.
    $('#clientsList').on('click', '[data-edit]', function (e) {
        e.stopPropagation();
        const id = String($(this).data('edit'));
        const client = allClients.find(function (c) { return String(c.id) === id; });
        if (client) openEditClient(client);
    });

    $('#clientsList').on('click', '[data-delete]', function (e) {
        e.stopPropagation();
        deleteClient($(this).data('delete'));
    });

    function deleteClient(id) {
        if (!confirm('Дали сте сигурни дека сакате да го избришете овој клиент?')) return;
        $.ajax({
            url: 'api/client_api.php',
            type: 'POST',
            data: { action: 'delete', id: id },
            dataType: 'json',
            success: function (res) {
                if (res.success) { allClients = allClients.filter(function (c) { return String(c.id) !== String(id); }); renderClients(getFilteredClients(), 1); }
                else alert(res.message);
            },
            error: function () { alert('Грешка при бришење.'); }
        });
    }

    // ---- Invoices ----

    let invoiceSearchTimer = null;

    function getInvoiceParams(page) {
        return {
            action:    'get_list',
            search:    $('#searchInvoices').val().trim(),
            month:     $('#filterMonth').val(),
            client_id: parseInt($('#filterClient').val(), 10) || 0,
            page:      page || 1
        };
    }

    function loadInvoices(page) {
        $('#invoicesList').html('<p class="list-msg" style="padding:0.75rem 0">Се вчитува...</p>');
        $('#invoicesPager').empty();

        $.ajax({
            url:      'api/invoice_api.php',
            data:     getInvoiceParams(page),
            dataType: 'json',
            success: function (res) {
                if (!res.success) { $('#invoicesList').html('<p class="list-msg err" style="padding:0.75rem 0">' + res.message + '</p>'); return; }
                renderInvoices(res.data, res.page, res.pages);
            },
            error: function () { $('#invoicesList').html('<p class="list-msg err" style="padding:0.75rem 0">Грешка при вчитување.</p>'); }
        });
    }

    function statusTag(status) {
        const s = (status || '').toLowerCase();
        let cls = 'inv-tag';
        if (s === 'платена')   cls += ' inv-tag--paid';
        else if (s === 'испратена') cls += ' inv-tag--sent';
        else if (s === 'нацрт')     cls += ' inv-tag--draft';
        else if (s === 'откажана')  cls += ' inv-tag--cancelled';
        return '<span class="' + cls + '">' + escapeHtml(status) + '</span>';
    }

    function renderInvoices(invoices, page, pages) {
        if (!invoices.length) {
            $('#invoicesList').html('<p class="list-msg" style="padding:0.75rem 0">Нема резултати.</p>');
            $('#invoicesPager').empty();
            return;
        }

        // Group by YYYY-MM, preserving DESC order
        const groups = {};
        const groupOrder = [];
        invoices.forEach(function (inv) {
            const key = (inv.date || '').slice(0, 7);
            if (!groups[key]) { groups[key] = []; groupOrder.push(key); }
            groups[key].push(inv);
        });

        let html = '';
        groupOrder.forEach(function (key, idx) {
            const parts = key.split('-');
            const label = MK_MONTHS[parseInt(parts[1], 10) - 1] + ' ' + parts[0];
            html += '<div class="inv-month-group' + (idx === 0 ? ' is-first' : '') + '">';
            html += '<div class="inv-month-sep">' + label + '</div>';
            groups[key].forEach(function (inv) {
                html += '<div class="inv-row">'
                    + '<span class="inv-num">'    + escapeHtml(inv.number)      + '</span>'
                    + '<span class="inv-name">'   + escapeHtml(inv.client_name) + '</span>'
                    + '<span class="inv-date">'   + formatDate(inv.date)        + '</span>'
                    + '<span class="inv-status">' + statusTag(inv.status)       + '</span>'
                    + '</div>';
            });
            html += '</div>';
        });
        $('#invoicesList').html(html);

        if (pages <= 1) { $('#invoicesPager').empty(); return; }
        let pHtml = '';
        for (let i = 1; i <= pages; i++) {
            pHtml += '<button class="page-btn' + (i === page ? ' active' : '') + '" data-inv-page="' + i + '">' + i + '</button>';
        }
        $('#invoicesPager').html(pHtml);
    }

    function loadClientsFilter() {
        const $sel = $('#filterClient');
        if ($sel.find('option').length > 1) return; // already populated
        $.ajax({
            url:      'api/client_api.php',
            data:     { action: 'get_all' },
            dataType: 'json',
            success: function (res) {
                if (!res.success) return;
                let opts = '<option value="">Сите клиенти</option>';
                res.data.forEach(function (c) {
                    const name = c.type === 'company' ? c.company_name : c.full_name;
                    opts += '<option value="' + c.id + '">' + escapeHtml(name) + '</option>';
                });
                $sel.html(opts);
            }
        });
    }

    function formatDate(dateStr) {
        const parts = (dateStr || '').slice(0, 10).split('-');
        if (parts.length < 3) return dateStr;
        return parts[2] + ' ' + MK_MONTHS[parseInt(parts[1], 10) - 1];
    }

    // ---- Dashboard ----

    function loadDashboard() {
        // Invoice stats + recent list are admin-only; the elements are only
        // rendered for admins, so guard on their presence.
        if ($('#statMonth').length) {
            $.ajax({
                url:      'api/invoice_api.php',
                data:     { action: 'get_stats' },
                dataType: 'json',
                success: function (res) {
                    if (!res.success) return;
                    $('#statMonth').text(res.stats.this_month);
                    $('#statSent').text(res.stats.sent);
                    $('#statDraft').text(res.stats.draft);
                    renderRecent(res.recent);
                }
            });
        }

        // Client count is available to every role.
        $.ajax({
            url:      'api/client_api.php',
            data:     { action: 'get_all' },
            dataType: 'json',
            success: function (res) {
                if (res.success) $('#statClients').text(res.data.length);
            }
        });
    }

    function renderRecent(invoices) {
        if (!invoices || !invoices.length) {
            $('#dashRecentList').html('<p class="list-msg" style="padding:0.75rem 0">Сè уште нема фактури.</p>');
            return;
        }
        let html = '';
        invoices.forEach(function (inv) {
            html += '<div class="inv-row">'
                + '<span class="inv-num">'    + escapeHtml(inv.number)      + '</span>'
                + '<span class="inv-name">'   + escapeHtml(inv.client_name) + '</span>'
                + '<span class="inv-date">'   + formatDate(inv.date)        + '</span>'
                + '<span class="inv-status">' + statusTag(inv.status)       + '</span>'
                + '</div>';
        });
        $('#dashRecentList').html(html);
    }

    // Load on page ready — only on pages that have these sections
    if ($('#dashboard').length) {
        loadDashboard();
    }
    if ($('#invoicesList').length) {
        loadInvoices(1);
        loadClientsFilter();
    }
    if ($('#clientsList').length) {
        loadClients();
    }

    $('#searchInvoices').on('input', function () {
        clearTimeout(invoiceSearchTimer);
        invoiceSearchTimer = setTimeout(function () { loadInvoices(1); }, 300);
    });
    $('#filterMonth').on('change', function () { loadInvoices(1); });
    $('#filterClient').on('change', function () { loadInvoices(1); });
    $('#invoicesPager').on('click', '[data-inv-page]', function () { loadInvoices(+$(this).data('inv-page')); });

    // ---- Utilities ----

    function showAlert(selector, type, message) {
        $(selector).removeClass('alert-ok alert-err').addClass('alert-' + type).text(message).show();
    }

    function escapeHtml(text) {
        if (!text) return '';
        const d = document.createElement('div');
        d.appendChild(document.createTextNode(text));
        return d.innerHTML;
    }

    // Soft background / readable foreground pairs for initial avatars.
    const AVATAR_PALETTE = [
        { bg: '#eff6ff', fg: '#1d4ed8' }, // blue
        { bg: '#fff7ed', fg: '#c2410c' }, // orange
        { bg: '#f0fdf4', fg: '#15803d' }, // green
        { bg: '#fdf4ff', fg: '#a21caf' }, // fuchsia
        { bg: '#fef2f2', fg: '#b91c1c' }, // red
        { bg: '#f0f9ff', fg: '#0369a1' }, // sky
        { bg: '#fefce8', fg: '#a16207' }, // amber
        { bg: '#f5f3ff', fg: '#6d28d9' }  // violet
    ];

    // Deterministic colour from a name, so a client always gets the same avatar.
    function avatarColor(name) {
        const s = (name || '').trim();
        let hash = 0;
        for (let i = 0; i < s.length; i++) hash = (hash + s.charCodeAt(i)) % AVATAR_PALETTE.length;
        return AVATAR_PALETTE[hash] || AVATAR_PALETTE[0];
    }

    // Up to two uppercase initials from the first and last word of a name.
    function initials(name) {
        const parts = (name || '').trim().split(/\s+/).filter(Boolean);
        if (!parts.length) return '?';
        if (parts.length === 1) return parts[0].slice(0, 2).toUpperCase();
        return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
    }

    // ---- Live avatar in the client modal ----
    // Remember each avatar's default icon so we can restore it when the name is cleared.
    const avatarDefaults = {
        '#avatarCompany':    $('#avatarCompany').html(),
        '#avatarIndividual': $('#avatarIndividual').html()
    };

    function updateModalAvatar(inputEl, avatarSel) {
        const name = $(inputEl).val().trim();
        const $av  = $(avatarSel);
        if (!name) { $av.html(avatarDefaults[avatarSel]); return; }
        $av.text(initials(name));
    }

    function resetModalAvatar(avatarSel) {
        $(avatarSel).html(avatarDefaults[avatarSel]);
    }

    $('#pravno_naziv').on('input', function () { updateModalAvatar(this, '#avatarCompany'); });
    $('#fizicko_ime').on('input',  function () { updateModalAvatar(this, '#avatarIndividual'); });

});

$(document).ready(function () {

    // ---- Frosted nav on scroll ----
    const $nav = $('.site-nav');
    window.addEventListener('scroll', function () {
        $nav.toggleClass('nav-scrolled', window.scrollY > 48);
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
        $('#formCompany')[0].reset();
        $('#alertCompany').hide();
        switchModalPanel('panelFormCompany');
    });

    $('#btnIndividual').on('click', function () {
        $('#formIndividual')[0].reset();
        $('#alertIndividual').hide();
        switchModalPanel('panelFormIndividual');
    });

    $('#formCompany, #formIndividual').on('submit', function (e) {
        e.preventDefault();
        const $form   = $(this);
        const $btn    = $form.find('button[type="submit"]');
        const alertId = '#' + $form.data('alert');

        $btn.prop('disabled', true).text('Се зачувува...');

        $.ajax({
            url: 'api/client_api.php',
            type: 'POST',
            data: $form.serialize() + '&action=' + $form.data('action'),
            dataType: 'json',
            success: function (res) {
                showAlert(alertId, res.success ? 'ok' : 'err', res.message);
                if (res.success) { $form[0].reset(); loadClients(); loadClientsFilter(); }
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
            const name = (c.type === 'company' ? c.company_name : c.full_name) || '';
            return name.toLowerCase().indexOf(q) !== -1;
        });
    }

    function renderClients(clients, page) {
        const total = clients.length;
        const pages = Math.ceil(total / PAGE_SIZE) || 1;
        page = Math.min(Math.max(page, 1), pages);

        if (!total) { $('#clientsList').html('<p class="list-msg">Нема резултати.</p>'); $('#clientsPager').empty(); return; }

        const slice = clients.slice((page - 1) * PAGE_SIZE, page * PAGE_SIZE);
        let html  = '';
        $.each(slice, function (_, c) {
            const name  = c.type === 'company' ? c.company_name : c.full_name;
            const badge = c.type === 'company'
                ? '<span class="badge-company">Правно лице</span>'
                : '<span class="badge-individual">Физичко лице</span>';
            html += '<div class="client-row" data-id="' + c.id + '"><span>' + escapeHtml(name) + '</span>' + badge + '</div>';
        });
        $('#clientsList').html(html);

        if (pages <= 1) { $('#clientsPager').empty(); return; }
        let pHtml = '';
        for (let i = 1; i <= pages; i++) {
            pHtml += '<button class="page-btn' + (i === page ? ' active' : '') + '" data-page="' + i + '">' + i + '</button>';
        }
        $('#clientsPager').html(pHtml);
    }

    $('#searchClients').on('input', function () { renderClients(getFilteredClients(), 1); });
    $('#clientsPager').on('click', '[data-page]', function () { renderClients(getFilteredClients(), +$(this).data('page')); });

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

    // Load on page ready — only on pages that have these sections
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

});

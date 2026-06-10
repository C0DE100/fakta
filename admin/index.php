<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('super_admin');

$apiUrl     = fakta_url('api/admin_api.php');
$profileUrl = fakta_url('admin/company.php');
$editUrl    = fakta_url('admin/company-edit.php');

$adminTitle = 'Компании';
require __DIR__ . '/../includes/admin_header.php';
?>

    <main>
        <!-- Stats -->
        <div class="stats">
            <div class="stat"><div class="n" id="statCompanies">—</div><div class="l">Компании</div></div>
            <div class="stat"><div class="n" id="statUsers">—</div><div class="l">Корисници</div></div>
            <div class="stat"><div class="n" id="statAdmins">—</div><div class="l">Администратори</div></div>
        </div>

        <!-- Companies -->
        <section class="panel">
            <div class="panel-head">
                <h2>Компании <span class="count" id="companiesCount"></span></h2>
                <div class="panel-tools">
                    <div class="search">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
                        <input type="search" id="searchCompanies" placeholder="Пребарај компанија…">
                    </div>
                    <button class="btn btn-soft" id="toggleCompanyForm">
                        <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><line x1="12" x2="12" y1="5" y2="19"/><line x1="5" x2="19" y1="12" y2="12"/></svg>
                        Нова компанија
                    </button>
                </div>
            </div>

            <div class="form-wrap" id="companyForm">
                <div class="alert" id="alertCompany"></div>
                <form id="formCompany">
                    <div class="form-grid">
                        <div class="fld"><label>Име на компанија *</label><input name="name" required></div>
                        <div class="fld"><label>Е-пошта</label><input type="email" name="email"></div>
                        <div class="fld"><label>Телефон</label><input name="phone"></div>
                        <div class="fld"><label>Адреса</label><input name="address"></div>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Креирај компанија</button>
                        <button type="button" class="btn btn-ghost" data-cancel="companyForm">Откажи</button>
                    </div>
                </form>
            </div>

            <table>
                <thead><tr><th>Компанија</th><th>Е-пошта</th><th>Телефон</th><th>Корисници</th><th>Креирана</th></tr></thead>
                <tbody id="companiesBody"></tbody>
            </table>
            <div class="pager" id="companiesPager" style="display:none"></div>
        </section>
    </main>

    <!-- Right-click context menu -->
    <div class="ctx-menu" id="ctxMenu">
        <button class="ctx-item" data-act="open">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h6v6"/><path d="M10 14 21 3"/><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/></svg>
            Отвори
        </button>
        <button class="ctx-item" data-act="edit">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>
            Уреди
        </button>
        <div class="ctx-sep"></div>
        <button class="ctx-item danger" data-act="delete">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
            Избриши
        </button>
    </div>

    <!-- Confirm delete modal -->
    <div class="modal-back" id="confirmBack">
        <div class="modal">
            <h3>Избриши компанија?</h3>
            <p id="confirmText"></p>
            <div class="modal-actions">
                <button class="btn btn-ghost" id="confirmCancel">Откажи</button>
                <button class="btn btn-danger" id="confirmDelete">Избриши трајно</button>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="<?= htmlspecialchars(fakta_url('js/admin.js')) ?>"></script>
    <script>
    const API = <?= json_encode($apiUrl) ?>;
    const PROFILE_URL = <?= json_encode($profileUrl) ?>;
    const EDIT_URL = <?= json_encode($editUrl) ?>;
    const A = window.FaktaAdmin;
    const PER_PAGE = 20;

    let coState = { search: '', page: 1 };
    let ctxId = null, ctxName = '';

    function loadStats(){
        $.getJSON(API, { action: 'get_stats' }, function(res){
            if(!res.success) return;
            $('#statCompanies').text(res.data.companies);
            $('#statUsers').text(res.data.users);
            $('#statAdmins').text(res.data.admins);
        });
    }

    function loadCompanies(){
        $.getJSON(API, { action: 'list_companies', search: coState.search, page: coState.page }, function(res){
            if(!res.success) return;
            const list = res.data || [];
            $('#companiesCount').text(res.total ? '(' + res.total + ')' : '');
            $('#companiesBody').html(list.length ? list.map(c =>
                '<tr class="row-click" data-id="' + c.id + '" data-name="' + A.esc(c.name) + '">'
                + '<td><div class="name-cell"><span class="avatar">' + A.esc(A.initials(c.name)) + '</span><span class="cell-strong">' + A.esc(c.name) + '</span></div></td>'
                + '<td>' + (c.email ? A.esc(c.email) : '<span class="muted">—</span>') + '</td>'
                + '<td>' + (c.phone ? A.esc(c.phone) : '<span class="muted">—</span>') + '</td>'
                + '<td><span class="pill pill-count">' + (c.user_count|0) + '</span></td>'
                + '<td class="muted">' + A.fmtDate(c.created_at) + '</td>'
                + '</tr>'
            ).join('') : '<tr><td colspan="5" class="empty">' + (coState.search ? 'Нема резултати за пребарувањето.' : 'Сè уште нема компании. Креирај ја првата.') + '</td></tr>');

            const $p = $('#companiesPager');
            if(res.pages > 1){ $p.show(); A.renderPager($p, { page: res.page, pages: res.pages, total: res.total, perPage: PER_PAGE, onGo: p => { coState.page = p; loadCompanies(); window.scrollTo({top:0,behavior:'smooth'}); } }); }
            else $p.hide().empty();
        });
    }

    // Search (debounced) + form toggle
    $('#searchCompanies').on('input', A.debounce(function(){ coState.search = this.value.trim(); coState.page = 1; loadCompanies(); }, 300));
    $('#toggleCompanyForm').on('click', () => $('#companyForm').toggleClass('open'));
    $('[data-cancel]').on('click', function(){ $('#' + $(this).data('cancel')).removeClass('open'); });

    // Row: left-click opens profile
    $('#companiesBody').on('click', 'tr.row-click', function(){ location.href = PROFILE_URL + '?id=' + $(this).data('id'); });

    // Row: right-click → context menu
    $('#companiesBody').on('contextmenu', 'tr.row-click', function(e){
        e.preventDefault();
        ctxId = $(this).data('id'); ctxName = $(this).data('name');
        const m = $('#ctxMenu');
        const mw = 180, mh = 130;
        let x = e.clientX, y = e.clientY;
        if(x + mw > window.innerWidth)  x = window.innerWidth  - mw - 8;
        if(y + mh > window.innerHeight) y = window.innerHeight - mh - 8;
        m.css({ left: x + 'px', top: y + 'px' }).addClass('open');
    });
    function closeCtx(){ $('#ctxMenu').removeClass('open'); }
    $(document).on('click scroll', closeCtx);
    $(window).on('resize', closeCtx);

    $('#ctxMenu').on('click', '.ctx-item', function(e){
        e.stopPropagation();
        const act = $(this).data('act');
        closeCtx();
        if(act === 'open')   location.href = PROFILE_URL + '?id=' + ctxId;
        if(act === 'edit')   location.href = EDIT_URL + '?id=' + ctxId;
        if(act === 'delete') openConfirm(ctxId, ctxName);
    });

    // Confirm delete modal
    let delId = null;
    function openConfirm(id, name){
        delId = id;
        $('#confirmText').html('Ова трајно ќе ја избрише <b>' + A.esc(name) + '</b> и СИТЕ нејзини податоци (корисници, клиенти, фактури, документи). Дејството е неповратно.');
        $('#confirmBack').addClass('open');
    }
    $('#confirmCancel, #confirmBack').on('click', function(e){ if(e.target === this) $('#confirmBack').removeClass('open'); });
    $('#confirmDelete').on('click', function(){
        if(!delId) return;
        const $b = $(this).prop('disabled', true).text('Се брише…');
        $.post(API, { action: 'delete_company', id: delId }, function(res){
            $('#confirmBack').removeClass('open');
            $b.prop('disabled', false).text('Избриши трајно');
            if(res.success){ coState.page = 1; loadCompanies(); loadStats(); }
            else alert(res.message || 'Грешка.');
        }, 'json').fail(() => { $b.prop('disabled', false).text('Избриши трајно'); alert('Серверска грешка.'); });
    });

    // Create company
    $('#formCompany').on('submit', function(e){
        e.preventDefault();
        $.post(API, $(this).serialize() + '&action=create_company', function(res){
            A.alertBox('#alertCompany', res.success, res.message);
            if(res.success){ e.target.reset(); coState.page = 1; loadCompanies(); loadStats(); }
        }, 'json').fail(() => A.alertBox('#alertCompany', false, 'Серверска грешка.'));
    });

    loadStats();
    loadCompanies();
    </script>
</body>
</html>

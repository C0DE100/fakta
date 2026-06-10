<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('super_admin');
require_once __DIR__ . '/../classes/Company.php';

$companyId = (int) ($_GET['id'] ?? 0);
$company   = new Company($GLOBALS['fakta_db']);
$row       = $companyId > 0 ? $company->getById($companyId) : null;

if (!$row) {
    header('Location: ' . fakta_url('admin/index.php'));
    exit;
}

// Initials for the avatar.
$parts    = preg_split('/\s+/', trim($row['name']));
$initials = mb_strtoupper(mb_substr($parts[0] ?? '', 0, 1) . (isset($parts[1]) ? mb_substr($parts[1], 0, 1) : ''));
$since    = date('d.m.Y', strtotime($row['created_at']));

$apiUrl  = fakta_url('api/admin_api.php');
$editUrl = fakta_url('admin/company-edit.php') . '?id=' . $companyId;
$homeUrl = fakta_url('admin/index.php');

$adminTitle = $row['name'];
$adminBack  = ['url' => $homeUrl, 'label' => 'Компании'];
require __DIR__ . '/../includes/admin_header.php';
?>

    <main>
        <!-- Profile header -->
        <div class="profile-head">
            <span class="avatar-lg"><?= htmlspecialchars($initials) ?></span>
            <div class="meta">
                <h1><?= htmlspecialchars($row['name']) ?></h1>
                <div class="profile-info">
                    <span class="it">
                        <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>
                        <?= $row['email'] ? htmlspecialchars($row['email']) : '<span class="muted">Без е-пошта</span>' ?>
                    </span>
                    <span class="it">
                        <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92Z"/></svg>
                        <?= $row['phone'] ? htmlspecialchars($row['phone']) : '<span class="muted">Без телефон</span>' ?>
                    </span>
                    <span class="it">
                        <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/></svg>
                        <?= $row['address'] ? htmlspecialchars($row['address']) : '<span class="muted">Без адреса</span>' ?>
                    </span>
                </div>
                <div class="since">Во базата од <?= htmlspecialchars($since) ?></div>
            </div>
            <div class="profile-actions">
                <a href="<?= htmlspecialchars($editUrl) ?>" class="btn btn-ghost">
                    <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>
                    Уреди
                </a>
                <button class="btn btn-danger-ghost" id="deleteCompany">
                    <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                    Избриши
                </button>
            </div>
        </div>

        <!-- Users -->
        <section class="panel">
            <div class="panel-head">
                <h2>Корисници <span class="count" id="usersCount"></span></h2>
                <div class="panel-tools">
                    <div class="search">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
                        <input type="search" id="searchUsers" placeholder="Пребарај корисник…">
                    </div>
                    <button class="btn btn-soft" id="toggleUserForm">
                        <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><line x1="12" x2="12" y1="5" y2="19"/><line x1="5" x2="19" y1="12" y2="12"/></svg>
                        Нов корисник
                    </button>
                </div>
            </div>

            <div class="form-wrap" id="userForm">
                <div class="alert" id="alertUser"></div>
                <form id="formUser">
                    <input type="hidden" name="company_id" value="<?= (int) $companyId ?>">
                    <div class="form-grid">
                        <div class="fld"><label>Име и презиме *</label><input name="name" required></div>
                        <div class="fld"><label>Е-пошта *</label><input type="email" name="email" required></div>
                        <div class="fld"><label>Лозинка *</label><input type="text" name="password" required></div>
                        <div class="fld"><label>Улога *</label><select name="role" required>
                            <option value="admin">Администратор</option>
                            <option value="employee">Вработен</option>
                            <option value="praktikant">Практикант</option>
                        </select></div>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Креирај корисник</button>
                        <button type="button" class="btn btn-ghost" data-cancel="userForm">Откажи</button>
                    </div>
                </form>
            </div>

            <table>
                <thead><tr><th>Име</th><th>Е-пошта</th><th>Улога</th><th>Креиран</th></tr></thead>
                <tbody id="usersBody"></tbody>
            </table>
            <div class="pager" id="usersPager" style="display:none"></div>
        </section>
    </main>

    <!-- Confirm delete modal -->
    <div class="modal-back" id="confirmBack">
        <div class="modal">
            <h3>Избриши компанија?</h3>
            <p>Ова трајно ќе ја избрише <b><?= htmlspecialchars($row['name']) ?></b> и СИТЕ нејзини податоци (корисници, клиенти, фактури, документи). Дејството е неповратно.</p>
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
    const HOME = <?= json_encode($homeUrl) ?>;
    const COMPANY_ID = <?= (int) $companyId ?>;
    const A = window.FaktaAdmin;
    const PER_PAGE = 15;

    let uState = { search: '', page: 1 };

    function loadUsers(){
        $.getJSON(API, { action: 'list_company_users', company_id: COMPANY_ID, search: uState.search, page: uState.page }, function(res){
            if(!res.success) return;
            const list = res.data || [];
            $('#usersCount').text(res.total ? '(' + res.total + ')' : '');
            $('#usersBody').html(list.length ? list.map(u =>
                '<tr>'
                + '<td><div class="name-cell"><span class="avatar">' + A.esc(A.initials(u.name)) + '</span><span class="cell-strong">' + A.esc(u.name) + '</span></div></td>'
                + '<td>' + A.esc(u.email) + '</td>'
                + '<td><span class="pill ' + A.roleClass(u.role) + '">' + A.roleLabel(u.role) + '</span></td>'
                + '<td class="muted">' + A.fmtDate(u.created_at) + '</td>'
                + '</tr>'
            ).join('') : '<tr><td colspan="4" class="empty">' + (uState.search ? 'Нема резултати.' : 'Оваа компанија сè уште нема корисници.') + '</td></tr>');

            const $p = $('#usersPager');
            if(res.pages > 1){ $p.show(); A.renderPager($p, { page: res.page, pages: res.pages, total: res.total, perPage: PER_PAGE, onGo: p => { uState.page = p; loadUsers(); } }); }
            else $p.hide().empty();
        });
    }

    $('#searchUsers').on('input', A.debounce(function(){ uState.search = this.value.trim(); uState.page = 1; loadUsers(); }, 300));
    $('#toggleUserForm').on('click', () => $('#userForm').toggleClass('open'));
    $('[data-cancel]').on('click', function(){ $('#' + $(this).data('cancel')).removeClass('open'); });

    $('#formUser').on('submit', function(e){
        e.preventDefault();
        const form = this;
        $.post(API, $(form).serialize() + '&action=create_user', function(res){
            A.alertBox('#alertUser', res.success, res.message);
            if(res.success){ form.reset(); form.company_id.value = COMPANY_ID; uState.page = 1; loadUsers(); }
        }, 'json').fail(() => A.alertBox('#alertUser', false, 'Серверска грешка.'));
    });

    // Delete company
    $('#deleteCompany').on('click', () => $('#confirmBack').addClass('open'));
    $('#confirmCancel, #confirmBack').on('click', function(e){ if(e.target === this) $('#confirmBack').removeClass('open'); });
    $('#confirmDelete').on('click', function(){
        const $b = $(this).prop('disabled', true).text('Се брише…');
        $.post(API, { action: 'delete_company', id: COMPANY_ID }, function(res){
            if(res.success){ location.href = HOME; }
            else { $b.prop('disabled', false).text('Избриши трајно'); alert(res.message || 'Грешка.'); }
        }, 'json').fail(() => { $b.prop('disabled', false).text('Избриши трајно'); alert('Серверска грешка.'); });
    });

    loadUsers();
    </script>
</body>
</html>

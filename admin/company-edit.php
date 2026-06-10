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

$apiUrl     = fakta_url('api/admin_api.php');
$profileUrl = fakta_url('admin/company.php') . '?id=' . $companyId;

$adminTitle = 'Уреди — ' . $row['name'];
$adminBack  = ['url' => $profileUrl, 'label' => $row['name']];
require __DIR__ . '/../includes/admin_header.php';
?>

    <main style="max-width:720px">
        <section class="panel">
            <div class="panel-head"><h2>Уреди компанија</h2></div>
            <div style="padding:22px">
                <div class="alert" id="alertEdit"></div>
                <form id="formEdit">
                    <input type="hidden" name="id" value="<?= (int) $companyId ?>">
                    <div class="form-grid" style="grid-template-columns:repeat(2,1fr)">
                        <div class="fld"><label>Име на компанија *</label><input name="name" required value="<?= htmlspecialchars($row['name']) ?>"></div>
                        <div class="fld"><label>Е-пошта</label><input type="email" name="email" value="<?= htmlspecialchars($row['email'] ?? '') ?>"></div>
                        <div class="fld"><label>Телефон</label><input name="phone" value="<?= htmlspecialchars($row['phone'] ?? '') ?>"></div>
                        <div class="fld"><label>Адреса</label><input name="address" value="<?= htmlspecialchars($row['address'] ?? '') ?>"></div>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Зачувај промени</button>
                        <a href="<?= htmlspecialchars($profileUrl) ?>" class="btn btn-ghost">Откажи</a>
                    </div>
                </form>
            </div>
        </section>
    </main>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="<?= htmlspecialchars(fakta_url('js/admin.js')) ?>"></script>
    <script>
    const API = <?= json_encode($apiUrl) ?>;
    const PROFILE = <?= json_encode($profileUrl) ?>;
    const A = window.FaktaAdmin;

    $('#formEdit').on('submit', function(e){
        e.preventDefault();
        const $btn = $(this).find('button[type=submit]').prop('disabled', true).text('Се зачувува…');
        $.post(API, $(this).serialize() + '&action=update_company', function(res){
            A.alertBox('#alertEdit', res.success, res.message);
            $btn.prop('disabled', false).text('Зачувај промени');
            if(res.success){ setTimeout(() => location.href = PROFILE, 500); }
        }, 'json').fail(() => { $btn.prop('disabled', false).text('Зачувај промени'); A.alertBox('#alertEdit', false, 'Серверска грешка.'); });
    });
    </script>
</body>
</html>

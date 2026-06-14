<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

// Super-admin manages tenants only — keep them out of the company app.
if (current_role() === 'super_admin') {
    header('Location: ' . fakta_url('admin/index.php'));
    exit;
}

require_once __DIR__ . '/classes/Encryption.php';
require_once __DIR__ . '/classes/Client.php';

$companyId = current_company_id();
$clientId  = (int) ($_GET['id'] ?? 0);

$clientObj = new Client($GLOBALS['fakta_db'], new Encryption(ENCRYPTION_KEY));
$client    = $clientId > 0 ? $clientObj->getById($clientId, $companyId) : null;

/** Up to two uppercase initials (Cyrillic-safe). */
function client_initials(?string $name): string
{
    $parts = preg_split('/\s+/', trim((string) $name), -1, PREG_SPLIT_NO_EMPTY);
    if (!$parts) return '?';
    if (count($parts) === 1) return mb_strtoupper(mb_substr($parts[0], 0, 2));
    return mb_strtoupper(mb_substr($parts[0], 0, 1) . mb_substr($parts[count($parts) - 1], 0, 1));
}

if ($client) {
    $isCompany = $client['type'] === 'company';
    $name      = $isCompany ? ($client['company_name'] ?? '') : ($client['full_name'] ?? '');
    $createdAt = !empty($client['created_at']) ? date('d.m.Y', strtotime($client['created_at'])) : '';

    $rows = $isCompany ? [
        ['Назив',     'company_name', $client['company_name'], true, 'text'],
        ['Седиште',   'headquarters', $client['headquarters'], true, 'text'],
        ['ЕМБС',      'embs',         $client['embs'],         true, 'text'],
        ['ЕДБ',       'edb',          $client['edb'],          true, 'text'],
        ['Управител', 'manager',      $client['manager'],      true, 'text'],
    ] : [
        ['Име и презиме',       'full_name',      $client['full_name'],      true, 'text'],
        ['Адреса',              'address',        $client['address'],        true, 'text'],
        ['ЕМБГ',                'embg',           $client['embg'],           true, 'text'],
        ['Број на лична карта', 'id_card_number', $client['id_card_number'], true, 'text'],
    ];
    $rows[] = ['Е-пошта', 'email', $client['email'], false, 'email'];
    $rows[] = ['Телефон', 'phone', $client['phone'], false, 'tel'];
}
?>
<!DOCTYPE html>
<html lang="mk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $client ? htmlspecialchars($name) . ' – ' : '' ?>Факта</title>
    <script>if(localStorage.getItem('darkMode')==='1')document.documentElement.setAttribute('data-theme','dark');</script>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="css/style.css">
</head>

<body class="min-h-screen">

    <?php include 'includes/nav.php'; ?>

    <div class="app-layout">

    <?php $currentPage = 'klienti'; include 'includes/sidebar.php'; ?>

    <div class="main-content">
    <div class="max-w-7xl mx-auto px-4 pb-16">

        <a href="klienti.php" class="profile-back">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="m15 18-6-6 6-6"/>
            </svg>
            Назад кон клиенти
        </a>

        <?php if (!$client): ?>
            <div class="bg-white border border-slate-200 rounded-xl shadow-sm p-10 text-center mt-4">
                <p class="text-slate-500">Клиентот не е пронајден или е избришан.</p>
            </div>
        <?php else: ?>

        <div class="profile-card mt-4">
            <div class="profile-card-head">
                <div class="client-avatar client-avatar--xl"><?= htmlspecialchars(client_initials($name)) ?></div>
                <div class="profile-head-text">
                    <h1 class="profile-name"><?= htmlspecialchars($name) ?></h1>
                    <span class="client-type-badge">
                        <svg xmlns="http://www.w3.org/2000/svg" class="client-type-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <?php if ($isCompany): ?>
                                <path d="M6 22V4a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v18Z"/><path d="M6 12H4a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h2"/><path d="M18 9h2a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2h-2"/><path d="M10 6h4"/><path d="M10 10h4"/><path d="M10 14h4"/><path d="M10 18h4"/>
                            <?php else: ?>
                                <path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>
                            <?php endif; ?>
                        </svg>
                        <?= $isCompany ? 'Правно лице' : 'Физичко лице' ?>
                    </span>
                </div>
                <div class="profile-head-actions">
                    <button type="button" id="btnEdit" class="btn-secondary" data-view-action>
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4Z"/>
                        </svg>
                        Уреди
                    </button>
                    <button type="button" id="btnDelete" class="btn-danger" data-view-action>
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                        </svg>
                        Избриши
                    </button>
                    <button type="button" id="btnCancel" class="btn-modal-cancel" data-edit-action>Откажи</button>
                    <button type="submit" form="profileForm" id="btnSave" class="btn-modal-save" data-edit-action>Зачувај</button>
                </div>
            </div>

            <form id="profileForm" class="profile-details">
                <input type="hidden" name="action" value="<?= $isCompany ? 'update_company' : 'update_individual' ?>">
                <input type="hidden" name="id" value="<?= (int) $client['id'] ?>">
                <div id="profileAlert" class="profile-alert" style="display:none;"></div>

                <?php foreach ($rows as [$label, $fname, $value, $req, $itype]): ?>
                <div class="detail-row">
                    <label class="detail-label" for="f_<?= $fname ?>"><?= $label ?></label>
                    <input class="detail-input" id="f_<?= $fname ?>" type="<?= $itype ?>" name="<?= $fname ?>" value="<?= htmlspecialchars((string) $value) ?>" placeholder="Не е внесено"<?= $req ? ' required' : '' ?> readonly>
                </div>
                <?php endforeach; ?>
            </form>

            <div class="profile-meta">
                <?php if (!empty($client['created_by_name'])): ?>
                    <span>Креирано од <strong><?= htmlspecialchars($client['created_by_name']) ?></strong></span>
                <?php else: ?>
                    <span>Креатор непознат</span>
                <?php endif; ?>
                <?php if ($createdAt !== ''): ?><span class="profile-meta-dot">·</span><span><?= $createdAt ?></span><?php endif; ?>
            </div>
        </div>

        <?php endif; ?>

    </div>
    </div> <!-- /.main-content -->
    </div> <!-- /.app-layout -->

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="js/app.js"></script>
    <?php if ($client): ?>
    <script>
    $(function () {
        const CLIENT_ID = <?= (int) $client['id'] ?>;
        const $card = $('.profile-card');

        function pAlert(type, msg) {
            $('#profileAlert').removeClass('alert-ok alert-err').addClass('alert-' + type).text(msg).show();
        }

        $('#btnEdit').on('click', function () {
            $card.addClass('is-editing');
            $('.detail-input').prop('readonly', false);
            $('.detail-input').first().trigger('focus');
        });

        $('#btnCancel').on('click', function () {
            $('#profileForm')[0].reset();
            $('.detail-input').prop('readonly', true);
            $('#profileAlert').hide();
            $card.removeClass('is-editing');
        });

        $('#profileForm').on('submit', function (e) {
            e.preventDefault();
            const $btn = $('#btnSave');
            $btn.prop('disabled', true).text('Се зачувува...');
            $.ajax({
                url: 'api/client_api.php',
                type: 'POST',
                data: $(this).serialize(),
                dataType: 'json',
                success: function (res) {
                    if (res.success) { window.location.reload(); return; }
                    pAlert('err', res.message);
                    $btn.prop('disabled', false).text('Зачувај');
                },
                error: function () {
                    pAlert('err', 'Грешка при комуникација со серверот.');
                    $btn.prop('disabled', false).text('Зачувај');
                }
            });
        });

        $('#btnDelete').on('click', function () {
            if (!confirm('Дали сте сигурни дека сакате да го избришете овој клиент?')) return;
            const $btn = $(this);
            $btn.prop('disabled', true);
            $.ajax({
                url: 'api/client_api.php',
                type: 'POST',
                data: { action: 'delete', id: CLIENT_ID },
                dataType: 'json',
                success: function (res) {
                    if (res.success) { window.location.href = 'klienti.php'; return; }
                    alert(res.message);
                    $btn.prop('disabled', false);
                },
                error: function () { alert('Грешка при бришење.'); $btn.prop('disabled', false); }
            });
        });
    });
    </script>
    <?php endif; ?>
</body>
</html>

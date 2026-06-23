<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

if (current_role() === 'super_admin') {
    header('Location: ' . fakta_url('admin/index.php'));
    exit;
}

require_once __DIR__ . '/classes/CaseFile.php';

$companyId = current_company_id();
$canManage = current_role() !== 'praktikant';
$caseId    = (int) ($_GET['id'] ?? 0);

$cases = new CaseFile($GLOBALS['fakta_db']);
$case  = $caseId > 0 ? $cases->getById($companyId, $caseId) : null;

/** Format денари/евра like 15.000,50 ден. */
function case_money(?string $amount, ?string $currency): string
{
    if ($amount === null || $amount === '') return '';
    return number_format((float) $amount, 2, ',', '.') . ' ' . ($currency ?: 'ден');
}

if ($case) {
    $clientParties   = array_filter($case['parties'], fn($p) => $p['side'] === 'client');
    $opponentParties = array_filter($case['parties'], fn($p) => $p['side'] === 'opponent');
    $currentAdmin    = null;
    foreach ($case['admin_numbers'] as $an) {
        if ((int) $an['is_current'] === 1) { $currentAdmin = $an['admin_number']; break; }
    }
}
?>
<!DOCTYPE html>
<html lang="mk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $case ? htmlspecialchars($case['case_number']) . ' – ' : '' ?>Предмет – Факта</title>
    <script>if(localStorage.getItem('darkMode')==='1')document.documentElement.setAttribute('data-theme','dark');</script>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="css/style.css">
</head>

<body class="min-h-screen">

    <?php include 'includes/nav.php'; ?>

    <div class="app-layout">
    <?php $currentPage = 'predmeti'; include 'includes/sidebar.php'; ?>

    <div class="main-content">
    <div class="max-w-5xl mx-auto px-4 pb-16">

        <a href="predmeti.php" class="profile-back">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
            Назад кон предмети
        </a>

        <?php if (!$case): ?>
            <div class="bg-white border border-slate-200 rounded-xl shadow-sm p-10 text-center mt-4">
                <p class="text-slate-500">Предметот не е пронајден или е избришан.</p>
            </div>
        <?php else: ?>

        <div class="case-detail mt-4" data-case-id="<?= (int) $case['id'] ?>">

            <!-- Header -->
            <div class="case-detail-hero">
                <div class="case-detail-hero-main">
                    <div class="case-detail-numwrap">
                        <span class="case-detail-numbadge"><?= htmlspecialchars($case['case_number']) ?></span>
                        <?php if (!empty($case['archived_at'])): ?>
                            <span class="case-badge case-badge--archived">Архивиран</span>
                        <?php else: ?>
                            <span class="case-badge case-badge--active">Активен</span>
                        <?php endif; ?>
                    </div>
                    <h1 class="case-detail-basis"><?= htmlspecialchars($case['basis'] ?? '—') ?></h1>
                    <p class="case-detail-sub">
                        Креиран <?= !empty($case['created_at']) ? date('d.m.Y', strtotime($case['created_at'])) : '' ?>
                        <?php if (!empty($case['created_by_name'])): ?> · од <strong><?= htmlspecialchars($case['created_by_name']) ?></strong><?php endif; ?>
                    </p>
                </div>
                <?php if ($canManage): ?>
                <div class="case-detail-actions">
                    <a href="predmeti.php?edit=<?= (int) $case['id'] ?>" class="btn-secondary">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>
                        Уреди
                    </a>
                    <?php if (empty($case['archived_at'])): ?>
                        <button class="btn-secondary" id="caseArchiveBtn">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="5" x="2" y="3" rx="1"/><path d="M4 8v11a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8"/><path d="m9 13 3 3 3-3"/><path d="M12 16V8"/></svg>
                            Архивирај
                        </button>
                    <?php else: ?>
                        <button class="btn-secondary" id="caseUnarchiveBtn">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 7v6h6"/><path d="M21 17a9 9 0 0 0-9-9 9 9 0 0 0-6 2.3L3 13"/></svg>
                            Врати од архива
                        </button>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- Key facts -->
            <div class="case-facts">
                <div class="case-fact">
                    <span class="case-fact-label">Статус</span>
                    <span class="case-fact-value">
                        <?php if (!empty($case['archived_at'])): ?>
                            <span class="case-status-dot case-status-dot--archived"></span>Архивиран
                        <?php else: ?>
                            <span class="case-status-dot case-status-dot--active"></span>Активен
                        <?php endif; ?>
                    </span>
                </div>
                <div class="case-fact">
                    <span class="case-fact-label">Вредност</span>
                    <span class="case-fact-value"><?= case_money($case['value_amount'], $case['value_currency']) ?: '—' ?></span>
                </div>
                <div class="case-fact">
                    <span class="case-fact-label">Административен број</span>
                    <span class="case-fact-value"><?= $currentAdmin ? htmlspecialchars($currentAdmin) : '—' ?></span>
                </div>
                <div class="case-fact">
                    <span class="case-fact-label">Странки</span>
                    <span class="case-fact-value"><?= count($clientParties) ?> · <?= count($opponentParties) ?></span>
                </div>
            </div>

            <!-- Parties -->
            <div class="case-detail-cols">
                <div class="case-panel">
                    <div class="case-panel-head">
                        <h2 class="case-panel-title">Наши странки</h2>
                        <span class="case-panel-badge case-panel-badge--client"><?= count($clientParties) ?></span>
                    </div>
                    <?php foreach ($clientParties as $p):
                        $col = fakta_avatar_color($p['name'] ?? ''); ?>
                        <div class="party-card">
                            <div class="party-card-av" style="background:<?= $col['bg'] ?>;color:<?= $col['fg'] ?>"><?= htmlspecialchars(fakta_initials($p['name'] ?? '?')) ?></div>
                            <div class="party-card-info">
                                <div class="party-card-name"><?= htmlspecialchars($p['name'] ?? '—') ?></div>
                                <div class="party-card-meta">
                                    <span class="case-role-tag"><?= htmlspecialchars($p['role']) ?></span>
                                    <?php if (!empty($p['client_id'])): ?>
                                        <a class="case-party-link" href="klient.php?id=<?= (int) $p['client_id'] ?>">Профил →</a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <?php if (!$clientParties): ?><p class="case-empty">Нема странки.</p><?php endif; ?>
                </div>

                <div class="case-panel">
                    <div class="case-panel-head">
                        <h2 class="case-panel-title">Спротивни странки</h2>
                        <span class="case-panel-badge case-panel-badge--opp"><?= count($opponentParties) ?></span>
                    </div>
                    <?php foreach ($opponentParties as $p):
                        $col = fakta_avatar_color($p['name'] ?? ''); ?>
                        <div class="party-card">
                            <div class="party-card-av" style="background:<?= $col['bg'] ?>;color:<?= $col['fg'] ?>"><?= htmlspecialchars(fakta_initials($p['name'] ?? '?')) ?></div>
                            <div class="party-card-info">
                                <div class="party-card-name">
                                    <?= htmlspecialchars($p['name'] ?? '—') ?>
                                    <span class="case-etype-tag"><?= $p['entity_type'] === 'company' ? 'Правно' : 'Физичко' ?></span>
                                </div>
                                <div class="party-card-meta">
                                    <span class="case-role-tag"><?= htmlspecialchars($p['role']) ?></span>
                                    <?php if (!empty($p['opposing_lawyer'])): ?>
                                        <span class="case-lawyer">
                                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 11l2-7H6l2 7"/><path d="M12 4v16"/><path d="M8 20h8"/></svg>
                                            адв. <?= htmlspecialchars($p['opposing_lawyer']) ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <?php if (!$opponentParties): ?><p class="case-empty">Нема спротивни странки.</p><?php endif; ?>
                </div>
            </div>

            <!-- Assignees -->
            <div class="case-panel">
                <div class="case-panel-head">
                    <h2 class="case-panel-title">Зададено на</h2>
                    <span class="case-panel-badge"><?= count($case['assignees']) ?></span>
                </div>
                <?php if ($case['assignees']): ?>
                    <div class="case-assignee-list">
                        <?php foreach ($case['assignees'] as $a):
                            $col = fakta_avatar_color($a['name']); ?>
                            <span class="case-assignee-pill">
                                <span class="case-assignee-av" style="background:<?= $col['bg'] ?>;color:<?= $col['fg'] ?>"><?= htmlspecialchars(fakta_initials($a['name'])) ?></span>
                                <?= htmlspecialchars($a['name']) ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="case-empty">Не е зададен на никого.</p>
                <?php endif; ?>
            </div>

            <!-- Admin number history -->
            <div class="case-panel">
                <div class="case-panel-head">
                    <h2 class="case-panel-title">Историја на административни броеви</h2>
                </div>
                <div id="adminHistory" class="case-admin-history">
                    <?php foreach ($case['admin_numbers'] as $an): ?>
                        <div class="case-admin-item<?= (int) $an['is_current'] === 1 ? ' is-current' : '' ?>"
                             data-id="<?= (int) $an['id'] ?>"
                             data-number="<?= htmlspecialchars($an['admin_number'], ENT_QUOTES) ?>"
                             data-note="<?= htmlspecialchars($an['note'] ?? '', ENT_QUOTES) ?>">
                            <span class="case-admin-dot"></span>
                            <span class="case-admin-num"><?= htmlspecialchars($an['admin_number']) ?></span>
                            <?php if (!empty($an['note'])): ?><span class="case-admin-note"><?= htmlspecialchars($an['note']) ?></span><?php endif; ?>
                            <?php if ((int) $an['is_current'] === 1): ?><span class="case-admin-current">тековен</span><?php endif; ?>
                            <span class="case-admin-date"><?= !empty($an['created_at']) ? date('d.m.Y', strtotime($an['created_at'])) : '' ?></span>
                            <?php if ($canManage): ?>
                            <span class="case-admin-actions">
                                <button type="button" class="admin-edit" title="Уреди"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg></button>
                                <button type="button" class="admin-del" title="Избриши"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg></button>
                            </span>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                    <?php if (!$case['admin_numbers']): ?><p class="case-empty">Сè уште нема внесено административен број.</p><?php endif; ?>
                </div>

                <?php if ($canManage): ?>
                <form id="adminNumberForm" class="case-admin-add">
                    <input type="text" class="field" id="newAdminNumber" placeholder="Нов административен број" style="flex:2 1 12rem">
                    <input type="text" class="field" id="newAdminNote" placeholder="Белешка — пр. фаза/институција (опц.)" style="flex:2 1 12rem">
                    <button type="submit" class="btn-modal-save">Додај</button>
                </form>
                <p class="case-admin-hint">Новиот број станува тековен; претходните остануваат во историјата.</p>
                <?php endif; ?>
            </div>
        </div>

        <?php endif; ?>

    </div>
    </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="js/app.js"></script>
    <?php if ($case && $canManage): ?>
    <script>
    $(function () {
        var API = 'api/case_api.php';
        var caseId = <?= (int) $case['id'] ?>;

        $('#caseArchiveBtn').on('click', function () {
            confirmDialog({
                title: 'Архивирање', message: 'Предметот ќе се премести во Архива и ќе добие архивски број. Продолжи?',
                confirmText: 'Архивирај', cancelText: 'Откажи',
                onConfirm: function () {
                    $.post(API, { action: 'archive', id: caseId }, null, 'json').done(function (r) {
                        if (r.success) { toast(r.message, 'success'); setTimeout(function () { location.reload(); }, 500); }
                        else toast(r.message || 'Грешка.', 'error');
                    });
                }
            });
        });

        $('#caseUnarchiveBtn').on('click', function () {
            $.post(API, { action: 'unarchive', id: caseId }, null, 'json').done(function (r) {
                if (r.success) { toast(r.message, 'success'); setTimeout(function () { location.reload(); }, 500); }
                else toast(r.message || 'Грешка.', 'error');
            });
        });

        $('#adminNumberForm').on('submit', function (e) {
            e.preventDefault();
            var num = $('#newAdminNumber').val().trim();
            if (!num) { toast('Внеси број.', 'error'); return; }
            $.post(API, { action: 'add_admin_number', id: caseId, admin_number: num, note: $('#newAdminNote').val().trim() }, null, 'json')
                .done(function (r) {
                    if (r.success) { toast(r.message, 'success'); setTimeout(function () { location.reload(); }, 400); }
                    else toast(r.message || 'Грешка.', 'error');
                });
        });

        // ---- Edit / delete a single админ. број ----
        function escAttr(s) { var d = document.createElement('div'); d.appendChild(document.createTextNode(s == null ? '' : s)); return d.innerHTML; }

        $('#adminHistory').on('click', '.admin-edit', function () {
            var $item = $(this).closest('.case-admin-item');
            if ($item.hasClass('editing')) return;
            var id = $item.attr('data-id');
            var num = $item.attr('data-number') || '';
            var note = $item.attr('data-note') || '';
            $item.addClass('editing').html(
                '<input type="text" class="field admin-edit-num" value="' + escAttr(num) + '" placeholder="Административен број">' +
                '<input type="text" class="field admin-edit-note" value="' + escAttr(note) + '" placeholder="Белешка (опц.)">' +
                '<button type="button" class="btn-modal-save admin-save" data-id="' + id + '">Зачувај</button>' +
                '<button type="button" class="btn-modal-cancel admin-cancel">Откажи</button>'
            );
            $item.find('.admin-edit-num').focus();
        });

        $('#adminHistory').on('click', '.admin-cancel', function () { location.reload(); });

        $('#adminHistory').on('click', '.admin-save', function () {
            var $item = $(this).closest('.case-admin-item');
            var num = $item.find('.admin-edit-num').val().trim();
            if (!num) { toast('Внеси број.', 'error'); return; }
            $.post(API, {
                action: 'update_admin_number', id: caseId, admin_id: $(this).data('id'),
                admin_number: num, note: $item.find('.admin-edit-note').val().trim()
            }, null, 'json').done(function (r) {
                if (r.success) { toast(r.message, 'success'); setTimeout(function () { location.reload(); }, 350); }
                else toast(r.message || 'Грешка.', 'error');
            });
        });

        $('#adminHistory').on('click', '.admin-del', function () {
            var $item = $(this).closest('.case-admin-item');
            var id = $item.attr('data-id');
            var num = $item.attr('data-number') || '';
            confirmDialog({
                title: 'Бришење', danger: true,
                message: 'Избриши го административниот број „' + num + '“? Ова не може да се врати.',
                confirmText: 'Избриши', cancelText: 'Откажи',
                onConfirm: function () {
                    $.post(API, { action: 'delete_admin_number', id: caseId, admin_id: id }, null, 'json').done(function (r) {
                        if (r.success) { toast(r.message, 'success'); setTimeout(function () { location.reload(); }, 350); }
                        else toast(r.message || 'Грешка.', 'error');
                    });
                }
            });
        });
    });
    </script>
    <?php endif; ?>
</body>
</html>

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

$members = [];
if ($case) {
    $clientParties   = array_filter($case['parties'], fn($p) => $p['side'] === 'client');
    $opponentParties = array_filter($case['parties'], fn($p) => $p['side'] === 'opponent');
    $currentAdmin    = null;
    foreach ($case['admin_numbers'] as $an) {
        if ((int) $an['is_current'] === 1) { $currentAdmin = $an['admin_number']; break; }
    }
    // Employees for the to-do assignee dropdown.
    $mStmt = $GLOBALS['fakta_db']->prepare(
        "SELECT id, name FROM users WHERE company_id = :cid AND role IN ('admin','employee','praktikant') ORDER BY name"
    );
    $mStmt->execute([':cid' => $companyId]);
    $members = $mStmt->fetchAll();
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
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.css">
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
                    <span class="case-fact-label">Состојба</span>
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
            </div>

            <!-- Parties -->
            <div class="case-detail-cols">
                <div class="case-panel">
                    <div class="case-panel-head">
                        <h2 class="case-panel-title">Наша странка</h2>
                        <span class="case-panel-badge case-panel-badge--client"><?= count($clientParties) ?></span>
                    </div>
                    <?php foreach ($clientParties as $p):
                        $col = fakta_avatar_color($p['name'] ?? ''); ?>
                        <div class="party-card">
                            <div class="party-card-av" style="background:<?= $col['bg'] ?>;color:<?= $col['fg'] ?>"><?= htmlspecialchars(fakta_initials($p['name'] ?? '?')) ?></div>
                            <div class="party-card-info">
                                <div class="party-card-name"><?= htmlspecialchars($p['name'] ?? '—') ?></div>
                                <div class="party-card-meta">
                                    <?php if (!empty($p['role'])): ?><span class="case-role-tag"><?= htmlspecialchars($p['role']) ?></span><?php endif; ?>
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
                        <h2 class="case-panel-title">Спротивна странка</h2>
                        <span class="case-panel-badge case-panel-badge--opp"><?= count($opponentParties) ?></span>
                    </div>
                    <?php foreach ($opponentParties as $p):
                        $col = fakta_avatar_color($p['name'] ?? ''); ?>
                        <div class="party-card">
                            <div class="party-card-av" style="background:<?= $col['bg'] ?>;color:<?= $col['fg'] ?>"><?= htmlspecialchars(fakta_initials($p['name'] ?? '?')) ?></div>
                            <div class="party-card-info">
                                <div class="party-card-name">
                                    <?= htmlspecialchars($p['name'] ?? '—') ?>
                                </div>
                                <div class="party-card-meta">
                                    <?php if (!empty($p['role'])): ?><span class="case-role-tag"><?= htmlspecialchars($p['role']) ?></span><?php endif; ?>
                                    <?php if (!empty($p['opposing_representative'])): ?>
                                        <span class="case-lawyer">
                                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 11l2-7H6l2 7"/><path d="M12 4v16"/><path d="M8 20h8"/></svg>
                                            застап. <?= htmlspecialchars($p['opposing_representative']) ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <?php if (!$opponentParties): ?><p class="case-empty">Нема спротивна странка.</p><?php endif; ?>
                </div>
            </div>

            <!-- Доделено на (assignees) -->
            <div class="case-panel">
                <div class="case-panel-head">
                    <h2 class="case-panel-title">Доделено на</h2>
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
                    <p class="case-empty">Не е доделено на никого.</p>
                <?php endif; ?>
            </div>

            <!-- Admin number history -->
            <div class="case-panel">
                <div class="case-panel-head">
                    <h2 class="case-panel-title">Административни броеви</h2>
                </div>
                <div id="adminHistory" class="case-admin-history">
                    <?php foreach ($case['admin_numbers'] as $an): ?>
                        <div class="case-admin-item<?= (int) $an['is_current'] === 1 ? ' is-current' : '' ?>"
                             data-id="<?= (int) $an['id'] ?>"
                             data-number="<?= htmlspecialchars($an['admin_number'], ENT_QUOTES) ?>"
                             data-official="<?= htmlspecialchars($an['official_person'] ?? '', ENT_QUOTES) ?>">
                            <span class="case-admin-dot"></span>
                            <span class="case-admin-num"><?= htmlspecialchars($an['admin_number']) ?></span>
                            <?php if (!empty($an['official_person'])): ?><span class="case-admin-note"><?= htmlspecialchars($an['official_person']) ?></span><?php endif; ?>
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
                    <div style="position:relative; flex:2 1 12rem">
                        <input type="text" class="field" id="newAdminOfficial" placeholder="Овластено лице (службеник) (опц.)" style="width:100%">
                        <div id="newAdminOfficialSuggest" class="basis-suggest" style="display:none"></div>
                    </div>
                    <button type="submit" class="btn-modal-save">Додај</button>
                </form>
                <p class="case-admin-hint">Новиот број станува тековен.</p>
                <?php endif; ?>
            </div>

            <!-- ============================================================
                 Tabs: Документи · Белешки · Задачи · Настани
            ============================================================ -->
            <div class="case-tabs-nav" id="caseTabsNav" role="tablist">
                <button type="button" class="case-tab-btn is-active" data-tab="docs">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/></svg>
                    Документи <span class="case-tab-count" id="tabDocs">0</span>
                </button>
                <button type="button" class="case-tab-btn" data-tab="notes">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                    Белешки <span class="case-tab-count" id="tabNotes">0</span>
                </button>
                <button type="button" class="case-tab-btn" data-tab="todos">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
                    Задачи <span class="case-tab-count" id="tabTodos">0</span>
                </button>
                <button type="button" class="case-tab-btn" data-tab="hearings">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="4" rx="2"/><path d="M3 10h18M8 2v4M16 2v4"/></svg>
                    Настани <span class="case-tab-count" id="tabHearings">0</span>
                </button>
            </div>

            <div class="case-tab-panels">

                <!-- Документи -->
                <div class="case-tab-panel is-active" data-tab="docs">
                    <div class="case-panel">
                        <div class="tab-toolbar">
                            <label class="btn-secondary case-panel-action" id="docUploadBtn" for="docFileInput">
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="M17 8l-5-5-5 5"/><path d="M12 3v12"/></svg>
                                <span id="docUploadLabel">Прикачи</span>
                            </label>
                            <input type="file" id="docFileInput" multiple style="display:none"
                                   accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.rtf,.odt,.ods,.csv,.jpg,.jpeg,.png,.gif,.zip">
                        </div>
                        <div id="docDrop" class="doc-drop">
                            <div id="docList" class="doc-list"><p class="case-empty">Се вчитува…</p></div>
                        </div>
                    </div>
                </div>

                <!-- Белешки -->
                <div class="case-tab-panel" data-tab="notes">
                    <div class="case-panel">
                        <div class="note-compose">
                            <textarea id="noteInput" class="field note-textarea" rows="2" placeholder="Напиши белешка за предметот…"></textarea>
                            <div class="note-types" id="noteTypes">
                                <button type="button" class="note-type-chip is-active" data-type="general">Општо</button>
                                <button type="button" class="note-type-chip ntype--call" data-type="call">Разговор</button>
                                <button type="button" class="note-type-chip ntype--meeting" data-type="meeting">Состанок</button>
                                <button type="button" class="note-type-chip ntype--important" data-type="important">Важно</button>
                            </div>
                            <div class="note-compose-foot">
                                <span class="note-hint">Tip: Ctrl+Enter за брзо додавање</span>
                                <button type="button" id="noteAddBtn" class="btn-modal-save">Додај белешка</button>
                            </div>
                        </div>
                        <div id="notesList" class="notes-list"><p class="case-empty">Се вчитува…</p></div>
                    </div>
                </div>

                <!-- Задачи -->
                <div class="case-tab-panel" data-tab="todos">
                    <div class="case-panel">
                        <div class="todo-compose">
                            <input type="text" id="todoInput" class="field todo-title-input" placeholder="Нова задача — пр. Поднеси тужба, Подготви документи…">
                            <input type="text" id="todoDue" class="field todo-due-input" placeholder="Рок (опц.)" title="Рок (опц.)">
                            <select id="todoAssignee" class="field todo-assignee-select" title="Доделено на (опц.)">
                                <option value="">Никој</option>
                                <?php foreach ($members as $m): ?>
                                    <option value="<?= (int) $m['id'] ?>"><?= htmlspecialchars($m['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button type="button" id="todoAddBtn" class="btn-modal-save">Додај</button>
                        </div>
                        <div id="todoList" class="todo-list"><p class="case-empty">Се вчитува…</p></div>
                    </div>
                </div>

                <!-- Настани (рочишта · судења · состаноци) -->
                <div class="case-tab-panel" data-tab="hearings">
                    <div class="case-panel">
                        <div class="hearing-compose">
                            <div class="hkind-chips" id="hearingKindChips">
                                <button type="button" class="hkind-chip hkind--hearing is-active" data-kind="hearing">Рочиште</button>
                                <button type="button" class="hkind-chip hkind--meeting" data-kind="meeting">Состанок</button>
                            </div>
                            <input type="text" id="hearingTitle" class="field hearing-title-input" placeholder="Наслов — пр. Главна расправа, Подготвително рочиште…">
                            <div class="hearing-compose-row">
                                <input type="text" id="hearingDate" class="field hearing-date-input" placeholder="Датум" title="Датум">
                                <select id="hearingTime" class="field hearing-time-input" title="Време"></select>
                                <input type="text" id="hearingLocation" class="field hearing-loc-input" placeholder="Локација / суд (опц.)">
                            </div>
                            <textarea id="hearingNote" class="field hearing-note-input" rows="2" placeholder="Белешка за настанот (опц.)"></textarea>
                            <div class="hearing-compose-foot">
                                <button type="button" id="hearingAddBtn" class="btn-modal-save">Додај настан</button>
                            </div>
                        </div>
                        <div id="hearingList" class="hearing-list"><p class="case-empty">Се вчитува…</p></div>
                    </div>
                </div>

            </div>
        </div>

        <?php endif; ?>

    </div>
    </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="js/app.js"></script>
    <?php if ($case && $canManage): ?>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/l10n/mk.js"></script>
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
            $.post(API, { action: 'add_admin_number', id: caseId, admin_number: num, official_person: $('#newAdminOfficial').val().trim() }, null, 'json')
                .done(function (r) {
                    if (r.success) { toast(r.message, 'success'); setTimeout(function () { location.reload(); }, 400); }
                    else toast(r.message || 'Грешка.', 'error');
                });
        });

        // Овластено лице autocomplete (new admin-number row).
        var officialTimer = null;
        $('#newAdminOfficial').on('input', function () {
            var q = $(this).val().trim();
            clearTimeout(officialTimer);
            if (q.length < 2) { $('#newAdminOfficialSuggest').hide(); return; }
            officialTimer = setTimeout(function () {
                $.ajax({ url: API, data: { action: 'suggest_official', q: q }, dataType: 'json' }).done(function (res) {
                    var items = (res.data || []);
                    if (!items.length) { $('#newAdminOfficialSuggest').hide(); return; }
                    $('#newAdminOfficialSuggest').html(items.map(function (it) {
                        return '<div class="basis-suggest-item" data-val="' + escAttr(it.official_person) + '">'
                            + '<span>' + escAttr(it.official_person) + '</span><span class="basis-suggest-count">' + it.cnt + '×</span></div>';
                    }).join('')).show();
                });
            }, 200);
        });
        $('#newAdminOfficialSuggest').on('mousedown', '.basis-suggest-item', function (e) {
            e.preventDefault();
            $('#newAdminOfficial').val($(this).data('val'));
            $('#newAdminOfficialSuggest').hide();
        });
        $('#newAdminOfficial').on('blur', function () { setTimeout(function () { $('#newAdminOfficialSuggest').hide(); }, 150); });

        // ---- Edit / delete a single админ. број ----
        function escAttr(s) { var d = document.createElement('div'); d.appendChild(document.createTextNode(s == null ? '' : s)); return d.innerHTML; }

        $('#adminHistory').on('click', '.admin-edit', function () {
            var $item = $(this).closest('.case-admin-item');
            if ($item.hasClass('editing')) return;
            var id = $item.attr('data-id');
            var num = $item.attr('data-number') || '';
            var official = $item.attr('data-official') || '';
            $item.addClass('editing').html(
                '<input type="text" class="field admin-edit-num" value="' + escAttr(num) + '" placeholder="Административен број">' +
                '<input type="text" class="field admin-edit-official" value="' + escAttr(official) + '" placeholder="Овластено лице (службеник) (опц.)">' +
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
                admin_number: num, official_person: $item.find('.admin-edit-official').val().trim()
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

    <?php if ($case): ?>
    <script>
    $(function () {
        /* ---- Date / time pickers (flatpickr, MK locale) ---- */
        var FP_LOCALE = (window.flatpickr && flatpickr.l10ns && flatpickr.l10ns.mk) ? flatpickr.l10ns.mk : 'default';
        function fpDate(el, def) {
            if (!el || !window.flatpickr) return null;
            return flatpickr(el, {
                locale: FP_LOCALE, dateFormat: 'Y-m-d', altInput: true, altFormat: 'd.m.Y',
                altInputClass: el.className, allowInput: true, disableMobile: true,
                defaultDate: def || el.value || null
            });
        }
        function fpPad(n) { return (n < 10 ? '0' : '') + n; }
        var DEFAULT_TIME = '09:00';
        // Fill a <select> with 15-min time slots; injects `selected` if off-grid.
        function fillTimes(sel, selected) {
            if (!sel) return;
            selected = selected || '';
            var has = false, html = '';
            for (var m = 0; m < 24 * 60; m += 15) {
                var v = fpPad(Math.floor(m / 60)) + ':' + fpPad(m % 60);
                if (v === selected) has = true;
                html += '<option value="' + v + '">' + v + '</option>';
            }
            if (selected && !has) html = '<option value="' + selected + '">' + selected + '</option>' + html;
            sel.innerHTML = html;
            if (selected) sel.value = selected;
        }
        var fpTodoDue   = fpDate(document.getElementById('todoDue'));
        var fpHearingDt = fpDate(document.getElementById('hearingDate'));
        fillTimes(document.getElementById('hearingTime'), DEFAULT_TIME);
        function fpClear(inst, sel) { if (inst) inst.clear(); else $(sel).val(''); }
        // Destroy inline-edit pickers before a list re-render (their calendars
        // live on <body>, so they'd otherwise leak when the row is replaced).
        function fpDestroyIn(id) {
            var c = document.getElementById(id);
            if (!c) return;
            Array.prototype.forEach.call(c.querySelectorAll('input'), function (i) {
                if (i._flatpickr) i._flatpickr.destroy();
            });
        }

        var API = 'api/case_api.php';
        var caseId = <?= (int) $case['id'] ?>;
        var UID = window.FAKTA_UID || 0;
        var ROLE = window.FAKTA_ROLE || '';
        var MEMBERS = <?= json_encode(array_map(fn($m) => ['id' => (int) $m['id'], 'name' => $m['name']], $members), JSON_UNESCAPED_UNICODE) ?>;
        var notes = [];
        var todos = [];
        var STATUSES = [
            { key: 'open',        label: 'Отворена' },
            { key: 'in_progress', label: 'Во тек' },
            { key: 'waiting',     label: 'Чека' },
            { key: 'done',        label: 'Завршена' },
            { key: 'declined',    label: 'Одбиена' }
        ];
        function statusLabel(k) { var s = STATUSES.find(function (x) { return x.key === k; }); return s ? s.label : k; }

        function esc(s) { var d = document.createElement('div'); d.appendChild(document.createTextNode(s == null ? '' : s)); return d.innerHTML; }
        function nl2br(s) { return esc(s).replace(/\n/g, '<br>'); }
        function initials(name) {
            var p = (name || '').trim().split(/\s+/).filter(Boolean);
            if (!p.length) return '?';
            return (p.length === 1 ? p[0].slice(0, 2) : (p[0][0] + p[p.length - 1][0])).toUpperCase();
        }
        var PAL = [['#eff6ff','#1d4ed8'],['#fff7ed','#c2410c'],['#f0fdf4','#15803d'],['#fdf4ff','#a21caf'],['#fef2f2','#b91c1c'],['#f0f9ff','#0369a1'],['#fefce8','#a16207'],['#f5f3ff','#6d28d9']];
        function color(name) { var s = (name||'').trim(), h = 0; for (var i=0;i<s.length;i++) h=(h+s.charCodeAt(i))%PAL.length; return PAL[h]||PAL[0]; }
        function when(s) {
            if (!s) return '';
            var d = new Date(String(s).replace(' ', 'T'));
            if (isNaN(d.getTime())) return '';
            return ('0'+d.getDate()).slice(-2)+'.'+('0'+(d.getMonth()+1)).slice(-2)+'.'+d.getFullYear()+' '+('0'+d.getHours()).slice(-2)+':'+('0'+d.getMinutes()).slice(-2);
        }

        var NOTE_TYPES = { general: 'Општо', call: 'Разговор', meeting: 'Состанок', important: 'Важно' };
        var selectedNoteType = 'general';
        // Relative time: сега / пред N мин / пред N часа / вчера / пред N дена / date.
        function ago(s) {
            if (!s) return '';
            var d = new Date(String(s).replace(' ', 'T'));
            if (isNaN(d.getTime())) return '';
            var sec = Math.floor((Date.now() - d.getTime()) / 1000);
            if (sec < 60) return 'сега';
            var min = Math.floor(sec / 60); if (min < 60) return 'пред ' + min + ' мин';
            var hr = Math.floor(min / 60); if (hr < 24) return 'пред ' + hr + (hr === 1 ? ' час' : ' часа');
            var day = Math.floor(hr / 24); if (day === 1) return 'вчера'; if (day < 7) return 'пред ' + day + ' дена';
            return when(s);
        }

        function noteHtml(n) {
            var own = String(n.user_id) === String(UID);
            var canDelete = own || ROLE === 'admin';
            var pinned = String(n.is_pinned) === '1';
            var type = n.note_type || 'general';
            var c = color(n.author_name);
            var typeTag = type !== 'general' ? '<span class="note-type-tag ntype--' + type + '">' + esc(NOTE_TYPES[type] || type) + '</span>' : '';
            var actions = '<span class="note-actions">'
                + '<button class="note-pin' + (pinned ? ' is-pinned' : '') + '" title="' + (pinned ? 'Откачи' : 'Закачи') + '"><svg width="13" height="13" viewBox="0 0 24 24" fill="' + (pinned ? 'currentColor' : 'none') + '" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 17v5"/><path d="M9 10.76a2 2 0 0 1-1.11 1.79l-1.78.9A2 2 0 0 0 5 15.24V16a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-.76a2 2 0 0 0-1.11-1.79l-1.78-.9A2 2 0 0 1 15 10.76V7a1 1 0 0 1 1-1 2 2 0 0 0 0-4H8a2 2 0 0 0 0 4 1 1 0 0 1 1 1z"/></svg></button>'
                + (own ? '<button class="note-edit" title="Уреди"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg></button>' : '')
                + (canDelete ? '<button class="note-del" title="Избриши"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg></button>' : '')
                + '</span>';
            var edited = n.updated_at ? ' <span class="note-edited">· изменето</span>' : '';
            return '<div class="note' + (pinned ? ' is-pinned' : '') + '" data-id="' + n.id + '">'
                + '<div class="note-head">'
                +   '<span class="note-avatar" style="background:' + c[0] + ';color:' + c[1] + '">' + esc(initials(n.author_name || '?')) + '</span>'
                +   '<span class="note-author">' + esc(n.author_name || 'Непознат') + '</span>'
                +   typeTag
                +   '<span class="note-time" title="' + when(n.created_at) + '">' + ago(n.created_at) + edited + '</span>'
                +   actions
                + '</div>'
                + '<div class="note-body">' + nl2br(n.body) + '</div>'
                + '</div>';
        }

        function render() {
            $('#tabNotes').text(notes.length);
            $('#notesList').html(notes.length ? notes.map(noteHtml).join('') : '<p class="case-empty">Сè уште нема белешки. Додај ја првата.</p>');
        }
        function loadNotes() {
            $.ajax({ url: API, data: { action: 'get_notes', id: caseId }, dataType: 'json' })
                .done(function (res) { notes = (res && res.data) || []; render(); })
                .fail(function () { $('#notesList').html('<p class="case-empty">Грешка при вчитување.</p>'); });
        }

        $('#noteTypes').on('click', '.note-type-chip', function () {
            $('#noteTypes .note-type-chip').removeClass('is-active');
            $(this).addClass('is-active');
            selectedNoteType = $(this).data('type');
        });

        function addNote() {
            var body = $('#noteInput').val().trim();
            if (!body) { toast('Напиши белешка.', 'error'); return; }
            var $b = $('#noteAddBtn').prop('disabled', true);
            $.post(API, { action: 'add_note', id: caseId, body: body, type: selectedNoteType }, null, 'json')
                .done(function (r) {
                    if (r.success) {
                        $('#noteInput').val('');
                        selectedNoteType = 'general';
                        $('#noteTypes .note-type-chip').removeClass('is-active').filter('[data-type="general"]').addClass('is-active');
                        loadNotes();
                    } else toast(r.message || 'Грешка.', 'error');
                })
                .always(function () { $b.prop('disabled', false); });
        }
        $('#noteAddBtn').on('click', addNote);
        $('#noteInput').on('keydown', function (e) { if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') { e.preventDefault(); addNote(); } });

        $('#notesList').on('click', '.note-pin', function (e) {
            e.stopPropagation();
            var $n = $(this).closest('.note');
            $.post(API, { action: 'pin_note', note_id: $n.data('id'), pinned: $n.hasClass('is-pinned') ? '0' : '1' }, null, 'json')
                .done(function (r) { if (r.success) loadNotes(); else toast('Грешка.', 'error'); });
        });

        function noteTypeChips(sel) {
            return Object.keys(NOTE_TYPES).map(function (k) {
                return '<button type="button" class="note-type-chip ntype--' + k + (k === sel ? ' is-active' : '') + '" data-type="' + k + '">' + esc(NOTE_TYPES[k]) + '</button>';
            }).join('');
        }
        $('#notesList').on('click', '.note-edit', function () {
            var $n = $(this).closest('.note');
            if ($n.hasClass('editing')) return;
            var n = notes.find(function (x) { return String(x.id) === String($n.data('id')); });
            $n.addClass('editing');
            $n.find('.note-body').html(
                '<textarea class="field note-textarea note-edit-area" rows="2"></textarea>'
                + '<div class="note-types note-edit-types">' + noteTypeChips((n && n.note_type) || 'general') + '</div>'
                + '<div class="note-compose-foot"><button class="btn-modal-cancel note-cancel">Откажи</button><button class="btn-modal-save note-save">Зачувај</button></div>'
            );
            $n.find('.note-edit-area').val(n ? n.body : '').focus();
        });
        $('#notesList').on('click', '.note-edit-types .note-type-chip', function () {
            $(this).closest('.note-edit-types').find('.note-type-chip').removeClass('is-active');
            $(this).addClass('is-active');
        });
        $('#notesList').on('click', '.note-cancel', render);
        $('#notesList').on('click', '.note-save', function () {
            var $n = $(this).closest('.note');
            var body = $n.find('.note-edit-area').val().trim();
            if (!body) { toast('Белешката не може да е празна.', 'error'); return; }
            var type = $n.find('.note-edit-types .note-type-chip.is-active').data('type') || 'general';
            $.post(API, { action: 'update_note', note_id: $n.data('id'), body: body, type: type }, null, 'json')
                .done(function (r) { if (r.success) loadNotes(); else toast(r.message || 'Грешка.', 'error'); });
        });
        $('#notesList').on('click', '.note-del', function () {
            var id = $(this).closest('.note').data('id');
            confirmDialog({
                title: 'Бришење белешка', danger: true, message: 'Избриши ја белешката? Ова не може да се врати.',
                confirmText: 'Избриши', cancelText: 'Откажи',
                onConfirm: function () {
                    $.post(API, { action: 'delete_note', note_id: id, id: caseId }, null, 'json')
                        .done(function (r) { if (r.success) loadNotes(); else toast(r.message || 'Грешка.', 'error'); });
                }
            });
        });

        /* ---------------- To-do (задачи) ---------------- */
        function memberOptions(sel) {
            var o = '<option value="">Никој</option>';
            MEMBERS.forEach(function (m) { o += '<option value="' + m.id + '"' + (String(m.id) === String(sel) ? ' selected' : '') + '>' + esc(m.name) + '</option>'; });
            return o;
        }
        function dueLabel(d) { if (!d) return ''; var p = String(d).slice(0, 10).split('-'); return p.length < 3 ? '' : p[2] + '.' + p[1] + '.' + p[0]; }
        // Relative, colour-coded due info. Returns {label, cls} or null.
        function dueInfo(d, closed) {
            if (!d) return null;
            if (closed) return { label: dueLabel(d), cls: '' };
            var today = new Date(); today.setHours(0, 0, 0, 0);
            var diff = Math.round((new Date(String(d).slice(0, 10) + 'T00:00:00') - today) / 86400000);
            if (diff < 0)  return { label: 'доцни · ' + dueLabel(d), cls: 'is-overdue' };
            if (diff === 0) return { label: 'денес', cls: 'is-today' };
            if (diff === 1) return { label: 'утре', cls: 'is-soon' };
            if (diff <= 7) return { label: 'за ' + diff + ' дена', cls: 'is-soon' };
            return { label: dueLabel(d), cls: '' };
        }

        function statusPill(status) {
            var menu = STATUSES.map(function (s) {
                return '<button type="button" class="todo-status-opt' + (s.key === status ? ' is-current' : '') + '" data-status="' + s.key + '">'
                    + '<span class="tdot tdot--' + s.key + '"></span>' + esc(s.label) + '</button>';
            }).join('');
            return '<div class="todo-status-wrap">'
                + '<button type="button" class="todo-status tstat--' + status + '" title="Промени статус">'
                +   '<span class="tdot tdot--' + status + '"></span>' + esc(statusLabel(status))
                +   '<svg class="todo-status-caret" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>'
                + '</button>'
                + '<div class="todo-status-menu" hidden>' + menu + '</div>'
                + '</div>';
        }

        function todoHtml(t) {
            var status = t.status || 'open';
            var closed = status === 'done' || status === 'declined';
            var canEdit = String(t.created_by) === String(UID) || ROLE === 'admin';
            var due = '';
            var di = dueInfo(t.due_date, closed);
            if (di) {
                due = '<span class="todo-chip todo-due ' + di.cls + '">'
                    + '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="4" rx="2"/><path d="M3 10h18M8 2v4M16 2v4"/></svg>'
                    + di.label + '</span>';
            }
            var asg = '';
            if (t.assignee_name) {
                var c = color(t.assignee_name);
                asg = '<span class="todo-chip todo-asg"><span class="todo-asg-av" style="background:' + c[0] + ';color:' + c[1] + '">' + esc(initials(t.assignee_name)) + '</span>' + esc(t.assignee_name) + '</span>';
            }
            var meta = (due || asg) ? ('<div class="todo-meta">' + due + asg + '</div>') : '';
            var actions = canEdit ? ('<span class="todo-actions">'
                + '<button class="todo-edit" title="Уреди"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg></button>'
                + '<button class="todo-del" title="Избриши"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg></button>'
                + '</span>') : '';
            return '<div class="todo' + (closed ? ' is-closed' : '') + (status === 'done' ? ' is-done' : '') + '" data-id="' + t.id + '">'
                + statusPill(status)
                + '<div class="todo-main"><span class="todo-title">' + esc(t.title) + '</span>' + meta + '</div>'
                + actions + '</div>';
        }
        function renderTodos() {
            fpDestroyIn('todoList');
            var doneCount = todos.filter(function (t) { return t.status === 'done'; }).length;
            $('#tabTodos').text(todos.length);
            if (!todos.length) {
                $('#todoList').html('<p class="case-empty">Сè уште нема задачи. Додај ја првата.</p>');
                return;
            }
            var isClosed = function (t) { return t.status === 'done' || t.status === 'declined'; };
            var active = todos.filter(function (t) { return !isClosed(t); });
            var closed = todos.filter(isClosed);
            var pct = Math.round(doneCount / todos.length * 100);

            var html = '<div class="todo-progress-wrap">'
                + '<div class="todo-progress"><div class="todo-progress-bar" style="width:' + pct + '%"></div></div>'
                + '<span class="todo-progress-text">' + doneCount + ' од ' + todos.length + ' завршени</span></div>';
            html += active.length
                ? '<div class="todo-group">' + active.map(todoHtml).join('') + '</div>'
                : '<p class="todo-alldone">🎉 Нема активни задачи</p>';
            if (closed.length) {
                html += '<button type="button" class="todo-show-done" data-open="0">'
                    + '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>'
                    + 'Покажи завршени / одбиени (' + closed.length + ')</button>';
                html += '<div class="todo-closed-list todo-group" hidden>' + closed.map(todoHtml).join('') + '</div>';
            }
            $('#todoList').html(html);
        }
        function loadTodos() {
            $.ajax({ url: API, data: { action: 'get_todos', id: caseId }, dataType: 'json' })
                .done(function (res) { todos = (res && res.data) || []; renderTodos(); })
                .fail(function () { $('#todoList').html('<p class="case-empty">Грешка при вчитување.</p>'); });
        }

        $('#todoAddBtn').on('click', function () {
            var title = $('#todoInput').val().trim();
            if (!title) { toast('Внеси задача.', 'error'); return; }
            var $b = $(this).prop('disabled', true);
            $.post(API, { action: 'add_todo', id: caseId, title: title, due_date: $('#todoDue').val(), assigned_to: $('#todoAssignee').val() || 0 }, null, 'json')
                .done(function (r) {
                    if (r.success) { $('#todoInput').val(''); fpClear(fpTodoDue, '#todoDue'); $('#todoAssignee').val(''); loadTodos(); }
                    else toast(r.message || 'Грешка.', 'error');
                }).always(function () { $b.prop('disabled', false); });
        });
        $('#todoInput').on('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); $('#todoAddBtn').click(); } });

        // Status pill → open its menu (close any other first).
        $('#todoList').on('click', '.todo-status', function (e) {
            e.stopPropagation();
            var $menu = $(this).siblings('.todo-status-menu');
            var wasOpen = !$menu.prop('hidden');
            $('.todo-status-menu').prop('hidden', true);
            $menu.prop('hidden', wasOpen);
        });
        $('#todoList').on('click', '.todo-status-opt', function (e) {
            e.stopPropagation();
            var $t = $(this).closest('.todo');
            var status = $(this).data('status');
            $('.todo-status-menu').prop('hidden', true);
            $.post(API, { action: 'set_todo_status', todo_id: $t.data('id'), status: status }, null, 'json')
                .done(function (r) { if (r.success) loadTodos(); else toast(r.message || 'Грешка.', 'error'); });
        });
        $(document).on('click', function () { $('.todo-status-menu').prop('hidden', true); });

        $('#todoList').on('click', '.todo-show-done', function () {
            var open = $(this).attr('data-open') === '1';
            var n = $('.todo-closed-list .todo').length;
            $('.todo-closed-list').prop('hidden', open);
            $(this).attr('data-open', open ? '0' : '1')
                .html('<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>'
                    + (open ? 'Покажи' : 'Сокриј') + ' завршени / одбиени (' + n + ')')
                .toggleClass('is-open', !open);
        });
        $('#todoList').on('click', '.todo-edit', function () {
            var $t = $(this).closest('.todo');
            if ($t.hasClass('editing')) return;
            var t = todos.find(function (x) { return String(x.id) === String($t.data('id')); });
            if (!t) return;
            $t.addClass('editing');
            $t.find('.todo-main').html(
                '<input type="text" class="field todo-edit-title">'
                + '<div class="todo-edit-row"><input type="text" class="field todo-edit-due" placeholder="Рок (опц.)" value="' + (t.due_date ? String(t.due_date).slice(0, 10) : '') + '">'
                + '<select class="field todo-edit-asg">' + memberOptions(t.assigned_to) + '</select></div>'
                + '<div class="note-compose-foot"><button class="btn-modal-cancel todo-cancel">Откажи</button><button class="btn-modal-save todo-save">Зачувај</button></div>'
            );
            fpDate($t.find('.todo-edit-due')[0]);
            $t.find('.todo-edit-title').val(t.title).focus();
        });
        $('#todoList').on('click', '.todo-cancel', renderTodos);
        $('#todoList').on('click', '.todo-save', function () {
            var $t = $(this).closest('.todo');
            var title = $t.find('.todo-edit-title').val().trim();
            if (!title) { toast('Внеси задача.', 'error'); return; }
            $.post(API, { action: 'update_todo', todo_id: $t.data('id'), title: title, due_date: $t.find('.todo-edit-due').val(), assigned_to: $t.find('.todo-edit-asg').val() || 0 }, null, 'json')
                .done(function (r) { if (r.success) loadTodos(); else toast(r.message || 'Грешка.', 'error'); });
        });
        $('#todoList').on('click', '.todo-del', function () {
            var id = $(this).closest('.todo').data('id');
            confirmDialog({
                title: 'Бришење задача', danger: true, message: 'Избриши ја задачата? Ова не може да се врати.',
                confirmText: 'Избриши', cancelText: 'Откажи',
                onConfirm: function () {
                    $.post(API, { action: 'delete_todo', todo_id: id }, null, 'json')
                        .done(function (r) { if (r.success) loadTodos(); else toast(r.message || 'Грешка.', 'error'); });
                }
            });
        });

        /* ---------------- Рочишта (hearings) ---------------- */
        var hearings = [];
        function hDate(s) { if (!s) return ''; var p = String(s).slice(0, 10).split('-'); return p[2] + '.' + p[1] + '.' + p[0]; }
        function hTime(s) { if (!s) return ''; return String(s).slice(11, 16); }
        function hRel(s) {
            if (!s) return '';
            var today = new Date(); today.setHours(0, 0, 0, 0);
            var d = new Date(String(s).slice(0, 10) + 'T00:00:00');
            var diff = Math.round((d - today) / 86400000);
            if (diff === 0) return 'денес'; if (diff === 1) return 'утре';
            if (diff > 1 && diff <= 14) return 'за ' + diff + ' дена';
            if (diff === -1) return 'вчера'; if (diff < -1) return 'пред ' + (-diff) + ' дена';
            return '';
        }
        var MK_MON = ['јан','фев','мар','апр','мај','јун','јул','авг','сеп','окт','ное','дек'];
        var HKIND = { hearing: 'Рочиште', trial: 'Судење', meeting: 'Состанок' };
        function kindOf(h) { return HKIND[h.kind] ? h.kind : 'hearing'; }
        var hearingKind = 'hearing'; // selected kind for the compose form

        function hearingHtml(h, isNext) {
            var canEdit = String(h.created_by) === String(UID) || ROLE === 'admin';
            var day = String(h.hearing_at).slice(8, 10);
            var mon = MK_MON[parseInt(String(h.hearing_at).slice(5, 7), 10) - 1] || '';
            var rel = hRel(h.hearing_at);
            var k = kindOf(h);
            var badge = '<span class="hkind-badge hkind--' + k + '">' + HKIND[k] + '</span>';
            var actions = canEdit ? '<span class="hearing-actions">'
                + '<button class="hearing-edit" title="Уреди"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg></button>'
                + '<button class="hearing-del" title="Избриши"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg></button>'
                + '</span>' : '';
            var dateStr = String(h.hearing_at).slice(0, 10);
            return '<div class="hearing hearing--' + k + (isNext ? ' is-next' : '') + '" data-id="' + h.id + '" data-kind="' + k + '">'
                + '<a class="hearing-date" href="kalendar.php?date=' + dateStr + '&view=day" title="Отвори во календарот">'
                +   '<span class="hearing-day">' + day + '</span><span class="hearing-mon">' + mon + '</span></a>'
                + '<div class="hearing-main">'
                +   '<div class="hearing-title">' + badge + esc(h.title) + (isNext ? '<span class="hearing-next-tag">следно</span>' : '') + '</div>'
                +   '<div class="hearing-meta">'
                +     '<span class="hearing-time">🕐 ' + hTime(h.hearing_at) + (rel ? ' · ' + rel : '') + '</span>'
                +     (h.location ? '<span class="hearing-loc">📍 ' + esc(h.location) + '</span>' : '')
                +   '</div>'
                +   (h.note ? '<div class="hearing-note">' + nl2br(h.note) + '</div>' : '')
                + '</div>' + actions + '</div>';
        }

        // Compose: kind chip selection.
        $('#hearingKindChips').on('click', '.hkind-chip', function () {
            $('#hearingKindChips .hkind-chip').removeClass('is-active');
            $(this).addClass('is-active');
            hearingKind = $(this).data('kind');
        });

        function renderHearings() {
            fpDestroyIn('hearingList');
            $('#tabHearings').text(hearings.length);
            if (!hearings.length) { $('#hearingList').html('<p class="case-empty">Сè уште нема закажани настани.</p>'); return; }
            var now = new Date();
            var upcoming = hearings.filter(function (h) { return new Date(String(h.hearing_at).replace(' ', 'T')) >= now; });
            var past = hearings.filter(function (h) { return new Date(String(h.hearing_at).replace(' ', 'T')) < now; }).reverse();
            var html = '';
            if (upcoming.length) {
                html += '<div class="hearing-sec-label">Претстојни</div>';
                html += upcoming.map(function (h, i) { return hearingHtml(h, i === 0); }).join('');
            }
            if (past.length) {
                html += '<div class="hearing-sec-label hearing-sec-past">Изминати</div>';
                html += '<div class="hearing-past">' + past.map(function (h) { return hearingHtml(h, false); }).join('') + '</div>';
            }
            $('#hearingList').html(html);
        }
        function loadHearings() {
            $.ajax({ url: API, data: { action: 'get_hearings', id: caseId }, dataType: 'json' })
                .done(function (res) { hearings = (res && res.data) || []; renderHearings(); })
                .fail(function () { $('#hearingList').html('<p class="case-empty">Грешка при вчитување.</p>'); });
        }

        $('#hearingAddBtn').on('click', function () {
            var title = $('#hearingTitle').val().trim();
            var date = $('#hearingDate').val();
            var time = $('#hearingTime').val();
            if (!title || !date || !time) { toast('Внеси наслов, датум и време.', 'error'); return; }
            var $b = $(this).prop('disabled', true);
            $.post(API, { action: 'add_hearing', id: caseId, kind: hearingKind, title: title, hearing_at: date + ' ' + time, location: $('#hearingLocation').val().trim(), note: $('#hearingNote').val().trim() }, null, 'json')
                .done(function (r) {
                    if (r.success) {
                        $('#hearingTitle').val(''); fpClear(fpHearingDt, '#hearingDate');
                        $('#hearingTime').val(DEFAULT_TIME); $('#hearingLocation').val(''); $('#hearingNote').val('');
                        loadHearings();
                    } else toast(r.message || 'Грешка.', 'error');
                }).always(function () { $b.prop('disabled', false); });
        });

        $('#hearingList').on('click', '.hearing-edit', function () {
            var $h = $(this).closest('.hearing');
            if ($h.hasClass('editing')) return;
            var h = hearings.find(function (x) { return String(x.id) === String($h.data('id')); });
            if (!h) return;
            $h.addClass('editing');
            var ek = kindOf(h);
            var chips = ['hearing', 'meeting'].map(function (kk) {
                return '<button type="button" class="hkind-chip hkind--' + kk + (kk === ek ? ' is-active' : '') + '" data-kind="' + kk + '">' + HKIND[kk] + '</button>';
            }).join('');
            $h.html(
                '<div class="hearing-edit-form">'
                + '<div class="hkind-chips hearing-edit-kind">' + chips + '</div>'
                + '<input type="text" class="field hearing-edit-title">'
                + '<div class="hearing-compose-row"><input type="text" class="field hearing-edit-date" placeholder="Датум" value="' + String(h.hearing_at).slice(0, 10) + '">'
                + '<select class="field hearing-edit-time"></select>'
                + '<input type="text" class="field hearing-edit-loc" placeholder="Локација (опц.)"></div>'
                + '<textarea class="field hearing-edit-note" rows="2" placeholder="Белешка (опц.)"></textarea>'
                + '<div class="note-compose-foot"><button class="btn-modal-cancel hearing-cancel">Откажи</button><button class="btn-modal-save hearing-save">Зачувај</button></div>'
                + '</div>'
            );
            fpDate($h.find('.hearing-edit-date')[0]);
            fillTimes($h.find('.hearing-edit-time')[0], String(h.hearing_at).slice(11, 16));
            $h.find('.hearing-edit-title').val(h.title).focus();
            $h.find('.hearing-edit-loc').val(h.location || '');
            $h.find('.hearing-edit-note').val(h.note || '');
        });
        $('#hearingList').on('click', '.hearing-edit-kind .hkind-chip', function () {
            $(this).closest('.hkind-chips').find('.hkind-chip').removeClass('is-active');
            $(this).addClass('is-active');
        });
        $('#hearingList').on('click', '.hearing-cancel', renderHearings);
        $('#hearingList').on('click', '.hearing-save', function () {
            var $h = $(this).closest('.hearing');
            var title = $h.find('.hearing-edit-title').val().trim();
            var date = $h.find('.hearing-edit-date').val();
            var time = $h.find('.hearing-edit-time').val();
            var ekind = $h.find('.hearing-edit-kind .hkind-chip.is-active').data('kind') || 'hearing';
            if (!title || !date || !time) { toast('Внеси наслов, датум и време.', 'error'); return; }
            $.post(API, { action: 'update_hearing', hearing_id: $h.data('id'), kind: ekind, title: title, hearing_at: date + ' ' + time, location: $h.find('.hearing-edit-loc').val().trim(), note: $h.find('.hearing-edit-note').val().trim() }, null, 'json')
                .done(function (r) { if (r.success) loadHearings(); else toast(r.message || 'Грешка.', 'error'); });
        });
        $('#hearingList').on('click', '.hearing-del', function () {
            var id = $(this).closest('.hearing').data('id');
            confirmDialog({
                title: 'Бришење настан', danger: true, message: 'Избриши го настанот? Ова не може да се врати.',
                confirmText: 'Избриши', cancelText: 'Откажи',
                onConfirm: function () {
                    $.post(API, { action: 'delete_hearing', hearing_id: id }, null, 'json')
                        .done(function (r) { if (r.success) loadHearings(); else toast(r.message || 'Грешка.', 'error'); });
                }
            });
        });

        /* ---------------- Документи (uploaded files) ---------------- */
        var docs = [];
        function extBadge(ext) { return '<span class="doc-ext doc-ext--' + (ext || 'file') + '">' + (ext ? ext.toUpperCase() : 'FILE') + '</span>'; }
        function fmtSize(b) {
            b = +b || 0;
            if (b < 1024) return b + ' B';
            if (b < 1048576) return (b / 1024).toFixed(0) + ' KB';
            return (b / 1048576).toFixed(1) + ' MB';
        }
        function docHtml(g) {
            return '<div class="doc-row" data-id="' + g.id + '">'
                + extBadge(g.ext)
                + '<div class="doc-info"><div class="doc-name">' + esc(g.orig_name) + '</div>'
                +   '<div class="doc-meta">' + fmtSize(g.size_bytes) + ' · ' + when(g.created_at) + (g.uploaded_by_name ? ' · ' + esc(g.uploaded_by_name) : '') + '</div></div>'
                + '<div class="doc-actions">'
                +   '<a class="btn-secondary doc-dl" href="' + API + '?action=download_document&file_id=' + g.id + '">Преземи</a>'
                +   '<button class="doc-unlink" title="Избриши"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg></button>'
                + '</div></div>';
        }
        function renderDocs() {
            $('#tabDocs').text(docs.length);
            $('#docList').html(docs.length ? docs.map(docHtml).join('') : '<p class="case-empty">Нема прикачени документи. Кликни „Прикачи" или повлечи датотеки тука.</p>');
        }
        function loadDocs() {
            $.ajax({ url: API, data: { action: 'get_documents', id: caseId }, dataType: 'json' })
                .done(function (res) { docs = (res && res.data) || []; renderDocs(); })
                .fail(function () { $('#docList').html('<p class="case-empty">Грешка при вчитување.</p>'); });
        }

        var uploading = false;
        function uploadFiles(fileList) {
            var arr = Array.prototype.slice.call(fileList || []);
            if (!arr.length || uploading) return;
            uploading = true;
            $('#docUploadBtn').addClass('is-busy'); $('#docUploadLabel').text('Се прикачува…');
            var done = 0, failed = 0;
            function next(i) {
                if (i >= arr.length) {
                    uploading = false;
                    $('#docUploadBtn').removeClass('is-busy'); $('#docUploadLabel').text('Прикачи');
                    if (done) toast('Прикачени ' + done + ' документи' + (failed ? ' · ' + failed + ' неуспешни' : '') + '.', failed ? 'error' : 'success');
                    else if (failed) toast('Прикачувањето не успеа.', 'error');
                    loadDocs();
                    return;
                }
                var fd = new FormData();
                fd.append('action', 'upload_document'); fd.append('id', caseId); fd.append('file', arr[i]);
                $.ajax({ url: API, type: 'POST', data: fd, processData: false, contentType: false, dataType: 'json' })
                    .done(function (r) { if (r && r.success) done++; else { failed++; toast((r && r.message) || 'Грешка при прикачување.', 'error'); } })
                    .fail(function () { failed++; })
                    .always(function () { next(i + 1); });
            }
            next(0);
        }

        $('#docFileInput').on('change', function () { uploadFiles(this.files); this.value = ''; });

        // Drag & drop onto the documents area.
        var $drop = $('#docDrop');
        $drop.on('dragover dragenter', function (e) { e.preventDefault(); e.stopPropagation(); $drop.addClass('is-dragover'); });
        $drop.on('dragleave dragend drop', function (e) { e.preventDefault(); e.stopPropagation(); $drop.removeClass('is-dragover'); });
        $drop.on('drop', function (e) {
            var dt = e.originalEvent && e.originalEvent.dataTransfer;
            if (dt && dt.files && dt.files.length) uploadFiles(dt.files);
        });

        $('#docList').on('click', '.doc-unlink', function () {
            var id = $(this).closest('.doc-row').data('id');
            confirmDialog({
                title: 'Бришење документ', message: 'Документот ќе биде трајно избришан од предметот. Продолжи?',
                confirmText: 'Избриши', cancelText: 'Откажи', danger: true,
                onConfirm: function () {
                    $.post(API, { action: 'delete_document', file_id: id }, null, 'json')
                        .done(function (r) { if (r.success) loadDocs(); else toast(r.message || 'Грешка.', 'error'); });
                }
            });
        });

        // Tab switching
        $('#caseTabsNav').on('click', '.case-tab-btn', function () {
            var tab = $(this).data('tab');
            $('#caseTabsNav .case-tab-btn').removeClass('is-active');
            $(this).addClass('is-active');
            $('.case-tab-panel').removeClass('is-active').filter('[data-tab="' + tab + '"]').addClass('is-active');
        });

        loadDocs();
        loadHearings();
        loadTodos();
        loadNotes();
    });
    </script>
    <?php endif; ?>
</body>
</html>

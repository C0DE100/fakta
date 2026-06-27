<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

// Audit log is for admins + employees only (not praktikant, not super-admin).
if (!in_array(current_role(), ['admin', 'employee'], true)) {
    header('Location: ' . fakta_url('index.php'));
    exit;
}

$currentPage = 'dnevnik';
$pdo = $GLOBALS['fakta_db']->getConnection();
$companyId = current_company_id();

$stmt = $pdo->prepare(
    'SELECT a.*, u.name AS live_name
     FROM audit_log a
     LEFT JOIN users u ON u.id = a.user_id
     WHERE a.company_id = ?
     ORDER BY a.id DESC
     LIMIT 300'
);
$stmt->execute([$companyId]);
$rows = $stmt->fetchAll();

// Human labels for action codes.
$ACTIONS = [
    'document.create'    => ['Креиран документ', 'create'],
    'document.update'    => ['Уреден документ', 'edit'],
    'document.delete'    => ['Избришан документ', 'delete'],
    'document.import'    => ['Импортиран документ', 'create'],
    'document.duplicate' => ['Дуплиран документ', 'create'],
    'document.rename'    => ['Преименуван документ', 'edit'],
    'document.download'  => ['Преземен документ', 'view'],
    'document.generate'        => ['Генериран документ за клиент', 'create'],
    'document.generate_delete' => ['Отстранет генериран документ', 'delete'],
    'template.create'    => ['Креиран шаблон', 'create'],
    'template.update'    => ['Уреден шаблон', 'edit'],
    'template.delete'    => ['Избришан шаблон', 'delete'],
    'folder.create'      => ['Креирана папка', 'create'],
    'folder.delete'      => ['Избришана папка', 'delete'],
    'client.create'      => ['Креиран клиент', 'create'],
    'client.update'      => ['Уреден клиент', 'edit'],
    'client.delete'      => ['Клиент во корпа', 'delete'],
    'client.restore'     => ['Вратен клиент', 'create'],
    'client.purge'       => ['Трајно избришан клиент', 'delete'],
    'case.create'              => ['Креиран предмет', 'create'],
    'case.update'              => ['Уреден предмет', 'edit'],
    'case.archive'             => ['Архивиран предмет', 'archive'],
    'case.unarchive'           => ['Вратен од архива', 'create'],
    'case.delete'              => ['Предмет во корпа', 'delete'],
    'case.restore'             => ['Вратен предмет', 'create'],
    'case.purge'               => ['Трајно избришан предмет', 'delete'],
    'case.admin_number'        => ['Додаден админ. број', 'edit'],
    'case.admin_number_edit'   => ['Уреден админ. број', 'edit'],
    'case.admin_number_delete' => ['Избришан админ. број', 'delete'],
    'case.import'              => ['Импортирани предмети (CSV)', 'create'],
    'case.note'                => ['Додадена белешка', 'edit'],
    'case.todo'                => ['Додадена задача', 'edit'],
    'case.status'              => ['Сменет статус на предмет', 'edit'],
    'case.hearing'             => ['Додаден настан', 'create'],
    'case.document'            => ['Прикачен документ на предмет', 'create'],
];

$MK_MONTHS = ['јан','фев','мар','апр','мај','јун','јул','авг','сеп','окт','ное','дек'];
function fakta_audit_when(string $s, array $months): string
{
    $t = strtotime($s);
    if (!$t) return $s;
    return date('j', $t) . ' ' . ($months[(int) date('n', $t) - 1] ?? '') . ' ' . date('Y, H:i', $t);
}
?>
<!DOCTYPE html>
<html lang="mk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Активности – Факта</title>
    <script>if(localStorage.getItem('darkMode')==='1')document.documentElement.setAttribute('data-theme','dark');</script>
    <script src="https://cdn.tailwindcss.com"></script>
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
                <h1 class="text-lg font-semibold text-slate-800">Активности</h1>
                <p class="text-sm text-slate-400 mt-1">Кој што направил и кога · последни 300 записи</p>
            </div>
            <div class="tpl-search-wrap tpl-search-inline" style="margin-top:0.25rem">
                <svg class="tpl-search-ico" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
                <input type="text" id="auditSearch" class="field tpl-search-input" placeholder="Пребарај..." autocomplete="off">
            </div>
        </div>

        <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
            <?php if (!$rows): ?>
                <p class="list-msg" style="padding:2.5rem 1rem;text-align:center;color:#a8a29e">Сè уште нема активности.</p>
            <?php else: ?>
            <table class="audit-table" id="auditTable">
                <thead>
                    <tr><th>Време</th><th>Корисник</th><th>Дејство</th><th>Детали</th></tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $r):
                    $meta = $ACTIONS[$r['action']] ?? [$r['action'], 'view'];
                    $who  = $r['live_name'] ?? $r['user_name'] ?? 'Непознат';
                ?>
                    <tr>
                        <td class="audit-when"><?= htmlspecialchars(fakta_audit_when($r['created_at'], $MK_MONTHS)) ?></td>
                        <td><?= htmlspecialchars($who) ?></td>
                        <td><span class="audit-badge audit-badge--<?= $meta[1] ?>"><?= htmlspecialchars($meta[0]) ?></span></td>
                        <td class="audit-detail"><?php
                            $detail = $r['detail'] ?? '';
                            if ($r['entity'] === 'case' && !empty($r['entity_id']) && $r['action'] !== 'case.purge') {
                                $label = $detail !== '' ? $detail : ('Предмет #' . (int) $r['entity_id']);
                                echo '<a class="audit-link" href="' . htmlspecialchars(fakta_url('predmet.php?id=' . (int) $r['entity_id'])) . '">' . htmlspecialchars($label) . '</a>';
                            } else {
                                echo htmlspecialchars($detail);
                            }
                        ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <p id="auditNoResults" class="list-msg" style="display:none;padding:2rem 1rem;text-align:center;color:#a8a29e">Нема резултати за пребарувањето.</p>
            <?php endif; ?>
        </div>

    </div>
    </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="js/app.js"></script>
    <script>
    (function () {
        var input = document.getElementById('auditSearch');
        var table = document.getElementById('auditTable');
        if (!input || !table) return;
        var rows = [].slice.call(table.tBodies[0].rows);
        var noRes = document.getElementById('auditNoResults');
        input.addEventListener('input', function () {
            var q = this.value.trim().toLowerCase();
            var shown = 0;
            rows.forEach(function (tr) {
                var match = tr.textContent.toLowerCase().indexOf(q) !== -1;
                tr.style.display = match ? '' : 'none';
                if (match) shown++;
            });
            table.style.display = shown ? '' : 'none';
            if (noRes) noRes.style.display = shown ? 'none' : '';
        });
    }());
    </script>
</body>
</html>

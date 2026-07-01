<?php
$navUser     = function_exists('current_user') ? current_user() : null;
$navCompany  = $navUser['company_name'] ?? '';
$navName     = $navUser['name'] ?? '';
$navEmail    = $navUser['email'] ?? '';
$navRole     = function_exists('current_role') ? current_role() : ($navUser['role'] ?? '');
$navRoleLabel = [
    'admin'       => 'Админ',
    'employee'    => 'Вработен',
    'praktikant'  => 'Практикант',
    'super_admin' => 'Супер админ',
][$navRole] ?? '';
$navInitials = function_exists('fakta_initials') ? fakta_initials($navName) : '?';
$navColor    = function_exists('fakta_avatar_color') ? fakta_avatar_color($navName) : ['bg' => '#e7e5e4', 'fg' => '#57534e'];
$logoutUrl   = function_exists('fakta_url') ? fakta_url('logout.php') : 'logout.php';
$settingsUrl = function_exists('fakta_url') ? fakta_url('podesuvanja.php') : 'podesuvanja.php';
?>
<nav class="bg-white site-nav">
    <div class="nav-inner w-full px-5 py-5 flex items-center justify-between">
        <a href="index.php" class="font-semibold tracking-tight text-slate-900 flex items-center gap-2 no-underline" style="text-decoration:none;color:inherit;">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="m16 16 3-8 3 8c-.87.65-1.92 1-3 1s-2.13-.35-3-1Z"/><path d="m2 16 3-8 3 8c-.87.65-1.92 1-3 1s-2.13-.35-3-1Z"/>
                <path d="M7 21h10"/><path d="M12 3v18"/><path d="M3 7h2c2 0 5-1 7-2 2 1 5 2 7 2h2"/>
            </svg>
            Факта
        </a>
        <div class="flex items-center gap-4">
            <?php if ($navCompany !== ''): ?>
            <div class="text-right leading-tight hidden sm:block">
                <div class="text-sm font-semibold text-slate-800"><?= htmlspecialchars($navCompany) ?></div>
                <?php if ($navName !== ''): ?><div class="text-xs text-slate-400"><?= htmlspecialchars($navName) ?></div><?php endif; ?>
            </div>
            <?php endif; ?>

            <div class="nav-notif" id="navNotif">
                <button type="button" class="nav-bell" id="navBellBtn" aria-haspopup="true" aria-expanded="false" aria-label="Известувања">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/>
                    </svg>
                    <span class="nav-bell-badge" id="navBellBadge" hidden>0</span>
                </button>

                <div class="nav-notif-menu" id="navNotifMenu" role="menu" aria-hidden="true">
                    <div class="nav-notif-head">
                        <span class="nav-notif-title">Известувања</span>
                        <button type="button" class="nav-notif-readall" id="navNotifReadAll">Означи сите како прочитани</button>
                    </div>
                    <div class="nav-notif-list" id="navNotifList">
                        <div class="nav-notif-empty">Се вчитува…</div>
                    </div>
                </div>
            </div>

            <div class="nav-user" id="navUser">
                <button type="button" class="nav-avatar" id="navAvatarBtn" aria-haspopup="true" aria-expanded="false" aria-label="Кориснички мени"
                        style="background:<?= htmlspecialchars($navColor['bg']) ?>;color:<?= htmlspecialchars($navColor['fg']) ?>;">
                    <?= htmlspecialchars($navInitials) ?>
                </button>

                <div class="nav-menu" id="navMenu" role="menu" aria-hidden="true">
                    <div class="nav-menu-head">
                        <div class="nav-avatar nav-avatar--sm" style="background:<?= htmlspecialchars($navColor['bg']) ?>;color:<?= htmlspecialchars($navColor['fg']) ?>;">
                            <?= htmlspecialchars($navInitials) ?>
                        </div>
                        <div class="nav-menu-head-text">
                            <?php if ($navName !== ''): ?><div class="nav-menu-name"><?= htmlspecialchars($navName) ?></div><?php endif; ?>
                            <?php if ($navRoleLabel !== ''): ?><div class="nav-menu-role"><?= htmlspecialchars($navRoleLabel) ?></div><?php endif; ?>
                            <?php if ($navEmail !== ''): ?><div class="nav-menu-email"><?= htmlspecialchars($navEmail) ?></div><?php endif; ?>
                        </div>
                    </div>

                    <a href="<?= htmlspecialchars($settingsUrl) ?>" class="nav-menu-item" role="menuitem">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1Z"/>
                        </svg>
                        Поставки
                    </a>

                    <div class="nav-menu-sep"></div>

                    <a href="<?= htmlspecialchars($logoutUrl) ?>" class="nav-menu-item nav-menu-item--danger" role="menuitem">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" x2="9" y1="12" y2="12"/>
                        </svg>
                        Одјава
                    </a>
                </div>
            </div>
        </div>
    </div>
</nav>
<script>
(function () {
    var wrap = document.getElementById('navUser');
    if (!wrap) return;
    var btn  = document.getElementById('navAvatarBtn');
    var menu = document.getElementById('navMenu');

    function close() {
        wrap.classList.remove('open');
        btn.setAttribute('aria-expanded', 'false');
        menu.setAttribute('aria-hidden', 'true');
    }
    function toggle() {
        var open = wrap.classList.toggle('open');
        btn.setAttribute('aria-expanded', open ? 'true' : 'false');
        menu.setAttribute('aria-hidden', open ? 'false' : 'true');
    }

    btn.addEventListener('click', function (e) { e.stopPropagation(); toggle(); });
    document.addEventListener('click', function (e) { if (!wrap.contains(e.target)) close(); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') close(); });
}());
</script>
<script>
window.FAKTA_CO   = <?= json_encode((string)(function_exists('current_company_id') ? (current_company_id() ?? '') : '')) ?>;
window.FAKTA_ROLE = <?= json_encode((string)(function_exists('current_role') ? (current_role() ?? '') : '')) ?>;
window.FAKTA_UID  = <?= json_encode((int)($navUser['id'] ?? 0)) ?>;
window.FAKTA_CSRF = <?= json_encode(function_exists('fakta_csrf') ? fakta_csrf() : '') ?>;
</script>
<!-- CSRF: attach the session token to every state-changing request -->
<script src="js/csrf.js" defer></script>
<!-- Global toast + confirm dialog helpers (window.toast / window.confirmDialog) -->
<script src="js/toast.js" defer></script>
<!-- Global "нов предмет" draft pill, docked on every page -->
<script src="js/case-draft.js" defer></script>
<!-- Global "Користи шаблон" workspace, docked on every page -->
<script src="js/draft-workspace.js" defer></script>
<!-- Notification bell (top-nav) -->
<script src="js/notifications.js" defer></script>

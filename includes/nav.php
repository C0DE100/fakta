<?php
$navUser    = function_exists('current_user') ? current_user() : null;
$navCompany = $navUser['company_name'] ?? '';
$navName    = $navUser['name'] ?? '';
$logoutUrl  = function_exists('fakta_url') ? fakta_url('logout.php') : 'logout.php';
?>
<nav class="bg-white site-nav">
    <div class="nav-inner max-w-7xl mx-auto px-7 py-5 flex items-center justify-between">
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
            <a href="<?= htmlspecialchars($logoutUrl) ?>" class="nav-logout inline-flex items-center gap-2 text-sm font-medium py-2.5 px-4 rounded-lg cursor-pointer select-none no-underline transition-colors">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" x2="9" y1="12" y2="12"/>
                </svg>
                Одјава
            </a>
        </div>
    </div>
</nav>
<script>window.FAKTA_CO = <?= json_encode((string)(function_exists('current_company_id') ? (current_company_id() ?? '') : '')) ?>;</script>
<!-- Global drafts (docked on every page): use-template workspace + in-progress document -->
<script src="js/draft-workspace.js" defer></script>
<script src="js/draft-document.js" defer></script>

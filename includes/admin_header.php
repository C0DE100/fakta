<?php
/*
|------------------------------------------------------------------------------
| Shared chrome for every admin/* screen.
|------------------------------------------------------------------------------
| Usage (after require_role('super_admin')):
|   $adminTitle = 'Компании';
|   $adminBack  = ['url' => fakta_url('admin/index.php'), 'label' => 'Компании']; // optional
|   require __DIR__ . '/../includes/admin_header.php';
|   ... page <main> ... then require admin_footer.php
*/
$me        = current_user();
$adminCss  = fakta_url('css/admin.css');
$logoutUrl = fakta_url('logout.php');
$adminBack = $adminBack ?? null;
?>
<!DOCTYPE html>
<html lang="mk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($adminTitle ?? 'Супер-админ') ?> — Факта</title>
    <script>if(localStorage.getItem('darkMode')==='1')document.documentElement.setAttribute('data-theme','dark');</script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= htmlspecialchars($adminCss) ?>">
</head>
<body>

    <div class="topbar">
        <div class="topbar-in">
            <div class="topbar-left">
                <a href="<?= htmlspecialchars(fakta_url('admin/index.php')) ?>" class="brand">
                    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="m16 16 3-8 3 8c-.87.65-1.92 1-3 1s-2.13-.35-3-1Z"/><path d="m2 16 3-8 3 8c-.87.65-1.92 1-3 1s-2.13-.35-3-1Z"/>
                        <path d="M7 21h10"/><path d="M12 3v18"/><path d="M3 7h2c2 0 5-1 7-2 2 1 5 2 7 2h2"/>
                    </svg>
                    Факта <span class="tag">СУПЕР-АДМИН</span>
                </a>
                <?php if ($adminBack): ?>
                <a href="<?= htmlspecialchars($adminBack['url']) ?>" class="crumb">
                    <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
                    <?= htmlspecialchars($adminBack['label']) ?>
                </a>
                <?php endif; ?>
            </div>
            <div class="topbar-right">
                <button class="icon-btn" id="themeToggle" type="button" title="Смени тема" aria-label="Смени тема">
                    <svg id="themeMoon" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/></svg>
                    <svg id="themeSun" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41"/></svg>
                </button>
                <span class="who"><?= htmlspecialchars($me['name']) ?></span>
                <a href="<?= htmlspecialchars($logoutUrl) ?>" class="logout">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" x2="9" y1="12" y2="12"/></svg>
                    Одјава
                </a>
            </div>
        </div>
    </div>

    <script>
    (function(){
        var btn = document.getElementById('themeToggle');
        if(!btn) return;
        btn.addEventListener('click', function(){
            var dark = document.documentElement.getAttribute('data-theme') === 'dark';
            if(dark){ document.documentElement.removeAttribute('data-theme'); localStorage.setItem('darkMode','0'); }
            else    { document.documentElement.setAttribute('data-theme','dark'); localStorage.setItem('darkMode','1'); }
        });
    })();
    </script>

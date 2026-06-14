<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

// Super-admin manages tenants only — keep them out of the company app.
if (current_role() === 'super_admin') {
    header('Location: ' . fakta_url('admin/index.php'));
    exit;
}

$role = current_role();
// Only admins see invoices for now (vraboten + praktikant don't).
$canSeeInvoices = $role === 'admin';
$userName = current_user()['name'] ?? '';
?>
<!DOCTYPE html>
<html lang="mk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Факта</title>
    <script>if(localStorage.getItem('darkMode')==='1')document.documentElement.setAttribute('data-theme','dark');</script>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="css/style.css">
</head>

<body class="min-h-screen">

    <?php include 'includes/nav.php'; ?>

    <div class="app-layout">

    <?php $currentPage = 'home'; include 'includes/sidebar.php'; ?>

    <div class="main-content">
    <div class="max-w-6xl mx-auto px-4 pb-16" id="dashboard">

        <!-- Greeting -->
        <div class="pt-10 pb-6">
            <h1 class="text-lg font-semibold text-slate-800">
                Добредојде<?= $userName !== '' ? ', ' . htmlspecialchars($userName) : '' ?>
            </h1>
            <p class="text-sm text-slate-400 mt-1">Преглед на твоите фактури и клиенти</p>
        </div>

        <!-- Stat cards -->
        <div id="dashStats" class="grid grid-cols-2 <?= $canSeeInvoices ? 'lg:grid-cols-4' : 'sm:grid-cols-2' ?> gap-3 mb-8">

            <?php if ($canSeeInvoices): ?>
            <div class="dash-card">
                <span class="dash-card-label">Фактури овој месец</span>
                <span class="dash-card-value" id="statMonth">—</span>
            </div>
            <div class="dash-card">
                <span class="dash-card-label">Испратени (во тек)</span>
                <span class="dash-card-value" id="statSent">—</span>
            </div>
            <div class="dash-card">
                <span class="dash-card-label">Нацрти</span>
                <span class="dash-card-value" id="statDraft">—</span>
            </div>
            <?php endif; ?>

            <div class="dash-card">
                <span class="dash-card-label">Вкупно клиенти</span>
                <span class="dash-card-value" id="statClients">—</span>
            </div>
        </div>

        <!-- Quick actions -->
        <div class="mb-8">
            <div class="section-label">Брзи дејства</div>
            <div class="flex flex-wrap gap-3">
                <?php if ($canSeeInvoices): ?>
                <a href="kreraj-faktura.php" class="dash-action">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="12" x2="12" y1="18" y2="12"/><line x1="9" x2="15" y1="15" y2="15"/>
                    </svg>
                    Креирај фактура
                </a>
                <?php endif; ?>
                <a href="klienti.php" class="dash-action">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" x2="19" y1="8" y2="14"/><line x1="22" x2="16" y1="11" y2="11"/>
                    </svg>
                    Креирај клиент
                </a>
                <a href="kreraj-dokument.php" class="dash-action">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/>
                    </svg>
                    Креирај документ
                </a>
            </div>
        </div>

        <?php if ($canSeeInvoices): ?>
        <!-- Recent invoices -->
        <div class="mb-6">
            <div class="flex items-center justify-between mb-2.5">
                <div class="section-label" style="margin-bottom:0;">Последни фактури</div>
                <a href="pregled-fakturi.php" class="text-sm text-slate-400 hover:text-slate-600 no-underline">Види ги сите →</a>
            </div>
            <div class="bg-white border border-slate-200 rounded-xl shadow-sm">
                <div class="inv-table">
                    <div class="inv-header">
                        <span class="inv-num">Број</span>
                        <span class="inv-name">Клиент</span>
                        <span class="inv-date">Датум</span>
                        <span class="inv-status">Статус</span>
                    </div>
                    <div id="dashRecentList"></div>
                </div>
            </div>
        </div>
        <?php endif; ?>

    </div>
    </div> <!-- /.main-content -->
    </div> <!-- /.app-layout -->

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="js/app.js"></script>
</body>
</html>

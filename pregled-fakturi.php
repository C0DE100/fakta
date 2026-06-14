<?php
require_once __DIR__ . '/includes/auth.php';
require_role('admin'); // invoices are admin-only for now
?>
<!DOCTYPE html>
<html lang="mk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Детален Преглед на Фактури – Факта</title>
    <script>if(localStorage.getItem('darkMode')==='1')document.documentElement.setAttribute('data-theme','dark');</script>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="css/style.css">
</head>

<body class="min-h-screen">

    <?php include 'includes/nav.php'; ?>

    <div class="app-layout">

    <?php $currentPage = 'pregled-fakturi'; include 'includes/sidebar.php'; ?>

    <div class="main-content">
    <div class="max-w-6xl mx-auto px-4 pb-16">

        <div class="pt-10 pb-6">
            <h1 class="text-lg font-semibold text-slate-800">Детален Преглед на Фактури</h1>
            <p class="text-sm text-slate-400 mt-1">Целосен список и детали за сите фактури</p>
        </div>

        <!-- Фактури -->
        <div id="sectionInvoices" class="mb-6">
            <div class="bg-white border border-slate-200 rounded-xl shadow-sm">
                <!-- Filter bar -->
                <div class="inv-filters">
                    <input type="search" id="searchInvoices" class="field inv-filter-search" placeholder="Пребарај по број на фактура, клиент...">
                    <input type="month" id="filterMonth" class="field inv-filter-month" lang="mk">
                    <select id="filterClient" class="field inv-filter-client">
                        <option value="">Сите клиенти</option>
                    </select>
                </div>
                <!-- Table -->
                <div class="inv-table">
                    <div class="inv-header">
                        <span class="inv-num">Број</span>
                        <span class="inv-name">Клиент</span>
                        <span class="inv-date">Датум</span>
                        <span class="inv-status">Статус</span>
                    </div>
                    <div id="invoicesList"></div>
                </div>
                <div id="invoicesPager" class="flex flex-wrap gap-1.5 px-4 py-3"></div>
            </div>
        </div>

    </div>
    </div> <!-- /.main-content -->
    </div> <!-- /.app-layout -->

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="js/app.js"></script>
</body>
</html>

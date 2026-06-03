<?php
// $currentPage should be set before including this file.
// Values: 'home' | 'kreraj-faktura' | 'pregled-fakturi'
$currentPage = $currentPage ?? 'home';
$invoicesOpen = in_array($currentPage, ['kreraj-faktura', 'pregled-fakturi']);
?>
<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <button class="sidebar-toggle" id="sidebarToggle" aria-label="Скриј страничен панел">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="m15 18-6-6 6-6"/>
            </svg>
        </button>
    </div>
    <nav class="sidebar-nav">

        <a href="index.php" class="sidebar-btn<?= $currentPage === 'home' ? ' sidebar-btn--active' : '' ?>" title="Почетна">
            <svg xmlns="http://www.w3.org/2000/svg" class="sidebar-btn-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>
            </svg>
            <span class="sidebar-btn-label">Почетна</span>
        </a>

        <button class="sidebar-btn" data-scroll="sectionClients" title="Клиенти">
            <svg xmlns="http://www.w3.org/2000/svg" class="sidebar-btn-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/>
                <path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>
            </svg>
            <span class="sidebar-btn-label">Клиенти</span>
        </button>

        <div class="sidebar-group">
            <button class="sidebar-btn sidebar-btn--parent<?= $invoicesOpen ? ' sidebar-btn--open sidebar-btn--active' : '' ?>" id="btnInvoicesToggle" title="Фактури">
                <svg xmlns="http://www.w3.org/2000/svg" class="sidebar-btn-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/>
                    <line x1="16" x2="8" y1="13" y2="13"/><line x1="16" x2="8" y1="17" y2="17"/>
                </svg>
                <span class="sidebar-btn-label">Фактури</span>
                <svg class="sidebar-btn-caret" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="m9 18 6-6-6-6"/>
                </svg>
            </button>

            <div class="sidebar-submenu<?= $invoicesOpen ? ' open' : '' ?>" id="submenuInvoices">
                <a href="kreraj-faktura.php" class="sidebar-sub-btn<?= $currentPage === 'kreraj-faktura' ? ' active' : '' ?>" title="Креирај Фактура">
                    <span class="sidebar-btn-label">Креирај Фактура</span>
                </a>
                <a href="pregled-fakturi.php" class="sidebar-sub-btn<?= $currentPage === 'pregled-fakturi' ? ' active' : '' ?>" title="Детален Преглед на Фактури">
                    <span class="sidebar-btn-label">Детален Преглед на Фактури</span>
                </a>
            </div>
        </div>

    </nav>

    <div class="sidebar-footer">
        <button class="sidebar-btn" id="darkModeToggle" title="Смени тема">
            <!-- Sun icon — visible in dark mode (click to go light) -->
            <svg id="iconSun" class="sidebar-btn-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="4"/>
                <path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41"/>
            </svg>
            <!-- Moon icon — visible in light mode (click to go dark) -->
            <svg id="iconMoon" class="sidebar-btn-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/>
            </svg>
            <span class="sidebar-btn-label">Темна тема</span>
        </button>
    </div>

</aside>
<script>
(function () {
    if (localStorage.getItem('sidebarCollapsed') === '1') {
        var s = document.getElementById('sidebar');
        s.style.transition = 'none';
        s.classList.add('collapsed');
        requestAnimationFrame(function () {
            requestAnimationFrame(function () { s.style.transition = ''; });
        });
    }
}());
</script>

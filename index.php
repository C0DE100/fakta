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
    <div class="max-w-6xl mx-auto px-4 pb-16">

        <!-- Hero -->
        <div id="hero" class="text-center pt-14 pb-10">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-12 h-12 text-slate-200 mx-auto mb-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                <path d="m16 16 3-8 3 8c-.87.65-1.92 1-3 1s-2.13-.35-3-1Z"/><path d="m2 16 3-8 3 8c-.87.65-1.92 1-3 1s-2.13-.35-3-1Z"/>
                <path d="M7 21h10"/><path d="M12 3v18"/><path d="M3 7h2c2 0 5-1 7-2 2 1 5 2 7 2h2"/>
            </svg>
            <h1 class="text-xl font-semibold text-slate-700 mb-1">Прегледна документација</h1>
            <p class="text-sm text-slate-400">на фактури и клиенти</p>
        </div>

        <!-- Фактури -->
        <div id="sectionInvoices" class="mb-6">
            <div class="section-label">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" x2="8" y1="13" y2="13"/><line x1="16" x2="8" y1="17" y2="17"/>
                </svg>
                Фактури
            </div>

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

        <!-- Клиенти -->
        <div id="sectionClients" class="mb-6">
            <div class="section-label">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/>
                    <path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                </svg>
                Клиенти
            </div>

            <div class="bg-white border border-slate-200 rounded-xl shadow-sm">
                <div class="inv-filters">
                    <input type="search" id="searchClients" class="field inv-filter-search" placeholder="Пребарај клиент...">
                    <button data-modal-open="panelSelectType" class="btn-new-client">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="12" x2="12" y1="5" y2="19"/><line x1="5" x2="19" y1="12" y2="12"/>
                        </svg>
                        Креирај клиент
                    </button>
                </div>
                <div id="clientsList" class="px-4 pt-2 pb-2"></div>
                <div id="clientsPager" class="flex flex-wrap gap-1.5 px-4 py-3"></div>
            </div>
        </div>

    </div>
    </div> <!-- /.main-content -->
    </div> <!-- /.app-layout -->

    <!-- ============================================================
         Client Modal
    ============================================================ -->
    <div id="clientModal" class="modal-overlay" aria-hidden="true">
        <div class="modal-box" role="dialog" aria-modal="true">

            <!-- Step 1: Choose type -->
            <div id="panelSelectType" class="modal-panel active">
                <div class="modal-header">
                    <h2 class="modal-title">Нов клиент</h2>
                    <button data-modal-close class="modal-close" aria-label="Затвори">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M18 6 6 18"/><path d="M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
                <p class="text-sm text-slate-500 mb-5">Избери тип на клиент за да продолжиш</p>
                <div class="flex flex-wrap gap-3">
                    <button id="btnCompany" class="inline-flex items-center gap-2 text-sm font-medium py-2.5 px-4 rounded-lg cursor-pointer select-none bg-slate-900 text-white border-0 transition-colors hover:bg-slate-700">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M6 22V4a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v18Z"/>
                            <path d="M6 12H4a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h2"/><path d="M18 9h2a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2h-2"/>
                            <path d="M10 6h4"/><path d="M10 10h4"/><path d="M10 14h4"/><path d="M10 18h4"/>
                        </svg>
                        Правно лице
                    </button>
                    <button id="btnIndividual" class="inline-flex items-center gap-2 text-sm font-medium py-2.5 px-4 rounded-lg cursor-pointer select-none bg-white border border-slate-300 text-slate-700 transition-colors hover:bg-slate-50">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>
                        </svg>
                        Физичко лице
                    </button>
                </div>
            </div>

            <!-- Step 2a: Company form -->
            <div id="panelFormCompany" class="modal-panel">
                <div class="modal-header">
                    <div class="flex items-center gap-2">
                        <button data-go-modal="panelSelectType" class="modal-back" aria-label="Назад">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="m15 18-6-6 6-6"/>
                            </svg>
                        </button>
                        <h2 class="modal-title">Правно лице</h2>
                    </div>
                    <button data-modal-close class="modal-close" aria-label="Затвори">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M18 6 6 18"/><path d="M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
                <div id="alertCompany" style="display:none;"></div>
                <form id="formCompany" data-action="create_company" data-alert="alertCompany" class="space-y-4">
                    <div><label for="pravno_naziv"     class="block text-sm font-medium text-slate-600 mb-1.5">Назив</label>    <input type="text" class="field" id="pravno_naziv"     name="company_name" required></div>
                    <div><label for="pravno_sediste"   class="block text-sm font-medium text-slate-600 mb-1.5">Седиште</label>  <input type="text" class="field" id="pravno_sediste"   name="headquarters" required></div>
                    <div><label for="pravno_embs"      class="block text-sm font-medium text-slate-600 mb-1.5">ЕМБС</label>     <input type="text" class="field" id="pravno_embs"      name="embs"         required></div>
                    <div><label for="pravno_edb"       class="block text-sm font-medium text-slate-600 mb-1.5">ЕДБ</label>      <input type="text" class="field" id="pravno_edb"       name="edb"          required></div>
                    <div><label for="pravno_upravitel" class="block text-sm font-medium text-slate-600 mb-1.5">Управител</label><input type="text" class="field" id="pravno_upravitel" name="manager"      required></div>
                    <div class="pt-1"><button type="submit" class="inline-flex items-center gap-2 text-sm font-medium py-2.5 px-4 rounded-lg cursor-pointer select-none bg-slate-900 text-white border-0 transition-colors hover:bg-slate-700 disabled:opacity-50 disabled:cursor-not-allowed">Зачувај</button></div>
                </form>
            </div>

            <!-- Step 2b: Individual form -->
            <div id="panelFormIndividual" class="modal-panel">
                <div class="modal-header">
                    <div class="flex items-center gap-2">
                        <button data-go-modal="panelSelectType" class="modal-back" aria-label="Назад">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="m15 18-6-6 6-6"/>
                            </svg>
                        </button>
                        <h2 class="modal-title">Физичко лице</h2>
                    </div>
                    <button data-modal-close class="modal-close" aria-label="Затвори">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M18 6 6 18"/><path d="M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
                <div id="alertIndividual" style="display:none;"></div>
                <form id="formIndividual" data-action="create_individual" data-alert="alertIndividual" class="space-y-4">
                    <div><label for="fizicko_ime"    class="block text-sm font-medium text-slate-600 mb-1.5">Име и презиме</label>      <input type="text" class="field" id="fizicko_ime"    name="full_name"      required></div>
                    <div><label for="fizicko_adresa" class="block text-sm font-medium text-slate-600 mb-1.5">Адреса</label>             <input type="text" class="field" id="fizicko_adresa" name="address"        required></div>
                    <div><label for="fizicko_embg"   class="block text-sm font-medium text-slate-600 mb-1.5">ЕМБГ</label>               <input type="text" class="field" id="fizicko_embg"   name="embg"           required></div>
                    <div><label for="fizicko_licna"  class="block text-sm font-medium text-slate-600 mb-1.5">Број на лична карта</label><input type="text" class="field" id="fizicko_licna"  name="id_card_number" required></div>
                    <div class="pt-1"><button type="submit" class="inline-flex items-center gap-2 text-sm font-medium py-2.5 px-4 rounded-lg cursor-pointer select-none bg-slate-900 text-white border-0 transition-colors hover:bg-slate-700 disabled:opacity-50 disabled:cursor-not-allowed">Зачувај</button></div>
                </form>
            </div>

        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="js/app.js"></script>
</body>
</html>

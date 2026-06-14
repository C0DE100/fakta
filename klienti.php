<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

// Super-admin manages tenants only — keep them out of the company app.
if (current_role() === 'super_admin') {
    header('Location: ' . fakta_url('admin/index.php'));
    exit;
}
?>
<!DOCTYPE html>
<html lang="mk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Клиенти – Факта</title>
    <script>if(localStorage.getItem('darkMode')==='1')document.documentElement.setAttribute('data-theme','dark');</script>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="css/style.css">
</head>

<body class="min-h-screen">

    <?php include 'includes/nav.php'; ?>

    <div class="app-layout">

    <?php $currentPage = 'klienti'; include 'includes/sidebar.php'; ?>

    <div class="main-content">
    <div class="max-w-6xl mx-auto px-4 pb-16">

        <div class="pt-10 pb-6">
            <h1 class="text-lg font-semibold text-slate-800">Клиенти</h1>
            <p class="text-sm text-slate-400 mt-1">Список на сите правни и физички лица</p>
        </div>

        <!-- Клиенти -->
        <div id="sectionClients" class="mb-6">
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
                    <div>
                        <h2 class="modal-title">Нов клиент</h2>
                        <p class="modal-subtitle">Избери тип на клиент за да продолжиш</p>
                    </div>
                    <button data-modal-close class="modal-close" aria-label="Затвори">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M18 6 6 18"/><path d="M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
                <div class="type-options">
                    <button id="btnCompany" class="type-option">
                        <span class="type-option-icon type-option-icon--company">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M6 22V4a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v18Z"/><path d="M6 12H4a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h2"/><path d="M18 9h2a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2h-2"/><path d="M10 6h4"/><path d="M10 10h4"/><path d="M10 14h4"/><path d="M10 18h4"/>
                            </svg>
                        </span>
                        <span class="type-option-text">
                            <span class="type-option-title">Правно лице</span>
                            <span class="type-option-desc">Фирма, компанија или институција</span>
                        </span>
                        <svg class="type-option-arrow" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="m9 18 6-6-6-6"/>
                        </svg>
                    </button>
                    <button id="btnIndividual" class="type-option">
                        <span class="type-option-icon type-option-icon--individual">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>
                            </svg>
                        </span>
                        <span class="type-option-text">
                            <span class="type-option-title">Физичко лице</span>
                            <span class="type-option-desc">Поединец / физичко лице</span>
                        </span>
                        <svg class="type-option-arrow" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="m9 18 6-6-6-6"/>
                        </svg>
                    </button>
                </div>
            </div>

            <!-- Step 2a: Company form -->
            <div id="panelFormCompany" class="modal-panel modal-panel--profile">
                <div class="profile-hero">
                    <button data-go-modal="panelSelectType" class="modal-back" aria-label="Назад">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="m15 18-6-6 6-6"/>
                        </svg>
                    </button>
                    <div class="client-avatar client-avatar--lg" id="avatarCompany">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M6 22V4a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v18Z"/><path d="M6 12H4a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h2"/><path d="M18 9h2a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2h-2"/><path d="M10 6h4"/><path d="M10 10h4"/><path d="M10 14h4"/><path d="M10 18h4"/>
                        </svg>
                    </div>
                    <div class="profile-hero-text">
                        <span class="profile-hero-title">Ново правно лице</span>
                        <span class="profile-hero-sub">Внеси ги податоците за клиентот</span>
                    </div>
                    <button data-modal-close class="modal-close" aria-label="Затвори">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M18 6 6 18"/><path d="M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
                <div id="alertCompany" style="display:none;"></div>
                <form id="formCompany" data-action="create_company" data-alert="alertCompany">
                    <div class="form-row">
                        <label for="pravno_naziv" class="form-row-label">Назив</label>
                        <div class="form-row-field"><input type="text" class="field" id="pravno_naziv" name="company_name" required></div>
                    </div>
                    <div class="form-row">
                        <label for="pravno_sediste" class="form-row-label">Седиште</label>
                        <div class="form-row-field"><input type="text" class="field" id="pravno_sediste" name="headquarters" required></div>
                    </div>
                    <div class="form-row">
                        <label for="pravno_embs" class="form-row-label">ЕМБС</label>
                        <div class="form-row-field"><input type="text" class="field" id="pravno_embs" name="embs" required></div>
                    </div>
                    <div class="form-row">
                        <label for="pravno_edb" class="form-row-label">ЕДБ</label>
                        <div class="form-row-field"><input type="text" class="field" id="pravno_edb" name="edb" required></div>
                    </div>
                    <div class="form-row">
                        <label for="pravno_upravitel" class="form-row-label">Управител</label>
                        <div class="form-row-field"><input type="text" class="field" id="pravno_upravitel" name="manager" required></div>
                    </div>
                    <div class="form-row">
                        <label for="pravno_email" class="form-row-label">Е-пошта</label>
                        <div class="form-row-field"><input type="email" class="field" id="pravno_email" name="email" placeholder="example@firma.mk"></div>
                    </div>
                    <div class="form-row">
                        <label for="pravno_phone" class="form-row-label">Телефон</label>
                        <div class="form-row-field"><input type="tel" class="field" id="pravno_phone" name="phone" placeholder="07X XXX XXX"></div>
                    </div>
                    <div class="form-actions">
                        <button type="button" data-go-modal="panelSelectType" class="btn-modal-cancel">Назад</button>
                        <button type="submit" class="btn-modal-save">Зачувај</button>
                    </div>
                </form>
            </div>

            <!-- Step 2b: Individual form -->
            <div id="panelFormIndividual" class="modal-panel modal-panel--profile">
                <div class="profile-hero">
                    <button data-go-modal="panelSelectType" class="modal-back" aria-label="Назад">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="m15 18-6-6 6-6"/>
                        </svg>
                    </button>
                    <div class="client-avatar client-avatar--lg" id="avatarIndividual">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>
                        </svg>
                    </div>
                    <div class="profile-hero-text">
                        <span class="profile-hero-title">Ново физичко лице</span>
                        <span class="profile-hero-sub">Внеси ги податоците за клиентот</span>
                    </div>
                    <button data-modal-close class="modal-close" aria-label="Затвори">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M18 6 6 18"/><path d="M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
                <div id="alertIndividual" style="display:none;"></div>
                <form id="formIndividual" data-action="create_individual" data-alert="alertIndividual">
                    <div class="form-row">
                        <label for="fizicko_ime" class="form-row-label">Име и презиме</label>
                        <div class="form-row-field"><input type="text" class="field" id="fizicko_ime" name="full_name" required></div>
                    </div>
                    <div class="form-row">
                        <label for="fizicko_adresa" class="form-row-label">Адреса</label>
                        <div class="form-row-field"><input type="text" class="field" id="fizicko_adresa" name="address" required></div>
                    </div>
                    <div class="form-row">
                        <label for="fizicko_embg" class="form-row-label">ЕМБГ</label>
                        <div class="form-row-field"><input type="text" class="field" id="fizicko_embg" name="embg" required></div>
                    </div>
                    <div class="form-row">
                        <label for="fizicko_licna" class="form-row-label">Број на лична карта</label>
                        <div class="form-row-field"><input type="text" class="field" id="fizicko_licna" name="id_card_number" required></div>
                    </div>
                    <div class="form-row">
                        <label for="fizicko_email" class="form-row-label">Е-пошта</label>
                        <div class="form-row-field"><input type="email" class="field" id="fizicko_email" name="email" placeholder="example@gmail.com"></div>
                    </div>
                    <div class="form-row">
                        <label for="fizicko_phone" class="form-row-label">Телефон</label>
                        <div class="form-row-field"><input type="tel" class="field" id="fizicko_phone" name="phone" placeholder="07X XXX XXX"></div>
                    </div>
                    <div class="form-actions">
                        <button type="button" data-go-modal="panelSelectType" class="btn-modal-cancel">Назад</button>
                        <button type="submit" class="btn-modal-save">Зачувај</button>
                    </div>
                </form>
            </div>

        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="js/app.js"></script>
</body>
</html>

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
                    <?php if (current_role() !== 'praktikant'): ?>
                    <button id="btnTrash" class="btn-secondary" title="Корпа (избришани клиенти)">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/></svg>
                        Корпа
                    </button>
                    <?php endif; ?>
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

    <!-- Trash (deleted clients) -->
    <div id="trashModal" class="modal-overlay" aria-hidden="true">
        <div class="modal-box" role="dialog" aria-modal="true" style="max-width:34rem">
            <div class="modal-header">
                <div>
                    <h2 class="modal-title">Корпа</h2>
                    <p class="modal-subtitle">Избришани клиенти · автоматски се чистат по 30 дена</p>
                </div>
                <button id="trashClose" class="modal-close" aria-label="Затвори">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="M6 6l12 12"/></svg>
                </button>
            </div>
            <div id="trashList" class="trash-list"><p class="trash-empty">Се вчитува…</p></div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="js/app.js"></script>
    <script>
    (function () {
        var btn = document.getElementById('btnTrash');
        if (!btn) return;
        var modal = document.getElementById('trashModal');
        var listEl = document.getElementById('trashList');
        var didRestore = false;

        function esc(s) { var d = document.createElement('div'); d.appendChild(document.createTextNode(s == null ? '' : s)); return d.innerHTML; }
        function clientName(c) { return c.type === 'company' ? (c.company_name || 'Правно лице') : (c.full_name || 'Физичко лице'); }
        function fmt(s) { if (!s) return ''; var d = new Date(String(s).replace(' ', 'T')); if (isNaN(d.getTime())) return ''; return ('0'+d.getDate()).slice(-2)+'.'+('0'+(d.getMonth()+1)).slice(-2)+'.'+d.getFullYear(); }

        function open() {
            didRestore = false;
            modal.classList.add('open'); modal.removeAttribute('aria-hidden'); document.body.classList.add('modal-open');
            load();
        }
        function close() {
            modal.classList.remove('open'); modal.setAttribute('aria-hidden', 'true'); document.body.classList.remove('modal-open');
            if (didRestore) location.reload(); // refresh the main list to show restored clients
        }
        function load() {
            listEl.innerHTML = '<p class="trash-empty">Се вчитува…</p>';
            fetch('api/client_api.php?action=list_deleted').then(function (r) { return r.json(); }).then(function (res) {
                if (!res.success) { listEl.innerHTML = '<p class="trash-empty">Грешка при вчитување.</p>'; return; }
                var rows = res.data || [];
                if (!rows.length) { listEl.innerHTML = '<p class="trash-empty">Корпата е празна.</p>'; return; }
                listEl.innerHTML = rows.map(function (c) {
                    return '<div class="trash-row" data-id="' + c.id + '">' +
                        '<div class="trash-row-info"><div class="trash-row-name">' + esc(clientName(c)) + '</div>' +
                            '<div class="trash-row-meta">' + (c.type === 'company' ? 'Правно лице' : 'Физичко лице') +
                            (c.deleted_at ? ' · избришан ' + fmt(c.deleted_at) : '') + '</div></div>' +
                        '<div class="trash-row-actions">' +
                            '<button class="btn-secondary trash-restore" data-id="' + c.id + '">Врати</button>' +
                            '<button class="btn-secondary btn-secondary--danger trash-purge" data-id="' + c.id + '">Избриши трајно</button>' +
                        '</div></div>';
                }).join('');
            }).catch(function () { listEl.innerHTML = '<p class="trash-empty">Грешка при поврзување.</p>'; });
        }

        function post(action, id) {
            return fetch('api/client_api.php', { method: 'POST', body: new URLSearchParams({ action: action, id: id }) })
                .then(function (r) { return r.json(); });
        }

        listEl.addEventListener('click', function (e) {
            var rb = e.target.closest('.trash-restore');
            var pb = e.target.closest('.trash-purge');
            if (rb) {
                post('restore', rb.getAttribute('data-id')).then(function (res) {
                    if (res.success) { didRestore = true; toast('Клиентот е вратен.', 'success'); load(); }
                    else toast(res.message || 'Грешка.', 'error');
                }).catch(function () { toast('Грешка при поврзување.', 'error'); });
            } else if (pb) {
                var id = pb.getAttribute('data-id');
                confirmDialog({
                    title: 'Трајно бришење', danger: true,
                    message: 'Овој клиент ќе биде трајно избришан и не може да се врати. Продолжи?',
                    confirmText: 'Избриши трајно', cancelText: 'Откажи',
                    onConfirm: function () {
                        post('force_delete', id).then(function (res) {
                            if (res.success) { toast('Клиентот е трајно избришан.', 'success'); load(); }
                            else toast(res.message || 'Грешка.', 'error');
                        }).catch(function () { toast('Грешка при поврзување.', 'error'); });
                    }
                });
            }
        });

        btn.addEventListener('click', open);
        document.getElementById('trashClose').addEventListener('click', close);
        modal.addEventListener('click', function (e) { if (e.target === modal) close(); });
        document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && modal.classList.contains('open')) close(); });
    }());
    </script>
</body>
</html>

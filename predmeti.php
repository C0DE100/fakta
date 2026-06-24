<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

// Super-admin manages tenants only — keep them out of the company app.
if (current_role() === 'super_admin') {
    header('Location: ' . fakta_url('admin/index.php'));
    exit;
}
$canManage = current_role() !== 'praktikant';
?>
<!DOCTYPE html>
<html lang="mk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Предмети – Факта</title>
    <script>if(localStorage.getItem('darkMode')==='1')document.documentElement.setAttribute('data-theme','dark');</script>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="css/style.css">
</head>

<body class="min-h-screen">

    <?php include 'includes/nav.php'; ?>

    <div class="app-layout">
    <?php $currentPage = 'predmeti'; include 'includes/sidebar.php'; ?>

    <div class="main-content">
    <div class="max-w-6xl mx-auto px-4 pb-16">

        <div class="pt-10 pb-6">
            <h1 class="text-lg font-semibold text-slate-800">Предмети</h1>
            <p class="text-sm text-slate-400 mt-1">Список на сите предмети на канцеларијата</p>
        </div>

        <!-- Tabs: active / archived -->
        <div class="case-tabs" id="caseTabs">
            <button class="case-tab is-active" data-status="active">Активни</button>
            <button class="case-tab" data-status="archived">Архива</button>
        </div>

        <div class="bg-white border border-slate-200 rounded-xl shadow-sm">
            <div class="inv-filters">
                <input type="search" id="caseSearch" class="field inv-filter-search" placeholder="Пребарај по број, основ, странка, адвокат, админ. број...">

                <select id="caseAssignee" class="field" style="max-width:12rem">
                    <option value="">Сите вработени</option>
                </select>

                <select id="caseSort" class="field" style="max-width:11rem">
                    <option value="newest">Најнови прво</option>
                    <option value="oldest">Најстари прво</option>
                </select>

                <div class="view-toggle" id="viewToggle" title="Поглед">
                    <button class="view-toggle-btn is-active" data-view="grid" aria-label="Картички">
                        <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="7" height="7" x="3" y="3" rx="1"/><rect width="7" height="7" x="14" y="3" rx="1"/><rect width="7" height="7" x="14" y="14" rx="1"/><rect width="7" height="7" x="3" y="14" rx="1"/></svg>
                    </button>
                    <button class="view-toggle-btn" data-view="list" aria-label="Листа">
                        <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="8" x2="21" y1="6" y2="6"/><line x1="8" x2="21" y1="12" y2="12"/><line x1="8" x2="21" y1="18" y2="18"/><line x1="3" x2="3.01" y1="6" y2="6"/><line x1="3" x2="3.01" y1="12" y2="12"/><line x1="3" x2="3.01" y1="18" y2="18"/></svg>
                    </button>
                </div>

                <?php if ($canManage): ?>
                <button id="caseTrashBtn" class="btn-secondary" title="Корпа (избришани предмети)">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/></svg>
                    Корпа
                </button>
                <?php endif; ?>

                <button id="caseImportBtn" class="btn-secondary" title="Импортирај предмети од CSV">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M12 18v-6"/><path d="m9 15 3 3 3-3"/></svg>
                    Импорт
                </button>

                <button id="caseNewBtn" class="btn-new-client">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" x2="12" y1="5" y2="19"/><line x1="5" x2="19" y1="12" y2="12"/></svg>
                    Креирај предмет
                </button>
            </div>

            <div id="casesList" class="px-4 pt-2 pb-2"></div>
            <div id="casesPager" class="flex flex-wrap gap-1.5 px-4 py-3"></div>
        </div>

    </div>
    </div> <!-- /.main-content -->
    </div> <!-- /.app-layout -->

    <!-- ============================================================
         Create / Edit case modal
    ============================================================ -->
    <div id="caseModal" class="modal-overlay" aria-hidden="true">
        <div class="modal-box modal-box--wide" role="dialog" aria-modal="true">
            <div class="modal-header">
                <div>
                    <h2 class="modal-title" id="caseModalTitle">Нов предмет</h2>
                    <p class="modal-subtitle" id="caseModalSub">Внеси ги основните податоци и странките</p>
                </div>
                <button data-case-close class="modal-close" aria-label="Затвори">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="M6 6l12 12"/></svg>
                </button>
            </div>

            <div id="caseAlert" style="display:none;"></div>

            <form id="caseForm" autocomplete="off">
                <input type="hidden" id="caseId" value="">

                <!-- 1 · Basics -->
                <section class="case-block">
                    <div class="case-block-head">
                        <span class="case-block-num">1</span>
                        <div>
                            <h3 class="case-block-title">Основни податоци</h3>
                            <p class="case-block-sub">Што е предметот и колку вреди</p>
                        </div>
                    </div>

                    <div class="case-hero-field">
                        <label for="caseBasis" class="case-hero-label">Што е предметот? <span class="case-req">*</span></label>
                        <div style="position:relative">
                            <input type="text" class="field field--hero" id="caseBasis" placeholder="пр. Оштета на возило, Работен спор, Развод…" required>
                            <div id="basisSuggest" class="basis-suggest" style="display:none"></div>
                        </div>
                        <span class="case-hint">Ова е <strong>основот</strong> — насловот на предметот. Ќе ви предложиме слични постоечки основи.</span>
                    </div>

                    <div class="case-form-grid case-form-grid--2">
                        <div class="case-field">
                            <label for="caseValue" class="case-label">Вредност на спорот <span class="case-opt">(опционално)</span></label>
                            <div style="display:flex; gap:0.5rem">
                                <input type="text" inputmode="decimal" class="field" id="caseValue" placeholder="0,00" style="flex:1">
                                <select id="caseCurrency" class="field" style="max-width:6.5rem">
                                    <option value="ден">ден</option>
                                    <option value="евра">евра</option>
                                </select>
                            </div>
                        </div>
                        <div class="case-field" id="adminNumberRow">
                            <label for="caseAdminNumber" class="case-label">Административен број <span class="case-opt">(опционално)</span></label>
                            <input type="text" class="field" id="caseAdminNumber" placeholder="службен број во институција">
                        </div>
                    </div>
                </section>

                <!-- 2 · Parties -->
                <section class="case-block">
                    <div class="case-block-head">
                        <span class="case-block-num">2</span>
                        <div>
                            <h3 class="case-block-title">Странки</h3>
                            <p class="case-block-sub">Кој е во предметот — наш клиент и спротивна страна</p>
                        </div>
                    </div>

                    <div class="case-subgroup">
                        <div class="case-subhead">
                            <div>
                                <span class="case-subhead-title">Наши странки <span class="case-section-count">клиенти</span></span>
                                <span class="case-subhead-desc">Барем една мора да биде клиент на канцеларијата</span>
                            </div>
                            <button type="button" class="case-add-btn" data-add-party="client">
                                <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" x2="12" y1="5" y2="19"/><line x1="5" x2="19" y1="12" y2="12"/></svg>
                                Додај
                            </button>
                        </div>
                        <div id="clientPartyList" class="case-party-list"></div>
                    </div>

                    <div class="case-subgroup">
                        <div class="case-subhead">
                            <div>
                                <span class="case-subhead-title">Спротивни странки</span>
                                <span class="case-subhead-desc">Не се чуваат како клиенти на канцеларијата</span>
                            </div>
                            <button type="button" class="case-add-btn" data-add-party="opponent">
                                <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" x2="12" y1="5" y2="19"/><line x1="5" x2="19" y1="12" y2="12"/></svg>
                                Додај
                            </button>
                        </div>
                        <div id="opponentPartyList" class="case-party-list"></div>
                    </div>
                </section>

                <!-- 3 · Assignees -->
                <section class="case-block">
                    <div class="case-block-head">
                        <span class="case-block-num">3</span>
                        <div>
                            <h3 class="case-block-title">Задолжени</h3>
                            <p class="case-block-sub">Кој ќе работи на предметот (опционално)</p>
                        </div>
                    </div>
                    <div class="assignee-picker">
                        <div id="assigneeSelected" class="assignee-selected"></div>
                        <div class="assignee-input-wrap">
                            <svg class="assignee-search-icon" xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
                            <input type="text" class="field" id="assigneeSearch" placeholder="Пребарај и додади вработен…" autocomplete="off">
                            <div id="assigneeDropdown" class="assignee-dropdown" style="display:none"></div>
                        </div>
                    </div>
                </section>

                <div class="form-actions">
                    <button type="button" data-case-close class="btn-modal-cancel">Откажи</button>
                    <button type="submit" class="btn-modal-save" id="caseSaveBtn">Зачувај предмет</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ============================================================
         Quick "new client" modal (inline client creation)
    ============================================================ -->
    <div id="quickClientModal" class="modal-overlay" aria-hidden="true">
        <div class="modal-box" role="dialog" aria-modal="true" style="max-width:32rem">
            <div class="modal-header">
                <div>
                    <h2 class="modal-title">Нов клиент</h2>
                    <p class="modal-subtitle">Се додава во клиентите и се поврзува со предметот</p>
                </div>
                <button id="quickClientClose" class="modal-close" aria-label="Затвори">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="M6 6l12 12"/></svg>
                </button>
            </div>

            <div class="qc-type-toggle">
                <button type="button" class="qc-type is-active" data-qc-type="individual">Физичко лице</button>
                <button type="button" class="qc-type" data-qc-type="company">Правно лице</button>
            </div>

            <div id="quickClientAlert" style="display:none;"></div>

            <!-- Individual -->
            <form id="qcFormIndividual" class="qc-form">
                <div class="form-row"><label class="form-row-label">Име и презиме</label><div class="form-row-field"><input type="text" class="field" name="full_name" required></div></div>
                <div class="form-row"><label class="form-row-label">Адреса</label><div class="form-row-field"><input type="text" class="field" name="address" required></div></div>
                <div class="form-row"><label class="form-row-label">ЕМБГ</label><div class="form-row-field"><input type="text" class="field" name="embg" required></div></div>
                <div class="form-row"><label class="form-row-label">Лична карта</label><div class="form-row-field"><input type="text" class="field" name="id_card_number" required></div></div>
                <div class="form-row"><label class="form-row-label">Телефон</label><div class="form-row-field"><input type="tel" class="field" name="phone" placeholder="опц."></div></div>
                <div class="form-actions">
                    <button type="button" id="quickClientCancel" class="btn-modal-cancel">Откажи</button>
                    <button type="submit" class="btn-modal-save">Зачувај клиент</button>
                </div>
            </form>

            <!-- Company -->
            <form id="qcFormCompany" class="qc-form" style="display:none">
                <div class="form-row"><label class="form-row-label">Назив</label><div class="form-row-field"><input type="text" class="field" name="company_name" required></div></div>
                <div class="form-row"><label class="form-row-label">Седиште</label><div class="form-row-field"><input type="text" class="field" name="headquarters" required></div></div>
                <div class="form-row"><label class="form-row-label">ЕМБС</label><div class="form-row-field"><input type="text" class="field" name="embs" required></div></div>
                <div class="form-row"><label class="form-row-label">ЕДБ</label><div class="form-row-field"><input type="text" class="field" name="edb" required></div></div>
                <div class="form-row"><label class="form-row-label">Управител</label><div class="form-row-field"><input type="text" class="field" name="manager" required></div></div>
                <div class="form-actions">
                    <button type="button" id="quickClientCancel2" class="btn-modal-cancel">Откажи</button>
                    <button type="submit" class="btn-modal-save">Зачувај клиент</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ============================================================
         CSV import modal
    ============================================================ -->
    <div id="csvModal" class="modal-overlay" aria-hidden="true">
        <div class="modal-box modal-box--wide" role="dialog" aria-modal="true">
            <div class="modal-header">
                <div>
                    <h2 class="modal-title">Импорт на предмети од CSV</h2>
                    <p class="modal-subtitle">Прикачи датотека — секој ред е еден предмет</p>
                </div>
                <button id="csvClose" class="modal-close" aria-label="Затвори">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="M6 6l12 12"/></svg>
                </button>
            </div>

            <div class="csv-intro">
                <p class="csv-intro-text">
                    Колони: <strong>Основ</strong> и <strong>Клиент</strong> се задолжителни. Клиентот мора да постои во системот
                    (се совпаѓа по име). Опционални: Вредност, Валута, Административен број, Својство на клиент,
                    Спротивна странка, Тип на спротивна, Својство на спротивна, Адвокат на спротивна, Зададено на.
                </p>
                <button type="button" id="csvTemplateBtn" class="btn-secondary">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="M7 10l5 5 5-5"/><path d="M12 15V3"/></svg>
                    Преземи шаблон
                </button>
            </div>

            <label id="csvDrop" class="csv-drop">
                <input type="file" id="csvFile" accept=".csv,text/csv" hidden>
                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/></svg>
                <span class="csv-drop-text">Кликни за да избереш CSV датотека</span>
                <span class="csv-drop-name" id="csvFileName"></span>
            </label>

            <div id="csvAlert" style="display:none;"></div>

            <div id="csvPreview" style="display:none;">
                <div class="csv-summary" id="csvSummary"></div>
                <div class="csv-table-wrap">
                    <table class="csv-table" id="csvTable"></table>
                </div>
            </div>

            <div class="form-actions">
                <button type="button" id="csvCancel" class="btn-modal-cancel">Откажи</button>
                <button type="button" id="csvImportConfirm" class="btn-modal-save" disabled>Импортирај</button>
            </div>
        </div>
    </div>

    <!-- Trash -->
    <div id="caseTrashModal" class="modal-overlay" aria-hidden="true">
        <div class="modal-box" role="dialog" aria-modal="true" style="max-width:34rem">
            <div class="modal-header">
                <div>
                    <h2 class="modal-title">Корпа</h2>
                    <p class="modal-subtitle">Избришани предмети · автоматски се чистат по 30 дена</p>
                </div>
                <button id="caseTrashClose" class="modal-close" aria-label="Затвори">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="M6 6l12 12"/></svg>
                </button>
            </div>
            <div id="caseTrashList" class="trash-list"><p class="trash-empty">Се вчитува…</p></div>
        </div>
    </div>

    <script>
        window.FAKTA_CAN_MANAGE = <?= $canManage ? 'true' : 'false' ?>;
    </script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="js/app.js"></script>
    <script src="js/predmeti.js"></script>
</body>
</html>

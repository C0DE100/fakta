<?php
/**
 * Shared create / edit case modal (+ inline "new client" modal).
 * Driven by js/predmeti.js. Included by predmeti.php (create + edit from the
 * list) and predmet.php (in-place edit of the open case).
 */
?>
    <!-- ============================================================
         Create / Edit case modal
    ============================================================ -->
    <div id="caseModal" class="modal-overlay" aria-hidden="true">
        <div class="modal-box modal-box--wide modal-box--xwide modal-scroll" role="dialog" aria-modal="true">
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
                            <!-- <p class="case-block-sub">Што е предметот и колку вреди</p> -->
                        </div>
                    </div>

                    <div class="case-hero-field">
                        <label for="caseBasis" class="case-hero-label">Основ на предмет <span class="case-req">*</span></label>
                        <div style="position:relative">
                            <input type="text" class="field field--hero" id="caseBasis" placeholder="пр. Работен спор, Развод, Извршна Постапка..." required>
                            <div id="basisSuggest" class="basis-suggest" style="display:none"></div>
                        </div>
                        <!-- <span class="case-hint">Ова е <strong>основот</strong> — насловот на предметот. Ќе ви предложиме слични постоечки основи.</span> -->
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
                        <div class="case-field">
                            <label for="caseStatus" class="case-label">Статус</label>
                            <select id="caseStatus" class="field">
                                <option value="active">Активен</option>
                                <option value="waiting">Во чекање</option>
                            </select>
                        </div>
                        <div class="case-field" id="adminNumberRow">
                            <label for="caseAdminNumber" class="case-label">Административен број <span class="case-opt">(опционално)</span></label>
                            <input type="text" class="field" id="caseAdminNumber" placeholder="пр. НПН 123/23, ВПП 123/24...">
                        </div>
                        <div class="case-field" id="officialPersonRow">
                            <label for="caseOfficialPerson" class="case-label">Овластено лице (службеник) <span class="case-opt">(опционално)</span></label>
                            <div style="position:relative">
                                <input type="text" class="field" id="caseOfficialPerson" placeholder="пр. судија, нотар, извршител...">
                                <div id="officialSuggest" class="basis-suggest" style="display:none"></div>
                            </div>
                        </div>
                    </div>

                    <div class="case-field" style="margin-top:0.85rem">
                        <label class="case-label">Боја на картичка <span class="case-opt">(опционално)</span></label>
                        <div class="case-color-picker" id="caseColorPicker"></div>
                    </div>
                </section>

                <!-- 2 · Parties -->
                <section class="case-block">
                    <div class="case-block-head">
                        <span class="case-block-num">2</span>
                        <div>
                            <h3 class="case-block-title">Странки</h3>
                            <!-- <p class="case-block-sub">Кој е во предметот — наш клиент и спротивна страна</p> -->
                        </div>
                    </div>

                    <div class="case-subgroup">
                        <div class="case-subhead">
                            <div>
                                <span class="case-subhead-title">Наша странка</span>
                                <!-- <span class="case-subhead-desc">Барем една мора да биде клиент на канцеларијата</span> -->
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
                                <span class="case-subhead-title">Спротивна странка</span>
                                <!-- <span class="case-subhead-desc">Не се чуваат како клиенти на канцеларијата</span> -->
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
                <section class="case-block case-block--compact">
                    <div class="case-block-head">
                        <span class="case-block-num">3</span>
                        <h3 class="case-block-title">Додели предмет на (вработен)</h3>
                    </div>
                    <div class="assignee-picker assignee-picker--compact">
                        <div id="assigneeSelected" class="assignee-selected"></div>
                        <div class="assignee-input-wrap">
                            <svg class="assignee-search-icon" xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
                            <input type="text" class="field" id="assigneeSearch" placeholder="Пребарај вработен…" autocomplete="off">
                            <div id="assigneeDropdown" class="assignee-dropdown" style="display:none"></div>
                        </div>
                    </div>
                </section>

                <!-- 4 · Notes (create only — managed via the Белешки tab once the case exists) -->
                <section class="case-block" id="caseNoteBlock">
                    <div class="case-block-head">
                        <span class="case-block-num">4</span>
                        <h3 class="case-block-title">Белешка</h3>
                    </div>
                    <textarea class="field" id="caseInitialNote" rows="2" placeholder="Белешка за предметот (опционално)"></textarea>
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

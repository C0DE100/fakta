/* ============================================================
   Global "new case" draft pill
   ------------------------------------------------------------
   A Gmail-style docked pill (bottom-right) that lives on EVERY
   page while an unfinished "new case" draft exists — mirroring
   the "Користи шаблон" workspace pill so the two can coexist
   side by side.

   • The draft itself is written/read by js/predmeti.js (where the
     case modal lives) into localStorage, namespaced per company +
     user so a shared browser never leaks one person's draft.
   • This module only owns the pill: showing it, discarding it, and
     resuming. Resume happens in-place on predmeti.php (predmeti.js
     exposes window.faktaResumeCaseDraft); from any other page it
     navigates to predmeti.php and auto-opens the draft.
   ============================================================ */
(function () {
    'use strict';

    var KEY = 'fakta_case_draft_co' + (window.FAKTA_CO || '0') + '_u' + (window.FAKTA_UID || '0');

    function get() {
        try { var raw = localStorage.getItem(KEY); return raw ? JSON.parse(raw) : null; }
        catch (e) { return null; }
    }
    function clear() {
        try { localStorage.removeItem(KEY); } catch (e) {}
        refresh();
    }

    var pill = null;
    function mount() {
        if (pill || !document.body) return;
        pill = document.createElement('div');
        pill.id = 'caseDraftPill';
        pill.className = 'case-draft-pill';
        pill.style.display = 'none';
        // Mirrors the "Користи шаблон" docked pill (.ws-bar): a small badge,
        // a title, and a Gmail-style window button. Clicking the bar resumes.
        pill.innerHTML =
            '<div class="case-draft-pill-bar" title="Продолжи со предметот">' +
                '<span class="case-draft-pill-badge">Нацрт</span>' +
                '<span class="case-draft-pill-title">Нов предмет</span>' +
                '<button type="button" class="case-draft-pill-close" title="Отфрли нацрт" aria-label="Отфрли нацрт">' +
                    '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18M6 6l12 12"/></svg>' +
                '</button>' +
            '</div>';
        document.body.appendChild(pill);
        pill.querySelector('.case-draft-pill-bar').addEventListener('click', function (e) {
            if (e.target.closest('.case-draft-pill-close')) return;
            resume();
        });
        pill.querySelector('.case-draft-pill-close').addEventListener('click', discard);
    }

    function refresh() {
        mount();
        if (pill) pill.style.display = get() ? 'flex' : 'none';
    }

    function resume() {
        // On predmeti.php the case modal exists — resume in place. Elsewhere,
        // jump to the list page and let it open the draft on load.
        if (typeof window.faktaResumeCaseDraft === 'function') window.faktaResumeCaseDraft();
        else window.location.href = 'predmeti.php?resume_draft=1';
    }

    function discard() {
        if (!window.confirmDialog) { clear(); return; }
        window.confirmDialog({
            title: 'Отфрли нацрт', danger: true,
            message: 'Недовршениот предмет ќе биде избришан. Ова не може да се врати.',
            confirmText: 'Отфрли', cancelText: 'Задржи',
            onConfirm: clear
        });
    }

    window.FaktaCaseDraft = { KEY: KEY, get: get, clear: clear, refresh: refresh };

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', refresh);
    else refresh();
}());

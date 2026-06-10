/* ============================================================
   Global "document in progress" draft pill
   ------------------------------------------------------------
   The document editor (kreraj-dokument.php) auto-saves an in-progress
   document to sessionStorage and records an "active doc draft" marker.
   This module shows a distinct pill (bottom-right) on every OTHER page
   so the user can jump back into the draft. It is intentionally styled
   differently from the "Користи шаблон" workspace pill.

   Values are never sent to the DB here — the editor itself decides when
   to persist (Зачувај документ), which clears the draft.
   ============================================================ */
(function () {
    'use strict';

    // Per-company namespace (window.FAKTA_CO set in nav.php) keeps tenants' drafts apart.
    var ACTIVE = 'fakta_active_doc_draft_co' + (window.FAKTA_CO || '0');
    var pill = null;

    function read() {
        try { return JSON.parse(sessionStorage.getItem(ACTIVE)); }
        catch (e) { return null; }
    }
    function esc(s) {
        var d = document.createElement('div');
        d.appendChild(document.createTextNode(s == null ? '' : s));
        return d.innerHTML;
    }
    // We are inside the document editor itself when these exist.
    function onEditorPage() {
        return !!document.getElementById('docTitleInput') &&
               !!document.getElementById('pagesContainer');
    }

    function removePill() { if (pill) { pill.remove(); pill = null; } }

    function discard(a) {
        try { sessionStorage.removeItem(a.key); } catch (e) {}
        try { sessionStorage.removeItem(ACTIVE); } catch (e) {}
        removePill();
    }

    function render() {
        removePill();
        if (onEditorPage()) return;          // don't show while editing
        var a = read();
        if (!a || !a.key) return;

        var creating = a.kind !== 'edit';
        var icon = creating
            ? '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="12" y1="13" x2="12" y2="19"/><line x1="9" y1="16" x2="15" y2="16"/></svg>'
            : '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>';

        pill = document.createElement('div');
        pill.className = 'docdraft-pill';
        pill.innerHTML =
            '<button class="docdraft-main" type="button">' +
                '<span class="docdraft-icon">' + icon + '</span>' +
                '<span class="docdraft-text">' +
                    '<span class="docdraft-kind">' + (creating ? 'Се креира документ' : 'Се уредува документ') + '</span>' +
                    '<span class="docdraft-title">' + esc(a.title || 'Без наслов') + '</span>' +
                '</span>' +
            '</button>' +
            '<button class="docdraft-x" type="button" title="Отфрли нацрт">' +
                '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18M6 6l12 12"/></svg>' +
            '</button>';
        document.body.appendChild(pill);

        // Sit above the "Користи шаблон" pill when it is docked.
        if (document.querySelector('.ws-overlay.minimized')) pill.classList.add('docdraft-stacked');

        pill.querySelector('.docdraft-main').addEventListener('click', function () {
            window.location.href = a.url;
        });
        pill.querySelector('.docdraft-x').addEventListener('click', function (e) {
            e.stopPropagation();
            if (window.confirm('Да се отфрли нацртот на документот?')) discard(a);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', render);
    } else {
        render();
    }
}());

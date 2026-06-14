/* ============================================================
   Global "Користи шаблон" draft workspace
   ------------------------------------------------------------
   A Gmail-style draft that lives on EVERY page. While a draft is
   active it shows as a docked pill (bottom-right); expanding it
   opens a full-screen workspace with the variable values on the
   left and a live, inline-editable preview of every document on
   the right.

   • Entered values  → sessionStorage (per template), never the DB.
   • Static-text edits → autosaved to the document via the API.
   • Quill is loaded lazily, only the first time a draft is opened
     on a page that doesn't already have it.
   ============================================================ */
(function () {
    'use strict';

    // Namespace draft keys per company so a shared browser never leaks
    // one tenant's drafts to another (window.FAKTA_CO is set in nav.php).
    var CO = '_co' + (window.FAKTA_CO || '0');
    var STORE_ACTIVE = 'fakta_active_draft' + CO;       // {id, name}
    var QUILL_JS  = 'https://cdn.quilljs.com/1.3.7/quill.js';
    var QUILL_CSS = 'https://cdn.quilljs.com/1.3.7/quill.snow.css';

    var ov = null;          // overlay element
    var mounted = false;
    var state = { id: null, name: '', docs: null, values: {}, built: false };

    /* ── tiny helpers ─────────────────────────────────────── */
    function esc(s) {
        var d = document.createElement('div');
        d.appendChild(document.createTextNode(s == null ? '' : s));
        return d.innerHTML;
    }
    function escAttr(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/"/g, '&quot;');
    }
    function cssEsc(s) { return String(s).replace(/["\\]/g, '\\$&'); }

    /* ── draft state in sessionStorage ────────────────────── */
    function activeDraft() {
        try { return JSON.parse(sessionStorage.getItem(STORE_ACTIVE)); }
        catch (e) { return null; }
    }
    function setActiveDraft(d) {
        try {
            if (d) sessionStorage.setItem(STORE_ACTIVE, JSON.stringify(d));
            else   sessionStorage.removeItem(STORE_ACTIVE);
        } catch (e) {}
    }
    function valsKey(id) { return 'fakta_tpl_vals_' + id + CO; }
    function loadVals(id) {
        try { return JSON.parse(sessionStorage.getItem(valsKey(id))) || {}; }
        catch (e) { return {}; }
    }
    function saveVals() {
        try { sessionStorage.setItem(valsKey(state.id), JSON.stringify(state.values)); }
        catch (e) {}
    }

    /* ── variable helpers ─────────────────────────────────── */
    function getVarsFromDocs(docs) {
        var map = {};
        (docs || []).forEach(function (doc) {
            (doc.variables || []).forEach(function (v) {
                if (!map[v]) map[v] = [];
                if (map[v].indexOf(doc.name) === -1) map[v].push(doc.name);
            });
        });
        return map;
    }
    function extractVarNames(pages) {
        var seen = {}, out = [];
        (pages || []).forEach(function (page) {
            ['single', 'left', 'right'].forEach(function (k) {
                var d = page[k];
                if (!d || !d.ops) return;
                d.ops.forEach(function (op) {
                    if (op.insert && typeof op.insert === 'object' && op.insert.variable) {
                        var v = op.insert.variable;
                        if (!seen[v]) { seen[v] = true; out.push(v); }
                    }
                });
            });
        });
        return out;
    }
    // Replace variable embeds with their (draft) value for printing.
    function applyVarsToDelta(delta, values) {
        if (!delta || !delta.ops) return delta;
        var ops = [];
        delta.ops.forEach(function (op) {
            if (op.insert && typeof op.insert === 'object' && op.insert.variable) {
                var name = op.insert.variable;
                var val = (values && values[name] !== undefined && values[name] !== '')
                    ? values[name] : '[' + name + ']';
                var nop = { insert: val };
                if (op.attributes) nop.attributes = op.attributes;
                ops.push(nop);
            } else {
                ops.push(op);
            }
        });
        return { ops: ops };
    }

    /* ── lazy Quill loader + variable blot ────────────────── */
    var blotDone = false, quillLoading = false, quillQueue = [];

    function registerBlot() {
        if (blotDone || !window.Quill) return;
        var Embed = window.Quill.import('blots/embed');
        var V = class extends Embed {
            static create(value) {
                var node = super.create();
                node.className = 'ql-variable';
                node.setAttribute('data-var', value);
                node.setAttribute('contenteditable', 'false');
                node.textContent = value;
                return node;
            }
            static value(node) { return node.getAttribute('data-var'); }
        };
        V.blotName = 'variable'; V.tagName = 'span'; V.className = 'ql-variable';
        window.Quill.register(V, true);
        blotDone = true;
    }

    function ensureQuill(cb) {
        if (window.Quill) { registerBlot(); cb(); return; }
        quillQueue.push(cb);
        if (quillLoading) return;
        quillLoading = true;
        if (!document.querySelector('link[data-ws-quill]')) {
            var l = document.createElement('link');
            l.rel = 'stylesheet'; l.href = QUILL_CSS; l.setAttribute('data-ws-quill', '1');
            document.head.appendChild(l);
        }
        var s = document.createElement('script');
        s.src = QUILL_JS;
        s.onload = function () {
            registerBlot();
            quillLoading = false;
            var q = quillQueue; quillQueue = [];
            q.forEach(function (f) { f(); });
        };
        s.onerror = function () { quillLoading = false; markSaved('Грешка при вчитување (Quill)'); };
        document.head.appendChild(s);
    }

    /* ── mount overlay DOM (once per page) ─────────────────── */
    function ensureMounted() {
        if (mounted) return;
        if (!document.body) return;

        if (!document.getElementById('printZone')) {
            var pz = document.createElement('div');
            pz.id = 'printZone';
            document.body.appendChild(pz);
        }

        ov = document.createElement('div');
        ov.id = 'useWorkspace';
        ov.className = 'ws-overlay';
        ov.setAttribute('aria-hidden', 'true');
        ov.innerHTML =
            '<div class="ws-bar">' +
                '<div class="ws-bar-left">' +
                    '<span class="ws-title" id="wsTitle">Користи шаблон</span>' +
                    '<span class="ws-saved" id="wsSaved"></span>' +
                '</div>' +
                '<div class="ws-bar-actions">' +
                    '<button id="wsDownload" class="btn-new-client">' +
                        '<svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" x2="12" y1="15" y2="3"/></svg>' +
                        'Преземи PDF' +
                    '</button>' +
                    '<button id="wsMinimize" class="ws-winbtn" title="Сокриј (нацрт)"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 18h14"/></svg></button>' +
                    '<button id="wsExpand" class="ws-winbtn" title="Зголеми"><svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 3 21 3 21 9"/><polyline points="9 21 3 21 3 15"/><line x1="21" y1="3" x2="14" y2="10"/><line x1="3" y1="21" x2="10" y2="14"/></svg></button>' +
                    '<button id="wsClose" class="ws-winbtn" title="Затвори"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18M6 6l12 12"/></svg></button>' +
                '</div>' +
            '</div>' +
            '<div class="ws-body">' +
                '<aside class="ws-side">' +
                    '<div class="ws-side-head">Вредности на променливите</div>' +
                    '<div id="wsVarFields" class="ws-var-fields"></div>' +
                    '<div class="ws-side-foot">' +
                        '<p class="ws-draft-note">Внесените вредности се чуваат како нацрт во овој прелистувач (не во базата) и остануваат додека не ги исчистиш.</p>' +
                        '<button id="wsClearVals" class="btn-secondary" style="width:100%">Исчисти вредности</button>' +
                    '</div>' +
                '</aside>' +
                '<main class="ws-preview" id="wsPreview"></main>' +
            '</div>';
        document.body.appendChild(ov);

        ov.querySelector('#wsMinimize').addEventListener('click', minimize);
        ov.querySelector('#wsExpand').addEventListener('click', expand);
        ov.querySelector('#wsClose').addEventListener('click', close);
        ov.querySelector('#wsDownload').addEventListener('click', download);
        ov.querySelector('#wsClearVals').addEventListener('click', clearValues);

        // Click the docked pill's bar (not its buttons) to reopen.
        ov.querySelector('.ws-bar').addEventListener('click', function (e) {
            if (!ov.classList.contains('minimized')) return;
            if (e.target.closest('button')) return;
            expand();
        });

        ov.querySelector('#wsVarFields').addEventListener('input', function (e) {
            var inp = e.target.closest('.ws-var-input');
            if (!inp) return;
            var name = inp.getAttribute('data-var');
            state.values[name] = inp.value;
            saveVals();
            applyValueToChips(name);
        });

        mounted = true;
    }

    function markSaved(text) {
        var el = document.getElementById('wsSaved');
        if (el) el.textContent = text || '';
    }
    function setTitle() {
        var el = document.getElementById('wsTitle');
        if (el) el.textContent = 'Користи шаблон: ' + state.name;
    }

    /* ── chip value overlay ───────────────────────────────── */
    function applyChipsInEl(root) {
        root.querySelectorAll('.ql-variable[data-var]').forEach(function (el) {
            var name = el.getAttribute('data-var');
            var v = state.values[name];
            if (v !== undefined && v !== '') { el.textContent = v; el.classList.remove('ql-variable--empty'); }
            else { el.textContent = name; el.classList.add('ql-variable--empty'); }
        });
    }
    function applyValueToChips(name) {
        var hasVal = state.values[name] !== undefined && state.values[name] !== '';
        var sel = '#wsPreview .ql-variable[data-var="' + cssEsc(name) + '"]';
        document.querySelectorAll(sel).forEach(function (el) {
            el.textContent = hasVal ? state.values[name] : name;
            el.classList.toggle('ql-variable--empty', !hasVal);
        });
    }
    function applyAllValues() {
        var p = document.getElementById('wsPreview');
        if (p) applyChipsInEl(p);
    }

    /* ── autosave of inline static-text edits ─────────────── */
    var saveTimers = {}, dirty = {};

    function readDocPages(block, doc) {
        var split = !!parseInt(doc.is_split);
        var pages = [];
        block.querySelectorAll('.doc-page').forEach(function (p) {
            pages.push({
                header: (p.querySelector('.page-header-editor') || {}).innerHTML || '',
                footer: (p.querySelector('.page-footer-editor') || {}).innerHTML || '',
                single: (!split && p._qSingle) ? p._qSingle.getContents() : null,
                left:   (split && p._qLeft)   ? p._qLeft.getContents()   : null,
                right:  (split && p._qRight)  ? p._qRight.getContents()  : null,
            });
        });
        return pages;
    }
    function scheduleDocSave(doc, block) {
        doc.pages = readDocPages(block, doc);
        dirty[doc.id] = doc;
        markSaved('Зачувување…');
        clearTimeout(saveTimers[doc.id]);
        saveTimers[doc.id] = setTimeout(function () { saveDocToServer(doc); }, 700);
    }
    function saveDocToServer(doc) {
        delete dirty[doc.id];
        var params = new URLSearchParams({
            action:    'update',
            id:        doc.id,
            name:      doc.name,
            is_split:  parseInt(doc.is_split) ? 1 : 0,
            pages:     JSON.stringify(doc.pages || []),
            variables: JSON.stringify(extractVarNames(doc.pages || [])),
        });
        return fetch('api/document_api.php', { method: 'POST', body: params })
            .then(function (r) { return r.json(); })
            .then(function (res) { markSaved(res.success ? 'Зачувано' : 'Грешка при зачувување'); })
            .catch(function () { markSaved('Грешка при зачувување'); });
    }
    function flushSaves() {
        Object.keys(dirty).forEach(function (id) {
            clearTimeout(saveTimers[id]);
            saveDocToServer(dirty[id]);
        });
    }

    /* ── build the editable preview ───────────────────────── */
    function makeWsQuill(el, delta) {
        var q = new window.Quill(el, { theme: 'snow', modules: { toolbar: false } });
        if (delta) q.setContents(delta, 'silent');
        q.on('text-change', function (d, o, source) {
            if (source !== 'user') return;
            applyChipsInEl(q.root);
            var block = el.closest('.ws-doc-block');
            if (block && block._doc) scheduleDocSave(block._doc, block);
        });
        return q;
    }
    function syncHF(block, which, html) {
        var sel = which === 'header' ? '.page-header-editor' : '.page-footer-editor';
        block.querySelectorAll(sel).forEach(function (el) {
            if (el.innerHTML !== html) el.innerHTML = html;
        });
    }
    function buildPreview() {
        var host = document.getElementById('wsPreview');
        host.innerHTML = '';
        var docs = state.docs || [];
        var total = docs.length;
        docs.forEach(function (doc, idx) {
            var split = !!parseInt(doc.is_split);
            var block = document.createElement('div');
            block.className = 'ws-doc-block';
            block.innerHTML =
                '<div class="ws-doc-spine">' +
                    '<span class="ws-doc-num" title="Документ ' + (idx + 1) + ' од ' + total + '">' + (idx + 1) + '</span>' +
                    '<span class="ws-doc-rail"></span>' +
                '</div>' +
                '<div class="ws-doc-content">' +
                    '<div class="ws-doc-head">' +
                        '<div class="ws-doc-head-left">' +
                            '<svg class="ws-doc-ico" xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>' +
                            '<span class="ws-doc-name">' + esc(doc.name) + '</span>' +
                        '</div>' +
                        '<a class="btn-secondary ws-doc-edit" style="font-size:0.75rem;padding:0.3rem 0.6rem" ' +
                           'href="kreraj-dokument.php?doc_id=' + doc.id + '&template_id=' + state.id + '">Отвори во уредувач</a>' +
                    '</div>' +
                    '<div class="ws-doc-pages"></div>' +
                '</div>';
            var pagesHost = block.querySelector('.ws-doc-pages');

            (doc.pages || []).forEach(function (page) {
                var pageEl = document.createElement('div');
                pageEl.className = 'doc-page' + (split ? ' split-mode' : '');
                pageEl.innerHTML =
                    '<div class="doc-page-header"><div class="page-header-editor" contenteditable="true" spellcheck="false"></div></div>' +
                    '<div class="doc-page-body">' +
                        '<div class="doc-editor-single"><div class="q-single"></div></div>' +
                        '<div class="doc-editor-split"><div class="doc-col"><div class="q-left"></div></div>' +
                            '<div class="doc-col-divider"></div><div class="doc-col"><div class="q-right"></div></div></div>' +
                    '</div>' +
                    '<div class="doc-page-footer"><div class="page-footer-editor" contenteditable="true" spellcheck="false"></div></div>';
                pagesHost.appendChild(pageEl);

                var h = pageEl.querySelector('.page-header-editor');
                var f = pageEl.querySelector('.page-footer-editor');
                h.innerHTML = page.header || '';
                f.innerHTML = page.footer || '';

                if (split) {
                    pageEl._qLeft  = makeWsQuill(pageEl.querySelector('.q-left'),  page.left);
                    pageEl._qRight = makeWsQuill(pageEl.querySelector('.q-right'), page.right);
                } else {
                    pageEl._qSingle = makeWsQuill(pageEl.querySelector('.q-single'), page.single);
                }

                h.addEventListener('input', function () { syncHF(block, 'header', h.innerHTML); scheduleDocSave(doc, block); });
                f.addEventListener('input', function () { syncHF(block, 'footer', f.innerHTML); scheduleDocSave(doc, block); });
            });

            block._doc = doc;
            host.appendChild(block);
        });
        applyAllValues();
    }

    function renderVarFields() {
        var host   = document.getElementById('wsVarFields');
        var varMap = getVarsFromDocs(state.docs);
        var names  = Object.keys(varMap);
        if (!names.length) {
            host.innerHTML = '<p style="font-size:0.8125rem;color:#a8a29e">Овој шаблон нема променливи.</p>';
            return;
        }
        host.innerHTML = names.map(function (name) {
            var hint = varMap[name].length ? 'Употребено во: ' + varMap[name].join(', ') : '';
            var val  = state.values[name] !== undefined ? state.values[name] : '';
            return '<div class="ws-var-field">' +
                '<label class="ws-var-field-label">$' + esc(name) + '$</label>' +
                (hint ? '<div class="ws-var-field-hint">' + esc(hint) + '</div>' : '') +
                '<input type="text" class="field ws-var-input" data-var="' + escAttr(name) + '" ' +
                    'value="' + escAttr(val) + '" placeholder="Внеси вредност..." autocomplete="off">' +
                '</div>';
        }).join('');
    }

    /* ── printing ─────────────────────────────────────────── */
    function buildPrintZone(doc, values) {
        var zone = document.getElementById('printZone');
        zone.innerHTML = '';
        var wrap = document.createElement('div');
        wrap.className = 'doc-page-wrap';
        var container = document.createElement('div');
        container.style.cssText = 'display:flex;flex-direction:column;align-items:center;gap:0;padding:0;width:100%';
        wrap.appendChild(container);
        zone.appendChild(wrap);

        (doc.pages || []).forEach(function (page) {
            var pageEl = document.createElement('div');
            pageEl.className = 'doc-page' + (parseInt(doc.is_split) ? ' split-mode' : '');

            var headerEl = document.createElement('div');
            headerEl.className = 'doc-page-header';
            var he = document.createElement('div');
            he.className = 'page-header-editor';
            he.innerHTML = page.header || '';
            headerEl.appendChild(he);
            pageEl.appendChild(headerEl);

            var bodyEl = document.createElement('div');
            bodyEl.className = 'doc-page-body';

            if (parseInt(doc.is_split)) {
                var splitDiv = document.createElement('div');
                splitDiv.className = 'doc-editor-split';
                var lc = document.createElement('div'); lc.className = 'doc-col';
                var lq = document.createElement('div'); lc.appendChild(lq); splitDiv.appendChild(lc);
                var rc = document.createElement('div'); rc.className = 'doc-col';
                var rq = document.createElement('div'); rc.appendChild(rq); splitDiv.appendChild(rc);
                bodyEl.appendChild(splitDiv); pageEl.appendChild(bodyEl);
                var qL = new window.Quill(lq, { readOnly: true, theme: 'snow', modules: { toolbar: false } });
                var qR = new window.Quill(rq, { readOnly: true, theme: 'snow', modules: { toolbar: false } });
                if (page.left)  qL.setContents(applyVarsToDelta(page.left,  values), 'silent');
                if (page.right) qR.setContents(applyVarsToDelta(page.right, values), 'silent');
            } else {
                var singleDiv = document.createElement('div');
                singleDiv.className = 'doc-editor-single';
                var sq = document.createElement('div'); singleDiv.appendChild(sq);
                bodyEl.appendChild(singleDiv); pageEl.appendChild(bodyEl);
                var qS = new window.Quill(sq, { readOnly: true, theme: 'snow', modules: { toolbar: false } });
                if (page.single) qS.setContents(applyVarsToDelta(page.single, values), 'silent');
            }

            var footerEl = document.createElement('div');
            footerEl.className = 'doc-page-footer';
            var fe = document.createElement('div');
            fe.className = 'page-footer-editor';
            fe.innerHTML = page.footer || '';
            footerEl.appendChild(fe);
            pageEl.appendChild(footerEl);

            container.appendChild(pageEl);
        });
    }
    function printDoc(doc, values) {
        buildPrintZone(doc, values);
        var prevTitle = document.title;
        document.title = doc.name;
        document.body.classList.add('printing-doc');
        return new Promise(function (resolve) {
            window.addEventListener('afterprint', function handler() {
                window.removeEventListener('afterprint', handler);
                document.body.classList.remove('printing-doc');
                document.title = prevTitle;
                document.getElementById('printZone').innerHTML = '';
                setTimeout(resolve, 400);
            });
            setTimeout(function () { window.print(); }, 150);
        });
    }
    function printAllDocs(docs, values) {
        var i = 0;
        (function next() {
            if (i >= docs.length) return;
            printDoc(docs[i++], values).then(next);
        }());
    }

    /* ── open / minimize / expand / close / download ──────── */
    function loadAndBuild() {
        ensureQuill(function () {
            if (state.docs) { renderVarFields(); buildPreview(); markSaved(''); state.built = true; return; }
            markSaved('Вчитување…');
            fetch('api/document_api.php?action=list_by_template&template_id=' + encodeURIComponent(state.id))
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    if (!res.success) { markSaved('Грешка при вчитување'); return; }
                    state.docs = res.data || [];
                    Object.keys(getVarsFromDocs(state.docs)).forEach(function (n) {
                        if (state.values[n] === undefined) state.values[n] = '';
                    });
                    renderVarFields();
                    buildPreview();
                    markSaved('');
                    state.built = true;
                })
                .catch(function () { markSaved('Грешка при вчитување'); });
        });
    }

    function open(id, name) {
        ensureMounted();
        if (!ov) return;
        // Switching templates resets the in-memory build.
        if (state.id !== id) { state.docs = null; state.built = false; }
        state.id = id;
        state.name = name || ('Шаблон #' + id);
        state.values = loadVals(id);
        setActiveDraft({ id: id, name: state.name });
        setTitle();
        expand();
    }
    function expand() {
        ensureMounted();
        if (!ov) return;
        ov.classList.remove('minimized');
        ov.classList.add('open');
        ov.removeAttribute('aria-hidden');
        document.body.classList.add('ws-open');
        setTitle();
        if (!state.built) loadAndBuild();
    }
    function minimize() {
        if (!ov) return;
        ov.classList.add('minimized');
        document.body.classList.remove('ws-open');
    }
    function close() {
        flushSaves();
        if (!ov) return;
        if (ov.contains(document.activeElement)) document.activeElement.blur();
        ov.classList.remove('open');
        ov.classList.remove('minimized');
        ov.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('ws-open');
        setActiveDraft(null); // remove the floating pill everywhere
    }
    function download() {
        if (!state.docs || !state.docs.length) return;
        flushSaves();
        printAllDocs(state.docs, state.values);
    }
    function clearValues() {
        Object.keys(state.values).forEach(function (k) { state.values[k] = ''; });
        saveVals();
        document.querySelectorAll('#wsVarFields .ws-var-input').forEach(function (inp) { inp.value = ''; });
        applyAllValues();
    }

    /* ── restore the docked pill on every page load ───────── */
    function initPill() {
        var d = activeDraft();
        if (!d || !d.id) return;
        ensureMounted();
        if (!ov) return;
        state.id = d.id;
        state.name = d.name || ('Шаблон #' + d.id);
        state.values = loadVals(d.id);
        state.docs = null;
        state.built = false;
        setTitle();
        ov.classList.add('open', 'minimized');
        ov.removeAttribute('aria-hidden');
    }

    /* ── public API + boot ────────────────────────────────── */
    window.DraftWorkspace = { open: open, expand: expand, close: close };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initPill);
    } else {
        initPill();
    }
}());

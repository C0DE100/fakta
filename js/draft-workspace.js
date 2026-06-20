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
    // docId/docName set ⇒ single-document mode (preview & download just that doc,
    // and only ask for its variables); null ⇒ whole-template mode. allDocs holds
    // the full fetched list so we can re-filter without re-fetching.
    var state = { id: null, name: '', docId: null, docName: '',
                  docs: null, allDocs: null, values: {}, built: false };

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
    // Merge a document's pages[] into one continuous stream per column. New
    // docs save a single page; legacy multi-page docs concatenate into one flow.
    function mergePages(pages) {
        var single = null, left = null, right = null, header = '', footer = '';
        (pages || []).forEach(function (p) {
            if (p.single) single = single ? { ops: single.ops.concat(p.single.ops) } : { ops: p.single.ops.slice() };
            if (p.left)   left   = left   ? { ops: left.ops.concat(p.left.ops) }     : { ops: p.left.ops.slice() };
            if (p.right)  right  = right  ? { ops: right.ops.concat(p.right.ops) }   : { ops: p.right.ops.slice() };
            if (!header && p.header) header = p.header;
            if (!footer && p.footer) footer = p.footer;
        });
        return { single: single, left: left, right: right, header: header, footer: footer };
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
                    '<span class="ws-mode-badge" id="wsModeBadge"></span>' +
                    '<span class="ws-title" id="wsTitle">Преземи шаблон</span>' +
                    '<span class="ws-saved" id="wsSaved"></span>' +
                '</div>' +
                '<div class="ws-bar-actions">' +
                    '<button id="wsDownload" class="btn-new-client">' +
                        '<svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" x2="12" y1="15" y2="3"/></svg>' +
                        'Преземи' +
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
            applyImportedValues();     // also live-fill imported .docx previews
            scheduleBreaks();          // chip text changed → line wrapping may shift
        });

        window.addEventListener('resize', scheduleBreaks);

        mounted = true;
    }

    function markSaved(text) {
        var el = document.getElementById('wsSaved');
        if (el) el.textContent = text || '';
    }
    function setTitle() {
        var el = document.getElementById('wsTitle');
        if (el) {
            el.textContent = state.docId
                ? 'Преземи: ' + (state.docName || state.name)
                : 'Преземи шаблон: ' + state.name;
        }
        var badge = document.getElementById('wsModeBadge');
        if (badge) {
            badge.textContent = state.docId ? 'Единечен документ' : 'Цел шаблон';
            badge.classList.toggle('ws-mode-badge--doc', !!state.docId);
        }
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
        return [{
            header: block._headerEl ? block._headerEl.innerHTML : '',
            footer: block._footerEl ? block._footerEl.innerHTML : '',
            single: (!split && block._qSingle) ? block._qSingle.getContents() : null,
            left:   (split && block._qLeft)    ? block._qLeft.getContents()   : null,
            right:  (split && block._qRight)   ? block._qRight.getContents()  : null,
        }];
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

    /* ── page-break lines (same logic/units as the editor) ── */
    var HF_GAP_CM = 0.5, _cmPx = 0;
    function cmPx() {
        if (_cmPx) return _cmPx;
        var p = document.createElement('div');
        p.style.cssText = 'position:absolute;visibility:hidden;height:10cm;';
        document.body.appendChild(p); _cmPx = (p.offsetHeight || 378) / 10; p.remove();
        return _cmPx;
    }
    // Per-page content height = A4 27.7cm (minus @page margins) − measured
    // header/footer height (+gap), matching the print reservation.
    function blockBudgetPx(block) {
        var cm = cmPx();
        var hasH = block._headerEl && block._headerEl.textContent.trim() !== '';
        var hasF = block._footerEl && block._footerEl.textContent.trim() !== '';
        var resH = hasH ? block._headerEl.offsetHeight + HF_GAP_CM * cm : 0;
        var resF = hasF ? block._footerEl.offsetHeight + HF_GAP_CM * cm : 0;
        return Math.round(27.7 * cm - resH - resF);
    }
    // Per-block line boxes (across blocks getClientRects returns whole-block
    // rects, so measure block by block); empty blocks fall back to their box.
    function collectLines(root) {
        var rootTop = root.getBoundingClientRect().top, lines = [], blocks = root.children;
        for (var b = 0; b < blocks.length; b++) {
            var rng = document.createRange();
            rng.selectNodeContents(blocks[b]);
            var rects = rng.getClientRects();
            if (rects.length) {
                for (var j = 0; j < rects.length; j++) {
                    if (rects[j].height < 1) continue;
                    lines.push({ top: rects[j].top - rootTop, bottom: rects[j].bottom - rootTop });
                }
            } else {
                var br = blocks[b].getBoundingClientRect();
                lines.push({ top: br.top - rootTop, bottom: br.bottom - rootTop });
            }
        }
        return lines;
    }
    // Break before the first line that would overflow the page (no line is split).
    function computeBreaks(root, budget) {
        var lines = collectLines(root), breaks = [], limit = budget, prevBottom = 0;
        for (var i = 0; i < lines.length; i++) {
            if (lines[i].bottom > limit + 1) {
                var y = (lines[i].top > prevBottom) ? (prevBottom + lines[i].top) / 2 : lines[i].top;
                breaks.push(y);
                limit = lines[i].top + budget;
            }
            prevBottom = lines[i].bottom;
        }
        return breaks;
    }
    function drawDocBreaks(block) {
        if (!block || !block._guidesEl || !block._doc) return;
        var split = !!parseInt(block._doc.is_split);
        var budget = blockBudgetPx(block);
        var breaks = split
            ? (function () {
                var lb = computeBreaks(block._qLeft.root, budget);
                var rb = computeBreaks(block._qRight.root, budget);
                return lb.length >= rb.length ? lb : rb;
              }())
            : computeBreaks(block._qSingle.root, budget);
        var html = '';
        for (var i = 0; i < breaks.length; i++) {
            html += '<div class="doc-page-break" style="top:' + Math.round(breaks[i]) + 'px">' +
                        '<span class="doc-page-break-num">Крај на страница ' + (i + 1) + '</span>' +
                    '</div>';
        }
        block._guidesEl.innerHTML = html;
    }
    function redrawAllBreaks() {
        var host = document.getElementById('wsPreview');
        if (host) [].forEach.call(host.querySelectorAll('.ws-doc-block'), drawDocBreaks);
    }
    var _breakTimer = null;
    function scheduleBreaks() {
        clearTimeout(_breakTimer);
        _breakTimer = setTimeout(redrawAllBreaks, 150);
    }

    /* ── build the editable preview ───────────────────────── */
    // Allowed formats — keep pastes clean (same whitelist as the editor).
    var ALLOWED_FORMATS = [
        'bold', 'italic', 'underline', 'strike',
        'list', 'indent', 'align',
        'color', 'background', 'header',
        'variable'
    ];

    function makeWsQuill(el, delta, block) {
        var q = new window.Quill(el, { theme: 'snow', formats: ALLOWED_FORMATS, modules: { toolbar: false } });
        if (delta) q.setContents(delta, 'silent');
        q.on('text-change', function (d, o, source) {
            if (source !== 'user') return;
            applyChipsInEl(q.root);
            scheduleBreaks();
            if (block && block._doc) scheduleDocSave(block._doc, block);
        });
        return q;
    }
    /* ── imported .docx live preview (docx-preview, lazy-loaded) ──────────── */
    var dpLoading = false, dpQueue = [];
    function loadScriptOnce(src, cb) {
        var s = document.createElement('script');
        s.src = src;
        s.onload = function () { cb(); };
        s.onerror = function () { cb(new Error('load ' + src)); };
        document.head.appendChild(s);
    }
    function ensureDocxPreview(cb) {
        if (window.docx && window.JSZip) { cb(); return; }
        dpQueue.push(cb);
        if (dpLoading) return;
        dpLoading = true;
        var done = function (err) { dpLoading = false; var q = dpQueue; dpQueue = []; q.forEach(function (f) { f(err); }); };
        var loadDocx = function (err) {
            if (err) { done(err); return; }
            if (window.docx) { done(); return; }
            loadScriptOnce('https://cdn.jsdelivr.net/npm/docx-preview@0.3.5/dist/docx-preview.min.js', done);
        };
        if (window.JSZip) loadDocx();
        else loadScriptOnce('https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js', loadDocx);
    }
    // Wrap each [placeholder] in a rendered docx with a highlight span.
    function wrapImportedPlaceholders(root) {
        var walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT, null);
        var nodes = [];
        while (walker.nextNode()) nodes.push(walker.currentNode);
        nodes.forEach(function (node) {
            var text = node.nodeValue;
            if (!text || text.indexOf('[') === -1) return;
            var re = /\[([^\[\]]{1,200})\]/g;
            if (!re.test(text)) return;
            re.lastIndex = 0;
            var frag = document.createDocumentFragment(), last = 0, m;
            while ((m = re.exec(text))) {
                if (m.index > last) frag.appendChild(document.createTextNode(text.slice(last, m.index)));
                var span = document.createElement('span');
                span.className = 'ph-mark';
                span.setAttribute('data-ph', m[1].trim());
                span.textContent = '[' + m[1] + ']';
                frag.appendChild(span);
                last = m.index + m[0].length;
            }
            if (last < text.length) frag.appendChild(document.createTextNode(text.slice(last)));
            node.parentNode.replaceChild(frag, node);
        });
    }
    function renderImportedPreview(doc, el) {
        ensureDocxPreview(function (err) {
            if (err) { el.innerHTML = '<p class="ws-imported-msg">Прегледот не е достапен. Сепак можеш да го пополниш и преземеш документот.</p>'; return; }
            fetch('api/document_api.php?action=master&id=' + encodeURIComponent(doc.id))
                .then(function (r) { if (!r.ok) throw new Error('fetch'); return r.blob(); })
                .then(function (blob) {
                    el.innerHTML = '';
                    return window.docx.renderAsync(blob, el, null, { inWrapper: true, ignoreLastRenderedPageBreak: true, experimental: true });
                })
                .then(function () { wrapImportedPlaceholders(el); applyImportedValues(); })
                .catch(function () { el.innerHTML = '<p class="ws-imported-msg">Прегледот не може да се вчита.</p>'; });
        });
    }
    // Update the highlighted placeholders in every imported preview from state.values.
    function applyImportedValues() {
        (state._importedRoots || []).forEach(function (entry) {
            entry.el.querySelectorAll('.ph-mark').forEach(function (mark) {
                var name = mark.getAttribute('data-ph');
                var val = state.values[name];
                if (val !== undefined && val !== '') { mark.textContent = val; mark.classList.add('ph-filled'); }
                else { mark.textContent = '[' + name + ']'; mark.classList.remove('ph-filled'); }
            });
        });
    }

    // One editor (Quill) document block.
    function buildEditorBlock(doc, idx, total) {
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

        var sheet = document.createElement('div');
        sheet.className = 'doc-sheet' + (split ? ' split-mode' : '');
        sheet.innerHTML =
            '<div class="doc-page-header"><div class="page-header-editor" contenteditable="true" spellcheck="false" data-placeholder="Заглавие..."></div></div>' +
            '<div class="doc-sheet-body">' +
                '<div class="doc-editor-single"><div class="q-single"></div></div>' +
                '<div class="doc-editor-split"><div class="doc-col"><div class="q-left"></div></div>' +
                    '<div class="doc-col-divider"></div><div class="doc-col"><div class="q-right"></div></div></div>' +
                '<div class="doc-page-guides"></div>' +
            '</div>' +
            '<div class="doc-page-footer"><div class="page-footer-editor" contenteditable="true" spellcheck="false" data-placeholder="Подножје..."></div></div>';
        pagesHost.appendChild(sheet);

        var page = mergePages(doc.pages);
        var hEl = sheet.querySelector('.page-header-editor');
        var fEl = sheet.querySelector('.page-footer-editor');
        hEl.innerHTML = page.header || '';
        fEl.innerHTML = page.footer || '';
        block._headerEl = hEl;
        block._footerEl = fEl;
        block._guidesEl = sheet.querySelector('.doc-page-guides');
        hEl.addEventListener('input', function () { scheduleDocSave(doc, block); scheduleBreaks(); });
        fEl.addEventListener('input', function () { scheduleDocSave(doc, block); scheduleBreaks(); });

        if (split) {
            block._qLeft  = makeWsQuill(sheet.querySelector('.q-left'),  page.left,  block);
            block._qRight = makeWsQuill(sheet.querySelector('.q-right'), page.right, block);
        } else {
            block._qSingle = makeWsQuill(sheet.querySelector('.q-single'), page.single, block);
        }
        block._doc = doc;
        return block;
    }

    // One imported (.docx) document block: a live docx-preview + its own
    // "Преземи .docx" button. NOT auto-downloaded — preview first, download on click.
    function buildImportedBlock(doc, idx, total) {
        var block = document.createElement('div');
        block.className = 'ws-doc-block ws-imported-block';
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
                        '<span class="doc-format-badge" data-ext="docx" style="margin-left:.5rem">docx</span>' +
                    '</div>' +
                    '<button type="button" class="btn-new-client ws-imported-dl" style="font-size:0.75rem;padding:0.35rem 0.7rem">' +
                        '<svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" x2="12" y1="15" y2="3"/></svg>' +
                        'Преземи .docx' +
                    '</button>' +
                '</div>' +
                '<div class="ws-imported-preview"><p class="ws-imported-msg">Се вчитува преглед…</p></div>' +
            '</div>';
        block.querySelector('.ws-imported-dl').addEventListener('click', function () {
            flushSaves();
            downloadImportedFilled(doc, state.values);
        });
        var previewEl = block.querySelector('.ws-imported-preview');
        renderImportedPreview(doc, previewEl);
        state._importedRoots.push({ doc: doc, el: previewEl });
        return block;
    }

    function buildPreview() {
        var host = document.getElementById('wsPreview');
        host.innerHTML = '';
        state._importedRoots = [];
        var ordered = state.orderedDocs || [];
        var total = ordered.length;
        // Render in original document order — editor and imported interleaved.
        ordered.forEach(function (doc, idx) {
            host.appendChild(doc.kind === 'imported'
                ? buildImportedBlock(doc, idx, total)
                : buildEditorBlock(doc, idx, total));
        });

        applyAllValues();
        // Draw the page-break lines once the sheets have laid out.
        redrawAllBreaks();
        setTimeout(redrawAllBreaks, 250);
    }

    function renderVarFields() {
        var host   = document.getElementById('wsVarFields');
        var varMap = getVarsFromDocs((state.docs || []).concat(state.importedDocs || []));
        var names  = Object.keys(varMap);
        if (!names.length) {
            host.innerHTML = '<p style="font-size:0.8125rem;color:#a8a29e">Овој шаблон нема променливи.</p>';
            return;
        }
        host.innerHTML = names.map(function (name) {
            var hint = varMap[name].length ? 'Употребено во: ' + varMap[name].join(', ') : '';
            var val  = state.values[name] !== undefined ? state.values[name] : '';
            return '<div class="ws-var-field">' +
                '<label class="ws-var-field-label">' + esc(name) + '</label>' +
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
        zone.appendChild(buildPrintTable(mergePages(doc.pages), !!parseInt(doc.is_split), values));
    }

    // Measure a header/footer's rendered height at the printed content width
    // (A4 21cm − 2·3cm margins = 15cm), to reserve exactly the right space for it
    // per page (gap must match the editor's HF_GAP_CM = 0.5cm).
    function measureHFHeight(html) {
        var m = document.createElement('div');
        m.className = 'page-header-editor';
        m.style.cssText = 'position:absolute;left:-99999px;top:0;visibility:hidden;width:15cm;';
        m.innerHTML = html || '';
        document.body.appendChild(m);
        var h = m.offsetHeight;
        m.remove();
        return h;
    }

    // The header/footer are pinned to the top/bottom of EVERY printed page via
    // position:fixed (they repeat per page). The table's <thead>/<tfoot> hold
    // spacers sized to the header/footer height (+gap) so the flowing <tbody>
    // never runs under them. <tbody> breaks across A4 pages.
    function buildPrintTable(page, split, values) {
        var cols = split ? 2 : 1;
        var wrap = document.createElement('div');
        wrap.className = 'doc-print';

        if (page.header) {
            var rh = document.createElement('div'); rh.className = 'doc-print-runhdr';
            var hd = document.createElement('div'); hd.className = 'page-header-editor';
            hd.innerHTML = page.header; rh.appendChild(hd); wrap.appendChild(rh);
        }
        if (page.footer) {
            var rf = document.createElement('div'); rf.className = 'doc-print-runftr';
            var fd = document.createElement('div'); fd.className = 'page-footer-editor';
            fd.innerHTML = page.footer; rf.appendChild(fd); wrap.appendChild(rf);
        }

        var table = document.createElement('table');
        table.className = 'doc-print-table' + (split ? ' split-mode' : '');

        if (page.header) {
            var thead = document.createElement('thead');
            var htr = document.createElement('tr');
            var htd = document.createElement('td'); htd.colSpan = cols;
            var hsp = document.createElement('div'); hsp.className = 'doc-print-spacer doc-print-spacer-h';
            hsp.style.height = 'calc(' + measureHFHeight(page.header) + 'px + 0.5cm)';
            htd.appendChild(hsp); htr.appendChild(htd); thead.appendChild(htr);
            table.appendChild(thead);
        }
        if (page.footer) {
            var tfoot = document.createElement('tfoot');
            var ftr = document.createElement('tr');
            var ftd = document.createElement('td'); ftd.colSpan = cols;
            var fsp = document.createElement('div'); fsp.className = 'doc-print-spacer doc-print-spacer-f';
            fsp.style.height = 'calc(' + measureHFHeight(page.footer) + 'px + 0.5cm)';
            ftd.appendChild(fsp); ftr.appendChild(ftd); tfoot.appendChild(ftr);
            table.appendChild(tfoot);
        }

        var tbody = document.createElement('tbody');
        var tr = document.createElement('tr');
        if (split) {
            var tdL = document.createElement('td'); tdL.className = 'doc-print-col';
            var tdR = document.createElement('td'); tdR.className = 'doc-print-col';
            var lq  = document.createElement('div'); tdL.appendChild(lq);
            var rq  = document.createElement('div'); tdR.appendChild(rq);
            tr.appendChild(tdL); tr.appendChild(tdR);
            tbody.appendChild(tr); table.appendChild(tbody);
            var qL = new window.Quill(lq, { readOnly: true, theme: 'snow', modules: { toolbar: false } });
            var qR = new window.Quill(rq, { readOnly: true, theme: 'snow', modules: { toolbar: false } });
            if (page.left)  qL.setContents(applyVarsToDelta(page.left,  values), 'silent');
            if (page.right) qR.setContents(applyVarsToDelta(page.right, values), 'silent');
        } else {
            var td = document.createElement('td'); td.className = 'doc-print-cell';
            var sq = document.createElement('div'); td.appendChild(sq);
            tr.appendChild(td); tbody.appendChild(tr); table.appendChild(tbody);
            var qS = new window.Quill(sq, { readOnly: true, theme: 'snow', modules: { toolbar: false } });
            if (page.single) qS.setContents(applyVarsToDelta(page.single, values), 'silent');
        }
        wrap.appendChild(table);
        return wrap;
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
    // Narrow allDocs to the active view (one doc, or the whole template), seed
    // any missing variable values, then render.
    function applyDocFilterAndBuild() {
        var scope = state.docId != null
            ? (state.allDocs || []).filter(function (d) { return parseInt(d.id, 10) === parseInt(state.docId, 10); })
            : (state.allDocs || []);
        // Editor docs get the Quill preview + print-to-PDF; imported (.docx)
        // docs have no preview — they're filled & downloaded as .docx instead.
        state.orderedDocs  = scope;   // original document order (editor + imported)
        state.docs         = scope.filter(function (d) { return d.kind !== 'imported'; });
        state.importedDocs = scope.filter(function (d) { return d.kind === 'imported'; });
        // Collect variable/placeholder names across BOTH so one value fills all.
        Object.keys(getVarsFromDocs(state.docs.concat(state.importedDocs))).forEach(function (n) {
            if (state.values[n] === undefined) state.values[n] = '';
        });
        renderVarFields();
        buildPreview();
        markSaved('');
        state.built = true;
    }

    function loadAndBuild() {
        ensureQuill(function () {
            if (state.allDocs) { applyDocFilterAndBuild(); return; }
            markSaved('Вчитување…');
            fetch('api/document_api.php?action=list_by_template&template_id=' + encodeURIComponent(state.id))
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    if (!res.success) { markSaved('Грешка при вчитување'); return; }
                    state.allDocs = res.data || [];
                    applyDocFilterAndBuild();
                })
                .catch(function () { markSaved('Грешка при вчитување'); });
        });
    }

    // opts.docId / opts.docName ⇒ open scoped to a single document.
    function open(id, name, opts) {
        opts = opts || {};
        ensureMounted();
        if (!ov) return;
        var docId = (opts.docId != null) ? parseInt(opts.docId, 10) : null;
        // Switching template resets the fetched docs; switching template OR the
        // doc scope invalidates the current build so the preview is rebuilt.
        // refresh:true forces a re-fetch (e.g. opened right after an edit was saved).
        if (state.id !== id || opts.refresh) state.allDocs = null;
        if (state.id !== id || state.docId !== docId || opts.refresh) state.built = false;
        state.id = id;
        state.name = name || ('Шаблон #' + id);
        state.docId = docId;
        state.docName = opts.docName || '';
        state.values = loadVals(id);
        setActiveDraft({ id: id, name: state.name, docId: docId, docName: state.docName });
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
        var ordered = state.orderedDocs || [];
        if (!ordered.length) return;
        flushSaves();
        // Walk every doc in document order: editor → print to PDF (waits for the
        // print dialog to close), imported → fill & download the .docx. Sequential
        // so the order is respected and dialogs don't pile up.
        var i = 0;
        (function next() {
            if (i >= ordered.length) return;
            var doc = ordered[i++];
            var step = doc.kind === 'imported'
                ? downloadImportedFilled(doc, state.values)
                : printDoc(doc, state.values);
            Promise.resolve(step).then(next);
        }());
    }

    // POST the entered values to the API, fill the .docx master, and trigger a
    // browser download of the filled file.
    function parseFilename(cd) {
        if (!cd) return '';
        var star = /filename\*=UTF-8''([^;]+)/i.exec(cd);
        if (star) { try { return decodeURIComponent(star[1]); } catch (e) {} }
        var plain = /filename="?([^";]+)"?/i.exec(cd);
        return plain ? plain[1] : '';
    }
    function downloadImportedFilled(doc, values) {
        var fd = new FormData();
        fd.append('action', 'download_filled');
        fd.append('id', doc.id);
        fd.append('values', JSON.stringify(values || {}));
        return fetch('api/document_api.php', { method: 'POST', body: fd })
            .then(function (r) {
                if (!r.ok) return r.json().then(function (j) { throw new Error(j.message || 'Грешка'); });
                var cd = r.headers.get('Content-Disposition') || '';
                return r.blob().then(function (b) { return { blob: b, name: parseFilename(cd) }; });
            })
            .then(function (o) {
                var fname = o.name || (doc.name + '.docx');
                var url = URL.createObjectURL(o.blob);
                var a = document.createElement('a');
                a.href = url; a.download = fname;
                document.body.appendChild(a); a.click(); a.remove();
                setTimeout(function () { URL.revokeObjectURL(url); }, 1000);
            })
            .catch(function (e) { markSaved(e.message || 'Грешка при преземање'); });
    }
    function clearValues() {
        Object.keys(state.values).forEach(function (k) { state.values[k] = ''; });
        saveVals();
        document.querySelectorAll('#wsVarFields .ws-var-input').forEach(function (inp) { inp.value = ''; });
        applyAllValues();
        applyImportedValues();
    }

    /* ── restore the docked pill on every page load ───────── */
    function initPill() {
        var d = activeDraft();
        if (!d || !d.id) return;
        ensureMounted();
        if (!ov) return;
        state.id = d.id;
        state.name = d.name || ('Шаблон #' + d.id);
        state.docId = (d.docId != null) ? d.docId : null;
        state.docName = d.docName || '';
        state.values = loadVals(d.id);
        state.docs = null;
        state.allDocs = null;
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

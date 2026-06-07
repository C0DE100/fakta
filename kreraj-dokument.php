<?php
$currentPage = 'kreraj-dokument';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/classes/Database.php';

$docId      = isset($_GET['doc_id'])      ? (int)$_GET['doc_id']      : 0;
$templateId = isset($_GET['template_id']) ? (int)$_GET['template_id'] : 0;
$editDoc    = null;

if ($docId) {
    $db  = new Database(DB_HOST, DB_NAME, DB_USER, DB_PASS);
    $pdo = $db->getConnection();
    $stmt = $pdo->prepare('SELECT * FROM documents WHERE id = ?');
    $stmt->execute([$docId]);
    $editDoc = $stmt->fetch();
    if ($editDoc) {
        $editDoc['pages'] = json_decode($editDoc['pages'], true) ?: [];
        $templateId = (int)$editDoc['template_id'];
    }
}

if (!$templateId) {
    header('Location: tipski-dokumenti.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="mk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $editDoc ? htmlspecialchars($editDoc['name']) . ' – Уреди' : 'Нов документ' ?> – Факта</title>
    <script>if(localStorage.getItem('darkMode')==='1')document.documentElement.setAttribute('data-theme','dark');</script>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdn.quilljs.com/1.3.7/quill.snow.css">
    <link rel="stylesheet" href="css/style.css">
</head>

<body class="min-h-screen">

    <?php include 'includes/nav.php'; ?>

    <div class="app-layout">

    <?php include 'includes/sidebar.php'; ?>

    <div class="main-content">

        <!-- Document header bar -->
        <div class="doc-header">
            <a href="pregled-shablon.php?id=<?= $templateId ?>" class="btn-secondary">
                <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M19 12H5"/><path d="m12 19-7-7 7-7"/>
                </svg>
                Назад
            </a>
            <input type="text" id="docTitleInput" class="doc-title-input" placeholder="Назив на документот...">
            <button id="btnSplitToggle" class="btn-secondary" title="Раздели на два столбца">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect width="18" height="18" x="3" y="3" rx="2"/>
                    <path d="M12 3v18"/>
                </svg>
                Подели
            </button>
            <button id="btnInsertVar" class="btn-secondary" title="Внеси променлива (или напиши $$ во текстот)">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M8 9h8M8 12h5M8 15h3"/><rect width="18" height="18" x="3" y="3" rx="2"/>
                </svg>
                Внеси Променлива
            </button>
            <button id="btnSave" class="btn-new-client">
                <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/>
                    <polyline points="17 21 17 13 7 13 7 21"/>
                    <polyline points="7 3 7 8 15 8"/>
                </svg>
                Зачувај документ
            </button>
        </div>

        <!-- Quill toolbar (sticky) -->
        <div class="doc-toolbar-sticky">
            <div id="quill-toolbar">
                <span class="ql-formats">
                    <select class="ql-header">
                        <option value="1">Наслов 1</option>
                        <option value="2">Наслов 2</option>
                        <option value="3">Наслов 3</option>
                        <option selected="">Нормален</option>
                    </select>
                </span>
                <span class="ql-formats">
                    <button class="ql-bold"></button>
                    <button class="ql-italic"></button>
                    <button class="ql-underline"></button>
                    <button class="ql-strike"></button>
                </span>
                <span class="ql-formats">
                    <select class="ql-color"></select>
                    <select class="ql-background"></select>
                </span>
                <span class="ql-formats">
                    <button class="ql-list" value="ordered"></button>
                    <button class="ql-list" value="bullet"></button>
                    <button class="ql-indent" value="-1"></button>
                    <button class="ql-indent" value="+1"></button>
                </span>
                <span class="ql-formats">
                    <select class="ql-align"></select>
                </span>
            </div>
        </div>

        <!-- Pages area -->
        <div class="doc-page-wrap">
            <div id="pagesContainer">

                <!-- Page 1 (always present) -->
                <div class="doc-page" id="page-0">
                    <div class="doc-page-header">
                        <div class="page-header-editor"
                             contenteditable="true"
                             data-placeholder="Наслов на документот..."
                             spellcheck="false"></div>
                    </div>
                    <div class="doc-page-body">
                        <div class="doc-editor-single">
                            <div class="q-single"></div>
                        </div>
                        <div class="doc-editor-split">
                            <div class="doc-col"><div class="q-left"></div></div>
                            <div class="doc-col-divider"></div>
                            <div class="doc-col"><div class="q-right"></div></div>
                        </div>
                    </div>
                    <div class="doc-page-footer">
                        <div class="page-footer-editor"
                             contenteditable="true"
                             data-placeholder="Белешка / подножје..."
                             spellcheck="false"></div>
                    </div>
                </div>

            </div>

            <button id="btnAddPage" class="doc-add-page-btn">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M5 12h14"/><path d="M12 5v14"/>
                </svg>
                Додај страница
            </button>
        </div>

    </div>
    </div>

    <!-- Modal: enter variable name -->
    <div id="varNameModal" class="modal-overlay">
        <div class="modal-box" style="max-width:22rem">
            <div class="modal-header">
                <span class="modal-title">Внеси Променлива</span>
                <button class="modal-close" id="varNameClose">&times;</button>
            </div>
            <p style="font-size:0.8125rem;color:#78716c;margin-bottom:1rem;">Именувај ја променливата. При печатење ќе бидеш прашан за нејзината вредност.</p>
            <input type="text" id="varNameInput" class="field" placeholder="пр. ime, datum, firma..." style="width:100%;margin-bottom:1rem;" autocomplete="off" spellcheck="false">
            <div style="display:flex;gap:0.5rem;justify-content:flex-end">
                <button id="varNameCancel" class="btn-secondary">Откажи</button>
                <button id="varNameConfirm" class="btn-new-client">Внеси</button>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.quilljs.com/1.3.7/quill.js"></script>
    <script src="js/app.js"></script>
    <script>
    var EDIT_DOC    = <?= $editDoc ? json_encode($editDoc) : 'null' ?>;
    var TEMPLATE_ID = <?= $templateId ?>;
    var DOC_ID      = <?= $docId ?>;
    </script>
    <script>
    (function () {

        /* ─────────────────────────────────────────────
           Variable blot — inline non-editable chip
        ───────────────────────────────────────────── */
        var Embed = Quill.import('blots/embed');
        class VariableBlot extends Embed {
            static create(value) {
                var node = super.create();
                node.className = 'ql-variable';
                node.setAttribute('data-var', value);
                node.setAttribute('contenteditable', 'false');
                node.textContent = '$' + value + '$';
                return node;
            }
            static value(node) { return node.getAttribute('data-var'); }
        }
        VariableBlot.blotName  = 'variable';
        VariableBlot.tagName   = 'span';
        VariableBlot.className = 'ql-variable';
        Quill.register(VariableBlot);

        /* ─────────────────────────────────────────────
           State
        ───────────────────────────────────────────── */
        var splitActive = false;
        var activeQuill = null;
        var quillMain   = null;
        var pageSeq     = 0;

        /* ─────────────────────────────────────────────
           Variable name modal
        ───────────────────────────────────────────── */
        var varNameCb = null;

        function openVarNameModal(cb) {
            varNameCb = cb;
            document.getElementById('varNameInput').value = '';
            document.getElementById('varNameModal').classList.add('open');
            document.body.classList.add('modal-open');
            setTimeout(function () { document.getElementById('varNameInput').focus(); }, 50);
        }

        function closeVarNameModal() {
            document.getElementById('varNameModal').classList.remove('open');
            document.body.classList.remove('modal-open');
            varNameCb = null;
        }

        document.getElementById('varNameConfirm').addEventListener('click', function () {
            var raw = document.getElementById('varNameInput').value.trim();
            if (!raw) { document.getElementById('varNameInput').focus(); return; }
            // Keep the name as the user typed it (spaces allowed, e.g. "Ime na klient").
            // Only strip the "$" delimiter so it can't break the merge-tag markup.
            var name = raw.replace(/\$/g, '').replace(/\s+/g, ' ').trim();
            if (!name) { document.getElementById('varNameInput').focus(); return; }
            var cb = varNameCb;
            closeVarNameModal();
            if (cb) cb(name);
        });

        document.getElementById('varNameCancel').addEventListener('click', closeVarNameModal);
        document.getElementById('varNameClose').addEventListener('click',  closeVarNameModal);

        document.getElementById('varNameInput').addEventListener('keydown', function (e) {
            if (e.key === 'Enter')  { e.preventDefault(); document.getElementById('varNameConfirm').click(); }
            if (e.key === 'Escape') closeVarNameModal();
        });

        /* ─────────────────────────────────────────────
           Insert variable at cursor
        ───────────────────────────────────────────── */
        function insertVariable() {
            var q = activeQuill;
            if (!q) return;
            var range = q.getSelection(true) || { index: q.getLength() - 1, length: 0 };
            openVarNameModal(function (name) {
                q.insertEmbed(range.index, 'variable', name, 'user');
                q.setSelection(range.index + 1, 0, 'silent');
                q.focus();
            });
        }

        document.getElementById('btnInsertVar').addEventListener('click', function () {
            if (!activeQuill) activeQuill = quillMain;
            insertVariable();
        });

        /* ─────────────────────────────────────────────
           Quill helpers
        ───────────────────────────────────────────── */
        function trackFocus(editor) {
            editor.root.addEventListener('focus', function () {
                activeQuill = editor;
            });
        }

        function makeQuill(el, placeholder, ownsToolbar) {
            var q = new Quill(el, {
                theme: 'snow',
                modules: { toolbar: ownsToolbar ? '#quill-toolbar' : false },
                placeholder: placeholder || 'Започни да пишуваш...',
            });
            trackFocus(q);
            q.on('text-change', function (delta, oldDelta, source) {
                if (source !== 'user') return;

                // Page height guard
                if (q.root.scrollHeight > q.root.clientHeight) {
                    q.history.undo();
                    return;
                }

                // $$ shortcut → open variable modal
                var ops = delta.ops || [];
                var lastOp = ops[ops.length - 1];
                if (lastOp && typeof lastOp.insert === 'string' && lastOp.insert === '$') {
                    var pos = 0;
                    ops.forEach(function (op) {
                        if (typeof op.retain === 'number') pos += op.retain;
                        else if (typeof op.insert === 'string') pos += op.insert.length;
                        else if (op.insert) pos += 1;
                    });
                    if (pos >= 2 && q.getText(pos - 2, 1) === '$') {
                        q.deleteText(pos - 2, 2, 'silent');
                        q.setSelection(pos - 2, 0, 'silent');
                        activeQuill = q;
                        setTimeout(insertVariable, 0);
                    }
                }
            });
            return q;
        }

        function initSplitForPage(pageEl) {
            if (pageEl._splitReady) return;
            pageEl._splitReady = true;
            var body = pageEl.querySelector('.doc-page-body');
            pageEl._quillLeft  = makeQuill(body.querySelector('.q-left'),  'Лев текст...',   false);
            pageEl._quillRight = makeQuill(body.querySelector('.q-right'), 'Десен текст...', false);
        }

        /* ─────────────────────────────────────────────
           Header / footer sync across all pages
        ───────────────────────────────────────────── */
        document.getElementById('pagesContainer').addEventListener('input', function (e) {
            var cls = e.target.classList;
            if (cls.contains('page-header-editor') || cls.contains('page-footer-editor')) {
                var selector = cls.contains('page-header-editor')
                    ? '.page-header-editor' : '.page-footer-editor';
                var html = e.target.innerHTML;
                document.querySelectorAll(selector).forEach(function (el) {
                    if (el !== e.target) el.innerHTML = html;
                });
            }
        });

        /* ─────────────────────────────────────────────
           Initialise page 0
        ───────────────────────────────────────────── */
        var page0      = document.getElementById('page-0');
        var page0body  = page0.querySelector('.doc-page-body');
        quillMain      = makeQuill(page0body.querySelector('.q-single'), 'Започни да пишуваш...', true);
        page0._quillSingle = quillMain;
        activeQuill    = quillMain;

        /* ─────────────────────────────────────────────
           Page HTML template
        ───────────────────────────────────────────── */
        function pageHTML(id) {
            return '<div class="doc-page-header">' +
                       '<div class="page-header-editor" contenteditable="true" data-placeholder="Наслов на документот..." spellcheck="false">' +
                           (document.querySelector('.page-header-editor').innerHTML || '') +
                       '</div>' +
                   '</div>' +
                   '<div class="doc-page-body">' +
                       '<div class="doc-editor-single"><div class="q-single"></div></div>' +
                       '<div class="doc-editor-split">' +
                           '<div class="doc-col"><div class="q-left"></div></div>' +
                           '<div class="doc-col-divider"></div>' +
                           '<div class="doc-col"><div class="q-right"></div></div>' +
                       '</div>' +
                   '</div>' +
                   '<div class="doc-page-footer">' +
                       '<div class="page-footer-editor" contenteditable="true" data-placeholder="Белешка / подножје..." spellcheck="false">' +
                           (document.querySelector('.page-footer-editor').innerHTML || '') +
                       '</div>' +
                   '</div>' +
                   '<button class="doc-page-delete" data-target="' + id + '" title="Избриши страница">' +
                       '<svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18M6 6l12 12"/></svg>' +
                   '</button>';
        }

        /* ─────────────────────────────────────────────
           Create page (blank)
        ───────────────────────────────────────────── */
        function createPage() {
            pageSeq++;
            var id  = 'page-' + pageSeq;
            var div = document.createElement('div');
            div.className = 'doc-page';
            div.id = id;
            if (splitActive) div.classList.add('split-mode');
            div.innerHTML = pageHTML(id);
            document.getElementById('pagesContainer').appendChild(div);

            var body    = div.querySelector('.doc-page-body');
            var qSingle = makeQuill(body.querySelector('.q-single'), 'Започни да пишуваш...', false);
            div._quillSingle = qSingle;

            if (splitActive) initSplitForPage(div);

            div.scrollIntoView({ behavior: 'smooth', block: 'start' });
            setTimeout(function () {
                (splitActive ? div._quillLeft : qSingle).focus();
            }, 80);
        }

        /* ─────────────────────────────────────────────
           Create page with data (for EDIT_DOC restore)
        ───────────────────────────────────────────── */
        function createPageWithData(pageData) {
            pageSeq++;
            var id  = 'page-' + pageSeq;
            var div = document.createElement('div');
            div.className = 'doc-page';
            div.id = id;
            if (splitActive) div.classList.add('split-mode');
            div.innerHTML = pageHTML(id);
            document.getElementById('pagesContainer').appendChild(div);

            var body    = div.querySelector('.doc-page-body');
            var qSingle = makeQuill(body.querySelector('.q-single'), 'Започни да пишуваш...', false);
            div._quillSingle = qSingle;

            if (splitActive) initSplitForPage(div);

            // Load data
            var h = div.querySelector('.page-header-editor');
            var f = div.querySelector('.page-footer-editor');
            if (h && pageData.header) h.innerHTML = pageData.header;
            if (f && pageData.footer) f.innerHTML = pageData.footer;
            if (pageData.single && div._quillSingle) div._quillSingle.setContents(pageData.single, 'silent');
            if (splitActive) {
                if (pageData.left  && div._quillLeft)  div._quillLeft.setContents(pageData.left,  'silent');
                if (pageData.right && div._quillRight) div._quillRight.setContents(pageData.right, 'silent');
            }
        }

        document.getElementById('btnAddPage').addEventListener('click', createPage);

        /* ─────────────────────────────────────────────
           Delete page
        ───────────────────────────────────────────── */
        document.getElementById('pagesContainer').addEventListener('click', function (e) {
            var btn = e.target.closest('.doc-page-delete');
            if (!btn) return;
            var target = document.getElementById(btn.dataset.target);
            if (!target) return;
            target.remove();
            var remaining = document.querySelectorAll('.doc-page');
            var last = remaining[remaining.length - 1];
            if (!last) return;
            var refocus = (splitActive && last._quillLeft) ? last._quillLeft
                        : (last._quillSingle || quillMain);
            refocus.focus();
        });

        /* ─────────────────────────────────────────────
           Toolbar bridge (routes to activeQuill)
        ───────────────────────────────────────────── */
        var toolbarEl = document.getElementById('quill-toolbar');

        toolbarEl.addEventListener('mousedown', function (e) {
            if (activeQuill === quillMain) return;
            if (!activeQuill) return;
            var btn = e.target.closest('button');
            if (!btn) return;
            e.preventDefault();
            e.stopImmediatePropagation();
            var range = activeQuill.getSelection() || { index: 0, length: 0 };
            var fmt   = activeQuill.getFormat(range);
            if      (btn.classList.contains('ql-bold'))      activeQuill.format('bold',      !fmt.bold);
            else if (btn.classList.contains('ql-italic'))    activeQuill.format('italic',    !fmt.italic);
            else if (btn.classList.contains('ql-underline')) activeQuill.format('underline', !fmt.underline);
            else if (btn.classList.contains('ql-strike'))    activeQuill.format('strike',    !fmt.strike);
            else if (btn.classList.contains('ql-list')) {
                var lv = btn.getAttribute('value');
                activeQuill.format('list', fmt.list === lv ? false : lv);
            } else if (btn.classList.contains('ql-indent')) {
                activeQuill.format('indent', parseInt(btn.getAttribute('value')));
            }
            setTimeout(function () { activeQuill.focus(); }, 0);
        }, true);

        toolbarEl.addEventListener('click', function (e) {
            if (activeQuill === quillMain) return;
            if (!activeQuill) return;
            var item = e.target.closest('.ql-picker-item');
            if (!item) return;
            var picker = item.closest('.ql-picker');
            if (!picker) return;
            var raw = item.getAttribute('data-value') || '';
            if      (picker.classList.contains('ql-header'))     activeQuill.format('header',     raw ? parseInt(raw) : false);
            else if (picker.classList.contains('ql-align'))      activeQuill.format('align',      raw || false);
            else if (picker.classList.contains('ql-color'))      activeQuill.format('color',      raw || false);
            else if (picker.classList.contains('ql-background')) activeQuill.format('background', raw || false);
            setTimeout(function () { activeQuill.focus(); }, 0);
        });

        /* ─────────────────────────────────────────────
           Split toggle
        ───────────────────────────────────────────── */
        var btnSplit = document.getElementById('btnSplitToggle');
        btnSplit.addEventListener('click', function () {
            splitActive = !splitActive;
            btnSplit.classList.toggle('is-active', splitActive);
            document.querySelectorAll('.doc-page').forEach(function (pageEl) {
                pageEl.classList.toggle('split-mode', splitActive);
                if (splitActive) initSplitForPage(pageEl);
            });
            if (splitActive) {
                activeQuill = page0._quillLeft;
                setTimeout(function () { page0._quillLeft.focus(); }, 60);
            } else {
                activeQuill = quillMain;
                setTimeout(function () { quillMain.focus(); }, 60);
            }
        });

        /* ─────────────────────────────────────────────
           Load EDIT_DOC data into page 0
        ───────────────────────────────────────────── */
        if (EDIT_DOC) {
            document.getElementById('docTitleInput').value = EDIT_DOC.name || '';
            splitActive = !!parseInt(EDIT_DOC.is_split);
            if (splitActive) {
                document.getElementById('btnSplitToggle').classList.add('is-active');
                page0.classList.add('split-mode');
                initSplitForPage(page0);
            }
            if (EDIT_DOC.pages && EDIT_DOC.pages.length > 0) {
                var p0 = EDIT_DOC.pages[0];
                var h0 = page0.querySelector('.page-header-editor');
                var f0 = page0.querySelector('.page-footer-editor');
                if (h0 && p0.header) h0.innerHTML = p0.header;
                if (f0 && p0.footer) f0.innerHTML = p0.footer;
                if (p0.single && page0._quillSingle) page0._quillSingle.setContents(p0.single, 'silent');
                if (splitActive) {
                    if (p0.left  && page0._quillLeft)  page0._quillLeft.setContents(p0.left,  'silent');
                    if (p0.right && page0._quillRight) page0._quillRight.setContents(p0.right, 'silent');
                }
                for (var pi = 1; pi < EDIT_DOC.pages.length; pi++) {
                    createPageWithData(EDIT_DOC.pages[pi]);
                }
            }
        }

        /* ─────────────────────────────────────────────
           Save document
        ───────────────────────────────────────────── */
        function extractVarNamesFromPages(pages) {
            var seen = {}, result = [];
            pages.forEach(function (page) {
                ['single', 'left', 'right'].forEach(function (key) {
                    var delta = page[key];
                    if (!delta || !delta.ops) return;
                    delta.ops.forEach(function (op) {
                        if (op.insert && typeof op.insert === 'object' && op.insert.variable) {
                            var v = op.insert.variable;
                            if (!seen[v]) { seen[v] = true; result.push(v); }
                        }
                    });
                });
            });
            return result;
        }

        // Strip leading/trailing whitespace (incl. &nbsp; and <br>) from
        // contenteditable HTML so stray trailing spaces can't push content.
        function trimHtml(html) {
            if (!html) return '';
            var trimmed = html
                .replace(/^(?:&nbsp;|\s|<br\s*\/?>)+/gi, '')
                .replace(/(?:&nbsp;|\s|<br\s*\/?>)+$/gi, '');
            // Drop content that is effectively empty after trimming.
            var tmp = document.createElement('div');
            tmp.innerHTML = trimmed;
            if (!tmp.textContent.replace(/ /g, '').trim()) return '';
            return trimmed;
        }

        // Trim trailing whitespace / blank lines from a Quill delta, keeping
        // the required single trailing newline.
        function trimDelta(delta) {
            if (!delta || !delta.ops || !delta.ops.length) return delta;
            var ops = delta.ops.map(function (op) {
                var clone = {};
                for (var k in op) clone[k] = op[k];
                return clone;
            });
            while (ops.length) {
                var last = ops[ops.length - 1];
                if (typeof last.insert !== 'string') break;
                var stripped = last.insert.replace(/[ \t\r\n]+$/, '');
                if (stripped === '') { ops.pop(); }
                else { last.insert = stripped; break; }
            }
            var tail = ops[ops.length - 1];
            if (!tail || typeof tail.insert !== 'string') ops.push({ insert: '\n' });
            else if (tail.insert.slice(-1) !== '\n')      tail.insert += '\n';
            return { ops: ops };
        }

        function getTrimmedContents(quill) {
            return quill ? trimDelta(quill.getContents()) : null;
        }

        document.getElementById('btnSave').addEventListener('click', function () {
            var name = document.getElementById('docTitleInput').value.trim();
            if (!name) { document.getElementById('docTitleInput').focus(); return; }

            var pages = [];
            document.querySelectorAll('.doc-page').forEach(function (pageEl) {
                // Persist only the content for the active layout. Dropping the
                // inactive side prevents stale content (and its variables) from
                // the previous split state lingering after a toggle + save.
                pages.push({
                    header: trimHtml((pageEl.querySelector('.page-header-editor') || {}).innerHTML || ''),
                    footer: trimHtml((pageEl.querySelector('.page-footer-editor') || {}).innerHTML || ''),
                    single: (!splitActive && pageEl._quillSingle) ? getTrimmedContents(pageEl._quillSingle) : null,
                    left:   (splitActive  && pageEl._quillLeft)   ? getTrimmedContents(pageEl._quillLeft)   : null,
                    right:  (splitActive  && pageEl._quillRight)  ? getTrimmedContents(pageEl._quillRight)  : null,
                });
            });

            var varNames = extractVarNamesFromPages(pages);

            var params = new URLSearchParams({
                action:    DOC_ID ? 'update' : 'create',
                name:      name,
                is_split:  splitActive ? 1 : 0,
                pages:     JSON.stringify(pages),
                variables: JSON.stringify(varNames),
            });
            if (DOC_ID) params.set('id', DOC_ID);
            else        params.set('template_id', TEMPLATE_ID);

            var btn = document.getElementById('btnSave');
            btn.disabled = true;
            btn.textContent = 'Зачувува...';

            fetch('api/document_api.php', { method: 'POST', body: params })
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    if (res.success) {
                        window.location.href = 'pregled-shablon.php?id=' + TEMPLATE_ID;
                    } else {
                        alert(res.message || 'Грешка при зачувување.');
                        btn.disabled = false;
                        btn.textContent = 'Зачувај документ';
                    }
                })
                .catch(function () {
                    alert('Грешка при поврзување.');
                    btn.disabled = false;
                    btn.textContent = 'Зачувај документ';
                });
        });

    }());
    </script>
</body>
</html>

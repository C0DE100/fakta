<?php
require_once __DIR__ . '/includes/auth.php';
require_login();
$currentPage = 'kreraj-dokument';

$companyId  = current_company_id();
$pdo        = $GLOBALS['fakta_db']->getConnection();

$docId      = isset($_GET['doc_id'])      ? (int)$_GET['doc_id']      : 0;
$templateId = isset($_GET['template_id']) ? (int)$_GET['template_id'] : 0;
$editDoc    = null;

if ($docId) {
    $stmt = $pdo->prepare('SELECT * FROM documents WHERE id = ? AND company_id = ?');
    $stmt->execute([$docId, $companyId]);
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

// The template must belong to the current company before we render its editor.
$ownStmt = $pdo->prepare('SELECT 1 FROM templates WHERE id = ? AND company_id = ?');
$ownStmt->execute([$templateId, $companyId]);
if (!$ownStmt->fetchColumn()) {
    header('Location: tipski-dokumenti.php');
    exit;
}

// Variables already used by the other documents in this template. The editor
// offers these as one-click suggestions so variable names stay consistent
// across every document in the template.
$templateVarMap = []; // name => [docName, ...]
$prefillHeader  = '';  // header/footer of the latest document in the template,
$prefillFooter  = '';  // used to seed a brand-new document (not when editing).
$stmt = $pdo->prepare('SELECT id, name, variables, pages FROM documents WHERE template_id = ? AND company_id = ? ORDER BY sort_order ASC, id ASC');
$stmt->execute([$templateId, $companyId]);
foreach ($stmt->fetchAll() as $row) {
    if ($docId && (int)$row['id'] === $docId) continue; // skip the doc being edited
    $vars = json_decode($row['variables'], true) ?: [];
    foreach ($vars as $v) {
        if (!isset($templateVarMap[$v]))                      $templateVarMap[$v] = [];
        if (!in_array($row['name'], $templateVarMap[$v], true)) $templateVarMap[$v][] = $row['name'];
    }

    // Seed a new document with the most recent header/footer found in the
    // template (iteration is ascending, so the last non-empty value wins).
    if (!$docId) {
        $pages = json_decode($row['pages'], true) ?: [];
        $first = $pages[0] ?? null;
        if ($first) {
            if (!empty($first['header'])) $prefillHeader = $first['header'];
            if (!empty($first['footer'])) $prefillFooter = $first['footer'];
        }
    }
}
$templateVars = [];
foreach ($templateVarMap as $name => $docNames) {
    $templateVars[] = ['name' => $name, 'docs' => $docNames];
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

            <!-- Document actions — sit at the right end of the toolbar row -->
            <div class="doc-toolbar-actions">
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
                <button id="btnSave" class="btn-new-client" title="Зачувај документ">
                    <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/>
                        <polyline points="17 21 17 13 7 13 7 21"/>
                        <polyline points="7 3 7 8 15 8"/>
                    </svg>
                    Зачувај документ
                </button>
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
            <p id="varReplaceNote" class="var-replace-note" style="display:none"></p>
            <input type="text" id="varNameInput" class="field" placeholder="пр. ime, datum, firma..." style="width:100%;margin-bottom:0.75rem;" autocomplete="off" spellcheck="false">
            <div id="varSuggestWrap" class="var-suggest-wrap" style="display:none">
                <div class="var-suggest-label">Променливи во шаблонот <span class="var-suggest-note">— кликни за да вметнеш</span></div>
                <div id="varSuggestList" class="var-suggest-list"></div>
            </div>
            <div style="display:flex;gap:0.5rem;justify-content:flex-end;margin-top:0.25rem">
                <button id="varNameCancel" class="btn-secondary">Откажи</button>
                <button id="varNameConfirm" class="btn-new-client">Внеси</button>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.quilljs.com/1.3.7/quill.js"></script>
    <script src="js/app.js"></script>
    <script>
    var EDIT_DOC       = <?= $editDoc ? json_encode($editDoc) : 'null' ?>;
    var TEMPLATE_ID    = <?= $templateId ?>;
    var DOC_ID         = <?= $docId ?>;
    var TEMPLATE_VARS  = <?= json_encode($templateVars, JSON_UNESCAPED_UNICODE) ?>;
    var PREFILL_HEADER = <?= json_encode($prefillHeader, JSON_UNESCAPED_UNICODE) ?>;
    var PREFILL_FOOTER = <?= json_encode($prefillFooter, JSON_UNESCAPED_UNICODE) ?>;
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
        var Delta       = Quill.import('delta');

        // Local auto-saved draft of this document (resumable via the bottom-right
        // "creating document" pill). Keyed by editing context so the new-doc draft
        // and each edited-doc draft stay separate.
        // Per-company namespace (window.FAKTA_CO set in nav.php) keeps tenants' drafts apart;
        // DOC_DRAFT_ACTIVE must stay in sync with js/draft-document.js.
        var DOC_CO           = '_co' + (window.FAKTA_CO || '0');
        var DOC_DRAFT_KEY    = 'fakta_doc_draft_' + (DOC_ID ? 'd' + DOC_ID : 't' + TEMPLATE_ID + '_new') + DOC_CO;
        var DOC_DRAFT_ACTIVE = 'fakta_active_doc_draft' + DOC_CO;
        var _saving          = false; // set on a real save so we don't re-draft on redirect

        /* ─────────────────────────────────────────────
           Variable name modal
        ───────────────────────────────────────────── */
        var varNameCb = null;
        var _varSuggestions = [];

        function escAttr(s) {
            return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;')
                .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        }

        // Names of variables currently embedded anywhere in this document.
        function collectLiveVarNames() {
            var names = {};
            document.querySelectorAll('.doc-page').forEach(function (pageEl) {
                ['_quillSingle', '_quillLeft', '_quillRight'].forEach(function (key) {
                    var q = pageEl[key];
                    if (!q) return;
                    (q.getContents().ops || []).forEach(function (op) {
                        if (op.insert && typeof op.insert === 'object' && op.insert.variable) {
                            names[op.insert.variable] = true;
                        }
                    });
                });
            });
            return names;
        }

        // Merge template-wide variables (used by sibling documents) with the
        // ones already placed in this document, into one sorted suggestion list.
        function buildSuggestions() {
            var map = {}; // name -> hint
            (TEMPLATE_VARS || []).forEach(function (item) {
                map[item.name] = (item.docs && item.docs.length)
                    ? 'Употребено во: ' + item.docs.join(', ')
                    : '';
            });
            var live = collectLiveVarNames();
            Object.keys(live).forEach(function (name) {
                if (!(name in map)) map[name] = 'Во овој документ';
            });
            return Object.keys(map).map(function (name) {
                return { name: name, hint: map[name] };
            }).sort(function (a, b) { return a.name.localeCompare(b.name, 'mk'); });
        }

        function renderVarSuggestions() {
            var wrap = document.getElementById('varSuggestWrap');
            var list = document.getElementById('varSuggestList');
            var filter = (document.getElementById('varNameInput').value || '')
                .replace(/\$/g, '').trim().toLowerCase();
            var matches = filter
                ? _varSuggestions.filter(function (s) { return s.name.toLowerCase().indexOf(filter) !== -1; })
                : _varSuggestions;
            if (!matches.length) { wrap.style.display = 'none'; list.innerHTML = ''; return; }
            wrap.style.display = '';
            list.innerHTML = matches.map(function (s) {
                return '<button type="button" class="var-suggest-chip" data-var="' + escAttr(s.name) + '"' +
                       (s.hint ? ' title="' + escAttr(s.hint) + '"' : '') +
                       '>$' + escAttr(s.name) + '$</button>';
            }).join('');
        }

        function openVarNameModal(cb, selectedText) {
            varNameCb = cb;
            document.getElementById('varNameInput').value = '';

            // Show what the variable will replace, if text was selected.
            var note = document.getElementById('varReplaceNote');
            var sel  = (selectedText || '').replace(/\s+/g, ' ').trim();
            if (sel) {
                if (sel.length > 80) sel = sel.slice(0, 80) + '…';
                note.textContent = 'Избраниот текст ќе биде заменет: „' + sel + '“';
                note.style.display = '';
            } else {
                note.style.display = 'none';
            }

            _varSuggestions = buildSuggestions();
            renderVarSuggestions();
            document.getElementById('varNameModal').classList.add('open');
            document.body.classList.add('modal-open');
            setTimeout(function () { document.getElementById('varNameInput').focus(); }, 50);
        }

        function closeVarNameModal() {
            document.getElementById('varNameModal').classList.remove('open');
            document.body.classList.remove('modal-open');
            varNameCb = null;
        }

        // Sanitise a chosen name and hand it to the active callback. Returns
        // false if the name was empty after cleanup. Spaces are allowed (e.g.
        // "Ime na klient"); only the "$" delimiter is stripped.
        function chooseVariable(name) {
            name = (name || '').replace(/\$/g, '').replace(/\s+/g, ' ').trim();
            if (!name) return false;
            var cb = varNameCb;
            closeVarNameModal();
            if (cb) cb(name);
            return true;
        }

        document.getElementById('varNameConfirm').addEventListener('click', function () {
            if (!chooseVariable(document.getElementById('varNameInput').value)) {
                document.getElementById('varNameInput').focus();
            }
        });

        // Click a suggestion → insert it immediately (one-click reuse).
        document.getElementById('varSuggestList').addEventListener('click', function (e) {
            var chip = e.target.closest('.var-suggest-chip');
            if (chip) chooseVariable(chip.getAttribute('data-var'));
        });

        // Live-filter the suggestions as the user types.
        document.getElementById('varNameInput').addEventListener('input', renderVarSuggestions);

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
            // Text currently selected — it will be replaced by the variable.
            var selectedText = range.length ? q.getText(range.index, range.length) : '';
            openVarNameModal(function (name) {
                // Delete the selection (if any) and drop the variable in its
                // place as one atomic change (single undo step).
                var change = new Delta().retain(range.index);
                if (range.length) change.delete(range.length);
                change.insert({ variable: name });
                q.updateContents(change, 'user');
                q.setSelection(range.index + 1, 0, 'silent');
                q.focus();
            }, selectedText);
        }

        // Preserve the editor selection when the button is pressed — without
        // this, clicking it blurs the editor and the highlight collapses, so
        // there'd be no selection left to replace.
        document.getElementById('btnInsertVar').addEventListener('mousedown', function (e) {
            e.preventDefault();
        });

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

        function makeQuill(el, placeholder, ownsToolbar, pageEl, col) {
            var q = new Quill(el, {
                theme: 'snow',
                modules: { toolbar: ownsToolbar ? '#quill-toolbar' : false },
                placeholder: placeholder || 'Започни да пишуваш...',
            });
            q._pageEl = pageEl || null;
            q._col    = col || 'single';
            trackFocus(q);
            q.on('text-change', function (delta, oldDelta, source) {
                // Our own reflow edits use the 'silent' source — ignore them
                // so we never recurse into pagination.
                if (source === 'silent') return;

                if (source === 'user') {
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
                            return; // variable insert will retrigger reflow
                        }
                    }
                }

                // Auto-paginate: spill overflow onto the next page, and pull
                // content back up when an edit could have freed space (a delete
                // or a formatting change). Pure inserts only ever push down.
                var mightShrink = (delta.ops || []).some(function (op) {
                    return op.delete != null || op.attributes != null;
                });
                var sel   = q.getSelection();
                var atEnd = !sel || sel.index >= q.getLength() - 1;
                var moved = reflowStream(q._col, q._pageEl, mightShrink);
                if (moved) restoreCaret(q, sel, atEnd);
                scheduleSnapshot();
            });
            return q;
        }

        function initSplitForPage(pageEl) {
            if (pageEl._splitReady) return;
            pageEl._splitReady = true;
            var body = pageEl.querySelector('.doc-page-body');
            pageEl._quillLeft  = makeQuill(body.querySelector('.q-left'),  'Лев текст...',   false, pageEl, 'left');
            pageEl._quillRight = makeQuill(body.querySelector('.q-right'), 'Десен текст...', false, pageEl, 'right');
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
        quillMain      = makeQuill(page0body.querySelector('.q-single'), 'Започни да пишуваш...', true, page0, 'single');
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
            var qSingle = makeQuill(body.querySelector('.q-single'), 'Започни да пишуваш...', false, div, 'single');
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
            var qSingle = makeQuill(body.querySelector('.q-single'), 'Започни да пишуваш...', false, div, 'single');
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

        /* ─────────────────────────────────────────────
           Auto-pagination (reflow) engine
           ─────────────────────────────────────────────
           Content that no longer fits on a page spills onto the next page
           in the same stream ('single' | 'left' | 'right'); auto-created
           pages are reclaimed when text shrinks. Pages the user added by
           hand (the "Додај страница" button) are never collapsed.
        ───────────────────────────────────────────── */
        var REFLOW_TOL = 1; // px tolerance for sub-pixel rounding
        var _reflowing = false;

        function isOverflowing(q) {
            return !!q && (q.root.scrollHeight - q.root.clientHeight) > REFLOW_TOL;
        }
        function isEmptyStream(q) {
            return !q || q.getLength() <= 1; // just the mandatory trailing newline
        }
        function getPagesArray() {
            return Array.prototype.slice.call(
                document.querySelectorAll('#pagesContainer .doc-page'));
        }
        function getColQuill(pageEl, col) {
            if (col === 'single') return pageEl._quillSingle;
            initSplitForPage(pageEl);
            return col === 'left' ? pageEl._quillLeft : pageEl._quillRight;
        }
        // Quill documents must end with a newline; ensure that before setting.
        function safeSet(q, delta) {
            var ops  = delta.ops || [];
            var last = ops[ops.length - 1];
            if (!last || typeof last.insert !== 'string' || last.insert.slice(-1) !== '\n') {
                delta = delta.concat(new Delta().insert('\n'));
            }
            q.setContents(delta, 'silent');
        }
        // Plain text of a delta, with embeds counted as one (non-newline) char
        // so string offsets line up with Quill's index units.
        function deltaToText(delta) {
            var s = '';
            (delta.ops || []).forEach(function (op) {
                if (typeof op.insert === 'string') s += op.insert;
                else if (op.insert != null) s += '￼';
            });
            return s;
        }

        // A blank page spun up purely to hold overflow (not user-added).
        function createAutoPage() {
            pageSeq++;
            var id  = 'page-' + pageSeq;
            var div = document.createElement('div');
            div.className = 'doc-page';
            div.id = id;
            div._autoCreated = true;
            if (splitActive) div.classList.add('split-mode');
            div.innerHTML = pageHTML(id);
            document.getElementById('pagesContainer').appendChild(div);

            var body = div.querySelector('.doc-page-body');
            div._quillSingle = makeQuill(body.querySelector('.q-single'), 'Започни да пишуваш...', false, div, 'single');
            if (splitActive) initSplitForPage(div);
            return div;
        }

        function getOrCreateNextPage(pageEl, col) {
            var nx = pageEl.nextElementSibling;
            while (nx && !nx.classList.contains('doc-page')) nx = nx.nextElementSibling;
            if (!nx) nx = createAutoPage();
            getColQuill(nx, col); // ensure the stream's editor exists
            return nx;
        }

        // Remove the tail of `q` that no longer fits and return it as a delta.
        // The cut is snapped to a paragraph boundary so whole paragraphs move
        // together; a single paragraph taller than a page falls back to a word
        // (then character) boundary.
        function takeOverflow(q) {
            if (!isOverflowing(q)) return null;
            var full  = q.getContents();
            var total = q.getLength();      // includes the final newline
            var text  = deltaToText(full);

            // Largest prefix length that still fits, via binary search.
            var lo = 1, hi = total - 1, bestFit = 1;
            while (lo <= hi) {
                var mid = (lo + hi) >> 1;
                safeSet(q, full.slice(0, mid));
                if (!isOverflowing(q)) { bestFit = mid; lo = mid + 1; }
                else hi = mid - 1;
            }

            var cut = -1, needNl = false;
            for (var i = bestFit; i >= 1; i--) {       // snap to paragraph break
                if (text.charAt(i - 1) === '\n') { cut = i; break; }
            }
            if (cut <= 0) {                            // one giant paragraph
                for (var j = bestFit; j >= 1; j--) {   // snap to a word break
                    if (text.charAt(j - 1) === ' ') { cut = j; break; }
                }
                if (cut <= 0) cut = bestFit;           // last resort: hard cut
                needNl = true;
            }

            var keep = full.slice(0, cut);
            if (needNl) keep = keep.concat(new Delta().insert('\n'));
            var overflow = full.slice(cut, total);
            safeSet(q, keep);
            return overflow;
        }

        function prependStream(q, overflow) {
            safeSet(q, overflow.concat(q.getContents()));
        }

        // Move the first paragraph of `nq` to the end of `cq`, but only if `cq`
        // still fits afterwards. Returns true when a paragraph was moved.
        function pullOneParagraph(cq, nq) {
            if (isEmptyStream(nq)) return false;
            var nc       = nq.getContents();
            var ntext    = deltaToText(nc);
            var nl       = ntext.indexOf('\n');
            var firstLen = nl < 0 ? nc.length() : nl + 1;
            var move     = nc.slice(0, firstLen);
            var rest     = nc.slice(firstLen);

            var cqOld    = cq.getContents();
            safeSet(cq, isEmptyStream(cq) ? move : cqOld.concat(move));
            if (isOverflowing(cq)) { safeSet(cq, cqOld); return false; }

            safeSet(nq, rest);
            return true;
        }

        // Drop auto-created pages that ended up empty across every stream.
        function cleanupEmptyAutoPages() {
            var removed = false;
            getPagesArray().forEach(function (p) {
                if (!p._autoCreated) return;
                var empty = isEmptyStream(p._quillSingle)
                    && (!p._quillLeft  || isEmptyStream(p._quillLeft))
                    && (!p._quillRight || isEmptyStream(p._quillRight));
                if (empty && document.querySelectorAll('#pagesContainer .doc-page').length > 1) {
                    p.remove();
                    removed = true;
                }
            });
            return removed;
        }

        // Normalise one stream from `fromPageEl` to the end of the document.
        // Returns true if any content moved (so the caller can fix the caret).
        function reflowStream(col, fromPageEl, compact) {
            if (_reflowing) return false;
            if (compact === undefined) compact = true;
            _reflowing = true;
            var changed = false;
            try {
                var pages = getPagesArray();
                var start = fromPageEl ? pages.indexOf(fromPageEl) : 0;
                if (start < 0) start = 0;

                // Push overflow downwards, creating pages as needed.
                for (var i = start; ; i++) {
                    pages = getPagesArray();
                    if (i >= pages.length) break;
                    var q = getColQuill(pages[i], col);
                    var guard = 0;
                    while (q && isOverflowing(q) && guard++ < 300) {
                        var overflow = takeOverflow(q);
                        if (!overflow) break;
                        var next = getOrCreateNextPage(pages[i], col);
                        prependStream(getColQuill(next, col), overflow);
                        changed = true;
                    }
                }

                // Pull content back up to fill freed space (only when an edit
                // could have shrunk content). Only auto-created pages are
                // compacted — manual page breaks are preserved.
                pages = getPagesArray();
                for (var a = start; compact && a < pages.length - 1; a++) {
                    var cq = getColQuill(pages[a], col);
                    var guard2 = 0;
                    while (guard2++ < 500) {
                        if (isOverflowing(cq)) break;
                        var nq = null, blocked = false;
                        for (var b = a + 1; b < pages.length; b++) {
                            var t = getColQuill(pages[b], col);
                            if (isEmptyStream(t)) continue;
                            if (!pages[b]._autoCreated) blocked = true;
                            nq = t; break;
                        }
                        if (!nq || blocked) break;
                        if (!pullOneParagraph(cq, nq)) break;
                        changed = true;
                    }
                }

                if (cleanupEmptyAutoPages()) changed = true;
            } finally {
                _reflowing = false;
            }
            return changed;
        }

        // After content shifts between pages, keep the caret where the user is
        // working: at the very end of the stream when appending, otherwise as
        // close as possible to the original position.
        function restoreCaret(q, sel, atEnd) {
            if (!sel) return;
            if (atEnd) {
                var pages = getPagesArray();
                var lq = getColQuill(pages[pages.length - 1], q._col);
                if (lq) { lq.focus(); lq.setSelection(Math.max(lq.getLength() - 1, 0), 0); }
            } else {
                var len = q.getLength();
                q.setSelection(Math.min(sel.index, Math.max(len - 1, 0)), 0);
            }
        }

        document.getElementById('btnAddPage').addEventListener('click', createPage);
        document.getElementById('btnAddPage').addEventListener('click', scheduleSnapshot);

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
            scheduleSnapshot();
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
            scheduleSnapshot();
        });

        /* ─────────────────────────────────────────────
           Load a state object (EDIT_DOC or a local draft) into the editor
        ───────────────────────────────────────────── */
        function loadState(state) {
            document.getElementById('docTitleInput').value = state.name || '';
            splitActive = !!parseInt(state.is_split);
            if (splitActive) {
                document.getElementById('btnSplitToggle').classList.add('is-active');
                page0.classList.add('split-mode');
                initSplitForPage(page0);
            }
            var pages = state.pages || [];
            if (pages.length > 0) {
                var p0 = pages[0];
                var h0 = page0.querySelector('.page-header-editor');
                var f0 = page0.querySelector('.page-footer-editor');
                if (h0 && p0.header) h0.innerHTML = p0.header;
                if (f0 && p0.footer) f0.innerHTML = p0.footer;
                if (p0.single && page0._quillSingle) page0._quillSingle.setContents(p0.single, 'silent');
                if (splitActive) {
                    if (p0.left  && page0._quillLeft)  page0._quillLeft.setContents(p0.left,  'silent');
                    if (p0.right && page0._quillRight) page0._quillRight.setContents(p0.right, 'silent');
                }
                for (var pi = 1; pi < pages.length; pi++) createPageWithData(pages[pi]);
            }
            // Re-paginate restored content in case anything no longer fits.
            setTimeout(function () {
                if (splitActive) { reflowStream('left', page0); reflowStream('right', page0); }
                else            { reflowStream('single', page0); }
            }, 0);
        }

        /* ─────────────────────────────────────────────
           Local draft — auto-saved snapshot, resumable from the pill
        ───────────────────────────────────────────── */
        function collectPages() {
            var pages = [];
            document.querySelectorAll('.doc-page').forEach(function (pageEl) {
                pages.push({
                    header: trimHtml((pageEl.querySelector('.page-header-editor') || {}).innerHTML || ''),
                    footer: trimHtml((pageEl.querySelector('.page-footer-editor') || {}).innerHTML || ''),
                    single: (!splitActive && pageEl._quillSingle) ? getTrimmedContents(pageEl._quillSingle) : null,
                    left:   (splitActive  && pageEl._quillLeft)   ? getTrimmedContents(pageEl._quillLeft)   : null,
                    right:  (splitActive  && pageEl._quillRight)  ? getTrimmedContents(pageEl._quillRight)  : null,
                });
            });
            return pages;
        }

        function editorIsEmpty() {
            if (document.getElementById('docTitleInput').value.trim()) return false;
            var has = false;
            document.querySelectorAll('.doc-page').forEach(function (pageEl) {
                ['_quillSingle', '_quillLeft', '_quillRight'].forEach(function (k) {
                    if (pageEl[k] && pageEl[k].getText().trim()) has = true;
                });
                var h = pageEl.querySelector('.page-header-editor');
                var f = pageEl.querySelector('.page-footer-editor');
                if (h && h.textContent.trim()) has = true;
                if (f && f.textContent.trim()) has = true;
            });
            return !has;
        }

        function clearDraft() {
            try { sessionStorage.removeItem(DOC_DRAFT_KEY); } catch (e) {}
            try {
                var a = JSON.parse(sessionStorage.getItem(DOC_DRAFT_ACTIVE));
                if (a && a.key === DOC_DRAFT_KEY) sessionStorage.removeItem(DOC_DRAFT_ACTIVE);
            } catch (e) {}
        }

        function snapshotDraft() {
            if (_saving) return;
            if (editorIsEmpty()) { clearDraft(); return; }
            var state = {
                name:     document.getElementById('docTitleInput').value,
                is_split: splitActive ? 1 : 0,
                pages:    collectPages(),
                ts:       Date.now()
            };
            try { sessionStorage.setItem(DOC_DRAFT_KEY, JSON.stringify(state)); } catch (e) {}
            var url = 'kreraj-dokument.php?' + (DOC_ID
                ? 'doc_id=' + DOC_ID + '&template_id=' + TEMPLATE_ID
                : 'template_id=' + TEMPLATE_ID);
            try {
                sessionStorage.setItem(DOC_DRAFT_ACTIVE, JSON.stringify({
                    key:   DOC_DRAFT_KEY,
                    title: (state.name || '').trim() || 'Без наслов',
                    url:   url,
                    kind:  DOC_ID ? 'edit' : 'create',
                    ts:    state.ts
                }));
            } catch (e) {}
        }

        var _draftTimer = null;
        function scheduleSnapshot() {
            clearTimeout(_draftTimer);
            _draftTimer = setTimeout(snapshotDraft, 600);
        }

        /* ─────────────────────────────────────────────
           Initial content: local draft → EDIT_DOC → blank (+ header/footer prefill)
        ───────────────────────────────────────────── */
        var _docDraft = null;
        try { _docDraft = JSON.parse(sessionStorage.getItem(DOC_DRAFT_KEY)); } catch (e) {}

        if (_docDraft && _docDraft.pages && _docDraft.pages.length) {
            loadState(_docDraft);
        } else if (EDIT_DOC) {
            loadState(EDIT_DOC);
        } else if (PREFILL_HEADER || PREFILL_FOOTER) {
            // New document: inherit the header/footer from the template's most
            // recent document so the user doesn't retype them every time.
            var ph = page0.querySelector('.page-header-editor');
            var pf = page0.querySelector('.page-footer-editor');
            if (ph && PREFILL_HEADER) ph.innerHTML = PREFILL_HEADER;
            if (pf && PREFILL_FOOTER) pf.innerHTML = PREFILL_FOOTER;
        }

        // Auto-snapshot on any edit (typing, title, header/footer changes), and
        // flush the latest state when leaving so nothing typed is lost.
        document.addEventListener('input', scheduleSnapshot);
        window.addEventListener('beforeunload', snapshotDraft);

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

        // Clear the "title required" error as soon as the user starts typing.
        document.getElementById('docTitleInput').addEventListener('input', function () {
            this.classList.remove('input-error');
        });

        document.getElementById('btnSave').addEventListener('click', function () {
            var titleInput = document.getElementById('docTitleInput');
            var name = titleInput.value.trim();
            if (!name) {
                titleInput.classList.add('input-error');
                titleInput.focus();
                return;
            }
            titleInput.classList.remove('input-error');

            // Persist only the content for the active layout (collectPages drops
            // the inactive split side so stale content can't linger after a toggle).
            var pages = collectPages();

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
                        _saving = true;
                        clearDraft(); // saved to DB → drop the local draft + pill
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

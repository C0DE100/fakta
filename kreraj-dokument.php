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
$ownStmt = $pdo->prepare('SELECT name FROM templates WHERE id = ? AND company_id = ?');
$ownStmt->execute([$templateId, $companyId]);
$templateRow = $ownStmt->fetch();
if (!$templateRow) {
    header('Location: tipski-dokumenti.php');
    exit;
}
$templateName = $templateRow['name'];

// Praktikant may edit only documents they created (they may still create new
// ones). Block opening the editor on someone else's document up front.
if ($editDoc && current_role() === 'praktikant'
    && (int) ($editDoc['created_by'] ?? 0) !== (int) (current_user()['id'] ?? -1)) {
    header('Location: ' . fakta_url('pregled-shablon.php?id=' . $templateId));
    exit;
}

// Variables already used by the other documents in this template. The editor
// offers these as one-click suggestions so variable names stay consistent
// across every document in the template.
$templateVarMap = []; // name => [docName, ...]
$stmt = $pdo->prepare('SELECT id, name, variables FROM documents WHERE template_id = ? AND company_id = ? ORDER BY sort_order ASC, id ASC');
$stmt->execute([$templateId, $companyId]);
foreach ($stmt->fetchAll() as $row) {
    if ($docId && (int)$row['id'] === $docId) continue; // skip the doc being edited
    $vars = json_decode($row['variables'], true) ?: [];
    foreach ($vars as $v) {
        if (!isset($templateVarMap[$v]))                      $templateVarMap[$v] = [];
        if (!in_array($row['name'], $templateVarMap[$v], true)) $templateVarMap[$v][] = $row['name'];
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
            <input type="text" id="docTitleInput" class="doc-title-input" placeholder="Назив на документот...">
        </div>

        <!-- Quill toolbar (sticky) -->
        <div class="doc-toolbar-sticky">
            <a href="pregled-shablon.php?id=<?= $templateId ?>" class="doc-toolbar-back" title="Назад кон шаблонот">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M19 12H5"/><path d="m12 19-7-7 7-7"/>
                </svg>
                <span>Назад</span>
            </a>
            <div id="quill-toolbar">
                <span class="ql-formats" data-group="Историја">
                    <button id="btnUndo" type="button" title="Врати (Ctrl+Z)">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 14 4 9l5-5"/><path d="M4 9h10.5a5.5 5.5 0 0 1 5.5 5.5v0a5.5 5.5 0 0 1-5.5 5.5H11"/></svg>
                    </button>
                    <button id="btnRedo" type="button" title="Повтори (Ctrl+Y)">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 14 5-5-5-5"/><path d="M20 9H9.5A5.5 5.5 0 0 0 4 14.5v0A5.5 5.5 0 0 0 9.5 20H13"/></svg>
                    </button>
                </span>
                <span class="ql-formats" data-group="Стил">
                    <select class="ql-header" title="Стил на текст и наслови">
                        <option value="1">Наслов 1</option>
                        <option value="2">Наслов 2</option>
                        <option value="3">Наслов 3</option>
                        <option selected="">Нормален текст</option>
                    </select>
                </span>
                <span class="ql-formats" data-group="Букви">
                    <button class="ql-bold" title="Здебелено (Ctrl+B)"></button>
                    <button class="ql-italic" title="Закосено (Ctrl+I)"></button>
                    <button class="ql-underline" title="Подвлечено (Ctrl+U)"></button>
                    <button class="ql-strike" title="Прецртано"></button>
                </span>
                <span class="ql-formats" data-group="Боја">
                    <select class="ql-color" title="Боја на текст"></select>
                    <select class="ql-background" title="Боја на маркер (позадина)"></select>
                </span>
                <span class="ql-formats" data-group="Листи">
                    <button class="ql-list" value="ordered" title="Нумерирана листа"></button>
                    <button class="ql-list" value="bullet" title="Точкаста листа"></button>
                    <button class="ql-indent" value="-1" title="Намали вовлекување"></button>
                    <button class="ql-indent" value="+1" title="Зголеми вовлекување"></button>
                </span>
                <span class="ql-formats" data-group="Порамнување">
                    <select class="ql-align" title="Порамнување на текст"></select>
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
                <button id="btnInsertVar" class="btn-secondary" title="Внеси променлива (или напиши / во текстот)">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M8 9h8M8 12h5M8 15h3"/><rect width="18" height="18" x="3" y="3" rx="2"/>
                    </svg>
                    Внеси Променлива
                </button>
                <?php if ($editDoc): ?>
                <button id="btnDownloadDoc" class="btn-secondary" title="Преземи го овој документ како PDF">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                        <polyline points="7 10 12 15 17 10"/>
                        <line x1="12" x2="12" y1="15" y2="3"/>
                    </svg>
                    Преземи
                </button>
                <button id="btnDeleteDoc" class="btn-secondary btn-tool-danger" title="Избриши документ">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/>
                    </svg>
                </button>
                <?php endif; ?>
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

        <!-- Pages area — one continuous A4 sheet; the browser paginates at print -->
        <div class="doc-page-wrap">
            <div id="pagesContainer">

                <div class="doc-sheet" id="docSheet">
                    <div class="doc-page-header">
                        <div class="page-header-editor" id="docHeaderEditor"
                             contenteditable="true"
                             data-placeholder="Заглавие (се повторува на секоја страница)..."
                             spellcheck="false"></div>
                    </div>
                    <div class="doc-sheet-body" id="docSheetBody">
                        <div class="doc-editor-single">
                            <div class="q-single"></div>
                        </div>
                        <div class="doc-editor-split">
                            <div class="doc-col"><div class="q-left"></div></div>
                            <div class="doc-col-divider"></div>
                            <div class="doc-col"><div class="q-right"></div></div>
                        </div>
                        <!-- Page-break guides: a non-interactive overlay drawn at
                             each A4 boundary. Purely visual — never touches the
                             editor content, caret or scroll. -->
                        <div class="doc-page-guides" id="docPageGuides" aria-hidden="true"></div>
                        <!-- Persistent selection highlight: drawn while the
                             "insert variable" panel is open, since the native
                             highlight is lost once the panel takes focus. -->
                        <div class="doc-sel-highlight" id="docSelHighlight" aria-hidden="true"></div>
                    </div>
                    <div class="doc-page-footer">
                        <div class="page-footer-editor" id="docFooterEditor"
                             contenteditable="true"
                             data-placeholder="Подножје (се повторува на секоја страница)..."
                             spellcheck="false"></div>
                    </div>
                </div>

            </div>

            <div id="docPageCounter" class="doc-page-counter" aria-hidden="true"></div>
        </div>

    </div>
    </div>

    <!-- Modal: enter variable name (non-blocking floating panel) -->
    <div id="varNameModal" class="modal-overlay var-modal-floating">
        <div class="modal-box" style="max-width:22rem">
            <div class="modal-header">
                <span class="modal-title">Внеси Променлива</span>
                <button class="modal-close" id="varNameClose">&times;</button>
            </div>
            <p style="font-size:0.8125rem;color:#78716c;margin-bottom:1rem;">Именувај ја променливата. При печатење ќе бидеш прашан за нејзината вредност. Прозорецот останува отворен — кликни каде било во документот за да внесеш уште.</p>
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

    <?php if ($editDoc): ?>
    <!-- Modal: confirm delete of this document -->
    <div id="docDeleteModal" class="modal-overlay" aria-hidden="true">
        <div class="modal-box" style="max-width:22rem">
            <div class="modal-header">
                <span class="modal-title">Избриши документ</span>
                <button class="modal-close" id="docDeleteClose">&times;</button>
            </div>
            <p style="font-size:0.875rem;color:#57534e;margin-bottom:1.25rem;">Дали сте сигурни дека сакате да го избришете овој документ? Ова не може да се врати.</p>
            <div style="display:flex;gap:0.5rem;justify-content:flex-end">
                <button id="docDeleteCancel" class="btn-secondary">Откажи</button>
                <button id="docDeleteConfirm" class="btn-new-client" style="background:#dc2626">Избриши</button>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Modal: unsaved-changes warning when leaving the editor -->
    <div id="leaveModal" class="modal-overlay" aria-hidden="true">
        <div class="modal-box" style="max-width:23rem">
            <div class="modal-header">
                <span class="modal-title">Незачувани промени</span>
                <button class="modal-close" id="leaveClose">&times;</button>
            </div>
            <p style="font-size:0.875rem;color:#57534e;margin-bottom:1.25rem;">Имаш промени што не се зачувани. Ако излезеш сега, тие ќе бидат изгубени.</p>
            <div style="display:flex;gap:0.5rem;justify-content:flex-end">
                <button id="leaveStay" class="btn-secondary">Остани</button>
                <button id="leaveDiscard" class="btn-new-client" style="background:#dc2626">Излези без зачувување</button>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.quilljs.com/1.3.7/quill.js"></script>
    <script src="js/app.js"></script>
    <script>
    var EDIT_DOC       = <?= $editDoc ? json_encode($editDoc) : 'null' ?>;
    var TEMPLATE_ID    = <?= $templateId ?>;
    var TEMPLATE_NAME  = <?= json_encode($templateName, JSON_UNESCAPED_UNICODE) ?>;
    var DOC_ID         = <?= $docId ?>;
    var TEMPLATE_VARS  = <?= json_encode($templateVars, JSON_UNESCAPED_UNICODE) ?>;
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
                node.textContent = value;
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
           ─────────────────────────────────────────────
           The editor is ONE continuous Quill per stream
           (qSingle, or qLeft + qRight in split mode). There is
           no pagination in JS at all — the document is stored as
           a single continuous stream and the browser paginates it
           at print time. quillMain is an alias to qSingle.
        ───────────────────────────────────────────── */
        var splitActive = false;
        var activeQuill = null;
        var quillMain   = null;   // alias of qSingle
        var qSingle = null, qLeft = null, qRight = null;
        var Delta       = Quill.import('delta');

        /* ─────────────────────────────────────────────
           Variable name modal
        ───────────────────────────────────────────── */
        var _varSuggestions = [];

        function escAttr(s) {
            return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;')
                .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        }

        // Names of variables currently embedded anywhere in this document.
        function collectLiveVarNames() {
            var names = {};
            [qSingle, qLeft, qRight].forEach(function (q) {
                if (!q) return;
                (q.getContents().ops || []).forEach(function (op) {
                    if (op.insert && typeof op.insert === 'object' && op.insert.variable) {
                        names[op.insert.variable] = true;
                    }
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
                // Show the variable exactly as it reads in the document (no $…$).
                return '<button type="button" class="var-suggest-chip" data-var="' + escAttr(s.name) + '"' +
                       (s.hint ? ' title="' + escAttr(s.hint) + '"' : '') +
                       '>' + escAttr(s.name) + '</button>';
            }).join('');
        }

        // Paint a persistent highlight over the editor's selection. The native
        // contenteditable highlight vanishes the moment focus moves to this
        // panel, so we draw our own from the selection's DOM rectangles (a
        // pointer-events:none overlay, like the page-guides). It's purely visual
        // and survives blur, scrolling and re-selection.
        function quillDomRange(q, range) {
            if (!q || !range || !range.length) return null;
            var s = q.getLeaf(range.index);
            var e = q.getLeaf(range.index + range.length);
            if (!s || !s[0] || !e || !e[0]) return null;
            try {
                var r = document.createRange();
                r.setStart(s[0].domNode, s[1]);
                r.setEnd(e[0].domNode, e[1]);
                return r;
            } catch (err) { return null; }
        }
        function showSelHighlight(q, range) {
            var overlay = document.getElementById('docSelHighlight');
            if (!overlay) return;
            var domRange = quillDomRange(q, range);
            if (!domRange) { overlay.innerHTML = ''; return; }
            var base  = overlay.parentNode.getBoundingClientRect();
            var rects = domRange.getClientRects();
            var html  = '';
            for (var i = 0; i < rects.length; i++) {
                var rc = rects[i];
                if (rc.width < 1 || rc.height < 1) continue;
                html += '<div class="sel-rect" style="left:' + (rc.left - base.left) +
                        'px;top:' + (rc.top - base.top) + 'px;width:' + rc.width +
                        'px;height:' + rc.height + 'px"></div>';
            }
            overlay.innerHTML = html;
        }
        function hideSelHighlight() {
            var overlay = document.getElementById('docSelHighlight');
            if (overlay) overlay.innerHTML = '';
        }

        // Reflect the current insert target in the panel: the "will be replaced"
        // note and the selection highlight. Called on open and whenever the user
        // re-selects a different piece of text while the panel stays open.
        function refreshVarTargetUI(q, range) {
            var note = document.getElementById('varReplaceNote');
            if (q && range && range.length) {
                var sel = q.getText(range.index, range.length).replace(/\s+/g, ' ').trim();
                if (sel.length > 80) sel = sel.slice(0, 80) + '…';
                note.textContent = 'Избраниот текст ќе биде заменет: „' + sel + '“';
                note.style.display = '';
                showSelHighlight(q, range);
            } else {
                note.style.display = 'none';
                hideSelHighlight();
            }
        }

        function openVarNameModal() {
            document.getElementById('varNameInput').value = '';
            var q = activeQuill || quillMain;
            refreshVarTargetUI(q, q ? (q.getSelection() || q._sel) : null);

            _varSuggestions = buildSuggestions();
            renderVarSuggestions();
            // Floating panel: stays open without dimming/locking the page, so the
            // user can keep reading the document and insert as many variables as
            // they like — clicking elsewhere in the text just moves the target.
            document.getElementById('varNameModal').classList.add('open');
            setTimeout(function () { document.getElementById('varNameInput').focus(); }, 50);
        }

        function closeVarNameModal() {
            document.getElementById('varNameModal').classList.remove('open');
            hideSelHighlight();
            document.getElementById('varReplaceNote').style.display = 'none';
        }

        // Insert a variable at the caret/selection the user last had in an editor.
        // We read q._sel (the remembered selection, which survives the editor
        // losing focus to this panel) and advance it ourselves — without calling
        // setSelection(), which would steal focus back from the panel and break
        // continuous inserting. Returns false if there's no editor to target.
        function insertVariableAt(name) {
            var q = activeQuill || quillMain;
            if (!q) return false;
            var range = q.getSelection() || q._sel
                     || { index: Math.max(q.getLength() - 1, 0), length: 0 };
            var change = new Delta().retain(range.index);
            if (range.length) change.delete(range.length);   // replace selection
            change.insert({ variable: name });
            preserveScroll(function () { q.updateContents(change, 'user'); });
            // Remember the spot just after the inserted variable so a follow-up
            // insert lands right after it if the caret isn't moved.
            q._sel = { index: range.index + 1, length: 0 };
            return true;
        }

        // Sanitise a chosen name, insert it at the current target, and KEEP the
        // panel open so the user can place another one. Spaces are allowed (e.g.
        // "Ime na klient"); only the "$" delimiter is stripped.
        function chooseVariable(name) {
            name = (name || '').replace(/\$/g, '').replace(/\s+/g, ' ').trim();
            if (!name) return false;
            if (!insertVariableAt(name)) return false;
            // Reset for the next one: clear the field, drop the now-stale replace
            // note, and refresh suggestions (the new variable now appears there).
            var input = document.getElementById('varNameInput');
            input.value = '';
            document.getElementById('varReplaceNote').style.display = 'none';
            hideSelHighlight();   // the highlighted text was just consumed
            _varSuggestions = buildSuggestions();
            renderVarSuggestions();
            input.focus();
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

        // The panel is non-modal, so focus may sit in the document while it is
        // open — catch Escape globally to close it from anywhere.
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' &&
                document.getElementById('varNameModal').classList.contains('open')) {
                closeVarNameModal();
            }
        });

        /* ─────────────────────────────────────────────
           Insert variable at cursor
        ───────────────────────────────────────────── */
        function insertVariable() {
            var q = activeQuill || quillMain;
            if (!q) return;
            // getSelection(true) focuses the editor and records the caret/selection
            // in _sel, so the panel knows where to insert and what to highlight.
            q.getSelection(true);
            openVarNameModal();
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

        // The page actually scrolls inside .main-content (not the window).
        var SCROLL_CONTAINER = document.querySelector('.main-content');

        // Snapshot the current scroll position NOW, then keep restoring it over
        // the next few frames. Quill scrolls the caret/selection into view after
        // many actions (paste, applying a toolbar format, focus) which yanks the
        // page around — call this BEFORE Quill does its work to hold the viewport.
        function pinScroll() {
            var sc = SCROLL_CONTAINER;
            var top  = sc ? sc.scrollTop : 0;
            var winX = window.scrollX, winY = window.scrollY;
            function restore() {
                if (sc) sc.scrollTop = top;
                window.scrollTo(winX, winY);
            }
            restore();
            requestAnimationFrame(restore);
            setTimeout(restore, 0);
            setTimeout(restore, 40);
        }

        // Run `fn` (our own caret-moving change, e.g. variable insert / undo),
        // then hold the viewport so Quill's scroll-into-view can't yank the page.
        function preserveScroll(fn) {
            var sc = SCROLL_CONTAINER;
            var top = sc ? sc.scrollTop : window.scrollY;
            if (fn) fn();
            if (sc) sc.scrollTop = top;        // undo a synchronous scroll
            pinScroll();                        // and any deferred one
        }

        // Pasting makes Quill scroll the page (it focuses an off-screen clipboard
        // node, then scrolls the caret into view a tick later). Capture in the
        // CAPTURE phase on the document — this runs BEFORE Quill's own paste
        // handler on the editor root — so we record the true pre-paste position.
        document.addEventListener('paste', function (e) {
            if (SCROLL_CONTAINER && SCROLL_CONTAINER.contains(e.target)) pinScroll();
        }, true);

        // Only these formats are allowed in the editor. Quill drops anything
        // else on paste (Word/Google-Docs fonts, font-sizes, inline-style noise)
        // while keeping everything the toolbar offers + the variable chip — this
        // is what makes pasting behave like a clean Google-Docs paste.
        var ALLOWED_FORMATS = [
            'bold', 'italic', 'underline', 'strike',
            'list', 'indent', 'align',
            'color', 'background', 'header',
            'variable'
        ];

        function makeQuill(el, placeholder, ownsToolbar) {
            var q = new Quill(el, {
                theme: 'snow',
                modules: { toolbar: ownsToolbar ? '#quill-toolbar' : false },
                formats: ALLOWED_FORMATS,
                placeholder: placeholder || 'Започни да пишуваш...',
                scrollingContainer: SCROLL_CONTAINER || undefined,
            });
            trackFocus(q);
            // Remember the editor's selection while it is focused (collapsed or
            // not). We deliberately ignore blur (range === null) so the range
            // survives clicking the toolbar — used to re-show the highlight after
            // a format (e.g. alignment) is applied.
            q.on('selection-change', function (range) {
                if (range) q._sel = range;
                // While the variable panel is open, keep its note + highlight in
                // sync as the user re-selects text. Ignore blur (range === null)
                // so focusing the panel doesn't wipe the highlight.
                if (range && document.getElementById('varNameModal').classList.contains('open')) {
                    activeQuill = q;
                    refreshVarTargetUI(q, range);
                }
            });
            q.on('text-change', function (delta, oldDelta, source) {
                if (source === 'user') {
                    // "/" at the start of a word opens the variable picker (slash menu).
                    // Guarded so dates (12/05), "и/или" and URLs (http://) never trigger it:
                    // it only fires when the slash follows whitespace or starts the text.
                    var ops = delta.ops || [];
                    var lastOp = ops[ops.length - 1];
                    if (lastOp && lastOp.insert === '/') {
                        var pos = 0;
                        ops.forEach(function (op) {
                            if (typeof op.retain === 'number') pos += op.retain;
                            else if (typeof op.insert === 'string') pos += op.insert.length;
                            else if (op.insert) pos += 1;
                        });
                        var prevCh = pos >= 2 ? q.getText(pos - 2, 1) : '';
                        if (pos === 1 || /\s/.test(prevCh)) {
                            q.deleteText(pos - 1, 1, 'silent');
                            q.setSelection(pos - 1, 0, 'silent');
                            activeQuill = q;
                            setTimeout(insertVariable, 0);
                            return;
                        }
                    }
                }
                // No pagination — the editor just grows. We only refresh the
                // (visual) page guides as the content changes.
                scheduleGuides();
            });
            return q;
        }

        /* ─────────────────────────────────────────────
           Build the continuous editors (one per stream)
        ───────────────────────────────────────────── */
        var sheet     = document.getElementById('docSheet');
        var sheetBody = document.getElementById('docSheetBody');
        var headerEl  = document.getElementById('docHeaderEditor');
        var footerEl  = document.getElementById('docFooterEditor');

        qSingle = makeQuill(sheetBody.querySelector('.q-single'), 'Започни да пишуваш...', true);
        qLeft   = makeQuill(sheetBody.querySelector('.q-left'),  'Лев текст...',   false);
        qRight  = makeQuill(sheetBody.querySelector('.q-right'), 'Десен текст...', false);
        quillMain   = qSingle;
        activeQuill = qSingle;

        /* ─────────────────────────────────────────────
           Real page-break lines (purely informational)
           ─────────────────────────────────────────────
           Draws a line in the editor exactly where the BROWSER will start a new
           printed page, so the user knows where the real pages end. It does NOT
           reflow or move the text — it only writes to a pointer-events:none
           overlay, so paste/typing/caret/scroll/print are untouched.

           The break interval = the printable content height of one A4 page:
           29.7cm − 2·1cm (page margins) − the repeating header/footer bands
           (1.5cm each, only when a header/footer exists). This matches the print
           layout built in pregled-shablon.php / js/draft-workspace.js.
        ───────────────────────────────────────────── */
        var guidesEl  = document.getElementById('docPageGuides');
        var counterEl = document.getElementById('docPageCounter');

        var _cmPx = 0;
        function cmPx() {
            if (_cmPx) return _cmPx;
            var p = document.createElement('div');
            p.style.cssText = 'position:absolute;visibility:hidden;height:10cm;';
            document.body.appendChild(p);
            _cmPx = (p.offsetHeight || 378) / 10;
            p.remove();
            return _cmPx;
        }
        // Gap reserved between the header/footer and the body on a printed page.
        // MUST match HF_GAP_CM in the print builders (pregled-shablon.php /
        // js/draft-workspace.js) so the editor's break lines line up with print.
        var HF_GAP_CM = 0.5;
        function pageBudgetPx() {
            var cm = cmPx();
            // The editor header/footer fields are rendered at the same width
            // (15cm) and font as the printed running header/footer, so their
            // measured height is what print reserves (height + gap) per page.
            var hasHeader = headerEl && headerEl.textContent.trim() !== '';
            var hasFooter = footerEl && footerEl.textContent.trim() !== '';
            var resH = hasHeader ? headerEl.offsetHeight + HF_GAP_CM * cm : 0;
            var resF = hasFooter ? footerEl.offsetHeight + HF_GAP_CM * cm : 0;
            return Math.round(27.7 * cm - resH - resF);
        }

        // Collect the Y position of every rendered LINE box (relative to the
        // editor top). getClientRects() per BLOCK returns one rect per line of
        // that block (across blocks it would return whole-block rects, which is
        // wrong — hence we measure block by block). Empty blocks (e.g. a blank
        // line) fall back to their own box.
        function collectLines(root) {
            var rootTop = root.getBoundingClientRect().top;
            var lines = [], blocks = root.children;
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

        // Page-break Y positions the way the browser paginates: break BEFORE the
        // first line that would overflow the page (a text line is never split).
        // The line is drawn in the gap between the last fitting line and the one
        // that moves to the next page, so it never sits on top of text.
        function computeBreaks(root, budget) {
            var lines = collectLines(root);
            var breaks = [], limit = budget, prevBottom = 0;
            for (var i = 0; i < lines.length; i++) {
                if (lines[i].bottom > limit + 1) {     // this line doesn't fit → new page
                    var y = (lines[i].top > prevBottom) ? (prevBottom + lines[i].top) / 2 : lines[i].top;
                    breaks.push(y);
                    limit = lines[i].top + budget;
                }
                prevBottom = lines[i].bottom;
            }
            return breaks;
        }

        function drawPageBreaks() {
            if (!guidesEl) return;
            // Defensively clear any leftover background from earlier builds.
            if (sheetBody.style.backgroundImage) sheetBody.style.backgroundImage = '';
            var budget = pageBudgetPx();
            var breaks;
            if (splitActive) {
                // The printed table row breaks both columns at the same page
                // boundary; use whichever column runs longer to place the line.
                var lb = computeBreaks(qLeft.root, budget);
                var rb = computeBreaks(qRight.root, budget);
                breaks = lb.length >= rb.length ? lb : rb;
            } else {
                breaks = computeBreaks(qSingle.root, budget);
            }
            var html = '';
            for (var i = 0; i < breaks.length; i++) {
                html += '<div class="doc-page-break" style="top:' + Math.round(breaks[i]) + 'px">' +
                            '<span class="doc-page-break-num">Крај на страница ' + (i + 1) + '</span>' +
                        '</div>';
            }
            guidesEl.innerHTML = html;
            var pages = breaks.length + 1;
            if (counterEl) counterEl.textContent = pages + ' ' + (pages === 1 ? 'страница' : 'страници');
        }

        var _guideTimer = null;
        function scheduleGuides() {
            clearTimeout(_guideTimer);
            _guideTimer = setTimeout(drawPageBreaks, 150);
        }

        /* ─────────────────────────────────────────────
           Save / load shape
           ─────────────────────────────────────────────
           No pagination: a document is stored as a SINGLE page holding the
           whole continuous stream plus the document-level header/footer. The
           browser paginates it at print time and repeats header/footer on every
           A4 page (via the print table's thead/tfoot). buildPages() returns that
           one-element pages[] array; mergePages() reads it back — and stays
           backward-compatible with documents saved as multiple pages (it
           concatenates every page's streams into one and takes the first page's
           header/footer).
        ───────────────────────────────────────────── */
        function buildPages() {
            return [{
                header: trimHtml(headerEl.innerHTML || ''),
                footer: trimHtml(footerEl.innerHTML || ''),
                single: splitActive ? null : getTrimmedContents(qSingle),
                left:   splitActive ? getTrimmedContents(qLeft)  : null,
                right:  splitActive ? getTrimmedContents(qRight) : null
            }];
        }

        function mergePages(pages) {
            var single = new Delta(), left = new Delta(), right = new Delta();
            var header = '', footer = '';
            (pages || []).forEach(function (p) {
                if (p.single) single = single.concat(new Delta(p.single));
                if (p.left)   left   = left.concat(new Delta(p.left));
                if (p.right)  right  = right.concat(new Delta(p.right));
                if (!header && p.header) header = p.header;
                if (!footer && p.footer) footer = p.footer;
            });
            return { single: single, left: left, right: right, header: header, footer: footer };
        }

        /* ─────────────────────────────────────────────
           Toolbar bridge (routes to activeQuill)
        ───────────────────────────────────────────── */
        var toolbarEl = document.getElementById('quill-toolbar');

        // Applying a toolbar format (bold, align, colour, heading…) makes Quill
        // re-focus the editor and scroll the selection into view, which jumps the
        // page. Snapshot the scroll in the CAPTURE phase — before Quill's own
        // button handlers and our split-mode bridge below run — and hold it.
        toolbarEl.addEventListener('mousedown', pinScroll, true);
        toolbarEl.addEventListener('click', pinScroll, true);

        // Keep the selection highlight visible WHILE a dropdown (alignment,
        // colour, heading…) is open: cancel the default focus-shift on the picker
        // LABEL's mousedown so opening the dropdown doesn't blur the editor.
        // (Only the label — preventing it on the option items stops Quill from
        // registering the choice. The item click then applies the format, and the
        // click handler below re-selects the range to keep the highlight.)
        toolbarEl.addEventListener('mousedown', function (e) {
            if (e.target.closest('.ql-picker-label')) e.preventDefault();
        }, true);

        // Keep the text visually selected after a toolbar action. Clicking the
        // toolbar blurs the editor, so the browser stops painting the selection
        // highlight even though the format still applies (most noticeable with
        // alignment, which is a dropdown). Re-focus + re-select the tracked range
        // after the format is applied, so the highlight stays visible.
        toolbarEl.addEventListener('click', function (e) {
            var q = activeQuill;
            if (!q || !q._sel || q._sel.length === 0) return;
            // Don't refocus while merely opening a dropdown (it would close it),
            // and let undo/redo manage their own caret.
            if (e.target.closest('#btnUndo, #btnRedo')) return;
            if (e.target.closest('.ql-picker-label') && !e.target.closest('.ql-picker-item')) return;
            var r = { index: q._sel.index, length: q._sel.length };
            function restore() {
                preserveScroll(function () {
                    q.focus();
                    q.setSelection(r.index, r.length, 'silent');
                });
            }
            // Run after Quill applies the format, and again a little later to beat
            // its deferred selection/dropdown-close update.
            setTimeout(restore, 0);
            setTimeout(restore, 60);
        }, true);

        // Quill replaces the <select>s with its own markup, so put the plain-language
        // tooltips on the generated dropdown labels for non-technical users.
        (function decorateToolbar() {
            var titles = {
                'ql-header':     'Стил на текст и наслови',
                'ql-color':      'Боја на текст',
                'ql-background': 'Боја на маркер (позадина)',
                'ql-align':      'Порамнување на текст'
            };
            Object.keys(titles).forEach(function (cls) {
                var picker = toolbarEl.querySelector('.ql-picker.' + cls);
                if (!picker) return;
                var label = picker.querySelector('.ql-picker-label');
                if (label) label.setAttribute('title', titles[cls]);
            });
        })();

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
            sheet.classList.toggle('split-mode', splitActive);
            if (splitActive) {
                activeQuill = qLeft;
                setTimeout(function () { qLeft.focus(); }, 60);
            } else {
                activeQuill = qSingle;
                setTimeout(function () { qSingle.focus(); }, 60);
            }
            scheduleGuides();
        });

        /* ─────────────────────────────────────────────
           Undo / redo — operate on the focused editor's own history. Buttons
           don't carry a ql- class, so Quill's toolbar leaves them to us.
        ───────────────────────────────────────────── */
        (function () {
            function run(which) {
                var q = activeQuill || quillMain;
                if (!q || !q.history) return;
                preserveScroll(function () {
                    q.history[which]();
                    q.focus();
                });
            }
            // mousedown preventDefault keeps the editor selection/focus intact.
            ['btnUndo', 'btnRedo'].forEach(function (id) {
                var btn = document.getElementById(id);
                btn.addEventListener('mousedown', function (e) { e.preventDefault(); });
            });
            document.getElementById('btnUndo').addEventListener('click', function () { run('undo'); });
            document.getElementById('btnRedo').addEventListener('click', function () { run('redo'); });
        }());

        /* ─────────────────────────────────────────────
           Load a state object (EDIT_DOC or a local draft) into the editor
        ───────────────────────────────────────────── */
        function applyStreams(streams) {
            if (streams.single != null) qSingle.setContents(new Delta(streams.single), 'silent');
            if (streams.left   != null) qLeft.setContents(new Delta(streams.left),   'silent');
            if (streams.right  != null) qRight.setContents(new Delta(streams.right), 'silent');
        }

        // Accepts both shapes: a saved document ({ pages: [...] }) and a local
        // draft ({ single|left|right } continuous streams). Legacy multi-page
        // documents are concatenated into one continuous stream by mergePages.
        function loadState(state) {
            document.getElementById('docTitleInput').value = state.name || '';
            splitActive = !!parseInt(state.is_split);
            if (splitActive) {
                document.getElementById('btnSplitToggle').classList.add('is-active');
                sheet.classList.add('split-mode');
                activeQuill = qLeft;
            }

            var streams, header, footer;
            if (state.single !== undefined || state.left !== undefined || state.right !== undefined) {
                streams = { single: state.single, left: state.left, right: state.right };
                header  = state.header || '';
                footer  = state.footer || '';
            } else {
                streams = mergePages(state.pages || []);
                header  = streams.header || '';
                footer  = streams.footer || '';
            }
            if (header) headerEl.innerHTML = header;
            if (footer) footerEl.innerHTML = footer;
            applyStreams(streams);
        }

        /* ─────────────────────────────────────────────
           Unsaved-change tracking — a snapshot of the editor's continuous
           streams, compared against the state the document opened with so we
           can warn before leaving with unsaved work.
        ───────────────────────────────────────────── */
        function collectContinuous() {
            return {
                header: trimHtml(headerEl.innerHTML || ''),
                footer: trimHtml(footerEl.innerHTML || ''),
                single: splitActive ? null : getTrimmedContents(qSingle),
                left:   splitActive ? getTrimmedContents(qLeft)  : null,
                right:  splitActive ? getTrimmedContents(qRight) : null,
            };
        }

        /* ─────────────────────────────────────────────
           Initial content: EDIT_DOC → blank
        ───────────────────────────────────────────── */
        if (EDIT_DOC) loadState(EDIT_DOC);

        // Draw the page-break lines on load. A second pass after layout settles
        // keeps them accurate once fonts/metrics are final.
        drawPageBreaks();
        setTimeout(drawPageBreaks, 200);
        window.addEventListener('resize', scheduleGuides);

        // Refresh the page-break lines on any edit (e.g. adding a header/footer
        // changes the per-page content height).
        document.addEventListener('input', scheduleGuides);

        // Signature of the state the document opened with; the live signature is
        // compared against it to know whether there are unsaved changes. Captured
        // after the initial load settles.
        var _initialSig = null;
        function draftSignature() {
            return JSON.stringify({
                name:     document.getElementById('docTitleInput').value,
                is_split: splitActive ? 1 : 0,
                streams:  collectContinuous()
            });
        }
        function isDirty() {
            return _initialSig !== null && draftSignature() !== _initialSig;
        }
        setTimeout(function () { _initialSig = draftSignature(); }, 50);

        /* ─────────────────────────────────────────────
           Warn before leaving the editor with unsaved changes
        ───────────────────────────────────────────── */
        var _leaving    = false;   // set once the user confirms they want to leave
        var _leaveHref  = null;
        var leaveModal  = document.getElementById('leaveModal');

        function openLeaveModal(href) {
            _leaveHref = href;
            leaveModal.classList.add('open');
            leaveModal.removeAttribute('aria-hidden');
            document.body.classList.add('modal-open');
        }
        function closeLeaveModal() {
            if (leaveModal.contains(document.activeElement)) document.activeElement.blur();
            leaveModal.classList.remove('open');
            leaveModal.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('modal-open');
        }

        // Intercept the Назад button: confirm only when there are unsaved changes.
        var backLink = document.querySelector('.doc-toolbar-back');
        if (backLink) backLink.addEventListener('click', function (e) {
            if (!isDirty()) return;          // nothing to lose → navigate normally
            e.preventDefault();
            openLeaveModal(backLink.getAttribute('href'));
        });
        document.getElementById('leaveClose').addEventListener('click', closeLeaveModal);
        document.getElementById('leaveStay').addEventListener('click', closeLeaveModal);
        leaveModal.addEventListener('click', function (e) { if (e.target === this) closeLeaveModal(); });
        document.getElementById('leaveDiscard').addEventListener('click', function () {
            _leaving = true;
            window.location.href = _leaveHref || ('pregled-shablon.php?id=' + TEMPLATE_ID);
        });

        // Catch other exits (refresh, tab close, browser Back) with the native
        // prompt while there are unsaved changes.
        window.addEventListener('beforeunload', function (e) {
            if (_leaving || !isDirty()) return;
            e.preventDefault();
            e.returnValue = '';
        });

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

        // Strip leading/trailing whitespace (incl. &nbsp; and <br>) from the
        // contenteditable header/footer HTML; collapse to '' when effectively empty.
        function trimHtml(html) {
            if (!html) return '';
            var trimmed = html
                .replace(/^(?:&nbsp;|\s|<br\s*\/?>)+/gi, '')
                .replace(/(?:&nbsp;|\s|<br\s*\/?>)+$/gi, '');
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
                if (stripped === '') {
                    // A trailing whitespace/newline op that carries block
                    // formatting (align, header, list, indent) is the LAST
                    // paragraph's terminating newline — keep it, and its format,
                    // instead of dropping it. Otherwise the final line's
                    // alignment/heading is lost on save.
                    if (last.attributes) { last.insert = '\n'; break; }
                    ops.pop();
                } else {
                    last.insert = stripped;
                    break;
                }
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

        // Briefly turn the save button green with a check mark to confirm a save,
        // then animate it back to its normal state.
        var CHECK_SVG = '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>';
        function showSaved(btn) {
            btn.classList.add('btn-saved');
            btn.innerHTML = CHECK_SVG + ' Зачувано';
            clearTimeout(btn._savedTimer);
            btn._savedTimer = setTimeout(function () {
                btn.classList.remove('btn-saved');
                btn.innerHTML = btn._origHtml;
                btn.disabled = false;
            }, 1900);
        }

        // Persist the document. Returns false (without saving) when the title is
        // missing; otherwise saves and calls onSuccess() once the server confirms.
        // Used by the Save button and by "Преземи" (which saves before previewing).
        function saveDocument(onSuccess) {
            var titleInput = document.getElementById('docTitleInput');
            var name = titleInput.value.trim();
            if (!name) {
                titleInput.classList.add('input-error');
                titleInput.focus();
                return false;
            }
            titleInput.classList.remove('input-error');

            // Slice the continuous editor into A4 pages (the saved shape every
            // consumer already understands). buildPages keeps only the active
            // layout, so stale split/single content can't linger after a toggle.
            var pages = buildPages();

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
            if (!btn._origHtml) btn._origHtml = btn.innerHTML;
            btn.disabled = true;

            fetch('api/document_api.php', { method: 'POST', body: params })
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    if (res.success) {
                        // Stay on the page (no redirect). If this was a brand-new
                        // doc, adopt the id the server assigned so later saves
                        // update it (no duplicates) and a refresh reopens it.
                        if (!DOC_ID && res.id) {
                            DOC_ID = res.id;
                            try {
                                history.replaceState(null, '',
                                    'kreraj-dokument.php?doc_id=' + DOC_ID + '&template_id=' + TEMPLATE_ID);
                            } catch (e) {}
                        }
                        // The just-saved state is the new "unchanged" baseline, so
                        // leaving right after a save won't warn about lost changes.
                        _initialSig = draftSignature();
                        showSaved(btn);
                        if (onSuccess) onSuccess();
                    } else {
                        alert(res.message || 'Грешка при зачувување.');
                        btn.disabled = false;
                    }
                })
                .catch(function () {
                    alert('Грешка при поврзување.');
                    btn.disabled = false;
                });
            return true;
        }

        document.getElementById('btnSave').addEventListener('click', function () { saveDocument(); });

        /* ─────────────────────────────────────────────
           Преземи / Избриши this document (only present when editing a saved doc)
        ───────────────────────────────────────────── */
        var btnDownloadDoc = document.getElementById('btnDownloadDoc');
        if (btnDownloadDoc) btnDownloadDoc.addEventListener('click', function () {
            // The preview workspace reads from the DB, so save the current edits
            // first, then open it scoped to just this document.
            saveDocument(function () {
                if (!window.DraftWorkspace) return;
                var name = document.getElementById('docTitleInput').value.trim();
                window.DraftWorkspace.open(TEMPLATE_ID, TEMPLATE_NAME, {
                    docId: DOC_ID, docName: name, refresh: true
                });
            });
        });

        var btnDeleteDoc = document.getElementById('btnDeleteDoc');
        if (btnDeleteDoc) {
            var dm = document.getElementById('docDeleteModal');
            var openDel  = function () { dm.classList.add('open'); dm.removeAttribute('aria-hidden'); document.body.classList.add('modal-open'); };
            var closeDel = function () {
                if (dm.contains(document.activeElement)) document.activeElement.blur();
                dm.classList.remove('open'); dm.setAttribute('aria-hidden', 'true'); document.body.classList.remove('modal-open');
            };
            btnDeleteDoc.addEventListener('click', openDel);
            document.getElementById('docDeleteClose').addEventListener('click', closeDel);
            document.getElementById('docDeleteCancel').addEventListener('click', closeDel);
            dm.addEventListener('click', function (e) { if (e.target === this) closeDel(); });

            document.getElementById('docDeleteConfirm').addEventListener('click', function () {
                var btn = this;
                btn.disabled = true;
                btn.textContent = 'Се брише...';
                fetch('api/document_api.php', { method: 'POST', body: new URLSearchParams({ action: 'delete', id: DOC_ID }) })
                    .then(function (r) { return r.json(); })
                    .then(function (res) {
                        if (res.success) {
                            _leaving = true;   // don't warn about unsaved changes
                            window.location.href = 'pregled-shablon.php?id=' + TEMPLATE_ID;
                        } else {
                            alert(res.message || 'Грешка при бришење.');
                            btn.disabled = false; btn.textContent = 'Избриши';
                        }
                    })
                    .catch(function () {
                        alert('Грешка при поврзување.');
                        btn.disabled = false; btn.textContent = 'Избриши';
                    });
            });
        }

        // Ctrl+S (Cmd+S) → save, instead of the browser's "save page" dialog.
        document.addEventListener('keydown', function (e) {
            if ((e.ctrlKey || e.metaKey) && (e.key === 's' || e.key === 'S')) {
                e.preventDefault();
                document.getElementById('btnSave').click();
            }
        });

    }());
    </script>
</body>
</html>

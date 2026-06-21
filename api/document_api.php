<?php

define('FAKTA_API', true);
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../classes/DocxFiller.php';

require_login();

header('Content-Type: application/json; charset=utf-8');

// On hosts with display_errors off (e.g. InfinityFree), a fatal error (memory
// limit, etc.) would otherwise return an empty body → "Unexpected end of JSON
// input" on the client. Surface it as JSON so the real cause is visible.
register_shutdown_function(function () {
    $e = error_get_last();
    if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true)) {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode(['success' => false, 'message' => 'Фатална грешка: ' . $e['message']]);
    }
});

$pdo = $GLOBALS['fakta_db']->getConnection();
$companyId = current_company_id();
$userId    = (int) (current_user()['id'] ?? 0);
$isPraktikant = current_role() === 'praktikant';

$action = $_POST['action'] ?? $_GET['action'] ?? '';

/**
 * Praktikant may only edit/delete documents they created themselves (they may,
 * however, add new documents to any template — see the 'create' action).
 * Returns true when allowed; otherwise echoes a 403 JSON and returns false.
 */
function praktikant_guard_document(PDO $pdo, int $documentId, int $companyId, int $userId, bool $isPraktikant): bool
{
    if (!$isPraktikant) {
        return true;
    }
    $stmt = $pdo->prepare('SELECT created_by FROM documents WHERE id = ? AND company_id = ?');
    $stmt->execute([$documentId, $companyId]);
    $row = $stmt->fetch();
    if ($row && $row['created_by'] !== null && (int) $row['created_by'] === $userId && $userId > 0) {
        return true;
    }
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Можете да менувате само документи што вие сте ги креирале.']);
    return false;
}

/** Recursively delete a directory and its contents (best-effort). */
function rrmdir(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }
    foreach (scandir($dir) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        $path = $dir . DIRECTORY_SEPARATOR . $entry;
        is_dir($path) ? rrmdir($path) : @unlink($path);
    }
    @rmdir($dir);
}

/** Sanitize a document name into a safe download filename base (Cyrillic-safe). */
function safe_filename(string $name): string
{
    $name = preg_replace('/[\/\\\\:*?"<>|]+/u', ' ', $name);   // strip path/illegal chars
    $name = trim(preg_replace('/\s+/u', ' ', $name));
    return $name !== '' ? $name : 'document';
}

try {
    switch ($action) {

        case 'create':
            $templateId = (int) ($_POST['template_id'] ?? 0);
            $name       = trim($_POST['name'] ?? '');
            $isSplit    = (int) ($_POST['is_split'] ?? 0);
            $pages      = $_POST['pages'] ?? '[]';
            $variables  = $_POST['variables'] ?? '[]';

            if ($templateId <= 0 || $name === '') {
                echo json_encode(['success' => false, 'message' => 'Невалидни параметри.']);
                exit;
            }

            if (!is_array(json_decode($pages, true))) {
                echo json_encode(['success' => false, 'message' => 'Невалиден JSON за страниците.']);
                exit;
            }
            if (!is_array(json_decode($variables, true))) {
                $variables = '[]';
            }

            // The template must belong to the current company.
            // (Adding documents is allowed for everyone, incl. praktikant.)
            $own = $pdo->prepare('SELECT 1 FROM templates WHERE id = ? AND company_id = ?');
            $own->execute([$templateId, $companyId]);
            if (!$own->fetchColumn()) {
                echo json_encode(['success' => false, 'message' => 'Шаблонот не е пронајден.']);
                exit;
            }

            $stmt = $pdo->prepare('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM documents WHERE template_id = ? AND company_id = ?');
            $stmt->execute([$templateId, $companyId]);
            $sortOrder = (int) $stmt->fetchColumn();

            $stmt = $pdo->prepare(
                'INSERT INTO documents (company_id, template_id, created_by, name, is_split, pages, variables, sort_order, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())'
            );
            $stmt->execute([$companyId, $templateId, $userId ?: null, $name, $isSplit ? 1 : 0, $pages, $variables, $sortOrder]);
            echo json_encode(['success' => true, 'id' => (int) $pdo->lastInsertId()]);
            break;

        case 'update':
            $id        = (int) ($_POST['id'] ?? 0);
            $name      = trim($_POST['name'] ?? '');
            $isSplit   = (int) ($_POST['is_split'] ?? 0);
            $pages     = $_POST['pages'] ?? '[]';
            $variables = $_POST['variables'] ?? '[]';

            if ($id <= 0 || $name === '') {
                echo json_encode(['success' => false, 'message' => 'Невалидни параметри.']);
                exit;
            }

            if (!is_array(json_decode($pages, true))) {
                echo json_encode(['success' => false, 'message' => 'Невалиден JSON за страниците.']);
                exit;
            }
            if (!is_array(json_decode($variables, true))) {
                $variables = '[]';
            }

            // Praktikant may edit only the documents they created themselves.
            if (!praktikant_guard_document($pdo, $id, $companyId, $userId, $isPraktikant)) {
                exit;
            }
            $stmt = $pdo->prepare(
                'UPDATE documents SET name = ?, is_split = ?, pages = ?, variables = ?, updated_at = NOW() WHERE id = ? AND company_id = ?'
            );
            $stmt->execute([$name, $isSplit ? 1 : 0, $pages, $variables, $id, $companyId]);
            echo json_encode(['success' => true]);
            break;

        case 'get':
            $id = (int) ($_GET['id'] ?? 0);
            if ($id <= 0) {
                echo json_encode(['success' => false, 'message' => 'Невалиден ID.']);
                exit;
            }
            $stmt = $pdo->prepare('SELECT * FROM documents WHERE id = ? AND company_id = ?');
            $stmt->execute([$id, $companyId]);
            $doc = $stmt->fetch();
            if (!$doc) {
                echo json_encode(['success' => false, 'message' => 'Документот не е пронајден.']);
                exit;
            }
            $doc['pages']     = json_decode($doc['pages'],     true) ?: [];
            $doc['variables'] = json_decode($doc['variables'], true) ?: [];
            echo json_encode(['success' => true, 'data' => $doc]);
            break;

        case 'list_by_template':
            $templateId = (int) ($_GET['template_id'] ?? 0);
            if ($templateId <= 0) {
                echo json_encode(['success' => false, 'message' => 'Невалиден template_id.']);
                exit;
            }
            $stmt = $pdo->prepare('SELECT * FROM documents WHERE template_id = ? AND company_id = ? ORDER BY sort_order ASC');
            $stmt->execute([$templateId, $companyId]);
            $docs = $stmt->fetchAll();
            foreach ($docs as &$doc) {
                $doc['pages']     = json_decode($doc['pages'],     true) ?: [];
                $doc['variables'] = json_decode($doc['variables'], true) ?: [];
            }
            unset($doc);
            echo json_encode(['success' => true, 'data' => $docs]);
            break;

        case 'delete':
            $id = (int) ($_POST['id'] ?? 0);
            if ($id <= 0) {
                echo json_encode(['success' => false, 'message' => 'Невалиден ID.']);
                exit;
            }
            if (!praktikant_guard_document($pdo, $id, $companyId, $userId, $isPraktikant)) {
                exit;
            }
            // For imported docs, delete the uploaded original + .docx master too.
            $fstmt = $pdo->prepare('SELECT file_path, orig_path FROM documents WHERE id = ? AND company_id = ?');
            $fstmt->execute([$id, $companyId]);
            if ($frow = $fstmt->fetch()) {
                foreach ([$frow['file_path'] ?? null, $frow['orig_path'] ?? null] as $rel) {
                    if ($rel) {
                        @unlink(UPLOADS_DIR . '/' . $rel);
                    }
                }
            }
            $stmt = $pdo->prepare('DELETE FROM documents WHERE id = ? AND company_id = ?');
            $stmt->execute([$id, $companyId]);
            echo json_encode(['success' => true]);
            break;

        // ── Imported files ([placeholder] documents) ─────────────────────────
        case 'import':
            $templateId = (int) ($_POST['template_id'] ?? 0);
            $name       = trim($_POST['name'] ?? '');
            if ($templateId <= 0 || $name === '') {
                echo json_encode(['success' => false, 'message' => 'Невалидни параметри.']);
                exit;
            }

            // The template must belong to the current company (everyone, incl.
            // praktikant, may add documents — same rule as 'create').
            $own = $pdo->prepare('SELECT 1 FROM templates WHERE id = ? AND company_id = ?');
            $own->execute([$templateId, $companyId]);
            if (!$own->fetchColumn()) {
                echo json_encode(['success' => false, 'message' => 'Шаблонот не е пронајден.']);
                exit;
            }

            if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
                echo json_encode(['success' => false, 'message' => 'Датотеката не е прикачена правилно.']);
                exit;
            }
            $file = $_FILES['file'];
            if ($file['size'] > 25 * 1024 * 1024) {
                echo json_encode(['success' => false, 'message' => 'Датотеката е преголема (макс. 25MB).']);
                exit;
            }
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if ($ext !== 'docx') {
                echo json_encode(['success' => false, 'message' => 'Дозволени се само .docx датотеки.']);
                exit;
            }

            $dir = UPLOADS_DIR . '/imported/' . $companyId;
            if (!is_dir($dir) && !@mkdir($dir, 0775, true)) {
                echo json_encode(['success' => false, 'message' => 'Не може да се креира папка за прикачување.']);
                exit;
            }

            $uid     = bin2hex(random_bytes(8));
            $fileRel = 'imported/' . $companyId . '/' . $uid . '.docx';
            $fileAbs = UPLOADS_DIR . '/' . $fileRel;
            if (!move_uploaded_file($file['tmp_name'], $fileAbs)) {
                echo json_encode(['success' => false, 'message' => 'Не може да се зачува датотеката.']);
                exit;
            }

            try {
                $placeholders = DocxFiller::extractPlaceholders($fileAbs);
            } catch (Throwable $e) {
                @unlink($fileAbs);
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
                exit;
            }

            $stmt = $pdo->prepare('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM documents WHERE template_id = ? AND company_id = ?');
            $stmt->execute([$templateId, $companyId]);
            $sortOrder = (int) $stmt->fetchColumn();

            $stmt = $pdo->prepare(
                'INSERT INTO documents
                   (company_id, template_id, created_by, kind, name, is_split, pages, variables,
                    file_path, orig_path, file_ext, sort_order, created_at, updated_at)
                 VALUES (?, ?, ?, \'imported\', ?, 0, \'[]\', ?, ?, ?, \'docx\', ?, NOW(), NOW())'
            );
            $stmt->execute([
                $companyId, $templateId, $userId ?: null, $name,
                json_encode($placeholders, JSON_UNESCAPED_UNICODE),
                $fileRel, $fileRel, $sortOrder,
            ]);
            echo json_encode([
                'success'      => true,
                'id'           => (int) $pdo->lastInsertId(),
                'placeholders' => $placeholders,
            ]);
            break;

        case 'download_filled':
            $id = (int) ($_POST['id'] ?? $_GET['id'] ?? 0);
            if ($id <= 0) {
                echo json_encode(['success' => false, 'message' => 'Невалиден ID.']);
                exit;
            }
            $stmt = $pdo->prepare("SELECT * FROM documents WHERE id = ? AND company_id = ? AND kind = 'imported'");
            $stmt->execute([$id, $companyId]);
            $doc = $stmt->fetch();
            if (!$doc) {
                echo json_encode(['success' => false, 'message' => 'Документот не е пронајден.']);
                exit;
            }
            $masterAbs = UPLOADS_DIR . '/' . $doc['file_path'];
            if (!is_file($masterAbs)) {
                echo json_encode(['success' => false, 'message' => 'Изворната датотека недостасува.']);
                exit;
            }

            $values = json_decode($_POST['values'] ?? '{}', true);
            if (!is_array($values)) {
                $values = [];
            }

            $tmpDir = sys_get_temp_dir() . '/fakta_fill_' . bin2hex(random_bytes(6));
            @mkdir($tmpDir, 0775, true);
            try {
                $sendPath = $tmpDir . '/filled.docx';
                DocxFiller::fill($masterAbs, $sendPath, $values);
            } catch (Throwable $e) {
                rrmdir($tmpDir);
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
                exit;
            }

            $dlName = safe_filename($doc['name']) . '.docx';
            $ascii  = preg_replace('/[^\x20-\x7E]/', '_', $dlName);

            // Override the JSON content-type set at the top with the real file.
            header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
            header('Content-Disposition: attachment; filename="' . $ascii . '"; '
                . "filename*=UTF-8''" . rawurlencode($dlName));
            header('Content-Length: ' . filesize($sendPath));
            header('Cache-Control: no-store');
            readfile($sendPath);
            rrmdir($tmpDir);
            exit;

        case 'rename':
            $id   = (int) ($_POST['id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            if ($id <= 0 || $name === '') {
                echo json_encode(['success' => false, 'message' => 'Невалидни параметри.']);
                exit;
            }
            // Praktikant may rename only documents they created.
            if (!praktikant_guard_document($pdo, $id, $companyId, $userId, $isPraktikant)) {
                exit;
            }
            $stmt = $pdo->prepare('UPDATE documents SET name = ?, updated_at = NOW() WHERE id = ? AND company_id = ?');
            $stmt->execute([$name, $id, $companyId]);
            echo json_encode(['success' => true]);
            break;

        case 'duplicate':
            $id = (int) ($_POST['id'] ?? 0);
            if ($id <= 0) {
                echo json_encode(['success' => false, 'message' => 'Невалиден ID.']);
                exit;
            }
            $stmt = $pdo->prepare('SELECT * FROM documents WHERE id = ? AND company_id = ?');
            $stmt->execute([$id, $companyId]);
            $src = $stmt->fetch();
            if (!$src) {
                echo json_encode(['success' => false, 'message' => 'Документот не е пронајден.']);
                exit;
            }

            $newName = $src['name'] . ' (копија)';
            $stmt = $pdo->prepare('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM documents WHERE template_id = ? AND company_id = ?');
            $stmt->execute([(int) $src['template_id'], $companyId]);
            $sortOrder = (int) $stmt->fetchColumn();

            $newFileRel = null;
            $newOrigRel = null;
            if (($src['kind'] ?? 'editor') === 'imported' && !empty($src['file_path'])) {
                // Copy the uploaded .docx on disk so the copy is independent.
                $srcAbs = UPLOADS_DIR . '/' . $src['file_path'];
                $dir    = UPLOADS_DIR . '/imported/' . $companyId;
                if (!is_dir($dir) && !@mkdir($dir, 0775, true)) {
                    echo json_encode(['success' => false, 'message' => 'Не може да се креира папка.']);
                    exit;
                }
                $uid        = bin2hex(random_bytes(8));
                $newFileRel = 'imported/' . $companyId . '/' . $uid . '.docx';
                if (!is_file($srcAbs) || !@copy($srcAbs, UPLOADS_DIR . '/' . $newFileRel)) {
                    echo json_encode(['success' => false, 'message' => 'Не може да се копира датотеката.']);
                    exit;
                }
                $newOrigRel = $newFileRel;
            }

            $stmt = $pdo->prepare(
                'INSERT INTO documents
                   (company_id, template_id, created_by, kind, name, is_split, pages, variables,
                    file_path, orig_path, file_ext, sort_order, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())'
            );
            $stmt->execute([
                $companyId, (int) $src['template_id'], $userId ?: null,
                $src['kind'] ?? 'editor', $newName, (int) $src['is_split'],
                $src['pages'] ?? '[]', $src['variables'] ?? '[]',
                $newFileRel, $newOrigRel, $src['file_ext'] ?? null, $sortOrder,
            ]);
            $newId = (int) $pdo->lastInsertId();

            // Return the new row so the client can render it without a reload.
            $stmt = $pdo->prepare('SELECT d.*, u.name AS created_by_name FROM documents d LEFT JOIN users u ON u.id = d.created_by WHERE d.id = ? AND d.company_id = ?');
            $stmt->execute([$newId, $companyId]);
            $row = $stmt->fetch();
            if ($row) {
                $row['pages']     = json_decode($row['pages'], true) ?: [];
                $row['variables'] = json_decode($row['variables'], true) ?: [];
            }
            echo json_encode(['success' => true, 'id' => $newId, 'data' => $row]);
            break;

        case 'master':
            // Stream the unfilled .docx master inline, for the client-side
            // (mammoth.js) live preview. Company-scoped, imported docs only.
            $id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
            if ($id <= 0) {
                echo json_encode(['success' => false, 'message' => 'Невалиден ID.']);
                exit;
            }
            $stmt = $pdo->prepare("SELECT file_path FROM documents WHERE id = ? AND company_id = ? AND kind = 'imported'");
            $stmt->execute([$id, $companyId]);
            $row = $stmt->fetch();
            if (!$row) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Документот не е пронајден.']);
                exit;
            }
            $masterAbs = UPLOADS_DIR . '/' . $row['file_path'];
            if (!is_file($masterAbs)) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Изворната датотека недостасува.']);
                exit;
            }
            header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
            header('Content-Disposition: inline');
            header('Content-Length: ' . filesize($masterAbs));
            header('Cache-Control: private, max-age=0');
            readfile($masterAbs);
            exit;

        default:
            echo json_encode(['success' => false, 'message' => 'Непозната акција.']);
    }

} catch (Throwable $e) {
    // Catch Error too (e.g. "Class ZipArchive not found" when the zip extension
    // is missing), so the client gets a real message instead of an empty 500.
    echo json_encode(['success' => false, 'message' => 'Серверска грешка: ' . $e->getMessage()]);
}

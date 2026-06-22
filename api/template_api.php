<?php

define('FAKTA_API', true);
require_once __DIR__ . '/../includes/auth.php';

require_login();

header('Content-Type: application/json; charset=utf-8');

$pdo = $GLOBALS['fakta_db']->getConnection();
$companyId = current_company_id();
$userId    = (int) (current_user()['id'] ?? 0);
$isPraktikant = current_role() === 'praktikant';

$action = $_POST['action'] ?? $_GET['action'] ?? '';

/** True when the folder id exists and belongs to this company. */
function folder_owned(PDO $pdo, int $folderId, int $companyId): bool
{
    if ($folderId <= 0) {
        return false;
    }
    $stmt = $pdo->prepare('SELECT 1 FROM template_folders WHERE id = ? AND company_id = ?');
    $stmt->execute([$folderId, $companyId]);
    return (bool) $stmt->fetchColumn();
}

/**
 * Praktikant may only edit/delete folders they created. Echoes a 403 JSON and
 * returns false when the current user may not manage $folderId.
 */
function can_manage_folder(PDO $pdo, int $folderId, int $companyId, int $userId, bool $isPraktikant): bool
{
    if (!$isPraktikant) {
        return true;
    }
    $stmt = $pdo->prepare('SELECT created_by FROM template_folders WHERE id = ? AND company_id = ?');
    $stmt->execute([$folderId, $companyId]);
    $row = $stmt->fetch();
    if ($row && $row['created_by'] !== null && (int) $row['created_by'] === $userId && $userId > 0) {
        return true;
    }
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Можете да управувате само со папките што вие сте ги креирале.']);
    return false;
}

/** created_by of a template within this company, or null if it doesn't exist. */
function template_owner(PDO $pdo, int $templateId, int $companyId): ?int
{
    $stmt = $pdo->prepare('SELECT created_by FROM templates WHERE id = ? AND company_id = ?');
    $stmt->execute([$templateId, $companyId]);
    $row = $stmt->fetch();
    if (!$row) {
        return 0; // exists-check fails elsewhere; 0 = "not yours"
    }
    return $row['created_by'] !== null ? (int) $row['created_by'] : null;
}

/**
 * Praktikant may only edit/delete templates they created. Echoes a 403 JSON
 * and returns false when the current user is not allowed to manage $templateId.
 */
function can_manage_template(PDO $pdo, int $templateId, int $companyId, int $userId, bool $isPraktikant): bool
{
    if (!$isPraktikant) {
        return true;
    }
    $owner = template_owner($pdo, $templateId, $companyId);
    if ($owner === $userId && $userId > 0) {
        return true;
    }
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Можете да управувате само со шаблоните што вие сте ги креирале.']);
    return false;
}

try {
    switch ($action) {

        case 'create':
            $name        = trim($_POST['name'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $color       = trim($_POST['color'] ?? '');
            $folderId    = (int) ($_POST['folder_id'] ?? 0);
            if ($name === '') {
                echo json_encode(['success' => false, 'message' => 'Името е задолжително.']);
                exit;
            }
            // Only accept a folder that belongs to this company.
            $folderId = folder_owned($pdo, $folderId, $companyId) ? $folderId : null;
            $stmt = $pdo->prepare('INSERT INTO templates (company_id, created_by, folder_id, name, description, color, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())');
            $stmt->execute([$companyId, $userId ?: null, $folderId, $name, $description !== '' ? $description : null, $color !== '' ? $color : null]);
            $id = (int) $pdo->lastInsertId();
            fakta_audit('template.create', 'template', $id, $name);
            echo json_encode(['success' => true, 'id' => $id]);
            break;

        // ── Folders ─────────────────────────────────────────────────────────
        case 'folder_create':
            $name  = trim($_POST['name'] ?? '');
            $color = trim($_POST['color'] ?? '');
            if ($name === '') {
                echo json_encode(['success' => false, 'message' => 'Името е задолжително.']);
                exit;
            }
            $stmt = $pdo->prepare('INSERT INTO template_folders (company_id, created_by, name, color, created_at, updated_at) VALUES (?, ?, ?, ?, NOW(), NOW())');
            $stmt->execute([$companyId, $userId ?: null, $name, $color !== '' ? $color : null]);
            $folderNewId = (int) $pdo->lastInsertId();
            fakta_audit('folder.create', 'folder', $folderNewId, $name);
            echo json_encode(['success' => true, 'id' => $folderNewId]);
            break;

        case 'folder_update':
            $id    = (int) ($_POST['id'] ?? 0);
            $name  = trim($_POST['name'] ?? '');
            if ($id <= 0 || $name === '') {
                echo json_encode(['success' => false, 'message' => 'Невалидни параметри.']);
                exit;
            }
            if (!can_manage_folder($pdo, $id, $companyId, $userId, $isPraktikant)) {
                exit;
            }
            if (array_key_exists('color', $_POST)) {
                $color = trim($_POST['color']);
                $stmt = $pdo->prepare('UPDATE template_folders SET name = ?, color = ?, updated_at = NOW() WHERE id = ? AND company_id = ?');
                $stmt->execute([$name, $color !== '' ? $color : null, $id, $companyId]);
            } else {
                $stmt = $pdo->prepare('UPDATE template_folders SET name = ?, updated_at = NOW() WHERE id = ? AND company_id = ?');
                $stmt->execute([$name, $id, $companyId]);
            }
            echo json_encode(['success' => true]);
            break;

        case 'folder_delete':
            // Hard-delete the folder AND everything inside it: every template in
            // the folder, all their documents, and any imported files on disk.
            $id = (int) ($_POST['id'] ?? 0);
            if ($id <= 0) {
                echo json_encode(['success' => false, 'message' => 'Невалиден ID.']);
                exit;
            }
            if (!can_manage_folder($pdo, $id, $companyId, $userId, $isPraktikant)) {
                exit;
            }

            // Templates living in this folder.
            $tstmt = $pdo->prepare('SELECT id FROM templates WHERE folder_id = ? AND company_id = ?');
            $tstmt->execute([$id, $companyId]);
            $tplIds = $tstmt->fetchAll(PDO::FETCH_COLUMN);

            if ($tplIds) {
                $place = implode(',', array_fill(0, count($tplIds), '?'));

                // Unlink uploaded files for any imported documents first.
                $dstmt = $pdo->prepare("SELECT file_path, orig_path FROM documents WHERE company_id = ? AND template_id IN ($place)");
                $dstmt->execute(array_merge([$companyId], $tplIds));
                foreach ($dstmt->fetchAll() as $drow) {
                    foreach ([$drow['file_path'] ?? null, $drow['orig_path'] ?? null] as $rel) {
                        if ($rel) {
                            @unlink(UPLOADS_DIR . '/' . $rel);
                        }
                    }
                }

                $del = $pdo->prepare("DELETE FROM documents WHERE company_id = ? AND template_id IN ($place)");
                $del->execute(array_merge([$companyId], $tplIds));
                $del = $pdo->prepare("DELETE FROM templates WHERE company_id = ? AND id IN ($place)");
                $del->execute(array_merge([$companyId], $tplIds));
            }

            $stmt = $pdo->prepare('DELETE FROM template_folders WHERE id = ? AND company_id = ?');
            $stmt->execute([$id, $companyId]);
            fakta_audit('folder.delete', 'folder', $id, count($tplIds) . ' шаблони избришани');
            echo json_encode(['success' => true]);
            break;

        case 'move':
            // Assign a template to a folder (or to root when folder_id = 0).
            $id       = (int) ($_POST['id'] ?? 0);
            $folderId = (int) ($_POST['folder_id'] ?? 0);
            if ($id <= 0) {
                echo json_encode(['success' => false, 'message' => 'Невалиден ID.']);
                exit;
            }
            // Praktikant may not move templates at all.
            if ($isPraktikant) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Немате дозвола да преместувате шаблони.']);
                exit;
            }
            $folderId = folder_owned($pdo, $folderId, $companyId) ? $folderId : null;
            $stmt = $pdo->prepare('UPDATE templates SET folder_id = ?, updated_at = NOW() WHERE id = ? AND company_id = ?');
            $stmt->execute([$folderId, $id, $companyId]);
            echo json_encode(['success' => true]);
            break;

        case 'update':
            $id          = (int) ($_POST['id'] ?? 0);
            $name        = trim($_POST['name'] ?? '');
            $description = trim($_POST['description'] ?? '');
            if ($id <= 0 || $name === '') {
                echo json_encode(['success' => false, 'message' => 'Невалидни параметри.']);
                exit;
            }
            if (!can_manage_template($pdo, $id, $companyId, $userId, $isPraktikant)) {
                exit;
            }
            // Update the colour only when it is part of the request, so a plain
            // name/description edit doesn't wipe an existing colour.
            if (array_key_exists('color', $_POST)) {
                $color = trim($_POST['color']);
                $stmt = $pdo->prepare('UPDATE templates SET name = ?, description = ?, color = ?, updated_at = NOW() WHERE id = ? AND company_id = ?');
                $stmt->execute([$name, $description !== '' ? $description : null, $color !== '' ? $color : null, $id, $companyId]);
            } else {
                $stmt = $pdo->prepare('UPDATE templates SET name = ?, description = ?, updated_at = NOW() WHERE id = ? AND company_id = ?');
                $stmt->execute([$name, $description !== '' ? $description : null, $id, $companyId]);
            }
            fakta_audit('template.update', 'template', $id, $name);
            echo json_encode(['success' => true]);
            break;

        case 'list':
            $stmt = $pdo->prepare(
                'SELECT t.id, t.name, t.description, t.color, t.folder_id, t.created_by, t.created_at, COUNT(d.id) AS doc_count
                 FROM templates t
                 LEFT JOIN documents d ON d.template_id = t.id
                 WHERE t.company_id = ?
                 GROUP BY t.id
                 ORDER BY t.created_at DESC'
            );
            $stmt->execute([$companyId]);
            $data = $stmt->fetchAll();

            // Attach the list of document names belonging to each template.
            $docStmt = $pdo->prepare('SELECT template_id, name FROM documents WHERE company_id = ? ORDER BY sort_order ASC, id ASC');
            $docStmt->execute([$companyId]);
            $docsByTemplate = [];
            foreach ($docStmt->fetchAll() as $row) {
                $docsByTemplate[(int) $row['template_id']][] = $row['name'];
            }
            $folderCounts = [];
            foreach ($data as &$tpl) {
                $tpl['documents'] = $docsByTemplate[(int) $tpl['id']] ?? [];
                $tpl['folder_id'] = $tpl['folder_id'] !== null ? (int) $tpl['folder_id'] : null;
                $tpl['created_by'] = $tpl['created_by'] !== null ? (int) $tpl['created_by'] : null;
                if ($tpl['folder_id'] !== null) {
                    $folderCounts[$tpl['folder_id']] = ($folderCounts[$tpl['folder_id']] ?? 0) + 1;
                }
            }
            unset($tpl);

            // Folders with how many templates each holds.
            $fStmt = $pdo->prepare('SELECT id, name, color, created_by, created_at FROM template_folders WHERE company_id = ? ORDER BY name ASC');
            $fStmt->execute([$companyId]);
            $folders = $fStmt->fetchAll();
            foreach ($folders as &$f) {
                $f['id'] = (int) $f['id'];
                $f['created_by'] = $f['created_by'] !== null ? (int) $f['created_by'] : null;
                $f['tpl_count'] = $folderCounts[$f['id']] ?? 0;
            }
            unset($f);

            echo json_encode(['success' => true, 'data' => $data, 'folders' => $folders]);
            break;

        case 'delete':
            $id = (int) ($_POST['id'] ?? 0);
            if ($id <= 0) {
                echo json_encode(['success' => false, 'message' => 'Невалиден ID.']);
                exit;
            }
            if (!can_manage_template($pdo, $id, $companyId, $userId, $isPraktikant)) {
                exit;
            }
            $stmt = $pdo->prepare('DELETE FROM templates WHERE id = ? AND company_id = ?');
            $stmt->execute([$id, $companyId]);
            fakta_audit('template.delete', 'template', $id);
            echo json_encode(['success' => true]);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Непозната акција.']);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Серверска грешка: ' . $e->getMessage()]);
}

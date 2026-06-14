<?php

define('FAKTA_API', true);
require_once __DIR__ . '/../includes/auth.php';

require_login();

header('Content-Type: application/json; charset=utf-8');

$pdo = $GLOBALS['fakta_db']->getConnection();
$companyId = current_company_id();

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
            $stmt = $pdo->prepare('INSERT INTO templates (company_id, folder_id, name, description, color, created_at, updated_at) VALUES (?, ?, ?, ?, ?, NOW(), NOW())');
            $stmt->execute([$companyId, $folderId, $name, $description !== '' ? $description : null, $color !== '' ? $color : null]);
            $id = (int) $pdo->lastInsertId();
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
            $stmt = $pdo->prepare('INSERT INTO template_folders (company_id, name, color, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())');
            $stmt->execute([$companyId, $name, $color !== '' ? $color : null]);
            echo json_encode(['success' => true, 'id' => (int) $pdo->lastInsertId()]);
            break;

        case 'folder_update':
            $id    = (int) ($_POST['id'] ?? 0);
            $name  = trim($_POST['name'] ?? '');
            if ($id <= 0 || $name === '') {
                echo json_encode(['success' => false, 'message' => 'Невалидни параметри.']);
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
            // Ungroup the templates (keep them), then drop the folder.
            $id = (int) ($_POST['id'] ?? 0);
            if ($id <= 0) {
                echo json_encode(['success' => false, 'message' => 'Невалиден ID.']);
                exit;
            }
            $stmt = $pdo->prepare('UPDATE templates SET folder_id = NULL WHERE folder_id = ? AND company_id = ?');
            $stmt->execute([$id, $companyId]);
            $stmt = $pdo->prepare('DELETE FROM template_folders WHERE id = ? AND company_id = ?');
            $stmt->execute([$id, $companyId]);
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
            echo json_encode(['success' => true]);
            break;

        case 'list':
            $stmt = $pdo->prepare(
                'SELECT t.id, t.name, t.description, t.color, t.folder_id, t.created_at, COUNT(d.id) AS doc_count
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
                if ($tpl['folder_id'] !== null) {
                    $folderCounts[$tpl['folder_id']] = ($folderCounts[$tpl['folder_id']] ?? 0) + 1;
                }
            }
            unset($tpl);

            // Folders with how many templates each holds.
            $fStmt = $pdo->prepare('SELECT id, name, color, created_at FROM template_folders WHERE company_id = ? ORDER BY name ASC');
            $fStmt->execute([$companyId]);
            $folders = $fStmt->fetchAll();
            foreach ($folders as &$f) {
                $f['id'] = (int) $f['id'];
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
            $stmt = $pdo->prepare('DELETE FROM templates WHERE id = ? AND company_id = ?');
            $stmt->execute([$id, $companyId]);
            echo json_encode(['success' => true]);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Непозната акција.']);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Серверска грешка: ' . $e->getMessage()]);
}

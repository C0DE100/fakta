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
            $stmt = $pdo->prepare('DELETE FROM documents WHERE id = ? AND company_id = ?');
            $stmt->execute([$id, $companyId]);
            echo json_encode(['success' => true]);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Непозната акција.']);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Серверска грешка: ' . $e->getMessage()]);
}

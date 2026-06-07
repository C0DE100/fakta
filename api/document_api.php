<?php

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../classes/Database.php';

header('Content-Type: application/json; charset=utf-8');

$db  = new Database(DB_HOST, DB_NAME, DB_USER, DB_PASS);
$pdo = $db->getConnection();

$action = $_POST['action'] ?? $_GET['action'] ?? '';

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

            $stmt = $pdo->prepare('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM documents WHERE template_id = ?');
            $stmt->execute([$templateId]);
            $sortOrder = (int) $stmt->fetchColumn();

            $stmt = $pdo->prepare(
                'INSERT INTO documents (template_id, name, is_split, pages, variables, sort_order, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())'
            );
            $stmt->execute([$templateId, $name, $isSplit ? 1 : 0, $pages, $variables, $sortOrder]);
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

            $stmt = $pdo->prepare(
                'UPDATE documents SET name = ?, is_split = ?, pages = ?, variables = ?, updated_at = NOW() WHERE id = ?'
            );
            $stmt->execute([$name, $isSplit ? 1 : 0, $pages, $variables, $id]);
            echo json_encode(['success' => true]);
            break;

        case 'get':
            $id = (int) ($_GET['id'] ?? 0);
            if ($id <= 0) {
                echo json_encode(['success' => false, 'message' => 'Невалиден ID.']);
                exit;
            }
            $stmt = $pdo->prepare('SELECT * FROM documents WHERE id = ?');
            $stmt->execute([$id]);
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
            $stmt = $pdo->prepare('SELECT * FROM documents WHERE template_id = ? ORDER BY sort_order ASC');
            $stmt->execute([$templateId]);
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
            $stmt = $pdo->prepare('DELETE FROM documents WHERE id = ?');
            $stmt->execute([$id]);
            echo json_encode(['success' => true]);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Непозната акција.']);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Серверска грешка: ' . $e->getMessage()]);
}

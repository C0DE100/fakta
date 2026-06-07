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
            $name        = trim($_POST['name'] ?? '');
            $description = trim($_POST['description'] ?? '');
            if ($name === '') {
                echo json_encode(['success' => false, 'message' => 'Името е задолжително.']);
                exit;
            }
            $stmt = $pdo->prepare('INSERT INTO templates (name, description, created_at, updated_at) VALUES (?, ?, NOW(), NOW())');
            $stmt->execute([$name, $description !== '' ? $description : null]);
            $id = (int) $pdo->lastInsertId();
            echo json_encode(['success' => true, 'id' => $id]);
            break;

        case 'update':
            $id          = (int) ($_POST['id'] ?? 0);
            $name        = trim($_POST['name'] ?? '');
            $description = trim($_POST['description'] ?? '');
            if ($id <= 0 || $name === '') {
                echo json_encode(['success' => false, 'message' => 'Невалидни параметри.']);
                exit;
            }
            $stmt = $pdo->prepare('UPDATE templates SET name = ?, description = ?, updated_at = NOW() WHERE id = ?');
            $stmt->execute([$name, $description !== '' ? $description : null, $id]);
            echo json_encode(['success' => true]);
            break;

        case 'list':
            $stmt = $pdo->query(
                'SELECT t.id, t.name, t.description, t.created_at, COUNT(d.id) AS doc_count
                 FROM templates t
                 LEFT JOIN documents d ON d.template_id = t.id
                 GROUP BY t.id
                 ORDER BY t.created_at DESC'
            );
            $data = $stmt->fetchAll();

            // Attach the list of document names belonging to each template.
            $docStmt = $pdo->query('SELECT template_id, name FROM documents ORDER BY sort_order ASC, id ASC');
            $docsByTemplate = [];
            foreach ($docStmt->fetchAll() as $row) {
                $docsByTemplate[(int) $row['template_id']][] = $row['name'];
            }
            foreach ($data as &$tpl) {
                $tpl['documents'] = $docsByTemplate[(int) $tpl['id']] ?? [];
            }
            unset($tpl);

            echo json_encode(['success' => true, 'data' => $data]);
            break;

        case 'delete':
            $id = (int) ($_POST['id'] ?? 0);
            if ($id <= 0) {
                echo json_encode(['success' => false, 'message' => 'Невалиден ID.']);
                exit;
            }
            $stmt = $pdo->prepare('DELETE FROM templates WHERE id = ?');
            $stmt->execute([$id]);
            echo json_encode(['success' => true]);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Непозната акција.']);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Серверска грешка: ' . $e->getMessage()]);
}

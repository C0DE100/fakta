<?php

define('FAKTA_API', true);
require_once __DIR__ . '/../includes/auth.php';

require_login();

header('Content-Type: application/json; charset=utf-8');

$pdo = $GLOBALS['fakta_db']->getConnection();
$companyId = current_company_id();

$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {

        case 'create':
            $name        = trim($_POST['name'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $color       = trim($_POST['color'] ?? '');
            if ($name === '') {
                echo json_encode(['success' => false, 'message' => 'Името е задолжително.']);
                exit;
            }
            $stmt = $pdo->prepare('INSERT INTO templates (company_id, name, description, color, created_at, updated_at) VALUES (?, ?, ?, ?, NOW(), NOW())');
            $stmt->execute([$companyId, $name, $description !== '' ? $description : null, $color !== '' ? $color : null]);
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
                'SELECT t.id, t.name, t.description, t.color, t.created_at, COUNT(d.id) AS doc_count
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

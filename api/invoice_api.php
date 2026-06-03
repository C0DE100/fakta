<?php

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/Invoice.php';

header('Content-Type: application/json; charset=utf-8');

$db      = new Database(DB_HOST, DB_NAME, DB_USER, DB_PASS);
$invoice = new Invoice($db);

$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {

        case 'get_list':
            $search   = trim($_GET['search']     ?? '');
            $month    = trim($_GET['month']      ?? '');
            $clientId = (int) ($_GET['client_id'] ?? 0);
            $page     = max(1, (int) ($_GET['page'] ?? 1));

            $result = $invoice->getList($search, $month, $clientId, $page);
            echo json_encode(['success' => true] + $result);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Непозната акција.']);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Серверска грешка: ' . $e->getMessage()]);
}

<?php

define('FAKTA_API', true);
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../classes/Invoice.php';

// Invoices are admin-only for now (employees can't see them).
require_role('admin');

header('Content-Type: application/json; charset=utf-8');

$db      = $GLOBALS['fakta_db'];
$invoice = new Invoice($db);
$companyId = current_company_id();

$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {

        case 'get_list':
            $search   = trim($_GET['search']     ?? '');
            $month    = trim($_GET['month']      ?? '');
            $clientId = (int) ($_GET['client_id'] ?? 0);
            $page     = max(1, (int) ($_GET['page'] ?? 1));

            $result = $invoice->getList($companyId, $search, $month, $clientId, $page);
            echo json_encode(['success' => true] + $result);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Непозната акција.']);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Серверска грешка: ' . $e->getMessage()]);
}

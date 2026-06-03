<?php

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/Encryption.php';
require_once __DIR__ . '/../classes/Client.php';

header('Content-Type: application/json; charset=utf-8');

$db = new Database(DB_HOST, DB_NAME, DB_USER, DB_PASS);
$enc = new Encryption(ENCRYPTION_KEY);
$client = new Client($db, $enc);

$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {

        case 'create_company':
            $companyName  = trim($_POST['company_name'] ?? '');
            $headquarters = trim($_POST['headquarters'] ?? '');
            $embs         = trim($_POST['embs'] ?? '');
            $edb          = trim($_POST['edb'] ?? '');
            $manager      = trim($_POST['manager'] ?? '');

            if (empty($companyName) || empty($headquarters) || empty($embs) || empty($edb) || empty($manager)) {
                echo json_encode(['success' => false, 'message' => 'Сите полиња се задолжителни.']);
                exit;
            }

            $id = $client->createCompany($companyName, $headquarters, $embs, $edb, $manager);
            echo json_encode(['success' => true, 'message' => 'Клиентот е успешно креиран.', 'id' => $id]);
            break;

        case 'create_individual':
            $fullName      = trim($_POST['full_name'] ?? '');
            $address       = trim($_POST['address'] ?? '');
            $embg          = trim($_POST['embg'] ?? '');
            $idCardNumber  = trim($_POST['id_card_number'] ?? '');

            if (empty($fullName) || empty($address) || empty($embg) || empty($idCardNumber)) {
                echo json_encode(['success' => false, 'message' => 'Сите полиња се задолжителни.']);
                exit;
            }

            $id = $client->createIndividual($fullName, $address, $embg, $idCardNumber);
            echo json_encode(['success' => true, 'message' => 'Клиентот е успешно креиран.', 'id' => $id]);
            break;

        case 'get_all':
            $clients = $client->getAll();
            echo json_encode(['success' => true, 'data' => $clients]);
            break;

        case 'get_one':
            $id = (int) ($_GET['id'] ?? 0);
            if ($id <= 0) {
                echo json_encode(['success' => false, 'message' => 'Невалиден ID.']);
                exit;
            }
            $data = $client->getById($id);
            echo json_encode(['success' => true, 'data' => $data]);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Непозната акција.']);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Серверска грешка: ' . $e->getMessage()]);
}
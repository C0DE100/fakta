<?php

define('FAKTA_API', true);
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../classes/Encryption.php';
require_once __DIR__ . '/../classes/Client.php';

require_login();

header('Content-Type: application/json; charset=utf-8');

$db = $GLOBALS['fakta_db'];
$enc = new Encryption(ENCRYPTION_KEY);
$client = new Client($db, $enc);
$companyId = current_company_id();

$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {

        case 'create_company':
            $companyName  = trim($_POST['company_name'] ?? '');
            $headquarters = trim($_POST['headquarters'] ?? '');
            $embs         = trim($_POST['embs'] ?? '');
            $edb          = trim($_POST['edb'] ?? '');
            $manager      = trim($_POST['manager'] ?? '');
            $email        = trim($_POST['email'] ?? '');
            $phone        = trim($_POST['phone'] ?? '');

            if (empty($companyName) || empty($headquarters) || empty($embs) || empty($edb) || empty($manager)) {
                echo json_encode(['success' => false, 'message' => 'Сите полиња се задолжителни.']);
                exit;
            }

            $id = $client->createCompany($companyId, $companyName, $headquarters, $embs, $edb, $manager, $email, $phone, current_user()['id'] ?? null);
            echo json_encode(['success' => true, 'message' => 'Клиентот е успешно креиран.', 'id' => $id]);
            break;

        case 'create_individual':
            $fullName      = trim($_POST['full_name'] ?? '');
            $address       = trim($_POST['address'] ?? '');
            $embg          = trim($_POST['embg'] ?? '');
            $idCardNumber  = trim($_POST['id_card_number'] ?? '');
            $email         = trim($_POST['email'] ?? '');
            $phone         = trim($_POST['phone'] ?? '');

            if (empty($fullName) || empty($address) || empty($embg) || empty($idCardNumber)) {
                echo json_encode(['success' => false, 'message' => 'Сите полиња се задолжителни.']);
                exit;
            }

            $id = $client->createIndividual($companyId, $fullName, $address, $embg, $idCardNumber, $email, $phone, current_user()['id'] ?? null);
            echo json_encode(['success' => true, 'message' => 'Клиентот е успешно креиран.', 'id' => $id]);
            break;

        case 'get_all':
            $clients = $client->getAll($companyId);
            echo json_encode(['success' => true, 'data' => $clients]);
            break;

        case 'get_one':
            $id = (int) ($_GET['id'] ?? 0);
            if ($id <= 0) {
                echo json_encode(['success' => false, 'message' => 'Невалиден ID.']);
                exit;
            }
            $data = $client->getById($id, $companyId);
            echo json_encode(['success' => true, 'data' => $data]);
            break;

        case 'update_company':
            $id           = (int) ($_POST['id'] ?? 0);
            $companyName  = trim($_POST['company_name'] ?? '');
            $headquarters = trim($_POST['headquarters'] ?? '');
            $embs         = trim($_POST['embs'] ?? '');
            $edb          = trim($_POST['edb'] ?? '');
            $manager      = trim($_POST['manager'] ?? '');
            $email        = trim($_POST['email'] ?? '');
            $phone        = trim($_POST['phone'] ?? '');

            if ($id <= 0 || empty($companyName) || empty($headquarters) || empty($embs) || empty($edb) || empty($manager)) {
                echo json_encode(['success' => false, 'message' => 'Сите полиња се задолжителни.']);
                exit;
            }

            $client->updateCompany($id, $companyId, $companyName, $headquarters, $embs, $edb, $manager, $email, $phone);
            echo json_encode(['success' => true, 'message' => 'Клиентот е успешно ажуриран.']);
            break;

        case 'update_individual':
            $id            = (int) ($_POST['id'] ?? 0);
            $fullName      = trim($_POST['full_name'] ?? '');
            $address       = trim($_POST['address'] ?? '');
            $embg          = trim($_POST['embg'] ?? '');
            $idCardNumber  = trim($_POST['id_card_number'] ?? '');
            $email         = trim($_POST['email'] ?? '');
            $phone         = trim($_POST['phone'] ?? '');

            if ($id <= 0 || empty($fullName) || empty($address) || empty($embg) || empty($idCardNumber)) {
                echo json_encode(['success' => false, 'message' => 'Сите полиња се задолжителни.']);
                exit;
            }

            $client->updateIndividual($id, $companyId, $fullName, $address, $embg, $idCardNumber, $email, $phone);
            echo json_encode(['success' => true, 'message' => 'Клиентот е успешно ажуриран.']);
            break;

        case 'delete':
            $id = (int) ($_POST['id'] ?? 0);
            if ($id <= 0) {
                echo json_encode(['success' => false, 'message' => 'Невалиден ID.']);
                exit;
            }
            $ok = $client->softDelete($id, $companyId);
            echo json_encode(['success' => $ok, 'message' => $ok ? 'Клиентот е избришан.' : 'Клиентот не постои.']);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Непозната акција.']);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Серверска грешка: ' . $e->getMessage()]);
}
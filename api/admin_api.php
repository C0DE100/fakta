<?php

define('FAKTA_API', true);
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../classes/Company.php';
require_once __DIR__ . '/../classes/User.php';

require_role('super_admin');

header('Content-Type: application/json; charset=utf-8');

/** @var Database $fakta_db */
$db      = $GLOBALS['fakta_db'];
$company = new Company($db);
$user    = new User($db);

$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {

        case 'get_stats':
            echo json_encode(['success' => true, 'data' => [
                'companies' => $company->countAll(),
                'users'     => $user->countUsers(),
                'admins'    => $user->countUsers('admin'),
            ]]);
            break;

        case 'create_company':
            $name    = trim($_POST['name'] ?? '');
            $email   = trim($_POST['email'] ?? '');
            $address = trim($_POST['address'] ?? '');
            $phone   = trim($_POST['phone'] ?? '');

            if ($name === '') {
                echo json_encode(['success' => false, 'message' => 'Името на компанијата е задолжително.']);
                exit;
            }

            $id = $company->create($name, $email, $address, $phone);
            echo json_encode(['success' => true, 'message' => 'Компанијата е креирана.', 'id' => $id]);
            break;

        case 'list_companies':
            $search = trim($_GET['search'] ?? '');
            $page   = max(1, (int) ($_GET['page'] ?? 1));
            $result = $company->getPaged($search, $page);
            echo json_encode(['success' => true] + $result);
            break;

        case 'update_company':
            $id      = (int) ($_POST['id'] ?? 0);
            $name    = trim($_POST['name'] ?? '');
            $email   = trim($_POST['email'] ?? '');
            $address = trim($_POST['address'] ?? '');
            $phone   = trim($_POST['phone'] ?? '');

            if ($id <= 0 || $name === '') {
                echo json_encode(['success' => false, 'message' => 'Името на компанијата е задолжително.']);
                exit;
            }
            if (!$company->getById($id)) {
                echo json_encode(['success' => false, 'message' => 'Компанијата не е пронајдена.']);
                exit;
            }

            $company->update($id, $name, $email, $address, $phone);
            echo json_encode(['success' => true, 'message' => 'Промените се зачувани.']);
            break;

        case 'delete_company':
            $id = (int) ($_POST['id'] ?? 0);
            if ($id <= 0 || !$company->getById($id)) {
                echo json_encode(['success' => false, 'message' => 'Компанијата не е пронајдена.']);
                exit;
            }
            $company->delete($id);
            echo json_encode(['success' => true, 'message' => 'Компанијата и сите нејзини податоци се избришани.']);
            break;

        case 'list_company_users':
            $companyId = (int) ($_GET['company_id'] ?? 0);
            $search    = trim($_GET['search'] ?? '');
            $page      = max(1, (int) ($_GET['page'] ?? 1));
            if ($companyId <= 0 || !$company->getById($companyId)) {
                echo json_encode(['success' => false, 'message' => 'Компанијата не е пронајдена.']);
                exit;
            }
            $result = $user->getByCompanyPaged($companyId, $search, $page);
            echo json_encode(['success' => true] + $result);
            break;

        case 'create_user':
            $companyId = (int) ($_POST['company_id'] ?? 0);
            $name      = trim($_POST['name'] ?? '');
            $email     = trim($_POST['email'] ?? '');
            $password  = $_POST['password'] ?? '';
            $role      = trim($_POST['role'] ?? '');

            if ($companyId <= 0 || $name === '' || $email === '' || $password === '') {
                echo json_encode(['success' => false, 'message' => 'Сите полиња се задолжителни.']);
                exit;
            }
            if (!$company->getById($companyId)) {
                echo json_encode(['success' => false, 'message' => 'Непостоечка компанија.']);
                exit;
            }

            $id = $user->create($companyId, $name, $email, $password, $role);
            echo json_encode(['success' => true, 'message' => 'Корисникот е креиран.', 'id' => $id]);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Непозната акција.']);
    }

} catch (Throwable $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

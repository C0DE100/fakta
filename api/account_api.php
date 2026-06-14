<?php

define('FAKTA_API', true);
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../classes/User.php';

require_login();

header('Content-Type: application/json; charset=utf-8');

/** @var Database $fakta_db */
$db        = $GLOBALS['fakta_db'];
$users     = new User($db);
$me        = current_user();
$companyId = current_company_id();

$action = $_POST['action'] ?? $_GET['action'] ?? '';

/** Admins of a company may manage that company's users. */
function require_company_admin(): void
{
    if (current_role() !== 'admin') {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Немате дозвола за оваа акција.']);
        exit;
    }
}

try {
    switch ($action) {

        case 'update_profile':
            $name  = trim($_POST['name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            if ($name === '' || $email === '') {
                echo json_encode(['success' => false, 'message' => 'Името и е-поштата се задолжителни.']);
                exit;
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                echo json_encode(['success' => false, 'message' => 'Невалидна е-пошта.']);
                exit;
            }

            $users->updateProfile((int) $me['id'], $name, $email, $phone);
            // Keep the session (and therefore the nav) in sync.
            $_SESSION['user']['name']  = $name;
            $_SESSION['user']['email'] = $email;
            $_SESSION['user']['phone'] = $phone !== '' ? $phone : null;
            echo json_encode(['success' => true, 'message' => 'Профилот е ажуриран.', 'name' => $name, 'email' => $email, 'phone' => $phone]);
            break;

        case 'change_password':
            $current = $_POST['current_password'] ?? '';
            $new     = $_POST['new_password'] ?? '';
            $confirm = $_POST['confirm_password'] ?? '';

            if ($current === '' || $new === '' || $confirm === '') {
                echo json_encode(['success' => false, 'message' => 'Сите полиња се задолжителни.']);
                exit;
            }
            if (strlen($new) < 8) {
                echo json_encode(['success' => false, 'message' => 'Новата лозинка мора да има барем 8 знаци.']);
                exit;
            }
            if ($new !== $confirm) {
                echo json_encode(['success' => false, 'message' => 'Двете лозинки не се совпаѓаат.']);
                exit;
            }

            $users->changePassword((int) $me['id'], $current, $new);
            echo json_encode(['success' => true, 'message' => 'Лозинката е сменета.']);
            break;

        case 'list_users':
            require_company_admin();
            $search = trim($_GET['search'] ?? '');
            $page   = max(1, (int) ($_GET['page'] ?? 1));
            $result = $users->getByCompanyPaged($companyId, $search, $page);
            echo json_encode(['success' => true] + $result);
            break;

        case 'create_user':
            require_company_admin();
            $name     = trim($_POST['name'] ?? '');
            $email    = trim($_POST['email'] ?? '');
            $phone    = trim($_POST['phone'] ?? '');
            $password = $_POST['password'] ?? '';
            $role     = trim($_POST['role'] ?? '');

            if ($name === '' || $email === '' || $password === '' || $role === '') {
                echo json_encode(['success' => false, 'message' => 'Сите полиња се задолжителни.']);
                exit;
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                echo json_encode(['success' => false, 'message' => 'Невалидна е-пошта.']);
                exit;
            }
            if (strlen($password) < 8) {
                echo json_encode(['success' => false, 'message' => 'Лозинката мора да има барем 8 знаци.']);
                exit;
            }
            // Tenant scoping: a company admin can only create users in their own company.
            $id = $users->create($companyId, $name, $email, $password, $role, $phone);
            echo json_encode(['success' => true, 'message' => 'Корисникот е креиран.', 'id' => $id]);
            break;

        case 'update_role':
            require_company_admin();
            $id   = (int) ($_POST['id'] ?? 0);
            $role = trim($_POST['role'] ?? '');
            if ($id <= 0 || $role === '') {
                echo json_encode(['success' => false, 'message' => 'Невалидни параметри.']);
                exit;
            }
            if ($id === (int) $me['id']) {
                echo json_encode(['success' => false, 'message' => 'Не можете да ја смените вашата сопствена улога.']);
                exit;
            }
            $target = $users->getById($id);
            if (!$target || (int) $target['company_id'] !== (int) $companyId) {
                echo json_encode(['success' => false, 'message' => 'Корисникот не е пронајден.']);
                exit;
            }
            $users->updateRole($id, $companyId, $role);
            echo json_encode(['success' => true, 'message' => 'Улогата е сменета.']);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Непозната акција.']);
    }

} catch (Throwable $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

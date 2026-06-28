<?php

define('FAKTA_API', true);
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../classes/Notification.php';

require_login();

header('Content-Type: application/json; charset=utf-8');

$db        = $GLOBALS['fakta_db'];
$notes     = new Notification($db);
$companyId = (int) (current_company_id() ?: 0);
$userId    = (int) (current_user()['id'] ?? 0);

$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {

        // Full feed for the dropdown + unread count.
        case 'list': {
            echo json_encode([
                'success' => true,
                'data'    => $notes->getForUser($companyId, $userId),
                'unread'  => $notes->unreadCount($companyId, $userId),
            ]);
            break;
        }

        // Lightweight poll for the badge.
        case 'unread_count': {
            echo json_encode(['success' => true, 'unread' => $notes->unreadCount($companyId, $userId)]);
            break;
        }

        case 'mark_read': {
            $id = (int) ($_POST['id'] ?? 0);
            $notes->markRead($companyId, $userId, $id);
            echo json_encode(['success' => true, 'unread' => $notes->unreadCount($companyId, $userId)]);
            break;
        }

        case 'mark_all_read': {
            $notes->markAllRead($companyId, $userId);
            echo json_encode(['success' => true, 'unread' => 0]);
            break;
        }

        default:
            echo json_encode(['success' => false, 'message' => 'Непозната акција.']);
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Грешка на серверот.']);
}

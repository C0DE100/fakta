<?php

define('FAKTA_API', true);
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../classes/CaseFile.php';

require_login();

header('Content-Type: application/json; charset=utf-8');

$db        = $GLOBALS['fakta_db'];
$cases     = new CaseFile($db);
$companyId = current_company_id();

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// Praktikant may create and view, but not modify/archive/delete (mirrors clients).
$restricted = ['update', 'archive', 'unarchive', 'delete', 'restore', 'force_delete', 'add_admin_number', 'update_admin_number', 'delete_admin_number'];
if (current_role() === 'praktikant' && in_array($action, $restricted, true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Немате дозвола за оваа акција.']);
    exit;
}

/** Decode a JSON array sent as a POST field; always returns an array. */
function json_field(string $name): array
{
    $raw = $_POST[$name] ?? '';
    if ($raw === '') {
        return [];
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

try {
    switch ($action) {

        case 'create': {
            $data = [
                'basis'          => trim($_POST['basis'] ?? ''),
                'value_amount'   => $_POST['value_amount'] ?? null,
                'value_currency' => $_POST['value_currency'] ?? 'ден',
                'admin_number'   => trim($_POST['admin_number'] ?? ''),
                'admin_note'     => trim($_POST['admin_note'] ?? ''),
                'parties'        => json_field('parties'),
                'assignees'      => json_field('assignees'),
            ];
            if ($data['basis'] === '') {
                echo json_encode(['success' => false, 'message' => 'Основот е задолжителен.']);
                exit;
            }
            $id = $cases->create($companyId, $data, current_user()['id'] ?? null);
            fakta_audit('case.create', 'case', $id, $cases->caseLabel($companyId, $id));
            echo json_encode(['success' => true, 'message' => 'Предметот е успешно креиран.', 'id' => $id]);
            break;
        }

        case 'update': {
            $id = (int) ($_POST['id'] ?? 0);
            if ($id <= 0) {
                echo json_encode(['success' => false, 'message' => 'Невалиден ID.']);
                exit;
            }
            $data = [
                'basis'          => trim($_POST['basis'] ?? ''),
                'value_amount'   => $_POST['value_amount'] ?? null,
                'value_currency' => $_POST['value_currency'] ?? 'ден',
                'parties'        => json_field('parties'),
                'assignees'      => json_field('assignees'),
            ];
            if ($data['basis'] === '') {
                echo json_encode(['success' => false, 'message' => 'Основот е задолжителен.']);
                exit;
            }
            $cases->update($companyId, $id, $data);
            fakta_audit('case.update', 'case', $id, $cases->caseLabel($companyId, $id));
            echo json_encode(['success' => true, 'message' => 'Предметот е успешно ажуриран.']);
            break;
        }

        case 'get_list': {
            $res = $cases->getListPaged($companyId, [
                'search'      => $_GET['search'] ?? '',
                'status'      => $_GET['status'] ?? 'active',
                'assignee_id' => (int) ($_GET['assignee_id'] ?? 0),
                'created_by'  => (int) ($_GET['created_by'] ?? 0),
                'sort'        => $_GET['sort'] ?? 'newest',
                'page'        => (int) ($_GET['page'] ?? 1),
            ]);
            echo json_encode(['success' => true] + $res);
            break;
        }

        case 'get_one': {
            $id = (int) ($_GET['id'] ?? 0);
            $data = $id > 0 ? $cases->getById($companyId, $id) : null;
            if (!$data) {
                echo json_encode(['success' => false, 'message' => 'Предметот не постои.']);
                exit;
            }
            echo json_encode(['success' => true, 'data' => $data]);
            break;
        }

        case 'archive': {
            $id = (int) ($_POST['id'] ?? 0);
            $ok = $id > 0 && $cases->archive($companyId, $id);
            if ($ok) fakta_audit('case.archive', 'case', $id, $cases->caseLabel($companyId, $id));
            echo json_encode(['success' => $ok, 'message' => $ok ? 'Предметот е архивиран.' : 'Предметот не може да се архивира.']);
            break;
        }

        case 'unarchive': {
            $id = (int) ($_POST['id'] ?? 0);
            $ok = $id > 0 && $cases->unarchive($companyId, $id);
            if ($ok) fakta_audit('case.unarchive', 'case', $id, $cases->caseLabel($companyId, $id));
            echo json_encode(['success' => $ok, 'message' => $ok ? 'Предметот е вратен од архива.' : 'Грешка.']);
            break;
        }

        case 'delete': {
            $id = (int) ($_POST['id'] ?? 0);
            $label = $id > 0 ? $cases->caseLabel($companyId, $id) : null;
            $ok = $id > 0 && $cases->softDelete($companyId, $id);
            if ($ok) fakta_audit('case.delete', 'case', $id, $label);
            echo json_encode(['success' => $ok, 'message' => $ok ? 'Предметот е преместен во корпа.' : 'Предметот не постои.']);
            break;
        }

        case 'list_deleted': {
            $cases->purgeOld($companyId, 30);
            echo json_encode(['success' => true, 'data' => $cases->getDeleted($companyId)]);
            break;
        }

        case 'restore': {
            $id = (int) ($_POST['id'] ?? 0);
            $ok = $id > 0 && $cases->restore($companyId, $id);
            if ($ok) fakta_audit('case.restore', 'case', $id, $cases->caseLabel($companyId, $id));
            echo json_encode(['success' => $ok, 'message' => $ok ? 'Предметот е вратен.' : 'Предметот не постои во корпата.']);
            break;
        }

        case 'force_delete': {
            $id = (int) ($_POST['id'] ?? 0);
            $label = $id > 0 ? $cases->caseLabel($companyId, $id) : null;
            $ok = $id > 0 && $cases->forceDelete($companyId, $id);
            if ($ok) fakta_audit('case.purge', 'case', $id, $label);
            echo json_encode(['success' => $ok, 'message' => $ok ? 'Предметот е трајно избришан.' : 'Предметот не постои во корпата.']);
            break;
        }

        case 'add_admin_number': {
            $id   = (int) ($_POST['id'] ?? 0);
            $num  = trim($_POST['admin_number'] ?? '');
            $note = trim($_POST['note'] ?? '');
            if ($id <= 0 || $num === '') {
                echo json_encode(['success' => false, 'message' => 'Внеси административен број.']);
                exit;
            }
            $ok = $cases->addAdminNumber($companyId, $id, $num, $note);
            if ($ok) fakta_audit('case.admin_number', 'case', $id, $cases->caseLabel($companyId, $id) . ' · № ' . $num);
            echo json_encode(['success' => $ok, 'message' => $ok ? 'Административниот број е додаден.' : 'Предметот не постои.']);
            break;
        }

        case 'update_admin_number': {
            $id    = (int) ($_POST['id'] ?? 0);
            $adminId = (int) ($_POST['admin_id'] ?? 0);
            $num   = trim($_POST['admin_number'] ?? '');
            $note  = trim($_POST['note'] ?? '');
            if ($id <= 0 || $adminId <= 0 || $num === '') {
                echo json_encode(['success' => false, 'message' => 'Внеси административен број.']);
                exit;
            }
            $ok = $cases->updateAdminNumber($companyId, $id, $adminId, $num, $note);
            if ($ok) fakta_audit('case.admin_number_edit', 'case', $id, $cases->caseLabel($companyId, $id) . ' · № ' . $num);
            echo json_encode(['success' => $ok, 'message' => $ok ? 'Административниот број е ажуриран.' : 'Записот не постои.']);
            break;
        }

        case 'delete_admin_number': {
            $id      = (int) ($_POST['id'] ?? 0);
            $adminId = (int) ($_POST['admin_id'] ?? 0);
            if ($id <= 0 || $adminId <= 0) {
                echo json_encode(['success' => false, 'message' => 'Невалиден ID.']);
                exit;
            }
            $ok = $cases->deleteAdminNumber($companyId, $id, $adminId);
            if ($ok) fakta_audit('case.admin_number_delete', 'case', $id, $cases->caseLabel($companyId, $id));
            echo json_encode(['success' => $ok, 'message' => $ok ? 'Административниот број е избришан.' : 'Записот не постои.']);
            break;
        }

        case 'suggest_basis': {
            $term = trim($_GET['q'] ?? '');
            echo json_encode(['success' => true, 'data' => $cases->suggestBasis($companyId, $term)]);
            break;
        }

        case 'members': {
            // Employees that a case can be assigned to (зададено на).
            $stmt = $db->prepare(
                "SELECT id, name, role FROM users
                 WHERE company_id = :cid AND role IN ('admin','employee','praktikant')
                 ORDER BY FIELD(role,'admin','employee','praktikant'), name"
            );
            $stmt->execute([':cid' => $companyId]);
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
            break;
        }

        default:
            echo json_encode(['success' => false, 'message' => 'Непозната акција.']);
    }

} catch (InvalidArgumentException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'message' => 'Серверска грешка: ' . $e->getMessage()]);
}

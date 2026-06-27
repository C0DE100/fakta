<?php

define('FAKTA_API', true);
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../classes/CaseFile.php';
require_once __DIR__ . '/../classes/CaseImporter.php';
require_once __DIR__ . '/../classes/CalendarEvent.php';

require_login();

header('Content-Type: application/json; charset=utf-8');

$db        = $GLOBALS['fakta_db'];
$cases     = new CaseFile($db);
$calendar  = new CalendarEvent($db);
$companyId = current_company_id();

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// Praktikant may create and view, but not modify/archive/delete (mirrors clients).
$restricted = ['update', 'archive', 'unarchive', 'delete', 'restore', 'force_delete', 'add_admin_number', 'update_admin_number', 'delete_admin_number'];
if (current_role() === 'praktikant' && in_array($action, $restricted, true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Немате дозвола за оваа акција.']);
    exit;
}

/** Macedonian label for a calendar event kind (for audit details). */
function hearing_kind_label(string $kind): string
{
    return ['hearing' => 'Рочиште', 'trial' => 'Судење', 'meeting' => 'Состанок'][$kind] ?? 'Рочиште';
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
                'basis'           => trim($_POST['basis'] ?? ''),
                'value_amount'    => $_POST['value_amount'] ?? null,
                'value_currency'  => $_POST['value_currency'] ?? 'ден',
                'admin_number'    => trim($_POST['admin_number'] ?? ''),
                'official_person' => trim($_POST['official_person'] ?? ''),
                'parties'         => json_field('parties'),
                'assignees'       => json_field('assignees'),
            ];
            if ($data['basis'] === '') {
                echo json_encode(['success' => false, 'message' => 'Основот е задолжителен.']);
                exit;
            }
            $id = $cases->create($companyId, $data, current_user()['id'] ?? null);
            $note = trim($_POST['note'] ?? '');
            if ($note !== '') {
                $cases->addNote($companyId, $id, current_user()['id'] ?? null, $note);
            }
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
            $result = $id > 0 ? $cases->forceDelete($companyId, $id) : ['ok' => false, 'files' => []];
            $ok = $result['ok'];
            if ($ok) {
                foreach ($result['files'] as $rel) {
                    @unlink(UPLOADS_DIR . '/' . $rel);
                }
                fakta_audit('case.purge', 'case', $id, $label);
            }
            echo json_encode(['success' => $ok, 'message' => $ok ? 'Предметот е трајно избришан.' : 'Предметот не постои во корпата.']);
            break;
        }

        case 'add_admin_number': {
            $id       = (int) ($_POST['id'] ?? 0);
            $num      = trim($_POST['admin_number'] ?? '');
            $official = trim($_POST['official_person'] ?? '');
            if ($id <= 0 || $num === '') {
                echo json_encode(['success' => false, 'message' => 'Внеси административен број.']);
                exit;
            }
            $ok = $cases->addAdminNumber($companyId, $id, $num, $official);
            if ($ok) fakta_audit('case.admin_number', 'case', $id, $cases->caseLabel($companyId, $id) . ' · № ' . $num);
            echo json_encode(['success' => $ok, 'message' => $ok ? 'Административниот број е додаден.' : 'Предметот не постои.']);
            break;
        }

        case 'update_admin_number': {
            $id       = (int) ($_POST['id'] ?? 0);
            $adminId  = (int) ($_POST['admin_id'] ?? 0);
            $num      = trim($_POST['admin_number'] ?? '');
            $official = trim($_POST['official_person'] ?? '');
            if ($id <= 0 || $adminId <= 0 || $num === '') {
                echo json_encode(['success' => false, 'message' => 'Внеси административен број.']);
                exit;
            }
            $ok = $cases->updateAdminNumber($companyId, $id, $adminId, $num, $official);
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

        case 'get_notes': {
            $id = (int) ($_GET['id'] ?? 0);
            echo json_encode(['success' => true, 'data' => $id > 0 ? $cases->getNotes($companyId, $id) : []]);
            break;
        }

        case 'add_note': {
            $id   = (int) ($_POST['id'] ?? 0);
            $body = trim($_POST['body'] ?? '');
            if ($id <= 0 || $body === '') {
                echo json_encode(['success' => false, 'message' => 'Напиши белешка.']);
                exit;
            }
            $type   = trim($_POST['type'] ?? 'general');
            $noteId = $cases->addNote($companyId, $id, current_user()['id'] ?? null, $body, $type);
            if ($noteId) fakta_audit('case.note', 'case', $id, $cases->caseLabel($companyId, $id));
            echo json_encode(['success' => (bool) $noteId, 'message' => $noteId ? 'Белешката е додадена.' : 'Предметот не постои.', 'id' => $noteId]);
            break;
        }

        case 'update_note': {
            $noteId = (int) ($_POST['note_id'] ?? 0);
            $body   = trim($_POST['body'] ?? '');
            $type   = trim($_POST['type'] ?? 'general');
            if ($noteId <= 0 || $body === '') {
                echo json_encode(['success' => false, 'message' => 'Белешката не може да е празна.']);
                exit;
            }
            $ok = $cases->updateNote($companyId, $noteId, (int) (current_user()['id'] ?? 0), $body, $type);
            echo json_encode(['success' => $ok, 'message' => $ok ? 'Белешката е ажурирана.' : 'Можете да ги уредувате само вашите белешки.']);
            break;
        }

        case 'pin_note': {
            $noteId = (int) ($_POST['note_id'] ?? 0);
            $pinned = ($_POST['pinned'] ?? '0') === '1';
            if ($noteId <= 0) { echo json_encode(['success' => false, 'message' => 'Невалиден ID.']); exit; }
            $ok = $cases->pinNote($companyId, $noteId, $pinned);
            echo json_encode(['success' => $ok]);
            break;
        }

        case 'delete_note': {
            $noteId = (int) ($_POST['note_id'] ?? 0);
            if ($noteId <= 0) {
                echo json_encode(['success' => false, 'message' => 'Невалиден ID.']);
                exit;
            }
            $ok = $cases->deleteNote($companyId, $noteId, (int) (current_user()['id'] ?? 0), current_role() === 'admin');
            echo json_encode(['success' => $ok, 'message' => $ok ? 'Белешката е избришана.' : 'Немате дозвола да ја избришете оваа белешка.']);
            break;
        }

        case 'get_documents': {
            $id = (int) ($_GET['id'] ?? 0);
            echo json_encode(['success' => true, 'data' => $id > 0 ? $cases->getFiles($companyId, $id) : []]);
            break;
        }

        case 'upload_document': {
            $id = (int) ($_POST['id'] ?? 0);
            if ($id <= 0 || empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($_FILES['file']['tmp_name'])) {
                echo json_encode(['success' => false, 'message' => 'Датотеката не е прикачена правилно.']);
                exit;
            }
            $file = $_FILES['file'];
            if ($file['size'] > 25 * 1024 * 1024) {
                echo json_encode(['success' => false, 'message' => 'Датотеката е преголема (макс. 25MB).']);
                exit;
            }
            $allowed = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'rtf', 'odt', 'ods', 'csv', 'jpg', 'jpeg', 'png', 'gif', 'zip'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, $allowed, true)) {
                echo json_encode(['success' => false, 'message' => 'Недозволен тип на датотека.']);
                exit;
            }
            // Confirm the case belongs to the company before storing anything.
            if (!$cases->getById($companyId, $id)) {
                echo json_encode(['success' => false, 'message' => 'Предметот не постои.']);
                exit;
            }
            $dir = UPLOADS_DIR . '/cases/' . $companyId;
            if (!is_dir($dir) && !@mkdir($dir, 0775, true)) {
                echo json_encode(['success' => false, 'message' => 'Не може да се креира папка за прикачување.']);
                exit;
            }
            $rel = 'cases/' . $companyId . '/' . bin2hex(random_bytes(8)) . '.' . $ext;
            if (!move_uploaded_file($file['tmp_name'], UPLOADS_DIR . '/' . $rel)) {
                echo json_encode(['success' => false, 'message' => 'Не може да се зачува датотеката.']);
                exit;
            }
            $fid = $cases->addFile($companyId, $id, current_user()['id'] ?? null, $file['name'], $rel, $ext, (int) $file['size']);
            if (!$fid) {
                @unlink(UPLOADS_DIR . '/' . $rel);
                echo json_encode(['success' => false, 'message' => 'Грешка при зачувување.']);
                exit;
            }
            fakta_audit('case.document', 'case', $id, $cases->caseLabel($companyId, $id) . ' · ' . $file['name']);
            echo json_encode(['success' => true, 'message' => 'Документот е прикачен.', 'id' => $fid]);
            break;
        }

        case 'download_document': {
            $fid = (int) ($_GET['file_id'] ?? 0);
            $f = $fid > 0 ? $cases->getFile($companyId, $fid) : null;
            $abs = $f ? UPLOADS_DIR . '/' . $f['stored_rel'] : null;
            if (!$f || !$abs || !is_file($abs)) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Документот не е пронајден.']);
                exit;
            }
            $dlName = $f['orig_name'] !== '' ? $f['orig_name'] : ('document.' . ($f['ext'] ?: 'bin'));
            $ascii  = preg_replace('/[^\x20-\x7E]/', '_', $dlName);
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . str_replace('"', '', $ascii) . '"; '
                . "filename*=UTF-8''" . rawurlencode($dlName));
            header('Content-Length: ' . filesize($abs));
            header('Cache-Control: no-store');
            readfile($abs);
            exit;
        }

        case 'delete_document': {
            $fid = (int) ($_POST['file_id'] ?? 0);
            if ($fid <= 0) { echo json_encode(['success' => false, 'message' => 'Невалиден ID.']); exit; }
            $f = $cases->getFile($companyId, $fid);
            $ok = $f && $cases->deleteFile($companyId, $fid);
            if ($ok && !empty($f['stored_rel'])) {
                @unlink(UPLOADS_DIR . '/' . $f['stored_rel']);
            }
            echo json_encode(['success' => (bool) $ok, 'message' => $ok ? 'Документот е избришан.' : 'Документот не постои.']);
            break;
        }

        case 'get_hearings': {
            $id = (int) ($_GET['id'] ?? 0);
            echo json_encode(['success' => true, 'data' => $id > 0 ? $cases->getHearings($companyId, $id) : []]);
            break;
        }

        case 'calendar_feed': {
            // Calendar page feed: case events (рочишта/судења/состаноци) + personal
            // events, in a date window, optionally filtered to one employee.
            // For case events the filter is the case assignee; for personal events
            // it's the owner.
            $from = trim($_GET['from'] ?? '');
            $to   = trim($_GET['to'] ?? '');
            $aid  = (int) ($_GET['assignee_id'] ?? 0);
            $uid  = (int) (current_user()['id'] ?? 0);

            $events = [];
            foreach ($cases->getCalendarEvents($companyId, $from, $to, $aid) as $e) {
                $e['source']   = 'case';
                $e['start']    = $e['hearing_at'];
                $e['can_edit'] = false;
                $events[] = $e;
            }
            foreach ($calendar->getForRange($companyId, $from, $to, $aid) as $e) {
                $e['source']      = 'personal';
                $e['start']       = $e['starts_at'];
                $e['can_edit']    = ((int) $e['user_id'] === $uid);
                $e['creator_name'] = $e['owner_name'];
                $events[] = $e;
            }
            usort($events, fn($a, $b) => strcmp($a['start'], $b['start']));
            echo json_encode(['success' => true, 'data' => $events]);
            break;
        }

        case 'add_event': {
            // Personal calendar event — always owned by the current user.
            $title = trim($_POST['title'] ?? '');
            $at    = trim($_POST['starts_at'] ?? '');
            $kind  = trim($_POST['kind'] ?? 'meeting');
            if ($title === '' || $at === '') {
                echo json_encode(['success' => false, 'message' => 'Внеси наслов и датум/време.']);
                exit;
            }
            $uid = (int) (current_user()['id'] ?? 0);
            $id  = $calendar->add($companyId, $uid, $title, $at, $kind, trim($_POST['location'] ?? ''), trim($_POST['note'] ?? ''));
            echo json_encode(['success' => (bool) $id, 'message' => $id ? 'Настанот е додаден.' : 'Невалидни податоци.', 'id' => $id]);
            break;
        }

        case 'update_event': {
            $eid   = (int) ($_POST['event_id'] ?? 0);
            $title = trim($_POST['title'] ?? '');
            $at    = trim($_POST['starts_at'] ?? '');
            $kind  = trim($_POST['kind'] ?? 'meeting');
            if ($eid <= 0 || $title === '' || $at === '') {
                echo json_encode(['success' => false, 'message' => 'Внеси наслов и датум/време.']);
                exit;
            }
            $uid = (int) (current_user()['id'] ?? 0);
            $ok  = $calendar->update($companyId, $eid, $uid, $title, $at, $kind, trim($_POST['location'] ?? ''), trim($_POST['note'] ?? ''));
            echo json_encode(['success' => $ok, 'message' => $ok ? 'Настанот е ажуриран.' : 'Можете да уредувате само свои настани.']);
            break;
        }

        case 'delete_event': {
            $eid = (int) ($_POST['event_id'] ?? 0);
            if ($eid <= 0) { echo json_encode(['success' => false, 'message' => 'Невалиден ID.']); exit; }
            $uid = (int) (current_user()['id'] ?? 0);
            $ok  = $calendar->delete($companyId, $eid, $uid);
            echo json_encode(['success' => $ok, 'message' => $ok ? 'Настанот е избришан.' : 'Можете да бришете само свои настани.']);
            break;
        }

        case 'add_hearing': {
            $id    = (int) ($_POST['id'] ?? 0);
            $title = trim($_POST['title'] ?? '');
            $at    = trim($_POST['hearing_at'] ?? '');
            $kind  = trim($_POST['kind'] ?? 'hearing');
            if ($id <= 0 || $title === '' || $at === '') {
                echo json_encode(['success' => false, 'message' => 'Внеси наслов и датум/време.']);
                exit;
            }
            $hid = $cases->addHearing($companyId, $id, current_user()['id'] ?? null, $title, $at, trim($_POST['location'] ?? ''), trim($_POST['note'] ?? ''), $kind);
            if ($hid) fakta_audit('case.hearing', 'case', $id, $cases->caseLabel($companyId, $id) . ' · ' . hearing_kind_label($kind) . ': ' . $title);
            echo json_encode(['success' => (bool) $hid, 'message' => $hid ? 'Настанот е додаден.' : 'Невалидни податоци.', 'id' => $hid]);
            break;
        }

        case 'update_hearing': {
            $hid   = (int) ($_POST['hearing_id'] ?? 0);
            $title = trim($_POST['title'] ?? '');
            $at    = trim($_POST['hearing_at'] ?? '');
            $kind  = trim($_POST['kind'] ?? 'hearing');
            if ($hid <= 0 || $title === '' || $at === '') {
                echo json_encode(['success' => false, 'message' => 'Внеси наслов и датум/време.']);
                exit;
            }
            $ok = $cases->updateHearing($companyId, $hid, (int) (current_user()['id'] ?? 0), current_role() === 'admin', $title, $at, trim($_POST['location'] ?? ''), trim($_POST['note'] ?? ''), $kind);
            echo json_encode(['success' => $ok, 'message' => $ok ? 'Настанот е ажуриран.' : 'Немате дозвола или невалидни податоци.']);
            break;
        }

        case 'delete_hearing': {
            $hid = (int) ($_POST['hearing_id'] ?? 0);
            if ($hid <= 0) { echo json_encode(['success' => false, 'message' => 'Невалиден ID.']); exit; }
            $ok = $cases->deleteHearing($companyId, $hid, (int) (current_user()['id'] ?? 0), current_role() === 'admin');
            echo json_encode(['success' => $ok, 'message' => $ok ? 'Настанот е избришан.' : 'Немате дозвола за овој настан.']);
            break;
        }

        case 'get_todos': {
            $id = (int) ($_GET['id'] ?? 0);
            echo json_encode(['success' => true, 'data' => $id > 0 ? $cases->getTodos($companyId, $id) : []]);
            break;
        }

        case 'add_todo': {
            $id    = (int) ($_POST['id'] ?? 0);
            $title = trim($_POST['title'] ?? '');
            $due   = trim($_POST['due_date'] ?? '');
            $asg   = (int) ($_POST['assigned_to'] ?? 0);
            if ($id <= 0 || $title === '') {
                echo json_encode(['success' => false, 'message' => 'Внеси задача.']);
                exit;
            }
            $todoId = $cases->addTodo($companyId, $id, current_user()['id'] ?? null, $title, $due ?: null, $asg ?: null);
            if ($todoId) fakta_audit('case.todo', 'case', $id, $cases->caseLabel($companyId, $id));
            echo json_encode(['success' => (bool) $todoId, 'message' => $todoId ? 'Задачата е додадена.' : 'Предметот не постои.', 'id' => $todoId]);
            break;
        }

        case 'set_todo_status': {
            $todoId = (int) ($_POST['todo_id'] ?? 0);
            $status = trim($_POST['status'] ?? '');
            if ($todoId <= 0 || $status === '') { echo json_encode(['success' => false, 'message' => 'Невалиден статус.']); exit; }
            $ok = $cases->setTodoStatus($companyId, $todoId, $status);
            echo json_encode(['success' => $ok, 'message' => $ok ? '' : 'Невалиден статус.']);
            break;
        }

        case 'update_todo': {
            $todoId = (int) ($_POST['todo_id'] ?? 0);
            $title  = trim($_POST['title'] ?? '');
            $due    = trim($_POST['due_date'] ?? '');
            $asg    = (int) ($_POST['assigned_to'] ?? 0);
            if ($todoId <= 0 || $title === '') { echo json_encode(['success' => false, 'message' => 'Внеси задача.']); exit; }
            $ok = $cases->updateTodo($companyId, $todoId, (int) (current_user()['id'] ?? 0), current_role() === 'admin', $title, $due ?: null, $asg ?: null);
            echo json_encode(['success' => $ok, 'message' => $ok ? 'Задачата е ажурирана.' : 'Можете да уредувате само ваши задачи.']);
            break;
        }

        case 'delete_todo': {
            $todoId = (int) ($_POST['todo_id'] ?? 0);
            if ($todoId <= 0) { echo json_encode(['success' => false, 'message' => 'Невалиден ID.']); exit; }
            $ok = $cases->deleteTodo($companyId, $todoId, (int) (current_user()['id'] ?? 0), current_role() === 'admin');
            echo json_encode(['success' => $ok, 'message' => $ok ? 'Задачата е избришана.' : 'Немате дозвола за оваа задача.']);
            break;
        }

        case 'suggest_basis': {
            $term = trim($_GET['q'] ?? '');
            echo json_encode(['success' => true, 'data' => $cases->suggestBasis($companyId, $term)]);
            break;
        }

        case 'suggest_official': {
            $term = trim($_GET['q'] ?? '');
            echo json_encode(['success' => true, 'data' => $cases->suggestOfficial($companyId, $term)]);
            break;
        }

        case 'csv_validate': {
            if (empty($_FILES['file']['tmp_name']) || !is_uploaded_file($_FILES['file']['tmp_name'])) {
                echo json_encode(['success' => false, 'message' => 'Прикачи CSV датотека.']);
                exit;
            }
            $csv = (string) file_get_contents($_FILES['file']['tmp_name']);
            $importer = new CaseImporter($db, $cases);
            echo json_encode(['success' => true] + $importer->prepare($companyId, $csv));
            break;
        }

        case 'csv_import': {
            if (empty($_FILES['file']['tmp_name']) || !is_uploaded_file($_FILES['file']['tmp_name'])) {
                echo json_encode(['success' => false, 'message' => 'Прикачи CSV датотека.']);
                exit;
            }
            $csv = (string) file_get_contents($_FILES['file']['tmp_name']);
            $importer = new CaseImporter($db, $cases);
            $res = $importer->import($companyId, $csv, current_user()['id'] ?? null);
            if (isset($res['fatal'])) {
                echo json_encode(['success' => false, 'message' => $res['fatal']]);
                exit;
            }
            if (($res['imported'] ?? 0) > 0) {
                fakta_audit('case.import', 'case', null, $res['imported'] . ' предмети импортирани од CSV');
            }
            echo json_encode(['success' => true] + $res);
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

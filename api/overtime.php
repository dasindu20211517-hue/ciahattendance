<?php
require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../services/notification_service.php';
require_once __DIR__ . '/../auth.php';
header('Content-Type: application/json');
requireLoginApi([1, 2]);
$action = $_GET['action'] ?? $_POST['action'] ?? 'my';
$userId = (int)currentUser()['id'];

try {
    $db = getDB();
    switch ($action) {
        case 'my':
            $year = (int)($_GET['year'] ?? date('Y'));
            $month = (int)($_GET['month'] ?? date('m'));
            echo json_encode(['success' => true, 'data' => getUserOT($db, $userId, $year, $month)]);
            break;
        case 'pending':
            $status = $_GET['status'] ?? 'pending';
            if (!in_array($status, ['all', 'pending', 'approved', 'rejected'], true)) $status = 'pending';
            $ots = getScopedPendingOT($db, $status, $userId, isAdminUser());
            $ots = filterRequestRows($ots);
            echo json_encode(['success' => true, 'data' => $ots]);
            break;
        case 'summary':
            $year = (int)($_GET['year'] ?? date('Y'));
            $month = (int)($_GET['month'] ?? date('m'));
            echo json_encode(['success' => true, 'data' => getOTSummary($db, $year, $month, $userId, isAdminUser())]);
            break;
        case 'submit':
            $otDate = $_POST['ot_date'] ?? '';
            $hours = (float)($_POST['hours'] ?? 0);
            $reason = $_POST['reason'] ?? '';
            if (!$otDate || $hours <= 0) throw new Exception('Missing required fields');
            if (isHoliday($db, $otDate)) { /* OT on holiday is fine */ }
            $id = createOT($db, $userId, $otDate, $hours, $reason);
            notifyOTRequest($userId);
            echo json_encode(['success' => true, 'id' => $id]);
            break;
        case 'approve':
            $oid = (int)($_POST['ot_id'] ?? 0);
            $target = $db->query("SELECT user_id FROM overtime WHERE id=" . $oid)->fetchColumn();
            if (!$target || !canManageEmployee($db, $target)) throw new Exception('You cannot manage this employee.');
            if (!approveOT($db, $oid, $userId)) throw new Exception('This OT request is no longer pending.');
            $ot = $db->query("SELECT user_id FROM overtime WHERE id=$oid")->fetch(PDO::FETCH_ASSOC);
            if ($ot) notifyOTStatus($ot['user_id'], 'approved');
            echo json_encode(['success' => true]);
            break;
        case 'reject':
            $oid = (int)($_POST['ot_id'] ?? 0);
            $target = $db->query("SELECT user_id FROM overtime WHERE id=" . $oid)->fetchColumn();
            if (!$target || !canManageEmployee($db, $target)) throw new Exception('You cannot manage this employee.');
            $reason = $_POST['rejection_reason'] ?? '';
            rejectOT($db, $oid, $userId, $reason);
            $ot = $db->query("SELECT user_id FROM overtime WHERE id=$oid")->fetch(PDO::FETCH_ASSOC);
            if ($ot) notifyOTStatus($ot['user_id'], 'rejected');
            echo json_encode(['success' => true]);
            break;
        case 'request_employees':
            $employees = $db->query("SELECT DISTINCT u.badge_id, u.employee_id, u.name, u.calling_name, u.company_name, u.department_name FROM users u INNER JOIN overtime o ON o.user_id = u.id WHERE u.employee_list_member = 1 ORDER BY u.name")->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'users' => $employees]);
            break;
        case 'clear_all':
            if (!isAdminUser()) throw new Exception('Admin only.');
            $db->exec("DELETE FROM form_submissions WHERE form_type = 'overtime'");
            $db->exec("DELETE FROM form_links WHERE form_type = 'overtime'");
            $db->exec("DELETE FROM overtime");
            echo json_encode(['success' => true, 'message' => 'All overtime requests and overtime form links cleared.']);
            break;
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

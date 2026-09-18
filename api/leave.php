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
            echo json_encode(['success' => true, 'data' => getUserLeaves($db, $userId, $year), 'balances' => getAllLeaveBalances($db, $userId, $year)]);
            break;
        case 'pending':
            $status = $_GET['status'] ?? 'pending';
            if (!in_array($status, ['all', 'pending', 'approved', 'rejected', 'cancelled'], true)) $status = 'pending';
            $leaves = getScopedPendingLeaves($db, $status, $userId, isAdminUser());
            $leaves = filterRequestRows($leaves);
            echo json_encode(['success' => true, 'data' => $leaves]);
            break;
        case 'summary':
            $year = (int)($_GET['year'] ?? date('Y'));
            echo json_encode(['success' => true, 'data' => getLeaveSummary($db, $year, $userId, isAdminUser())]);
            break;
        case 'submit':
            $lt = $_POST['leave_type'] ?? '';
            $sd = $_POST['start_date'] ?? '';
            $ed = $_POST['end_date'] ?? '';
            $reason = $_POST['reason'] ?? '';
            if (!$lt || !$sd || !$ed) throw new Exception('Missing required fields');
            if (hasOverlappingLeave($db, $userId, $sd, $ed)) throw new Exception('Overlapping leave request exists');
            $id = createLeave($db, $userId, $lt, $sd, $ed, $reason);
            notifyLeaveRequest($userId);
            echo json_encode(['success' => true, 'id' => $id]);
            break;
        case 'approve':
            $lid = (int)($_POST['leave_id'] ?? 0);
            $target = $db->query("SELECT user_id FROM leaves WHERE id=" . $lid)->fetchColumn();
            if (!$target || !canManageEmployee($db, $target)) throw new Exception('You cannot manage this employee.');
            if (!approveLeave($db, $lid, $userId)) throw new Exception('This leave request is no longer pending.');
            $leave = $db->query("SELECT user_id, leave_type FROM leaves WHERE id=$lid")->fetch(PDO::FETCH_ASSOC);
            if ($leave) notifyLeaveStatus($leave['user_id'], 'approved', $leave['leave_type']);
            echo json_encode(['success' => true]);
            break;
        case 'reject':
            $lid = (int)($_POST['leave_id'] ?? 0);
            $target = $db->query("SELECT user_id FROM leaves WHERE id=" . $lid)->fetchColumn();
            if (!$target || !canManageEmployee($db, $target)) throw new Exception('You cannot manage this employee.');
            $reason = $_POST['rejection_reason'] ?? '';
            rejectLeave($db, $lid, $userId, $reason);
            $leave = $db->query("SELECT user_id, leave_type FROM leaves WHERE id=$lid")->fetch(PDO::FETCH_ASSOC);
            if ($leave) notifyLeaveStatus($leave['user_id'], 'rejected', $leave['leave_type']);
            echo json_encode(['success' => true]);
            break;
        case 'cancel':
            cancelLeave($db, (int)($_POST['leave_id'] ?? 0), $userId);
            echo json_encode(['success' => true]);
            break;
        case 'clear_all':
            if (!isAdminUser()) throw new Exception('Admin only.');
            $db->exec("DELETE FROM form_submissions WHERE form_type = 'leave'");
            $db->exec("DELETE FROM form_links WHERE form_type = 'leave'");
            $db->exec("DELETE FROM leaves");
            $db->exec("DELETE FROM leave_balances");
            echo json_encode(['success' => true, 'message' => 'All leave requests and leave form links cleared.']);
            break;
        case 'request_employees':
            $employees = $db->query("SELECT DISTINCT u.badge_id, u.employee_id, u.name, u.calling_name, u.company_name, u.department_name FROM users u INNER JOIN leaves l ON l.user_id = u.id WHERE u.employee_list_member = 1 ORDER BY u.name")->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'users' => $employees]);
            break;
        case 'balance':
            $lt = $_GET['leave_type'] ?? 'annual';
            $yr = (int)($_GET['year'] ?? date('Y'));
            echo json_encode(['success' => true, 'data' => getLeaveBalance($db, $userId, $lt, $yr)]);
            break;
        case 'tracker':
            $year = (int)($_GET['year'] ?? date('Y'));
            $targetUserId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : $userId;
            if ($targetUserId !== $userId && !isAdminUser()) {
                throw new Exception('You can only view your own leave tracker.');
            }
            $records = $db->prepare("SELECT * FROM leaves WHERE user_id = ? AND (strftime('%Y', start_date) = ? OR strftime('%Y', end_date) = ?) ORDER BY start_date DESC");
            $records->execute([$targetUserId, $year, $year]);
            $balances = getAllLeaveBalances($db, $targetUserId, $year);
            echo json_encode([
                'success' => true,
                'records' => $records->fetchAll(PDO::FETCH_ASSOC),
                'balances' => $balances,
                'user_id' => $targetUserId,
                'year' => $year
            ]);
            break;
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

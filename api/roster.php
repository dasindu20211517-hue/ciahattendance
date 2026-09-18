<?php
require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../auth.php';
header('Content-Type: application/json');
requireLoginApi([1, 2]);
$action = $_GET['action'] ?? $_POST['action'] ?? 'list';
$userId = (int)currentUser()['id'];
$requestedUserId = (int)($_GET['user_id'] ?? $_POST['user_id'] ?? 0);

try {
    $db = getDB();
    switch ($action) {
        case 'list':
            $year = (int)($_GET['year'] ?? date('Y'));
            $month = (int)($_GET['month'] ?? date('m'));
            $ym = sprintf('%04d-%02d', $year, $month);
            $users = getScopedUsers($db, $userId, isAdminUser());
            $result = [];
            foreach ($users as $u) {
                $roster = getUserRoster($db, $u['id'], "$ym-15");
                if (!$roster) continue;
                $days = getRosterDays($db, $roster['id']);
                $overrides = $db->prepare("SELECT * FROM roster_overrides WHERE roster_id=? AND override_date LIKE ?");
                $overrides->execute([$roster['id'], $ym . '%']);
                $ovs = $overrides->fetchAll(PDO::FETCH_ASSOC);
                $result[] = ['user_id' => $u['id'], 'name' => $u['name'], 'roster_id' => $roster['id'], 'effective_from' => $roster['effective_from'], 'effective_to' => $roster['effective_to'], 'days' => $days, 'overrides' => $ovs];
            }
            echo json_encode(['success' => true, 'data' => $result]);
            break;
        case 'user':
            $date = $_GET['date'] ?? date('Y-m-d');
            if (!$requestedUserId || !isAdminUser() && !canManageEmployee($db, $requestedUserId)) throw new Exception('You cannot view this employee roster.');
            $info = getShiftForDate($db, $requestedUserId, $date);
            echo json_encode(['success' => true, 'data' => $info]);
            break;
        case 'create':
            $data = json_decode($_POST['data'] ?? '{}', true);
            if (!isAdminUser() && !canManageEmployee($db, $data['user_id'] ?? 0)) throw new Exception('You cannot manage this employee roster.');
            $rid = createRoster($db, $data['user_id'], $data['effective_from'], $data['effective_to'] ?? null, $userId, $data['days'] ?? []);
            echo json_encode(['success' => true, 'id' => $rid]);
            break;
        case 'update_days':
            $rosterId = (int)($_POST['roster_id'] ?? 0);
            $target = $db->query("SELECT user_id FROM rosters WHERE id=" . $rosterId)->fetchColumn();
            if (!$target || !canManageEmployee($db, $target)) throw new Exception('You cannot manage this employee roster.');
            $days = json_decode($_POST['days'] ?? '[]', true);
            updateRosterDays($db, $rosterId, $days);
            echo json_encode(['success' => true]);
            break;
        case 'override':
            $rosterId = (int)($_POST['roster_id'] ?? 0);
            $target = $db->query("SELECT user_id FROM rosters WHERE id=" . $rosterId)->fetchColumn();
            if (!$target || !canManageEmployee($db, $target)) throw new Exception('You cannot manage this employee roster.');
            setRosterOverride($db, $rosterId, $_POST['date'], $_POST['shift_id'] ?? null, (int)($_POST['is_rest_day'] ?? 0));
            echo json_encode(['success' => true]);
            break;
        case 'delete':
            $rosterId = (int)($_POST['roster_id'] ?? 0);
            $target = $db->query("SELECT user_id FROM rosters WHERE id=" . $rosterId)->fetchColumn();
            if (!$target || !canManageEmployee($db, $target)) throw new Exception('You cannot manage this employee roster.');
            deleteRoster($db, $rosterId);
            echo json_encode(['success' => true]);
            break;
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

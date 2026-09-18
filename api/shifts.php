<?php
require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../auth.php';
header('Content-Type: application/json');
requireLoginApi([2]);
$action = $_GET['action'] ?? $_POST['action'] ?? 'list';

try {
    $db = getDB();
    switch ($action) {
        case 'list':
            echo json_encode(['success' => true, 'shifts' => getAllShifts($db)]);
            break;
        case 'active':
            echo json_encode(['success' => true, 'shifts' => getActiveShifts($db)]);
            break;
        case 'create':
            $name = $_POST['name'] ?? '';
            $start = $_POST['start_time'] ?? '';
            $end = $_POST['end_time'] ?? '';
            $rostered = (int)($_POST['is_rostered'] ?? 0);
            if (!$name || !$start || !$end) throw new Exception('Missing required fields');
            $id = createShift($db, $name, $start, $end, $rostered);
            echo json_encode(['success' => true, 'id' => $id]);
            break;
        case 'update':
            $id = (int)($_POST['id'] ?? 0);
            updateShift($db, $id, $_POST['name'], $_POST['start_time'], $_POST['end_time'], (int)$_POST['is_rostered'], (int)$_POST['is_active']);
            echo json_encode(['success' => true]);
            break;
        case 'delete':
            deleteShift($db, (int)($_POST['id'] ?? 0));
            echo json_encode(['success' => true]);
            break;
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

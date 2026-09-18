<?php require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../auth.php';
header('Content-Type: application/json');
requireLoginApi([2]);
$action = $_GET['action'] ?? 'list';

try {
    $db = getDB();
    switch ($action) {
        case 'list':
            $type = $_GET['type'] ?? '';
            $limit = (int)($_GET['limit'] ?? 100);
            if ($type) {
                $stmt = $db->prepare("SELECT n.*, u.name as user_name FROM notifications n LEFT JOIN users u ON n.user_id = u.id WHERE n.type = ? ORDER BY n.created_at DESC LIMIT ?");
                $stmt->execute([$type, $limit]);
            } else {
                $stmt = $db->prepare("SELECT n.*, u.name as user_name FROM notifications n LEFT JOIN users u ON n.user_id = u.id ORDER BY n.created_at DESC LIMIT ?");
                $stmt->execute([$limit]);
            }
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

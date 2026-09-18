<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../database.php';

try {
    requireLoginApi([2]);
    $db = getDB();
    $action = $_GET['action'] ?? ($_POST['action'] ?? '');

    if ($action === 'list') {
        $stmt = $db->query("SELECT id, badge_id, employee_id, COALESCE(NULLIF(calling_name,''), name) as name, whatsapp_number, department_name, company_name FROM users WHERE employee_list_member = 1 ORDER BY name");
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        exit;
    }

    if ($action === 'save') {
        $input = json_decode(file_get_contents('php://input'), true);
        $userId = (int)($input['user_id'] ?? 0);
        $whatsappNumber = trim($input['whatsapp_number'] ?? '');
        if (!$userId) throw new Exception('Invalid employee');
        
        // Basic phone number validation (allow digits, +, -, spaces, parentheses)
        if ($whatsappNumber !== '' && !preg_match('/^[0-9+\-\s()]+$/', $whatsappNumber)) {
            throw new Exception('Invalid phone number format');
        }
        
        $stmt = $db->prepare("UPDATE users SET whatsapp_number = ? WHERE id = ?");
        $stmt->execute([$whatsappNumber, $userId]);
        echo json_encode(['success' => true, 'message' => 'WhatsApp number updated']);
        exit;
    }

    if ($action === 'save_bulk') {
        $input = json_decode(file_get_contents('php://input'), true);
        $entries = $input['entries'] ?? [];
        $stmt = $db->prepare("UPDATE users SET whatsapp_number = ? WHERE id = ?");
        $count = 0;
        foreach ($entries as $entry) {
            $uid = (int)($entry['user_id'] ?? 0);
            $num = trim($entry['whatsapp_number'] ?? '');
            if ($uid > 0) {
                $stmt->execute([$num, $uid]);
                $count++;
            }
        }
        echo json_encode(['success' => true, 'message' => "Updated {$count} employees"]);
        exit;
    }

    throw new Exception('Unknown action');

} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

<?php
require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../config_whatsapp.php';

// SMTP/Email functions removed - using WhatsApp integration only

function sendWhatsAppMessage($userId, $message) {
    $db = getDB();
    $user = $db->query("SELECT whatsapp_number, COALESCE(NULLIF(calling_name,''), name) as name FROM users WHERE id=" . (int)$userId)->fetch(PDO::FETCH_ASSOC);
    
    if (!$user || !$user['whatsapp_number']) {
        logNotification($db, $userId, 'general', 'whatsapp', 'WhatsApp Message', $message, 'failed - no number');
        return false;
    }
    
    $formattedNumber = formatWhatsAppNumber($user['whatsapp_number']);
    
    // Try OpenWA first, fall back to SMS gateway
    $success = sendWhatsAppViaOpenWA($formattedNumber, $message);
    
    if (!$success) {
        // Fallback to SMS gateway
        $params = http_build_query([
            'user_id'  => NOTIFY_USER_ID,
            'api_key'  => NOTIFY_API_KEY,
            'sender_id'=> NOTIFY_SENDER_ID,
            'to'       => $user['whatsapp_number'],
            'message'  => $message,
        ]);
        $url = 'https://app.notify.lk/api/v1/send?' . $params;

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 10,
                CURLOPT_SSL_VERIFYPEER => false,
            ]);
            $response = curl_exec($ch);
            $err = curl_error($ch);
            curl_close($ch);
            if ($err) error_log('[WhatsApp] SMS fallback error: ' . $err);
            else error_log('[WhatsApp] SMS fallback response: ' . $response);
        }
    }
    
    logNotification($db, $userId, 'general', 'whatsapp', 'WhatsApp Message', $message, $success ? 'sent' : 'queued');
    return true;
}

function sendSMS($userId, $message) {
    // Redirect to WhatsApp messaging
    return sendWhatsAppMessage($userId, $message);
}

// Email functions removed - using WhatsApp integration only

function notifyLeaveRequest($employeeId) {
    $db = getDB();
    $emp = $db->query("SELECT name FROM users WHERE id=" . (int)$employeeId)->fetch(PDO::FETCH_ASSOC);
    $dept = getUserDepartment($db, $employeeId);
    if (!$dept) return;
    $hod = getHODForDepartment($db, $dept['id']);
    if (!$hod) return;
    $name = $emp['name'] ?? 'Unknown';
    $msg = "[HRIS ROWELMARK GROUP]\n\nNew Leave Request\nEmployee: {$name}\nDept: {$dept['name']}\n\nPlease review and approve/reject from the Leave module.";
    sendWhatsAppMessage($hod['id'], $msg);
}

function notifyLeaveStatus($employeeId, $status, $leaveType) {
    $db = getDB();
    $emp = $db->query("SELECT COALESCE(NULLIF(calling_name,''), name) as name FROM users WHERE id=" . (int)$employeeId)->fetch(PDO::FETCH_ASSOC);
    $name = $emp['name'] ?? 'Employee';
    $statusText = ucfirst($status);
    $emoji = $status === 'approved' ? '✅ Approved' : ($status === 'rejected' ? '❌ Rejected' : $statusText);
    $msg = "[HRIS ROWELMARK GROUP]\n\nLeave {$statusText}\nType: {$leaveType}\nStatus: {$emoji}\n\nContact your supervisor for details.";
    sendWhatsAppMessage($employeeId, $msg);
}

function notifyOTRequest($employeeId) {
    $db = getDB();
    $emp = $db->query("SELECT name FROM users WHERE id=" . (int)$employeeId)->fetch(PDO::FETCH_ASSOC);
    $dept = getUserDepartment($db, $employeeId);
    if (!$dept) return;
    $hod = getHODForDepartment($db, $dept['id']);
    if (!$hod) return;
    $name = $emp['name'] ?? 'Unknown';
    $msg = "[HRIS ROWELMARK GROUP]\n\nNew Overtime Request\nEmployee: {$name}\nDept: {$dept['name']}\n\nPlease review and approve/reject from the Overtime module.";
    sendWhatsAppMessage($hod['id'], $msg);
}

function notifyOTStatus($employeeId, $status) {
    $db = getDB();
    $emp = $db->query("SELECT COALESCE(NULLIF(calling_name,''), name) as name FROM users WHERE id=" . (int)$employeeId)->fetch(PDO::FETCH_ASSOC);
    $name = $emp['name'] ?? 'Employee';
    $statusText = ucfirst($status);
    $emoji = $status === 'approved' ? '✅ Approved' : ($status === 'rejected' ? '❌ Rejected' : $statusText);
    $msg = "[HRIS ROWELMARK GROUP]\n\nOvertime {$statusText}\nStatus: {$emoji}\nYour overtime request has been {$status}.\n\nContact your supervisor for details.";
    sendWhatsAppMessage($employeeId, $msg);
}

// WhatsApp-only notification system - email functions removed
// Form links are now automatically sent via WhatsApp in the API endpoints

function sendFormLinkNotification($userId, $formType, $url) {
    // This function is deprecated - form links are now automatically sent via WhatsApp
    // See api/form_links.php and api/form_links_mgmt.php for WhatsApp integration
    error_log('[Deprecated] sendFormLinkNotification called - use API endpoints for WhatsApp integration');
    return false;
}

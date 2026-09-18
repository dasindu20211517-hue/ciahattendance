<?php
/**
 * Cron Job Version - Daily Attendance Report
 * This version is optimized for automated execution
 */

// Set error reporting for cron jobs
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/daily_report_errors.log');

// Create logs directory if it doesn't exist
if (!is_dir(__DIR__ . '/logs')) {
    mkdir(__DIR__ . '/logs', 0755, true);
}

// Log start
$logFile = __DIR__ . '/logs/daily_report.log';
$startTime = date('Y-m-d H:i:s');
file_put_contents($logFile, "[$startTime] Starting daily report process\n", FILE_APPEND | LOCK_EX);

try {
    require_once 'database.php';
    require_once 'config_whatsapp.php';
    require_once 'whatsapp_web_sender.php';
    
    // Configuration
    $DASINDU_USER_ID = 141;
    $TARGET_WHATSAPP = '+94783788180';
    $REPORT_DATE = date('Y-m-d');
    
    $db = getDB();
    
    // Get user info
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$DASINDU_USER_ID]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        throw new Exception("User not found");
    }
    
    // Get attendance data
    $nextDate = (new DateTime($REPORT_DATE))->modify('+1 day')->format('Y-m-d');
    
    $stmt = $db->prepare("
        SELECT 
            MIN(CASE WHEN state IN (0, 2, 4) THEN check_time END) as first_checkin,
            MAX(CASE WHEN state IN (1, 3, 5) THEN check_time END) as last_checkout,
            COUNT(*) as total_records
        FROM attendance 
        WHERE user_id = ? AND check_time >= ? AND check_time < ?
    ");
    $stmt->execute([$DASINDU_USER_ID, $REPORT_DATE, $nextDate]);
    $attendance = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Format times
    $firstIn = $attendance['first_checkin'] ? formatAttendanceTime($attendance['first_checkin']) : null;
    $lastOut = $attendance['last_checkout'] ? formatAttendanceTime($attendance['last_checkout']) : null;
    $totalHours = calculateTotalHours($attendance['first_checkin'], $attendance['last_checkout']);
    $status = getAttendanceStatus($attendance['first_checkin'], $attendance['last_checkout']);
    $isLate = $attendance['first_checkin'] ? isLateForUserOnDate($db, $DASINDU_USER_ID, $REPORT_DATE, $attendance['first_checkin']) : false;
    
    // Build message
    $message = "📋 *DAILY ATTENDANCE REPORT*\n";
    $message .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
    $message .= "👤 *Employee:* {$user['calling_name']}\n";
    $message .= "🏷️ *Badge:* {$user['badge_id']}\n";
    $message .= "🏢 *Department:* {$user['department_name']}\n";
    $message .= "📅 *Date:* " . date('l, F j, Y', strtotime($REPORT_DATE)) . "\n\n";
    
    if ($attendance['total_records'] > 0) {
        $message .= "⏰ *ATTENDANCE SUMMARY*\n";
        $message .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        $message .= "🕐 *Check-in:* " . ($firstIn ?: 'None') . "\n";
        $message .= "🕐 *Check-out:* " . ($lastOut ?: 'None') . "\n";
        $message .= "⏱️ *Hours:* $totalHours\n";
        $message .= "📊 *Status:* $status\n";
        $message .= "⚠️ *Late:* " . ($isLate ? 'Yes ⚠️' : 'No ✅') . "\n";
    } else {
        $message .= "❌ *NO ATTENDANCE RECORDED*\n";
    }
    
    $message .= "\n🤖 _Automated Report • " . date('H:i') . "_";
    
    // Try to send (API first, then web fallback)
    $success = false;
    
    // Try OpenWA API first (silent)
    if (function_exists('curl_init')) {
        $result = @sendWhatsAppViaOpenWA($TARGET_WHATSAPP, $message);
        if ($result) {
            $success = true;
            file_put_contents($logFile, "[$startTime] Message sent via OpenWA API\n", FILE_APPEND | LOCK_EX);
        }
    }
    
    // If API fails, use web method
    if (!$success) {
        sendWhatsAppViaWeb($TARGET_WHATSAPP, $message);
        $success = true;
        file_put_contents($logFile, "[$startTime] Message sent via Web interface\n", FILE_APPEND | LOCK_EX);
    }
    
    // Log notification
    $stmt = $db->prepare("INSERT INTO notifications (user_id, type, channel, subject, message, status) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $DASINDU_USER_ID,
        'daily_report',
        'whatsapp',
        "Daily Report - $REPORT_DATE",
        $message,
        'sent'
    ]);
    
    file_put_contents($logFile, "[$startTime] SUCCESS: Daily report completed\n", FILE_APPEND | LOCK_EX);
    echo "SUCCESS: Daily report sent\n";
    
} catch (Exception $e) {
    $error = "ERROR: " . $e->getMessage();
    file_put_contents($logFile, "[$startTime] $error\n", FILE_APPEND | LOCK_EX);
    echo "$error\n";
}

$endTime = date('Y-m-d H:i:s');
file_put_contents($logFile, "[$endTime] Daily report process completed\n\n", FILE_APPEND | LOCK_EX);
?>